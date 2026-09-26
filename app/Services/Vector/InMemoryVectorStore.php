<?php

declare(strict_types=1);

namespace App\Services\Vector;

/**
 * Non-persistent vector store used in tests and as a fallback when Qdrant is
 * disabled. Stores points in memory and ranks by cosine similarity.
 */
class InMemoryVectorStore implements VectorStore
{
    /** @var list<array{id: string, vector: list<float>, payload: array<string, mixed>}> */
    private array $points = [];

    public function ensureCollection(?int $dimensions = null, ?string $distance = null): void
    {
        // No-op
    }

    /**
     * @param  list<array{id: string, vector: list<float>, payload: array<string, mixed>}>  $points
     */
    public function upsert(array $points): void
    {
        foreach ($points as $point) {
            $this->points = array_filter(
                $this->points,
                fn (array $existing) => $existing['id'] !== $point['id'],
            );
            $this->points[] = $point;
        }
    }

    /**
     * @param  list<float>  $vector
     * @param  array{must?: list<array{key: string, value: mixed}>, should?: list<array{key: string, value: mixed}>}|null  $filter
     * @return list<array{id: string, score: float, payload: array<string, mixed>}>
     */
    public function search(array $vector, int $limit = 5, ?array $filter = null): array
    {
        $ranked = collect($this->points)
            ->filter(fn (array $point) => $this->matches($point['payload'], $filter))
            ->map(fn (array $point) => [
                'id' => $point['id'],
                'score' => $this->cosine($vector, $point['vector']),
                'payload' => $point['payload'],
            ])
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();

        return $ranked;
    }

    public function deleteWherePayload(string $key, mixed $value): void
    {
        $this->points = array_filter(
            $this->points,
            fn (array $point) => ($point['payload'][$key] ?? null) !== $value,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{must?: list<array{key: string, value: mixed}>, should?: list<array{key: string, value: mixed}>}|null  $filter
     */
    protected function matches(array $payload, ?array $filter): bool
    {
        if ($filter === null) {
            return true;
        }

        foreach ($filter['must'] ?? [] as $condition) {
            if (($payload[$condition['key']] ?? null) !== $condition['value']) {
                return false;
            }
        }

        $should = $filter['should'] ?? [];
        if ($should !== []) {
            foreach ($should as $condition) {
                if (($payload[$condition['key']] ?? null) === $condition['value']) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    protected function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;

        foreach ($a as $i => $value) {
            $dot += $value * $b[$i];
            $na += $value ** 2;
            $nb += $b[$i] ** 2;
        }

        if ($na === 0.0 || $nb === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }
}
