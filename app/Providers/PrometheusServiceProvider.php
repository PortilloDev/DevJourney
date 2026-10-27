<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Prometheus\Collectors\Horizon\CurrentMasterSupervisorCollector;
use Spatie\Prometheus\Collectors\Horizon\CurrentProcessesPerQueueCollector;
use Spatie\Prometheus\Collectors\Horizon\CurrentWorkloadCollector;
use Spatie\Prometheus\Collectors\Horizon\FailedJobsPerHourCollector;
use Spatie\Prometheus\Collectors\Horizon\HorizonStatusCollector;
use Spatie\Prometheus\Collectors\Horizon\JobsPerMinuteCollector;
use Spatie\Prometheus\Collectors\Horizon\RecentJobsCollector;
use Spatie\Prometheus\Collectors\Queue\QueueDelayedJobsCollector;
use Spatie\Prometheus\Collectors\Queue\QueueOldestPendingJobCollector;
use Spatie\Prometheus\Collectors\Queue\QueuePendingJobsCollector;
use Spatie\Prometheus\Collectors\Queue\QueueReservedJobsCollector;
use Spatie\Prometheus\Collectors\Queue\QueueSizeCollector;
use Spatie\Prometheus\Prometheus;

class PrometheusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $prometheus = $this->app->make(Prometheus::class);

        $this->registerQueueCollectors($prometheus, ['default']);
        $this->registerHorizonCollectors($prometheus);
    }

    public function registerHorizonCollectors(Prometheus $prometheus): void
    {
        $prometheus->registerCollectorClasses([
            CurrentMasterSupervisorCollector::class,
            CurrentProcessesPerQueueCollector::class,
            CurrentWorkloadCollector::class,
            FailedJobsPerHourCollector::class,
            HorizonStatusCollector::class,
            JobsPerMinuteCollector::class,
            RecentJobsCollector::class,
        ]);
    }

    /**
     * @param  list<string>  $queues
     */
    public function registerQueueCollectors(Prometheus $prometheus, array $queues = [], ?string $connection = null): void
    {
        $prometheus->registerCollectorClasses([
            QueueSizeCollector::class,
            QueuePendingJobsCollector::class,
            QueueDelayedJobsCollector::class,
            QueueReservedJobsCollector::class,
            QueueOldestPendingJobCollector::class,
        ], compact('connection', 'queues'));
    }
}
