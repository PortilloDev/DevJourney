<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiUsage;
use App\Models\Post;
use App\Models\User;
use App\Services\Embedding\EmbeddingService;
use App\Services\Llm\LlmClient;
use App\Services\Rag\RagService;
use App\Services\Vector\InMemoryVectorStore;
use App\Services\Vector\VectorStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Covers the per-post chat spec (R1–R8).
 */
class PostChatTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryVectorStore $store;

    private BagEmbedding $embedding;

    private SpyLlm $llm;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'rag.qdrant.enabled' => false,
            'rag.retrieval.min_similarity' => 0.35,
            'rag.post_chat.max_answer_tokens' => 120,
            'rag.post_chat.related_threshold' => 0.5,
            'rag.post_chat.unrelated_threshold' => 0.15,
        ]);

        $this->store = new InMemoryVectorStore;
        $this->embedding = new BagEmbedding;
        $this->llm = new SpyLlm;

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

    private function postB(): Post
    {
        return Post::factory()->create([
            'slug' => 'kubernetes',
            'title' => 'Kubernetes scaling',
            'body_md' => '## Scaling'.PHP_EOL.'Kubernetes autoscaling with the horizontal pod autoscaler.',
        ]);
    }

    // R1
    public function test_post_page_renders_the_chat_widget(): void
    {
        config(['rag.api_key' => 'test']);
        $post = $this->postA();

        $this->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee("chatWidget('hexagonal')", false);
    }

    // R2
    public function test_home_does_not_render_the_chat_widget(): void
    {
        config(['rag.api_key' => 'test']);

        $this->get('/')->assertOk()->assertDontSee('chatWidget', false);
    }

    // R3 + R4 + R5 + R8-E1: unrelated question -> predefined answer, no external calls
    public function test_unrelated_question_is_refused_without_calling_embedding_or_llm(): void
    {
        $post = $this->postA();
        app(RagService::class)->indexContent($post);
        $this->embedding->calls = 0;

        $result = app(RagService::class)->askForPost($post, 'How does kubernetes autoscaling work?', '127.0.0.1');

        $this->assertTrue($result['refused']);
        $this->assertSame(RagService::UNRELATED_MESSAGE, $result['answer']);
        $this->assertSame(0, $this->llm->calls);
        $this->assertSame(0, $this->embedding->calls);
        $this->assertDatabaseCount('ai_usage', 1);
    }

    // R3 + R6: related question -> concise answer, sources only from that post
    public function test_related_question_is_answered_concisely_from_that_post_only(): void
    {
        $postA = $this->postA();
        $postB = $this->postB();
        app(RagService::class)->indexContent($postA);
        app(RagService::class)->indexContent($postB);

        $result = app(RagService::class)->askForPost($postA, 'How does hexagonal architecture isolate the domain?', '127.0.0.1');

        $this->assertFalse($result['refused']);
        $this->assertSame('Concise answer.', $result['answer']);
        $this->assertSame(1, $this->llm->calls);

        $system = $this->llm->lastMessages[0]['content'];
        $this->assertStringContainsStringIgnoringCase('concise', $system);
        $this->assertSame(120, $this->llm->lastMaxTokens);

        $this->assertNotEmpty($result['sources']);
        foreach ($result['sources'] as $source) {
            $this->assertSame('Hexagonal architecture', $source['title']);
        }
    }

    // R8-E2: ambiguous question -> embeddings fallback
    public function test_ambiguous_question_falls_back_to_embeddings(): void
    {
        $post = $this->postA();
        app(RagService::class)->indexContent($post);
        $this->embedding->calls = 0;

        app(RagService::class)->askForPost($post, 'why hexagonal patterns matter distributed systems', '127.0.0.1');

        $this->assertGreaterThanOrEqual(1, $this->embedding->calls);
    }

    // R3 + A2: a question sharing a title term is considered related (incl. other language)
    public function test_question_sharing_a_title_term_is_answered(): void
    {
        $post = $this->postA();
        app(RagService::class)->indexContent($post);
        $this->embedding->calls = 0;

        $result = app(RagService::class)->askForPost($post, 'Como aplico hexagonal en python, por donde empiezo', '127.0.0.1');

        $this->assertFalse($result['refused']);
        $this->assertSame(1, $this->llm->calls);
        $this->assertGreaterThanOrEqual(1, $this->embedding->calls);
    }

    // R7: publishing indexes the post; drafts are not indexed
    public function test_publishing_a_post_indexes_it_and_drafts_are_not(): void
    {
        config(['rag.api_key' => 'test', 'rag.embeddings.api_key' => 'test']);

        $published = Post::factory()->create([
            'slug' => 'published-indexed',
            'title' => 'Hexagonal architecture',
            'body_md' => '## Intro'.PHP_EOL.'Hexagonal architecture keeps the domain isolated.',
        ]);

        $draft = Post::factory()->draft()->create(['slug' => 'draft-not-indexed']);

        $this->assertNotEmpty($this->searchForPost($published->id));
        $this->assertEmpty($this->searchForPost($draft->id));
    }

    // R1: indexed sources store a relative path (resolves against current origin)
    public function test_indexed_content_stores_a_relative_source_url(): void
    {
        $post = $this->postA();
        app(RagService::class)->indexContent($post);

        $hits = $this->searchForPost($post->id);

        $this->assertNotEmpty($hits);
        $url = $hits[0]['payload']['url'];
        $this->assertIsString($url);
        $this->assertStringStartsWith('/', $url);
        $this->assertStringNotContainsString('http', $url);
    }

    // R2 + R3: the widget renders the conversation history loop and copy controls
    public function test_widget_renders_history_and_copy_controls(): void
    {
        config(['rag.api_key' => 'test']);
        $post = $this->postA();

        $this->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee('x-for="(message, i) in messages"', false)
            ->assertSee('copy(message, i)', false);
    }

    // Endpoint: requires a known, published post
    public function test_chat_endpoint_rejects_missing_or_unknown_post(): void
    {
        $this->postJson('/api/chat', ['question' => 'hello'])->assertStatus(404);
        $this->postJson('/api/chat', ['question' => 'hello', 'post' => 'nope'])->assertStatus(404);
    }

    // Endpoint: scoped answer over SSE
    public function test_chat_endpoint_streams_a_scoped_answer(): void
    {
        $post = $this->postA();
        app(RagService::class)->indexContent($post);

        $response = $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'hexagonal',
            'stream' => true,
        ]);

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('"delta":"Concise answer."', $content);
        $this->assertStringContainsString('data: [DONE]', $content);
    }

    // R1 + R2 + R6: every turn is logged with full detail, grouped by the
    // conversation the server issues on the first turn.
    public function test_conversation_turns_are_logged_and_grouped(): void
    {
        $post = $this->postA();
        app(RagService::class)->indexContent($post);

        $first = $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'hexagonal',
        ])->assertOk();

        $token = $first->json('conversation_token');
        $this->assertIsString($token);

        $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'hexagonal',
            'conversation_token' => $token,
        ])->assertOk();

        $conversation = AiConversation::query()->first();
        $this->assertNotNull($conversation);
        $this->assertSame(2, $conversation->turns_count);
        $this->assertSame(2, $conversation->turns()->count());

        $turn = $conversation->turns()->first();
        $this->assertSame($post->getKey(), $turn->post_id);
        $this->assertFalse($turn->refused);
        $this->assertNotNull($turn->prompt);
        $this->assertNotNull($turn->answer);
        $this->assertNotEmpty($turn->retrievedSources());
        $this->assertNotNull($turn->provider);
        $this->assertNotNull($turn->embedding_model);
    }

    // R5
    public function test_prune_command_deletes_old_conversations(): void
    {
        AiConversation::query()->create(['session_id' => 'old', 'ip_address' => '1.1.1.1', 'started_at' => now()->subDays(40), 'last_activity_at' => now()->subDays(40)]);
        AiConversation::query()->create(['session_id' => 'new', 'ip_address' => '1.1.1.1', 'started_at' => now(), 'last_activity_at' => now()]);

        $this->artisan('ai:prune-conversations')->assertExitCode(0);

        $this->assertDatabaseMissing('ai_conversations', ['session_id' => 'old']);
        $this->assertDatabaseHas('ai_conversations', ['session_id' => 'new']);
    }

    // R4
    public function test_manual_deletion_by_range_and_all(): void
    {
        AiConversation::query()->create(['session_id' => 'a', 'ip_address' => '1.1.1.1', 'last_activity_at' => now()->subDays(10)]);
        AiConversation::query()->create(['session_id' => 'b', 'ip_address' => '1.1.1.1', 'last_activity_at' => now()->subDays(2)]);
        $recent = AiConversation::query()->create(['session_id' => 'c', 'ip_address' => '1.1.1.1', 'last_activity_at' => now()]);
        AiUsage::query()->create(['conversation_id' => $recent->id, 'ip_address' => '1.1.1.1', 'question' => 'q', 'model' => 'm']);

        $deleted = AiConversation::deleteBetween(now()->subDays(5)->toDateString(), now()->toDateString());

        $this->assertSame(2, $deleted);
        $this->assertDatabaseHas('ai_conversations', ['session_id' => 'a']);
        $this->assertDatabaseMissing('ai_conversations', ['session_id' => 'b']);
        $this->assertDatabaseMissing('ai_conversations', ['session_id' => 'c']);

        AiConversation::deleteAllLogs();

        $this->assertDatabaseCount('ai_conversations', 0);
        $this->assertDatabaseCount('ai_usage', 0);
    }

    // R3
    public function test_conversation_viewer_renders(): void
    {
        $email = 'admin@devjourney.test';
        config(['app.filament_admin_email' => $email]);
        $admin = User::factory()->create(['email' => $email]);

        $conversation = AiConversation::query()->create([
            'session_id' => 'viewer-sess',
            'ip_address' => '1.1.1.1',
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        AiUsage::query()->create([
            'conversation_id' => $conversation->id,
            'ip_address' => '1.1.1.1',
            'question' => 'What is hexagonal?',
            'prompt' => '[{"role":"system","content":"..."}]',
            'answer' => 'It isolates the domain.',
            'model' => 'm',
            'refused' => false,
            'sources' => json_encode([['title' => 'Hex', 'url' => '/journal/hex', 'heading' => 'Intro', 'score' => 0.9]]),
        ]);

        $this->actingAs($admin)->get('/admin/ai-conversations')->assertOk();
        $this->actingAs($admin)->get('/admin/ai-conversations/'.$conversation->id)->assertOk();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchForPost(int $postId): array
    {
        return $this->store->search(
            [1.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0],
            10,
            ['must' => [['key' => 'source_id', 'value' => 'post:'.$postId]]],
        );
    }
}

final class BagEmbedding implements EmbeddingService
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
            $vector[count($vector) - 1] = 1.0; // never return a zero vector
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

final class SpyLlm implements LlmClient
{
    public int $calls = 0;

    /** @var list<array{role: string, content: string}> */
    public array $lastMessages = [];

    public ?int $lastMaxTokens = null;

    public function complete(array $messages, ?int $maxTokens = null, ?string $model = null, ?float $temperature = null): array
    {
        $this->calls++;
        $this->lastMessages = $messages;
        $this->lastMaxTokens = $maxTokens;

        return ['content' => 'Concise answer.', 'tokens_in' => 12, 'tokens_out' => 4, 'model' => 'spy'];
    }

    public function stream(array $messages, callable $onDelta, ?int $maxTokens = null, ?string $model = null): array
    {
        $this->calls++;
        $this->lastMessages = $messages;
        $this->lastMaxTokens = $maxTokens;
        $onDelta('Concise answer.');

        return ['content' => 'Concise answer.', 'tokens_in' => 12, 'tokens_out' => 4, 'model' => 'spy'];
    }
}
