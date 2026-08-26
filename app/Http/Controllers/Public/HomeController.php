<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\Milestone;
use App\Models\Post;
use App\Models\Project;
use App\Services\SeoMetaService;
use App\Services\SiteSettingService;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function index(SeoMetaService $seo, SiteSettingService $settings): View
    {
        $seo->set(
            title: 'DevJourney',
            description: $settings->get('hero_tagline', 'A senior backend developer documenting the climb toward architect roles — in English.'),
        )->addJsonLd([
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $settings->get('author_name', 'DevJourney'),
            'jobTitle' => 'Senior Backend Developer',
            'url' => url('/'),
        ]);

        $latestPosts = Post::query()
            ->latestPublished()
            ->with(['category', 'tags'])
            ->where('featured', false)
            ->limit(4)
            ->get();

        $featuredPosts = Post::query()
            ->latestPublished()
            ->featured()
            ->with(['category', 'tags'])
            ->limit(4)
            ->get();

        $featuredChallenge = Challenge::query()
            ->published()
            ->latest('published_at')
            ->first();

        $projects = Project::query()
            ->published()
            ->ordered()
            ->limit(3)
            ->get();

        $milestones = Milestone::query()
            ->chronological()
            ->limit(5)
            ->get();

        $stats = [
            'posts' => Post::query()->where('status', PostStatus::Published->value)->count(),
            'challenges' => Challenge::query()->where('status', PostStatus::Published->value)->count(),
            'projects' => Project::query()->where('status', PostStatus::Published->value)->count(),
        ];

        return view('public.home', compact(
            'latestPosts',
            'featuredPosts',
            'featuredChallenge',
            'projects',
            'milestones',
            'stats',
        ));
    }
}
