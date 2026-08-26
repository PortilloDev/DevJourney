<?php

use App\Http\Middleware\CachePublicPages;
use App\Http\Middleware\SetSecurityHeaders;
use App\Http\Middleware\TrackVisit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // TrackVisit runs first so page views are recorded even for cached HTML.
        $middleware->web(append: [
            TrackVisit::class,
            CachePublicPages::class,
        ]);

        $middleware->append(SetSecurityHeaders::class);

        $middleware->validateCsrfTokens(except: [
            'track/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
