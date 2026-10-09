<?php

declare(strict_types=1);

namespace App\Services\Llm;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Provider circuit breaker backed by the shared cache. Consecutive failures are
 * counted in `ai:circuit:failures`; once they exceed the configured threshold the
 * circuit opens by writing `ai:circuit:opened_at` with the cooldown as TTL, so it
 * closes on its own when the cooldown elapses. A successful turn resets both
 * (R2, D2).
 */
class CircuitBreaker
{
    private const FAILURES_KEY = 'ai:circuit:failures';

    private const OPENED_AT_KEY = 'ai:circuit:opened_at';

    /**
     * Whether the circuit is currently open (cooling down).
     */
    public function isOpen(): bool
    {
        return Cache::has(self::OPENED_AT_KEY);
    }

    /**
     * When the circuit opened, if it is open.
     */
    public function openedAt(): ?Carbon
    {
        $value = Cache::get(self::OPENED_AT_KEY);

        return is_numeric($value) ? Carbon::createFromTimestamp((int) $value) : null;
    }

    /**
     * Consecutive failures recorded so far.
     */
    public function failures(): int
    {
        return (int) Cache::get(self::FAILURES_KEY, 0);
    }

    /**
     * A successful turn clears the failure streak and closes the circuit.
     */
    public function recordSuccess(): void
    {
        Cache::forget(self::FAILURES_KEY);
        Cache::forget(self::OPENED_AT_KEY);
    }

    /**
     * A provider failure extends the streak and may open the circuit.
     */
    public function recordFailure(): void
    {
        Cache::add(self::FAILURES_KEY, 0);
        $failures = (int) Cache::increment(self::FAILURES_KEY);

        if ($failures > $this->threshold()) {
            Cache::put(self::OPENED_AT_KEY, now()->getTimestamp(), $this->cooldown());
        }
    }

    public function threshold(): int
    {
        return (int) config('rag.circuit.threshold', 5);
    }

    public function cooldown(): int
    {
        return (int) config('rag.circuit.cooldown_seconds', 300);
    }
}
