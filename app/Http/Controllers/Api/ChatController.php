<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\AiUsage;
use App\Services\Rag\RagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ChatController
{
    public function __invoke(Request $request, RagService $rag): JsonResponse
    {
        $ip = $request->ip() ?? 'unknown';

        if ($this->requestLimitReached($ip)) {
            return response()->json([
                'error' => 'daily_request_limit',
                'message' => 'You have reached the daily chat limit. Try again tomorrow.',
            ], 429);
        }

        if ($this->tokenBudgetReached($ip)) {
            return response()->json([
                'error' => 'daily_token_limit',
                'message' => 'You have reached the daily usage budget. Try again tomorrow.',
            ], 429);
        }

        $question = trim((string) $request->input('question', ''));

        if ($question === '' || mb_strlen($question) > 1000) {
            return response()->json([
                'error' => 'invalid_question',
                'message' => 'Ask a question between 1 and 1000 characters.',
            ], 422);
        }

        $startedAt = microtime(true);

        try {
            $result = $rag->ask($question, $ip, $request->user()?->getKey());
        } catch (RuntimeException $e) {
            Log::warning('Chat error', ['message' => $e->getMessage()]);

            return response()->json([
                'error' => 'service_error',
                'message' => 'The assistant is temporarily unavailable.',
            ], 503);
        } catch (\Throwable $e) {
            Log::error('Chat unexpected error', ['exception' => $e]);

            return response()->json([
                'error' => 'service_error',
                'message' => 'Something went wrong. Please try again.',
            ], 503);
        }

        $this->recordLatency($ip, $startedAt);

        return response()->json($result);
    }

    protected function requestLimitReached(string $ip): bool
    {
        $key = 'ai:req:'.$ip.':'.now()->toDateString();

        if (! Cache::add($key, 1, 86400)) {
            Cache::increment($key);
        }

        return (int) Cache::get($key) > (int) config('rag.limits.requests_per_day', 20);
    }

    protected function tokenBudgetReached(string $ip): bool
    {
        $tokens = AiUsage::query()
            ->where('ip_address', $ip)
            ->whereDate('created_at', now()->toDateString())
            ->get()
            ->sum(fn (AiUsage $usage) => $usage->totalTokens());

        return $tokens >= (int) config('rag.limits.tokens_per_day', 20000);
    }

    protected function recordLatency(string $ip, float $startedAt): void
    {
        $last = AiUsage::query()
            ->where('ip_address', $ip)
            ->latest('created_at')
            ->first();

        if ($last !== null) {
            $last->update(['latency_ms' => (int) ((microtime(true) - $startedAt) * 1000)]);
        }
    }
}
