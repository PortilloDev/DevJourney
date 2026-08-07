<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MilestoneType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property Carbon $achieved_at
 * @property string|null $icon
 * @property MilestoneType $type
 */
class Milestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'achieved_at',
        'icon',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'type' => MilestoneType::class,
            'achieved_at' => 'date',
        ];
    }

    /** @param Builder<Milestone> $query */
    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderByDesc('achieved_at');
    }
}
