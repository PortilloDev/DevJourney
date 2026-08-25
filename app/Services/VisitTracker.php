<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ActivityEventType;
use App\Models\Visit;
use App\Models\VisitEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Tracks anonymous browsing sessions across the public site.
 *
 * A "visit" represents one contiguous session for a given visitor (identified
 * by a persistent cookie token). A visit is reused while the visitor's last
 * recorded activity falls within {@see self::TIMEOUT_MINUTES}; otherwise a new
 * visit is started. Every page load creates a {@see VisitEvent} so the admin can
 * audit exactly where each user navigated.
 */
class VisitTracker
{
    public const COOKIE = 'dv_cid';

    public const TIMEOUT_MINUTES = 30;

    /**
     * Record a page view for the current request, creating or continuing a session.
     */
    public function track(Request $request): ?Visit
    {
        if (! $request->isMethod('GET') || $this->shouldSkip($request)) {
            return null;
        }

        $token = $this->visitorToken($request);
        $visit = $this->currentVisit($token);

        if ($visit === null) {
            $visit = $this->startVisit($request, $token);
        } else {
            $visit->last_activity_at = now();
            $visit->page_views = $visit->page_views + 1;
            $visit->save();
        }

        $visit->recordEvent(
            ActivityEventType::PageView,
            $this->pageType($request),
            $request->path(),
            $request->fullUrl(),
            $this->reference($request),
            $this->pageTitle($request),
        );

        return $visit;
    }

    /**
     * Refresh the activity timestamp for the visitor's current session. Called
     * periodically from the frontend while a visitor lingers on a page.
     */
    public function heartbeat(Request $request, ActivityEventType $type = ActivityEventType::Heartbeat): void
    {
        $visit = $this->currentVisit($this->visitorToken($request));

        if ($visit === null) {
            return;
        }

        $visit->last_activity_at = now();
        $visit->save();
    }

    /**
     * Ensure a visitor cookie exists in the response. The token generated here
     * is what the cookie value is signed with.
     */
    public function token(): string
    {
        return (string) Str::uuid();
    }

    public function isBot(Request $request): bool
    {
        $ua = strtolower((string) $request->userAgent());

        if ($ua === '') {
            return false;
        }

        foreach (['bot', 'spider', 'crawler', 'slurp', 'curl', 'wget', 'python', 'postman', 'headless'] as $needle) {
            if (str_contains($ua, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function shouldSkip(Request $request): bool
    {
        if ($this->isBot($request)) {
            return true;
        }

        $path = '/'.ltrim($request->path(), '/');

        $adminPath = config('filament.path', 'admin');

        return str_starts_with($path, '/'.ltrim($adminPath, '/'))
            || str_starts_with($path, '/track')
            || str_starts_with($path, '/storage')
            || str_starts_with($path, '/up')
            || $request->route()?->getName() === 'feed'
            || $request->route()?->getName() === 'sitemap';
    }

    /**
     * The current active visit for a visitor, if it hasn't timed out yet.
     */
    protected function currentVisit(string $token): ?Visit
    {
        return Visit::query()
            ->where('visitor_token', $token)
            ->where('last_activity_at', '>=', now()->subMinutes(self::TIMEOUT_MINUTES))
            ->latest('last_activity_at')
            ->first();
    }

    protected function startVisit(Request $request, string $token): Visit
    {
        $now = now();

        return Visit::create([
            'visitor_token' => $token,
            'user_id' => $request->user()?->getKey(),
            'ip_address' => $request->ip(),
            'country' => $this->country($request),
            'user_agent' => $request->userAgent(),
            'device' => $this->device($request),
            'referer' => $request->headers->get('referer'),
            'entry_url' => $request->fullUrl(),
            'entry_path' => $request->path(),
            'entry_page_type' => $this->pageType($request),
            'page_views' => 1,
            'started_at' => $now,
            'last_activity_at' => $now,
        ]);
    }

    protected function pageType(Request $request): string
    {
        $map = [
            'home' => 'home',
            'posts.index' => 'journal',
            'posts.show' => 'post',
            'posts.category' => 'category',
            'posts.tag' => 'tag',
            'challenges.index' => 'challenges',
            'challenges.show' => 'challenge',
            'projects.index' => 'projects',
            'projects.show' => 'project',
            'about' => 'about',
            'now' => 'now',
            'progress' => 'progress',
        ];

        return $map[$request->route()?->getName()] ?? 'other';
    }

    protected function reference(Request $request): ?string
    {
        foreach (['post', 'category', 'tag', 'challenge', 'project'] as $key) {
            $value = $request->route()?->parameter($key);

            if ($value instanceof Model) {
                $slug = $value->getAttribute('slug');

                if (filled($slug)) {
                    return (string) $slug;
                }

                if ($value->getKey() !== null) {
                    return (string) $value->getKey();
                }
            }
        }

        return null;
    }

    protected function pageTitle(Request $request): ?string
    {
        foreach (['post', 'challenge', 'project', 'category', 'tag'] as $key) {
            $value = $request->route()?->parameter($key);

            if ($value instanceof Model) {
                $title = $value->getAttribute('title') ?? $value->getAttribute('name');

                if (filled($title)) {
                    return (string) $title;
                }
            }
        }

        return null;
    }

    protected function country(Request $request): ?string
    {
        foreach (['CF-IPCountry', 'CloudFront-Viewer-Country', 'X-Country-Code'] as $header) {
            $value = $request->headers->get($header);

            if (filled($value) && strlen($value) === 2) {
                return strtoupper($value);
            }
        }

        return null;
    }

    protected function device(Request $request): string
    {
        $ua = strtolower((string) $request->userAgent());

        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
            return 'tablet';
        }

        if (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return 'mobile';
        }

        return 'desktop';
    }

    protected function visitorToken(Request $request): string
    {
        return (string) ($request->cookie(self::COOKIE) ?: $this->token());
    }
}
