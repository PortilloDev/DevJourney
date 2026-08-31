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
     * @return array{content: string, tokens_in: int, tokens_out: int, model: string}
     */
    public function complete(array $messages, ?int $maxTokens = null): array;
}
