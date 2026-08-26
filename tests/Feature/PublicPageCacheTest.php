<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use App\Services\PageCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicPageCacheTest extends TestCase
{
    use RefreshDatabase;

    private PageCacheService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PageCacheService::class);
    }

    public function test_public_pages_are_cached_with_client_headers(): void
    {
        $response = $this->get('/')->assertOk();

        $this->assertStringContainsString('max-age=60', (string) $response->headers->get('Cache-Control'));
    }

    public function test_cached_html_is_served_without_rerendering(): void
    {
        $post = Post::factory()->create(['title' => 'Cached Beacon Post']);

        $this->get('/journal')->assertOk()->assertSee('Cached Beacon Post');

        // Delete directly via the query builder to bypass Eloquent events, so the
        // cache is NOT invalidated. The second request must serve the cached HTML.
        DB::table('posts')->delete();

        $this->get('/journal')->assertOk()->assertSee('Cached Beacon Post');
    }

    public function test_revision_bump_invalidates_cached_pages(): void
    {
        $post = Post::factory()->create(['title' => 'Before Edit']);

        $this->get('/journal')->assertOk()->assertSee('Before Edit');

        // A real Eloquent save triggers the cache invalidation (revision bump).
        $post = Post::query()->firstOrFail();
        $post->update(['title' => 'After Edit']);

        $this->get('/journal')->assertOk()->assertSee('After Edit');
    }

    public function test_revision_increments_and_changes_the_page_key(): void
    {
        $request = Request::create('http://localhost/journal', 'GET');

        $revision = $this->service->revision();
        $keyBefore = $this->service->pageKey($request);

        $this->service->bump();

        $this->assertGreaterThan($revision, $this->service->revision());
        $this->assertNotSame($keyBefore, $this->service->pageKey($request));
    }

    public function test_saving_content_models_invalidates_the_cache(): void
    {
        $before = $this->service->revision();

        // Same revision after an unrelated bump-less operation.
        Post::factory()->create();
        $this->assertGreaterThan($before, $this->service->revision());

        $r = $this->service->revision();
        Post::query()->firstOrFail()->delete();
        $this->assertGreaterThan($r, $this->service->revision());
    }
}
