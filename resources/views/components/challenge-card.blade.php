@props(['challenge'])

<article class="group flex h-full flex-col rounded-xl border border-slate-200 bg-white p-5 transition hover:border-accent-400 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900 dark:hover:border-accent-600">
    <div class="mb-3 flex items-center justify-between">
        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-accent-600 dark:text-accent-400">
            <x-dynamic-component :component="$challenge->topic->icon()" class="h-4 w-4" />
            {{ $challenge->topic->label() }}
        </span>
        <x-difficulty-badge :difficulty="$challenge->difficulty" />
    </div>

    <h3 class="mb-4 flex-1 text-lg font-semibold leading-snug">
        <a href="{{ route('challenges.show', $challenge) }}" class="hover:text-accent-600 dark:hover:text-accent-400">
            {{ $challenge->title }}
        </a>
    </h3>

    <div class="mt-auto flex items-center justify-between border-t border-slate-100 pt-3 dark:border-slate-800">
        <x-english-level-badge :level="$challenge->english_level" />
        <a href="{{ route('challenges.show', $challenge) }}" class="text-sm font-medium text-accent-600 hover:underline dark:text-accent-400">
            View solution →
        </a>
    </div>
</article>
