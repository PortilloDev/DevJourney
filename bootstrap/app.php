<?php

use App\Http\Middleware\CachePublicPages;
use App\Http\Middleware\SetSecurityHeaders;
use App\Http\Middleware\TrackVisit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Trusted reverse proxies. Use `*` only in local dev; in production list the
// real proxy IPs/CIDRs (see .env.example). Empty/`none` trusts no proxy.
$trustedProxies = trim((string) env('TRUSTED_PROXIES', '*'));
$trustedProxies = match (true) {
    $trustedProxies === '*' => '*',
    $trustedProxies === '' => [],
    default => collect(explode(',', $trustedProxies))
        ->map(fn (string $ip) => trim($ip))
        ->filter()
        ->values()
        ->all(),
};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) use ($trustedProxies) {
        // TrackVisit runs first so page views are recorded even for cached HTML.
        $middleware->web(append: [
            TrackVisit::class,
            CachePublicPages::class,
        ]);

        $middleware->append(SetSecurityHeaders::class);
        $middleware->trustProxies(at: $trustedProxies);

        $middleware->validateCsrfTokens(except: [
            'track/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
