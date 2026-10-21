<?php

declare(strict_types=1);

namespace App\Services\Rag;

/**
 * One-way derivation of a visitor IP for storage and quota keys.
 *
 * The chat quotas need exact-match lookups and an indexed column, so the IP is
 * hashed (HMAC-SHA256 keyed with the app key) rather than encrypted: a hashed
 * value is stable and comparable, while a randomized-IV ciphertext is not. The
 * original IP is never needed back.
 */
class IpHasher
{
    public function hash(string $ip): string
    {
        return substr(hash_hmac('sha256', $ip, (string) config('app.key')), 0, 64);
    }
}
