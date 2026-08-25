<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\VisitResource;
use App\Models\Visit;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TrafficStatsOverview extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $today = now()->startOfDay();
        $weekAgo = now()->subDays(7)->startOfDay();
        $thirtyDaysAgo = now()->subDays(30)->startOfDay();

        $visitsToday = Visit::query()->where('started_at', '>=', $today)->count();
        $visitsWeek = Visit::query()->where('started_at', '>=', $weekAgo)->count();
        $uniqueVisitors = Visit::query()
            ->where('started_at', '>=', $thirtyDaysAgo)
            ->distinct()
            ->count('visitor_token');
        $avgDuration = Visit::query()
            ->where('started_at', '>=', $weekAgo)
            ->get()
            ->average(fn (Visit $visit) => $visit->durationSeconds());

        return [
            Stat::make('Visits today', (string) $visitsToday)
                ->description('New sessions started')
                ->descriptionIcon('heroicon-m-eye')
                ->color('primary'),
            Stat::make('Visits (7d)', (string) $visitsWeek)
                ->description('Rolling week')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
            Stat::make('Unique visitors (30d)', (string) $uniqueVisitors)
                ->description('By anonymous token')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),
            Stat::make('Avg session (7d)', $avgDuration ? VisitResource::formatDuration((int) round($avgDuration)) : '—')
                ->description('Time between first & last activity')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('warning'),
        ];
    }
}
