/**
 * Public AI chat widget — streams answers from /api/chat over SSE.
 *
 * Full-page cache consideration: this component is interactive and never cached;
 * it lives inside the home Blade which is full-page cached, but the widget itself
 * is pure client-side JS, so no per-user data is rendered server-side.
 */
export function chatWidget() {
    return {
        question: '',
        answer: '',
        sources: [],
        error: null,
        loading: false,
        asked: false,

        async ask() {
            const q = this.question.trim();
            if (!q || this.loading) {
                return;
            }

            this.answer = '';
            this.sources = [];
            this.error = null;
            this.loading = true;
            this.asked = true;

            try {
                const res = await fetch('/api/chat', {
                    method: 'POST',
                    headers: {
                        'Accept': 'text/event-stream',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''),
                    },
                    body: JSON.stringify({ question: q, stream: true }),
                });

                if (!res.ok) {
                    const body = await res.json().catch(() => ({}));
                    this.error = body.message || 'The assistant is unavailable. Try again later.';
                    this.loading = false;

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
                        this.handleLine(line);
                    }

                    this.$nextTick(() => this.scrollToBottom());
                }

                this.handleLine(buffer);
            } catch (e) {
                this.error = 'Something went wrong. Please try again.';
            } finally {
                this.loading = false;
            }
        },

        handleLine(line) {
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
                this.answer += event.delta;
            }

            if (event.error) {
                this.error = event.message || 'The assistant is temporarily unavailable.';
            }

            if (event.done) {
                this.sources = event.sources || [];
            }
        },

        hasSources() {
            return Array.isArray(this.sources) && this.sources.filter((s) => s.url).length > 0;
        },

        scrollToBottom() {
            this.$refs.output?.scrollTo({ top: this.$refs.output.scrollHeight, behavior: 'smooth' });
        },
    };
}
