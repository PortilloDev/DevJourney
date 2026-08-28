<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppLogResource\Pages;

use App\Filament\Resources\AppLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAppLogs extends ListRecords
{
    protected static string $resource = AppLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
