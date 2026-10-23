@props(['enabled' => true, 'post' => null])

@if($enabled && $post)
    <div x-data="chatWidget('{{ $post->slug }}')">
        {{-- Mobile floating button --}}
        <button type="button" @click="toggle"
                class="fixed bottom-5 right-5 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-accent-600 text-white shadow-lg transition hover:bg-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-400 xl:hidden"
                :aria-expanded="open"
                aria-controls="chat-panel"
                aria-label="Open article chat">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72A7.963 7.963 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
        </button>

        <section id="chat-panel"
                 class="fixed inset-x-0 bottom-0 z-40 max-h-[85vh] overflow-hidden rounded-t-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-800 dark:bg-slate-900 xl:static xl:z-auto xl:max-h-none xl:rounded-2xl xl:shadow-none"
                 :class="open ? 'block' : 'hidden xl:block'"
                 aria-label="Article chat">
            <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <span class="relative flex h-2.5 w-2.5 shrink-0">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent-400 opacity-75"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-accent-500"></span>
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Ask about this article</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Answers come only from this post.</p>
                    </div>
                </div>

                <button type="button" @click="open = false"
                        class="rounded-lg p-1 text-slate-400 transition hover:text-slate-600 dark:hover:text-slate-200 xl:hidden"
                        aria-label="Close chat">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </header>

            <div class="flex max-h-[calc(85vh-5rem)] flex-col gap-4 px-6 py-5 xl:max-h-[calc(100vh-12rem)]">
                <div x-show="messages.length" x-cloak x-ref="output" class="min-h-0 flex-1 space-y-4 overflow-y-auto pr-1">
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

                                    <p x-show="message.incomplete" x-cloak
                                       class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                        This answer was cut off before it finished. Please ask again.
                                    </p>

                                    <p x-show="message.truncated" x-cloak
                                       class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        This answer was shortened because it reached the length limit.
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

                                    <div x-show="message.related && message.related.length" x-cloak class="mt-2 flex flex-wrap items-center gap-2">
                                        <span class="text-xs font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">Challenges</span>
                                        <template x-for="(challenge, j) in (message.related || [])" :key="j">
                                            <a :href="challenge.url"
                                               class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600 transition hover:text-accent-600 dark:bg-slate-800 dark:text-slate-300"
                                               x-text="challenge.title"></a>
                                        </template>
                                    </div>

                                    <div class="mt-1 flex items-center gap-3">
                                        <button type="button" @click="copy(message, i)" x-show="message.text"
                                                class="text-[11px] text-slate-400 transition hover:text-accent-600 dark:hover:text-accent-400"
                                                x-text="copiedIndex === i ? 'Copied!' : 'Copy'"></button>

                                        <template x-if="message.usageId">
                                            <div class="flex items-center gap-3">
                                                <button type="button" @click="rate(message, 1)"
                                                        class="text-[11px] transition"
                                                        :class="message.rating === 1 ? 'font-semibold text-accent-600 dark:text-accent-400' : 'text-slate-400 hover:text-accent-600 dark:hover:text-accent-400'"
                                                        aria-label="Mark this answer as useful">Helpful</button>
                                                <button type="button" @click="rate(message, -1)"
                                                        class="text-[11px] transition"
                                                        :class="message.rating === -1 ? 'font-semibold text-red-600 dark:text-red-400' : 'text-slate-400 hover:text-red-600 dark:hover:text-red-400'"
                                                        aria-label="Mark this answer as not useful">Not helpful</button>
                                            </div>
                                        </template>
                                    </div>
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
    </div>
@endif
