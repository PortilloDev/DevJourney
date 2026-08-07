@extends('layouts.public')

@section('content')
    <header class="mb-8">
        <h1 class="text-3xl font-bold tracking-tight">Challenges</h1>
        <p class="mt-2 text-slate-600 dark:text-slate-400">Think first, then reveal the solution. System design, DDD, CQRS, hexagonal architecture and more.</p>
    </header>

    <div class="lg:grid lg:grid-cols-[1fr_18rem] lg:gap-10">
        <div class="min-w-0">
            {{-- Filters --}}
            <form method="GET" class="mb-8 grid gap-3 sm:grid-cols-3">
                <select name="topic" onchange="this.form.submit()" class="rounded-lg border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-900">
                    <option value="">All topics</option>
                    @foreach($topics as $topic)
                        <option value="{{ $topic->value }}" @selected(($filters['topic'] ?? '') === $topic->value)>{{ $topic->label() }}</option>
                    @endforeach
                </select>
                <select name="difficulty" onchange="this.form.submit()" class="rounded-lg border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-900">
                    <option value="">All difficulties</option>
                    @foreach($difficulties as $difficulty)
                        <option value="{{ $difficulty->value }}" @selected(($filters['difficulty'] ?? '') === $difficulty->value)>{{ $difficulty->label() }}</option>
                    @endforeach
                </select>
                <select name="level" onchange="this.form.submit()" class="rounded-lg border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-900">
                    <option value="">All English levels</option>
                    @foreach($levels as $level)
                        <option value="{{ $level->value }}" @selected(($filters['level'] ?? '') === $level->value)>{{ $level->value }}</option>
                    @endforeach
                </select>
            </form>

            @if($challenges->isEmpty())
                <p class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500 dark:border-slate-700 dark:text-slate-400">No challenges match these filters.</p>
            @else
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach($challenges as $challenge)
                        <x-challenge-card :challenge="$challenge" />
                    @endforeach
                </div>
                <div class="mt-10">{{ $challenges->links() }}</div>
            @endif
        </div>

        {{-- Stats sidebar --}}
        <aside class="mt-10 lg:mt-0">
            <div class="sticky top-24 space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">Total solved</p>
                    <p class="mt-1 font-mono text-3xl font-bold text-accent-600 dark:text-accent-400">{{ $totalSolved }}</p>
                </div>

                @if($byTopic->isNotEmpty())
                    <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <p class="mb-3 text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">By topic</p>
                        <ul class="space-y-2 text-sm">
                            @foreach($byTopic as $topic => $count)
                                <li class="flex items-center justify-between">
                                    <a href="{{ route('challenges.index', ['topic' => $topic]) }}" class="text-slate-600 hover:text-accent-600 dark:text-slate-300">{{ \App\Enums\ChallengeTopic::from($topic)->label() }}</a>
                                    <span class="font-mono text-slate-400">{{ $count }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($byDifficulty->isNotEmpty())
                    <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <p class="mb-3 text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">By difficulty</p>
                        <ul class="space-y-2 text-sm">
                            @foreach($byDifficulty as $difficulty => $count)
                                <li class="flex items-center justify-between">
                                    <span class="text-slate-600 dark:text-slate-300">{{ \App\Enums\ChallengeDifficulty::from($difficulty)->label() }}</span>
                                    <span class="font-mono text-slate-400">{{ $count }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </aside>
    </div>
@endsection
