<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EnglishLevel;
use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the long-form journal entries stored as Markdown files next to this
 * class. Each file carries a tiny front-matter block (title, category, level,
 * tags, excerpt, featured); the body is the article itself.
 *
 * Run only this seeder with:
 *   php artisan db:seed --class=LongFormPostSeeder
 */
class LongFormPostSeeder extends Seeder
{
    public function run(): void
    {
        $files = glob(database_path('seeders/posts/*.md')) ?: [];
        sort($files);

        foreach ($files as $index => $file) {
            [$meta, $body] = $this->parse($file);

            $title = trim((string) ($meta['title'] ?? ''));

            if ($title === '' || $body === '') {
                continue;
            }

            $post = Post::updateOrCreate(
                ['slug' => Str::slug($title)],
                [
                    'title' => $title,
                    'excerpt' => isset($meta['excerpt']) && $meta['excerpt'] !== '' ? $meta['excerpt'] : null,
                    'body_md' => $body,
                    'category_id' => $this->categoryId($meta['category'] ?? null),
                    'english_level' => EnglishLevel::from($meta['level'] ?? 'B1'),
                    'status' => PostStatus::Published,
                    'featured' => filter_var($meta['featured'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'published_at' => now()->subDays(($index + 1) * 4),
                ],
            );

            $post->tags()->sync($this->tagIds($meta['tags'] ?? ''));
        }
    }

    /**
     * @return array{0: array<string, string>, 1: string}
     */
    private function parse(string $file): array
    {
        $contents = (string) file_get_contents($file);

        if (! str_starts_with($contents, "---\n")) {
            return [[], trim($contents)];
        }

        $parts = explode("\n---\n", substr($contents, 4), 2);
        $frontMatter = $parts[0];
        $body = $parts[1] ?? '';
        $meta = [];

        foreach (explode("\n", $frontMatter) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $meta[trim($key)] = trim($value);
        }

        return [$meta, trim($body)];
    }

    private function categoryId(?string $name): ?int
    {
        if ($name === null || $name === '') {
            return null;
        }

        return Category::query()->where('name', $name)->value('id');
    }

    /**
     * @return list<int>
     */
    private function tagIds(string $names): array
    {
        $ids = [];

        foreach (array_filter(array_map('trim', explode(',', $names))) as $name) {
            $tag = Tag::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );

            $ids[] = (int) $tag->getKey();
        }

        return $ids;
    }
}
