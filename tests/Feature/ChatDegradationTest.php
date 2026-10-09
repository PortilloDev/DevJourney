<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Widgets\AiUsageOverviewWidget;
use App\Models\Document;
use App\Models\Post;
use App\Services\Embedding\EmbeddingService;
use App\Services\Llm\CircuitBreaker;
use App\Services\Llm\CircuitBreakerLlmClient;
use App\Services\Llm\LlmClient;
use App\Services\Rag\RagService;
use App\Services\Vector\InMemoryVectorStore;
use App\Services\Vector\QdrantVectorStore;
use App\Services\Vector\VectorStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Covers the automatic degradation and configuration guards spec (R2–R6).
 */
class ChatDegradationTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryVectorStore $store;

    private DegradationEmbedding $embedding;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'rag.qdrant.enabled' => false,
            'rag.embeddings.model' => 'test-embed',
            'rag.embeddings.dimensions' => 3,
            'rag.retrieval.min_similarity' => 0.35,
            'rag.post_chat.related_threshold' => 0.5,
            'rag.post_chat.unrelated_threshold' => 0.15,
            'rag.post_chat.max_answer_tokens' => 120,
            'rag.circuit.threshold' => 2,
            'rag.circuit.cooldown_seconds' => 300,
        ]);

        $this->store = new InMemoryVectorStore;
        $this->embedding = new DegradationEmbedding;

        $this->app->instance(VectorStore::class, $this->store);
        $this->app->instance(EmbeddingService::class, $this->embedding);
    }

    private function degradationPost(): Post
    {
        return Post::factory()->create([
            'slug' => 'degradation-circuit',
            'title' => 'Degradation circuit',
            'body_md' => '## Intro'.PHP_EOL.'The degradation circuit pauses provider calls when failures pile up.',
        ]);
    }

    // R2-E1
    public function test_open_circuit_returns_503_without_calling_provider(): void
    {
        config(['rag.api_key' => 'test']);
        $post = $this->degradationPost();
        app(RagService::class)->indexContent($post);

        $inner = new FlakyLlm;
        $inner->fail = true;
        $circuit = new CircuitBreaker;
        $client = new CircuitBreakerLlmClient($inner, $circuit);

        $this->app->instance(LlmClient::class, $client);
        $this->app->forgetInstance(RagService::class);

        // The provider has already failed more times than the threshold (2).
        for ($i = 0; $i < 3; $i++) {
            try {
                $client->complete([], 10);
            } catch (RuntimeException) {
            }
        }

        $this->assertTrue($circuit->isOpen());
        $callsBefore = $inner->calls;

        $this->postJson('/api/chat', [
            'question' => 'How does the degradation circuit work?',
            'post' => 'degradation-circuit',
        ])->assertStatus(503)->assertJson(['error' => 'service_error']);

        $this->assertSame($callsBefore, $inner->calls);
    }

    // R2-E2
    public function test_circuit_reinvokes_provider_after_cooldown(): void
    {
        $inner = new FlakyLlm;
        $inner->fail = true;
        $circuit = new CircuitBreaker;
        $client = new CircuitBreakerLlmClient($inner, $circuit);

        for ($i = 0; $i < 3; $i++) {
            try {
                $client->complete([], 10);
            } catch (RuntimeException) {
            }
        }

        $this->assertTrue($circuit->isOpen());
        $callsBefore = $inner->calls;

        $this->travel(301)->seconds();
        $inner->fail = false;

        $result = $client->complete([], 10);

        $this->assertSame('ok', $result['content']);
        $this->assertGreaterThan($callsBefore, $inner->calls);
        $this->assertFalse($circuit->isOpen());
    }

    // R3-E1
    public function test_widget_shows_open_circuit_state(): void
    {
        $circuit = app(CircuitBreaker::class);
        for ($i = 0; $i < 3; $i++) {
            $circuit->recordFailure();
        }

        $this->assertTrue($circuit->isOpen());

        Livewire::test(AiUsageOverviewWidget::class)
            ->assertSee('Provider circuit')
            ->assertSee('Open')
            ->assertSee('Since');
    }

    // R4-E1
    public function test_vector_store_resolution_fails_outside_testing(): void
    {
        config(['rag.qdrant.enabled' => false]);

        $this->app['env'] = 'production';
        $this->app->forgetInstance(VectorStore::class);

        try {
            $this->app->make(VectorStore::class);
            $this->fail('Expected a configuration exception.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('vector store', strtolower($e->getMessage()));
        } finally {
            $this->app['env'] = 'testing';
            $this->app->forgetInstance(VectorStore::class);
        }
    }

    // R5-E1
    public function test_dimension_mismatch_aborts_before_writing_points(): void
    {
        config([
            'rag.qdrant.host' => 'http://qdrant.test',
            'rag.qdrant.collection' => 'content',
            'rag.embeddings.dimensions' => 768,
        ]);

        Http::fake([
            'http://qdrant.test/collections/content' => Http::response([
                'result' => ['config' => ['params' => ['vectors' => ['size' => 1536]]]],
            ], 200),
        ]);

        $store = new QdrantVectorStore;

        try {
            $store->ensureCollection(768);
            $this->fail('Expected a dimension mismatch exception.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('1536', $e->getMessage());
            $this->assertStringContainsString('768', $e->getMessage());
        }

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/points'));
    }

    // R6-E1
    public function test_indexed_points_include_embedding_model(): void
    {
        config(['rag.embeddings.model' => 'test-embed']);

        $post = $this->degradationPost();
        app(RagService::class)->indexContent($post);

        $hits = $this->store->search([1.0, 0.0, 0.0], 10);
        $this->assertNotEmpty($hits);
        $this->assertSame('test-embed', $hits[0]['payload']['embedding_model']);

        $document = Document::create([
            'title' => 'Spec PDF',
            'filename' => 'spec.pdf',
            'path' => 'documents/spec.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'status' => 'pending',
        ]);
        app(RagService::class)->indexDocument($document, 'The degradation circuit pauses provider calls.');

        $documentHits = $this->store->search(
            [1.0, 0.0, 0.0],
            10,
            ['must' => [['key' => 'source_id', 'value' => 'doc:'.$document->id]]],
        );
        $this->assertNotEmpty($documentHits);
        $this->assertSame('test-embed', $documentHits[0]['payload']['embedding_model']);
    }
}

final class DegradationEmbedding implements EmbeddingService
{
    /** @var list<string> */
    private array $keywords = ['degradation', 'circuit', 'provider', 'pauses'];

    public function embed(string $text): array
    {
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

final class FlakyLlm implements LlmClient
{
    public int $calls = 0;

    public bool $fail = false;

    public function complete(array $messages, ?int $maxTokens = null, ?string $model = null, ?float $temperature = null): array
    {
        $this->calls++;

        if ($this->fail) {
            throw new RuntimeException('provider down');
        }

        return ['content' => 'ok', 'tokens_in' => 1, 'tokens_out' => 1, 'model' => 'flaky'];
    }

    public function stream(array $messages, callable $onDelta, ?int $maxTokens = null, ?string $model = null): array
    {
        $this->calls++;

        if ($this->fail) {
            throw new RuntimeException('provider down');
        }

        $onDelta('ok');

        return ['content' => 'ok', 'tokens_in' => 1, 'tokens_out' => 1, 'model' => 'flaky'];
    }
}
