<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects the Prometheus metrics endpoint with HTTP Basic Auth.
 *
 * The credentials come from `prometheus.basic_auth` (PROMETHEUS_USER /
 * PROMETHEUS_PASSWORD). Without configured credentials the endpoint is only
 * reachable in the local environment; anywhere else it is refused so that a
 * missing password can never leave production metrics open. The Prometheus
 * scrape job sends the same credentials via its `basic_auth` block.
 */
class PrometheusBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $username = (string) config('prometheus.basic_auth.username', '');
        $password = (string) config('prometheus.basic_auth.password', '');

        if ($username === '' || $password === '') {
            return app()->environment('local') ? $next($request) : $this->unauthorized();
        }

        $header = $request->header('Authorization');

        if ($header === null || ! str_starts_with($header, 'Basic ')) {
            return $this->unauthorized();
        }

        $provided = base64_decode(substr($header, 6), true);

        if ($provided === false || ! hash_equals($username.':'.$password, $provided)) {
            return $this->unauthorized();
        }

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response('Unauthorized.', 401, ['WWW-Authenticate' => 'Basic realm="prometheus"']);
    }
}
