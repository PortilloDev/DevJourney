<?php

declare(strict_types=1);

namespace App\Enums;

enum ChallengeTopic: string
{
    case SystemDesign = 'system-design';
    case Ddd = 'ddd';
    case Cqrs = 'cqrs';
    case Hexagonal = 'hexagonal';
    case Solid = 'solid';
    case DesignPatterns = 'design-patterns';
    case Databases = 'databases';
    case Apis = 'apis';
    case Testing = 'testing';
    case Devops = 'devops';
    case Microservices = 'microservices';
    case Security = 'security';
    case Performance = 'performance';

    public function label(): string
    {
        return match ($this) {
            self::SystemDesign => 'System Design',
            self::Ddd => 'DDD',
            self::Cqrs => 'CQRS',
            self::Hexagonal => 'Hexagonal',
            self::Solid => 'SOLID',
            self::DesignPatterns => 'Design Patterns',
            self::Databases => 'Databases',
            self::Apis => 'APIs',
            self::Testing => 'Testing',
            self::Devops => 'DevOps',
            self::Microservices => 'Microservices',
            self::Security => 'Security',
            self::Performance => 'Performance',
        };
    }

    /**
     * Heroicon name shown on challenge cards.
     */
    public function icon(): string
    {
        return match ($this) {
            self::SystemDesign => 'heroicon-o-squares-2x2',
            self::Ddd => 'heroicon-o-cube',
            self::Cqrs => 'heroicon-o-arrows-right-left',
            self::Hexagonal => 'heroicon-o-cube-transparent',
            self::Solid => 'heroicon-o-rectangle-stack',
            self::DesignPatterns => 'heroicon-o-puzzle-piece',
            self::Databases => 'heroicon-o-circle-stack',
            self::Apis => 'heroicon-o-code-bracket',
            self::Testing => 'heroicon-o-beaker',
            self::Devops => 'heroicon-o-server-stack',
            self::Microservices => 'heroicon-o-share',
            self::Security => 'heroicon-o-shield-check',
            self::Performance => 'heroicon-o-bolt',
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
