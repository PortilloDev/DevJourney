<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AiConversationResource\Pages;
use App\Models\AiConversation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AiConversationResource extends Resource
{
    protected static ?string $model = AiConversation::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static ?string $navigationGroup = 'AI';

    protected static ?string $modelLabel = 'conversation';

    protected static ?string $pluralModelLabel = 'conversations';

    protected static ?string $recordTitleAttribute = 'session_id';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('session_id')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_activity_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('session_id')
                    ->label('Session')
                    ->limit(18)
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('post.title')->label('Post')->limit(30)->placeholder('—'),
                Tables\Columns\TextColumn::make('ip_address')->label('IP')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('turns_count')->label('Turns')->alignCenter()->badge(),
                Tables\Columns\TextColumn::make('tokens_total')->label('Tokens')->alignEnd(),
                Tables\Columns\TextColumn::make('started_at')->dateTime('M j, Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('last_activity_at')->dateTime('M j, Y H:i')->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('post')->relationship('post', 'title')->searchable(),
                Tables\Filters\Filter::make('last_activity_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function ($query, array $data): void {
                        $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('last_activity_at', '>=', $date))
                            ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('last_activity_at', '<=', $date));
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
            'index' => Pages\ListAiConversations::route('/'),
            'view' => Pages\ViewAiConversation::route('/{record}'),
        ];
    }
}
