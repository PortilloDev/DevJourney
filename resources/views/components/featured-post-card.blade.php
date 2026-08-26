@props(['post'])

<article class="group relative flex h-full flex-col rounded-2xl border-2 border-accent-400/60 bg-gradient-to-br from-accent-50/70 to-white p-6 transition hover:border-accent-500 hover:shadow-xl dark:border-accent-500/40 dark:from-accent-900/25 dark:to-slate-900 dark:hover:border-accent-400">
    <div class="mb-3 inline-flex items-center gap-1.5 self-start rounded-full bg-accent-600 px-2.5 py-1 text-xs font-semibold text-white">
        <x-dynamic-component :component="'heroicon-o-star'" class="h-3.5 w-3.5" />
        Featured
    </div>

    <div class="mb-3 flex flex-wrap items-center gap-2 text-xs">
        @if($post->category)
            <a href="{{ route('posts.category', $post->category) }}" class="rounded-md bg-slate-100 px-2 py-0.5 font-medium text-slate-600 hover:text-accent-600 dark:bg-slate-800 dark:text-slate-300">{{ $post->category->name }}</a>
        @endif
        <x-english-level-badge :level="$post->english_level" />
    </div>

    <h3 class="mb-3 text-xl font-bold leading-snug">
        <a href="{{ route('posts.show', $post) }}" class="hover:text-accent-600 dark:hover:text-accent-400">
            {{ $post->title }}
        </a>
    </h3>

    @if($post->excerpt)
        <p class="mb-5 line-clamp-3 flex-1 text-sm text-slate-600 dark:text-slate-400">{{ $post->excerpt }}</p>
    @endif

    <div class="mt-auto flex items-center justify-between border-t border-accent-200/60 pt-4 text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">
        <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('M j, Y') }}</time>
        <x-reading-time :minutes="$post->reading_minutes" />
    </div>
</article>
