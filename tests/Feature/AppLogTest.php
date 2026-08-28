<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AppLog;
use App\Models\Post;
use App\Models\User;
use App\Services\LogService;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AppLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_logging_writes_to_the_database(): void
    {
        Log::info('a database log message', ['key' => 'value']);

        $this->assertDatabaseCount('app_logs', 1);
        $this->assertDatabaseHas('app_logs', [
            'level' => 'info',
            'message' => 'a database log message',
        ]);
    }

    public function test_exception_logging_captures_the_exception(): void
    {
        $this->app->make(LogService::class)->exception(
            new \RuntimeException('boom'),
            'something failed',
        );

        $this->assertDatabaseHas('app_logs', ['level' => 'error', 'exception' => \RuntimeException::class]);
    }

    public function test_audit_fires_when_content_is_saved(): void
    {
        Post::factory()->create(['title' => 'Audited Post']);

        $this->assertDatabaseHas('app_logs', [
            'level' => 'info',
            'message' => 'audit: content.saved',
        ]);
    }

    public function test_the_log_viewer_pages_render(): void
    {
        $setting = AppLog::create([
            'level' => 'error',
            'message' => 'Something went wrong',
            'context' => ['key' => 'value'],
            'exception' => \RuntimeException::class,
            'trace' => 'stack trace',
            'url' => 'https://example.com/foo',
            'method' => 'GET',
        ]);

        $this->withAdmin()->get('/admin/app-logs')->assertOk()->assertSee('Something went wrong');
        $this->withAdmin()->get("/admin/app-logs/{$setting->id}")->assertOk();
    }

    public function test_login_event_is_audited(): void
    {
        $user = User::factory()->create();

        event(new Login('web', $user, false));

        $this->assertDatabaseHas('app_logs', ['message' => 'audit: auth.login']);
    }

    private ?User $admin = null;

    private function withAdmin()
    {
        $email = 'admin@devjourney.test';
        config(['app.filament_admin_email' => $email]);

        $this->admin ??= User::factory()->create(['email' => $email]);

        return $this->actingAs($this->admin);
    }
}
