<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use App\Services\Embedding\EmbeddingService;
use App\Services\Llm\LlmClient;
use App\Services\SiteSettingService;
use App\Services\Vector\InMemoryVectorStore;
use App\Services\Vector\VectorStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChatConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['rag.qdrant.enabled' => false]);

        $this->app->instance(VectorStore::class, new InMemoryVectorStore);
        $this->app->instance(EmbeddingService::class, new ConfigTestEmbedding);
        $this->app->instance(LlmClient::class, new ConfigTestLlm);
    }

    private function setChat(bool $blocked, bool $hidden): void
    {
        $settings = app(SiteSettingService::class);
        $settings->set('chat_api_blocked', $blocked, 'chat', 'boolean');
        $settings->set('chat_hidden', $hidden, 'chat', 'boolean');
    }

    private function publishedPost(): Post
    {
        return Post::factory()->create([
            'slug' => 'cfg-post',
            'title' => 'Cfg',
            'body_md' => '## Intro'.PHP_EOL.'Content about configuration.',
        ]);
    }

    // R1
    public function test_the_two_boolean_settings_are_editable(): void
    {
        $this->setChat(true, true);

        $settings = app(SiteSettingService::class);
        $this->assertTrue($settings->chatApiBlocked());
        $this->assertTrue($settings->chatHidden());

        $this->setChat(false, false);
        $this->assertFalse($settings->chatApiBlocked());
        $this->assertFalse($settings->chatHidden());

        $this->assertDatabaseHas('site_settings', [
            'key' => 'chat_api_blocked',
            'group' => 'chat',
            'type' => 'boolean',
        ]);
    }

    // R2
    public function test_blocking_the_api_returns_403(): void
    {
        config(['rag.api_key' => 'test']);
        $this->setChat(true, false);

        $this->postJson('/api/chat', ['question' => 'hello', 'post' => 'cfg-post'])
            ->assertStatus(403)
            ->assertJson(['error' => 'chat_blocked']);
    }

    // R3
    public function test_blocking_hides_the_widget(): void
    {
        config(['rag.api_key' => 'test']);
        $post = $this->publishedPost();
        $this->setChat(true, false);

        $this->get(route('posts.show', $post))->assertOk()->assertDontSee('chatWidget', false);
    }

    // R4
    public function test_hiding_hides_the_widget_but_keeps_the_api_working(): void
    {
        config(['rag.api_key' => 'test']);
        $post = $this->publishedPost();
        $this->setChat(false, true);

        $this->get(route('posts.show', $post))->assertOk()->assertDontSee('chatWidget', false);

        // Not blocked: the API still reaches the service.
        $this->postJson('/api/chat', ['question' => 'How does configuration work?', 'post' => 'cfg-post'])
            ->assertOk();
    }
}

final class ConfigTestEmbedding implements EmbeddingService
{
    public function embed(string $text): array
    {
        return [1.0, 0.0, 0.0];
    }

    public function embedMany(array $texts): array
    {
        return array_map(fn (): array => [1.0, 0.0, 0.0], $texts);
    }

    public function dimensions(): int
    {
        return 3;
    }
}

final class ConfigTestLlm implements LlmClient
{
    public function complete(array $messages, ?int $maxTokens = null, ?string $model = null, ?float $temperature = null): array
    {
        return ['content' => 'ok', 'tokens_in' => 1, 'tokens_out' => 1, 'model' => 'test'];
    }

    public function stream(array $messages, callable $onDelta, ?int $maxTokens = null, ?string $model = null): array
    {
        $onDelta('ok');

        return ['content' => 'ok', 'tokens_in' => 1, 'tokens_out' => 1, 'model' => 'test'];
    }
}
