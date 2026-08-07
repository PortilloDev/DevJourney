@extends('layouts.public')

@section('content')
    <div class="mx-auto max-w-3xl">
        <h1 class="mb-8 text-3xl font-bold tracking-tight">About</h1>
        <div class="prose prose-slate max-w-none dark:prose-invert prose-pre:p-4">
            {!! $bioHtml !!}
        </div>

        @php($expertise = $settings->get('expertise', ['PHP', 'Laravel', 'Symfony', 'DDD', 'CQRS', 'Hexagonal Architecture', 'MySQL', 'Docker', 'AWS']))
        @if(! empty($expertise))
            <h2 class="mb-4 mt-12 text-xl font-bold">Tech stack &amp; expertise</h2>
            <div class="flex flex-wrap gap-2">
                @foreach($expertise as $skill)
                    <span class="rounded-lg bg-slate-100 px-3 py-1.5 font-mono text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $skill }}</span>
                @endforeach
            </div>
        @endif
    </div>
@endsection
