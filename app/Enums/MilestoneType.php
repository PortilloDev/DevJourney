<?php

declare(strict_types=1);

namespace App\Enums;

enum MilestoneType: string
{
    case English = 'english';
    case Technical = 'technical';
    case Career = 'career';
    case Project = 'project';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function colorClasses(): string
    {
        return match ($this) {
            self::English => 'bg-purple-500/10 text-purple-400 ring-purple-500/30',
            self::Technical => 'bg-teal-500/10 text-teal-400 ring-teal-500/30',
            self::Career => 'bg-blue-500/10 text-blue-400 ring-blue-500/30',
            self::Project => 'bg-amber-500/10 text-amber-400 ring-amber-500/30',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $t) => [$t->value => $t->label()])
            ->all();
    }
}
