<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeaturedPostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_featured_posts_render_in_the_featured_section_on_home(): void
    {
        Post::factory()->create(['title' => 'Starred Post', 'featured' => true]);
        Post::factory()->create(['title' => 'Regular Post', 'featured' => false]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Featured', false)
            ->assertSee('Starred Post')
            ->assertSee('Regular Post');
    }

    public function test_featured_posts_render_in_the_featured_section_on_the_journal(): void
    {
        Post::factory()->create(['title' => 'Starred Journal Post', 'featured' => true]);
        Post::factory()->create(['title' => 'Regular Journal Post', 'featured' => false]);

        $this->get('/journal')
            ->assertOk()
            ->assertSee('Featured', false)
            ->assertSee('Starred Journal Post');
    }

    public function test_the_main_journal_list_excludes_featured_posts(): void
    {
        Post::factory()->create(['title' => 'Duplicate Title', 'featured' => true]);

        $response = $this->get('/journal')->assertOk();

        // The featured card renders the title, but it must not double-render in
        // the regular grid below it.
        $this->assertSame(1, substr_count($response->getContent(), 'Duplicate Title'));
    }

    public function test_featured_posts_are_not_shown_when_none_are_marked(): void
    {
        Post::factory()->create(['title' => 'Unfeatured Post', 'featured' => false]);

        $response = $this->get('/')->assertOk();

        $this->assertStringNotContainsString('>Featured</', $response->getContent());
    }
}
