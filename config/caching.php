<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content cache TTL
    |--------------------------------------------------------------------------
    |
    | Public content (posts, projects, challenges) is almost static, so the
    | generated pages are cached server-side for a generous lifetime. The TTL
    | is the number of seconds a page stays in cache before being re-rendered.
    | 3600s = 1 hour; content rarely changes so a longer TTL is acceptable.
    | Cache is invalidated immediately on any content edit/delete, so the TTL
    | is only a fallback (e.g. if Redis is flushed or the counter drifts).
    |
    */
    'ttl' => (int) env('CONTENT_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Full-page cache
    |--------------------------------------------------------------------------
    |
    | Caches the entire rendered HTML for public GET pages. Because the public
    | site has no server-side per-user content (theme toggle and the reading
    | progress bar are client-side), the HTML is identical for every visitor.
    |
    | Disabled automatically when the Vite dev server is running so Blade
    | changes are not masked during development.
    |
    */
    'page' => [
        'enabled' => filter_var(env('PUBLIC_PAGE_CACHE', true), FILTER_VALIDATE_BOOLEAN),
        'ttl' => (int) env('PUBLIC_PAGE_CACHE_TTL', 3600),
    ],

];
