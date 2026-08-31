<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiUsageResource\Pages;

use App\Filament\Resources\AiUsageResource;
use Filament\Resources\Pages\ListRecords;

class ListAiUsage extends ListRecords
{
    protected static string $resource = AiUsageResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
