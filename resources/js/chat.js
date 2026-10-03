/**
 * Per-article AI chat widget — streams answers from /api/chat over SSE.
 *
 * The widget is scoped to a single post (sends its slug) and keeps the
 * conversation history in memory for the current page only. Each message can be
 * copied to the clipboard. Pure client-side JS, safe inside cached HTML.
 */
export function chatWidget(slug) {
    return {
        slug: slug,
        conversationId: (typeof crypto !== 'undefined' && crypto.randomUUID)
            ? crypto.randomUUID()
            : 'c-'.concat(Date.now(), '-', Math.random().toString(16).slice(2)),
        question: '',
        messages: [],
        error: null,
        loading: false,
        copiedIndex: null,

        async ask() {
            const q = this.question.trim();
            if (!q || this.loading) {
                return;
            }

            this.error = null;
            this.loading = true;
            this.question = '';
            this.messages.push({ role: 'user', text: q });
            this.messages.push({ role: 'assistant', text: '', sources: [], refused: false });
            const index = this.messages.length - 1;

            try {
                const res = await fetch('/api/chat', {
                    method: 'POST',
                    headers: {
                        'Accept': 'text/event-stream',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''),
                    },
                    body: JSON.stringify({ question: q, post: this.slug, conversation: this.conversationId, stream: true }),
                });

                if (!res.ok) {
                    const body = await res.json().catch(() => ({}));
                    this.error = body.message || 'The assistant is unavailable. Try again later.';
                    this.messages.splice(index, 1);

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
            }

            if (event.error) {
                this.error = event.message || 'The assistant is temporarily unavailable.';
            }

            if (event.done) {
                this.messages[index].sources = event.sources || [];
                this.messages[index].refused = !!event.refused;
            }
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

        scrollToBottom() {
            this.$refs.output?.scrollTo({ top: this.$refs.output.scrollHeight, behavior: 'smooth' });
        },
    };
}
