<?php

declare(strict_types=1);

namespace App\Filament\Resources\PostResource\RelationManagers;

use App\Enums\ChallengeDifficulty;
use App\Enums\PostStatus;
use App\Models\Post;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChallengesRelationManager extends RelationManager
{
    protected static string $relationship = 'challenges';

    protected static ?string $title = 'Related challenges';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(50)
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('difficulty')
                    ->badge()
                    ->formatStateUsing(fn (ChallengeDifficulty $state): string => $state->label()),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (PostStatus $state): string => $state->value),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['title'])
                    ->recordSelectOptionsQuery(function (Builder $query): Builder {
                        /** @var Post $post */
                        $post = $this->getOwnerRecord();

                        return $query->suggestedForPost($post);
                    }),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
