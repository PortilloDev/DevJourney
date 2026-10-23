<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use App\Services\Embedding\EmbeddingService;
use App\Services\Llm\LlmClient;
use App\Services\Rag\RagService;
use App\Services\Vector\InMemoryVectorStore;
use App\Services\Vector\VectorStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatRelevanceCalibrationTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryVectorStore $store;

    private CalibrationEmbedding $embedding;

    private CalibrationLlm $llm;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'rag.qdrant.enabled' => false,
            'rag.retrieval.min_similarity' => 0.30,
            'rag.retrieval.related_min_similarity' => 0.20,
            'rag.post_chat.related_threshold' => 0.5,
            'rag.post_chat.unrelated_threshold' => 0.15,
        ]);

        $this->store = new InMemoryVectorStore;
        $this->embedding = new CalibrationEmbedding;
        $this->llm = new CalibrationLlm;

        $this->app->instance(VectorStore::class, $this->store);
        $this->app->instance(EmbeddingService::class, $this->embedding);
        $this->app->instance(LlmClient::class, $this->llm);
    }

    // R1-E1: a two/three-letter acronym keeps the question out of the local
    // rejection and lets retrieval continue.
    public function test_acronyms_of_two_and_three_letters_count_for_the_local_check(): void
    {
        $post = $this->createPost('DDD in practice', 'Domain-driven design. DDD keeps the model at the heart.');
        $this->point($post, 'p-ddd', [1.0, 0.0, 0.0]);

        $result = app(RagService::class)->askForPost($post, '¿para qué sirve DDD?', '127.0.0.1');

        $this->assertFalse($result['refused']);
        $this->assertSame(1, $this->llm->calls);
        $this->assertGreaterThanOrEqual(1, $this->embedding->calls);
    }

    // R1-E2: an acronym absent from the post is refused locally, with no external
    // call at all.
    public function test_an_acronym_absent_from_the_post_is_refused_locally(): void
    {
        $post = $this->createPost('DDD in practice', 'Domain-driven design. DDD keeps the model at the heart.');

        $result = app(RagService::class)->askForPost($post, '¿qué es CQRS?', '127.0.0.1');

        $this->assertTrue($result['refused']);
        $this->assertSame(RagService::UNRELATED_MESSAGE, $result['answer']);
        $this->assertSame(0, $this->embedding->calls);
        $this->assertSame(0, $this->llm->calls);
    }

    // R2-E1 (negative): sharing a title term no longer skips the semantic floor,
    // so an off-topic question never reaches the chat completions endpoint.
    public function test_a_title_term_does_not_bypass_the_semantic_floor(): void
    {
        $post = $this->createPost('Hexagonal architecture', 'Hexagonal architecture isolates the domain.');
        $this->point($post, 'p-hex', $this->vectorForScore(0.10));

        $result = app(RagService::class)->askForPost($post, 'hexagonal kubernetes autoscaling', '127.0.0.1');

        $this->assertTrue($result['refused']);
        $this->assertSame(RagService::UNRELATED_MESSAGE, $result['answer']);
        $this->assertSame(0, $this->llm->calls);
        $this->assertGreaterThanOrEqual(1, $this->embedding->calls);
    }

    // R3-E1: a shared title term selects the permissive floor, rescuing a related
    // multilingual question whose score sits between both floors.
    public function test_a_title_term_rescues_a_related_question_with_the_permissive_floor(): void
    {
        $post = $this->createPost('Hexagonal architecture', 'Hexagonal architecture isolates the domain from frameworks.');
        $this->point($post, 'p-hex', $this->vectorForScore(0.25));

        $result = app(RagService::class)->askForPost($post, 'Como aplico hexagonal en python', '127.0.0.1');

        $this->assertFalse($result['refused']);
        $this->assertSame(1, $this->llm->calls);
    }

    // R4-E1: the configured strict floor is the one used (a value above the code
    // default rejects a score that would otherwise pass).
    public function test_the_configured_similarity_floor_is_the_one_used(): void
    {
        config(['rag.retrieval.min_similarity' => 0.9]);

        $post = $this->createPost('Scaling systems', 'Kubernetes autoscaling adjusts the number of pods automatically.');
        $this->point($post, 'p-k8s', $this->vectorForScore(0.60));

        $result = app(RagService::class)->askForPost($post, 'kubernetes orchestration overhead tuning', '127.0.0.1');

        $this->assertTrue($result['refused']);
        $this->assertSame(0, $this->llm->calls);
    }

    private function createPost(string $title, string $body): Post
    {
        return Post::factory()->create([
            'slug' => Str::slug($title),
            'title' => $title,
            'body_md' => '## Intro'.PHP_EOL.$body,
        ]);
    }

    /**
     * @param  list<float>  $vector
     */
    private function point(Post $post, string $id, array $vector): void
    {
        $this->store->upsert([[
            'id' => $id,
            'vector' => $vector,
            'payload' => [
                'source_type' => 'post',
                'source_id' => 'post:'.$post->getKey(),
                'title' => $post->title,
                'text' => 'Retrieved context for the article.',
            ],
        ]]);
    }

    /**
     * Point vector whose cosine against the query [1, 0, 0] equals the given score.
     *
     * @return list<float>
     */
    private function vectorForScore(float $score): array
    {
        $score = max(0.0001, min(0.9999, $score));

        return [1.0, sqrt((1 / ($score ** 2)) - 1), 0.0];
    }
}

final class CalibrationEmbedding implements EmbeddingService
{
    public int $calls = 0;

    public function embed(string $text): array
    {
        $this->calls++;

        return [1.0, 0.0, 0.0];
    }

    public function embedMany(array $texts): array
    {
        return array_map(fn (): array => [1.0, 0.0, 0.0], $texts);
    }

    public function dimensions(): int
    {
        return 3;
    }
}

final class CalibrationLlm implements LlmClient
{
    public int $calls = 0;

    public function complete(array $messages, ?int $maxTokens = null, ?string $model = null, ?float $temperature = null): array
    {
        $this->calls++;

        return [
            'content' => 'Calibrated answer.',
            'tokens_in' => 10,
            'tokens_out' => 4,
            'model' => 'calibration-llm',
            'finish_reason' => 'stop',
        ];
    }

    public function stream(array $messages, callable $onDelta, ?int $maxTokens = null, ?string $model = null): array
    {
        $this->calls++;
        ($onDelta)('Calibrated answer.');

        return [
            'content' => 'Calibrated answer.',
            'tokens_in' => 10,
            'tokens_out' => 4,
            'model' => 'calibration-llm',
            'finish_reason' => 'stop',
        ];
    }
}
