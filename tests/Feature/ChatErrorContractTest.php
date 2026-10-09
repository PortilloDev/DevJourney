<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiUsage;
use App\Models\Post;
use App\Services\Embedding\EmbeddingService;
use App\Services\Llm\LlmClient;
use App\Services\Llm\OpenAiLlmClient;
use App\Services\Rag\RagService;
use App\Services\Vector\InMemoryVectorStore;
use App\Services\Vector\VectorStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Covers the deterministic chat error contract spec (R1–R5).
 */
class ChatErrorContractTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryVectorStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'rag.qdrant.enabled' => false,
            'rag.embeddings.model' => 'test-embed',
            'rag.retrieval.min_similarity' => 0.35,
            'rag.post_chat.related_threshold' => 0.5,
            'rag.post_chat.unrelated_threshold' => 0.15,
            'rag.post_chat.max_answer_tokens' => 120,
        ]);

        $this->store = new InMemoryVectorStore;

        $this->app->instance(VectorStore::class, $this->store);
        $this->app->instance(EmbeddingService::class, new ContractEmbedding);
    }

    private function errorPost(): Post
    {
        return Post::factory()->create([
            'slug' => 'error-contract',
            'title' => 'Error contract',
            'body_md' => '## Intro'.PHP_EOL.'The error contract records failed turns and marks incomplete streams.',
        ]);
    }

    // R1-E1 + R4-E1
    public function test_stream_cut_after_text_marks_the_turn_incomplete_without_retry(): void
    {
        $post = $this->errorPost();
        app(RagService::class)->indexContent($post);

        $llm = new PartialStreamLlm;
        $this->app->instance(LlmClient::class, $llm);
        $this->app->forgetInstance(RagService::class);

        $response = $this->postJson('/api/chat', [
            'question' => 'How does the error contract work?',
            'post' => 'error-contract',
            'stream' => true,
        ])->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('"delta":"Partial "', $content);
        $this->assertStringContainsString('"incomplete":true', $content);
        $this->assertSame(1, $llm->calls);

        $turn = AiUsage::query()->latest('id')->first();
        $this->assertNotNull($turn);
        $this->assertSame('error', $turn->refused_stage);
    }

    // R2-E1
    public function test_failed_provider_call_is_recorded_and_reports_service_error(): void
    {
        $post = $this->errorPost();
        app(RagService::class)->indexContent($post);

        $this->app->instance(LlmClient::class, new AlwaysFailLlm);
        $this->app->forgetInstance(RagService::class);

        $question = 'How does the error contract work?';

        $this->postJson('/api/chat', ['question' => $question, 'post' => 'error-contract'])
            ->assertStatus(503)
            ->assertJson(['error' => 'service_error']);

        $turn = AiUsage::query()->latest('id')->first();
        $this->assertNotNull($turn);
        $this->assertSame('error', $turn->refused_stage);
        $this->assertSame($question, $turn->question);
        $this->assertSame($post->getKey(), $turn->post_id);
    }

    // R3-E1
    public function test_transient_error_is_retried_once_on_the_non_stream_path(): void
    {
        config([
            'rag.api_key' => 'test',
            'rag.base_url' => 'https://llm.test/v1',
            'rag.chat_model' => 'test-model',
        ]);

        $post = $this->errorPost();
        app(RagService::class)->indexContent($post);

        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            if ($attempts === 1) {
                return Http::response('server error', 500);
            }

            return Http::response([
                'choices' => [['message' => ['content' => 'Recovered answer']]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3],
                'model' => 'test-model',
            ], 200);
        });

        $this->app->instance(LlmClient::class, new OpenAiLlmClient);
        $this->app->forgetInstance(RagService::class);

        $this->postJson('/api/chat', ['question' => 'How does the error contract work?', 'post' => 'error-contract'])
            ->assertOk()
            ->assertJson(['answer' => 'Recovered answer']);

        $this->assertSame(2, $attempts);
    }

    // R4-E1
    public function test_stream_client_does_not_retry_a_failed_response(): void
    {
        config([
            'rag.api_key' => 'test',
            'rag.base_url' => 'https://llm.test/v1',
            'rag.chat_model' => 'test-model',
        ]);

        Http::fake(['https://llm.test/*' => Http::response('server error', 500)]);

        $client = new OpenAiLlmClient;

        try {
            $client->stream([['role' => 'user', 'content' => 'hi']], fn () => null);
            $this->fail('Expected the stream to fail.');
        } catch (RuntimeException) {
            // expected
        }

        Http::assertSentCount(1);
    }

    // R5-E1
    public function test_provider_timeout_aborts_with_service_unavailable(): void
    {
        config([
            'rag.api_key' => 'test',
            'rag.base_url' => 'https://llm.test/v1',
            'rag.timeouts.complete' => 30,
        ]);

        $this->assertLessThanOrEqual(30, (int) config('rag.timeouts.complete'));

        $post = $this->errorPost();
        app(RagService::class)->indexContent($post);

        Http::fake(['https://llm.test/*' => Http::failedConnection('cURL error 28: Operation timed out')]);

        $this->app->instance(LlmClient::class, new OpenAiLlmClient);
        $this->app->forgetInstance(RagService::class);

        $this->postJson('/api/chat', ['question' => 'How does the error contract work?', 'post' => 'error-contract'])
            ->assertStatus(503)
            ->assertJson(['error' => 'service_error']);
    }
}

final class ContractEmbedding implements EmbeddingService
{
    /** @var list<string> */
    private array $keywords = ['error', 'contract', 'failed', 'incomplete'];

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

final class AlwaysFailLlm implements LlmClient
{
    public function complete(array $messages, ?int $maxTokens = null, ?string $model = null, ?float $temperature = null): array
    {
        throw new RuntimeException('provider down');
    }

    public function stream(array $messages, callable $onDelta, ?int $maxTokens = null, ?string $model = null): array
    {
        throw new RuntimeException('provider down');
    }
}

final class PartialStreamLlm implements LlmClient
{
    public int $calls = 0;

    public function complete(array $messages, ?int $maxTokens = null, ?string $model = null, ?float $temperature = null): array
    {
        $this->calls++;

        throw new RuntimeException('provider down');
    }

    public function stream(array $messages, callable $onDelta, ?int $maxTokens = null, ?string $model = null): array
    {
        $this->calls++;
        $onDelta('Partial ');
        $onDelta('answer');

        throw new RuntimeException('stream cut');
    }
}
