<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EnglishLevel;
use App\Enums\PostStatus;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\Taggable;
use App\Services\MarkdownRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string $body_md
 * @property string $body_html
 * @property int|null $category_id
 * @property EnglishLevel $english_level
 * @property string|null $featured_image
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property int $reading_minutes
 * @property PostStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $updated_at
 */
class Post extends Model
{
    use HasFactory;
    use HasSlug;
    use Taggable;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'body_md',
        'body_html',
        'category_id',
        'english_level',
        'featured_image',
        'seo_title',
        'seo_description',
        'reading_minutes',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'english_level' => EnglishLevel::class,
            'status' => PostStatus::class,
            'reading_minutes' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Post $post): void {
            $renderer = app(MarkdownRenderer::class);
            $post->body_html = $renderer->toHtml($post->body_md);
            $post->reading_minutes = $renderer->readingMinutes($post->body_md);
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @param Builder<Post> $query */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PostStatus::Published->value)
            ->where('published_at', '<=', now());
    }

    /** @param Builder<Post> $query */
    public function scopeLatestPublished(Builder $query): Builder
    {
        return $query->published()->orderByDesc('published_at');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Table of contents extracted from the pre-rendered HTML.
     *
     * @return array<int, array{level: int, text: string, id: string}>
     */
    public function tableOfContents(): array
    {
        return app(MarkdownRenderer::class)->extractToc($this->body_html);
    }
}
