@props(['minutes'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400']) }}>
    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    {{ max(1, (int) $minutes) }} min read
</span>
