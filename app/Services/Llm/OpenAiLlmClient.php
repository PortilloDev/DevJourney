<?php

declare(strict_types=1);

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client for any OpenAI-compatible `/chat/completions` endpoint. Use it for
 * DeepSeek (`deepseek-chat`), OpenAI or a local (Ollama) model.
 */
class OpenAiLlmClient implements LlmClient
{
    private string $baseUrl;

    private string $model;

    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('rag.base_url'), '/');
        $this->model = (string) config('rag.chat_model');
        $this->apiKey = config('rag.api_key');
    }

    public function complete(array $messages, ?int $maxTokens = null): array
    {
        $this->guardConfigured();

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.2,
        ];

        if ($maxTokens !== null) {
            $payload['max_tokens'] = $maxTokens;
        }

        $response = Http::withHeaders($this->headers())
            ->asJson()
            ->timeout(90)
            ->post($this->baseUrl.'/chat/completions', $payload);

        if ($response->failed()) {
            throw new RuntimeException("Chat completion failed: {$response->body()}");
        }

        $body = $response->json();

        return [
            'content' => $body['choices'][0]['message']['content'] ?? '',
            'tokens_in' => (int) ($body['usage']['prompt_tokens'] ?? 0),
            'tokens_out' => (int) ($body['usage']['completion_tokens'] ?? 0),
            'model' => $body['model'] ?? $this->model,
        ];
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
