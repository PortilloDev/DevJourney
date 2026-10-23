<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Challenge;
use App\Services\Rag\RagService;
use Illuminate\Console\Command;

class PruneChallengeVectors extends Command
{
    protected $signature = 'rag:prune-challenge-vectors';

    protected $description = 'Remove vector points for every challenge, which no retrieval path can reach.';

    public function handle(RagService $rag): int
    {
        $count = 0;

        Challenge::query()->each(function (Challenge $challenge) use ($rag, &$count): void {
            $rag->deleteContent($challenge);
            $count++;
        });

        $this->info("Removed vector points for {$count} challenge(s).");

        return self::SUCCESS;
    }
}
