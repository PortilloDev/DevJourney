<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\AppLogResource;
use App\Models\AppLog;
use Filament\Widgets\Widget;

class RecentErrorsWidget extends Widget
{
    protected static string $view = 'filament.widgets.recent-errors-widget';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $logs = AppLog::query()
            ->whereIn('level', ['emergency', 'alert', 'critical', 'error'])
            ->latest('created_at')
            ->limit(5)
            ->get();

        return [
            'heading' => 'Recent errors',
            'logs' => $logs,
            'url' => AppLogResource::getUrl('index'),
        ];
    }
}
