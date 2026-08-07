# DevJourney — Technical Specification

> A public learning journal & portfolio platform where a senior backend developer documents his professional growth, technical challenges, and English language progression — all written in English.

---

## 1. Project Overview

### 1.1 Purpose

DevJourney is a personal professional platform with two sides:

- **Public site**: A modern, fast, SEO-friendly website where visitors can read learning journal entries, browse solved technical challenges, explore projects, and follow the author's progression from A2 to professional English.
- **Private dashboard**: An admin panel for content management, site customization, analytics overview, and writing workflow.

### 1.2 Core Value Proposition

- Document the journey of a senior backend developer leveling up toward architect/staff roles.
- Write everything in English, making the language progression itself part of the story.
- Showcase real technical challenges with solutions (system design, DDD, CQRS, hexagonal architecture, etc.).
- Build in public — transparency as a differentiator.

### 1.3 Target Audience

- Tech recruiters and hiring managers evaluating senior backend / architect candidates.
- Fellow developers interested in learning paths, architecture patterns, and interview prep.
- The author himself — as an accountability and reflection tool.

---

## 2. Tech Stack

| Layer | Technology | Version / Notes |
|---|---|---|
| Language | PHP | 8.4 |
| Framework | Laravel | Latest (11.x+) |
| Database | MySQL | 8.x |
| Containerization | Docker | Docker Compose for local dev |
| CI/CD | GitHub Actions | Lint, test, build, deploy |
| Hosting | AWS | ECS Fargate or EC2 (see Section 9) |
| VCS | GitHub | Monorepo |
| Frontend | Blade + Tailwind CSS + Alpine.js | Server-rendered, minimal JS |
| Admin UI | Filament PHP | v3 — Laravel admin panel |
| Search | Laravel Scout + Meilisearch | Full-text search on posts/challenges |
| Cache | Redis | Sessions, cache, queues |
| Media | S3 + CloudFront | Image/file storage and CDN |
| Mail | SES or Mailgun | Transactional emails, newsletter |

---

## 3. Architecture

### 3.1 High-Level Architecture

```
┌─────────────────────────────────────────────────┐
│                   CloudFront CDN                │
└──────────────────────┬──────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────┐
│              ALB / Load Balancer                │
└──────────────────────┬──────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────┐
│           Laravel App (ECS / EC2)               │
│  ┌─────────────┐  ┌─────────────┐              │
│  │ Public Site  │  │  Dashboard  │              │
│  │   (Blade)    │  │ (Filament)  │              │
│  └─────────────┘  └─────────────┘              │
└───────┬──────────────┬──────────────┬───────────┘
        │              │              │
   ┌────▼────┐   ┌─────▼─────┐  ┌────▼────┐
   │  MySQL  │   │   Redis   │  │   S3    │
   │  (RDS)  │   │(ElastiC.) │  │ (media) │
   └─────────┘   └───────────┘  └─────────┘
```

### 3.2 Application Structure

Use Laravel's default structure enhanced with clear domain separation:

```
app/
├── Models/               # Eloquent models
│   ├── Post.php
│   ├── Challenge.php
│   ├── Project.php
│   ├── Category.php
│   ├── Tag.php
│   ├── EnglishLevel.php
│   └── SiteSetting.php
├── Enums/
│   └── EnglishLevel.php  # A1, A2, B1, B2, C1, C2
├── Filament/             # Admin panel resources
│   ├── Resources/
│   │   ├── PostResource.php
│   │   ├── ChallengeResource.php
│   │   ├── ProjectResource.php
│   │   └── SiteSettingResource.php
│   ├── Pages/
│   │   └── Dashboard.php
│   └── Widgets/
├── Http/
│   ├── Controllers/
│   │   ├── Public/       # Public-facing controllers
│   │   │   ├── HomeController.php
│   │   │   ├── PostController.php
│   │   │   ├── ChallengeController.php
│   │   │   ├── ProjectController.php
│   │   │   ├── AboutController.php
│   │   │   └── RssController.php
│   │   └── Api/          # Optional API controllers
│   └── Middleware/
├── Services/
│   ├── MarkdownRenderer.php
│   ├── SeoMetaService.php
│   ├── MediaService.php
│   └── SiteSettingService.php
├── View/
│   └── Components/       # Blade components
resources/
├── views/
│   ├── layouts/
│   │   └── public.blade.php
│   ├── public/
│   │   ├── home.blade.php
│   │   ├── posts/
│   │   ├── challenges/
│   │   ├── projects/
│   │   ├── about.blade.php
│   │   └── now.blade.php
│   └── components/
│       ├── english-level-badge.blade.php
│       ├── challenge-card.blade.php
│       ├── post-card.blade.php
│       ├── project-card.blade.php
│       ├── toc.blade.php
│       └── reading-time.blade.php
├── css/
│   └── app.css           # Tailwind
└── js/
    └── app.js            # Alpine.js
```

---

## 4. Data Model

### 4.1 Entity Relationship Diagram

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│     posts    │     │  categories  │     │     tags     │
├──────────────┤     ├──────────────┤     ├──────────────┤
│ id           │     │ id           │     │ id           │
│ title        │  ┌──│ slug         │     │ name         │
│ slug         │  │  │ name         │     │ slug         │
│ excerpt      │  │  │ description  │     └──────┬───────┘
│ body_md      │  │  │ sort_order   │            │
│ body_html    │  │  └──────────────┘      ┌─────┴──────┐
│ category_id ─┼──┘                        │ taggables  │
│ english_level│     ┌──────────────┐      │(polymorphic│
│ featured_img │     │  challenges  │      │  pivot)    │
│ seo_title    │     ├──────────────┤      └────────────┘
│ seo_desc     │     │ id           │
│ reading_min  │     │ title        │
│ status       │     │ slug         │
│ published_at │     │ difficulty   │  ┌──────────────┐
│ created_at   │     │ topic        │  │   projects   │
│ updated_at   │     │ question_md  │  ├──────────────┤
└──────────────┘     │ answer_md    │  │ id           │
                     │ explanation  │  │ title        │
                     │ english_level│  │ slug         │
                     │ status       │  │ description  │
                     │ published_at │  │ body_md      │
                     └──────────────┘  │ repo_url     │
                                       │ demo_url     │
┌──────────────┐     ┌──────────────┐  │ stack (json) │
│site_settings │     │    media     │  │ featured_img │
├──────────────┤     ├──────────────┤  │ status       │
│ key          │     │ id           │  │ sort_order   │
│ value (json) │     │ filename     │  └──────────────┘
│ group        │     │ path         │
│ type         │     │ mime_type    │  ┌──────────────┐
└──────────────┘     │ alt_text     │  │  milestones  │
                     │ size         │  ├──────────────┤
                     └──────────────┘  │ id           │
                                       │ title        │
                                       │ description  │
                                       │ achieved_at  │
                                       │ icon         │
                                       │ type         │
                                       └──────────────┘
```

### 4.2 Key Fields

**Post statuses**: `draft`, `published`, `archived`

**English levels** (enum): `A1`, `A2`, `B1`, `B2`, `C1`, `C2` — every content piece gets tagged with the author's English level at the time of writing.

**Challenge difficulties**: `beginner`, `intermediate`, `advanced`, `expert`

**Challenge topics**: `system-design`, `ddd`, `cqrs`, `hexagonal`, `solid`, `design-patterns`, `databases`, `apis`, `testing`, `devops`, `microservices`, `security`, `performance`

**Milestone types**: `english`, `technical`, `career`, `project`

---

## 5. Features

### 5.1 Public Site

#### Home Page
- Hero section with tagline and current status ("Week 24 · English level: B1 · 87 challenges solved").
- Latest journal entries (3–5 cards).
- Featured challenge of the week.
- Current learning focus / "What I'm working on now".
- Milestone timeline highlights.
- Projects showcase (top 3).

#### Journal (Blog)
- Paginated list with category/tag filters.
- Each post shows: title, excerpt, date, reading time, English level badge, category, tags.
- Post detail: rendered Markdown, table of contents, reading progress bar, related posts, social sharing.
- RSS feed (`/feed`).

#### Challenges
- Filterable grid: by topic, difficulty, English level.
- Card view: topic icon, title, difficulty badge, "View Solution" toggle.
- Detail view: problem statement → try to think → reveal solution → explanation.
- Stats sidebar: total solved, by topic breakdown, by difficulty.

#### Projects Portfolio
- Card grid with screenshots, tech stack badges, links to repo/demo.
- Detail page with full write-up, lessons learned, architecture notes.

#### About / Now
- `/about` — professional bio, tech stack expertise, career timeline.
- `/now` — what the author is currently focused on (updated manually via dashboard). Inspired by nownownow.com.

#### Progress Timeline
- `/progress` — visual timeline of milestones (English levels achieved, certifications, challenges milestones, projects shipped, job search milestones).

#### SEO & Performance
- Dynamic meta tags (title, description, OG, Twitter cards) per page.
- Structured data (JSON-LD): Article, Person, BreadcrumbList.
- Sitemap.xml auto-generated.
- Canonical URLs.
- Lazy-loaded images with WebP conversion.
- Page speed target: 90+ Lighthouse score.

### 5.2 Private Dashboard (Filament)

#### Content Management
- CRUD for posts with Markdown editor (live preview).
- CRUD for challenges (question/answer/explanation with Markdown).
- CRUD for projects.
- Media library with drag-and-drop upload to S3.
- Tag and category management.
- Milestone management.

#### Writing Workflow
- Draft → Review → Published status flow.
- Schedule posts for future publication.
- "English level at writing time" auto-suggested based on current setting, editable per post.

#### Site Customization
- Edit hero text, tagline, "now" page content.
- Toggle sections on/off on the home page.
- Social links configuration.
- Custom announcement banner.

#### Dashboard Widgets
- Content stats: total posts, challenges, projects.
- Publishing calendar.
- English level progression chart.
- Most viewed content (if analytics integrated).

---

## 6. Docker Setup

### 6.1 docker-compose.yml Services

```yaml
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    volumes:
      - .:/var/www/html
    ports:
      - "8000:8000"
    depends_on:
      - mysql
      - redis
    environment:
      - APP_ENV=local

  mysql:
    image: mysql:8.0
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: devjourney
      MYSQL_USER: devjourney
      MYSQL_PASSWORD: secret

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"

  meilisearch:
    image: getmeili/meilisearch:latest
    ports:
      - "7700:7700"
    volumes:
      - meilisearch_data:/meili_data

  mailpit:
    image: axllent/mailpit
    ports:
      - "8025:8025"
      - "1025:1025"

volumes:
  mysql_data:
  meilisearch_data:
```

### 6.2 Dockerfile

```dockerfile
FROM php:8.4-fpm

# System dependencies
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libjpeg-dev \
    libfreetype6-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip opcache redis

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Node.js (for Vite asset compilation)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader
RUN npm ci && npm run build

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
```

---

## 7. Database Migrations (Key Tables)

```php
// Posts
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('slug')->unique();
    $table->text('excerpt')->nullable();
    $table->longText('body_md');
    $table->longText('body_html');
    $table->foreignId('category_id')->nullable()->constrained();
    $table->string('english_level', 2);       // A1–C2
    $table->string('featured_image')->nullable();
    $table->string('seo_title')->nullable();
    $table->string('seo_description')->nullable();
    $table->unsignedSmallInteger('reading_minutes')->default(0);
    $table->string('status')->default('draft'); // draft, published, archived
    $table->timestamp('published_at')->nullable();
    $table->timestamps();

    $table->index(['status', 'published_at']);
    $table->index('english_level');
});

// Challenges
Schema::create('challenges', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('slug')->unique();
    $table->string('difficulty');               // beginner–expert
    $table->string('topic');                    // system-design, ddd, etc.
    $table->longText('question_md');
    $table->longText('question_html');
    $table->longText('answer_md');
    $table->longText('answer_html');
    $table->longText('explanation_md')->nullable();
    $table->longText('explanation_html')->nullable();
    $table->string('english_level', 2);
    $table->string('status')->default('draft');
    $table->timestamp('published_at')->nullable();
    $table->timestamps();

    $table->index(['status', 'topic', 'difficulty']);
});

// Projects
Schema::create('projects', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('slug')->unique();
    $table->text('description');
    $table->longText('body_md')->nullable();
    $table->longText('body_html')->nullable();
    $table->string('repo_url')->nullable();
    $table->string('demo_url')->nullable();
    $table->json('stack');                     // ["PHP","Laravel","Docker"]
    $table->string('featured_image')->nullable();
    $table->string('status')->default('draft');
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->timestamps();
});

// Milestones
Schema::create('milestones', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('description')->nullable();
    $table->date('achieved_at');
    $table->string('icon')->nullable();
    $table->string('type');                    // english, technical, career, project
    $table->timestamps();
});

// Site Settings (key-value)
Schema::create('site_settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->json('value');
    $table->string('group')->default('general');
    $table->string('type')->default('text');   // text, textarea, boolean, json
    $table->timestamps();
});

// Categories
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->timestamps();
});

// Tags (polymorphic via taggables)
Schema::create('tags', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->timestamps();
});

Schema::create('taggables', function (Blueprint $table) {
    $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
    $table->morphs('taggable');
    $table->primary(['tag_id', 'taggable_id', 'taggable_type']);
});
```

---

## 8. CI/CD Pipeline (GitHub Actions)

### 8.1 Workflow: `.github/workflows/ci.yml`

```yaml
name: CI/CD Pipeline

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - run: composer install --no-progress
      - run: vendor/bin/pint --test        # Laravel Pint (code style)
      - run: vendor/bin/phpstan analyse    # Static analysis

  test:
    runs-on: ubuntu-latest
    needs: lint
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: secret
          MYSQL_DATABASE: devjourney_test
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: xdebug
      - run: composer install --no-progress
      - run: cp .env.testing .env
      - run: php artisan key:generate
      - run: php artisan migrate --force
      - run: php artisan test --coverage --min=70

  build-and-deploy:
    runs-on: ubuntu-latest
    needs: test
    if: github.ref == 'refs/heads/main'
    steps:
      - uses: actions/checkout@v4
      - uses: aws-actions/configure-aws-credentials@v4
        with:
          aws-access-key-id: ${{ secrets.AWS_ACCESS_KEY_ID }}
          aws-secret-access-key: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
          aws-region: eu-west-1
      - uses: aws-actions/amazon-ecr-login@v2
      - name: Build and push Docker image
        run: |
          docker build -t devjourney .
          docker tag devjourney:latest ${{ secrets.ECR_REGISTRY }}/devjourney:latest
          docker push ${{ secrets.ECR_REGISTRY }}/devjourney:latest
      - name: Deploy to ECS
        run: |
          aws ecs update-service \
            --cluster devjourney-cluster \
            --service devjourney-service \
            --force-new-deployment
```

---

## 9. AWS Infrastructure

### 9.1 Services Used

| Service | Purpose |
|---|---|
| ECS Fargate (or EC2) | Run the Laravel app container |
| RDS MySQL 8.x | Managed database |
| ElastiCache Redis | Session, cache, queues |
| S3 | Media storage (images, files) |
| CloudFront | CDN for static assets and media |
| ECR | Docker image registry |
| Route 53 | DNS management |
| ACM | SSL/TLS certificates |
| SES | Transactional email |
| CloudWatch | Logs and monitoring |
| Secrets Manager | Environment secrets |

### 9.2 Cost Optimization (Personal Project)

For initial launch, a cost-effective option:

- **EC2 t3.micro** (free tier eligible) instead of Fargate.
- **RDS db.t3.micro** (free tier eligible).
- **ElastiCache t3.micro** or skip Redis initially and use file/database cache.
- **S3 + CloudFront** — minimal cost at low traffic.
- Estimated monthly cost: **$15–30 USD** during low-traffic phase.

---

## 10. Routes

```php
// Public routes
Route::get('/', [HomeController::class, 'index'])->name('home');

// Journal
Route::get('/journal', [PostController::class, 'index'])->name('posts.index');
Route::get('/journal/{post:slug}', [PostController::class, 'show'])->name('posts.show');
Route::get('/category/{category:slug}', [PostController::class, 'byCategory'])->name('posts.category');
Route::get('/tag/{tag:slug}', [PostController::class, 'byTag'])->name('posts.tag');

// Challenges
Route::get('/challenges', [ChallengeController::class, 'index'])->name('challenges.index');
Route::get('/challenges/{challenge:slug}', [ChallengeController::class, 'show'])->name('challenges.show');

// Projects
Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/{project:slug}', [ProjectController::class, 'show'])->name('projects.show');

// Static pages
Route::get('/about', [AboutController::class, 'index'])->name('about');
Route::get('/now', [AboutController::class, 'now'])->name('now');
Route::get('/progress', [AboutController::class, 'progress'])->name('progress');

// Feeds
Route::get('/feed', [RssController::class, 'index'])->name('feed');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
```

---

## 11. Design Guidelines

### 11.1 Visual Identity

- **Style**: Clean, minimal, developer-focused. Think of it as a modern dev portfolio crossed with a blog.
- **Color palette**: Dark mode by default with light mode toggle. Accent color: a distinctive blue or teal.
- **Typography**: Monospace for code and headings (JetBrains Mono or similar), sans-serif for body (Inter).
- **Layout**: Max width 768px for content (optimal reading width), wider for grids.

### 11.2 Key UI Elements

- **English Level Badge**: A small colored pill next to every piece of content showing the CEFR level (A2 = orange, B1 = yellow, B2 = green, C1 = blue, C2 = purple).
- **Progress indicators**: Visual bars or counters showing total challenges solved, posts written, current streak.
- **Code blocks**: Syntax-highlighted with copy button (Shiki or Torchlight).
- **Challenge reveal**: The answer/solution starts hidden behind a toggle/accordion — encourages the reader to think first.

### 11.3 Responsive

- Mobile-first design.
- Hamburger menu on mobile.
- Cards stack vertically on small screens.
- Code blocks scroll horizontally.

---

## 12. Content Categories (Suggested Initial Set)

| Category | Description |
|---|---|
| Architecture | DDD, hexagonal, CQRS, microservices, event sourcing |
| Backend | PHP, Laravel, Symfony, APIs, databases |
| DevOps | Docker, CI/CD, AWS, Kubernetes, monitoring |
| System Design | Scalability, distributed systems, design interviews |
| Career | Job search insights, interview experiences, career strategy |
| Learning | Study methods, English progress, books, courses |
| Projects | Build logs and postmortems for side projects |

---

## 13. Future Enhancements (v2+)

These are out of scope for v1 but worth planning for:

- **Newsletter**: Email subscription with weekly digest (Mailchimp or self-hosted via Laravel).
- **Comments**: Giscus (GitHub Discussions-based) or custom comments.
- **Analytics dashboard**: Integrate Plausible or Umami (privacy-friendly) and show stats in the admin panel.
- **AI writing assistant**: Use the Claude API to suggest grammar improvements before publishing (leveraging the existing BYOK pattern from FluentForge).
- **Spaced repetition integration**: Export challenges as Anki cards.
- **i18n**: Add Spanish translations for select posts to compare.
- **API**: Public REST or GraphQL API for consuming content programmatically.
- **PWA**: Offline reading support.

---

## 14. Development Phases

### Phase 1 — Foundation (Week 1–2)
- [ ] Laravel project scaffolding with PHP 8.4.
- [ ] Docker Compose setup (app, MySQL, Redis, Mailpit).
- [ ] Database migrations for all core tables.
- [ ] Filament admin panel installation and configuration.
- [ ] Basic auth (single admin user, seeded).
- [ ] Filament CRUD resources: Posts, Challenges, Categories, Tags.

### Phase 2 — Public Site (Week 3–4)
- [ ] Tailwind + Alpine.js setup via Vite.
- [ ] Public layout (header, footer, nav, dark/light mode).
- [ ] Home page with all sections.
- [ ] Journal listing + detail pages with Markdown rendering.
- [ ] Challenge listing + detail pages with solution toggle.
- [ ] Project listing + detail pages.
- [ ] About and Now pages.
- [ ] English level badge component.
- [ ] Reading time calculation.

### Phase 3 — Polish & SEO (Week 5)
- [ ] SEO meta tags (dynamic per page).
- [ ] Structured data (JSON-LD).
- [ ] Sitemap.xml generation.
- [ ] RSS feed.
- [ ] OG images (auto-generated or template-based).
- [ ] Image optimization (WebP, lazy loading).
- [ ] Responsive polish and cross-browser testing.

### Phase 4 — Dashboard Extras (Week 6)
- [ ] Site settings CRUD in Filament.
- [ ] Media library with S3 upload.
- [ ] Milestone management + progress timeline page.
- [ ] Dashboard widgets (stats, calendar, English progression chart).
- [ ] Meilisearch integration for full-text search.

### Phase 5 — CI/CD & Deployment (Week 7)
- [ ] GitHub Actions pipeline (lint, test, build, deploy).
- [ ] AWS infrastructure setup (ECS or EC2, RDS, S3, CloudFront).
- [ ] Production Dockerfile optimization (multi-stage).
- [ ] Environment secrets management.
- [ ] Domain setup + SSL (Route 53 + ACM).
- [ ] Monitoring (CloudWatch logs, health checks).

### Phase 6 — Launch & Content (Week 8)
- [ ] Seed initial content: 3–5 journal posts, 10+ challenges, 3 projects.
- [ ] Write the first "about" and "now" pages.
- [ ] Create initial milestones timeline.
- [ ] Final QA pass.
- [ ] Launch.

---

## 15. Environment Variables

```env
APP_NAME=DevJourney
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://devjourney.dev

DB_CONNECTION=mysql
DB_HOST=devjourney-db.xxxxx.eu-west-1.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=devjourney
DB_USERNAME=devjourney
DB_PASSWORD=

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=devjourney-redis.xxxxx.cache.amazonaws.com

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=eu-west-1
AWS_BUCKET=devjourney-media
AWS_URL=https://cdn.devjourney.dev

MAIL_MAILER=ses

SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=

FILAMENT_PATH=admin
```

---

## 16. Testing Strategy

| Layer | Tool | Coverage Target |
|---|---|---|
| Unit | PHPUnit | Models, Services, Enums — 80% |
| Feature | PHPUnit + Laravel HTTP tests | Controllers, routes, middleware — 70% |
| Browser | Laravel Dusk (optional v2) | Critical user flows |
| Static Analysis | PHPStan (level 6+) | All app code |
| Code Style | Laravel Pint | PSR-12 + Laravel preset |

Key test scenarios:
- Publishing a post transitions it from draft to published and sets `published_at`.
- Slug generation handles duplicates.
- Only published content appears on public routes.
- Challenge solution is hidden by default in the HTML response.
- English level badge renders correctly for each CEFR level.
- SEO meta tags are present and correct on every public page.
- RSS feed validates against the RSS 2.0 spec.
- Admin routes return 403 for unauthenticated users.

---

## 17. Coding Standards

- Follow **PSR-12** coding standard enforced by Laravel Pint.
- Use **PHPStan level 6** minimum for static analysis.
- Write **strict types** (`declare(strict_types=1)`) in all PHP files.
- Use **PHP 8.4 features**: readonly properties, enums, match expressions, named arguments, first-class callables.
- All Eloquent queries should use **eager loading** to avoid N+1 problems.
- Markdown is stored raw (`body_md`) and pre-rendered to HTML (`body_html`) on save — never render at request time.
- All public routes must be **cacheable** (no user-specific data in responses).
- Use **Laravel's built-in features** first (caching, queues, notifications, events) before reaching for external packages.
- Commit messages follow **Conventional Commits** format.

---

## 18. Security

- Single admin user — no registration endpoint.
- Rate limiting on login route.
- CSRF protection on all forms.
- Content Security Policy headers.
- S3 bucket is private; serve media through signed URLs or CloudFront.
- Keep dependencies updated (Dependabot enabled on GitHub).
- All secrets in AWS Secrets Manager or GitHub Secrets — never in code.

---

*Document version: 1.0 — August 2026*
*Stack: Laravel 11+ · PHP 8.4 · MySQL 8 · Docker · AWS · GitHub Actions · Filament v3 · Tailwind CSS · Alpine.js*
