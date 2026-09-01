<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Services\DisputePlanEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DisputePlanEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_nuke_mode_waits_for_identity_scrub_then_starts_bureau_clocks(): void
    {
        Carbon::setTestNow('2026-09-01 09:00:00');
        $client = $this->client();
        $engine = app(DisputePlanEngine::class);
        $plan = $engine->enroll($client, [
            'playbook_key' => 'nuke',
            'execution_mode' => 'automatic',
            'mailing_method' => 'certified',
            'letter_review' => false,
            'budget_cap_cents' => 10000,
            'consent_name' => 'Test Client',
        ]);

        $this->assertCount(6, $plan->steps);
        $this->assertSame(1, $engine->runDue($plan));

        $identityStep = $plan->steps()->where('step_key', 'identity_scrub')->firstOrFail();
        $this->assertSame('queued', $identityStep->fresh()->status);
        $this->assertSame('pending', $plan->steps()->where('step_key', 'round_1_bureau_disputes')->firstOrFail()->status);

        $engine->completeStep($plan, $identityStep);
        Carbon::setTestNow('2026-09-02 09:00:00');
        $engine->runDue($plan->fresh());

        $bureauStep = $plan->steps()->where('step_key', 'round_1_bureau_disputes')->firstOrFail();
        $this->assertSame('queued', $bureauStep->fresh()->status);
        $this->assertSame(6090, $bureauStep->estimated_cost_cents);

        $engine->completeStep($plan->fresh(), $bureauStep);

        $this->assertDatabaseCount('dispute_bureau_clocks', 6);
        $this->assertSame(6090, $plan->fresh()->spent_cents);
    }

    public function test_costed_step_stays_blocked_when_it_exceeds_the_client_cap(): void
    {
        Carbon::setTestNow('2026-09-01 09:00:00');
        $engine = app(DisputePlanEngine::class);
        $plan = $engine->enroll($this->client(), [
            'playbook_key' => 'strategy',
            'execution_mode' => 'review',
            'mailing_method' => 'certified',
            'letter_review' => true,
            'budget_cap_cents' => 1000,
            'consent_name' => 'Budget Client',
        ]);

        $engine->runDue($plan);

        $step = $plan->steps()->where('step_key', 'round_1_bureau_disputes')->firstOrFail();
        $this->assertSame('budget_blocked', $step->fresh()->status);
        $this->assertDatabaseHas('tasks', [
            'client_id' => $plan->client_id,
            'source' => 'dispute_plan',
            'priority' => 'high',
        ]);
    }

    public function test_completing_a_report_refresh_extends_the_forty_five_day_loop(): void
    {
        Carbon::setTestNow('2026-09-01 09:00:00');
        $engine = app(DisputePlanEngine::class);
        $plan = $engine->enroll($this->client(), [
            'playbook_key' => 'strategy',
            'execution_mode' => 'automatic',
            'mailing_method' => 'regular',
            'letter_review' => false,
            'budget_cap_cents' => 100000,
            'consent_name' => 'Loop Client',
        ]);
        $engine->runDue($plan);
        $engine->completeStep($plan, $plan->steps()->where('step_key', 'round_1_bureau_disputes')->firstOrFail());

        Carbon::setTestNow('2026-10-16 09:00:00');
        $engine->runDue($plan->fresh());
        $refresh = $plan->steps()->where('step_key', 'round_1_report_refresh')->firstOrFail();
        $this->assertSame('queued', $refresh->fresh()->status);
        $engine->completeStep($plan->fresh(), $refresh);

        $this->assertDatabaseHas('dispute_plan_steps', [
            'dispute_plan_id' => $plan->getKey(),
            'step_key' => 'round_2_report_refresh',
            'action_type' => 'report_reimport',
        ]);
        $this->assertSame('2026-11-30', $plan->fresh()->next_report_due_at?->toDateString());
    }

    protected function client(): Client
    {
        return Client::query()->create([
            'cuid' => 'c_'.strtolower(fake()->unique()->bothify('??????????')),
            'first_name' => 'Test',
            'last_name' => 'Client',
            'status' => 'active',
        ]);
    }
}
