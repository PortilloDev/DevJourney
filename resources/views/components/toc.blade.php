@props(['items'])

@if(count($items) > 0)
    <nav aria-label="Table of contents" class="text-sm">
        <p class="mb-3 font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">On this page</p>
        <ul class="space-y-2 border-l border-slate-200 dark:border-slate-800">
            @foreach($items as $item)
                <li @class(['pl-4' => $item['level'] === 2, 'pl-8' => $item['level'] >= 3])>
                    <a href="#{{ $item['id'] }}" class="block text-slate-500 transition hover:text-accent-600 dark:text-slate-400 dark:hover:text-accent-400">
                        {{ $item['text'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
