@props(['post'])

<article class="group flex h-full flex-col rounded-xl border border-slate-200 bg-white p-5 transition hover:border-accent-400 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900 dark:hover:border-accent-600">
    <div class="mb-3 flex flex-wrap items-center gap-2 text-xs">
        @if($post->category)
            <a href="{{ route('posts.category', $post->category) }}" class="rounded-md bg-slate-100 px-2 py-0.5 font-medium text-slate-600 hover:text-accent-600 dark:bg-slate-800 dark:text-slate-300">{{ $post->category->name }}</a>
        @endif
        <x-english-level-badge :level="$post->english_level" />
    </div>

    <h3 class="mb-2 text-lg font-semibold leading-snug">
        <a href="{{ route('posts.show', $post) }}" class="hover:text-accent-600 dark:hover:text-accent-400">
            {{ $post->title }}
        </a>
    </h3>

    @if($post->excerpt)
        <p class="mb-4 line-clamp-3 flex-1 text-sm text-slate-600 dark:text-slate-400">{{ $post->excerpt }}</p>
    @endif

    <div class="mt-auto flex items-center justify-between border-t border-slate-100 pt-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
        <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('M j, Y') }}</time>
        <x-reading-time :minutes="$post->reading_minutes" />
    </div>
</article>
