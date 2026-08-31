<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiUsageResource\Pages;

use App\Filament\Resources\AiUsageResource;
use App\Models\AiUsage;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewAiUsage extends ViewRecord
{
    protected static string $resource = AiUsageResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Grid::make(3)->schema([
                Infolists\Components\TextEntry::make('created_at')->dateTime('M j, Y H:i:s'),
                Infolists\Components\TextEntry::make('ip_address')->label('IP')->copyable(),
                Infolists\Components\TextEntry::make('model')->badge(),
                Infolists\Components\TextEntry::make('tokens_in')->label('Tokens in'),
                Infolists\Components\TextEntry::make('tokens_out')->label('Tokens out'),
                Infolists\Components\TextEntry::make('context_chunks')->label('Context chunks'),
                Infolists\Components\TextEntry::make('latency_ms')->label('Latency')->suffix(' ms'),
            ]),
            Infolists\Components\Section::make('Question')->schema([
                Infolists\Components\TextEntry::make('question')->prose(),
            ]),
            Infolists\Components\Section::make('Sources')->schema([
                Infolists\Components\TextEntry::make('sources')
                    ->state(fn (AiUsage $record) => $record->sources ? json_encode(json_decode($record->sources, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null)
                    ->copyable()
                    ->placeholder('No sources'),
            ]),
        ]);
    }
}
