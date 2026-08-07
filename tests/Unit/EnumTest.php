<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ChallengeDifficulty;
use App\Enums\EnglishLevel;
use PHPUnit\Framework\TestCase;

class EnumTest extends TestCase
{
    public function test_every_english_level_has_a_badge_class(): void
    {
        foreach (EnglishLevel::cases() as $level) {
            $this->assertNotEmpty($level->badgeClasses());
            $this->assertNotEmpty($level->label());
        }
    }

    public function test_english_levels_are_ranked_in_order(): void
    {
        $this->assertLessThan(EnglishLevel::B1->rank(), EnglishLevel::A2->rank());
        $this->assertLessThan(EnglishLevel::C2->rank(), EnglishLevel::C1->rank());
    }

    public function test_difficulty_options_map_values_to_labels(): void
    {
        $options = ChallengeDifficulty::options();

        $this->assertSame('Beginner', $options['beginner']);
        $this->assertSame('Expert', $options['expert']);
    }
}
