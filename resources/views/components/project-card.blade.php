@props(['project'])

<article class="group flex h-full flex-col overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:border-accent-400 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900 dark:hover:border-accent-600">
    @if($project->featured_image)
        <a href="{{ route('projects.show', $project) }}" class="block aspect-video overflow-hidden bg-slate-100 dark:bg-slate-800">
            <img src="{{ asset('storage/'.$project->featured_image) }}" alt="{{ $project->title }}" loading="lazy" decoding="async" class="h-full w-full object-cover transition group-hover:scale-105">
        </a>
    @endif

    <div class="flex flex-1 flex-col p-5">
        <h3 class="mb-2 text-lg font-semibold">
            <a href="{{ route('projects.show', $project) }}" class="hover:text-accent-600 dark:hover:text-accent-400">{{ $project->title }}</a>
        </h3>
        <p class="mb-4 line-clamp-2 flex-1 text-sm text-slate-600 dark:text-slate-400">{{ $project->description }}</p>

        <div class="mb-4 flex flex-wrap gap-1.5">
            @foreach(($project->stack ?? []) as $tech)
                <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $tech }}</span>
            @endforeach
        </div>

        <div class="mt-auto flex items-center gap-4 border-t border-slate-100 pt-3 text-sm dark:border-slate-800">
            @if($project->repo_url)
                <a href="{{ $project->repo_url }}" rel="noopener" class="text-slate-500 hover:text-accent-500 dark:text-slate-400">Repo</a>
            @endif
            @if($project->demo_url)
                <a href="{{ $project->demo_url }}" rel="noopener" class="text-slate-500 hover:text-accent-500 dark:text-slate-400">Demo</a>
            @endif
            <a href="{{ route('projects.show', $project) }}" class="ml-auto font-medium text-accent-600 hover:underline dark:text-accent-400">Read more →</a>
        </div>
    </div>
</article>
