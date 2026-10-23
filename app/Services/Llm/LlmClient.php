<?php

declare(strict_types=1);

namespace App\Services\Llm;

/**
 * OpenAI-compatible chat completion client (OpenAI, DeepSeek, Ollama, …).
 */
interface LlmClient
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  string|null  $model  Overrides the configured model when given.
     * @param  float|null  $temperature  Overrides the client default when given.
     * @return array{content: string, tokens_in: int, tokens_out: int, model: string, finish_reason?: string|null}
     */
    public function complete(array $messages, ?int $maxTokens = null, ?string $model = null, ?float $temperature = null): array;

    /**
     * Stream a chat completion token-by-token. `$onDelta` receives each new
     * piece of text; the returned array carries the final usage metadata.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @param  string|null  $model  Overrides the configured model when given.
     * @return array{content: string, tokens_in: int, tokens_out: int, model: string, finish_reason?: string|null}
     */
    public function stream(array $messages, callable $onDelta, ?int $maxTokens = null, ?string $model = null): array;
}
