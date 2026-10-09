<?php

declare(strict_types=1);

namespace App\Services\Rag;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Cache of chat answers scoped to a post, and the per-post revision counter that
 * invalidates every previously stored answer when the post is edited (R3, D2).
 *
 * The cache key is `chat:ans:{revision}:{postId}:{sha1(question)}`. Because the
 * revision is part of the key, bumping it makes older entries unreachable
 * without enumerating Redis keys; they are left to expire by TTL.
 */
class ChatAnswerCache
{
    private const REVISION_PREFIX = 'chat:rev:post:';

    private const ANSWER_PREFIX = 'chat:ans:';

    private int $answerTtl;

    public function __construct()
    {
        $this->answerTtl = (int) config('rag.cache.answer_ttl', 604800);
    }

    /**
     * Normalize a question so capitalisation, accents and spacing do not create
     * distinct entries. Word order is preserved on purpose (D1).
     */
    public function normalize(string $question): string
    {
        $lowered = Str::lower(Str::ascii(trim($question)));
        $collapsed = preg_replace('/\s+/', ' ', $lowered);

        return trim($collapsed ?? $lowered);
    }

    /**
     * Current revision for a post. Defaults to 0 before any edit.
     */
    public function revision(int $postId): int
    {
        return (int) Cache::get(self::REVISION_PREFIX.$postId, 0);
    }

    /**
     * Invalidate every stored answer for a post by moving it to a new revision.
     */
    public function bumpRevision(int $postId): void
    {
        $key = self::REVISION_PREFIX.$postId;

        // Seed so the increment always changes the value, even on a cold cache.
        Cache::add($key, 0);
        Cache::increment($key);
    }

    /**
     * @return array{answer: string, sources: list<array<string, mixed>>, model: string}|null
     */
    public function get(int $postId, string $question): ?array
    {
        $value = Cache::get($this->key($postId, $question));

        if (! is_array($value) || ! isset($value['answer']) || ! is_string($value['answer'])) {
            return null;
        }

        /** @var array{answer: string, sources: list<array<string, mixed>>, model: string} $value */
        return $value;
    }

    /**
     * @param  array{answer: string, sources: list<array<string, mixed>>, model: string}  $value
     */
    public function put(int $postId, string $question, array $value): void
    {
        Cache::put($this->key($postId, $question), $value, $this->answerTtl);
    }

    private function key(int $postId, string $question): string
    {
        return self::ANSWER_PREFIX.$this->revision($postId).':'.$postId.':'.sha1($this->normalize($question));
    }
}
