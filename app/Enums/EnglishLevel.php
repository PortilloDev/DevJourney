<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * CEFR English proficiency levels. Every content piece is tagged with the
 * author's English level at the time of writing.
 */
enum EnglishLevel: string
{
    case A1 = 'A1';
    case A2 = 'A2';
    case B1 = 'B1';
    case B2 = 'B2';
    case C1 = 'C1';
    case C2 = 'C2';

    public function label(): string
    {
        return match ($this) {
            self::A1 => 'A1 · Beginner',
            self::A2 => 'A2 · Elementary',
            self::B1 => 'B1 · Intermediate',
            self::B2 => 'B2 · Upper-Intermediate',
            self::C1 => 'C1 · Advanced',
            self::C2 => 'C2 · Proficient',
        };
    }

    /**
     * Tailwind colour classes used by the English level badge component.
     * A2 = orange, B1 = yellow, B2 = green, C1 = blue, C2 = purple.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::A1 => 'bg-red-500/10 text-red-400 ring-red-500/30',
            self::A2 => 'bg-orange-500/10 text-orange-400 ring-orange-500/30',
            self::B1 => 'bg-yellow-500/10 text-yellow-400 ring-yellow-500/30',
            self::B2 => 'bg-green-500/10 text-green-400 ring-green-500/30',
            self::C1 => 'bg-blue-500/10 text-blue-400 ring-blue-500/30',
            self::C2 => 'bg-purple-500/10 text-purple-400 ring-purple-500/30',
        };
    }

    /**
     * Numeric rank, useful for progression charts.
     */
    public function rank(): int
    {
        return match ($this) {
            self::A1 => 1,
            self::A2 => 2,
            self::B1 => 3,
            self::B2 => 4,
            self::C1 => 5,
            self::C2 => 6,
        };
    }

    /**
     * @return array<string, string> value => label, for Filament select options.
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $level) => [$level->value => $level->label()])
            ->all();
    }
}
