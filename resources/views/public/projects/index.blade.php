@extends('layouts.public')

@section('content')
    <header class="mb-8">
        <h1 class="text-3xl font-bold tracking-tight">Projects</h1>
        <p class="mt-2 text-slate-600 dark:text-slate-400">Side projects with tech stacks, architecture notes and lessons learned.</p>
    </header>

    @if($projects->isEmpty())
        <p class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500 dark:border-slate-700 dark:text-slate-400">No projects published yet.</p>
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($projects as $project)
                <x-project-card :project="$project" />
            @endforeach
        </div>
    @endif
@endsection
