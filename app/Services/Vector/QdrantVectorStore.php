<?php

declare(strict_types=1);

namespace App\Services\Vector;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Qdrant client over its HTTP (REST) API.
 */
class QdrantVectorStore implements VectorStore
{
    private string $collection;

    private string $host;

    private ?string $apiKey;

    private string $distance;

    public function __construct()
    {
        $this->collection = config('rag.qdrant.collection', 'content');
        $this->host = rtrim((string) config('rag.qdrant.host', 'http://qdrant:6333'), '/');
        $this->apiKey = config('rag.qdrant.api_key');
        $this->distance = config('rag.qdrant.distance', 'Cosine');
    }

    public function ensureCollection(?int $dimensions = null, ?string $distance = null): void
    {
        if ($this->collectionExists()) {
            return;
        }

        $response = $this->client()->put("/collections/{$this->collection}", [
            'vectors' => [
                'size' => $dimensions ?? config('rag.embeddings.dimensions', 1536),
                'distance' => $distance ?? $this->distance,
            ],
        ]);

        $this->throwIfFailed($response, 'ensure collection');
    }

    /**
     * @param  list<array{id: string, vector: list<float>, payload: array<string, mixed>}>  $points
     */
    public function upsert(array $points): void
    {
        if ($points === []) {
            return;
        }

        $response = $this->client()->put("/collections/{$this->collection}/points?wait=true", [
            'points' => $points,
        ]);

        $this->throwIfFailed($response, 'upsert points');
    }

    /**
     * @param  list<float>  $vector
     * @param  array<string, mixed>|null  $filter
     * @return list<array{id: string, score: float, payload: array<string, mixed>}>
     */
    public function search(array $vector, int $limit = 5, ?array $filter = null): array
    {
        $response = $this->client()->post("/collections/{$this->collection}/points/search", [
            'vector' => $vector,
            'limit' => $limit,
            'with_payload' => true,
        ]);

        $this->throwIfFailed($response, 'search');

        $hits = [];

        foreach ((array) $response->json('result', []) as $hit) {
            if (! is_array($hit)) {
                continue;
            }

            $payload = is_array($hit['payload'] ?? null) ? $hit['payload'] : [];

            $hits[] = [
                'id' => (string) ($hit['id'] ?? ''),
                'score' => (float) ($hit['score'] ?? 0),
                'payload' => $payload,
            ];
        }

        return $hits;
    }

    public function deleteWherePayload(string $key, mixed $value): void
    {
        $response = $this->client()->post("/collections/{$this->collection}/points/delete?wait=true", [
            'filter' => [
                'must' => [
                    ['key' => $key, 'match' => ['value' => $value]],
                ],
            ],
        ]);

        $this->throwIfFailed($response, 'delete points');
    }

    protected function collectionExists(): bool
    {
        $response = $this->client()->get("/collections/{$this->collection}");

        return $response->successful();
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->host)
            ->acceptJson()
            ->withHeaders($this->apiKey ? ['api-key' => $this->apiKey] : []);
    }

    protected function throwIfFailed(Response $response, string $operation): void
    {
        if ($response->failed()) {
            throw new RuntimeException("Qdrant {$operation} failed: {$response->body()}");
        }
    }
}
