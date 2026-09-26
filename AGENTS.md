# AGENTS.md

DevJourney — a Laravel 11 / PHP 8.4 monolith. Public site in Blade + Tailwind +
Alpine (Vite), admin in Filament v3 at `/admin`, MySQL 8 in prod and SQLite for
tests, Redis for cache/queues/sessions. Product context lives in `README.md` and
`devjourney-technical-spec.md`; read them for domain background.

This file is the single source of truth for agent guidance. Do not duplicate it.

## Commands

PHP, Artisan, Composer and the quality tools run **inside the app container**.
The container ships PHP only — it has **no Node**, so npm runs on the HOST.

```bash
# Dev stack (MySQL, Redis, Meilisearch, Qdrant, Mailpit)
docker compose -f docker-compose.dev.yml up -d
docker compose -f docker-compose.dev.yml exec app php artisan migrate --seed

# Tests / quality (inside the container)
docker compose -f docker-compose.dev.yml exec app php artisan test
docker compose -f docker-compose.dev.yml exec app vendor/bin/pint            # add --test to check only
docker compose -f docker-compose.dev.yml exec app vendor/bin/phpstan analyse --memory-limit=1G

# Frontend assets (on the HOST, not in the container)
npm ci && npm run dev        # HMR, writes public/hot   — or: npm run build
```

CI (`.github/workflows/ci.yml`) is the definition of done: `pint --test`,
`phpstan analyse`, `php artisan test --coverage --min=70`, then build & deploy.

## Non-obvious rules (do not relearn these the hard way)

- **Tests run on SQLite `:memory:`**, forced in `tests/TestCase.php`. The
  docker-compose env exports MySQL, which otherwise wins over `phpunit.xml`; do
  not let the suite touch the dev MySQL database.
- **Run `php artisan optimize:clear` after deploying code.** The full-page cache
  (`App\Http\Middleware\CachePublicPages`) is invalidated only by *content*
  changes, so new code can serve a stale page until the cache is cleared.
- `bootstrap/app.php` must keep a **single** `->withMiddleware()` block: a second
  call overwrites the first and silently drops custom middleware.
- Markdown is **pre-rendered on save** (model `saving` hooks →
  `App\Services\MarkdownRenderer`). Never render Markdown at request time.
- Only **published** content (status `published` + past `published_at`) is public;
  drafts and scheduled items return 404.
- Admin access is allow-listed by `config('app.filament_admin_email')`
  (`FILAMENT_ADMIN_EMAIL`). Seeded login: `admin@devjourney.test` / `password`.
- The RAG stack (Qdrant + OpenAI/DeepSeek) is gated by API keys in `.env`
  (`config/rag.php`); without keys the chat returns 503 and indexing is off.
- Style: `declare(strict_types=1)` everywhere, PSR-12 via Pint, PHPStan level 6.
  Follow the existing patterns (Services, Enums, Filament Resources) — do not
  introduce new architectural layers without reason.

## Layout

`app/` domain code (Models, Enums, Services, Jobs, Filament, Http/Controllers),
`resources/` views + JS, `database/` migrations/factories/seeders, `tests/`
PHPUnit. Scope-specific rules live in nested `AGENTS.md` files under `app/`,
`tests/` and `resources/`.
