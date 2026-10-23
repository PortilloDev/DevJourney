/**
 * Per-article AI chat widget — streams answers from /api/chat over SSE.
 *
 * The widget is scoped to a single post (sends its slug) and keeps its thread
 * and conversation credential in sessionStorage, so a reload keeps the thread
 * but closing the tab forgets it. Each message can be copied to the clipboard.
 * Pure client-side JS, safe inside cached HTML.
 */
export function chatWidget(slug) {
    const storageKey = `chat:${slug}`;

    return {
        slug: slug,
        conversationToken: null,
        question: '',
        messages: [],
        error: null,
        loading: false,
        copiedIndex: null,
        open: false,

        init() {
            try {
                const stored = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
                if (stored && Array.isArray(stored.messages)) {
                    this.messages = stored.messages;
                    this.conversationToken = stored.token || null;
                }
            } catch (e) {
                this.messages = [];
            }
            this.$nextTick(() => this.scrollToBottom(true));
        },

        persist() {
            try {
                sessionStorage.setItem(storageKey, JSON.stringify({
                    token: this.conversationToken,
                    messages: this.messages,
                }));
            } catch (e) {
                // Storage unavailable (private mode): the thread stays in memory.
            }
        },

        async ask() {
            const q = this.question.trim();
            if (!q || this.loading) {
                return;
            }

            this.error = null;
            this.loading = true;
            this.question = '';
            this.messages.push({ role: 'user', text: q });
            this.messages.push({ role: 'assistant', text: '', sources: [], related: [], refused: false, incomplete: false, truncated: false });
            const index = this.messages.length - 1;
            this.persist();
            this.$nextTick(() => this.scrollToBottom(true));

            try {
                const res = await fetch('/api/chat', {
                    method: 'POST',
                    headers: {
                        'Accept': 'text/event-stream',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''),
                    },
                    body: JSON.stringify({ question: q, post: this.slug, conversation_token: this.conversationToken, stream: true }),
                });

                if (!res.ok) {
                    const body = await res.json().catch(() => ({}));
                    this.error = body.message || 'The assistant is unavailable. Try again later.';
                    this.messages.splice(index, 1);
                    this.persist();

                    return;
                }

                const reader = res.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) {
                        break;
                    }

                    buffer += decoder.decode(value, { stream: true });

                    let idx;
                    while ((idx = buffer.indexOf('\n')) !== -1) {
                        const line = buffer.slice(0, idx).replace(/\r$/, '');
                        buffer = buffer.slice(idx + 1);
                        this.handleLine(line, index);
                    }

                    this.$nextTick(() => this.scrollToBottom());
                }

                this.handleLine(buffer, index);
            } catch (e) {
                this.error = 'Something went wrong. Please try again.';
                this.messages.splice(index, 1);
            } finally {
                this.loading = false;
                this.persist();
                this.$nextTick(() => this.scrollToBottom());
            }
        },

        handleLine(line, index) {
            if (!line.startsWith('data:')) {
                return;
            }

            const data = line.slice(5).trim();
            if (data === '' || data === '[DONE]') {
                return;
            }

            let event;
            try {
                event = JSON.parse(data);
            } catch (e) {
                return;
            }

            if (event.delta) {
                this.messages[index].text += event.delta;
                this.$nextTick(() => this.scrollToBottom());
            }

            if (event.error) {
                this.error = event.message || 'The assistant is temporarily unavailable.';
                this.messages[index].incomplete = !!event.incomplete;
            }

            if (event.done) {
                const seen = new Set();

                this.messages[index].sources = (event.sources || []).filter((source) => {
                    const key = source.url || source.title || '';

                    if (seen.has(key)) {
                        return false;
                    }

                    seen.add(key);

                    return true;
                });
                this.messages[index].related = event.related || [];
                this.messages[index].refused = !!event.refused;
                this.messages[index].incomplete = !!event.incomplete;
                this.messages[index].truncated = !!event.truncated;
                this.messages[index].usageId = event.usage_id ?? null;
                if (event.conversation_token) {
                    this.conversationToken = event.conversation_token;
                }
            }

            this.persist();
        },

        async rate(message, value) {
            if (!message.usageId || !this.conversationToken) {
                return;
            }

            const previous = message.rating ?? null;
            message.rating = value;
            this.persist();

            try {
                const res = await fetch('/api/chat/feedback', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''),
                    },
                    body: JSON.stringify({
                        conversation: this.conversationToken,
                        usage_id: message.usageId,
                        rating: value,
                    }),
                });

                if (!res.ok) {
                    throw new Error('feedback_rejected');
                }
            } catch (e) {
                message.rating = previous;
                this.error = 'Could not register your feedback.';
            }

            this.persist();
        },

        async copy(message, index) {
            try {
                await navigator.clipboard.writeText(message.text);
                this.copiedIndex = index;
                setTimeout(() => {
                    if (this.copiedIndex === index) {
                        this.copiedIndex = null;
                    }
                }, 1500);
            } catch (e) {
                this.error = 'Could not copy to clipboard.';
            }
        },

        hasSources(message) {
            return Array.isArray(message.sources) && message.sources.some((s) => s.url);
        },

        toggle() {
            this.open = !this.open;

            if (this.open) {
                this.$nextTick(() => this.scrollToBottom(true));
            }
        },

        scrollToBottom(force = false) {
            const el = this.$refs.output;

            if (!el) {
                return;
            }

            const nearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 80;

            if (!force && !nearBottom) {
                return;
            }

            el.scrollTop = el.scrollHeight;
        },
    };
}
