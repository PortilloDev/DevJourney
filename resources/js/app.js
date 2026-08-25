import './bootstrap';
import './tracker';

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import hljs from 'highlight.js/lib/common';

window.Alpine = Alpine;
Alpine.plugin(collapse);
Alpine.start();

// Syntax-highlight code blocks and add a copy button to each.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.prose pre code').forEach((block) => {
        hljs.highlightElement(block);

        const pre = block.parentElement;
        if (pre.querySelector('.copy-btn')) return;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'copy-btn';
        btn.textContent = 'Copy';
        btn.addEventListener('click', async () => {
            await navigator.clipboard.writeText(block.innerText);
            btn.textContent = 'Copied!';
            setTimeout(() => (btn.textContent = 'Copy'), 1500);
        });
        pre.appendChild(btn);
    });

    // Reading progress bar on article pages.
    const bar = document.getElementById('reading-progress');
    if (bar) {
        const update = () => {
            const h = document.documentElement;
            const scrolled = (h.scrollTop / (h.scrollHeight - h.clientHeight)) * 100;
            bar.style.width = `${scrolled}%`;
        };
        window.addEventListener('scroll', update, { passive: true });
        update();
    }
});
