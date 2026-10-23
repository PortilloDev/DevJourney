<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Analytics retention
    |--------------------------------------------------------------------------
    |
    | Raw browsing sessions (`visits` and their `visit_events`) older than this
    | many days are aggregated into the `daily_traffic` table and deleted by the
    | scheduled `analytics:prune` command. The aggregated counts contain no
    | personal data and are kept indefinitely.
    |
    */
    'retention_days' => (int) env('ANALYTICS_RETENTION_DAYS', 90),

];
