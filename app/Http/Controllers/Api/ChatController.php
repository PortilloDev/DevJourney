<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\AiConversation;
use App\Models\AiUsage;
use App\Models\Post;
use App\Services\Rag\ConversationToken;
use App\Services\Rag\RagService;
use App\Services\SiteSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController
{
    public function __invoke(Request $request, RagService $rag, ConversationToken $tokens): JsonResponse|StreamedResponse
    {
        if (app(SiteSettingService::class)->chatApiBlocked()) {
            return response()->json([
                'error' => 'chat_blocked',
                'message' => 'The chat is currently disabled.',
            ], 403);
        }

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

        $sessionId = $tokens->conversationId((string) $request->input('conversation_token', ''));

        if ($sessionId === null) {
            $sessionId = (string) Str::uuid();
        }

        $conversation = AiConversation::firstOrCreate(
            ['session_id' => $sessionId],
            [
                'ip_address' => $ip,
                'post_id' => $post->getKey(),
                'started_at' => now(),
                'last_activity_at' => now(),
            ],
        );

        $conversationToken = $tokens->issue($sessionId);

        if ($request->boolean('stream')) {
            return $this->streamAnswer($request, $rag, $post, $conversation->id, $question, $ip, $conversationToken);
        }

        return $this->jsonAnswer($request, $rag, $post, $conversation->id, $question, $ip, $conversationToken);
    }

    protected function jsonAnswer(Request $request, RagService $rag, Post $post, int $conversationId, string $question, string $ip, string $conversationToken): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $result = $rag->askForPost($post, $question, $ip, $request->user()?->getKey(), $conversationId);
        } catch (RuntimeException $e) {
            Log::warning('Chat error', ['message' => $e->getMessage()]);

            return response()->json(['error' => 'service_error', 'message' => 'The assistant is temporarily unavailable.'], 503);
        } catch (\Throwable $e) {
            Log::error('Chat unexpected error', ['exception' => $e]);

            return response()->json(['error' => 'service_error', 'message' => 'Something went wrong. Please try again.'], 503);
        }

        $this->recordLatency($ip, $startedAt);

        return response()->json(['conversation_token' => $conversationToken] + $result);
    }

    protected function streamAnswer(Request $request, RagService $rag, Post $post, int $conversationId, string $question, string $ip, string $conversationToken): StreamedResponse
    {
        $startedAt = microtime(true);

        return response()->stream(function () use ($rag, $post, $conversationId, $question, $ip, $request, $startedAt, $conversationToken): void {
            $emitted = false;

            try {
                $meta = $rag->askPostStream($post, $question, function (string $delta) use (&$emitted): void {
                    $emitted = true;
                    $this->sendSse(['delta' => $delta]);
                }, $ip, $request->user()?->getKey(), $conversationId);

                $this->recordLatency($ip, $startedAt);

                $this->sendSse([
                    'done' => true,
                    'incomplete' => false,
                    'refused' => $meta['refused'],
                    'sources' => $meta['sources'],
                    'tokens_in' => $meta['tokens_in'],
                    'tokens_out' => $meta['tokens_out'],
                    'context_chunks' => $meta['context_chunks'],
                    'model' => $meta['model'],
                    'conversation_token' => $conversationToken,
                ]);
            } catch (RuntimeException $e) {
                Log::warning('Chat stream error', ['message' => $e->getMessage()]);
                $this->sendSse([
                    'error' => 'service_error',
                    'message' => 'The assistant is temporarily unavailable.',
                    'incomplete' => $emitted,
                ]);
            } catch (\Throwable $e) {
                Log::error('Chat stream unexpected error', ['exception' => $e]);
                $this->sendSse([
                    'error' => 'service_error',
                    'message' => 'Something went wrong. Please try again.',
                    'incomplete' => $emitted,
                ]);
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
