<?php

declare(strict_types=1);

namespace App\Services\Embedding;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * OpenAI-compatible embeddings endpoint (`/embeddings`). Works with OpenAI,
 * DeepSeek and any provider exposing the same schema.
 */
class OpenAiEmbeddingService implements EmbeddingService
{
    private string $baseUrl;

    private string $model;

    private int $dimensions;

    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('rag.embeddings.base_url'), '/');
        $this->model = (string) config('rag.embeddings.model');
        $this->dimensions = (int) config('rag.embeddings.dimensions');
        $this->apiKey = config('rag.embeddings.api_key');
    }

    public function embed(string $text): array
    {
        return $this->embedMany([$text])[0] ?? [];
    }

    public function embedMany(array $texts): array
    {
        $this->guardConfigured();

        $response = Http::withHeaders($this->headers())
            ->asJson()
            ->post($this->baseUrl.'/embeddings', [
                'model' => $this->model,
                'input' => $texts,
                'dimensions' => $this->dimensions,
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Embedding request failed: {$response->body()}");
        }

        $vectors = [];

        foreach ((array) $response->json('data', []) as $item) {
            if (! is_array($item) || ! is_array($item['embedding'] ?? null)) {
                continue;
            }

            $vectors[(int) ($item['index'] ?? count($vectors))] = array_map('floatval', $item['embedding']);
        }

        ksort($vectors);

        return array_values($vectors);
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    protected function guardConfigured(): void
    {
        if (blank($this->apiKey)) {
            throw new RuntimeException('LLM API key is not configured.');
        }
    }

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return $this->apiKey
            ? ['Authorization' => 'Bearer '.$this->apiKey, 'Content-Type' => 'application/json']
            : ['Content-Type' => 'application/json'];
    }
}
