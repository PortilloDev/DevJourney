<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Challenge;
use App\Models\Post;
use App\Models\Project;
use App\Services\Rag\RagService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class IndexContentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $modelClass,
        public int $modelId,
    ) {}

    public function handle(RagService $rag): void
    {
        $model = ($this->modelClass)::find($this->modelId);

        if ($model === null || ! $this->isIndexable($model)) {
            return;
        }

        try {
            $rag->indexContent($model);
        } catch (\Throwable $e) {
            Log::error('Failed to index content for RAG', [
                'model' => $this->modelClass,
                'id' => $this->modelId,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    private function isIndexable(object $model): bool
    {
        if (! in_array($model::class, [Post::class, Challenge::class, Project::class], true)) {
            return false;
        }

        $status = $model->status ?? null;
        $value = $status instanceof \BackedEnum ? $status->value : $status;

        return $value === 'published';
    }
}
