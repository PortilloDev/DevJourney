@extends('layouts.public')

@section('content')
    @php
        $currentLevel = $settings->get('current_english_level', 'B1');
        $week = $settings->get('current_week');
        $focus = $settings->get('current_focus');
    @endphp

    {{-- Hero --}}
    <section class="mb-16 text-center">
        <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-1.5 font-mono text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
            @if($week)<span>Week {{ $week }}</span><span class="text-slate-300 dark:text-slate-600">·</span>@endif
            <span>English level: {{ $currentLevel }}</span>
            <span class="text-slate-300 dark:text-slate-600">·</span>
            <span>{{ $stats['challenges'] }} challenges solved</span>
        </p>

        <h1 class="mx-auto max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl">
            {{ $settings->get('hero_title', 'Building in public, one commit at a time.') }}
        </h1>
        <p class="mx-auto mt-5 max-w-2xl text-lg text-slate-600 dark:text-slate-400">
            {{ $settings->get('hero_tagline', 'A senior backend developer documenting the climb toward architect roles — and the English journey from A2 to professional, written entirely in English.') }}
        </p>

        <div class="mt-8 flex items-center justify-center gap-3">
            <a href="{{ route('posts.index') }}" class="rounded-lg bg-accent-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-accent-500">Read the journal</a>
            <a href="{{ route('challenges.index') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold transition hover:border-accent-400 dark:border-slate-700">Browse challenges</a>
        </div>

        <dl class="mx-auto mt-12 grid max-w-lg grid-cols-3 gap-4">
            @foreach(['Posts' => $stats['posts'], 'Challenges' => $stats['challenges'], 'Projects' => $stats['projects']] as $label => $value)
                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                    <dd class="mt-1 font-mono text-2xl font-bold text-accent-600 dark:text-accent-400">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- What I'm working on now --}}
    @if($focus)
        <section class="mb-16 rounded-2xl border border-accent-200 bg-accent-50 p-6 dark:border-accent-800/60 dark:bg-accent-900/20">
            <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-accent-700 dark:text-accent-300">
                <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent-400 opacity-75"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-accent-500"></span></span>
                What I'm working on now
            </h2>
            <p class="text-slate-700 dark:text-slate-200">{{ $focus }}</p>
            <a href="{{ route('now') }}" class="mt-2 inline-block text-sm font-medium text-accent-600 hover:underline dark:text-accent-400">More on the /now page →</a>
        </section>
    @endif

    {{-- Featured journal entries --}}
    @if($featuredPosts->isNotEmpty())
        <section class="mb-16">
            <div class="mb-6 flex items-end justify-between">
                <h2 class="text-2xl font-bold">Featured</h2>
                <span class="text-sm font-medium text-accent-600 dark:text-accent-400">Editor's picks</span>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($featuredPosts as $post)
                    <x-featured-post-card :post="$post" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Latest journal entries --}}
    <section class="mb-16">
        <div class="mb-6 flex items-end justify-between">
            <h2 class="text-2xl font-bold">Latest from the journal</h2>
            <a href="{{ route('posts.index') }}" class="text-sm font-medium text-accent-600 hover:underline dark:text-accent-400">View all →</a>
        </div>
        @if($latestPosts->isEmpty())
            <p class="text-slate-500 dark:text-slate-400">No posts published yet.</p>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($latestPosts as $post)
                    <x-post-card :post="$post" />
                @endforeach
            </div>
        @endif
    </section>

    {{-- Featured challenge --}}
    @if($featuredChallenge)
        <section class="mb-16">
            <h2 class="mb-6 text-2xl font-bold">Challenge of the week</h2>
            <div class="grid gap-5 md:grid-cols-2">
                <x-challenge-card :challenge="$featuredChallenge" />
            </div>
        </section>
    @endif

    {{-- Projects --}}
    @if($projects->isNotEmpty())
        <section class="mb-16">
            <div class="mb-6 flex items-end justify-between">
                <h2 class="text-2xl font-bold">Selected projects</h2>
                <a href="{{ route('projects.index') }}" class="text-sm font-medium text-accent-600 hover:underline dark:text-accent-400">View all →</a>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($projects as $project)
                    <x-project-card :project="$project" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Milestones --}}
    @if($milestones->isNotEmpty())
        <section>
            <div class="mb-6 flex items-end justify-between">
                <h2 class="text-2xl font-bold">Recent milestones</h2>
                <a href="{{ route('progress') }}" class="text-sm font-medium text-accent-600 hover:underline dark:text-accent-400">Full timeline →</a>
            </div>
            <ul class="space-y-3">
                @foreach($milestones as $milestone)
                    <li class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $milestone->type->colorClasses() }}">{{ $milestone->type->label() }}</span>
                        <span class="flex-1 font-medium">{{ $milestone->title }}</span>
                        <time class="text-sm text-slate-500 dark:text-slate-400">{{ $milestone->achieved_at->format('M Y') }}</time>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
