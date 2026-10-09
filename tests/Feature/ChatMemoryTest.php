<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiUsage;
use App\Models\Post;
use App\Services\Embedding\EmbeddingService;
use App\Services\Llm\LlmClient;
use App\Services\Rag\RagService;
use App\Services\Vector\InMemoryVectorStore;
use App\Services\Vector\VectorStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Covers the conversational memory spec (R1–R6).
 */
class ChatMemoryTest extends TestCase
{
    use RefreshDatabase;

    private InMemoryVectorStore $store;

    private MemoryEmbedding $embedding;

    private MemoryLlm $llm;

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
            'rag.rewrite.model' => 'rewrite-model',
            'rag.rewrite.max_tokens' => 60,
            'rag.conversation.max_turns' => 3,
        ]);

        $this->store = new InMemoryVectorStore;
        $this->embedding = new MemoryEmbedding;
        $this->llm = new MemoryLlm;

        $this->app->instance(VectorStore::class, $this->store);
        $this->app->instance(EmbeddingService::class, $this->embedding);
        $this->app->instance(LlmClient::class, $this->llm);
    }

    private function article(): Post
    {
        return Post::factory()->create([
            'slug' => 'ports-adapters',
            'title' => 'Hexagonal architecture',
            'excerpt' => 'Ports and adapters',
            'body_md' => '## Intro'.PHP_EOL.'Hexagonal architecture keeps the domain isolated. Ports and adapters connect it to the outside.',
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $turns
     */
    private function conversationWithTurns(Post $post, array $turns): AiConversation
    {
        $conversation = AiConversation::query()->create([
            'session_id' => (string) Str::uuid(),
            'ip_address' => '127.0.0.1',
            'post_id' => $post->getKey(),
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        foreach ($turns as [$question, $answer]) {
            $this->travel(1)->seconds();

            AiUsage::query()->create([
                'conversation_id' => $conversation->id,
                'post_id' => $post->getKey(),
                'ip_address' => '127.0.0.1',
                'question' => $question,
                'answer' => $answer,
                'model' => 'test',
            ]);
        }

        return $conversation;
    }

    // R1-E1
    public function test_follow_up_is_rewritten_into_a_standalone_question(): void
    {
        $post = $this->article();
        app(RagService::class)->indexContent($post);
        $conversation = $this->conversationWithTurns($post, [
            ['How do ports and adapters work?', 'They isolate the domain from the outside.'],
        ]);

        // On its own, the follow-up is refused locally.
        $alone = app(RagService::class)->askForPost($post, '¿y eso cómo se prueba?', '127.0.0.1');
        $this->assertTrue($alone['refused']);

        $result = app(RagService::class)->askForPost($post, '¿y eso cómo se prueba?', '127.0.0.1', null, $conversation->id);

        $this->assertFalse($result['refused']);
        $this->assertSame('Concise answer.', $result['answer']);
        $this->assertSame(1, $this->llm->rewriteCalls);
        $this->assertSame(2, $this->llm->calls);

        $turn = AiUsage::query()->latest('id')->first();
        $this->assertSame('¿y eso cómo se prueba?', $turn->question);
        $this->assertSame(MemoryLlm::REWRITTEN, $turn->rewritten_question);
    }

    // R2-E1
    public function test_first_question_is_not_rewritten(): void
    {
        $post = $this->article();
        app(RagService::class)->indexContent($post);

        $result = app(RagService::class)->askForPost($post, 'How does hexagonal architecture isolate the domain?', '127.0.0.1');

        $this->assertFalse($result['refused']);
        $this->assertSame(0, $this->llm->rewriteCalls);
        $this->assertSame(1, $this->llm->calls);
        $this->assertNull(AiUsage::query()->latest('id')->first()->rewritten_question);
    }

    // R3-E1
    public function test_history_is_capped_at_the_configured_maximum(): void
    {
        config(['rag.conversation.max_turns' => 2]);

        $post = $this->article();
        app(RagService::class)->indexContent($post);
        $conversation = $this->conversationWithTurns($post, [
            ['first question alpha', 'first answer'],
            ['second question beta', 'second answer'],
            ['third question gamma', 'third answer'],
            ['fourth question delta', 'fourth answer'],
        ]);

        app(RagService::class)->askForPost($post, 'How does hexagonal architecture isolate the domain?', '127.0.0.1', null, $conversation->id);

        $contents = array_column($this->llm->lastMessages, 'content');

        $this->assertContains('third question gamma', $contents);
        $this->assertContains('fourth question delta', $contents);
        $this->assertNotContains('first question alpha', $contents);
        $this->assertNotContains('second question beta', $contents);
        $this->assertCount(6, $this->llm->lastMessages);
    }

    // R4-E1
    public function test_rewrite_failure_degrades_to_the_original_question(): void
    {
        $post = $this->article();
        app(RagService::class)->indexContent($post);
        $conversation = $this->conversationWithTurns($post, [
            ['How do ports and adapters work?', 'They isolate the domain.'],
        ]);

        $this->llm->failRewrite = true;

        $result = app(RagService::class)->askForPost($post, 'How does hexagonal architecture isolate the domain?', '127.0.0.1', null, $conversation->id);

        $this->assertFalse($result['refused']);
        $this->assertSame('Concise answer.', $result['answer']);
        $this->assertSame(1, $this->llm->rewriteCalls);
        $this->assertNull(AiUsage::query()->latest('id')->first()->rewritten_question);
    }

    // R5-E1
    public function test_thread_survives_a_reload_via_the_issued_credential(): void
    {
        $post = $this->article();
        app(RagService::class)->indexContent($post);

        $first = $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'ports-adapters',
        ])->assertOk();

        $token = $first->json('conversation_token');
        $this->assertIsString($token);

        // "Reload": a fresh client sends back the credential it had persisted.
        $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'ports-adapters',
            'conversation_token' => $token,
        ])->assertOk();

        $this->assertSame(1, AiConversation::query()->count());
        $this->assertSame(2, AiConversation::query()->first()->turns()->count());
    }

    // R6-E1
    public function test_foreign_conversation_identifier_without_credential_opens_a_new_conversation(): void
    {
        $post = $this->article();
        app(RagService::class)->indexContent($post);

        $victim = AiConversation::query()->create([
            'session_id' => 'victim-conversation-id',
            'ip_address' => '10.0.0.9',
            'post_id' => $post->getKey(),
            'started_at' => now(),
            'last_activity_at' => now(),
        ]);

        // A bare identifier (no credential) and a forged credential must both be
        // treated as a new conversation, leaving the foreign one untouched.
        $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'ports-adapters',
            'conversation' => 'victim-conversation-id',
        ])->assertOk();

        $this->postJson('/api/chat', [
            'question' => 'How does hexagonal architecture isolate the domain?',
            'post' => 'ports-adapters',
            'conversation_token' => 'victim-conversation-id.deadbeef',
        ])->assertOk();

        $this->assertSame(0, $victim->turns()->count());
        $this->assertSame(2, AiConversation::query()->where('id', '!=', $victim->id)->count());
    }
}

final class MemoryEmbedding implements EmbeddingService
{
    /** @var list<string> */
    private array $keywords = ['hexagonal', 'architecture', 'domain', 'isolated', 'ports', 'adapters', 'test'];

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

final class MemoryLlm implements LlmClient
{
    public const REWRITTEN = 'How do you test ports and adapters?';

    public int $calls = 0;

    public int $rewriteCalls = 0;

    /** @var list<array{role: string, content: string}> */
    public array $lastMessages = [];

    public ?int $lastMaxTokens = null;

    public ?string $lastModel = null;

    public bool $failRewrite = false;

    public function complete(array $messages, ?int $maxTokens = null, ?string $model = null, ?float $temperature = null): array
    {
        $this->calls++;
        $this->lastMessages = $messages;
        $this->lastMaxTokens = $maxTokens;
        $this->lastModel = $model;

        if ($model !== null) {
            $this->rewriteCalls++;

            if ($this->failRewrite) {
                throw new RuntimeException('rewrite provider down');
            }

            return ['content' => self::REWRITTEN, 'tokens_in' => 7, 'tokens_out' => 3, 'model' => $model];
        }

        return ['content' => 'Concise answer.', 'tokens_in' => 12, 'tokens_out' => 4, 'model' => 'memory-llm'];
    }

    public function stream(array $messages, callable $onDelta, ?int $maxTokens = null, ?string $model = null): array
    {
        $this->calls++;
        $this->lastMessages = $messages;
        $this->lastMaxTokens = $maxTokens;
        $this->lastModel = $model;

        $onDelta('Concise answer.');

        return ['content' => 'Concise answer.', 'tokens_in' => 12, 'tokens_out' => 4, 'model' => 'memory-llm'];
    }
}
