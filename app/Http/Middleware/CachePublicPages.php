<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\PageCacheService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves cached full-page HTML for public GET requests.
 *
 * The public site has no server-side per-user content — the theme toggle and
 * the reading-progress bar are client-side — so the rendered HTML is identical
 * for every visitor and can be served straight from cache. Invalidation is
 * handled by {@see PageCacheService::bump()} when content changes.
 *
 * Runs inside {@see TrackVisit} so page views are still recorded even when the
 * HTML is served from cache.
 */
class CachePublicPages
{
    private const TTL_LIMIT = 60 * 60 * 24 * 7;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldAttempt($request)) {
            return $next($request);
        }

        $key = app(PageCacheService::class)->pageKey($request);
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $this->cachedResponse($cached);
        }

        $response = $next($request);

        $this->storeIfCacheable($key, $response);
        $this->applyClientCacheHeaders($response);

        return $response;
    }

    /**
     * Request-level eligibility (independent of the generated response).
     */
    protected function shouldAttempt(Request $request): bool
    {
        if (! config('caching.page.enabled', true)) {
            return false;
        }

        if (! $request->isMethod('GET')) {
            return false;
        }

        // No point caching during local Vite development — Blade edits would be
        // hidden until the TTL expires.
        if ($this->isViteDevRequest()) {
            return false;
        }

        $path = '/'.ltrim($request->path(), '/');

        return ! str_starts_with($path, '/admin')
            && ! str_starts_with($path, '/track')
            && ! str_starts_with($path, '/storage')
            && ! str_starts_with($path, '/up');
    }

    /**
     * Response-level eligibility: only cache fully-rendered, HTML pages.
     */
    protected function storeIfCacheable(string $key, Response $response): void
    {
        if (! $response->isOk()) {
            return;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        if (! str_contains($contentType, 'text/html')) {
            return;
        }

        Cache::put($key, $response->getContent(), (int) config('caching.page.ttl', 3600));
    }

    protected function cachedResponse(string $html): Response
    {
        $response = response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        $this->applyClientCacheHeaders($response);

        return $response;
    }

    protected function applyClientCacheHeaders(Response $response): void
    {
        // Short max-age so browsers revalidate soon, with stale-while-revalidate
        // for fast repeat loads after content changes.
        $response->headers->set(
            'Cache-Control',
            'public, max-age=60, stale-while-revalidate='.self::TTL_LIMIT,
        );
    }

    protected function isViteDevRequest(): bool
    {
        return app()->environment('local') && is_file(public_path('hot'));
    }
}
