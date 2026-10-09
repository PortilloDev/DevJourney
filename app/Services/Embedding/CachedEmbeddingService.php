<?php

declare(strict_types=1);

namespace App\Services\Embedding;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Caches question embeddings so a repeated question (normalized, same model) is
 * answered without a second call to the provider (R5, D9). The question is not
 * scoped to a post: the same text always yields the same vector.
 *
 * The decorator leaves the concrete provider untouched. Bulk indexing calls
 * {@see self::embedMany()} and is delegated as-is — it is content, not a
 * question, so it is not cached.
 */
class CachedEmbeddingService implements EmbeddingService
{
    private int $ttl;

    private string $model;

    public function __construct(private readonly EmbeddingService $inner)
    {
        $this->ttl = (int) config('rag.cache.embedding_ttl', 604800);
        $this->model = (string) config('rag.embeddings.model', 'unknown');
    }

    public function embed(string $text): array
    {
        $key = $this->key($text);
        $cached = Cache::get($key);

        if (is_array($cached)) {
            /** @var list<float> $cached */
            return array_map('floatval', $cached);
        }

        $vector = $this->inner->embed($text);
        Cache::put($key, $vector, $this->ttl);

        return $vector;
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedMany(array $texts): array
    {
        return $this->inner->embedMany($texts);
    }

    public function dimensions(): int
    {
        return $this->inner->dimensions();
    }

    private function key(string $text): string
    {
        return 'chat:vec:'.$this->model.':'.sha1($this->normalize($text));
    }

    private function normalize(string $text): string
    {
        $lowered = Str::lower(Str::ascii(trim($text)));
        $collapsed = preg_replace('/\s+/', ' ', $lowered);

        return trim($collapsed ?? $lowered);
    }
}
