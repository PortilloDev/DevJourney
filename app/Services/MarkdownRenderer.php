<?php

declare(strict_types=1);

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Renders raw Markdown to HTML once (on save) so public routes never pay the
 * rendering cost at request time — per the spec's "store raw, pre-render on save"
 * rule.
 */
class MarkdownRenderer
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'heading_permalink' => [
                'html_class' => 'heading-anchor',
                'id_prefix' => '',
                'fragment_prefix' => '',
                'insert' => 'after',
                'min_heading_level' => 2,
                'max_heading_level' => 4,
                'symbol' => '#',
                'aria_hidden' => true,
            ],
        ]);

        $environment
            ->addExtension(new CommonMarkCoreExtension)
            ->addExtension(new GithubFlavoredMarkdownExtension)
            ->addExtension(new AutolinkExtension)
            ->addExtension(new TableExtension)
            ->addExtension(new HeadingPermalinkExtension);

        $this->converter = new MarkdownConverter($environment);
    }

    public function toHtml(?string $markdown): string
    {
        if (blank($markdown)) {
            return '';
        }

        return $this->converter->convert($markdown)->getContent();
    }

    /**
     * Estimate reading time in minutes from raw Markdown (~200 wpm).
     */
    public function readingMinutes(?string $markdown): int
    {
        if (blank($markdown)) {
            return 0;
        }

        $words = str_word_count(strip_tags($markdown));

        return max(1, (int) ceil($words / 200));
    }

    /**
     * Extract a flat table of contents from rendered HTML headings (h2/h3).
     *
     * @return array<int, array{level: int, text: string, id: string}>
     */
    public function extractToc(?string $html): array
    {
        if (blank($html)) {
            return [];
        }

        // Heading ids live on the permalink anchor injected inside each heading:
        // <h2>Text<a id="text" class="heading-anchor" ...>#</a></h2>
        preg_match_all(
            '/<h([234])[^>]*>(.*?)<a id="([^"]+)"[^>]*class="heading-anchor"[^>]*>.*?<\/h\1>/is',
            $html,
            $matches,
            PREG_SET_ORDER,
        );

        $toc = [];
        foreach ($matches as $match) {
            $text = trim(strip_tags($match[2]));

            if ($text === '') {
                continue;
            }

            $toc[] = [
                'level' => (int) $match[1],
                'text' => $text,
                'id' => $match[3],
            ];
        }

        return $toc;
    }
}
