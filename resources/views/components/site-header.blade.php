@props(['settings'])

@php
    $links = [
        ['route' => 'posts.index', 'label' => 'Journal', 'active' => 'posts.*'],
        ['route' => 'challenges.index', 'label' => 'Challenges', 'active' => 'challenges.*'],
        ['route' => 'projects.index', 'label' => 'Projects', 'active' => 'projects.*'],
        ['route' => 'about', 'label' => 'About', 'active' => 'about'],
        ['route' => 'now', 'label' => 'Now', 'active' => 'now'],
        ['route' => 'progress', 'label' => 'Progress', 'active' => 'progress'],
    ];
@endphp

@if($banner = $settings->get('announcement_banner'))
    <div class="bg-accent-600 px-4 py-2 text-center text-sm font-medium text-white">
        {{ $banner }}
    </div>
@endif

<header
    x-data="{ open: false }"
    class="sticky top-0 z-40 border-b border-slate-200 bg-slate-50/80 backdrop-blur dark:border-slate-800 dark:bg-slate-950/80"
>
    <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" class="flex items-center gap-2 font-mono text-lg font-bold">
            <span class="text-accent-500">&gt;_</span>
            <span>{{ config('app.name') }}</span>
        </a>

        {{-- Desktop nav --}}
        <div class="hidden items-center gap-1 md:flex">
            @foreach($links as $link)
                <a
                    href="{{ route($link['route']) }}"
                    @class([
                        'rounded-md px-3 py-2 text-sm font-medium transition',
                        'text-accent-600 dark:text-accent-400' => request()->routeIs($link['active']),
                        'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white' => ! request()->routeIs($link['active']),
                    ])
                >{{ $link['label'] }}</a>
            @endforeach

            <x-theme-toggle class="ml-2" />
        </div>

        {{-- Mobile controls --}}
        <div class="flex items-center gap-2 md:hidden">
            <x-theme-toggle />
            <button
                @click="open = ! open"
                class="rounded-md p-2 text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-800"
                aria-label="Toggle menu"
            >
                <svg x-show="! open" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg x-show="open" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </nav>

    {{-- Mobile menu --}}
    <div x-show="open" x-collapse x-cloak class="border-t border-slate-200 md:hidden dark:border-slate-800">
        <div class="space-y-1 px-4 py-3">
            @foreach($links as $link)
                <a
                    href="{{ route($link['route']) }}"
                    @class([
                        'block rounded-md px-3 py-2 text-base font-medium',
                        'bg-slate-200 text-accent-600 dark:bg-slate-800 dark:text-accent-400' => request()->routeIs($link['active']),
                        'text-slate-700 dark:text-slate-300' => ! request()->routeIs($link['active']),
                    ])
                >{{ $link['label'] }}</a>
            @endforeach
        </div>
    </div>
</header>
