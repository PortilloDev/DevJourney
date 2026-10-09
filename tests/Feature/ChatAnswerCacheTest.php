<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiUsage;
use App\Models\Post;
use App\Services\Embedding\CachedEmbeddingService;
use App\Services\Embedding\EmbeddingService;
use App\Services\Llm\LlmClient;
use App\Services\Rag\RagService;
use App\Services\Vector\InMemoryVectorStore;
use App\Services\Vector\VectorStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Covers the answer cache spec (R1–R5).
 */
class ChatAnswerCacheTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryVectorStore $store;

    private CacheCountingEmbedding $embedding;

    private CacheCountingLlm $llm;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'rag.qdrant.enabled' => false,
            'rag.embeddings.model' => 'test-embed',
            'rag.cache.answer_ttl' => 604800,
            'rag.cache.embedding_ttl' => 604800,
            'rag.retrieval.min_similarity' => 0.35,
            'rag.post_chat.max_answer_tokens' => 120,
            'rag.post_chat.related_threshold' => 0.5,
            'rag.post_chat.unrelated_threshold' => 0.15,
        ]);

        $this->store = new InMemoryVectorStore;
        $this->embedding = new CacheCountingEmbedding;
        $this->llm = new CacheCountingLlm;

        $this->app->instance(VectorStore::class, $this->store);
        // Wrap the counting provider in the real decorator so R5 is exercised.
        $this->app->instance(EmbeddingService::class, new CachedEmbeddingService($this->embedding));
        $this->app->instance(LlmClient::class, $this->llm);
    }

    private function postA(): Post
    {
        return Post::factory()->create([
            'slug' => 'hexagonal',
            'title' => 'Hexagonal architecture',
            'excerpt' => 'Ports and adapters',
            'body_md' => '## Intro'.PHP_EOL.'Hexagonal architecture keeps the domain isolated from frameworks.',
        ]);
    }

    private function postB(): Post
    {
        return Post::factory()->create([
            'slug' => 'hexagonal-scale',
            'title' => 'Hexagonal architecture at scale',
            'body_md' => '## Scale'.PHP_EOL.'Hexagonal architecture also scales the domain layer.',
        ]);
    }

    // R1-E1
    public function test_repeated_normalized_question_is_served_without_provider_calls(): void
    {
        $post = $this->postA();
        app(RagService::class)->indexContent($post);
        $this->embedding->calls = 0;

        $first = app(RagService::class)->askForPost($post, 'How does hexagonal architecture isolate the domain?', '127.0.0.1');
        $embeddingAfterFirst = $this->embedding->calls;

        $second = app(RagService::class)->askForPost($post, '  how DOES hexagonal ARCHITECTURE isolate the domain?  ', '127.0.0.1');

        $this->assertSame('Concise answer.', $first['answer']);
        $this->assertSame($first['answer'], $second['answer']);
        $this->assertSame(1, $this->llm->calls);
        $this->assertSame($embeddingAfterFirst, $this->embedding->calls);
    }

    // R2-E1
    public function test_stored_answer_is_delivered_over_the_stream_channel(): void
    {
        $post = $this->postA();
        app(RagService::class)->indexContent($post);

        $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'hexagonal',
            'stream' => true,
        ])->assertOk();

        $response = $this->postJson('/api/chat', [
            'question' => 'HOW does hexagonal architecture isolate the domain?',
            'post' => 'hexagonal',
            'stream' => true,
        ])->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('"delta":"Concise answer."', $content);
        $this->assertStringContainsString('"done":true', $content);
        $this->assertStringContainsString('"sources":', $content);
        $this->assertSame(1, $this->llm->calls);
    }

    // R3-E1
    public function test_editing_the_post_invalidates_the_stored_answer(): void
    {
        $post = $this->postA();
        app(RagService::class)->indexContent($post);

        app(RagService::class)->askForPost($post, 'How does hexagonal architecture isolate the domain?', '127.0.0.1');
        $this->assertSame(1, $this->llm->calls);

        $post->update(['title' => 'Hexagonal architecture revised']);

        app(RagService::class)->askForPost($post, 'How does hexagonal architecture isolate the domain?', '127.0.0.1');

        $this->assertSame(2, $this->llm->calls);
    }

    // R4-E1
    public function test_cache_served_turn_is_logged_with_origin_and_zero_tokens(): void
    {
        $post = $this->postA();
        app(RagService::class)->indexContent($post);

        $question = 'How does hexagonal architecture isolate the domain?';
        app(RagService::class)->askForPost($post, $question, '127.0.0.1');
        app(RagService::class)->askForPost($post, $question, '127.0.0.1');

        $turn = AiUsage::query()->latest('id')->first();

        $this->assertNotNull($turn);
        $this->assertTrue($turn->cache_hit);
        $this->assertSame(0, $turn->tokens_in);
        $this->assertSame(0, $turn->tokens_out);
        $this->assertNotEmpty($turn->retrievedSources());
    }

    // R5-E1
    public function test_question_vector_is_reused_across_posts(): void
    {
        $postA = $this->postA();
        $postB = $this->postB();
        app(RagService::class)->indexContent($postA);
        app(RagService::class)->indexContent($postB);

        $this->embedding->calls = 0;

        $question = 'How does hexagonal architecture isolate the domain?';
        app(RagService::class)->askForPost($postA, $question, '127.0.0.1');
        app(RagService::class)->askForPost($postB, $question, '127.0.0.1');

        $this->assertSame(1, $this->embedding->calls);
    }
}

final class CacheCountingEmbedding implements EmbeddingService
{
    public int $calls = 0;

    /** @var list<string> */
    private array $keywords = ['hexagonal', 'domain', 'architecture', 'isolate', 'scale'];

    public function embed(string $text): array
    {
        $this->calls++;

        $lower = strtolower($text);
        $vector = [];
        foreach ($this->keywords as $word) {
            $vector[] = (float) substr_count($lower, $word);
        }

        if (array_sum($vector) === 0.0) {
            $vector[count($vector) - 1] = 1.0;
        }

        return $vector;
    }

    public function embedMany(array $texts): array
    {
        return array_map(fn (string $text) => $this->embed($text), $texts);
    }

    public function dimensions(): int
    {
        return count($this->keywords);
    }
}

final class CacheCountingLlm implements LlmClient
{
    public int $calls = 0;

    public function complete(array $messages, ?int $maxTokens = null, ?string $model = null, ?float $temperature = null): array
    {
        $this->calls++;

        return ['content' => 'Concise answer.', 'tokens_in' => 12, 'tokens_out' => 4, 'model' => 'spy'];
    }

    public function stream(array $messages, callable $onDelta, ?int $maxTokens = null, ?string $model = null): array
    {
        $this->calls++;
        $onDelta('Concise answer.');

        return ['content' => 'Concise answer.', 'tokens_in' => 12, 'tokens_out' => 4, 'model' => 'spy'];
    }
}
