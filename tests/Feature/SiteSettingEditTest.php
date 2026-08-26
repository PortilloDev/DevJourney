<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSettingEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_shows_the_stored_value_not_true(): void
    {
        $setting = SiteSetting::create([
            'key' => 'hero_tagline',
            'value' => 'A senior backend developer documenting the climb.',
            'group' => 'home',
            'type' => 'textarea',
        ]);

        $this->withAdmin()
            ->get("/admin/site-settings/{$setting->id}/edit")
            ->assertOk()
            ->assertSee('documenting the climb.', false)
            ->assertDontSee('>true<', false);
    }

    public function test_edit_renders_for_json_value_without_error(): void
    {
        $setting = SiteSetting::create([
            'key' => 'some_json',
            'value' => ['a' => 1, 'b' => 2],
            'group' => 'general',
            'type' => 'json',
        ]);

        $this->withAdmin()
            ->get("/admin/site-settings/{$setting->id}/edit")
            ->assertOk()
            ->assertDontSee('>true<', false);
    }

    private function withAdmin()
    {
        $email = 'admin@devjourney.test';
        config(['app.filament_admin_email' => $email]);

        return $this->actingAs(User::factory()->create(['email' => $email]));
    }
}
