<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\IndexDocumentJob;
use App\Models\AiUsage;
use App\Models\Document;
use App\Models\Post;
use App\Services\Embedding\EmbeddingService;
use App\Services\Llm\LlmClient;
use App\Services\Rag\IpHasher;
use App\Services\Rag\RagService;
use App\Services\Vector\InMemoryVectorStore;
use App\Services\Vector\VectorStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChatPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryVectorStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['rag.qdrant.enabled' => false, 'rag.retrieval.min_similarity' => 0.3]);

        $this->store = new InMemoryVectorStore;

        $this->app->instance(VectorStore::class, $this->store);
        $this->app->instance(EmbeddingService::class, new PrivacyEmbedding);
        $this->app->instance(LlmClient::class, new PrivacyLlm);
    }

    private function publishedPost(): Post
    {
        return Post::factory()->create([
            'slug' => 'hexagonal',
            'title' => 'Hexagonal architecture',
            'body_md' => '## Intro'.PHP_EOL.'Hexagonal architecture isolates the domain from frameworks. UNIQUE_CONTEXT_MARKER',
        ]);
    }

    // R1-E1: the stored value is not the raw IP.
    public function test_ip_is_stored_hashed_not_raw(): void
    {
        $this->publishedPost();

        $this->postJson('/api/chat', ['question' => 'anything', 'post' => 'hexagonal'])->assertOk();

        $turn = AiUsage::query()->firstOrFail();

        $this->assertNotSame('127.0.0.1', $turn->ip_address);
        $this->assertSame(app(IpHasher::class)->hash('127.0.0.1'), $turn->ip_address);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $turn->ip_address);
    }

    // R1-E2 + R2: same visitor always derives the same value, so the quota counts it.
    public function test_same_visitor_gets_the_same_derived_value(): void
    {
        $this->publishedPost();

        $this->postJson('/api/chat', ['question' => 'first', 'post' => 'hexagonal'])->assertOk();
        $this->postJson('/api/chat', ['question' => 'second', 'post' => 'hexagonal'])->assertOk();

        $values = AiUsage::query()->orderBy('id')->pluck('ip_address')->unique();

        $this->assertCount(1, $values);
        $this->assertSame(app(IpHasher::class)->hash('127.0.0.1'), $values->first());
    }

    // R3-E1: a non-public document is never indexed, so its content is out of context.
    public function test_non_public_document_is_not_indexed(): void
    {
        $post = $this->publishedPost();
        app(RagService::class)->indexContent($post);

        $document = Document::create([
            'title' => 'Secret DB',
            'filename' => 'secret.pdf',
            'path' => 'documents/secret.pdf',
            'status' => 'pending',
            'is_public' => false,
        ]);

        app(RagService::class)->indexDocument($document, 'database internals');

        $this->assertEmpty($this->searchForDocument($document->id));

        // A question only answerable from that document is refused.
        $result = app(RagService::class)->askForPost($post, 'tell me about database internals', '127.0.0.1');

        $this->assertTrue($result['refused']);
        $this->assertSame(RagService::UNRELATED_MESSAGE, $result['answer']);
    }

    // R3-E2: marking a document non-public removes its points from the store.
    public function test_marking_a_document_non_public_removes_its_points(): void
    {
        $document = Document::create([
            'title' => 'Public then private',
            'filename' => 'doc.pdf',
            'path' => 'documents/doc.pdf',
            'status' => 'indexed',
            'is_public' => true,
        ]);

        app(RagService::class)->indexDocument($document, 'database internals');

        $this->assertNotEmpty($this->searchForDocument($document->id));

        $document->update(['is_public' => false]);
        IndexDocumentJob::dispatchSync($document->id);

        $this->assertEmpty($this->searchForDocument($document->id));
    }

    // R5-E1: sources carry the point id and the full prompt includes the context.
    public function test_sources_include_point_id_and_prompt_includes_context(): void
    {
        $post = $this->publishedPost();
        app(RagService::class)->indexContent($post);

        $result = app(RagService::class)->askForPost($post, 'How does hexagonal architecture isolate the domain?', '127.0.0.1');

        $this->assertFalse($result['refused']);

        $turn = AiUsage::query()->firstOrFail();
        $this->assertArrayHasKey('point_id', $turn->retrievedSources()[0]);
        $this->assertNotEmpty($turn->retrievedSources()[0]['point_id']);

        $this->assertStringContainsString('UNIQUE_CONTEXT_MARKER', (string) $turn->prompt);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchForDocument(int $documentId): array
    {
        return $this->store->search(
            [1.0, 0.0, 0.0],
            10,
            ['must' => [['key' => 'source_id', 'value' => 'doc:'.$documentId]]],
        );
    }
}

final class PrivacyEmbedding implements EmbeddingService
{
    public function embed(string $text): array
    {
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

final class PrivacyLlm implements LlmClient
{
    public function complete(array $messages, ?int $maxTokens = null, ?string $model = null, ?float $temperature = null): array
    {
        return ['content' => 'Concise answer.', 'tokens_in' => 10, 'tokens_out' => 4, 'model' => 'test'];
    }

    public function stream(array $messages, callable $onDelta, ?int $maxTokens = null, ?string $model = null): array
    {
        $onDelta('Concise answer.');

        return ['content' => 'Concise answer.', 'tokens_in' => 10, 'tokens_out' => 4, 'model' => 'test'];
    }
}
