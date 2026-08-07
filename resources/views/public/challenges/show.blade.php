@extends('layouts.public')

@section('content')
    <article class="mx-auto max-w-3xl">
        <nav class="mb-6 text-sm text-slate-500 dark:text-slate-400" aria-label="Breadcrumb">
            <a href="{{ route('challenges.index') }}" class="hover:text-accent-500">Challenges</a>
            <span class="mx-1">/</span>
            <a href="{{ route('challenges.index', ['topic' => $challenge->topic->value]) }}" class="hover:text-accent-500">{{ $challenge->topic->label() }}</a>
        </nav>

        <header class="mb-8">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center gap-1.5 text-sm font-medium text-accent-600 dark:text-accent-400">
                    <x-dynamic-component :component="$challenge->topic->icon()" class="h-5 w-5" />
                    {{ $challenge->topic->label() }}
                </span>
                <x-difficulty-badge :difficulty="$challenge->difficulty" />
                <x-english-level-badge :level="$challenge->english_level" />
            </div>
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $challenge->title }}</h1>
        </header>

        {{-- Problem statement --}}
        <section class="mb-8">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">The problem</h2>
            <div class="prose prose-slate max-w-none dark:prose-invert prose-pre:p-4">
                {!! $challenge->question_html !!}
            </div>
        </section>

        {{-- Reveal solution --}}
        <section x-data="{ shown: false }" class="mb-8">
            <div class="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <button
                    @click="shown = ! shown"
                    class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left"
                    :aria-expanded="shown"
                >
                    <span class="font-semibold">
                        <span x-show="! shown">💡 Try to think it through, then reveal the solution</span>
                        <span x-show="shown" x-cloak>Solution</span>
                    </span>
                    <svg class="h-5 w-5 shrink-0 text-slate-400 transition" :class="shown && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="shown" x-collapse x-cloak class="border-t border-slate-200 px-5 py-5 dark:border-slate-800">
                    <div class="prose prose-slate max-w-none dark:prose-invert prose-pre:p-4">
                        {!! $challenge->answer_html !!}
                    </div>

                    @if($challenge->explanation_html)
                        <div class="mt-8 border-t border-slate-200 pt-6 dark:border-slate-800">
                            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Why it works</h3>
                            <div class="prose prose-slate max-w-none dark:prose-invert prose-pre:p-4">
                                {!! $challenge->explanation_html !!}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        @if($challenge->tags->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                @foreach($challenge->tags as $tag)
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-600 dark:bg-slate-800 dark:text-slate-300">#{{ $tag->name }}</span>
                @endforeach
            </div>
        @endif
    </article>
@endsection
