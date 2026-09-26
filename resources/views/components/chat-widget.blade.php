@props(['enabled' => true, 'post' => null])

@if($enabled && $post)
    <section class="mt-16 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
             x-data="chatWidget('{{ $post->slug }}')">
        <header class="flex items-center gap-3 border-b border-slate-100 px-6 py-4 dark:border-slate-800">
            <span class="relative flex h-2.5 w-2.5 shrink-0">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent-400 opacity-75"></span>
                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-accent-500"></span>
            </span>
            <div>
                <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Ask about this article</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Answers come only from this post.</p>
            </div>
        </header>

        <div class="space-y-4 px-6 py-5">
            <div x-show="messages.length" x-cloak x-ref="output" class="max-h-96 space-y-4 overflow-y-auto pr-1">
                <template x-for="(message, i) in messages" :key="i">
                    <div>
                        {{-- User message --}}
                        <template x-if="message.role === 'user'">
                            <div class="flex flex-col items-end">
                                <p class="max-w-[85%] rounded-2xl rounded-br-sm bg-accent-600 px-4 py-2 text-sm text-white"
                                   x-text="message.text"></p>
                                <button type="button" @click="copy(message, i)"
                                        class="mt-1 text-[11px] text-slate-400 transition hover:text-accent-600 dark:hover:text-accent-400"
                                        x-text="copiedIndex === i ? 'Copied!' : 'Copy'"></button>
                            </div>
                        </template>

                        {{-- Assistant message --}}
                        <template x-if="message.role === 'assistant'">
                            <div class="flex flex-col items-start">
                                <p class="max-w-[85%] whitespace-pre-wrap rounded-2xl rounded-bl-sm bg-slate-100 px-4 py-2 text-sm leading-relaxed text-slate-800 dark:bg-slate-800 dark:text-slate-100"
                                   :class="message.refused && 'bg-transparent px-0 text-slate-500 dark:bg-transparent dark:text-slate-400'">
                                    <span x-text="message.text"></span><span
                                        x-show="loading && i === messages.length - 1"
                                        class="ml-1.5 inline-block h-3 w-3 animate-spin rounded-full border-2 border-accent-500 border-t-transparent align-middle"></span>
                                </p>

                                <div x-show="hasSources(message)" x-cloak class="mt-2 flex flex-wrap items-center gap-2">
                                    <span class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">Sources</span>
                                    <template x-for="(source, j) in message.sources" :key="j">
                                        <span>
                                            <a x-show="source.url" :href="source.url"
                                               class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600 transition hover:text-accent-600 dark:bg-slate-800 dark:text-slate-300"
                                               x-text="source.title"></a>
                                            <span x-show="!source.url"
                                                  class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                                                  x-text="source.title"></span>
                                        </span>
                                    </template>
                                </div>

                                <button type="button" @click="copy(message, i)" x-show="message.text"
                                        class="mt-1 text-[11px] text-slate-400 transition hover:text-accent-600 dark:hover:text-accent-400"
                                        x-text="copiedIndex === i ? 'Copied!' : 'Copy'"></button>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <p x-show="error" x-cloak class="text-sm text-red-600 dark:text-red-400" x-text="error"></p>

            <form @submit.prevent="ask" class="flex items-center gap-2">
                <input type="text" x-model="question" name="question" autocomplete="off"
                       placeholder="e.g. Can you explain the part about ports and adapters?"
                       class="w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-800 placeholder-slate-400 focus:border-accent-500 focus:outline-none focus:ring-1 focus:ring-accent-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                       aria-label="Ask a question about this article">
                <button type="submit" :disabled="loading"
                        class="shrink-0 rounded-xl bg-accent-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-accent-500 disabled:cursor-not-allowed disabled:opacity-50">
                    <span x-show="!loading">Ask</span>
                    <span x-show="loading">…</span>
                </button>
            </form>
        </div>
    </section>
@endif
