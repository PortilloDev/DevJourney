<?php

declare(strict_types=1);

namespace App\Providers;

use App\Jobs\IndexContentJob;
use App\Models\Challenge;
use App\Models\Milestone;
use App\Models\Post;
use App\Models\Project;
use App\Models\SiteSetting;
use App\Services\Embedding\CachedEmbeddingService;
use App\Services\Embedding\EmbeddingService;
use App\Services\Embedding\OpenAiEmbeddingService;
use App\Services\Llm\CircuitBreaker;
use App\Services\Llm\CircuitBreakerLlmClient;
use App\Services\Llm\LlmClient;
use App\Services\Llm\OpenAiLlmClient;
use App\Services\LogService;
use App\Services\MarkdownRenderer;
use App\Services\PageCacheService;
use App\Services\Rag\ChatAnswerCache;
use App\Services\Rag\RagService;
use App\Services\SeoMetaService;
use App\Services\SiteSettingService;
use App\Services\Vector\InMemoryVectorStore;
use App\Services\Vector\QdrantVectorStore;
use App\Services\Vector\VectorStore;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Rendering markdown is stateless — share one instance.
        $this->app->singleton(MarkdownRenderer::class);
        $this->app->singleton(SiteSettingService::class);
        $this->app->singleton(LogService::class);
        $this->app->singleton(RagService::class);
        $this->app->singleton(ChatAnswerCache::class);
        $this->app->singleton(CircuitBreaker::class);

        $this->app->bind(VectorStore::class, function (): VectorStore {
            if (config('rag.qdrant.enabled', true)) {
                return new QdrantVectorStore;
            }

            // The in-memory store is empty per request: a silent degradation.
            // Reserve it for the tests and fail loudly anywhere else (R4).
            if ($this->app->environment('testing')) {
                return new InMemoryVectorStore;
            }

            throw new RuntimeException(
                'The vector store is disabled (rag.qdrant.enabled=false) outside the testing '
                .'environment. Enable Qdrant (QDRANT_ENABLED=true) or fix the configuration.',
            );
        });
        $this->app->bind(EmbeddingService::class, function (): EmbeddingService {
            // Cache question embeddings without touching the provider client.
            return new CachedEmbeddingService(new OpenAiEmbeddingService);
        });
        $this->app->bind(LlmClient::class, function (): LlmClient {
            // Trip open on consecutive provider failures without touching RagService.
            return new CircuitBreakerLlmClient(new OpenAiLlmClient, app(CircuitBreaker::class));
        });

        // SEO meta is populated per request by controllers and read by the layout.
        $this->app->scoped(SeoMetaService::class);
    }

    public function boot(): void
    {
        Paginator::useTailwind();

        // Make site settings and SEO meta available to every Blade view.
        View::share('settings', $this->app->make(SiteSettingService::class));
        View::composer('*', function ($view): void {
            $view->with('seo', $this->app->make(SeoMetaService::class));
        });

        // The app has a single canonical origin. Force it so absolute URLs
        // (route(), url(), asset()) are generated with APP_URL even when the
        // container receives a portless Host (Docker) — otherwise links and
        // assets point to http://localhost with no port and fail.
        if (filled(config('app.url'))) {
            URL::forceRootUrl((string) config('app.url'));
        }

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Invalidate the public full-page cache and keep an audit trail whenever
        // content changes.
        $log = fn (string $action) => fn ($model) => app(LogService::class)
            ->audit($action, $model instanceof Model ? $model : null);

        foreach ([
            Post::class,
            Project::class,
            Challenge::class,
            Milestone::class,
            SiteSetting::class,
        ] as $model) {
            $model::saved(app(PageCacheService::class)->bump(...));
            $model::deleted(app(PageCacheService::class)->bump(...));
            $model::saved($log('content.saved'));
            $model::deleted($log('content.deleted'));
        }

        // Editing a post invalidates its stored chat answers: the per-post
        // revision is part of every answer cache key (R3, D2).
        Post::saving(function (Post $post): void {
            if ($post->exists) {
                app(ChatAnswerCache::class)->bumpRevision((int) $post->getKey());
            }
        });

        // Content: index published posts/challenges/projects when they are
        // published, and remove their vectors when deleted. The credential check
        // lives inside IndexContentJob so the hooks can be registered always.
        foreach ([Post::class, Project::class, Challenge::class] as $model) {
            $model::saved(function (Model $m) use ($model): void {
                if ($this->isPublished($m)) {
                    dispatch(new IndexContentJob($model, $m->getKey()));
                }
            });
            $model::deleted(fn (Model $m) => app(RagService::class)->deleteContent($m));
        }

        // Security audit trail for authentication.
        $auditUser = fn (Login|Logout $event) => $event->user instanceof Model
            ? $event->user
            : null;

        Event::listen(Login::class, fn (Login $event) => app(LogService::class)
            ->audit('auth.login', $auditUser($event), ['guard' => $event->guard]));
        Event::listen(Logout::class, fn (Logout $event) => app(LogService::class)
            ->audit('auth.logout', $auditUser($event), ['guard' => $event->guard]));
    }

    private function isPublished(Model $model): bool
    {
        if (! in_array($model::class, [Post::class, Challenge::class, Project::class], true)) {
            return false;
        }

        $status = $model->status ?? null;
        $value = $status instanceof \BackedEnum ? $status->value : $status;

        return $value === 'published';
    }
}
