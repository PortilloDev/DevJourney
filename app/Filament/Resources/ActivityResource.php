<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\ActivityEventType;
use App\Filament\Resources\ActivityResource\Pages;
use App\Models\VisitEvent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ActivityResource extends Resource
{
    protected static ?string $model = VisitEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationGroup = 'Analytics';

    protected static ?string $modelLabel = 'activity logged';

    protected static ?string $pluralModelLabel = 'activity log';

    protected static ?string $recordTitleAttribute = 'path';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('type')->disabled(),
            Forms\Components\TextInput::make('page_type')->disabled(),
            Forms\Components\TextInput::make('path')->disabled(),
            Forms\Components\TextInput::make('url')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->limit(45)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('path')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('visit.ip_address')
                    ->label('IP')
                    ->toggleable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('visit.device')
                    ->label('Device')
                    ->badge()
                    ->toggleable()
                    ->color(fn (string $state) => match ($state) {
                        'mobile' => 'info',
                        'tablet' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(fn () => collect(ActivityEventType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all()),
                Tables\Filters\SelectFilter::make('page_type')
                    ->options(array_combine(VisitResource::pageTypes(), VisitResource::pageTypes())),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function ($query, array $data): void {
                        $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('viewSession')
                    ->label('Session')
                    ->icon('heroicon-o-users')
                    ->url(fn (VisitEvent $record) => VisitResource::getUrl('view', ['record' => $record->visit_id])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivity::route('/'),
        ];
    }
}
