@extends('layouts.public')

@section('content')
    <header class="mb-8">
        <h1 class="text-3xl font-bold tracking-tight">
            @if($activeCategory) {{ $activeCategory->name }}
            @elseif($activeTag) #{{ $activeTag->name }}
            @else Journal
            @endif
        </h1>
        <p class="mt-2 text-slate-600 dark:text-slate-400">
            @if($activeCategory) {{ $activeCategory->description ?? 'Posts in this category.' }}
            @elseif($activeTag) Posts tagged “{{ $activeTag->name }}”.
            @else Notes on architecture, backend engineering and the English journey.
            @endif
        </p>
    </header>

    {{-- Category filters --}}
    @if($categories->isNotEmpty())
        <div class="mb-8 flex flex-wrap gap-2">
            <a href="{{ route('posts.index') }}" @class(['rounded-full px-3 py-1 text-sm font-medium transition', 'bg-accent-600 text-white' => ! $activeCategory && ! $activeTag, 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' => $activeCategory || $activeTag])>All</a>
            @foreach($categories as $category)
                <a href="{{ route('posts.category', $category) }}" @class(['rounded-full px-3 py-1 text-sm font-medium transition', 'bg-accent-600 text-white' => $activeCategory?->is($category), 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' => ! $activeCategory?->is($category)])>{{ $category->name }}</a>
            @endforeach
        </div>
    @endif

    {{-- Featured posts, shown separately from the main list --}}
    @if($featuredPosts->isNotEmpty())
        <section class="mb-10">
            <h2 class="mb-5 flex items-center gap-2 text-lg font-bold">
                <x-dynamic-component :component="'heroicon-o-star'" class="h-5 w-5 text-accent-600 dark:text-accent-400" />
                Featured
            </h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($featuredPosts as $post)
                    <x-featured-post-card :post="$post" />
                @endforeach
            </div>
        </section>
    @endif

    @if($posts->isNotEmpty())
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($posts as $post)
                <x-post-card :post="$post" />
            @endforeach
        </div>

        <div class="mt-10">{{ $posts->links() }}</div>
    @elseif($featuredPosts->isEmpty())
        <p class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500 dark:border-slate-700 dark:text-slate-400">No posts found.</p>
    @endif
@endsection
