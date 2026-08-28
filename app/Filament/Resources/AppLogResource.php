<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AppLogResource\Pages;
use App\Models\AppLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppLogResource extends Resource
{
    protected static ?string $model = AppLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-bug-ant';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $modelLabel = 'log entry';

    protected static ?string $pluralModelLabel = 'logs';

    protected static ?string $recordTitleAttribute = 'message';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('level')->disabled(),
            Forms\Components\Textarea::make('message')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('level')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => strtoupper($state))
                    ->color(fn (string $state) => match ($state) {
                        'emergency', 'alert', 'critical', 'error' => 'danger',
                        'warning' => 'warning',
                        'notice', 'info' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('message')
                    ->searchable()
                    ->limit(70)
                    ->wrap(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('url')
                    ->limit(40)
                    ->toggleable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable()
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('level')
                    ->options([
                        'emergency' => 'Emergency',
                        'alert' => 'Alert',
                        'critical' => 'Critical',
                        'error' => 'Error',
                        'warning' => 'Warning',
                        'notice' => 'Notice',
                        'info' => 'Info',
                        'debug' => 'Debug',
                    ]),
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
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListAppLogs::route('/'),
            'view' => Pages\ViewAppLog::route('/{record}'),
        ];
    }
}
