@props(['enabled' => true])

@if($enabled)
    <section class="mb-16 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
             x-data="chatWidget">
        <header class="mb-4 flex items-center gap-2">
            <span class="relative flex h-2.5 w-2.5">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent-400 opacity-75"></span>
                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-accent-500"></span>
            </span>
            <h2 class="text-lg font-bold">Ask the DevJourney assistant</h2>
        </header>

        <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
            Ask about journal posts, challenges, projects or reference documents. Answers come from the site content only.
        </p>

        <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800/60"
             x-ref="output"
             x-show="asked"
             x-cloak
             class="mb-4 max-h-96 overflow-y-auto">
            <template x-if="!error">
                <p class="whitespace-pre-wrap text-sm text-slate-800 dark:text-slate-100">
                    <span x-show="refused" x-cloak>I can only answer about DevJourney content. Try asking about a post, challenge, project or document.</span>
                    <span x-show="!refused" x-text="answer"></span>
                    <span x-show="loading && !answer" class="text-slate-400 dark:text-slate-500">Thinking…</span>
                    <span x-show="loading && answer" class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-accent-500 border-t-transparent align-middle"></span>
                </p>
            </template>

            <div x-show="hasSources()" x-cloak class="mt-3 border-t border-slate-200 pt-3 dark:border-slate-700">
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Sources</p>
                <ul class="space-y-1">
                    <template x-for="(source, i) in sources" :key="i">
                        <li>
                            <a x-show="source.url" :href="source.url" target="_blank" rel="noopener"
                               class="text-sm text-accent-600 hover:underline dark:text-accent-400">
                                <span x-text="source.title"></span>
                            </a>
                            <span x-show="!source.url" class="text-sm text-slate-600 dark:text-slate-400" x-text="source.title"></span>
                        </li>
                    </template>
                </ul>
            </div>

            <p x-show="error" x-cloak class="text-sm text-red-600 dark:text-red-400" x-text="error"></p>
        </div>

        <form @submit.prevent="ask" class="flex items-start gap-2">
            <input type="text" x-model="question" name="question"
                   autocomplete="off"
                   placeholder="e.g. What did you learn about hexagonal architecture?"
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                   aria-label="Ask a question">
            <button type="submit" :disabled="loading"
                    class="shrink-0 rounded-lg bg-accent-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-accent-500 disabled:opacity-50">
                <span x-show="!loading">Ask</span>
                <span x-show="loading">…</span>
            </button>
        </form>
    </section>
@endif
