<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ChallengeDifficulty;
use App\Enums\ChallengeTopic;
use App\Enums\EnglishLevel;
use App\Enums\MilestoneType;
use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Challenge;
use App\Models\Milestone;
use App\Models\Post;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use App\Services\SiteSettingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdmin();
        $this->seedSettings();
        $categories = $this->seedCategories();
        $tags = $this->seedTags();
        $this->seedPosts($categories, $tags);
        $this->seedChallenges($tags);
        $this->seedProjects();
        $this->seedMilestones();
    }

    private function seedAdmin(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@devjourney.test'],
            [
                'name' => 'DevJourney Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
    }

    private function seedSettings(): void
    {
        $settings = app(SiteSettingService::class);

        $settings->set('current_english_level', 'B1', 'progress');
        $settings->set('current_week', '24', 'progress');
        $settings->set('current_focus', 'Deep in system design and Domain-Driven Design — solving one challenge a day and writing it all in English.', 'progress', 'textarea');
        $settings->set('hero_title', 'Building in public, one commit at a time.', 'home', 'textarea');
        $settings->set('hero_tagline', 'A senior backend developer documenting the climb toward architect roles — and the English journey from A2 to professional, written entirely in English.', 'home', 'textarea');
        $settings->set('author_name', 'DevJourney', 'general');
        $settings->set('now_content', "Right now I'm:\n\n- Practicing **system design** interviews weekly.\n- Reading *Implementing Domain-Driven Design*.\n- Pushing my English toward **B2** — every post here is a rep.\n- Shipping small Laravel side projects to keep the fundamentals sharp.", 'now', 'textarea');
        $settings->set('now_updated_at', now()->toDateString(), 'now');
        $settings->set('social_github', 'https://github.com/PortilloDev', 'social');
        $settings->set('social_linkedin', 'https://www.linkedin.com/in/ivan-portillo-perez/', 'social');
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $definitions = [
            'Architecture' => 'DDD, hexagonal, CQRS, microservices, event sourcing.',
            'Backend' => 'PHP, Laravel, Symfony, APIs, databases.',
            'DevOps' => 'Docker, CI/CD, AWS, Kubernetes, monitoring.',
            'System Design' => 'Scalability, distributed systems, design interviews.',
            'Career' => 'Job search insights, interview experiences, career strategy.',
            'Learning' => 'Study methods, English progress, books, courses.',
        ];

        $categories = [];
        $order = 0;
        foreach ($definitions as $name => $description) {
            $categories[$name] = Category::updateOrCreate(
                ['slug' => str($name)->slug()->toString()],
                ['name' => $name, 'description' => $description, 'sort_order' => $order++],
            );
        }

        return $categories;
    }

    /**
     * @return array<string, Tag>
     */
    private function seedTags(): array
    {
        $names = ['php', 'laravel', 'ddd', 'cqrs', 'hexagonal', 'aws', 'docker', 'testing', 'english', 'interviews'];

        $tags = [];
        foreach ($names as $name) {
            $tags[$name] = Tag::updateOrCreate(['slug' => $name], ['name' => $name]);
        }

        return $tags;
    }

    /**
     * @param  array<string, Category>  $categories
     * @param  array<string, Tag>  $tags
     */
    private function seedPosts(array $categories, array $tags): void
    {
        $posts = [
            [
                'title' => 'Why I started writing everything in English',
                'category' => 'Learning',
                'level' => EnglishLevel::B1,
                'featured' => true,
                'tags' => ['english'],
                'excerpt' => 'Making my language progression part of the story — and why building in public keeps me accountable.',
                'body' => "## The premise\n\nI'm a senior backend developer, but my English has always lagged behind my code. So I made a rule: **write everything in English**, in public.\n\n## The plan\n\n- One journal entry a week.\n- One technical challenge a day.\n- Track my CEFR level over time.\n\n```php\nfunction commit(string \$habit): void\n{\n    // small reps, every day\n}\n```\n\n## What I expect\n\nProgress is compounding. In a year, both the code and the prose should look very different.",
            ],
            [
                'title' => 'Hexagonal architecture in a Laravel app, pragmatically',
                'category' => 'Architecture',
                'level' => EnglishLevel::B1,
                'featured' => true,
                'tags' => ['laravel', 'hexagonal', 'ddd'],
                'excerpt' => 'Ports and adapters without fighting the framework — where the boundaries actually pay off.',
                'body' => "## The goal\n\nKeep the domain independent from Laravel, but don't cargo-cult every port.\n\n## Ports and adapters\n\nThe domain defines interfaces (ports); infrastructure implements them (adapters).\n\n```php\ninterface PostRepository\n{\n    public function save(Post \$post): void;\n}\n```\n\n## Where it pays off\n\nTesting the domain becomes trivial, and swapping infrastructure is a local change.",
            ],
            [
                'title' => 'Reading for a system design interview: my weekly loop',
                'category' => 'System Design',
                'level' => EnglishLevel::B2,
                'featured' => false,
                'tags' => ['interviews'],
                'excerpt' => 'A repeatable weekly routine for turning scattered study into interview-ready intuition.',
                'body' => "## Monday: fundamentals\n\nCaching, queues, partitioning — one topic, deep.\n\n## Midweek: a mock\n\nDesign something concrete (a URL shortener, a feed) end to end.\n\n## Friday: write-up\n\nI publish what I learned here. Teaching forces clarity.",
            ],
        ];

        foreach ($posts as $i => $data) {
            $post = Post::updateOrCreate(
                ['slug' => str($data['title'])->slug()->toString()],
                [
                    'title' => $data['title'],
                    'excerpt' => $data['excerpt'],
                    'body_md' => $data['body'],
                    'category_id' => $categories[$data['category']]->id,
                    'english_level' => $data['level'],
                    'status' => PostStatus::Published,
                    'featured' => $data['featured'] ?? false,
                    'published_at' => now()->subDays(($i + 1) * 5),
                ],
            );

            $post->tags()->sync(collect($data['tags'])->map(fn ($t) => $tags[$t]->id)->all());
        }
    }

    /**
     * @param  array<string, Tag>  $tags
     */
    private function seedChallenges(array $tags): void
    {
        $challenges = [
            [
                'title' => 'Design a URL shortener',
                'topic' => ChallengeTopic::SystemDesign,
                'difficulty' => ChallengeDifficulty::Intermediate,
                'level' => EnglishLevel::B2,
                'tags' => ['interviews'],
                'question' => 'Design a URL shortener like bit.ly. Consider: unique short codes, redirection latency, and analytics at scale.',
                'answer' => "Use a **base62 encoding** of an auto-increment id or a Snowflake id for the short code.\n\n```text\nid 125 -> base62 -> \"21\"\n```\n\nStore the mapping in a key-value store (Redis) fronted by a relational source of truth. Redirects are a single cache lookup; analytics are written asynchronously to a queue.",
                'explanation' => 'Base62 keeps codes short and collision-free because they map 1:1 to ids. Decoupling analytics via a queue keeps the redirect path fast.',
            ],
            [
                'title' => 'When would you reach for CQRS?',
                'topic' => ChallengeTopic::Cqrs,
                'difficulty' => ChallengeDifficulty::Advanced,
                'level' => EnglishLevel::B1,
                'tags' => ['cqrs', 'ddd'],
                'question' => 'Explain CQRS and describe a concrete scenario where the added complexity is justified.',
                'answer' => '**CQRS** separates the write model (commands) from the read model (queries). Justify it when reads and writes have very different shapes or scaling needs — e.g. a reporting dashboard reading denormalized projections while writes stay normalized and transactional.',
                'explanation' => 'CQRS is not free: two models, eventual consistency, more moving parts. Reach for it only when the asymmetry between reads and writes is real.',
            ],
            [
                'title' => 'Fix the N+1 query',
                'topic' => ChallengeTopic::Databases,
                'difficulty' => ChallengeDifficulty::Beginner,
                'level' => EnglishLevel::A2,
                'tags' => ['laravel', 'php'],
                'question' => "A page lists 100 posts and prints each post's category name, firing 101 queries. How do you fix it?",
                'answer' => "Eager-load the relationship:\n\n```php\nPost::query()->with('category')->get();\n```\n\nNow it's 2 queries regardless of the number of posts.",
                'explanation' => 'Eager loading batches the related lookups into a single `WHERE IN` query, eliminating the per-row query.',
            ],
        ];

        foreach ($challenges as $i => $data) {
            $challenge = Challenge::updateOrCreate(
                ['slug' => str($data['title'])->slug()->toString()],
                [
                    'title' => $data['title'],
                    'topic' => $data['topic'],
                    'difficulty' => $data['difficulty'],
                    'english_level' => $data['level'],
                    'question_md' => $data['question'],
                    'answer_md' => $data['answer'],
                    'explanation_md' => $data['explanation'],
                    'status' => PostStatus::Published,
                    'published_at' => now()->subDays($i + 1),
                ],
            );

            $challenge->tags()->sync(collect($data['tags'])->map(fn ($t) => $tags[$t]->id)->all());
        }
    }

    private function seedProjects(): void
    {
        $projects = [
            [
                'title' => 'DevJourney',
                'description' => 'This very site — a Laravel learning journal and portfolio built in public.',
                'stack' => ['PHP', 'Laravel', 'Filament', 'Tailwind', 'MySQL', 'Docker'],
                'repo' => 'https://github.com',
                'body' => "## Why\n\nI needed a home for the journey and a reason to keep shipping.\n\n## Architecture\n\nServer-rendered Blade + Filament admin, Markdown pre-rendered on save.",
            ],
            [
                'title' => 'TaskQueue',
                'description' => 'A tiny distributed job runner exploring at-least-once delivery and idempotency.',
                'stack' => ['PHP', 'Redis', 'Docker'],
                'repo' => 'https://github.com',
                'body' => "## Lessons learned\n\nIdempotency keys are non-negotiable once you accept at-least-once delivery.",
            ],
        ];

        foreach ($projects as $i => $data) {
            Project::updateOrCreate(
                ['slug' => str($data['title'])->slug()->toString()],
                [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'body_md' => $data['body'],
                    'stack' => $data['stack'],
                    'repo_url' => $data['repo'],
                    'status' => PostStatus::Published,
                    'sort_order' => $i,
                ],
            );
        }
    }

    private function seedMilestones(): void
    {
        $milestones = [
            ['Reached English level A2', MilestoneType::English, '-14 months', 'Placement test confirmed A2.'],
            ['Started the daily challenge habit', MilestoneType::Technical, '-8 months', 'One system-design or backend challenge every day.'],
            ['Reached English level B1', MilestoneType::English, '-4 months', 'Can now write these posts unassisted.'],
            ['Shipped DevJourney v1', MilestoneType::Project, '-1 week', 'Public learning journal and portfolio launched.'],
            ['First architect-level interview', MilestoneType::Career, '-3 days', 'Made it to the system-design round.'],
        ];

        foreach ($milestones as [$title, $type, $offset, $description]) {
            Milestone::updateOrCreate(
                ['title' => $title],
                [
                    'type' => $type,
                    'achieved_at' => now()->modify($offset)->format('Y-m-d'),
                    'description' => $description,
                ],
            );
        }
    }
}
