<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EnglishLevel;
use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(5);

        return [
            'title' => $title,
            'excerpt' => fake()->sentence(12),
            'body_md' => "## Intro\n\n".fake()->paragraph()."\n\n## Details\n\n".fake()->paragraph(),
            'english_level' => fake()->randomElement(EnglishLevel::cases()),
            'status' => PostStatus::Published,
            'published_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Published,
            'published_at' => now()->addWeek(),
        ]);
    }
}
