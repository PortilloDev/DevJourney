<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Document;
use App\Services\Rag\PdfTextExtractor;
use App\Services\Rag\RagService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class IndexDocumentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $documentId,
    ) {}

    public function handle(RagService $rag, PdfTextExtractor $extractor): void
    {
        $document = Document::find($this->documentId);

        if ($document === null) {
            return;
        }

        // A type disabled in rag.indexing.source_types must not spend embeddings.
        // Remove any points from a previous run so it stops being retrievable.
        if (! $this->typeIsIndexable()) {
            $rag->deleteDocument($document);
            $document->update(['status' => 'pending', 'indexed_at' => null]);

            return;
        }

        // A document without a post is unreachable: the chat filters by post, so
        // indexing it would only spend embeddings (R6).
        if ($document->post_id === null) {
            $rag->deleteDocument($document);
            $document->update(['status' => 'pending', 'indexed_at' => null]);

            return;
        }

        // A non-public document is never indexed; if it was public before, its
        // points are removed so it stops being retrievable from the chat.
        if (! $document->is_public) {
            $rag->deleteDocument($document);
            $document->update(['status' => 'indexed', 'indexed_at' => null]);

            return;
        }

        $document->update(['status' => 'pending', 'indexed_at' => null]);

        if (! $extractor->available()) {
            $document->update(['status' => 'failed']);

            Log::warning('Cannot index document: pdftotext is not available.', ['document' => $document->id]);

            return;
        }

        try {
            $absolutePath = Storage::disk('local')->path($document->path);
            $text = $extractor->extract($absolutePath);

            if (trim($text) === '') {
                $document->update(['status' => 'failed']);

                return;
            }

            $rag->indexDocument($document, $text);

            $document->update(['status' => 'indexed', 'indexed_at' => now()]);
        } catch (\Throwable $e) {
            $document->update(['status' => 'failed']);

            Log::error('Failed to index document', ['document' => $document->id, 'exception' => $e]);
        }
    }

    private function typeIsIndexable(): bool
    {
        $types = config('rag.indexing.source_types', ['post', 'document']);

        return is_array($types) && in_array('document', $types, true);
    }
}
