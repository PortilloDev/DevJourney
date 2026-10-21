<?php

declare(strict_types=1);

namespace App\Services\Rag;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Daily chat quota per visitor plus a site-wide ceiling on model calls.
 *
 * Both windows end at the application's midnight, so the counters are stored
 * with a decay that reaches that instant. Reservations are atomic through the
 * rate limiter, so two simultaneous requests from the same visitor cannot both
 * take the last slot of the day.
 */
class ChatQuota
{
    /**
     * The error code of the limit that blocks this visitor, or null when a new
     * model call would be allowed. Checks without consuming.
     */
    public function blockedCode(string $ip): ?string
    {
        if (RateLimiter::tooManyAttempts($this->globalKey(), $this->globalMax())) {
            return 'daily_global_limit';
        }

        if (RateLimiter::tooManyAttempts($this->visitorKey($ip), $this->visitorMax())) {
            return 'daily_request_limit';
        }

        return null;
    }

    /**
     * Seconds until the quota resets when the visitor or the site is already
     * over a limit, or null when a new model call would be allowed.
     */
    public function availableIn(string $ip): ?int
    {
        $code = $this->blockedCode($ip);

        if ($code === null) {
            return null;
        }

        $key = $code === 'daily_global_limit' ? $this->globalKey() : $this->visitorKey($ip);

        return RateLimiter::availableIn($key);
    }

    /**
     * Atomically reserves one model call for the visitor and for the site.
     * Returns false when either limit is already reached.
     */
    public function consume(string $ip): bool
    {
        if ($this->availableIn($ip) !== null) {
            return false;
        }

        if (! RateLimiter::attempt($this->visitorKey($ip), $this->visitorMax(), fn (): bool => true, $this->decay())) {
            return false;
        }

        RateLimiter::hit($this->globalKey(), $this->decay());

        return true;
    }

    private function visitorKey(string $ip): string
    {
        return 'ai:req:'.$ip.':'.now()->toDateString();
    }

    private function globalKey(): string
    {
        return 'ai:global:'.now()->toDateString();
    }

    private function visitorMax(): int
    {
        return (int) config('rag.limits.requests_per_day', 20);
    }

    private function globalMax(): int
    {
        return (int) config('rag.limits.global_requests_per_day', 500);
    }

    private function decay(): int
    {
        return max(1, now()->endOfDay()->getTimestamp() - now()->getTimestamp());
    }
}
