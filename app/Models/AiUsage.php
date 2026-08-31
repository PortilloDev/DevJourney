<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single AI chat usage record (public chat endpoint). Used to enforce daily
 * budgets per visitor and to analyse what people ask.
 *
 * @property int $id
 * @property string $ip_address
 * @property int|null $user_id
 * @property string $question
 * @property string $model
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
        'ip_address',
        'user_id',
        'question',
        'model',
        'tokens_in',
        'tokens_out',
        'context_chunks',
        'latency_ms',
        'sources',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'context_chunks' => 'integer',
            'latency_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function totalTokens(): int
    {
        return $this->tokens_in + $this->tokens_out;
    }
}
