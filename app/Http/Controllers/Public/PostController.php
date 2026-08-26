<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Services\SeoMetaService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request, SeoMetaService $seo): View
    {
        $seo->set(
            title: 'Journal',
            description: 'Learning journal entries on architecture, backend engineering, DevOps and the English journey from A2 to professional.',
        );

        $posts = $this->baseQuery()->where('featured', false)->paginate(9)->withQueryString();
        $featuredPosts = $this->featuredQuery()->limit(3)->get();
        $categories = Category::query()->orderBy('sort_order')->get();

        return view('public.posts.index', [
            'posts' => $posts,
            'featuredPosts' => $featuredPosts,
            'categories' => $categories,
            'activeCategory' => null,
            'activeTag' => null,
        ]);
    }

    public function show(Post $post, SeoMetaService $seo): View
    {
        abort_unless($this->isVisible($post), 404);

        $post->load(['category', 'tags']);

        $seo->set(
            title: $post->seo_title ?: $post->title,
            description: $post->seo_description ?: $post->excerpt,
            image: $post->featured_image ? asset('storage/'.$post->featured_image) : null,
            type: 'article',
            canonical: route('posts.show', $post),
        )->addJsonLd([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => $post->updated_at?->toIso8601String(),
            'articleSection' => $post->category?->name,
        ]);

        $related = Post::query()
            ->published()
            ->when($post->category_id, fn ($q) => $q->where('category_id', $post->category_id))
            ->whereKeyNot($post->getKey())
            ->latest('published_at')
            ->limit(3)
            ->get();

        return view('public.posts.show', [
            'post' => $post,
            'related' => $related,
            'toc' => $post->tableOfContents(),
        ]);
    }

    public function byCategory(Category $category, SeoMetaService $seo): View
    {
        $seo->set(
            title: $category->name,
            description: $category->description ?: "Posts in {$category->name}.",
        );

        $posts = $this->baseQuery()
            ->where('category_id', $category->id)
            ->where('featured', false)
            ->paginate(9)
            ->withQueryString();

        $featuredPosts = $this->featuredQuery()
            ->where('category_id', $category->id)
            ->limit(3)
            ->get();

        return view('public.posts.index', [
            'posts' => $posts,
            'featuredPosts' => $featuredPosts,
            'categories' => Category::query()->orderBy('sort_order')->get(),
            'activeCategory' => $category,
            'activeTag' => null,
        ]);
    }

    public function byTag(Tag $tag, SeoMetaService $seo): View
    {
        $seo->set(
            title: "#{$tag->name}",
            description: "Posts tagged {$tag->name}.",
        );

        $posts = $tag->posts()
            ->published()
            ->with(['category', 'tags'])
            ->where('featured', false)
            ->latest('published_at')
            ->paginate(9)
            ->withQueryString();

        $featuredPosts = $this->featuredQuery()
            ->whereHas('tags', fn ($q) => $q->whereKey($tag->getKey()))
            ->limit(3)
            ->get();

        return view('public.posts.index', [
            'posts' => $posts,
            'featuredPosts' => $featuredPosts,
            'categories' => Category::query()->orderBy('sort_order')->get(),
            'activeCategory' => null,
            'activeTag' => $tag,
        ]);
    }

    /**
     * @return Builder<Post>
     */
    private function baseQuery()
    {
        return Post::query()
            ->published()
            ->with(['category', 'tags'])
            ->latest('published_at');
    }

    /**
     * @return Builder<Post>
     */
    private function featuredQuery()
    {
        return Post::query()
            ->published()
            ->featured()
            ->with(['category', 'tags'])
            ->latest('published_at');
    }

    private function isVisible(Post $post): bool
    {
        return $post->status->value === 'published'
            && $post->published_at !== null
            && $post->published_at->isPast();
    }
}
