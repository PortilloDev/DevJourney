@props(['settings'])

@php
    $socials = [
        'GitHub' => $settings->get('social_github'),
        'LinkedIn' => $settings->get('social_linkedin'),
        'X' => $settings->get('social_x'),
    ];
@endphp

<footer class="border-t border-slate-200 dark:border-slate-800">
    <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 py-8 sm:px-6 md:flex-row lg:px-8">
        <p class="text-sm text-slate-500 dark:text-slate-400">
            &copy; {{ date('Y') }} {{ config('app.name') }} · Built in public with Laravel.
        </p>
        <div class="flex items-center gap-4 text-sm">
            @foreach($socials as $label => $url)
                @if($url)
                    <a href="{{ $url }}" rel="me noopener" class="text-slate-500 hover:text-accent-500 dark:text-slate-400">{{ $label }}</a>
                @endif
            @endforeach
            <a href="{{ route('feed') }}" class="text-slate-500 hover:text-accent-500 dark:text-slate-400">RSS</a>
        </div>
    </div>
</footer>
