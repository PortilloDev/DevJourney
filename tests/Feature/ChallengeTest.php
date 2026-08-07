<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ChallengeTopic;
use App\Models\Challenge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChallengeTest extends TestCase
{
    use RefreshDatabase;

    public function test_challenge_solution_is_behind_a_reveal_toggle(): void
    {
        $challenge = Challenge::factory()->create();

        $response = $this->get(route('challenges.show', $challenge));

        $response->assertOk();
        // The solution starts collapsed behind an Alpine toggle (shown = false).
        $response->assertSee('x-data="{ shown: false }"', false);
        $response->assertSee('reveal the solution');
    }

    public function test_challenges_can_be_filtered_by_topic(): void
    {
        $ddd = Challenge::factory()->create(['topic' => ChallengeTopic::Ddd, 'title' => 'DDD Question']);
        $sd = Challenge::factory()->create(['topic' => ChallengeTopic::SystemDesign, 'title' => 'Scaling Question']);

        $response = $this->get(route('challenges.index', ['topic' => 'ddd']));

        $response->assertOk();
        $response->assertSee('DDD Question');
        $response->assertDontSee('Scaling Question');
    }

    public function test_draft_challenges_are_not_reachable(): void
    {
        $draft = Challenge::factory()->draft()->create();

        $this->get(route('challenges.show', $draft))->assertNotFound();
    }
}
