<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\DisputeBureauClock;
use App\Models\DisputePlan;
use App\Models\DisputePlanStep;
use App\Services\DisputeModeCatalog;
use App\Services\DisputePlanEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DisputePlanController extends Controller
{
    public function store(
        Request $request,
        Client $client,
        DisputeModeCatalog $catalog,
        DisputePlanEngine $engine,
    ): RedirectResponse {
        $validated = $request->validate([
            'playbook_key' => ['required', 'string', Rule::in($catalog->keys())],
            'execution_mode' => ['required', 'string', 'in:review,automatic'],
            'mailing_method' => ['required', 'string', 'in:certified,regular'],
            'letter_review' => ['required', 'boolean'],
            'budget_cap' => ['required', 'numeric', 'min:0', 'max:100000'],
            'consent_name' => ['required', 'string', 'max:255'],
            'consent_accepted' => ['accepted'],
        ]);

        $plan = $engine->enroll($client, [
            ...$validated,
            'budget_cap_cents' => (int) round(((float) $validated['budget_cap']) * 100),
            'source' => 'client_dossier',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ], $request->user());

        $engine->runDue($plan);

        return back()->with('success', "{$plan->display_name} started with client consent recorded.");
    }

    public function update(
        Request $request,
        Client $client,
        DisputePlan $disputePlan,
        DisputePlanEngine $engine,
    ): RedirectResponse {
        $this->guardPlan($client, $disputePlan);
        $validated = $request->validate([
            'status' => ['sometimes', 'required', 'string', 'in:active,sleeping'],
            'execution_mode' => ['sometimes', 'required', 'string', 'in:review,automatic'],
            'mailing_method' => ['sometimes', 'required', 'string', 'in:certified,regular'],
            'letter_review' => ['sometimes', 'required', 'boolean'],
            'budget_cap' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        if (array_key_exists('budget_cap', $validated)) {
            $validated['budget_cap_cents'] = $validated['budget_cap'] === null
                ? null
                : (int) round(((float) $validated['budget_cap']) * 100);
            unset($validated['budget_cap']);
        }

        $plan = $engine->updateControls($disputePlan, $validated, $request->user());

        if ($plan->status === 'active') {
            $engine->runDue($plan);
        }

        return back()->with('success', "{$plan->display_name} controls updated.");
    }

    public function run(
        Request $request,
        Client $client,
        DisputePlan $disputePlan,
        DisputePlanEngine $engine,
    ): RedirectResponse {
        $this->guardPlan($client, $disputePlan);
        $count = $engine->runDue($disputePlan);

        return back()->with('success', "Processed {$count} due dispute plan item(s).");
    }

    public function completeStep(
        Request $request,
        Client $client,
        DisputePlan $disputePlan,
        DisputePlanStep $step,
        DisputePlanEngine $engine,
    ): RedirectResponse {
        $this->guardPlan($client, $disputePlan);
        abort_unless($step->dispute_plan_id === $disputePlan->getKey(), 404);
        $validated = $request->validate([
            'actual_cost' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);
        $actualCost = array_key_exists('actual_cost', $validated) && $validated['actual_cost'] !== null
            ? (int) round(((float) $validated['actual_cost']) * 100)
            : null;

        $engine->completeStep($disputePlan, $step, $request->user(), $actualCost);
        $engine->runDue($disputePlan->fresh());

        return back()->with('success', 'Dispute plan step completed.');
    }

    public function recordClock(
        Request $request,
        Client $client,
        DisputePlan $disputePlan,
        DisputeBureauClock $clock,
        DisputePlanEngine $engine,
    ): RedirectResponse {
        $this->guardPlan($client, $disputePlan);
        abort_unless($clock->dispute_plan_id === $disputePlan->getKey(), 404);
        $validated = $request->validate([
            'result' => ['required', 'string', 'in:response_received,remark_present,remark_missing,delivery_unverified'],
        ]);

        $engine->recordClockResult($clock, $validated['result'], $request->user());

        return back()->with('success', 'Bureau clock evidence recorded.');
    }

    protected function guardPlan(Client $client, DisputePlan $plan): void
    {
        abort_unless($plan->client_id === $client->getKey(), 404);
    }
}
