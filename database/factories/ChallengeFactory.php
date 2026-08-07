<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChallengeDifficulty;
use App\Enums\ChallengeTopic;
use App\Enums\EnglishLevel;
use App\Enums\PostStatus;
use App\Models\Challenge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Challenge>
 */
class ChallengeFactory extends Factory
{
    protected $model = Challenge::class;

    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(4),
            'topic' => fake()->randomElement(ChallengeTopic::cases()),
            'difficulty' => fake()->randomElement(ChallengeDifficulty::cases()),
            'english_level' => fake()->randomElement(EnglishLevel::cases()),
            'question_md' => fake()->paragraph(),
            'answer_md' => 'The secret answer is '.fake()->unique()->word().'.',
            'explanation_md' => fake()->paragraph(),
            'status' => PostStatus::Published,
            'published_at' => now()->subDays(fake()->numberBetween(1, 10)),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Draft,
            'published_at' => null,
        ]);
    }
}
