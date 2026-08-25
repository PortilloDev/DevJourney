<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single recorded interaction within a visit, e.g. a page view or click.
 *
 * @property int $id
 * @property int $visit_id
 * @property ActivityEventType $type
 * @property string|null $page_type
 * @property string|null $path
 * @property string|null $url
 * @property string|null $reference
 * @property string|null $title
 * @property Carbon $created_at
 */
class VisitEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'visit_id',
        'type',
        'page_type',
        'path',
        'url',
        'reference',
        'title',
    ];

    protected function casts(): array
    {
        return [
            'type' => ActivityEventType::class,
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
