<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\AiUsage;
use App\Services\Llm\CircuitBreaker;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AiUsageOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 6;

    protected function getStats(): array
    {
        $today = now()->startOfDay();
        $usage = AiUsage::query()->where('created_at', '>=', $today);
        $total = (clone $usage)->get();

        $requests = (clone $usage)->count();
        $tokens = $total->sum(fn (AiUsage $u) => $u->totalTokens());
        $questions = (clone $usage)->where('sources', '!=', null)->count();
        $avgLatency = $total->avg('latency_ms');

        $circuit = app(CircuitBreaker::class);
        $circuitOpen = $circuit->isOpen();
        $openedAt = $circuit->openedAt();

        return [
            Stat::make('Today requests', (string) $requests)
                ->description('Chat calls')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('primary'),
            Stat::make('Today tokens', (string) $tokens)
                ->description('In + out')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),
            Stat::make('Answered', (string) $questions)
                ->description('With retrieved context')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('Avg latency', $avgLatency !== null ? round((float) $avgLatency).' ms' : '—')
                ->description('Across today')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            Stat::make('Provider circuit', $circuitOpen ? 'Open' : 'Closed')
                ->description($circuitOpen && $openedAt !== null
                    ? 'Since '.$openedAt->toDateTimeString()
                    : 'Calls allowed')
                ->descriptionIcon($circuitOpen ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($circuitOpen ? 'danger' : 'success'),
        ];
    }
}
