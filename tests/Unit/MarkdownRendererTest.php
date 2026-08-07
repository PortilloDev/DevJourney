<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MarkdownRenderer;
use PHPUnit\Framework\TestCase;

class MarkdownRendererTest extends TestCase
{
    private MarkdownRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new MarkdownRenderer;
    }

    public function test_it_renders_markdown_to_html(): void
    {
        $html = $this->renderer->toHtml("# Hello\n\nThis is **bold**.");

        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
    }

    public function test_it_returns_empty_string_for_blank_input(): void
    {
        $this->assertSame('', $this->renderer->toHtml(null));
        $this->assertSame('', $this->renderer->toHtml(''));
    }

    public function test_it_estimates_reading_time(): void
    {
        $words = str_repeat('word ', 400);

        $this->assertSame(2, $this->renderer->readingMinutes($words));
        $this->assertSame(1, $this->renderer->readingMinutes('just a few words'));
    }

    public function test_it_extracts_a_table_of_contents_from_headings(): void
    {
        $html = $this->renderer->toHtml("## Section A\n\ntext\n\n### Sub B\n\nmore");
        $toc = $this->renderer->extractToc($html);

        $this->assertCount(2, $toc);
        $this->assertSame('Section A', $toc[0]['text']);
        $this->assertSame('section-a', $toc[0]['id']);
        $this->assertSame(2, $toc[0]['level']);
        $this->assertSame(3, $toc[1]['level']);
    }

    public function test_it_highlights_fenced_code_blocks(): void
    {
        $html = $this->renderer->toHtml("```php\necho 1;\n```");

        $this->assertStringContainsString('<pre>', $html);
        $this->assertStringContainsString('language-php', $html);
    }
}
