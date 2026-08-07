<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_markdown_is_pre_rendered_to_html_on_save(): void
    {
        $post = Post::factory()->create(['body_md' => '# Heading']);

        $this->assertStringContainsString('<h1', $post->body_html);
        $this->assertGreaterThan(0, $post->reading_minutes);
    }

    public function test_slug_is_generated_and_deduplicated(): void
    {
        $a = Post::factory()->create(['title' => 'Same Title', 'slug' => null]);
        $b = Post::factory()->create(['title' => 'Same Title', 'slug' => null]);

        $this->assertSame('same-title', $a->slug);
        $this->assertSame('same-title-2', $b->slug);
    }

    public function test_only_published_posts_appear_on_public_routes(): void
    {
        $published = Post::factory()->create(['title' => 'Visible Post']);
        $draft = Post::factory()->draft()->create(['title' => 'Hidden Draft']);
        $scheduled = Post::factory()->scheduled()->create(['title' => 'Future Post']);

        $index = $this->get('/journal');
        $index->assertOk();
        $index->assertSee('Visible Post');
        $index->assertDontSee('Hidden Draft');
        $index->assertDontSee('Future Post');

        $this->get(route('posts.show', $draft))->assertNotFound();
        $this->get(route('posts.show', $scheduled))->assertNotFound();
        $this->get(route('posts.show', $published))->assertOk();
    }

    public function test_published_scope_excludes_drafts_and_future_posts(): void
    {
        Post::factory()->create();
        Post::factory()->draft()->create();
        Post::factory()->scheduled()->create();

        $this->assertSame(1, Post::query()->published()->count());
    }

    public function test_publishing_sets_status_and_published_at(): void
    {
        $post = Post::factory()->draft()->create();
        $this->assertNull($post->published_at);

        $post->update(['status' => PostStatus::Published, 'published_at' => now()]);

        $this->assertSame(PostStatus::Published, $post->fresh()->status);
        $this->assertNotNull($post->fresh()->published_at);
    }
}
