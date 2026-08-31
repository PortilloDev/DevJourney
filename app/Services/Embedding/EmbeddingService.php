<?php

declare(strict_types=1);

namespace App\Services\Embedding;

interface EmbeddingService
{
    /**
     * @return list<float>
     */
    public function embed(string $text): array;

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedMany(array $texts): array;

    public function dimensions(): int;
}
