<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Auto-generates a unique slug from the model's `title` (or a custom source
 * column) when one is not supplied. Handles duplicates with an incrementing
 * suffix: my-title, my-title-2, my-title-3, …
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::saving(function (self $model): void {
            $source = $model->slugSource();

            if (blank($model->slug) && filled($model->{$source})) {
                $model->slug = $model->generateUniqueSlug(Str::slug($model->{$source}));
            }
        });
    }

    protected function slugSource(): string
    {
        return 'title';
    }

    protected function generateUniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;

        while (
            static::query()
                ->where('slug', $slug)
                ->when($this->exists, fn ($q) => $q->whereKeyNot($this->getKey()))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
