<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rag\ChatQualityEvaluator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Runs the versioned evaluation set and publishes the quality report (R1).
 *
 * Not scheduled on purpose: every run spends real provider quota, so it is a
 * manual check (see README).
 */
class EvalChatQuality extends Command
{
    protected $signature = 'ai:eval
                            {--judge : Score each answer grounding with the evaluator model}
                            {--min= : Minimum pass rate; below it the exit code is non-zero}';

    protected $description = 'Run the versioned chat evaluation set and report retrieval, answer and refusal metrics.';

    public function handle(ChatQualityEvaluator $evaluator): int
    {
        $path = base_path('tests/Eval/cases.json');

        if (! File::exists($path)) {
            $this->error("Eval case set not found at [{$path}].");

            return self::FAILURE;
        }

        $cases = json_decode((string) File::get($path), true);

        if (! is_array($cases) || $cases === []) {
            $this->error('The eval case set is empty or not valid JSON.');

            return self::FAILURE;
        }

        $report = $evaluator->evaluate(array_values($cases), (bool) $this->option('judge'));

        /** @var list<array<string, mixed>> $results */
        $results = $report['results'];
        /** @var array<string, mixed> $summary */
        $summary = $report['summary'];

        $this->renderTable($results);
        $this->renderSummary($summary);

        $timestamp = now()->format('Y-m-d_His');
        $file = 'eval/'.$timestamp.'.json';

        Storage::disk('local')->put(
            $file,
            (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        $this->line('Report written to '.Storage::disk('local')->path($file));

        $min = $this->option('min') !== null
            ? (float) $this->option('min')
            : (float) config('rag.eval.min_score', 0);

        $passRate = (float) $summary['pass_rate'];

        if ($passRate < $min) {
            $this->error(sprintf('Pass rate %.2f is below the minimum %.2f.', $passRate, $min));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $results
     */
    private function renderTable(array $results): void
    {
        $rows = [];

        foreach ($results as $row) {
            $rows[] = [
                Str::limit((string) $row['question'], 48),
                $row['expects'] === 'answer'
                    ? ($row['retrieval_hit'] === true ? 'hit #'.$row['rank'] : 'miss')
                    : '—',
                $row['expects'] === 'answer'
                    ? ($row['answer_correct'] === null ? '—' : ($row['answer_correct'] === true ? 'ok' : 'fail'))
                    : '—',
                $row['expects'] === 'refusal'
                    ? ($row['refused'] === true ? 'ok' : 'fail')
                    : '—',
                $row['judge'] === null ? '—' : (string) $row['judge'],
            ];
        }

        $this->table(['Question', 'Retrieval', 'Answer', 'Refusal', 'Judge'], $rows);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function renderSummary(array $summary): void
    {
        $this->newLine();
        $this->line('Cases: '.$summary['total']
            .' ('.$summary['answer_cases'].' answer, '.$summary['refusal_cases'].' refusal)');
        $this->line('Retrieval hit rate: '.$this->rate($summary['retrieval_hit_rate'])
            .'  MRR: '.number_format((float) $summary['mrr'], 3)
            .'  failures: '.$summary['retrieval_failures']);
        $this->line('Answer accuracy: '.$this->rate($summary['answer_accuracy'])
            .'  failures: '.$summary['answer_failures']
            .' (of '.$summary['answer_evaluated'].' evaluated)');
        $this->line('Refusal accuracy: '.$this->rate($summary['refusal_accuracy']));
        $this->line('Pass rate: '.$this->rate($summary['pass_rate']));

        if ($summary['judge_scores'] > 0) {
            $this->line('Judge average grounding (0-2): '.number_format((float) $summary['judge_average'], 2));
        }
    }

    private function rate(mixed $value): string
    {
        return $value === null ? 'n/a' : number_format((float) $value, 2);
    }
}
