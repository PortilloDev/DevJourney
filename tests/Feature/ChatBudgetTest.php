<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiUsage;
use App\Models\Post;
use App\Services\Embedding\EmbeddingService;
use App\Services\Llm\LlmClient;
use App\Services\Llm\OpenAiLlmClient;
use App\Services\Rag\ChatQuota;
use App\Services\Rag\IpHasher;
use App\Services\Rag\RagService;
use App\Services\Vector\InMemoryVectorStore;
use App\Services\Vector\VectorStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the global budget and quota spec (R1–R6).
 */
class ChatBudgetTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryVectorStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config(['rag.qdrant.enabled' => false]);

        $this->store = new InMemoryVectorStore;

        $this->app->instance(VectorStore::class, $this->store);
        $this->app->instance(EmbeddingService::class, new BudgetEmbedding);
        $this->app->instance(LlmClient::class, new BudgetLlm);
    }

    private function postA(): Post
    {
        return Post::factory()->create([
            'slug' => 'hexagonal',
            'title' => 'Hexagonal architecture',
            'excerpt' => 'Ports and adapters',
            'body_md' => '## Intro'.PHP_EOL.'Hexagonal architecture keeps the domain isolated from frameworks.',
        ]);
    }

    // R1: the global daily ceiling is configurable and has a default.
    public function test_global_daily_limit_is_configured(): void
    {
        $this->assertSame(500, (int) config('rag.limits.global_requests_per_day'));
    }

    // R5-E1: the per-visitor quota is reserved atomically, so a second
    // concurrent reservation for the last slot loses.
    public function test_visitor_quota_is_reserved_atomically(): void
    {
        config(['rag.limits.requests_per_day' => 1, 'rag.limits.global_requests_per_day' => 100]);

        $quota = app(ChatQuota::class);

        $this->assertTrue($quota->consume('127.0.0.1'));
        $this->assertFalse($quota->consume('127.0.0.1'));
    }

    // R1-E1: the site-wide ceiling also blocks the reservation.
    public function test_global_ceiling_blocks_reservation_and_reports_wait(): void
    {
        config(['rag.limits.requests_per_day' => 100, 'rag.limits.global_requests_per_day' => 1]);

        $quota = app(ChatQuota::class);

        $this->assertTrue($quota->consume('10.0.0.1'));
        $this->assertFalse($quota->consume('10.0.0.2'));
        $this->assertNotNull($quota->availableIn('10.0.0.2'));
    }

    // R2-E1: a request rejected by validation must not spend the daily quota.
    public function test_validation_rejection_does_not_consume_the_request_quota(): void
    {
        config(['rag.limits.requests_per_day' => 1, 'rag.limits.global_requests_per_day' => 100]);

        $post = $this->postA();
        app(RagService::class)->indexContent($post);

        $this->postJson('/api/chat', ['question' => 'hello', 'post' => 'does-not-exist'])
            ->assertStatus(404);

        $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'hexagonal',
        ])->assertOk();
    }

    // R1-E1: once the site ceiling is reached, the model is never called.
    public function test_global_ceiling_rejects_without_calling_the_model(): void
    {
        config(['rag.limits.requests_per_day' => 100, 'rag.limits.global_requests_per_day' => 1]);

        $post = $this->postA();
        app(RagService::class)->indexContent($post);

        $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'hexagonal',
        ])->assertOk();

        $llm = app(LlmClient::class);
        $before = $llm->calls;

        $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'hexagonal',
        ])
            ->assertStatus(429)
            ->assertJson(['error' => 'daily_global_limit']);

        $this->assertSame($before, $llm->calls);
    }

    // R1-E2 + R5-E1: an attended request reserves the visitor quota, so a second
    // one in the same day is rejected.
    public function test_attended_request_reserves_the_visitor_quota(): void
    {
        config(['rag.limits.requests_per_day' => 1, 'rag.limits.global_requests_per_day' => 100]);

        $post = $this->postA();
        app(RagService::class)->indexContent($post);

        $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'hexagonal',
        ])->assertOk();

        $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'hexagonal',
        ])
            ->assertStatus(429)
            ->assertJson(['error' => 'daily_request_limit']);
    }

    // R2: a turn refused by the local relevance check does not consume the quota.
    public function test_local_rejection_does_not_consume_the_visitor_quota(): void
    {
        config(['rag.limits.requests_per_day' => 1, 'rag.limits.global_requests_per_day' => 100]);

        $post = $this->postA();
        app(RagService::class)->indexContent($post);

        $this->postJson('/api/chat', [
            'question' => 'How does kubernetes autoscaling work?',
            'post' => 'hexagonal',
        ])->assertOk();

        $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'hexagonal',
        ])->assertOk();

        $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'hexagonal',
        ])->assertStatus(429);
    }

    // R4-E1: the daily token budget is a SQL aggregate, not a row load.
    public function test_token_budget_is_aggregated_in_the_database(): void
    {
        $post = $this->postA();
        app(RagService::class)->indexContent($post);

        DB::enableQueryLog();

        $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'hexagonal',
        ])->assertOk();

        $queries = collect(DB::getQueryLog())->pluck('query');

        $this->assertTrue(
            $queries->contains(fn (string $q): bool => str_contains($q, 'sum(') && str_contains($q, 'tokens_in')),
            'Expected the token budget to be computed with a SQL aggregate.',
        );
    }

    // R4-E1: the budget is the sum of the day's tokens for that visitor.
    public function test_token_budget_blocks_when_the_daily_sum_is_reached(): void
    {
        config(['rag.limits.tokens_per_day' => 10, 'rag.limits.requests_per_day' => 100]);

        $post = $this->postA();
        app(RagService::class)->indexContent($post);

        AiUsage::query()->create([
            'ip_address' => app(IpHasher::class)->hash('127.0.0.1'),
            'question' => 'earlier',
            'model' => 'm',
            'tokens_in' => 6,
            'tokens_out' => 6,
        ]);

        $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'hexagonal',
        ])
            ->assertStatus(429)
            ->assertJson(['error' => 'daily_token_limit']);
    }

    // R6-E1: a 429 for an exhausted quota carries Retry-After with the reset wait.
    public function test_daily_limit_429_includes_retry_after(): void
    {
        config(['rag.limits.requests_per_day' => 1, 'rag.limits.global_requests_per_day' => 100]);

        $post = $this->postA();
        app(RagService::class)->indexContent($post);

        $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'hexagonal',
        ])->assertOk();

        $response = $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'hexagonal',
        ])->assertStatus(429);

        $retryAfter = $response->headers->get('Retry-After');

        $this->assertNotNull($retryAfter);
        $this->assertGreaterThan(0, (int) $retryAfter);
        $this->assertLessThanOrEqual(86400, (int) $retryAfter);
    }

    // R3: a completion without provider usage still counts input and output tokens.
    public function test_completion_without_usage_estimates_tokens(): void
    {
        config(['rag.api_key' => 'test', 'rag.base_url' => 'https://llm.test/v1', 'rag.chat_model' => 'test-model']);

        Http::fake(['https://llm.test/*' => Http::response([
            'choices' => [['message' => ['content' => 'Hello world, this is a concise answer.']]],
            'model' => 'test-model',
        ], 200)]);

        $result = (new OpenAiLlmClient)->complete([
            ['role' => 'user', 'content' => 'Tell me about hexagonal architecture and its ports.'],
        ]);

        $this->assertGreaterThan(0, $result['tokens_in']);
        $this->assertGreaterThan(0, $result['tokens_out']);
    }

    // R3-E1: a stream without provider usage records tokens > 0 on the turn and
    // therefore increases the visitor's daily budget.
    public function test_stream_without_usage_records_tokens_on_the_turn(): void
    {
        config(['rag.api_key' => 'test', 'rag.base_url' => 'https://llm.test/v1', 'rag.chat_model' => 'test-model']);

        $post = $this->postA();
        app(RagService::class)->indexContent($post);

        $this->fakeStreamWithoutUsage();

        $this->app->instance(LlmClient::class, new OpenAiLlmClient);
        $this->app->forgetInstance(RagService::class);

        $response = $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'hexagonal',
            'stream' => true,
        ])->assertOk();

        $response->streamedContent();

        $turn = AiUsage::query()->latest('id')->first();

        $this->assertNotNull($turn);
        $this->assertGreaterThan(0, $turn->tokens_in);
        $this->assertGreaterThan(0, $turn->tokens_out);
    }

    private function fakeStreamWithoutUsage(): void
    {
        $frames = [
            'data: '.json_encode(['choices' => [['delta' => ['content' => 'Concise ']]]])."\n\n",
            'data: '.json_encode(['choices' => [['delta' => ['content' => 'answer.']]]])."\n\n",
            "data: [DONE]\n\n",
        ];

        Http::fake([
            'https://llm.test/*' => Http::response(implode('', $frames), 200, ['Content-Type' => 'text/event-stream']),
        ]);
    }
}

final class BudgetEmbedding implements EmbeddingService
{
    /** @var list<string> */
    private array $keywords = ['hexagonal', 'domain', 'architecture', 'kubernetes', 'autoscal'];

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

final class BudgetLlm implements LlmClient
{
    public int $calls = 0;

    public function complete(array $messages, ?int $maxTokens = null, ?string $model = null, ?float $temperature = null): array
    {
        $this->calls++;

        return ['content' => 'Concise answer.', 'tokens_in' => 12, 'tokens_out' => 4, 'model' => 'spy'];
    }

    public function stream(array $messages, callable $onDelta, ?int $maxTokens = null, ?string $model = null): array
    {
        $this->calls++;
        $onDelta('Concise answer.');

        return ['content' => 'Concise answer.', 'tokens_in' => 12, 'tokens_out' => 4, 'model' => 'spy'];
    }
}
