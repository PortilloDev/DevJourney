<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\ActivityEventType;
use App\Models\Visit;
use App\Models\VisitEvent;
use Filament\Widgets\ChartWidget;

/**
 * Daily sessions and page views over the trailing 30 days.
 */
class TrafficChart extends ChartWidget
{
    protected static ?string $heading = 'Traffic — last 30 days';

    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $start = now()->subDays(30)->startOfDay();

        $visitsPerDay = Visit::query()
            ->where('started_at', '>=', $start)
            ->selectRaw('DATE(started_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $viewsPerDay = VisitEvent::query()
            ->where('type', ActivityEventType::PageView->value)
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $labels = [];
        $sessions = [];
        $views = [];

        foreach (range(29, 0) as $daysAgo) {
            $day = now()->subDays($daysAgo);
            $key = $day->toDateString();

            $labels[] = $day->format('M j');
            $sessions[] = (int) ($visitsPerDay[$key] ?? 0);
            $views[] = (int) ($viewsPerDay[$key] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Sessions',
                    'data' => $sessions,
                    'borderColor' => '#06b6d4',
                    'backgroundColor' => 'rgba(6, 182, 212, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Page views',
                    'data' => $views,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
