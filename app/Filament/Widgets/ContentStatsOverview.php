<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PostStatus;
use App\Models\Challenge;
use App\Models\Post;
use App\Models\Project;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContentStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $publishedPosts = Post::query()->where('status', PostStatus::Published->value)->count();
        $draftPosts = Post::query()->where('status', PostStatus::Draft->value)->count();
        $challenges = Challenge::query()->where('status', PostStatus::Published->value)->count();
        $projects = Project::query()->where('status', PostStatus::Published->value)->count();

        return [
            Stat::make('Published posts', (string) $publishedPosts)
                ->description($draftPosts.' draft(s)')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color('success'),
            Stat::make('Challenges solved', (string) $challenges)
                ->description('Across all topics')
                ->descriptionIcon('heroicon-m-puzzle-piece')
                ->color('info'),
            Stat::make('Projects shipped', (string) $projects)
                ->description('In the portfolio')
                ->descriptionIcon('heroicon-m-rocket-launch')
                ->color('warning'),
        ];
    }
}
