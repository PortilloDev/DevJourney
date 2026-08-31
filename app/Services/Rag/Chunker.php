<?php

declare(strict_types=1);

namespace App\Services\Rag;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Str;

/**
 * Splits a rendered HTML body into semantic chunks, one per heading level, so
 * each chunk stays self-contained and easy to cite.
 *
 * @return list<array{text: string, heading: string|null}>
 */
class Chunker
{
    private int $chunkSize;

    private int $overlap;

    public function __construct()
    {
        $this->chunkSize = (int) config('rag.indexing.chunk_size', 900);
        $this->overlap = (int) config('rag.indexing.chunk_overlap', 120);
    }

    /**
     * @return list<array{text: string, heading: string|null}>
     */
    public function chunk(string $html): array
    {
        $chunks = [];

        foreach ($this->sections($html) as $section) {
            foreach ($this->splitText($section['text']) as $text) {
                if (trim($text) === '') {
                    continue;
                }

                $chunks[] = [
                    'text' => trim($text),
                    'heading' => $section['heading'],
                ];
            }
        }

        return $chunks;
    }

    /**
     * @return list<array{heading: string|null, text: string}>
     */
    protected function sections(string $html): array
    {
        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="ctx">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $container = $doc->getElementById('ctx');
        if (! $container instanceof DOMElement) {
            return [['heading' => null, 'text' => $this->clean($html)]];
        }

        $sections = [];
        $currentHeading = null;
        $buffer = [];

        foreach ($container->childNodes as $node) {
            if ($node instanceof DOMElement && in_array($node->tagName, ['h1', 'h2', 'h3', 'h4'], true)) {
                if ($this->flush($currentHeading, $buffer, $sections)) {
                    $buffer = [];
                }
                $currentHeading = $node->textContent;

                continue;
            }

            $buffer[] = $this->html($node);
        }

        $this->flush($currentHeading, $buffer, $sections);

        return $sections;
    }

    /**
     * @param  list<string>  $buffer
     * @param  list<array{heading: string|null, text: string}>  $sections
     */
    protected function flush(?string $heading, array $buffer, array &$sections): bool
    {
        $text = $this->clean(implode("\n", $buffer));

        if ($text !== '') {
            $sections[] = ['heading' => $heading, 'text' => $text];

            return true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function splitText(string $text): array
    {
        if (mb_strlen($text) <= $this->chunkSize) {
            return [$text];
        }

        $chunks = [];
        $start = 0;
        $length = mb_strlen($text);

        while ($start < $length) {
            $chunk = mb_substr($text, $start, $this->chunkSize);
            $chunks[] = $chunk;
            $start += $this->chunkSize - $this->overlap;
        }

        return $chunks;
    }

    protected function html(\DOMNode $node): string
    {
        $doc = new DOMDocument;
        $doc->appendChild($doc->importNode($node, true));

        return (string) $doc->saveHTML();
    }

    protected function clean(string $html): string
    {
        $text = strip_tags($html);

        return (string) Str::of($text)
            ->replaceMatches('/\s+/u', ' ')
            ->trim();
    }
}
