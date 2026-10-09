<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single AI chat turn (one question + answer). Enriched with the prompt sent
 * to the LLM, the retrieval used and observability fields for the admin viewer.
 * Also the basis of the per-IP daily usage budget.
 *
 * @property int $id
 * @property int|null $conversation_id
 * @property int|null $post_id
 * @property string $ip_address
 * @property int|null $user_id
 * @property string $question
 * @property string|null $rewritten_question
 * @property string|null $prompt
 * @property string|null $answer
 * @property bool $refused
 * @property bool $cache_hit
 * @property string|null $refused_stage
 * @property string $model
 * @property string|null $provider
 * @property string|null $embedding_model
 * @property int|null $top_k
 * @property int $tokens_in
 * @property int $tokens_out
 * @property int $context_chunks
 * @property int $latency_ms
 * @property string|null $sources
 * @property Carbon $created_at
 */
class AiUsage extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'ai_usage';

    protected $fillable = [
        'conversation_id',
        'post_id',
        'ip_address',
        'user_id',
        'question',
        'rewritten_question',
        'prompt',
        'answer',
        'refused',
        'cache_hit',
        'refused_stage',
        'model',
        'provider',
        'embedding_model',
        'top_k',
        'tokens_in',
        'tokens_out',
        'context_chunks',
        'latency_ms',
        'sources',
    ];

    protected function casts(): array
    {
        return [
            'conversation_id' => 'integer',
            'post_id' => 'integer',
            'user_id' => 'integer',
            'refused' => 'boolean',
            'cache_hit' => 'boolean',
            'top_k' => 'integer',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'context_chunks' => 'integer',
            'latency_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function totalTokens(): int
    {
        return $this->tokens_in + $this->tokens_out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function retrievedSources(): array
    {
        if (blank($this->sources)) {
            return [];
        }

        $decoded = json_decode((string) $this->sources, true);

        return is_array($decoded) ? $decoded : [];
    }
}
