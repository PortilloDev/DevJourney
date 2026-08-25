<?php

declare(strict_types=1);

namespace App\Filament\Resources\VisitResource\RelationManagers;

use App\Enums\ActivityEventType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class VisitEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $title = 'Activity trail';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('url')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (ActivityEventType $state) => match ($state) {
                        ActivityEventType::PageView => 'primary',
                        ActivityEventType::Heartbeat => 'info',
                        ActivityEventType::Leave => 'warning',
                        ActivityEventType::Click => 'gray',
                    }),
                Tables\Columns\TextColumn::make('page_type')
                    ->label('Section')
                    ->badge(),
                Tables\Columns\TextColumn::make('title')
                    ->limit(40)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('path')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('url')
                    ->limit(50)
                    ->copyable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(fn () => collect(ActivityEventType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all()),
            ]);
    }
}
