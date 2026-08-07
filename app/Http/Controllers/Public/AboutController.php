<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use App\Services\MarkdownRenderer;
use App\Services\SeoMetaService;
use App\Services\SiteSettingService;
use Illuminate\Contracts\View\View;

class AboutController extends Controller
{
    public function index(SeoMetaService $seo, SiteSettingService $settings, MarkdownRenderer $md): View
    {
        $seo->set(
            title: 'About',
            description: 'Professional bio, tech stack expertise and career timeline.',
        );

        return view('public.about', [
            'bioHtml' => $md->toHtml($settings->get('about_bio', $this->defaultBio())),
            'settings' => $settings,
        ]);
    }

    public function now(SeoMetaService $seo, SiteSettingService $settings, MarkdownRenderer $md): View
    {
        $seo->set(
            title: 'Now',
            description: 'What I am focused on right now.',
        );

        return view('public.now', [
            'nowHtml' => $md->toHtml($settings->get('now_content', $this->defaultNow())),
            'updatedAt' => $settings->get('now_updated_at'),
        ]);
    }

    public function progress(SeoMetaService $seo): View
    {
        $seo->set(
            title: 'Progress',
            description: 'A visual timeline of milestones: English levels, certifications, challenges and projects shipped.',
        );

        $milestones = Milestone::query()->chronological()->get();

        return view('public.progress', compact('milestones'));
    }

    private function defaultBio(): string
    {
        return "## Hi, I'm a senior backend developer\n\nI build maintainable systems with PHP, Laravel and Symfony, "
            ."and I'm leveling up toward architect and staff-engineer roles. This site documents that journey — "
            .'including my English progression from A2 to professional fluency.';
    }

    private function defaultNow(): string
    {
        return "I'm currently deep in **system design** and **Domain-Driven Design**, solving a challenge a day "
            .'and writing everything in English to push my language level forward.';
    }
}
