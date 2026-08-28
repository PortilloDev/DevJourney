<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single structured application log or audit event written to the database.
 *
 * @property int $id
 * @property string $level
 * @property string $message
 * @property array|null $context
 * @property string|null $exception
 * @property string|null $trace
 * @property string|null $url
 * @property string|null $method
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property int|null $user_id
 * @property Carbon $created_at
 */
class AppLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'level',
        'message',
        'context',
        'exception',
        'trace',
        'url',
        'method',
        'ip_address',
        'user_agent',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'user_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function levelClass(): string
    {
        return match ($this->level) {
            'emergency', 'alert', 'critical', 'error' => 'danger',
            'warning' => 'warning',
            'notice', 'info' => 'info',
            default => 'gray',
        };
    }
}
