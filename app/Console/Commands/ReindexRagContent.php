<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\IndexContentJob;
use App\Jobs\IndexDocumentJob;
use App\Models\Challenge;
use App\Models\Document;
use App\Models\Post;
use App\Models\Project;
use App\Services\Rag\RagService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class ReindexRagContent extends Command
{
    protected $signature = 'rag:reindex
        {--type=* : Limit to post, project, challenge or document}';

    protected $description = 'Rebuild the vector index for published content and public documents.';

    /** @var list<string> */
    private const TYPES = ['post', 'project', 'challenge', 'document'];

    public function handle(RagService $rag): int
    {
        if (blank(config('rag.api_key')) || blank(config('rag.embeddings.api_key'))) {
            $this->error('RAG credentials are missing (LLM_API_KEY / EMBEDDING_API_KEY).');

            return self::FAILURE;
        }

        $configured = array_values(array_intersect(
            array_values(array_filter((array) config('rag.indexing.source_types', []))),
            self::TYPES,
        ));
        $requested = array_values(array_filter((array) $this->option('type')));

        foreach ($requested as $type) {
            if (! in_array($type, self::TYPES, true)) {
                $this->error("Unknown type: {$type}. Use post, project, challenge or document.");

                return self::FAILURE;
            }
        }

        $types = $requested !== [] ? $requested : $configured;

        foreach (array_diff($types, $configured) as $type) {
            $this->warn("Skipping {$type}: not listed in rag.indexing.source_types.");
        }

        $total = 0;

        foreach (array_intersect($types, $configured) as $type) {
            $count = match ($type) {
                'post' => $this->reindexContent(Post::class, $rag),
                'project' => $this->reindexContent(Project::class, $rag),
                'challenge' => $this->reindexContent(Challenge::class, $rag),
                'document' => $this->reindexDocuments($rag),
                default => 0,
            };

            $this->line("Reindexed {$count} {$type}(s).");
            $total += $count;
        }

        $this->info("Done. {$total} item(s) reindexed.");

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Post|Project|Challenge>  $modelClass
     */
    private function reindexContent(string $modelClass, RagService $rag): int
    {
        $count = 0;

        $modelClass::query()->published()->each(function (Model $model) use ($rag, &$count): void {
            // Delete first so chunks left over from a longer previous version
            // do not survive the rebuild.
            $rag->deleteContent($model);

            IndexContentJob::dispatchSync($model::class, (int) $model->getKey());

            $count++;
        });

        return $count;
    }

    private function reindexDocuments(RagService $rag): int
    {
        $count = 0;

        Document::query()->where('is_public', true)->each(function (Document $document) use ($rag, &$count): void {
            $rag->deleteDocument($document);

            IndexDocumentJob::dispatchSync($document->getKey());

            $count++;
        });

        return $count;
    }
}
