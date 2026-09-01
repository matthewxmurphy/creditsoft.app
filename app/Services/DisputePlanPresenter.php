<?php

namespace App\Services;

use App\Models\Client;
use App\Models\DisputePlan;

class DisputePlanPresenter
{
    public function __construct(
        protected DisputeModeCatalog $catalog,
        protected OfficeImpactStatsService $impactStats,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        return [
            'modes' => array_values($this->catalog->modes()),
            'mailing_rates_cents' => config('dispute_modes.mailing_rates_cents', []),
            'notice' => 'Mailing costs are configurable planning estimates. Credit-report and mailing-provider connectors must confirm delivery before the system records a send.',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activeFor(Client $client): ?array
    {
        $plan = $client->disputePlans()
            ->with(['steps', 'clocks'])
            ->whereIn('status', ['active', 'sleeping'])
            ->latest('started_at')
            ->first();

        return $plan ? $this->plan($plan) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function plan(DisputePlan $plan): array
    {
        $plan->loadMissing(['client.reportingCycles.bureauSnapshots.tradelines', 'client.reportingCycles.browserCaptures', 'steps', 'clocks']);
        $completed = $plan->steps->where('status', 'completed')->count();
        $total = $plan->steps->count();
        $impact = $this->impactStats->clientImpact($plan->client);
        $budgetRemaining = $plan->budget_cap_cents === null
            ? null
            : max(0, $plan->budget_cap_cents - $plan->spent_cents);

        return [
            'id' => $plan->getKey(),
            'playbook_key' => $plan->playbook_key,
            'playbook_version' => $plan->playbook_version,
            'display_name' => $plan->display_name,
            'status' => $plan->status,
            'execution_mode' => $plan->execution_mode,
            'mailing_method' => $plan->mailing_method,
            'letter_review' => $plan->letter_review,
            'budget_cap_cents' => $plan->budget_cap_cents,
            'spent_cents' => $plan->spent_cents,
            'budget_remaining_cents' => $budgetRemaining,
            'current_round' => $plan->current_round,
            'consent_name' => $plan->consent_name,
            'consented_at' => optional($plan->consented_at)?->toIso8601String(),
            'started_at' => optional($plan->started_at)?->toIso8601String(),
            'next_report_due_at' => optional($plan->next_report_due_at)?->toIso8601String(),
            'completed_steps' => $completed,
            'total_steps' => $total,
            'progress_percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'deletions' => $impact['negative_items_removed'],
            'debt_removed' => $impact['debt_removed'],
            'progress_text' => "Round {$plan->current_round} of {$plan->display_name}, {$impact['negative_items_removed']} deletion".($impact['negative_items_removed'] === 1 ? '' : 's').' so far.',
            'steps' => $plan->steps->map(fn ($step) => [
                'id' => $step->getKey(),
                'step_key' => $step->step_key,
                'round' => $step->round,
                'title' => $step->title,
                'action_type' => $step->action_type,
                'status' => $step->status,
                'scheduled_for' => optional($step->scheduled_for)?->toIso8601String(),
                'completed_at' => optional($step->completed_at)?->toIso8601String(),
                'estimated_letter_count' => $step->estimated_letter_count,
                'estimated_cost_cents' => $step->estimated_cost_cents,
                'requires_review' => $step->requires_review,
                'depends_on' => data_get($step->metadata, 'depends_on'),
            ])->values()->all(),
            'clocks' => $plan->clocks->map(fn ($clock) => [
                'id' => $clock->getKey(),
                'bureau' => $clock->bureau,
                'clock_type' => $clock->clock_type,
                'status' => $clock->status,
                'sent_at' => optional($clock->sent_at)?->toIso8601String(),
                'due_at' => optional($clock->due_at)?->toIso8601String(),
                'responded_at' => optional($clock->responded_at)?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
