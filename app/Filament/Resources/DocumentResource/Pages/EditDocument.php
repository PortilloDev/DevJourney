<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use App\Jobs\IndexDocumentJob;
use Filament\Resources\Pages\EditRecord;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function afterSave(): void
    {
        if ($this->record->path) {
            dispatch(new IndexDocumentJob($this->record->id));
        }
    }
}
