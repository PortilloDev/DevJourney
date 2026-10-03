<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A chat conversation: the group of turns produced in one page session.
 *
 * @property int $id
 * @property string $session_id
 * @property string|null $ip_address
 * @property int|null $post_id
 * @property Carbon|null $started_at
 * @property Carbon|null $last_activity_at
 * @property int $turns_count
 * @property int $tokens_total
 */
class AiConversation extends Model
{
    protected $fillable = [
        'session_id',
        'ip_address',
        'post_id',
        'started_at',
        'last_activity_at',
        'turns_count',
        'tokens_total',
    ];

    protected function casts(): array
    {
        return [
            'post_id' => 'integer',
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'turns_count' => 'integer',
            'tokens_total' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function turns(): HasMany
    {
        return $this->hasMany(AiUsage::class, 'conversation_id')->orderBy('created_at');
    }

    /**
     * Delete conversations whose last activity falls in a date range (inclusive).
     * Turns are removed explicitly because SQLite (tests) cannot enforce the FK
     * cascade added via ALTER on an existing table.
     */
    public static function deleteBetween(?string $from, ?string $until): int
    {
        $query = static::query();

        if (filled($from)) {
            $query->whereDate('last_activity_at', '>=', $from);
        }

        if (filled($until)) {
            $query->whereDate('last_activity_at', '<=', $until);
        }

        return self::deleteConversations($query->pluck('id')->all());
    }

    public static function deleteAllLogs(): int
    {
        AiUsage::query()->delete();

        return static::query()->delete();
    }

    public static function pruneOlderThanDays(int $days): int
    {
        $ids = static::query()
            ->where('last_activity_at', '<', now()->subDays($days))
            ->pluck('id')
            ->all();

        return self::deleteConversations($ids);
    }

    /**
     * @param  list<int>  $ids
     */
    private static function deleteConversations(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        AiUsage::query()->whereIn('conversation_id', $ids)->delete();

        return static::query()->whereIn('id', $ids)->delete();
    }
}
