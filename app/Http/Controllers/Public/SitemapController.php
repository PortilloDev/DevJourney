<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\Post;
use App\Models\Project;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = [];

        foreach (['home', 'posts.index', 'challenges.index', 'projects.index', 'about', 'now', 'progress'] as $name) {
            $urls[] = ['loc' => route($name), 'lastmod' => now()->toAtomString(), 'priority' => '0.8'];
        }

        Post::query()->published()->get(['slug', 'updated_at'])->each(function (Post $post) use (&$urls): void {
            $urls[] = ['loc' => route('posts.show', $post), 'lastmod' => $post->updated_at?->toAtomString(), 'priority' => '0.7'];
        });

        Challenge::query()->published()->get(['slug', 'updated_at'])->each(function (Challenge $c) use (&$urls): void {
            $urls[] = ['loc' => route('challenges.show', $c), 'lastmod' => $c->updated_at?->toAtomString(), 'priority' => '0.6'];
        });

        Project::query()->published()->get(['slug', 'updated_at'])->each(function (Project $p) use (&$urls): void {
            $urls[] = ['loc' => route('projects.show', $p), 'lastmod' => $p->updated_at?->toAtomString(), 'priority' => '0.6'];
        });

        $content = view('feeds.sitemap', ['urls' => $urls])->render();

        return response($content, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
