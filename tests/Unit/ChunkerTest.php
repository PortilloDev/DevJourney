<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Rag\Chunker;
use Tests\TestCase;

class ChunkerTest extends TestCase
{
    private Chunker $chunker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chunker = new Chunker;
    }

    public function test_it_splits_html_by_headings(): void
    {
        $html = '<h2>Introduction</h2><p>First paragraph about architecture.</p>'
            .'<h2>Details</h2><p>A second paragraph.</p>';

        $chunks = $this->chunker->chunk($html);

        $this->assertCount(2, $chunks);
        $this->assertSame('Introduction', $chunks[0]['heading']);
        $this->assertStringContainsString('architecture', $chunks[0]['text']);
        $this->assertSame('Details', $chunks[1]['heading']);
    }

    public function test_it_collapses_whitespace_and_ignores_empty(): void
    {
        $html = '<h2>Intro</h2><p>Hello    world!</p>';

        $chunks = $this->chunker->chunk($html);

        $this->assertCount(1, $chunks);
        $this->assertSame('Hello world!', $chunks[0]['text']);
        $this->assertSame('Intro', $chunks[0]['heading']);
    }

    public function test_it_returns_unheaded_chunk_when_no_headings(): void
    {
        $chunks = $this->chunker->chunk('<p>Plain content without headings.</p>');

        $this->assertCount(1, $chunks);
        $this->assertNull($chunks[0]['heading']);
    }

    public function test_it_splits_long_text_into_multiple_chunks(): void
    {
        $text = str_repeat('word ', 300);
        $chunks = $this->chunker->chunk('<p>'.$text.'</p>');

        $this->assertGreaterThan(1, count($chunks));
    }
}
