<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Visit;
use App\Services\VisitTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisitTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_page_view_creates_a_visit_and_event_and_sets_the_cookie(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertCookie(VisitTracker::COOKIE);

        $this->assertDatabaseCount('visits', 1);
        $this->assertDatabaseCount('visit_events', 1);
        $this->assertDatabaseHas('visit_events', [
            'type' => 'page_view',
            'page_type' => 'home',
            'path' => '/',
        ]);
    }

    public function test_a_continuing_session_reuses_the_same_visit(): void
    {
        $token = (string) Str::uuid();

        $this->withCookie(VisitTracker::COOKIE, $token)->get('/')->assertOk();
        $this->withCookie(VisitTracker::COOKIE, $token)->get('/journal')->assertOk();

        $this->assertDatabaseCount('visits', 1);
        $this->assertDatabaseCount('visit_events', 2);
        $this->assertDatabaseHas('visits', [
            'visitor_token' => $token,
            'page_views' => 2,
        ]);
        $this->assertDatabaseHas('visit_events', [
            'type' => 'page_view',
            'page_type' => 'journal',
        ]);
    }

    public function test_heartbeat_keeps_the_session_alive(): void
    {
        $token = (string) Str::uuid();

        $this->withCookie(VisitTracker::COOKIE, $token)->get('/')->assertOk();

        $visit = Visit::query()->firstOrFail();

        // Force a drift so the heartbeat visibly extends the session.
        $drifted = now()->subMinute();
        $visit->forceFill(['last_activity_at' => $drifted])->save();

        $this->withCookie(VisitTracker::COOKIE, $token)
            ->post('/track/heartbeat')
            ->assertNoContent();

        $visit->refresh();

        $this->assertGreaterThan($drifted, $visit->last_activity_at);
        $this->assertDatabaseCount('visit_events', 1);
    }

    public function test_a_new_session_begins_after_the_timeout_window(): void
    {
        $token = (string) Str::uuid();

        $visit = Visit::create([
            'visitor_token' => $token,
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Test)',
            'device' => 'desktop',
            'entry_url' => '/',
            'entry_path' => '/',
            'entry_page_type' => 'home',
            'page_views' => 1,
            'started_at' => now()->subHours(2),
            'last_activity_at' => now()->subHours(2),
        ]);

        $this->withCookie(VisitTracker::COOKIE, $token)->get('/')->assertOk();

        $this->assertDatabaseCount('visits', 2);
        $this->assertNotSame($visit->id, Visit::query()->latest('id')->value('id'));
    }
}
