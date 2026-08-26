<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\VisitResource\Pages;
use App\Filament\Resources\VisitResource\RelationManagers\VisitEventsRelationManager;
use App\Models\Visit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VisitResource extends Resource
{
    protected static ?string $model = Visit::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $modelLabel = 'session';

    protected static ?string $pluralModelLabel = 'sessions';

    protected static ?string $recordTitleAttribute = 'entry_path';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('entry_path')->label('Entry page'),
            Forms\Components\TextInput::make('ip_address'),
            Forms\Components\TextInput::make('country'),
            Forms\Components\TextInput::make('device'),
            Forms\Components\TextInput::make('referer'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('durationSeconds')
                    ->label('Duration')
                    ->state(fn (Visit $record) => self::formatDuration($record->durationSeconds()))
                    ->description(fn (Visit $record) => $record->last_activity_at?->isAfter(now()->subMinutes(30)) ? 'active' : null)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('page_views')
                    ->label('Pages')
                    ->alignCenter()
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('entry_page_type')
                    ->label('Entry')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('device')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'mobile' => 'info',
                        'tablet' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('referer')
                    ->limit(40)->icon(fn ($state) => $state ? 'heroicon-o-arrow-top-right-on-square' : null),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('entry_page_type')
                    ->options(array_combine(self::pageTypes(), self::pageTypes())),
                Tables\Filters\SelectFilter::make('device')
                    ->options([
                        'desktop' => 'Desktop',
                        'mobile' => 'Mobile',
                        'tablet' => 'Tablet',
                    ]),
                Tables\Filters\Filter::make('started_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function ($query, array $data): void {
                        $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('started_at', '>=', $date))
                            ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('started_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            VisitEventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVisits::route('/'),
            'view' => Pages\ViewVisit::route('/{record}'),
        ];
    }

    public static function formatDuration(int $seconds): string
    {
        if ($seconds < 1) {
            return '<1s';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %02dm', $hours, $minutes);
        }

        if ($minutes > 0) {
            return sprintf('%dm %02ds', $minutes, $secs);
        }

        return sprintf('%ds', $secs);
    }

    /**
     * Known public page types, used for filter options.
     *
     * @return list<string>
     */
    public static function pageTypes(): array
    {
        return [
            'home',
            'journal',
            'post',
            'category',
            'tag',
            'challenges',
            'challenge',
            'projects',
            'project',
            'about',
            'now',
            'progress',
        ];
    }
}
