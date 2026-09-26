# resources/ — Blade, Tailwind, Alpine, Vite

Scope-specific rules for `resources/`. Root `AGENTS.md` wins on anything not covered here.

- JS lives in `resources/js/*.js` and is bundled by Vite. Register Alpine
  components with `Alpine.data('name', fn)` in `resources/js/app.js`.
- After changing JS you MUST rebuild: `npm run build` (or `npm run dev` for HMR).
  Serving a stale `public/build` bundle produces runtime errors like
  `Alpine ... is not defined`. Vite runs on the HOST — the PHP container has no Node.
- Views: `layouts/public.blade.php` is the public shell; reusable UI in
  `components/`; page templates in `public/`; RSS/sitemap in `feeds/`.
- Public pages are **full-page cached** and must not contain per-user server-side
  data; anything interactive (e.g. the chat widget) is client-side Alpine only.
- Match the existing Tailwind utility style and dark-mode classes; keep components
  small and data-driven via props (`@props([...])`).
