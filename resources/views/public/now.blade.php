@extends('layouts.public')

@section('content')
    <div class="mx-auto max-w-3xl">
        <h1 class="mb-2 text-3xl font-bold tracking-tight">Now</h1>
        <p class="mb-8 text-sm text-slate-500 dark:text-slate-400">
            What I'm focused on right now.
            @if($updatedAt) <span>Last updated {{ \Illuminate\Support\Carbon::parse($updatedAt)->format('F j, Y') }}.</span> @endif
            Inspired by <a href="https://nownownow.com" rel="noopener" class="text-accent-600 hover:underline dark:text-accent-400">nownownow.com</a>.
        </p>
        <div class="prose prose-slate max-w-none dark:prose-invert prose-pre:p-4">
            {!! $nowHtml !!}
        </div>
    </div>
@endsection
