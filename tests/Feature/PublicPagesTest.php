<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EnglishLevel;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('challenges solved');
    }

    public function test_static_pages_render(): void
    {
        foreach (['/about', '/now', '/progress', '/journal', '/challenges', '/projects'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_every_public_page_has_seo_meta_tags(): void
    {
        $post = Post::factory()->create();

        foreach (['/', '/journal', '/challenges', route('posts.show', $post)] as $path) {
            $response = $this->get($path);
            $response->assertOk();
            $response->assertSee('<meta name="description"', false);
            $response->assertSee('property="og:title"', false);
            $response->assertSee('name="twitter:card"', false);
            $response->assertSee('rel="canonical"', false);
        }
    }

    public function test_article_page_emits_json_ld_structured_data(): void
    {
        $post = Post::factory()->create();

        $this->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee('application/ld+json', false)
            ->assertSee('"@type":"Article"', false);
    }

    public function test_english_level_badge_renders_for_each_level(): void
    {
        foreach (EnglishLevel::cases() as $level) {
            $post = Post::factory()->create(['english_level' => $level]);

            $this->get(route('posts.show', $post))
                ->assertOk()
                ->assertSee($level->value);
        }
    }
}
