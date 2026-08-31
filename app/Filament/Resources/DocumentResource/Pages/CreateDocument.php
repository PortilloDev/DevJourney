<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use App\Jobs\IndexDocumentJob;
use Filament\Resources\Pages\CreateRecord;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected function afterCreate(): void
    {
        if ($this->record->path) {
            dispatch(new IndexDocumentJob($this->record->id));
        }
    }
}
