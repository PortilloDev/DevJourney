<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiUsage;
use App\Models\Document;
use App\Models\Post;
use App\Models\User;
use App\Services\Embedding\EmbeddingService;
use App\Services\Llm\LlmClient;
use App\Services\Rag\RagService;
use App\Services\Vector\InMemoryVectorStore;
use App\Services\Vector\VectorStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RagSystemTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryVectorStore $store;

    private FakeEmbeddingService $embedding;

    private FakeLlmClient $llm;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config(['rag.qdrant.enabled' => false]);
        config(['rag.embeddings.dimensions' => 3]);
        config(['rag.retrieval.min_similarity' => 0.35]);

        $this->store = new InMemoryVectorStore;
        $this->embedding = new FakeEmbeddingService;
        $this->llm = new FakeLlmClient;

        $this->app->instance(VectorStore::class, $this->store);
        $this->app->instance(EmbeddingService::class, $this->embedding);
        $this->app->instance(LlmClient::class, $this->llm);
    }

    public function test_ask_returns_answer_with_sources_and_logs_usage(): void
    {
        $this->store->upsert([
            [
                'id' => '11111111-1111-4111-8111-111111111111',
                'vector' => [1.0, 0.0, 0.0],
                'payload' => [
                    'source_type' => 'post',
                    'source_id' => 'post:1',
                    'title' => 'Hexagonal architecture',
                    'url' => 'https://example.com/journal/x',
                    'text' => 'Hexagonal architecture isolates the domain.',
                ],
            ],
        ]);

        $result = app(RagService::class)->ask('What is hexagonal architecture?', '127.0.0.1');

        $this->assertFalse($result['refused']);
        $this->assertSame('Hello from the fake LLM', $result['answer']);
        $this->assertSame('Hexagonal architecture', $result['sources'][0]['title']);
        $this->assertSame(10, $result['tokens_in']);
        $this->assertDatabaseCount('ai_usage', 1);
        $this->assertDatabaseHas('ai_usage', ['ip_address' => '127.0.0.1']);
    }

    public function test_ask_refuses_off_topic_questions(): void
    {
        $this->store->upsert([
            [
                'id' => '22222222-2222-4222-8222-222222222222',
                'vector' => [0.0, 1.0, 0.0],
                'payload' => [
                    'source_type' => 'post',
                    'source_id' => 'post:1',
                    'title' => 'Unrelated',
                    'text' => 'Something unrelated.',
                ],
            ],
        ]);

        $result = app(RagService::class)->ask('What is the weather tomorrow?', '127.0.0.1');

        $this->assertTrue($result['refused']);
        $this->assertNull($result['answer']);
        $this->assertSame(config('rag.chat_model'), $result['model']);
        $this->assertDatabaseCount('ai_usage', 1);
    }

    public function test_indexing_content_adds_points(): void
    {
        $post = Post::factory()->create([
            'title' => 'Indexed Post',
            'body_md' => '## Intro'.PHP_EOL.'Content about index.',
        ]);

        app(RagService::class)->indexContent($post);

        $this->assertNotEmpty($this->store->search([1.0, 0.0, 0.0], 5));
    }

    public function test_document_and_usage_resources_render(): void
    {
        $email = 'admin@devjourney.test';
        config(['app.filament_admin_email' => $email]);
        $admin = User::factory()->create(['email' => $email]);

        $document = Document::create([
            'title' => 'Spec PDF',
            'filename' => 'spec.pdf',
            'path' => 'documents/spec.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'status' => 'pending',
        ]);

        $usage = AiUsage::create([
            'ip_address' => '127.0.0.1',
            'question' => 'What is DDD?',
            'model' => 'fake-llm',
            'tokens_in' => 10,
            'tokens_out' => 5,
            'context_chunks' => 1,
            'latency_ms' => 120,
            'sources' => json_encode(['title' => 'DDD']),
        ]);

        $this->actingAs($admin)->get('/admin/documents')->assertOk()->assertSee('Spec PDF');
        $this->actingAs($admin)->get('/admin/documents/'.$document->id.'/edit')->assertOk();
        $this->actingAs($admin)->get('/admin/ai-usages')->assertOk()->assertSee('What is DDD?');
        $this->actingAs($admin)->get('/admin/ai-usages/'.$usage->id)->assertOk();
    }
}

final class FakeEmbeddingService implements EmbeddingService
{
    public function embed(string $text): array
    {
        return [1.0, 0.0, 0.0];
    }

    public function embedMany(array $texts): array
    {
        return array_map(fn () => [1.0, 0.0, 0.0], $texts);
    }

    public function dimensions(): int
    {
        return 3;
    }
}

final class FakeLlmClient implements LlmClient
{
    public function complete(array $messages, ?int $maxTokens = null): array
    {
        return [
            'content' => 'Hello from the fake LLM',
            'tokens_in' => 10,
            'tokens_out' => 5,
            'model' => 'fake-llm',
        ];
    }

    public function stream(array $messages, callable $onDelta, ?int $maxTokens = null): array
    {
        $content = 'Streamed answer about DDD.';
        ($onDelta)($content);

        return [
            'content' => $content,
            'tokens_in' => 10,
            'tokens_out' => 5,
            'model' => 'fake-llm',
        ];
    }
}
