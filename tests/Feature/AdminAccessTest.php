<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_admin_panel(): void
    {
        $this->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_login_page_is_reachable(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_authenticated_users_can_open_the_dashboard(): void
    {
        // The panel is restricted to the allow-listed admin email (see
        // User::canAccessPanel()), so create that exact admin account.
        $adminEmail = 'admin@email.test';
        config(['app.filament_admin_email' => $adminEmail]);

        $user = User::factory()->create(['email' => $adminEmail]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }
}
