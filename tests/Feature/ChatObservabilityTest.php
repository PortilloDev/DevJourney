<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Widgets\AiUsageOverviewWidget;
use App\Models\AiConversation;
use App\Models\AiUsage;
use App\Models\Post;
use App\Services\Embedding\EmbeddingService;
use App\Services\Llm\LlmClient;
use App\Services\Rag\ConversationToken;
use App\Services\Rag\RagService;
use App\Services\Vector\InMemoryVectorStore;
use App\Services\Vector\VectorStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Covers the chat observability / quality spec (R1–R5).
 */
class ChatObservabilityTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryVectorStore $store;

    private ObsEmbedding $embedding;

    private ObsLlm $llm;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config(['rag.qdrant.enabled' => false]);

        $this->store = new InMemoryVectorStore;
        $this->embedding = new ObsEmbedding;
        $this->llm = new ObsLlm;

        $this->app->instance(VectorStore::class, $this->store);
        $this->app->instance(EmbeddingService::class, $this->embedding);
        $this->app->instance(LlmClient::class, $this->llm);
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

    // R1 + R4: the turn can store a rating and an estimated cost.
    public function test_ai_usage_stores_rating_and_cost_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('ai_usage', 'rating'), 'rating column (R1)');
        $this->assertTrue(Schema::hasColumn('ai_usage', 'cost_eur'), 'cost_eur column (R4)');

        $usage = AiUsage::query()->create([
            'ip_address' => '127.0.0.1',
            'question' => 'q',
            'model' => 'm',
            'rating' => -1,
            'cost_eur' => 0.000123,
        ]);

        $fresh = $usage->fresh();
        $this->assertSame(-1, $fresh->rating);
        $this->assertSame(0.000123, $fresh->cost_eur);
    }

    // R3: an answered turn exposes the identifier of the row it recorded.
    public function test_answered_turn_exposes_its_usage_id(): void
    {
        $post = $this->postA();
        app(RagService::class)->indexContent($post);

        $result = app(RagService::class)->askForPost($post, 'How does hexagonal architecture isolate the domain?', '127.0.0.1');

        $this->assertArrayHasKey('usage_id', $result);
        $this->assertNotNull($result['usage_id']);
        $this->assertDatabaseHas('ai_usage', ['id' => $result['usage_id'], 'post_id' => $post->getKey()]);
    }

    // R3: a refused turn exposes the identifier of the row it recorded.
    public function test_refused_turn_exposes_its_usage_id(): void
    {
        $post = $this->postA();
        app(RagService::class)->indexContent($post);

        $result = app(RagService::class)->askForPost($post, 'How does kubernetes autoscaling work?', '127.0.0.1');

        $this->assertTrue($result['refused']);
        $this->assertNotNull($result['usage_id']);
        $this->assertDatabaseHas('ai_usage', ['id' => $result['usage_id'], 'refused' => true]);
    }

    // R3-E1: two turns of the same visitor in the same second keep their own latency.
    public function test_latency_is_written_to_the_turn_that_produced_it(): void
    {
        $post = $this->postA();
        app(RagService::class)->indexContent($post);

        $previous = AiUsage::query()->create([
            'ip_address' => '127.0.0.1',
            'question' => 'previous turn',
            'model' => 'm',
            'latency_ms' => 999999,
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'hexagonal',
        ])->assertOk();

        $this->assertSame(999999, $previous->fresh()->latency_ms);

        $usageId = $response->json('usage_id');
        $this->assertNotNull($usageId);
        $this->assertNotSame($previous->getKey(), $usageId);
        $this->assertLessThan(999999, AiUsage::query()->findOrFail($usageId)->latency_ms);
    }

    // R1: the streamed response tells the client which turn it may rate.
    public function test_stream_final_event_carries_the_usage_id(): void
    {
        $post = $this->postA();
        app(RagService::class)->indexContent($post);

        $response = $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'hexagonal',
            'stream' => true,
        ])->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('"done":true', $content);
        $this->assertMatchesRegularExpression('/"usage_id":\d+/', $content);
    }

    // R4-E1: the turn stores the estimated cost of its tokens at the configured price.
    public function test_turn_cost_is_estimated_from_tokens_and_pricing(): void
    {
        config([
            'rag.chat_model' => 'cost-model',
            'rag.pricing' => ['cost-model' => ['input' => 1.0, 'output' => 2.0]],
        ]);

        $post = $this->postA();
        app(RagService::class)->indexContent($post);

        $result = app(RagService::class)->askForPost($post, 'How does hexagonal architecture isolate the domain?', '127.0.0.1');

        $usage = AiUsage::query()->findOrFail($result['usage_id']);

        $this->assertSame(12, $usage->tokens_in);
        $this->assertSame(4, $usage->tokens_out);
        $this->assertSame(0.00002, $usage->cost_eur);
    }

    // R4-E2: a model without configured prices records no cost, nothing else changes.
    public function test_turn_without_configured_pricing_records_no_cost(): void
    {
        config([
            'rag.chat_model' => 'unpriced-model',
            'rag.pricing' => [],
        ]);

        $post = $this->postA();
        app(RagService::class)->indexContent($post);

        $result = app(RagService::class)->askForPost($post, 'How does hexagonal architecture isolate the domain?', '127.0.0.1');

        $usage = AiUsage::query()->findOrFail($result['usage_id']);

        $this->assertNull($usage->cost_eur);
        $this->assertFalse($usage->refused);
        $this->assertSame(12, $usage->tokens_in);
    }

    // R1-E1: a delivered turn is rated from the client.
    public function test_visitor_rates_a_delivered_turn(): void
    {
        [$conversation, $usage] = $this->conversationWithTurn();

        $this->postJson('/api/chat/feedback', [
            'conversation' => app(ConversationToken::class)->issue($conversation->session_id),
            'usage_id' => $usage->getKey(),
            'rating' => -1,
        ])->assertOk();

        $this->assertSame(-1, $usage->fresh()->rating);
    }

    // R1-E2: the turn keeps a single rating, the last one.
    public function test_rating_is_overwritten_with_the_last_value(): void
    {
        [$conversation, $usage] = $this->conversationWithTurn();
        $token = app(ConversationToken::class)->issue($conversation->session_id);

        $this->postJson('/api/chat/feedback', ['conversation' => $token, 'usage_id' => $usage->getKey(), 'rating' => 1])->assertOk();
        $this->postJson('/api/chat/feedback', ['conversation' => $token, 'usage_id' => $usage->getKey(), 'rating' => -1])->assertOk();

        $this->assertSame(-1, $usage->fresh()->rating);
        $this->assertDatabaseCount('ai_usage', 1);
    }

    // R2-E1: a turn from another conversation cannot be rated.
    public function test_visitor_cannot_rate_a_turn_from_another_conversation(): void
    {
        [$conversationA] = $this->conversationWithTurn();
        [, $foreign] = $this->conversationWithTurn('other-session');

        $this->postJson('/api/chat/feedback', [
            'conversation' => app(ConversationToken::class)->issue($conversationA->session_id),
            'usage_id' => $foreign->getKey(),
            'rating' => 1,
        ])->assertStatus(404);

        $this->assertNull($foreign->fresh()->rating);
    }

    // R1: the widget exposes the rating controls for an answered turn.
    public function test_widget_renders_the_rating_controls(): void
    {
        config(['rag.api_key' => 'test']);

        $this->get(route('posts.show', $this->postA()))
            ->assertOk()
            ->assertSee('rate(message, 1)', false)
            ->assertSee('rate(message, -1)', false);
    }

    // R5-E1: the panel shows today's quality and cost signals.
    public function test_panel_metrics_cover_today_signals(): void
    {
        $this->recordTurn(['refused' => true, 'refused_stage' => 'local']);
        $this->recordTurn(['refused' => true, 'refused_stage' => 'embedding']);
        $this->recordTurn(['refused' => false, 'refused_stage' => 'error']);
        $this->recordTurn(['latency_ms' => 100, 'context_chunks' => 2, 'cost_eur' => 0.001, 'rating' => -1]);
        $this->recordTurn(['latency_ms' => 200, 'context_chunks' => 0]);
        $this->recordTurn(['latency_ms' => 0, 'context_chunks' => 0, 'cache_hit' => true]);
        $this->recordTurn(['latency_ms' => 300, 'context_chunks' => 3, 'rating' => 1]);
        $this->recordTurn(['latency_ms' => 999], now()->subDay());

        $metrics = AiUsageOverviewWidget::todaysMetrics();

        $this->assertSame(['local' => 1, 'embedding' => 1], $metrics['rejection_by_stage']);
        $this->assertEqualsWithDelta(1 / 7, $metrics['error_rate'], 1e-9);
        $this->assertSame(300, $metrics['latency_p95']);
        $this->assertEqualsWithDelta(0.001, $metrics['cost_total'], 1e-9);
        $this->assertEqualsWithDelta(0.5, $metrics['negative_rating_rate'], 1e-9);
        // D9 counts every non-refused, non-cached turn with no chunks: the
        // provider-error turn and the genuine zero-chunk turn (cache hit excluded).
        $this->assertSame(2, $metrics['zero_chunk_turns']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recordTurn(array $attributes = [], ?Carbon $at = null): AiUsage
    {
        $usage = new AiUsage(array_merge([
            'ip_address' => '127.0.0.1',
            'question' => 'q',
            'model' => 'm',
        ], $attributes));
        $usage->created_at = $at ?? now();
        $usage->save();

        return $usage;
    }

    /**
     * @return array{0: AiConversation, 1: AiUsage}
     */
    private function conversationWithTurn(string $sessionId = 'obs-session'): array
    {
        $conversation = AiConversation::query()->create([
            'session_id' => $sessionId,
            'ip_address' => '127.0.0.1',
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        $usage = AiUsage::query()->create([
            'conversation_id' => $conversation->id,
            'ip_address' => '127.0.0.1',
            'question' => 'q',
            'model' => 'm',
            'created_at' => now(),
        ]);

        return [$conversation, $usage];
    }
}

final class ObsEmbedding implements EmbeddingService
{
    public int $calls = 0;

    /** @var list<string> */
    private array $keywords = ['hexagonal', 'domain', 'architecture', 'kubernetes', 'autoscal', 'english', 'writing'];

    public function embed(string $text): array
    {
        $this->calls++;

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

final class ObsLlm implements LlmClient
{
    public int $calls = 0;

    /** @var list<array{role: string, content: string}> */
    public array $lastMessages = [];

    public function complete(array $messages, ?int $maxTokens = null, ?string $model = null, ?float $temperature = null): array
    {
        $this->calls++;
        $this->lastMessages = $messages;

        return ['content' => 'Concise answer.', 'tokens_in' => 12, 'tokens_out' => 4, 'model' => 'spy'];
    }

    public function stream(array $messages, callable $onDelta, ?int $maxTokens = null, ?string $model = null): array
    {
        $this->calls++;
        $this->lastMessages = $messages;
        $onDelta('Concise answer.');

        return ['content' => 'Concise answer.', 'tokens_in' => 12, 'tokens_out' => 4, 'model' => 'spy'];
    }
}
