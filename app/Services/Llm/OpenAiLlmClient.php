<?php

declare(strict_types=1);

namespace App\Services\Llm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
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

    private int $completeTimeout;

    private int $streamTimeout;

    /**
     * Statuses worth retrying: rate limit and server errors.
     */
    private const RETRYABLE_STATUSES = [429, 500, 502, 503, 504];

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('rag.base_url'), '/');
        $this->model = (string) config('rag.chat_model');
        $this->apiKey = config('rag.api_key');
        $this->completeTimeout = (int) config('rag.timeouts.complete', 30);
        $this->streamTimeout = (int) config('rag.timeouts.stream', 30);
    }

    public function complete(array $messages, ?int $maxTokens = null, ?string $model = null, ?float $temperature = null): array
    {
        $this->guardConfigured();

        $model = $model ?? $this->model;

        $payload = static::payload($model, $messages, $maxTokens, stream: false, temperature: $temperature);

        $response = Http::withHeaders($this->headers())
            ->asJson()
            ->timeout($this->completeTimeout)
            ->retry(2, 200, fn (\Throwable $exception): bool => $this->shouldRetry($exception), throw: false)
            ->post($this->baseUrl.'/chat/completions', $payload);

        if ($response->failed()) {
            throw new RuntimeException("Chat completion failed: {$response->body()}");
        }

        $body = $response->json();

        return static::result($body, $model);
    }

    public function stream(array $messages, callable $onDelta, ?int $maxTokens = null, ?string $model = null): array
    {
        $this->guardConfigured();

        $model = $model ?? $this->model;

        $payload = static::payload($model, $messages, $maxTokens, stream: true);

        $response = Http::withHeaders($this->headers())
            ->withOptions(['stream' => true])
            ->timeout($this->streamTimeout)
            ->send('POST', $this->baseUrl.'/chat/completions', ['json' => $payload]);

        if ($response->failed()) {
            throw new RuntimeException("Chat stream failed: {$response->body()}");
        }

        $content = '';
        $tokensIn = 0;
        $tokensOut = 0;
        $usage = null;

        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (! $stream->eof() || $buffer !== '') {
            $buffer .= $stream->read(2048);

            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newline);
                $buffer = substr($buffer, $newline + 1);

                $data = $this->sseData($line);
                if ($data === null) {
                    continue;
                }

                if ($data === '[DONE]') {
                    break 2;
                }

                $decoded = json_decode($data, true);
                if (! is_array($decoded)) {
                    continue;
                }

                if (isset($decoded['usage']) && is_array($decoded['usage'])) {
                    $usage = $decoded['usage'];

                    continue;
                }

                $delta = $decoded['choices'][0]['delta']['content'] ?? null;
                if (is_string($delta) && $delta !== '') {
                    $content .= $delta;
                    ($onDelta)($delta);
                }
            }
        }

        $tokensIn = (int) ($usage['prompt_tokens'] ?? 0);
        $tokensOut = (int) ($usage['completion_tokens'] ?? ($tokensIn === 0 ? 0 : $this->estimateTokens($content)));

        return static::result([
            'choices' => [['message' => ['content' => $content]]],
            'usage' => ['prompt_tokens' => $tokensIn, 'completion_tokens' => $tokensOut],
            'model' => $model,
        ], $model);
    }

    protected function guardConfigured(): void
    {
        if (blank($this->apiKey)) {
            throw new RuntimeException('LLM API key is not configured.');
        }
    }

    /**
     * Retry only transient failures: connection errors and the statuses in
     * {@see self::RETRYABLE_STATUSES} (R3, D5). Streaming never retries.
     */
    private function shouldRetry(\Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException && $exception->response !== null) {
            return in_array($exception->response->status(), self::RETRYABLE_STATUSES, true);
        }

        return false;
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

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     */
    protected static function payload(string $model, array $messages, ?int $maxTokens, bool $stream, ?float $temperature = null): array
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature ?? 0.2,
            'stream' => $stream,
        ];

        if ($maxTokens !== null) {
            $payload['max_tokens'] = $maxTokens;
        }

        if ($stream) {
            $payload['stream_options'] = ['include_usage' => true];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{content: string, tokens_in: int, tokens_out: int, model: string}
     */
    protected static function result(array $body, string $defaultModel): array
    {
        return [
            'content' => $body['choices'][0]['message']['content'] ?? '',
            'tokens_in' => (int) ($body['usage']['prompt_tokens'] ?? 0),
            'tokens_out' => (int) ($body['usage']['completion_tokens'] ?? 0),
            'model' => $body['model'] ?? $defaultModel,
        ];
    }

    private function sseData(string $line): ?string
    {
        $line = rtrim($line, "\r");

        if (! str_starts_with($line, 'data:')) {
            return null;
        }

        $data = ltrim(substr($line, 5));

        return $data === '' ? null : $data;
    }

    private function estimateTokens(string $content): int
    {
        return (int) ceil(mb_strlen($content) / 4);
    }
}
