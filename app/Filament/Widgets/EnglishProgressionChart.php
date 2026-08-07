<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\MilestoneType;
use App\Models\Milestone;
use Filament\Widgets\ChartWidget;

/**
 * Plots the author's CEFR English level over time from `english`-type milestones
 * whose title contains a level code (A1–C2).
 */
class EnglishProgressionChart extends ChartWidget
{
    protected static ?string $heading = 'English level progression';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $rankByLevel = ['A1' => 1, 'A2' => 2, 'B1' => 3, 'B2' => 4, 'C1' => 5, 'C2' => 6];

        $milestones = Milestone::query()
            ->where('type', MilestoneType::English->value)
            ->orderBy('achieved_at')
            ->get();

        $labels = [];
        $points = [];

        foreach ($milestones as $milestone) {
            if (preg_match('/\b([ABC][12])\b/', $milestone->title, $m)) {
                $labels[] = $milestone->achieved_at->format('M Y');
                $points[] = $rankByLevel[$m[1]];
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'CEFR level',
                    'data' => $points,
                    'borderColor' => '#14b8a6',
                    'backgroundColor' => 'rgba(20, 184, 166, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'min' => 0,
                    'max' => 6,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
