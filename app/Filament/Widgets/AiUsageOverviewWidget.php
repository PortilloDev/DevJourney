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

    /**
     * Quality and cost signals for the current day, computed from the logged
     * turns. Turns attended with zero context chunks exclude cache hits, which
     * legitimately carry no retrieval.
     *
     * @return array{rejection_by_stage: array<string, int>, error_rate: float, latency_p95: int|null, cost_total: float, negative_rating_rate: float, zero_chunk_turns: int}
     */
    public static function todaysMetrics(): array
    {
        $all = AiUsage::query()
            ->where('created_at', '>=', now()->startOfDay())
            ->get();

        $total = $all->count();

        $rejectionByStage = $all
            ->where('refused', true)
            ->groupBy('refused_stage')
            ->map(fn ($group): int => $group->count())
            ->mapWithKeys(fn (int $count, string $stage): array => [$stage => $count])
            ->all();

        $errors = $all->where('refused_stage', 'error')->count();

        $latencies = $all
            ->pluck('latency_ms')
            ->filter(fn ($value): bool => (int) $value > 0)
            ->map(fn ($value): int => (int) $value)
            ->sort()
            ->values();

        $p95 = null;
        if ($latencies->isNotEmpty()) {
            $index = (int) ceil(0.95 * $latencies->count()) - 1;
            $p95 = $latencies[max(0, min($index, $latencies->count() - 1))];
        }

        $rated = $all->whereNotNull('rating');
        $negativeRatingRate = $rated->isEmpty()
            ? 0.0
            : $rated->filter(fn (AiUsage $u): bool => $u->rating === -1)->count() / $rated->count();

        $zeroChunkTurns = $all
            ->filter(fn (AiUsage $u): bool => ! $u->refused && ! $u->cache_hit && (int) $u->context_chunks === 0)
            ->count();

        return [
            'rejection_by_stage' => $rejectionByStage,
            'error_rate' => $total > 0 ? $errors / $total : 0.0,
            'latency_p95' => $p95,
            'cost_total' => (float) $all->sum('cost_eur'),
            'negative_rating_rate' => $negativeRatingRate,
            'zero_chunk_turns' => $zeroChunkTurns,
        ];
    }

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

        $metrics = self::todaysMetrics();
        $stages = $metrics['rejection_by_stage'];
        $rejectionDescription = $stages === []
            ? 'No refusals today'
            : collect($stages)->map(fn (int $count, string $stage): string => $stage.': '.$count)->implode(', ');

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
            Stat::make('Refusals by stage', (string) array_sum($stages))
                ->description($rejectionDescription)
                ->descriptionIcon('heroicon-m-hand-raised')
                ->color('warning'),
            Stat::make('Error rate', number_format($metrics['error_rate'] * 100, 1).'%')
                ->description('Provider failures today')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($metrics['error_rate'] > 0 ? 'danger' : 'success'),
            Stat::make('Latency p95', $metrics['latency_p95'] !== null ? $metrics['latency_p95'].' ms' : '—')
                ->description('Across today')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            Stat::make('Today cost', '€'.number_format($metrics['cost_total'], 4))
                ->description('Estimated')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Negative ratings', number_format($metrics['negative_rating_rate'] * 100, 1).'%')
                ->description('Of rated answers')
                ->descriptionIcon('heroicon-m-hand-thumb-down')
                ->color('danger'),
            Stat::make('Zero-context turns', (string) $metrics['zero_chunk_turns'])
                ->description('Answered with no chunks')
                ->descriptionIcon('heroicon-m-magnifying-glass-minus')
                ->color($metrics['zero_chunk_turns'] > 0 ? 'danger' : 'success'),
            Stat::make('Provider circuit', $circuitOpen ? 'Open' : 'Closed')
                ->description($circuitOpen && $openedAt !== null
                    ? 'Since '.$openedAt->toDateTimeString()
                    : 'Calls allowed')
                ->descriptionIcon($circuitOpen ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($circuitOpen ? 'danger' : 'success'),
        ];
    }
}
