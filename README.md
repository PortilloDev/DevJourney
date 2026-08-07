# DevJourney

> A public learning journal & portfolio platform where a senior backend developer documents his professional growth, technical challenges, and English language progression — all written in English.

Built from [`devjourney-technical-spec.md`](devjourney-technical-spec.md). Two sides:

- **Public site** (Blade + Tailwind + Alpine): home, journal, challenges, projects, about/now/progress, RSS + sitemap.
- **Private dashboard** (Filament v3 at `/admin`): CRUD for posts, challenges, projects, categories, tags, milestones, and site settings, plus dashboard widgets.

## Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.4 (dev tested on 8.3) |
| Framework | Laravel 11 |
| Admin UI | Filament v3 |
| Frontend | Blade + Tailwind CSS v3 + Alpine.js (Vite) |
| Markdown | league/commonmark (GFM), pre-rendered on save |
| Database | MySQL 8 (prod) · SQLite (local/tests) |
| Search | Laravel Scout + Meilisearch (optional) |
| Quality | Pint (PSR-12), PHPStan level 6 (Larastan), PHPUnit |

## Quick start (local, SQLite)

Everything is preconfigured to run against SQLite so you need no external services:

```bash
composer install
npm install
cp .env.example .env      # already present in this repo; edit if needed
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm run build             # or `npm run dev` for HMR
php artisan serve
```

Then open:

- Public site: <http://127.0.0.1:8000>
- Admin panel: <http://127.0.0.1:8000/admin>

**Seeded admin login:** `admin@devjourney.test` / `password`

## Run with Docker (MySQL, Redis, Meilisearch, Mailpit)

```bash
docker compose up -d --build
docker compose exec app php artisan migrate --seed
```

- App: <http://localhost:8000>
- Mailpit: <http://localhost:8025>
- Meilisearch: <http://localhost:7700>

The `app` service is wired to MySQL/Redis/Meilisearch/Mailpit via environment
variables in `docker-compose.yml`.

## Architecture notes

- **Domain data** lives in `app/Models` with typed enums in `app/Enums`
  (`EnglishLevel`, `PostStatus`, `ChallengeDifficulty`, `ChallengeTopic`,
  `MilestoneType`).
- **Markdown is stored raw and pre-rendered to HTML on save** (model `saving`
  hooks call `App\Services\MarkdownRenderer`) so public routes never render at
  request time. Reading time and the table of contents are derived here too.
- **Slugs** are generated and de-duplicated automatically by the
  `App\Models\Concerns\HasSlug` trait.
- **SEO**: each public controller populates a request-scoped
  `App\Services\SeoMetaService`; the layout renders `<title>`, meta description,
  Open Graph, Twitter cards, canonical URLs and JSON-LD (`Article`, `Person`).
- **Site settings** are a cached key/value store (`App\Services\SiteSettingService`)
  editable from the Filament panel — hero copy, `/now` content, social links,
  announcement banner, current English level, etc.
- Only **published** content (status `published` with a past `published_at`) is
  visible on public routes; drafts and scheduled posts return 404.

## Key routes

| Path | Description |
|---|---|
| `/` | Home (hero, latest posts, featured challenge, projects, milestones) |
| `/journal`, `/journal/{slug}` | Blog listing + article (TOC, reading progress, related) |
| `/category/{slug}`, `/tag/{slug}` | Filtered journal |
| `/challenges`, `/challenges/{slug}` | Filterable grid + think-first solution reveal |
| `/projects`, `/projects/{slug}` | Portfolio |
| `/about`, `/now`, `/progress` | Static pages + milestone timeline |
| `/feed`, `/sitemap.xml` | RSS 2.0 feed + sitemap |
| `/admin` | Filament dashboard |

## Quality gates

```bash
vendor/bin/pint --test                        # code style (PSR-12)
vendor/bin/phpstan analyse --memory-limit=1G  # static analysis, level 6
php artisan test                              # unit + feature tests
```

CI (`.github/workflows/ci.yml`) runs lint → test → (on `main`) build & deploy to
AWS ECS. See §9 of the spec for the target AWS infrastructure.

## Project layout

```
app/
├── Enums/            # EnglishLevel, PostStatus, ChallengeDifficulty, ...
├── Models/           # Post, Challenge, Project, Category, Tag, Milestone, ...
│   └── Concerns/     # HasSlug, Taggable
├── Services/         # MarkdownRenderer, SeoMetaService, SiteSettingService
├── Http/Controllers/Public/   # HomeController, PostController, ...
└── Filament/         # Resources + dashboard Widgets
resources/views/
├── layouts/public.blade.php
├── components/       # english-level-badge, post-card, challenge-card, toc, ...
├── public/           # home, posts/, challenges/, projects/, about, now, progress
└── feeds/            # rss, sitemap
```
