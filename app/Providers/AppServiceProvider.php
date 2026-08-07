<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\MarkdownRenderer;
use App\Services\SeoMetaService;
use App\Services\SiteSettingService;
use Illuminate\Pagination\Paginator;
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
    }
}
