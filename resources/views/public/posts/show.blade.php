@extends('layouts.public')

@section('progress')
    <div class="fixed inset-x-0 top-0 z-50 h-1 bg-transparent">
        <div id="reading-progress" class="h-full w-0 bg-accent-500 transition-[width] duration-75"></div>
    </div>
@endsection

@section('content')
    <div class="lg:grid lg:grid-cols-[1fr_16rem] lg:gap-12">
        <article class="min-w-0">
            <nav class="mb-6 text-sm text-slate-500 dark:text-slate-400" aria-label="Breadcrumb">
                <a href="{{ route('posts.index') }}" class="hover:text-accent-500">Journal</a>
                @if($post->category)
                    <span class="mx-1">/</span>
                    <a href="{{ route('posts.category', $post->category) }}" class="hover:text-accent-500">{{ $post->category->name }}</a>
                @endif
            </nav>

            <header class="mb-8 border-b border-slate-200 pb-8 dark:border-slate-800">
                <div class="mb-4 flex flex-wrap items-center gap-3 text-sm text-slate-500 dark:text-slate-400">
                    <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('F j, Y') }}</time>
                    <span>·</span>
                    <x-reading-time :minutes="$post->reading_minutes" />
                    <x-english-level-badge :level="$post->english_level" />
                </div>
                <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $post->title }}</h1>
                @if($post->excerpt)
                    <p class="mt-4 text-lg text-slate-600 dark:text-slate-400">{{ $post->excerpt }}</p>
                @endif
            </header>

            @if($post->featured_image)
                <img src="{{ asset('storage/'.$post->featured_image) }}" alt="{{ $post->title }}" class="mb-8 w-full rounded-xl" loading="lazy">
            @endif

            <div class="prose prose-slate max-w-none dark:prose-invert prose-pre:p-4">
                {!! $post->body_html !!}
            </div>

            @if($post->tags->isNotEmpty())
                <div class="mt-10 flex flex-wrap gap-2 border-t border-slate-200 pt-6 dark:border-slate-800">
                    @foreach($post->tags as $tag)
                        <a href="{{ route('posts.tag', $tag) }}" class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-600 hover:text-accent-600 dark:bg-slate-800 dark:text-slate-300">#{{ $tag->name }}</a>
                    @endforeach
                </div>
            @endif

            {{-- Share --}}
            <div class="mt-8 flex items-center gap-3 text-sm text-slate-500 dark:text-slate-400">
                <span>Share:</span>
                <a href="https://twitter.com/intent/tweet?url={{ urlencode(route('posts.show', $post)) }}&text={{ urlencode($post->title) }}" rel="noopener" class="hover:text-accent-500">X</a>
                <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(route('posts.show', $post)) }}" rel="noopener" class="hover:text-accent-500">LinkedIn</a>
            </div>

            @if($related->isNotEmpty())
                <section class="mt-16">
                    <h2 class="mb-6 text-xl font-bold">Related posts</h2>
                    <div class="grid gap-5 sm:grid-cols-3">
                        @foreach($related as $rel)
                            <x-post-card :post="$rel" />
                        @endforeach
                    </div>
                </section>
            @endif
        </article>

        {{-- TOC sidebar --}}
        <aside class="hidden lg:block">
            <div class="sticky top-24">
                <x-toc :items="$toc" />
            </div>
        </aside>
    </div>
@endsection
