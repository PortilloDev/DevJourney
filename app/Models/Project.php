<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PostStatus;
use App\Models\Concerns\HasSlug;
use App\Services\MarkdownRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $description
 * @property string|null $body_md
 * @property string|null $body_html
 * @property string|null $repo_url
 * @property string|null $demo_url
 * @property array<int, string> $stack
 * @property string|null $featured_image
 * @property PostStatus $status
 * @property int $sort_order
 * @property Carbon|null $updated_at
 */
class Project extends Model
{
    use HasFactory;
    use HasSlug;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'body_md',
        'body_html',
        'repo_url',
        'demo_url',
        'stack',
        'featured_image',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'stack' => 'array',
            'status' => PostStatus::class,
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Project $project): void {
            $project->body_html = app(MarkdownRenderer::class)->toHtml($project->body_md);
        });
    }

    /** @param Builder<Project> $query */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Published->value);
    }

    /** @param Builder<Project> $query */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('created_at');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
