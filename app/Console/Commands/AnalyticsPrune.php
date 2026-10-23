<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ActivityEventType;
use App\Models\Visit;
use App\Models\VisitEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rolls up raw browsing sessions into the aggregated `daily_traffic` table and
 * deletes the source rows once they leave the retention window.
 *
 * Aggregation runs before deletion and is idempotent: a day that is already
 * present in `daily_traffic` is never overwritten, so a partially completed run
 * can be repeated safely. Only counts are kept — no personal data.
 */
class AnalyticsPrune extends Command
{
    protected $signature = 'analytics:prune
        {--days= : Retention window in days (defaults to analytics.retention_days)}';

    protected $description = 'Aggregate and delete browsing sessions older than the retention window.';

    public function handle(): int
    {
        $days = $this->option('days') !== null && $this->option('days') !== ''
            ? (int) $this->option('days')
            : (int) config('analytics.retention_days', 90);

        $cutoff = Carbon::now()->subDays($days)->startOfDay();

        $aggregated = $this->aggregate($cutoff);
        $deleted = $this->deleteRaw($cutoff);

        $this->info("Aggregated {$aggregated} day(s) into daily_traffic and deleted {$deleted} session(s) older than {$days} days.");

        return self::SUCCESS;
    }

    private function aggregate(Carbon $cutoff): int
    {
        $sessions = Visit::query()
            ->where('started_at', '<', $cutoff)
            ->selectRaw('DATE(started_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $views = VisitEvent::query()
            ->where('created_at', '<', $cutoff)
            ->where('type', ActivityEventType::PageView->value)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $uniques = Visit::query()
            ->where('started_at', '<', $cutoff)
            ->selectRaw('DATE(started_at) as d, COUNT(DISTINCT visitor_token) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $days = array_unique(array_merge(
            $sessions->keys()->all(),
            $views->keys()->all(),
            $uniques->keys()->all(),
        ));

        if ($days === []) {
            return 0;
        }

        $existing = DB::table('daily_traffic')
            ->whereIn('date', $days)
            ->pluck('date')
            ->all();

        $inserted = 0;

        foreach ($days as $day) {
            if (in_array($day, $existing, true)) {
                continue;
            }

            DB::table('daily_traffic')->insert([
                'date' => $day,
                'sessions' => (int) ($sessions[$day] ?? 0),
                'views' => (int) ($views[$day] ?? 0),
                'unique_visitors' => (int) ($uniques[$day] ?? 0),
            ]);

            $inserted++;
        }

        return $inserted;
    }

    private function deleteRaw(Carbon $cutoff): int
    {
        $deleted = 0;

        do {
            $count = Visit::query()
                ->where(function ($query) use ($cutoff): void {
                    $query->where('last_activity_at', '<', $cutoff)
                        ->orWhere(function ($query) use ($cutoff): void {
                            $query->whereNull('last_activity_at')
                                ->where('started_at', '<', $cutoff);
                        });
                })
                ->limit(1000)
                ->delete();
            $deleted += $count;
        } while ($count > 0);

        return $deleted;
    }
}
