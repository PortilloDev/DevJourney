<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Normalises the request Host to the app's canonical origin.
 *
 * Docker delivers requests to the container with a portless `Host`, so signed
 * URLs (Livewire temporary uploads, etc.) are generated for `APP_URL` but
 * validated against the portless request URL. When the incoming host matches the
 * configured host, this corrects the port so signatures validate.
 *
 * A mismatched host is left untouched: a wrong `APP_URL` must not hijack the
 * request host and take the site down, and never adopting an arbitrary host also
 * avoids host-header injection.
 */
class SetRequestHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $url = (string) config('app.url');

        if ($url !== '' && ! $this->shouldSkip()) {
            $host = parse_url($url, PHP_URL_HOST);
            $port = parse_url($url, PHP_URL_PORT);

            if (is_string($host) && $host !== '' && strcasecmp($request->getHost(), $host) === 0) {
                $request->headers->set('HOST', $port !== null ? $host.':'.$port : $host);
                $request->server->set('SERVER_NAME', $host);

                if ($port !== null) {
                    $request->server->set('SERVER_PORT', (string) $port);
                }
            }
        }

        return $next($request);
    }

    /**
     * HTTP middleware never runs for console commands; the guard only skips the
     * real CLI so Artisan commands are unaffected while tests still exercise it.
     */
    protected function shouldSkip(): bool
    {
        return app()->runningInConsole() && ! app()->runningUnitTests();
    }
}
