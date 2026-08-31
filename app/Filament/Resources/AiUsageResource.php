<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AiUsageResource\Pages;
use App\Models\AiUsage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AiUsageResource extends Resource
{
    protected static ?string $model = AiUsage::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'AI';

    protected static ?string $modelLabel = 'chat usage';

    protected static ?string $pluralModelLabel = 'chat usage';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('model')->disabled(),
            Forms\Components\Textarea::make('question')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->dateTime('M j, Y H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('ip_address')->label('IP')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('question')->limit(60)->wrap(),
                Tables\Columns\TextColumn::make('model')->badge()->color('gray')->toggleable(),
                Tables\Columns\TextColumn::make('tokens_in')->label('In')->alignEnd()->toggleable(),
                Tables\Columns\TextColumn::make('tokens_out')->label('Out')->alignEnd()->toggleable(),
                Tables\Columns\TextColumn::make('totalTokens')
                    ->label('Total')
                    ->state(fn (AiUsage $record) => $record->totalTokens())
                    ->alignEnd()
                    ->sortable(),
                Tables\Columns\TextColumn::make('context_chunks')->label('Chunks')->alignCenter()->toggleable(),
            ])
            ->filters([
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
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAiUsage::route('/'),
            'view' => Pages\ViewAiUsage::route('/{record}'),
        ];
    }
}
