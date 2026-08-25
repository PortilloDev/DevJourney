<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\VisitTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records a page view for the public site on every GET request and keeps the
 * anonymous visitor cookie alive so the session can be followed across requests.
 */
class TrackVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $visit = app(VisitTracker::class)->track($request);

        if ($visit && ! $request->cookie(VisitTracker::COOKIE)) {
            $response->withCookie($this->visitorCookie($visit->visitor_token));
        }

        return $response;
    }

    protected function visitorCookie(string $token): Cookie
    {
        return cookie()
            ->make(VisitTracker::COOKIE, $token, 60 * 24 * 365, '/', null, false, true, false, 'lax');
    }
}
