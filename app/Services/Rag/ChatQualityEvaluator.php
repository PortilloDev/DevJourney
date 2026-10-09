<?php

declare(strict_types=1);

namespace App\Services\Rag;

use App\Models\Post;
use App\Services\Llm\LlmClient;
use RuntimeException;

/**
 * Runs the versioned evaluation case set against the per-post chat and turns the
 * outcomes into retrieval, answer and refusal metrics (R1, R2, R3).
 *
 * The evaluation never records usage: `askForPost()` is called without an IP and
 * with the answer cache disabled (D5, A1), so a run does not distort the chat
 * history nor serve a stored answer.
 */
class ChatQualityEvaluator
{
    public function __construct(
        private readonly RagService $rag,
        private readonly LlmClient $llm,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $cases
     * @return array{generated_at: string, judge: bool, results: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function evaluate(array $cases, bool $judge = false): array
    {
        $results = [];
        $judgeScores = [];

        foreach ($cases as $index => $case) {
            $result = $this->runCase($case, $index + 1, $judge);

            $results[] = $result;

            if ($result['judge'] !== null) {
                $judgeScores[] = $result['judge'];
            }
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'judge' => $judge,
            'results' => $results,
            'summary' => $this->summarise($results, $judgeScores),
        ];
    }

    /**
     * @param  array<string, mixed>  $case
     * @return array<string, mixed>
     */
    private function runCase(array $case, int $number, bool $judge): array
    {
        $slug = (string) ($case['post_slug'] ?? '');
        $question = (string) ($case['question'] ?? '');
        $expects = (string) ($case['expects'] ?? 'answer');

        $post = Post::query()->published()->where('slug', $slug)->first();

        if ($post === null) {
            throw new RuntimeException("Eval case #{$number}: unknown or unpublished post slug [{$slug}].");
        }

        $result = $this->rag->askForPost($post, $question, useCache: false);

        $rank = $this->retrievalRank($case, $result['sources']);
        $retrievalHit = $rank !== null;

        $answerCorrect = null;
        if ($expects === 'answer') {
            // A retrieval failure is not an answer failure: it is excluded from
            // the answer denominator (R2, D3).
            if ($retrievalHit) {
                $answerCorrect = $this->answerIsCorrect($case, $result);
            }
        }

        $judgeScore = null;
        if ($judge && $expects === 'answer' && $result['refused'] === false) {
            $judgeScore = $this->judge($question, (string) $result['context'], (string) $result['answer']);
        }

        return [
            'post_slug' => $slug,
            'question' => $question,
            'expects' => $expects,
            'refused' => (bool) $result['refused'],
            'retrieval_hit' => $retrievalHit,
            'rank' => $rank,
            'answer_correct' => $answerCorrect,
            'judge' => $judgeScore,
            'sources' => count($result['sources']),
            'tokens_in' => (int) $result['tokens_in'],
            'tokens_out' => (int) $result['tokens_out'],
        ];
    }

    /**
     * 1-based position of the first source whose title or heading contains the
     * expected fragment, or null on a miss (R2, D3).
     *
     * @param  array<string, mixed>  $case
     * @param  list<array<string, mixed>>  $sources
     */
    private function retrievalRank(array $case, array $sources): ?int
    {
        $expected = strtolower(trim((string) ($case['expected_source'] ?? '')));

        if ($expected === '') {
            return null;
        }

        foreach (array_values($sources) as $position => $source) {
            $haystack = strtolower(trim((string) ($source['title'] ?? '').' '.(string) ($source['heading'] ?? '')));

            if ($haystack !== '' && str_contains($haystack, $expected)) {
                return $position + 1;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $case
     * @param  array<string, mixed>  $result
     */
    private function answerIsCorrect(array $case, array $result): bool
    {
        if ($result['refused'] === true) {
            return false;
        }

        $answer = strtolower((string) $result['answer']);

        foreach ((array) ($case['must_include'] ?? []) as $term) {
            if (! str_contains($answer, strtolower((string) $term))) {
                return false;
            }
        }

        return true;
    }

    private function judge(string $question, string $context, string $answer): int
    {
        $messages = [
            ['role' => 'system', 'content' => 'You are a strict evaluator. Score from 0 to 2 how well the answer is grounded in the provided context: 2 fully supported, 1 partially, 0 unsupported or hallucinated. Reply with a single digit.'],
            ['role' => 'user', 'content' => "Question:\n{$question}\n\nContext:\n{$context}\n\nAnswer:\n{$answer}"],
        ];

        $model = (string) config('rag.eval.judge_model', config('rag.chat_model'));

        $response = $this->llm->complete($messages, null, $model ?: null);

        if (preg_match('/[0-2]/', (string) $response['content'], $matches) !== 1) {
            return 0;
        }

        return (int) $matches[0];
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @param  list<int>  $judgeScores
     * @return array<string, mixed>
     */
    private function summarise(array $results, array $judgeScores): array
    {
        $answerCases = array_values(array_filter($results, fn (array $r): bool => $r['expects'] === 'answer'));
        $refusalCases = array_values(array_filter($results, fn (array $r): bool => $r['expects'] !== 'answer'));

        $retrievalHits = count(array_filter($answerCases, fn (array $r): bool => $r['retrieval_hit'] === true));
        $evaluated = count(array_filter($answerCases, fn (array $r): bool => $r['answer_correct'] !== null));
        $answerCorrect = count(array_filter($answerCases, fn (array $r): bool => $r['answer_correct'] === true));
        $refusalCorrect = count(array_filter($refusalCases, fn (array $r): bool => $r['refused'] === true));

        $mrr = 0.0;
        foreach ($answerCases as $row) {
            if (is_int($row['rank'])) {
                $mrr += 1 / $row['rank'];
            }
        }
        $mrr = $answerCases !== [] ? $mrr / count($answerCases) : 0.0;

        $correctCases = $answerCorrect + $refusalCorrect;
        $total = count($results);

        return [
            'total' => $total,
            'answer_cases' => count($answerCases),
            'refusal_cases' => count($refusalCases),
            'retrieval_hits' => $retrievalHits,
            'retrieval_failures' => count($answerCases) - $retrievalHits,
            'retrieval_hit_rate' => $answerCases !== [] ? $retrievalHits / count($answerCases) : null,
            'mrr' => $mrr,
            'answer_evaluated' => $evaluated,
            'answer_correct' => $answerCorrect,
            'answer_failures' => $evaluated - $answerCorrect,
            'answer_accuracy' => $evaluated > 0 ? $answerCorrect / $evaluated : null,
            'refusal_correct' => $refusalCorrect,
            'refusal_accuracy' => $refusalCases !== [] ? $refusalCorrect / count($refusalCases) : null,
            'correct_cases' => $correctCases,
            'pass_rate' => $total > 0 ? $correctCases / $total : 0.0,
            'judge_scores' => count($judgeScores),
            'judge_average' => $judgeScores !== [] ? array_sum($judgeScores) / count($judgeScores) : null,
        ];
    }
}
