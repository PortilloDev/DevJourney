<?php

declare(strict_types=1);

namespace App\Filament\Resources\AppLogResource\Pages;

use App\Filament\Resources\AppLogResource;
use App\Models\AppLog;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewAppLog extends ViewRecord
{
    protected static string $resource = AppLogResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Grid::make(3)->schema([
                Infolists\Components\TextEntry::make('level')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => strtoupper($state))
                    ->color(fn (string $state) => match ($state) {
                        'emergency', 'alert', 'critical', 'error' => 'danger',
                        'warning' => 'warning',
                        'notice', 'info' => 'info',
                        default => 'gray',
                    }),
                Infolists\Components\TextEntry::make('created_at')
                    ->dateTime('M j, Y H:i:s'),
                Infolists\Components\TextEntry::make('user.email')
                    ->label('User')
                    ->placeholder('—'),
                Infolists\Components\TextEntry::make('method'),
                Infolists\Components\TextEntry::make('ip_address')
                    ->label('IP')
                    ->copyable()
                    ->placeholder('—'),
                Infolists\Components\TextEntry::make('user_agent')
                    ->label('User agent')
                    ->placeholder('—')
                    ->limit(120),
            ]),
            Infolists\Components\Section::make('Message')->schema([
                Infolists\Components\TextEntry::make('message')->prose(),
                Infolists\Components\TextEntry::make('url')
                    ->copyable()
                    ->label('URL'),
            ]),
            Infolists\Components\Section::make('Context')->schema([
                Infolists\Components\TextEntry::make('context')
                    ->state(fn (AppLog $record) => $record->context ? json_encode($record->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null)
                    ->copyable()
                    ->placeholder('No context'),
                Infolists\Components\TextEntry::make('exception')
                    ->placeholder('—')
                    ->label('Exception'),
            ]),
            Infolists\Components\Section::make('Stack trace')->schema([
                Infolists\Components\TextEntry::make('trace')
                    ->copyable()
                    ->placeholder('No trace'),
            ])->collapsible(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
