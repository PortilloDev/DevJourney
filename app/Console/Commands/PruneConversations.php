<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AiConversation;
use Illuminate\Console\Command;

class PruneConversations extends Command
{
    protected $signature = 'ai:prune-conversations';

    protected $description = 'Delete chat conversations older than the configured retention period.';

    public function handle(): int
    {
        $days = (int) config('rag.retention_days', 30);

        $deleted = AiConversation::pruneOlderThanDays($days);

        $this->info("Deleted {$deleted} conversation(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
