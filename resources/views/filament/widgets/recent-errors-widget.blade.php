<x-filament::section>
    <x-slot name="heading">
        {{ $heading }}
    </x-slot>
    <x-slot name="description">
        <a href="{{ $url }}" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">View all logs →</a>
    </x-slot>

    @if($logs->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">No errors logged yet.</p>
    @else
        <ul class="space-y-3">
            @foreach($logs as $log)
                <li class="flex items-start gap-3">
                    <span @class([
                        'mt-1 inline-flex h-2 w-2 shrink-0 rounded-full',
                        $log->levelClass() === 'danger' ? 'bg-danger-500' : 'bg-warning-500',
                    ])></span>
                    <div class="min-w-0">
                        <p class="truncate text-sm" title="{{ $log->message }}">{{ $log->message }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $log->created_at->diffForHumans() }}
                            @if($log->url) · {{ \Illuminate\Support\Str::limit($log->url, 60) }} @endif
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</x-filament::section>
