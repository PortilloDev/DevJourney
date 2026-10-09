<?php

declare(strict_types=1);

namespace App\Services\Rag;

use App\Models\AiConversation;
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
    /**
     * Predefined answer for questions that are not related to the post. Defined
     * in code (not in the database) as required by R4.
     */
    public const UNRELATED_MESSAGE = 'Sorry, I can only answer questions about this article. '
        .'Try asking something that is covered in the post.';

    private string $collection;

    private int $topK;

    private float $minSimilarity;

    private int $contextChunks;

    private int $maxInputTokens;

    private int $postMaxAnswerTokens;

    private float $relatedThreshold;

    private float $unrelatedThreshold;

    public function __construct(
        private readonly VectorStore $store,
        private readonly EmbeddingService $embedding,
        private readonly LlmClient $llm,
        private readonly Chunker $chunker,
        private readonly ChatAnswerCache $answers,
    ) {
        $this->collection = (string) config('rag.qdrant.collection', 'content');
        $this->topK = (int) config('rag.retrieval.top_k', 5);
        $this->minSimilarity = (float) config('rag.retrieval.min_similarity', 0.35);
        $this->contextChunks = (int) config('rag.limits.max_context_chunks', 5);
        $this->maxInputTokens = (int) config('rag.limits.max_input_tokens', 2000);
        $this->postMaxAnswerTokens = (int) config('rag.post_chat.max_answer_tokens', 200);
        $this->relatedThreshold = (float) config('rag.post_chat.related_threshold', 0.5);
        $this->unrelatedThreshold = (float) config('rag.post_chat.unrelated_threshold', 0.15);
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
     * Answer a question scoped strictly to a single post (and its linked
     * documents).
     *
     * @return array{answer: string, refused: bool, sources: list<array<string, mixed>>, context: string, tokens_in: int, tokens_out: int, context_chunks: int, model: string}
     */
    public function askForPost(Post $post, string $question, ?string $ip = null, ?int $userId = null, ?int $conversationId = null, bool $useCache = true): array
    {
        $question = trim($question);
        $history = $this->conversationHistory($conversationId);
        $rewrite = $this->rewriteQuestion($history, $question);
        $effective = $rewrite['question'];
        $rewriteTokens = $rewrite['tokens_in'] + $rewrite['tokens_out'];
        $rewritten = $rewrite['rewritten'] ? $effective : null;
        $cacheable = $useCache && $history === [];
        $postId = (int) $post->getKey();

        // A stored answer is served before resolving relevance: a hit spends no
        // embedding call and skips the relevance gates (R1, D5). Only a
        // history-free turn is reusable, so the cache is limited to it (D9).
        if ($cacheable) {
            $cached = $this->answers->get($postId, $effective);

            if ($cached !== null) {
                return $this->cachedResult($post, $question, $cached, $ip, $userId, $conversationId);
            }
        }

        $context = $this->resolvePostContext($post, $effective);

        if ($context['refused']) {
            $this->recordUsage($ip, $userId, $question, refused: true, extra: $this->observability($post, $conversationId, $context['stage'], [
                'rewritten_question' => $rewritten,
            ]));

            return $this->refusedResult();
        }

        $messages = $this->postMessages($effective, $context['context'], $history);

        try {
            $result = $this->llm->complete($messages, $this->postMaxAnswerTokens);
        } catch (\Throwable $e) {
            // A failed provider call must still leave a trace (R2, D4).
            $this->recordUsage(
                $ip,
                $userId,
                $question,
                extra: $this->observability($post, $conversationId, 'error', [
                    'rewritten_question' => $rewritten,
                    'prompt' => (string) json_encode($messages, JSON_UNESCAPED_UNICODE),
                ]),
            );

            throw $e;
        }

        if ($cacheable) {
            $this->answers->put($postId, $effective, [
                'answer' => $result['content'],
                'sources' => $context['sources'],
                'model' => $result['model'],
            ]);
        }

        $this->recordUsage(
            $ip,
            $userId,
            $question,
            tokensIn: $result['tokens_in'] + $rewriteTokens,
            tokensOut: $result['tokens_out'],
            chunks: $context['chunks'],
            sources: $context['sources'],
            extra: $this->observability($post, $conversationId, null, [
                'rewritten_question' => $rewritten,
                'prompt' => (string) json_encode($messages, JSON_UNESCAPED_UNICODE),
                'answer' => $result['content'],
            ]),
        );

        return [
            'answer' => $result['content'],
            'refused' => false,
            'sources' => $context['sources'],
            'context' => $context['context'],
            'tokens_in' => $result['tokens_in'] + $rewriteTokens,
            'tokens_out' => $result['tokens_out'],
            'context_chunks' => $context['chunks'],
            'model' => $result['model'],
        ];
    }

    /**
     * Streamed variant of {@see self::askForPost()}.
     *
     * @return array{refused: bool, sources: list<array<string, mixed>>, tokens_in: int, tokens_out: int, context_chunks: int, model: string}
     */
    public function askPostStream(Post $post, string $question, callable $onDelta, ?string $ip = null, ?int $userId = null, ?int $conversationId = null): array
    {
        $question = trim($question);
        $history = $this->conversationHistory($conversationId);
        $rewrite = $this->rewriteQuestion($history, $question);
        $effective = $rewrite['question'];
        $rewriteTokens = $rewrite['tokens_in'] + $rewrite['tokens_out'];
        $rewritten = $rewrite['rewritten'] ? $effective : null;
        $cacheable = $history === [];
        $postId = (int) $post->getKey();

        // A cache hit is streamed by splitting the stored text, so the visitor
        // cannot tell it apart from a generated answer (R2, D6).
        if ($cacheable) {
            $cached = $this->answers->get($postId, $effective);

            if ($cached !== null) {
                return $this->streamCachedResult($post, $question, $cached, $onDelta, $ip, $userId, $conversationId);
            }
        }

        $context = $this->resolvePostContext($post, $effective);

        if ($context['refused']) {
            $onDelta(self::UNRELATED_MESSAGE);
            $this->recordUsage($ip, $userId, $question, refused: true, extra: $this->observability($post, $conversationId, $context['stage'], [
                'rewritten_question' => $rewritten,
            ]));

            return $this->refusedStreamResult();
        }

        $messages = $this->postMessages($effective, $context['context'], $history);

        try {
            $result = $this->llm->stream($messages, $onDelta, $this->postMaxAnswerTokens);
        } catch (\Throwable $e) {
            // A stream cut mid-flight must still leave a trace (R2, D4).
            $this->recordUsage(
                $ip,
                $userId,
                $question,
                extra: $this->observability($post, $conversationId, 'error', [
                    'rewritten_question' => $rewritten,
                    'prompt' => (string) json_encode($messages, JSON_UNESCAPED_UNICODE),
                ]),
            );

            throw $e;
        }

        if ($cacheable) {
            $this->answers->put($postId, $effective, [
                'answer' => $result['content'],
                'sources' => $context['sources'],
                'model' => $result['model'],
            ]);
        }

        $this->recordUsage(
            $ip,
            $userId,
            $question,
            tokensIn: $result['tokens_in'] + $rewriteTokens,
            tokensOut: $result['tokens_out'],
            chunks: $context['chunks'],
            sources: $context['sources'],
            extra: $this->observability($post, $conversationId, null, [
                'rewritten_question' => $rewritten,
                'prompt' => (string) json_encode($messages, JSON_UNESCAPED_UNICODE),
                'answer' => $result['content'],
            ]),
        );

        return [
            'refused' => false,
            'sources' => $context['sources'],
            'tokens_in' => $result['tokens_in'] + $rewriteTokens,
            'tokens_out' => $result['tokens_out'],
            'context_chunks' => $context['chunks'],
            'model' => $result['model'],
        ];
    }

    /**
     * Observability metadata stored with every turn plus the conversation link.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function observability(Post $post, ?int $conversationId, ?string $refusedStage, array $extra = []): array
    {
        return array_merge([
            'conversation_id' => $conversationId,
            'post_id' => $post->getKey(),
            'refused_stage' => $refusedStage,
            'provider' => (string) config('rag.provider', 'unknown'),
            'embedding_model' => (string) config('rag.embeddings.model', 'unknown'),
            'top_k' => $this->topK,
        ], $extra);
    }

    /**
     * The last turns of the conversation, oldest first, capped at
     * `rag.conversation.max_turns` (R3). Only complete turns (with an answer)
     * form the history sent to the model.
     *
     * @return list<array{question: string, answer: string}>
     */
    private function conversationHistory(?int $conversationId): array
    {
        if ($conversationId === null) {
            return [];
        }

        $conversation = AiConversation::find($conversationId);

        if ($conversation === null) {
            return [];
        }

        $max = max(0, (int) config('rag.conversation.max_turns', 3));

        return $conversation->turns()
            ->whereNotNull('answer')
            ->reorder()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($max)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (AiUsage $turn): array => [
                'question' => (string) $turn->question,
                'answer' => (string) $turn->answer,
            ])
            ->all();
    }

    /**
     * Rewrite a follow-up into a standalone question using the conversation
     * (R1). It is only invoked when there is prior history (R2) and any failure
     * or empty output degrades to the original question (R4).
     *
     * @param  list<array{question: string, answer: string}>  $history
     * @return array{question: string, tokens_in: int, tokens_out: int, rewritten: bool}
     */
    private function rewriteQuestion(array $history, string $question): array
    {
        if ($history === []) {
            return ['question' => $question, 'tokens_in' => 0, 'tokens_out' => 0, 'rewritten' => false];
        }

        $messages = [['role' => 'system', 'content' => $this->rewritePrompt()]];

        foreach ($history as $turn) {
            $messages[] = ['role' => 'user', 'content' => $turn['question']];
            $messages[] = ['role' => 'assistant', 'content' => $turn['answer']];
        }

        $messages[] = ['role' => 'user', 'content' => $question];

        try {
            $result = $this->llm->complete(
                $messages,
                (int) config('rag.rewrite.max_tokens', 60),
                $this->rewriteModel(),
                0.0,
            );
        } catch (\Throwable) {
            return ['question' => $question, 'tokens_in' => 0, 'tokens_out' => 0, 'rewritten' => false];
        }

        $rewritten = trim($result['content']);

        if ($rewritten === '') {
            return ['question' => $question, 'tokens_in' => 0, 'tokens_out' => 0, 'rewritten' => false];
        }

        return [
            'question' => $rewritten,
            'tokens_in' => $result['tokens_in'],
            'tokens_out' => $result['tokens_out'],
            'rewritten' => true,
        ];
    }

    private function rewriteModel(): ?string
    {
        $model = config('rag.rewrite.model') ?: config('rag.chat_model');

        return is_string($model) && $model !== '' ? $model : null;
    }

    private function rewritePrompt(): string
    {
        return 'You rewrite a follow-up question into a standalone question. Use the previous '
            .'conversation to resolve pronouns, references and ellipsis, keep the original language '
            .'and intent, and add no new information. Return only the rewritten question.';
    }

    /**
     * Build the final message list: the system prompt, the recent turns as
     * user/assistant pairs, and the current question with its context (R3, D5).
     *
     * @param  list<array{question: string, answer: string}>  $history
     * @return list<array{role: string, content: string}>
     */
    private function postMessages(string $question, string $context, array $history): array
    {
        $messages = [['role' => 'system', 'content' => $this->postChatPrompt()]];

        foreach ($history as $turn) {
            $messages[] = ['role' => 'user', 'content' => $turn['question']];
            $messages[] = ['role' => 'assistant', 'content' => $turn['answer']];
        }

        $messages[] = ['role' => 'user', 'content' => $question."\n\nUse only the context below:\n\n".$context];

        return $messages;
    }

    /**
     * Serve a stored answer without touching the provider, logging the turn as a
     * cache hit with no token usage (R4, D8).
     *
     * @param  array{answer: string, sources: list<array<string, mixed>>, model: string}  $cached
     * @return array{answer: string, refused: bool, sources: list<array<string, mixed>>, context: string, tokens_in: int, tokens_out: int, context_chunks: int, model: string}
     */
    private function cachedResult(Post $post, string $question, array $cached, ?string $ip, ?int $userId, ?int $conversationId): array
    {
        $this->recordUsage(
            $ip,
            $userId,
            $question,
            chunks: 0,
            sources: $cached['sources'],
            extra: $this->observability($post, $conversationId, null, [
                'cache_hit' => true,
                'answer' => $cached['answer'],
            ]),
        );

        return [
            'answer' => $cached['answer'],
            'refused' => false,
            'sources' => $cached['sources'],
            'context' => '',
            'tokens_in' => 0,
            'tokens_out' => 0,
            'context_chunks' => 0,
            'model' => $cached['model'],
        ];
    }

    /**
     * Streamed variant of {@see self::cachedResult()} (R2, D6).
     *
     * @param  array{answer: string, sources: list<array<string, mixed>>, model: string}  $cached
     * @return array{refused: bool, sources: list<array<string, mixed>>, tokens_in: int, tokens_out: int, context_chunks: int, model: string}
     */
    private function streamCachedResult(Post $post, string $question, array $cached, callable $onDelta, ?string $ip, ?int $userId, ?int $conversationId): array
    {
        foreach ($this->splitAnswer($cached['answer']) as $delta) {
            $onDelta($delta);
        }

        $this->recordUsage(
            $ip,
            $userId,
            $question,
            chunks: 0,
            sources: $cached['sources'],
            extra: $this->observability($post, $conversationId, null, [
                'cache_hit' => true,
                'answer' => $cached['answer'],
            ]),
        );

        return [
            'refused' => false,
            'sources' => $cached['sources'],
            'tokens_in' => 0,
            'tokens_out' => 0,
            'context_chunks' => 0,
            'model' => $cached['model'],
        ];
    }

    /**
     * Split stored text into small deltas so a cache hit looks streamed (D6).
     *
     * @return list<string>
     */
    private function splitAnswer(string $answer, int $size = 24): array
    {
        $length = mb_strlen($answer);

        if ($length === 0) {
            return [];
        }

        $deltas = [];

        for ($offset = 0; $offset < $length; $offset += $size) {
            $deltas[] = mb_substr($answer, $offset, $size);
        }

        return $deltas;
    }

    /**
     * Two-stage relevance resolution scoped to a post (D8): a local lexical
     * check short-circuits clearly unrelated questions without any external
     * call; otherwise the embedding provider decides.
     *
     * @return array{refused: bool, context: string, chunks: int, sources: list<array<string, mixed>>, stage: string|null}
     */
    private function resolvePostContext(Post $post, string $question): array
    {
        $questionTokens = $this->contentTokens($question);
        $overlap = $this->lexicalOverlap($question, $this->postText($post));

        // Sharing a distinctive term with the article title is a strong signal of
        // relevance (e.g. the question says "hexagonal" / "laravel"). This also
        // rescues cross-language questions that reuse the same domain term.
        $sharesTitleTerm = $questionTokens !== []
            && array_intersect($questionTokens, $this->contentTokens((string) $post->title)) !== [];

        // Stage 1: a clearly unrelated question is refused with no external call.
        if ($questionTokens !== [] && $overlap <= $this->unrelatedThreshold && ! $sharesTitleTerm) {
            return $this->refusedContext('local');
        }

        $clearlyRelated = $sharesTitleTerm || $overlap >= $this->relatedThreshold;

        // Stage 2: embedding-based retrieval, filtered to the post + its documents.
        $vector = $this->embedding->embed($question);
        $hits = $this->store->search($vector, $this->topK, $this->postFilter((int) $post->getKey()));

        if ($hits === []) {
            return $this->refusedContext('embedding');
        }

        // The similarity gate only decides the ambiguous case; a lexically
        // conclusive question is trusted.
        if (! $clearlyRelated && $this->bestScore($hits) < $this->minSimilarity) {
            return $this->refusedContext('embedding');
        }

        $used = array_slice($hits, 0, $this->contextChunks);

        return [
            'refused' => false,
            'context' => $this->buildContext($used),
            'chunks' => count($used),
            'sources' => $this->sources($used),
            'stage' => null,
        ];
    }

    /**
     * @return array{should: list<array{key: string, value: mixed}>}
     */
    private function postFilter(int $postId): array
    {
        return [
            'should' => [
                ['key' => 'source_id', 'value' => 'post:'.$postId],
                ['key' => 'post_id', 'value' => $postId],
            ],
        ];
    }

    /**
     * @return array{refused: bool, context: string, chunks: int, sources: list<array<string, mixed>>, stage: string}
     */
    private function refusedContext(string $stage): array
    {
        return ['refused' => true, 'context' => '', 'chunks' => 0, 'sources' => [], 'stage' => $stage];
    }

    private function postText(Post $post): string
    {
        return trim(($post->title ?? '').' '.($post->excerpt ?? '').' '.strip_tags((string) $post->body_html));
    }

    private function postChatPrompt(): string
    {
        return 'You are the assistant for ONE DevJourney journal article. Answer ONLY using the '
            .'provided context about this article. Be concise: a single short paragraph, at most the '
            .'length of two or three tweets (roughly 60 words). Return only the most important '
            .'information needed to answer. If the context does not contain the answer, say you do not '
            .'know. Never use information from other posts.';
    }

    /**
     * Lexical overlap between the question and the post text: fraction of the
     * question's content words that appear in the post.
     */
    private function lexicalOverlap(string $question, string $text): float
    {
        $questionTokens = $this->contentTokens($question);

        if ($questionTokens === []) {
            return 0.0;
        }

        $textTokens = $this->contentTokens($text);
        $hits = array_intersect($questionTokens, $textTokens);

        return count($hits) / count($questionTokens);
    }

    /**
     * @return list<string>
     */
    private function contentTokens(string $text): array
    {
        $normalized = Str::lower(Str::ascii($text));
        $parts = preg_split('/[^a-z0-9]+/', $normalized) ?: [];

        $stopwords = ['the', 'and', 'for', 'with', 'that', 'this', 'from', 'what', 'when', 'where', 'which', 'how', 'why', 'who', 'are', 'was', 'were', 'you', 'your', 'about', 'into', 'como', 'para', 'sobre', 'este', 'esta', 'esto'];

        return collect($parts)
            ->filter(fn (string $token) => mb_strlen($token) >= 4 && ! in_array($token, $stopwords, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{answer: string, refused: bool, sources: list<array<string, mixed>>, context: string, tokens_in: int, tokens_out: int, context_chunks: int, model: string}
     */
    private function refusedResult(): array
    {
        return [
            'answer' => self::UNRELATED_MESSAGE,
            'refused' => true,
            'sources' => [],
            'context' => '',
            'tokens_in' => 0,
            'tokens_out' => 0,
            'context_chunks' => 0,
            'model' => config('rag.chat_model', 'unknown'),
        ];
    }

    /**
     * @return array{refused: bool, sources: list<array<string, mixed>>, tokens_in: int, tokens_out: int, context_chunks: int, model: string}
     */
    private function refusedStreamResult(): array
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
            'url' => $document->post ? route('posts.show', $document->post, absolute: false) : null,
            'post_id' => $document->post_id,
            'embedding_model' => (string) config('rag.embeddings.model', 'unknown'),
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
            'embedding_model' => (string) config('rag.embeddings.model', 'unknown'),
        ];
    }

    private function modelUrl(Model $model): ?string
    {
        // Relative paths so the source resolves against the current origin
        // (host + port) instead of the host captured at indexing time.
        return match ($model::class) {
            Post::class => route('posts.show', $model, absolute: false),
            Challenge::class => route('challenges.show', $model, absolute: false),
            Project::class => route('projects.show', $model, absolute: false),
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
     * @param  array<string, mixed>  $extra
     */
    private function recordUsage(?string $ip, ?int $userId, string $question, bool $refused = false, int $tokensIn = 0, int $tokensOut = 0, int $chunks = 0, array $sources = [], array $extra = []): void
    {
        if ($ip === null) {
            return;
        }

        AiUsage::create(array_merge([
            'ip_address' => $ip,
            'user_id' => $userId,
            'question' => $question,
            'model' => config('rag.chat_model', 'unknown'),
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'context_chunks' => $chunks,
            'latency_ms' => 0,
            'refused' => $refused,
            'sources' => $sources !== [] ? json_encode($sources) : null,
        ], $extra));

        $this->touchConversation($extra['conversation_id'] ?? null, $tokensIn + $tokensOut);
    }

    private function touchConversation(mixed $conversationId, int $tokens): void
    {
        if (! is_numeric($conversationId)) {
            return;
        }

        $conversation = AiConversation::find((int) $conversationId);

        if ($conversation === null) {
            return;
        }

        $conversation->turns_count = $conversation->turns_count + 1;
        $conversation->tokens_total = $conversation->tokens_total + $tokens;
        $conversation->last_activity_at = now();
        $conversation->save();
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
