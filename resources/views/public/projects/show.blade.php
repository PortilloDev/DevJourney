@extends('layouts.public')

@section('content')
    <article class="mx-auto max-w-3xl">
        <nav class="mb-6 text-sm text-slate-500 dark:text-slate-400" aria-label="Breadcrumb">
            <a href="{{ route('projects.index') }}" class="hover:text-accent-500">Projects</a>
        </nav>

        <header class="mb-8">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $project->title }}</h1>
            <p class="mt-3 text-lg text-slate-600 dark:text-slate-400">{{ $project->description }}</p>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                @foreach(($project->stack ?? []) as $tech)
                    <span class="rounded bg-slate-100 px-2 py-1 font-mono text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $tech }}</span>
                @endforeach
            </div>

            <div class="mt-5 flex flex-wrap gap-3">
                @if($project->repo_url)
                    <a href="{{ $project->repo_url }}" rel="noopener" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:border-accent-400 dark:border-slate-700">View repository</a>
                @endif
                @if($project->demo_url)
                    <a href="{{ $project->demo_url }}" rel="noopener" class="rounded-lg bg-accent-600 px-4 py-2 text-sm font-medium text-white hover:bg-accent-500">Live demo</a>
                @endif
            </div>
        </header>

        @if($project->featured_image)
            <img src="{{ asset('storage/'.$project->featured_image) }}" alt="{{ $project->title }}" class="mb-8 w-full rounded-xl" loading="lazy" decoding="async">
        @endif

        @if($project->body_html)
            <div class="prose prose-slate max-w-none dark:prose-invert prose-pre:p-4">
                {!! $project->body_html !!}
            </div>
        @endif
    </article>
@endsection
