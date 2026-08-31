<?php

declare(strict_types=1);

namespace App\Services\Rag;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Extracts plain text from a PDF via the `pdftotext` CLI (poppler-utils). If the
 * binary is unavailable the extractor degrades gracefully and returns metadata
 * only, so the document can still be stored.
 */
class PdfTextExtractor
{
    public function extract(string $path): string
    {
        $binary = $this->binary();

        if ($binary === null) {
            throw new RuntimeException('pdftotext is not available to extract the document text.');
        }

        $process = new Process([$binary, '-layout', $path, '-']);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Failed to extract PDF text: '.$process->getErrorOutput());
        }

        return trim($process->getOutput());
    }

    public function available(): bool
    {
        return $this->binary() !== null;
    }

    protected function binary(): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return null;
        }

        foreach (['/usr/bin/pdftotext', '/usr/local/bin/pdftotext', '/opt/homebrew/bin/pdftotext'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
