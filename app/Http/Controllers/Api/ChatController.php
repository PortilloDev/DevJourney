<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\QuotaExceededException;
use App\Models\AiConversation;
use App\Models\AiUsage;
use App\Models\Post;
use App\Services\Rag\ChatQuota;
use App\Services\Rag\ConversationToken;
use App\Services\Rag\IpHasher;
use App\Services\Rag\RagService;
use App\Services\SiteSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Single derivation point: everything downstream (quota, conversation and
        // the recorded turn) uses this non-reversible value, never the raw IP.
        $ip = app(IpHasher::class)->hash($ip);

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

        $quota = app(ChatQuota::class);
        $quotaCode = $quota->blockedCode($ip);

        if ($quotaCode !== null) {
            return response()->json([
                'error' => $quotaCode,
                'message' => 'You have reached the daily chat limit. Try again tomorrow.',
            ], 429)->header('Retry-After', (string) ($quota->availableIn($ip) ?? $this->secondsUntilReset()));
        }

        if ($this->tokenBudgetReached($ip)) {
            return response()->json([
                'error' => 'daily_token_limit',
                'message' => 'You have reached the daily usage budget. Try again tomorrow.',
            ], 429)->header('Retry-After', (string) $this->secondsUntilReset());
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
        } catch (QuotaExceededException $e) {
            return response()->json([
                'error' => $e->errorCode,
                'message' => 'You have reached the daily chat limit. Try again tomorrow.',
            ], 429)->header('Retry-After', (string) (app(ChatQuota::class)->availableIn($ip) ?? $this->secondsUntilReset()));
        } catch (RuntimeException $e) {
            Log::warning('Chat error', ['message' => $e->getMessage()]);

            return response()->json(['error' => 'service_error', 'message' => 'The assistant is temporarily unavailable.'], 503);
        } catch (\Throwable $e) {
            Log::error('Chat unexpected error', ['exception' => $e]);

            return response()->json(['error' => 'service_error', 'message' => 'Something went wrong. Please try again.'], 503);
        }

        $this->recordLatency($result['usage_id'] ?? null, $startedAt);

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

                $this->recordLatency($meta['usage_id'] ?? null, $startedAt);

                $this->sendSse([
                    'done' => true,
                    'incomplete' => false,
                    'refused' => $meta['refused'],
                    'sources' => $meta['sources'],
                    'tokens_in' => $meta['tokens_in'],
                    'tokens_out' => $meta['tokens_out'],
                    'context_chunks' => $meta['context_chunks'],
                    'model' => $meta['model'],
                    'truncated' => $meta['truncated'],
                    'related' => $meta['related'],
                    'usage_id' => $meta['usage_id'] ?? null,
                    'conversation_token' => $conversationToken,
                ]);
            } catch (QuotaExceededException $e) {
                $this->sendSse([
                    'error' => $e->errorCode,
                    'message' => 'You have reached the daily chat limit. Try again tomorrow.',
                    'incomplete' => $emitted,
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

    protected function tokenBudgetReached(string $ip): bool
    {
        $tokens = AiUsage::query()
            ->where('ip_address', $ip)
            ->whereDate('created_at', now()->toDateString())
            ->sum(DB::raw('tokens_in + tokens_out'));

        return (int) $tokens >= (int) config('rag.limits.tokens_per_day', 20000);
    }

    protected function secondsUntilReset(): int
    {
        return max(1, now()->endOfDay()->getTimestamp() - now()->getTimestamp());
    }

    protected function recordLatency(?int $usageId, float $startedAt): void
    {
        if ($usageId === null) {
            return;
        }

        $usage = AiUsage::find($usageId);

        if ($usage !== null) {
            $usage->update(['latency_ms' => (int) ((microtime(true) - $startedAt) * 1000)]);
        }
    }
}
