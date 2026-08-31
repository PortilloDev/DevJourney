<?php

declare(strict_types=1);

namespace App\Services\Vector;

/**
 * Minimal abstraction over a vector store. The current implementation targets
 * Qdrant, but tests can swap in an in-memory store.
 */
interface VectorStore
{
    public function ensureCollection(?int $dimensions = null, ?string $distance = null): void;

    /**
     * @param  list<array{id: string, vector: list<float>, payload: array<string, mixed>}>  $points
     */
    public function upsert(array $points): void;

    /**
     * @param  list<float>  $vector
     * @param  array<string, mixed>|null  $filter
     * @return list<array{id: string, score: float, payload: array<string, mixed>}>
     */
    public function search(array $vector, int $limit = 5, ?array $filter = null): array;

    /**
     * Delete every point whose payload field equals the given value.
     */
    public function deleteWherePayload(string $key, mixed $value): void;
}
