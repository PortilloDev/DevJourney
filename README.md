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

> **Frontend assets are compiled, not served live by the PHP container.** When you
> change anything under `resources/js`, rebuild (`npm run build`) or run the Vite
> dev server on the host (`npm run dev`, writes `public/hot` for HMR). `npm` runs
> on the **host** — the container has no Node. With `docker-compose.dev.yml` the
> build output lives in a **named volume**
> (`build:/var/www/html/public/build`) that shadows the host directory, so a host
> build is only served after copying it into the volume:
> `docker cp public/build/. <app-container>:/var/www/html/public/build/` plus
> `php artisan optimize:clear`. A stale bundle shows up in the browser console as
> `Uncaught ReferenceError: <component> is not defined` (e.g. broken buttons).
>
> **Asset host:** set `ASSET_URL` to the origin the browser actually uses. Docker
> delivers the request to the container with a portless `Host`, so otherwise
> `asset()` emits `http://localhost/build/...` and the browser fails with
> `ERR_CONNECTION_REFUSED` (unstyled page). Dev: `ASSET_URL=http://localhost:8002`;
> prod: your public https origin.

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
## 🚀 Deploy

### 🔧 Configuración

- Copia el archivo `.env.example` a `.env` y edita los valores de la variable `APP_ENV` a `local`.
- Crea una base de datos MySQL y configura la variable `DB_DATABASE` en `.env`.
- Crea un usuario y contraseña para la base de datos y configura la variable `DB_USERNAME` y `DB_PASSWORD` en `.env`.

### ⚙️ Requisitos de producción

- **`APP_URL` debe ser el origen público** (p. ej. `https://example.com`), e
  idealmente `ASSET_URL` igual. El middleware `SetRequestHost` y
  `URL::forceRootUrl` derivan de ahí los hosts y la validación de URLs firmadas;
  un valor incorrecto rompe enlaces y subidas.
- **Claves del RAG** en `.env` para el chat/indexado: `LLM_API_KEY`,
  `EMBEDDING_API_KEY`, `QDRANT_API_KEY` (la misma que usa el contenedor `qdrant`).
- **Timeouts del servidor web > timeouts del proveedor LLM.** El cliente aborta
  la llamada al LLM a los `RAG_TIMEOUT_COMPLETE` / `RAG_TIMEOUT_STREAM` segundos
  (30 por defecto). Comprueba que `fastcgi_read_timeout` de nginx (120 s en
  `docker-fpm-setup/nginx.conf`) y `request_terminate_timeout` de PHP-FPM (sin
  límite por defecto) son **mayores**: si el servidor corta antes, el stream se
  trunca sin evento de error y el widget se queda cargando.
- La imagen **ya incluye `poppler-utils`** (`pdftotext`) para indexar PDFs.
- **Evaluación de calidad del chat (`php artisan ai:eval`).** Recorre
  `tests/Eval/cases.json`, mide recuperación (acierto y MRR), respuestas y
  rechazos, e imprime un informe que además guarda en `storage/app/eval/`. **Gasta
  cuota real del proveedor**, así que no se ejecuta sola ni está en el scheduler:
  lánzala a mano, sobre todo antes de un despliegue que toque prompt, `top_k`,
  umbrales o modelo. Añade `--judge` para puntuar la fundamentación con
  `RAG_EVAL_JUDGE_MODEL` y `--min=0.8` (o `RAG_EVAL_MIN_SCORE`) para que devuelva
  un código de salida distinto de cero si la tasa de acierto baja del umbral. No
  registra turnos en el histórico ni usa la caché de respuestas.
- Tras desplegar, ejecuta:
  ```bash
  php artisan migrate --force      # nuevas tablas
  php artisan optimize:clear       # caché de páginas/assets
  # reindexar contenido y documentos para el RAG
  ```
- **No** hagas `php artisan config:cache` en la build: el `.env` se monta en
  runtime y una caché de build lo ignoraría.

### 🚀 Despliegue

```bash
chmod +x deploy.sh -- la primera vez
./deploy.sh
```

## 📝 Licencia

Este proyecto está bajo la licencia MIT.

