<?php

declare(strict_types=1);

namespace App\Services\Rag;

use App\Models\AiUsage;
use App\Models\Challenge;
use App\Models\Document;
use App\Models\Post;
use App\Models\Project;
use App\Services\Embedding\EmbeddingService;
use App\Services\Llm\LlmClient;
use App\Services\Vector\VectorStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Retrieval-augmented generation over the DevJourney content indexed in the
 * vector store. Embeds the question, retrieves the most relevant chunks and asks
 * the LLM to answer strictly from that context. If the question is not relevant
 * to the site content it refuses instead of hallucinating.
 */
class RagService
{
    private string $collection;

    private int $topK;

    private float $minSimilarity;

    private int $contextChunks;

    private int $maxInputTokens;

    public function __construct(
        private readonly VectorStore $store,
        private readonly EmbeddingService $embedding,
        private readonly LlmClient $llm,
        private readonly Chunker $chunker,
    ) {
        $this->collection = (string) config('rag.qdrant.collection', 'content');
        $this->topK = (int) config('rag.retrieval.top_k', 5);
        $this->minSimilarity = (float) config('rag.retrieval.min_similarity', 0.35);
        $this->contextChunks = (int) config('rag.limits.max_context_chunks', 5);
        $this->maxInputTokens = (int) config('rag.limits.max_input_tokens', 2000);
    }

    /**
     * @return array{answer: string|null, refused: bool, sources: list<array<string, mixed>>, tokens_in: int, tokens_out: int, context_chunks: int, model: string}
     */
    public function ask(string $question, ?string $ip = null, ?int $userId = null): array
    {
        $question = trim($question);

        $retrieved = $this->retrieve($question);
        if ($retrieved['refused']) {
            $this->recordUsage($ip, $userId, $question, refused: true);

            return $this->emptyResult();
        }

        $result = $this->llm->complete(
            [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $question."\n\nUse only the context below:\n\n".$retrieved['context']],
            ],
            $this->maxInputTokens,
        );

        $this->recordUsage($ip, $userId, $question, tokensIn: $result['tokens_in'], tokensOut: $result['tokens_out'], chunks: $retrieved['chunks'], sources: $retrieved['sources']);

        return [
            'answer' => $result['content'],
            'refused' => false,
            'sources' => $retrieved['sources'],
            'tokens_in' => $result['tokens_in'],
            'tokens_out' => $result['tokens_out'],
            'context_chunks' => $retrieved['chunks'],
            'model' => $result['model'],
        ];
    }

    /**
     * Streamed variant of {@see self::ask()}. `$onDelta` receives each generated
     * piece of text; the returned array mirrors the non-streamed shape minus the
     * answer (which is streamed).
     *
     * @return array{refused: bool, sources: list<array<string, mixed>>, tokens_in: int, tokens_out: int, context_chunks: int, model: string}
     */
    public function askStream(string $question, callable $onDelta, ?string $ip = null, ?int $userId = null): array
    {
        $question = trim($question);

        $retrieved = $this->retrieve($question);
        if ($retrieved['refused']) {
            $this->recordUsage($ip, $userId, $question, refused: true);

            return $this->emptyStreamResult();
        }

        $result = $this->llm->stream(
            [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $question."\n\nUse only the context below:\n\n".$retrieved['context']],
            ],
            $onDelta,
            $this->maxInputTokens,
        );

        $this->recordUsage($ip, $userId, $question, tokensIn: $result['tokens_in'], tokensOut: $result['tokens_out'], chunks: $retrieved['chunks'], sources: $retrieved['sources']);

        return [
            'refused' => false,
            'sources' => $retrieved['sources'],
            'tokens_in' => $result['tokens_in'],
            'tokens_out' => $result['tokens_out'],
            'context_chunks' => $retrieved['chunks'],
            'model' => $result['model'],
        ];
    }

    /**
     * Embed the question, search the vector store and decide whether it is on
     * topic. Returns a context block plus metadata for the caller to build the
     * prompt.
     *
     * @return array{refused: bool, context: string, chunks: int, sources: list<array<string, mixed>>}
     */
    private function retrieve(string $question): array
    {
        $queryVector = $this->embedding->embed($question);
        $hits = $this->store->search($queryVector, $this->topK);

        if ($hits === [] || ($this->bestScore($hits) < $this->minSimilarity)) {
            return ['refused' => true, 'context' => '', 'chunks' => 0, 'sources' => []];
        }

        $used = array_slice($hits, 0, $this->contextChunks);

        return [
            'refused' => false,
            'context' => $this->buildContext($used),
            'chunks' => count($used),
            'sources' => $this->sources($used),
        ];
    }

    public function indexContent(Model $model): void
    {
        $sourceType = $this->sourceType($model);
        $html = $this->contentHtml($model);

        if ($html === null) {
            return;
        }

        $this->indexChunks($model, $sourceType, $this->chunker->chunk($html));
    }

    public function indexDocument(Document $document, string $text): void
    {
        $payload = [
            'source_type' => 'document',
            'source_id' => 'doc:'.$document->id,
            'title' => $document->title,
            'url' => $document->post ? route('posts.show', $document->post) : null,
            'post_id' => $document->post_id,
        ];

        $this->indexChunks(null, 'document', $this->chunker->chunk($text), $payload, 'doc:'.$document->id);
    }

    public function deleteContent(Model $model): void
    {
        $this->store->deleteWherePayload('source_id', $this->contentKey($model));
    }

    public function deleteDocument(Document $document): void
    {
        $this->store->deleteWherePayload('source_id', 'doc:'.$document->id);
    }

    public function ensureIndex(): void
    {
        $this->store->ensureCollection($this->embedding->dimensions());
    }

    /**
     * @param  array<int, array{text: string, heading: string|null}>  $chunks
     * @param  array<string, mixed>|null  $basePayload
     */
    private function indexChunks(?Model $model, string $sourceType, array $chunks, ?array $basePayload = null, int|string|null $overrideId = null): void
    {
        if ($chunks === []) {
            return;
        }

        $points = [];

        foreach ($chunks as $i => $chunk) {
            $embedding = $this->embedding->embed($chunk['text']);

            $points[] = [
                'id' => $this->pointId($sourceType, $overrideId ?? $this->contentKey($model), $i),
                'vector' => $embedding,
                'payload' => array_merge($basePayload ?? $this->basePayload($model, $sourceType), [
                    'chunk_index' => $i,
                    'heading' => $chunk['heading'],
                    'text' => $chunk['text'],
                ]),
            ];
        }

        $this->ensureIndex();
        $this->store->upsert($points);
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(?Model $model, string $sourceType): array
    {
        if ($model === null) {
            return [];
        }

        return [
            'source_type' => $sourceType,
            'source_id' => $this->contentKey($model),
            'title' => (string) ($model->title ?? ''),
            'url' => $this->modelUrl($model),
        ];
    }

    private function modelUrl(Model $model): ?string
    {
        return match ($model::class) {
            Post::class => route('posts.show', $model),
            Challenge::class => route('challenges.show', $model),
            Project::class => route('projects.show', $model),
            default => null,
        };
    }

    private function systemPrompt(): string
    {
        return 'You are the DevJourney assistant. You help people learn from the author\'s blog posts, '
            .'solved technical challenges, projects and reference documents. Answer ONLY using the '
            .'provided context. If the context does not contain the answer, say clearly that you do not '
            .'know and suggest checking the journal. Keep answers concise and cite the source title when '
            .'possible. Never answer about topics unrelated to the provided context.';
    }

    /**
     * @param  list<array{id: string, score: float, payload: array<string, mixed>}>  $hits
     */
    private function buildContext(array $hits): string
    {
        $blocks = [];

        foreach ($hits as $hit) {
            $payload = $hit['payload'];
            $heading = isset($payload['heading']) && $payload['heading'] ? " [{$payload['heading']}]" : '';
            $blocks[] = trim(($payload['title'] ?? 'Untitled').$heading."\n".($payload['text'] ?? ''));
        }

        return implode("\n\n---\n\n", $blocks);
    }

    /**
     * @param  list<array{id: string, score: float, payload: array<string, mixed>}>  $hits
     * @return list<array<string, mixed>>
     */
    private function sources(array $hits): array
    {
        $sources = [];

        foreach (array_slice($hits, 0, $this->contextChunks) as $hit) {
            $sources[] = [
                'title' => $hit['payload']['title'] ?? null,
                'url' => $hit['payload']['url'] ?? null,
                'heading' => $hit['payload']['heading'] ?? null,
                'score' => round($hit['score'], 3),
            ];
        }

        return $sources;
    }

    /**
     * @param  list<array{id: string, score: float, payload: array<string, mixed>}>  $hits
     */
    private function bestScore(array $hits): float
    {
        return max(array_map(fn (array $hit) => (float) $hit['score'], $hits));
    }

    private function pointId(string $sourceType, int|string|null $key, int $index): string
    {
        $hash = (string) md5("{$this->collection}|{$sourceType}|{$key}|{$index}");

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 12, 4),
            substr($hash, 16, 4), substr($hash, 20, 12),
        );
    }

    private function contentKey(Model $model): string
    {
        return $this->sourceType($model).':'.$model->getKey();
    }

    private function sourceType(Model $model): string
    {
        return match ($model::class) {
            Post::class => 'post',
            Challenge::class => 'challenge',
            Project::class => 'project',
            default => Str::snake(class_basename($model)),
        };
    }

    private function contentHtml(Model $model): ?string
    {
        $html = match (true) {
            $model instanceof Post => $model->body_html,
            $model instanceof Challenge => $model->question_html."\n".$model->answer_html,
            $model instanceof Project => $model->body_html,
            default => null,
        };

        return is_string($html) && $html !== '' ? $html : null;
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     */
    private function recordUsage(?string $ip, ?int $userId, string $question, bool $refused = false, int $tokensIn = 0, int $tokensOut = 0, int $chunks = 0, array $sources = []): void
    {
        if ($ip === null) {
            return;
        }

        AiUsage::create([
            'ip_address' => $ip,
            'user_id' => $userId,
            'question' => $question,
            'model' => config('rag.chat_model', 'unknown'),
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'context_chunks' => $chunks,
            'latency_ms' => 0,
            'sources' => $sources !== [] ? json_encode($sources) : null,
        ]);
    }

    /**
     * @return array{answer: string|null, refused: bool, sources: list<array<string, mixed>>, tokens_in: int, tokens_out: int, context_chunks: int, model: string}
     */
    private function emptyResult(): array
    {
        return [
            'answer' => null,
            'refused' => true,
            'sources' => [],
            'tokens_in' => 0,
            'tokens_out' => 0,
            'context_chunks' => 0,
            'model' => config('rag.chat_model', 'unknown'),
        ];
    }

    /**
     * @return array{refused: bool, sources: list<array<string, mixed>>, tokens_in: int, tokens_out: int, context_chunks: int, model: string}
     */
    private function emptyStreamResult(): array
    {
        return [
            'refused' => true,
            'sources' => [],
            'tokens_in' => 0,
            'tokens_out' => 0,
            'context_chunks' => 0,
            'model' => config('rag.chat_model', 'unknown'),
        ];
    }
}
