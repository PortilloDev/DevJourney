<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_rss_feed_returns_valid_xml(): void
    {
        $post = Post::factory()->create(['title' => 'Feed Post']);

        $response = $this->get('/feed');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
        $response->assertSee('<rss version="2.0"', false);
        $response->assertSee('Feed Post');

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'RSS feed is not well-formed XML');
        $this->assertSame('Feed Post', (string) $xml->channel->item[0]->title);
    }

    public function test_sitemap_returns_valid_xml_with_published_urls(): void
    {
        $post = Post::factory()->create();

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertSee('<urlset', false);

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'Sitemap is not well-formed XML');
        $this->assertStringContainsString($post->slug, $response->getContent());
    }
}
