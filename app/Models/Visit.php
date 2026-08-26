<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A single browsing session on the public site. Rows are grouped per visitor
 * (via a persistent cookie token) and reused while the visitor stays active
 * within the tracker's inactivity window.
 *
 * @property int $id
 * @property string $visitor_token
 * @property int|null $user_id
 * @property string|null $ip_address
 * @property string|null $country
 * @property string|null $user_agent
 * @property string|null $device
 * @property string|null $referer
 * @property string|null $entry_url
 * @property string|null $entry_path
 * @property string|null $entry_page_type
 * @property int $page_views
 * @property Carbon|null $started_at
 * @property Carbon|null $last_activity_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Visit extends Model
{
    protected $fillable = [
        'visitor_token',
        'user_id',
        'ip_address',
        'country',
        'user_agent',
        'device',
        'referer',
        'entry_url',
        'entry_path',
        'entry_page_type',
        'page_views',
        'started_at',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'page_views' => 'integer',
            'user_id' => 'integer',
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            // Personal data is encrypted at rest (see the security checklist).
            'ip_address' => 'encrypted',
            'user_agent' => 'encrypted',
            'referer' => 'encrypted',
        ];
    }

    /**
     * Total active time for the session, derived from the first and last
     * recorded activity. Only meaningful once the session has ended (or when
     * enough time has passed without new activity).
     */
    public function durationSeconds(): int
    {
        if ($this->started_at === null || $this->last_activity_at === null) {
            return 0;
        }

        return max(0, $this->last_activity_at->diffInSeconds($this->started_at));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(VisitEvent::class);
    }

    public function recordEvent(
        ActivityEventType $type,
        ?string $pageType = null,
        ?string $path = null,
        ?string $url = null,
        ?string $reference = null,
        ?string $title = null,
    ): VisitEvent {
        return $this->events()->create([
            'type' => $type->value,
            'page_type' => $pageType,
            'path' => $path,
            'url' => $url,
            'reference' => $reference,
            'title' => $title,
        ]);
    }
}
