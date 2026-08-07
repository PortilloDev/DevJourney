<?php

declare(strict_types=1);

namespace App\Enums;

enum ChallengeDifficulty: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';
    case Expert = 'expert';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Beginner => 'bg-green-500/10 text-green-400 ring-green-500/30',
            self::Intermediate => 'bg-yellow-500/10 text-yellow-400 ring-yellow-500/30',
            self::Advanced => 'bg-orange-500/10 text-orange-400 ring-orange-500/30',
            self::Expert => 'bg-red-500/10 text-red-400 ring-red-500/30',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $d) => [$d->value => $d->label()])
            ->all();
    }
}
