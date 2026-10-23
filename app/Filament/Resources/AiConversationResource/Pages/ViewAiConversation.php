<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiConversationResource\Pages;

use App\Filament\Resources\AiConversationResource;
use App\Models\AiConversation;
use App\Models\AiUsage;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewAiConversation extends ViewRecord
{
    protected static string $resource = AiConversationResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Conversation')->schema([
                Infolists\Components\TextEntry::make('session_id')->copyable(),
                Infolists\Components\TextEntry::make('post.title')->label('Post')->placeholder('—'),
                Infolists\Components\TextEntry::make('ip_address')->label('IP')->copyable()->placeholder('—'),
                Infolists\Components\TextEntry::make('started_at')->dateTime('M j, Y H:i:s'),
                Infolists\Components\TextEntry::make('last_activity_at')->dateTime('M j, Y H:i:s'),
                Infolists\Components\TextEntry::make('turns_count')->label('Turns'),
                Infolists\Components\TextEntry::make('tokens_total')->label('Tokens'),
            ])->columns(4),

            Infolists\Components\Section::make('Turns')->schema([
                Infolists\Components\RepeatableEntry::make('turns')
                    ->hiddenLabel()
                    ->state(fn (AiConversation $record) => $record->turns()->orderBy('created_at')->get())
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')->dateTime('M j, Y H:i:s'),
                        Infolists\Components\TextEntry::make('refused')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'refused' : 'answered')
                            ->color(fn (bool $state): string => $state ? 'warning' : 'success'),
                        Infolists\Components\TextEntry::make('refused_stage')->label('Stage')->placeholder('—'),
                        Infolists\Components\TextEntry::make('model')->label('Model'),
                        Infolists\Components\TextEntry::make('provider')->placeholder('—'),
                        Infolists\Components\TextEntry::make('embedding_model')->label('Embedding')->placeholder('—'),
                        Infolists\Components\TextEntry::make('top_k')->label('top-k')->placeholder('—'),
                        Infolists\Components\TextEntry::make('tokens_in')->label('Tokens in'),
                        Infolists\Components\TextEntry::make('tokens_out')->label('Tokens out'),
                        Infolists\Components\TextEntry::make('latency_ms')->label('Latency ms'),
                        Infolists\Components\TextEntry::make('question')->prose()->columnSpanFull(),
                        Infolists\Components\TextEntry::make('prompt')
                            ->label('Prompt sent to the LLM')
                            ->placeholder('—')
                            ->copyable()
                            ->columnSpanFull()
                            ->state(function (AiUsage $record): ?string {
                                if (blank($record->prompt)) {
                                    return null;
                                }

                                $decoded = json_decode((string) $record->prompt, true);

                                return is_array($decoded)
                                    ? (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                                    : (string) $record->prompt;
                            }),
                        Infolists\Components\TextEntry::make('answer')->prose()->placeholder('—')->columnSpanFull(),
                        Infolists\Components\TextEntry::make('sources')
                            ->label('Retrieval')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->state(fn (AiUsage $record): ?string => $record->retrievedSources() === []
                                ? null
                                : json_encode($record->retrievedSources(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                    ])->columns(3),
            ]),
        ]);
    }
}
