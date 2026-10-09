<?php

declare(strict_types=1);

namespace App\Services\Llm;

use RuntimeException;

/**
 * Wraps an {@see LlmClient} with a {@see CircuitBreaker}: while the circuit is
 * open it fails fast with a {@see RuntimeException} (which ChatController already
 * maps to a 503 `service_error`) and never reaches the provider. Rejections by
 * relevance never get here, so only provider failures count (R2, D3, D4).
 */
class CircuitBreakerLlmClient implements LlmClient
{
    public function __construct(
        private readonly LlmClient $inner,
        private readonly CircuitBreaker $circuit,
    ) {}

    public function complete(array $messages, ?int $maxTokens = null, ?string $model = null, ?float $temperature = null): array
    {
        $this->guardClosed();

        try {
            $result = $this->inner->complete($messages, $maxTokens, $model, $temperature);
            $this->circuit->recordSuccess();

            return $result;
        } catch (\Throwable $e) {
            $this->circuit->recordFailure();

            throw $e;
        }
    }

    public function stream(array $messages, callable $onDelta, ?int $maxTokens = null, ?string $model = null): array
    {
        $this->guardClosed();

        try {
            $result = $this->inner->stream($messages, $onDelta, $maxTokens, $model);
            $this->circuit->recordSuccess();

            return $result;
        } catch (\Throwable $e) {
            $this->circuit->recordFailure();

            throw $e;
        }
    }

    private function guardClosed(): void
    {
        if ($this->circuit->isOpen()) {
            throw new RuntimeException('The chat provider is temporarily unavailable (circuit open).');
        }
    }
}
