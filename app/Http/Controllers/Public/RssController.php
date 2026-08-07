<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Response;

class RssController extends Controller
{
    public function index(): Response
    {
        $posts = Post::query()
            ->latestPublished()
            ->limit(30)
            ->get();

        $content = view('feeds.rss', [
            'posts' => $posts,
            'updatedAt' => $posts->first()?->published_at,
        ])->render();

        return response($content, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }
}
