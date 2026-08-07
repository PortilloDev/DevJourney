@extends('layouts.public')

@section('content')
    <div class="mx-auto max-w-3xl">
        <header class="mb-10">
            <h1 class="text-3xl font-bold tracking-tight">Progress</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-400">A timeline of milestones: English levels, certifications, challenges and projects shipped.</p>
        </header>

        @if($milestones->isEmpty())
            <p class="text-slate-500 dark:text-slate-400">No milestones recorded yet.</p>
        @else
            <ol class="relative border-l border-slate-200 dark:border-slate-800">
                @foreach($milestones as $milestone)
                    <li class="mb-10 ml-6">
                        <span class="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-slate-50 dark:ring-slate-950 {{ $milestone->type->colorClasses() }}">
                            <span class="h-2 w-2 rounded-full bg-current"></span>
                        </span>
                        <div class="flex flex-wrap items-center gap-2">
                            <time class="font-mono text-sm text-slate-500 dark:text-slate-400">{{ $milestone->achieved_at->format('M j, Y') }}</time>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $milestone->type->colorClasses() }}">{{ $milestone->type->label() }}</span>
                        </div>
                        <h3 class="mt-1 text-lg font-semibold">{{ $milestone->title }}</h3>
                        @if($milestone->description)
                            <p class="mt-1 text-slate-600 dark:text-slate-400">{{ $milestone->description }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
@endsection
