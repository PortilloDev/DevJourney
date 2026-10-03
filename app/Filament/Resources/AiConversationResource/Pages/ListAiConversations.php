<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiConversationResource\Pages;

use App\Filament\Resources\AiConversationResource;
use App\Models\AiConversation;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAiConversations extends ListRecords
{
    protected static string $resource = AiConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('deleteRange')
                ->label('Delete by date range')
                ->color('danger')
                ->icon('heroicon-o-calendar-days')
                ->form([
                    Forms\Components\DatePicker::make('from')->label('From'),
                    Forms\Components\DatePicker::make('until')->label('Until'),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    $count = AiConversation::deleteBetween($data['from'] ?? null, $data['until'] ?? null);

                    Notification::make()
                        ->title("Deleted {$count} conversation(s)")
                        ->success()
                        ->send();
                }),
            Actions\Action::make('deleteAll')
                ->label('Delete all')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->action(function (): void {
                    $count = AiConversation::deleteAllLogs();

                    Notification::make()
                        ->title("Deleted {$count} conversation(s)")
                        ->success()
                        ->send();
                }),
        ];
    }
}
