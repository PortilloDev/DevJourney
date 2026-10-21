<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\AiConversation;
use App\Models\AiUsage;
use App\Services\Rag\ConversationToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Records the visitor's rating (useful / not useful) for a delivered chat turn.
 *
 * The rating only lands on a turn that belongs to the conversation the visitor
 * holds and that was logged recently, so a turn identifier cannot be used to
 * rate someone else's answers.
 */
class ChatFeedbackController
{
    public function __invoke(Request $request, ConversationToken $tokens): JsonResponse
    {
        $sessionId = $tokens->conversationId((string) $request->input('conversation', ''));

        if ($sessionId === null) {
            return response()->json(['error' => 'invalid_conversation'], 422);
        }

        $usageId = $request->input('usage_id');
        $rating = $request->input('rating');

        if (! is_numeric($usageId) || ! is_numeric($rating) || ! in_array((int) $rating, [1, -1], true)) {
            return response()->json(['error' => 'invalid_feedback'], 422);
        }

        $conversation = AiConversation::query()->where('session_id', $sessionId)->first();

        if ($conversation === null) {
            return response()->json(['error' => 'unknown_conversation'], 404);
        }

        $usage = AiUsage::query()
            ->where('id', (int) $usageId)
            ->where('conversation_id', $conversation->getKey())
            ->where('created_at', '>=', now()->subHour())
            ->first();

        if ($usage === null) {
            return response()->json(['error' => 'unknown_turn'], 404);
        }

        $usage->update(['rating' => (int) $rating]);

        return response()->json(['rating' => (int) $rating]);
    }
}
