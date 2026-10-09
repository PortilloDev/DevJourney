<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiUsage;
use App\Models\Post;
use App\Services\Embedding\EmbeddingService;
use App\Services\Llm\LlmClient;
use App\Services\Rag\ChatAnswerCache;
use App\Services\Rag\RagService;
use App\Services\Vector\InMemoryVectorStore;
use App\Services\Vector\VectorStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers the chat quality evaluation spec: R1–R5 plus amendments A1 and A3.
 */
class EvalChatQualityTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryVectorStore $store;

    private EvalEmbedding $embedding;

    private EvalLlm $llm;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Storage::fake('local');

        config([
            'rag.api_key' => 'test',
            'rag.embeddings.api_key' => 'test',
            'rag.qdrant.enabled' => false,
            'rag.retrieval.min_similarity' => 0.35,
            'rag.post_chat.max_answer_tokens' => 120,
            'rag.post_chat.related_threshold' => 0.5,
            'rag.post_chat.unrelated_threshold' => 0.15,
            'rag.eval.judge_model' => 'judge-model',
        ]);

        $this->store = new InMemoryVectorStore;
        $this->embedding = new EvalEmbedding;
        $this->llm = new EvalLlm;

        $this->app->instance(VectorStore::class, $this->store);
        $this->app->instance(EmbeddingService::class, $this->embedding);
        $this->app->instance(LlmClient::class, $this->llm);
    }

    private function evalPost(): Post
    {
        $post = Post::factory()->create([
            'slug' => 'hexagonal-eval',
            'title' => 'Hexagonal architecture',
            'excerpt' => 'Ports and adapters',
            'body_md' => '## Intro'.PHP_EOL.'Hexagonal architecture keeps the domain isolated from frameworks.',
        ]);

        app(RagService::class)->indexContent($post);

        return $post;
    }

    /**
     * @param  list<array<string, mixed>>  $cases
     * @return array<string, mixed>
     */
    private function runEval(array $cases, array $options = []): array
    {
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('get')->andReturn((string) json_encode($cases));

        $this->artisan('ai:eval', $options)->assertExitCode($options['expected_exit'] ?? 0);

        $files = Storage::disk('local')->files('eval');
        $this->assertNotEmpty($files, 'The evaluation should write a report.');

        /** @var array<string, mixed> $report */
        $report = json_decode((string) Storage::disk('local')->get($files[0]), true);

        return $report;
    }

    // R1-E1: a full run publishes a report with retrieval, MRR and answer metrics.
    public function test_command_publishes_a_report_with_the_metrics(): void
    {
        $this->evalPost();

        $report = $this->runEval([
            [
                'post_slug' => 'hexagonal-eval',
                'question' => 'How does hexagonal architecture isolate the domain?',
                'expects' => 'answer',
                'must_include' => ['domain'],
                'expected_source' => 'Hexagonal',
            ],
            [
                'post_slug' => 'hexagonal-eval',
                'question' => 'What is the capital of France?',
                'expects' => 'refusal',
            ],
        ]);

        $summary = $report['summary'];

        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, $summary['answer_cases']);
        $this->assertSame(1, $summary['refusal_cases']);
        $this->assertSame(1, $summary['retrieval_hits']);
        $this->assertEquals(1.0, $summary['mrr']);
        $this->assertEquals(1.0, $summary['answer_accuracy']);
        $this->assertEquals(1.0, $summary['refusal_accuracy']);
        $this->assertEquals(1.0, $summary['pass_rate']);
    }

    // R1-E2: a result below the configured minimum yields a non-zero exit code.
    public function test_command_returns_non_zero_below_the_minimum(): void
    {
        $this->evalPost();

        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('get')->andReturn((string) json_encode([
            [
                'post_slug' => 'hexagonal-eval',
                'question' => 'How does hexagonal architecture isolate the domain?',
                'expects' => 'answer',
                'must_include' => ['domain'],
                'expected_source' => 'Nowhere',
            ],
        ]));

        $this->artisan('ai:eval', ['--min' => '0.99'])->assertExitCode(1);
    }

    // R2-E1: a retrieval miss is a retrieval failure, not an answer failure.
    public function test_retrieval_failure_is_not_counted_as_answer_failure(): void
    {
        $this->evalPost();

        $report = $this->runEval([
            [
                'post_slug' => 'hexagonal-eval',
                'question' => 'How does hexagonal architecture isolate the domain?',
                'expects' => 'answer',
                'must_include' => ['domain'],
                'expected_source' => 'Nonexistent section',
            ],
        ]);

        $this->assertFalse($report['results'][0]['retrieval_hit']);
        $this->assertNull($report['results'][0]['answer_correct']);
        $this->assertSame(1, $report['summary']['retrieval_failures']);
        $this->assertSame(0, $report['summary']['answer_failures']);
        $this->assertSame(0, $report['summary']['answer_evaluated']);
    }

    // R3-E1: an out-of-scope case fails if the system answers instead of refusing.
    public function test_out_of_scope_case_fails_when_answered(): void
    {
        $this->evalPost();

        $report = $this->runEval([
            [
                'post_slug' => 'hexagonal-eval',
                'question' => 'How does hexagonal architecture isolate the domain?',
                'expects' => 'refusal',
            ],
        ]);

        $this->assertFalse($report['results'][0]['refused']);
        $this->assertEquals(0.0, $report['summary']['refusal_accuracy']);
    }

    // R4-E1: a full run keeps the usage history unchanged.
    public function test_evaluation_does_not_record_usage(): void
    {
        $this->evalPost();

        AiUsage::query()->create(['ip_address' => '1.1.1.1', 'question' => 'seed', 'model' => 'm']);
        AiUsage::query()->create(['ip_address' => '1.1.1.1', 'question' => 'seed', 'model' => 'm']);

        $this->runEval([
            [
                'post_slug' => 'hexagonal-eval',
                'question' => 'How does hexagonal architecture isolate the domain?',
                'expects' => 'answer',
                'must_include' => ['domain'],
                'expected_source' => 'Hexagonal',
            ],
        ]);

        $this->assertDatabaseCount('ai_usage', 2);
    }

    // A1: the evaluation neither reads nor writes the answer cache.
    public function test_evaluation_bypasses_the_answer_cache(): void
    {
        $post = $this->evalPost();

        $cases = [
            [
                'post_slug' => 'hexagonal-eval',
                'question' => 'How does hexagonal architecture isolate the domain?',
                'expects' => 'answer',
                'must_include' => ['domain'],
                'expected_source' => 'Hexagonal',
            ],
        ];

        $this->runEval($cases);
        $this->assertSame(1, $this->llm->calls);

        $this->assertNull(app(ChatAnswerCache::class)->get($post->getKey(), 'How does hexagonal architecture isolate the domain?'));

        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('get')->andReturn((string) json_encode($cases));
        $this->artisan('ai:eval')->assertExitCode(0);

        $this->assertSame(2, $this->llm->calls, 'The second run must not reuse a cached answer.');
    }

    // R5-E1 + A3: --judge scores grounding per case using the projected context.
    public function test_judge_scores_grounding_per_case(): void
    {
        $this->evalPost();

        $report = $this->runEval([
            [
                'post_slug' => 'hexagonal-eval',
                'question' => 'How does hexagonal architecture isolate the domain?',
                'expects' => 'answer',
                'must_include' => ['domain'],
                'expected_source' => 'Hexagonal',
            ],
        ], ['--judge' => true]);

        $this->assertSame(2, $report['results'][0]['judge']);
        $this->assertEquals(2.0, $report['summary']['judge_average']);
        $this->assertSame('judge-model', $this->llm->lastModel);
        $this->assertStringContainsString('Hexagonal architecture keeps the domain isolated', $this->llm->judgeMessages[1]['content']);
    }
}

final class EvalEmbedding implements EmbeddingService
{
    /** @var list<string> */
    private array $keywords = ['hexagonal', 'architecture', 'domain', 'isolated', 'frameworks'];

    public function embed(string $text): array
    {
        $lower = strtolower($text);
        $vector = [];
        foreach ($this->keywords as $word) {
            $vector[] = (float) substr_count($lower, $word);
        }

        if (array_sum($vector) === 0.0) {
            $vector[count($vector) - 1] = 1.0;
        }

        return $vector;
    }

    public function embedMany(array $texts): array
    {
        return array_map(fn (string $text) => $this->embed($text), $texts);
    }

    public function dimensions(): int
    {
        return count($this->keywords);
    }
}

final class EvalLlm implements LlmClient
{
    public int $calls = 0;

    public ?string $lastModel = null;

    /** @var list<array{role: string, content: string}> */
    public array $judgeMessages = [];

    public function complete(array $messages, ?int $maxTokens = null, ?string $model = null, ?float $temperature = null): array
    {
        $this->calls++;
        $this->lastModel = $model;

        if (str_contains((string) $messages[0]['content'], 'evaluator')) {
            $this->judgeMessages = $messages;

            return ['content' => '2', 'tokens_in' => 1, 'tokens_out' => 1, 'model' => $model ?? 'eval'];
        }

        return ['content' => 'Hexagonal architecture keeps the domain isolated.', 'tokens_in' => 1, 'tokens_out' => 1, 'model' => $model ?? 'eval'];
    }

    public function stream(array $messages, callable $onDelta, ?int $maxTokens = null, ?string $model = null): array
    {
        $result = $this->complete($messages, $maxTokens, $model);
        $onDelta($result['content']);

        return $result;
    }
}
