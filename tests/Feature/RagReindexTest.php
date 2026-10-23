<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\IndexDocumentJob;
use App\Models\Challenge;
use App\Models\Document;
use App\Models\Post;
use App\Services\Embedding\EmbeddingService;
use App\Services\Vector\InMemoryVectorStore;
use App\Services\Vector\VectorStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RagReindexTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryVectorStore $store;

    private ReindexEmbedding $embedding;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'rag.qdrant.enabled' => false,
            'rag.api_key' => null,
            'rag.embeddings.api_key' => null,
            'rag.indexing.source_types' => ['post', 'document'],
        ]);

        $this->store = new InMemoryVectorStore;
        $this->embedding = new ReindexEmbedding;

        $this->app->instance(VectorStore::class, $this->store);
        $this->app->instance(EmbeddingService::class, $this->embedding);
    }

    public function test_it_rebuilds_post_vectors_and_removes_stale_points(): void
    {
        $post = Post::factory()->create([
            'title' => 'Hexagonal architecture',
            'body_md' => '## Intro'.PHP_EOL.'Hexagonal architecture isolates the domain.',
        ]);

        $this->store->upsert([[
            'id' => 'stale-point',
            'vector' => [1.0, 0.0, 0.0],
            'payload' => [
                'source_type' => 'post',
                'source_id' => 'post:'.$post->getKey(),
                'title' => 'Old',
                'text' => 'Old content',
            ],
        ]]);

        config(['rag.api_key' => 'test', 'rag.embeddings.api_key' => 'test']);
        $this->embedding->calls = 0;

        $this->artisan('rag:reindex', ['--type' => ['post']])->assertSuccessful();

        $points = $this->pointsFor('post:'.$post->getKey());

        $this->assertNotSame([], $points);
        $this->assertNotContains('stale-point', array_column($points, 'id'));
        $this->assertGreaterThan(0, $this->embedding->calls);
    }

    public function test_a_type_not_listed_in_the_configuration_is_skipped(): void
    {
        Challenge::factory()->create(['title' => 'A challenge']);

        config(['rag.api_key' => 'test', 'rag.embeddings.api_key' => 'test']);
        $this->embedding->calls = 0;

        $this->artisan('rag:reindex', ['--type' => ['challenge']])
            ->expectsOutputToContain('Skipping challenge')
            ->assertSuccessful();

        $this->assertSame(0, $this->embedding->calls);
    }

    public function test_it_fails_without_rag_credentials(): void
    {
        $this->artisan('rag:reindex', ['--type' => ['post']])
            ->expectsOutputToContain('RAG credentials are missing')
            ->assertFailed();
    }

    public function test_documents_are_not_indexed_when_the_type_is_disabled(): void
    {
        $post = Post::factory()->create();

        config([
            'rag.api_key' => 'test',
            'rag.embeddings.api_key' => 'test',
            'rag.indexing.source_types' => ['post'],
        ]);

        $document = Document::create([
            'title' => 'Spec PDF',
            'post_id' => $post->getKey(),
            'filename' => 'spec.pdf',
            'path' => 'documents/spec.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'status' => 'pending',
            'is_public' => true,
        ]);

        $this->embedding->calls = 0;

        IndexDocumentJob::dispatchSync($document->getKey());

        $this->assertSame(0, $this->embedding->calls);
        $this->assertSame('pending', $document->fresh()?->status);
        $this->assertSame([], $this->pointsFor('doc:'.$document->getKey()));
    }

    public function test_documents_without_a_post_are_not_indexed(): void
    {
        config([
            'rag.api_key' => 'test',
            'rag.embeddings.api_key' => 'test',
        ]);

        $document = Document::create([
            'title' => 'Unlinked PDF',
            'filename' => 'unlinked.pdf',
            'path' => 'documents/unlinked.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'status' => 'pending',
            'is_public' => true,
        ]);

        $this->embedding->calls = 0;

        IndexDocumentJob::dispatchSync($document->getKey());

        $this->assertSame(0, $this->embedding->calls);
        $this->assertSame('pending', $document->fresh()?->status);
        $this->assertSame([], $this->pointsFor('doc:'.$document->getKey()));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pointsFor(string $sourceId): array
    {
        return $this->store->search(
            [1.0, 0.0, 0.0],
            20,
            ['must' => [['key' => 'source_id', 'value' => $sourceId]]],
        );
    }
}

final class ReindexEmbedding implements EmbeddingService
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
