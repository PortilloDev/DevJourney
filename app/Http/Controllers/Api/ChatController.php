<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\AiUsage;
use App\Models\Post;
use App\Services\Rag\RagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController
{
    public function __invoke(Request $request, RagService $rag): JsonResponse|StreamedResponse
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

        $post = Post::query()->published()->where('slug', (string) $request->input('post', ''))->first();

        if ($post === null) {
            return response()->json([
                'error' => 'unknown_post',
                'message' => 'This article is not available.',
            ], 404);
        }

        $question = trim((string) $request->input('question', ''));

        if ($question === '' || mb_strlen($question) > 1000) {
            return response()->json([
                'error' => 'invalid_question',
                'message' => 'Ask a question between 1 and 1000 characters.',
            ], 422);
        }

        if ($request->boolean('stream')) {
            return $this->streamAnswer($request, $rag, $post, $question, $ip);
        }

        return $this->jsonAnswer($request, $rag, $post, $question, $ip);
    }

    protected function jsonAnswer(Request $request, RagService $rag, Post $post, string $question, string $ip): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $result = $rag->askForPost($post, $question, $ip, $request->user()?->getKey());
        } catch (RuntimeException $e) {
            Log::warning('Chat error', ['message' => $e->getMessage()]);

            return response()->json(['error' => 'service_error', 'message' => 'The assistant is temporarily unavailable.'], 503);
        } catch (\Throwable $e) {
            Log::error('Chat unexpected error', ['exception' => $e]);

            return response()->json(['error' => 'service_error', 'message' => 'Something went wrong. Please try again.'], 503);
        }

        $this->recordLatency($ip, $startedAt);

        return response()->json($result);
    }

    protected function streamAnswer(Request $request, RagService $rag, Post $post, string $question, string $ip): StreamedResponse
    {
        $startedAt = microtime(true);

        return response()->stream(function () use ($rag, $post, $question, $ip, $request, $startedAt): void {
            try {
                $meta = $rag->askPostStream($post, $question, function (string $delta): void {
                    $this->sendSse(['delta' => $delta]);
                }, $ip, $request->user()?->getKey());

                $this->recordLatency($ip, $startedAt);

                $this->sendSse([
                    'done' => true,
                    'refused' => $meta['refused'],
                    'sources' => $meta['sources'],
                    'tokens_in' => $meta['tokens_in'],
                    'tokens_out' => $meta['tokens_out'],
                    'context_chunks' => $meta['context_chunks'],
                    'model' => $meta['model'],
                ]);
            } catch (RuntimeException $e) {
                Log::warning('Chat stream error', ['message' => $e->getMessage()]);
                $this->sendSse(['error' => 'service_error', 'message' => 'The assistant is temporarily unavailable.']);
            } catch (\Throwable $e) {
                Log::error('Chat stream unexpected error', ['exception' => $e]);
                $this->sendSse(['error' => 'service_error', 'message' => 'Something went wrong. Please try again.']);
            }

            echo "data: [DONE]\n\n";
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function sendSse(array $data): void
    {
        echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
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
