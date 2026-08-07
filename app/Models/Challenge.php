<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChallengeDifficulty;
use App\Enums\ChallengeTopic;
use App\Enums\EnglishLevel;
use App\Enums\PostStatus;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\Taggable;
use App\Services\MarkdownRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property ChallengeDifficulty $difficulty
 * @property ChallengeTopic $topic
 * @property string $question_md
 * @property string $question_html
 * @property string $answer_md
 * @property string $answer_html
 * @property string|null $explanation_md
 * @property string|null $explanation_html
 * @property EnglishLevel $english_level
 * @property PostStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $updated_at
 */
class Challenge extends Model
{
    use HasFactory;
    use HasSlug;
    use Taggable;

    protected $fillable = [
        'title',
        'slug',
        'difficulty',
        'topic',
        'question_md',
        'question_html',
        'answer_md',
        'answer_html',
        'explanation_md',
        'explanation_html',
        'english_level',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'difficulty' => ChallengeDifficulty::class,
            'topic' => ChallengeTopic::class,
            'english_level' => EnglishLevel::class,
            'status' => PostStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Challenge $challenge): void {
            $renderer = app(MarkdownRenderer::class);
            $challenge->question_html = $renderer->toHtml($challenge->question_md);
            $challenge->answer_html = $renderer->toHtml($challenge->answer_md);
            $challenge->explanation_html = $renderer->toHtml($challenge->explanation_md);
        });
    }

    /** @param Builder<Challenge> $query */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PostStatus::Published->value)
            ->where('published_at', '<=', now());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
