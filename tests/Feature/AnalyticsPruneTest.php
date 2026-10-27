<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ActivityEventType;
use App\Models\Visit;
use App\Models\VisitEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsPruneTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function visit(array $overrides = []): Visit
    {
        return Visit::create(array_merge([
            'visitor_token' => (string) Str::uuid(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Test)',
            'device' => 'desktop',
            'entry_url' => '/',
            'entry_path' => '/',
            'entry_page_type' => 'home',
            'page_views' => 1,
            'started_at' => now(),
            'last_activity_at' => now(),
        ], $overrides));
    }

    private function pageView(Visit $visit, $createdAt): VisitEvent
    {
        return VisitEvent::forceCreate([
            'visit_id' => $visit->id,
            'type' => ActivityEventType::PageView->value,
            'created_at' => $createdAt,
        ]);
    }

    public function test_old_sessions_are_aggregated_then_deleted(): void
    {
        $old = $this->visit([
            'visitor_token' => 'old-token',
            'started_at' => now()->subDays(100),
            'last_activity_at' => now()->subDays(100),
        ]);
        $this->pageView($old, now()->subDays(100));

        $recent = $this->visit();

        $this->artisan('analytics:prune', ['--days' => 90])->assertSuccessful();

        $day = now()->subDays(100)->toDateString();

        $this->assertDatabaseHas('daily_traffic', [
            'date' => $day,
            'sessions' => 1,
            'views' => 1,
            'unique_visitors' => 1,
        ]);

        $this->assertDatabaseMissing('visits', ['id' => $old->id]);
        $this->assertDatabaseMissing('visit_events', ['visit_id' => $old->id]);
        $this->assertDatabaseHas('visits', ['id' => $recent->id]);
    }

    public function test_prune_is_idempotent(): void
    {
        $old = $this->visit([
            'started_at' => now()->subDays(100),
            'last_activity_at' => now()->subDays(100),
        ]);
        $this->pageView($old, now()->subDays(100));

        $this->artisan('analytics:prune', ['--days' => 90])->assertSuccessful();
        $this->artisan('analytics:prune', ['--days' => 90])->assertSuccessful();

        $this->assertDatabaseCount('daily_traffic', 1);
        $this->assertDatabaseHas('daily_traffic', [
            'date' => now()->subDays(100)->toDateString(),
            'sessions' => 1,
            'views' => 1,
        ]);
    }

    public function test_sessions_with_recent_activity_are_kept(): void
    {
        $lingering = $this->visit([
            'started_at' => now()->subDays(100),
            'last_activity_at' => now()->subDay(),
        ]);

        $this->artisan('analytics:prune', ['--days' => 90])->assertSuccessful();

        $this->assertDatabaseHas('visits', ['id' => $lingering->id]);
    }
}
