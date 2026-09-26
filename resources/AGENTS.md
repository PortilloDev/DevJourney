# resources/ — Blade, Tailwind, Alpine, Vite

Scope-specific rules for `resources/`. Root `AGENTS.md` wins on anything not covered here.

- JS lives in `resources/js/*.js` and is bundled by Vite. Register Alpine
  components with `Alpine.data('name', fn)` in `resources/js/app.js`.
- Vite runs on the HOST — the PHP container has **no Node**.
- Changing JS has NO effect until you rebuild, and a stale bundle causes runtime
  errors such as `Uncaught ReferenceError: <component> is not defined` (Alpine
  expressions then break, leaving buttons unresponsive). Two options:
  - **Dev (recomendado):** `npm run dev` on the host. It writes `public/hot` and
    serves the JS live, bypassing the built bundle.
  - **Build:** `npm run build`, then deploy `public/build/`.
- **Gotcha:** `docker-compose.dev.yml` mounts `build:/var/www/html/public/build`
  as a **named volume**, which shadows the host `public/build`. A host build is
  NOT served until you also copy it into the volume, e.g.
  `docker cp public/build/. <app-container>:/var/www/html/public/build/`, then
  `php artisan optimize:clear` (cached pages reference the old asset hash).
- **Asset URLs:** the dev container receives the request with `Host: localhost`
  (Docker drops the port), so `asset()` would emit `http://localhost/build/...`
  and the browser (on `:8002`) fails with `ERR_CONNECTION_REFUSED` (unstyled
  pages). Set `ASSET_URL` to the real dev origin in `.env`
  (`ASSET_URL=http://localhost:8002`). In production set it to the public origin.
- Views: `layouts/public.blade.php` is the public shell; reusable UI in
  `components/`; page templates in `public/`; RSS/sitemap in `feeds/`.
- Public pages are **full-page cached** and must not contain per-user server-side
  data; anything interactive (e.g. the chat widget) is client-side Alpine only.
- Match the existing Tailwind utility style and dark-mode classes; keep components
  small and data-driven via props (`@props([...])`).
