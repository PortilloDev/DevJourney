<?php

declare(strict_types=1);

namespace App\Filament\Resources\VisitResource\Pages;

use App\Filament\Resources\VisitResource;
use App\Models\Visit;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewVisit extends ViewRecord
{
    protected static string $resource = VisitResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Grid::make(3)->schema([
                Infolists\Components\Group::make()->schema([
                    Infolists\Components\TextEntry::make('started_at')
                        ->dateTime('M j, Y H:i:s')
                        ->label('Started')
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('last_activity_at')
                        ->dateTime('M j, Y H:i:s')
                        ->label('Last activity')
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('durationSeconds')
                        ->state(fn (Visit $record) => VisitResource::formatDuration($record->durationSeconds()))
                        ->label('Duration'),
                ]),
                Infolists\Components\Group::make()->schema([
                    Infolists\Components\TextEntry::make('entry_page_type')
                        ->label('Entry page type')
                        ->badge()
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('entry_path')
                        ->label('Entry path')
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('page_views')
                        ->label('Pages viewed')
                        ->suffix(' page(s)'),
                ]),
                Infolists\Components\Group::make()->schema([
                    Infolists\Components\TextEntry::make('device')
                        ->badge()
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('country')
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('ip_address')
                        ->label('IP address')
                        ->copyable()
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('referer')
                        ->placeholder('—'),
                ]),
            ]),
            Infolists\Components\Section::make('Event trail')->schema([
                Infolists\Components\RepeatableEntry::make('events')
                    ->hiddenLabel()
                    ->state(fn (Visit $record) => $record->events()->orderByDesc('created_at')->get())
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime('M j, Y H:i:s')
                            ->label('Time'),
                        Infolists\Components\TextEntry::make('type')
                            ->badge()
                            ->label('Type'),
                        Infolists\Components\TextEntry::make('page_type')
                            ->label('Section'),
                        Infolists\Components\TextEntry::make('title')
                            ->placeholder('—')
                            ->label('Title'),
                        Infolists\Components\TextEntry::make('url')
                            ->copyable()
                            ->placeholder('—')
                            ->label('URL'),
                    ])
                    ->columns(5),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
