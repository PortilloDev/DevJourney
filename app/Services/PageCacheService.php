<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Manages a monotonically-increasing content revision used to namespace cached
 * public pages. When any content model changes, {@see self::bump()} increments
 * the revision, which effectively invalidates every previously-cached page for
 * that revision. Old entries are left to expire by TTL — there is no need to
 * enumerate paginated/filtered URLs.
 */
class PageCacheService
{
    /**
     * Single revision counter shared by every public page. A single counter is
     * intentional: this is a small site where content edits are rare, so
     * invalidating all public pages on any change is cheap and always correct.
     */
    private const REVISION_KEY = 'page_cache.revision';

    /**
     * Current content revision. Defaults to 1 before any bump.
     */
    public function revision(): int
    {
        return (int) Cache::get(self::REVISION_KEY, 1);
    }

    /**
     * Invalidate cached public pages. Call this whenever content changes.
     */
    public function bump(): void
    {
        // Seed so the increment always results in a different value, even when
        // the key is missing (e.g. after Redis flush or in a fresh env).
        Cache::add(self::REVISION_KEY, 1);
        Cache::increment(self::REVISION_KEY);
    }

    /**
     * Cache key for the given public request, namespaced by the current revision
     * and the full URL (path + query string) so paginated and filtered pages
     * each get their own entry.
     */
    public function pageKey(Request $request): string
    {
        return 'public_page.'.$this->revision().'.'.hash('sha256', $request->method().'|'.$request->fullUrl());
    }
}
