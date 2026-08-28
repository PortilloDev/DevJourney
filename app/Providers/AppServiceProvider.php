<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Challenge;
use App\Models\Milestone;
use App\Models\Post;
use App\Models\Project;
use App\Models\SiteSetting;
use App\Services\LogService;
use App\Services\MarkdownRenderer;
use App\Services\PageCacheService;
use App\Services\SeoMetaService;
use App\Services\SiteSettingService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Rendering markdown is stateless — share one instance.
        $this->app->singleton(MarkdownRenderer::class);
        $this->app->singleton(SiteSettingService::class);
        $this->app->singleton(LogService::class);

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

        // Security audit trail for authentication.
        $auditUser = fn (Login|Logout $event) => $event->user instanceof Model
            ? $event->user
            : null;

        Event::listen(Login::class, fn (Login $event) => app(LogService::class)
            ->audit('auth.login', $auditUser($event), ['guard' => $event->guard]));
        Event::listen(Logout::class, fn (Logout $event) => app(LogService::class)
            ->audit('auth.logout', $auditUser($event), ['guard' => $event->guard]));
    }
}
