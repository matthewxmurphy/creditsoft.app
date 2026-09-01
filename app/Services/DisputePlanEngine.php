<?php

namespace App\Services;

use App\Models\Client;
use App\Models\DisputeBureauClock;
use App\Models\DisputePlan;
use App\Models\DisputePlanStep;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisputePlanEngine
{
    private const MAILING_ACTIONS = [
        'bureau_dispute',
        'secondary_bureau_dispute',
        'debt_validation',
        'cease_desist',
    ];

    public function __construct(
        protected DisputeModeCatalog $catalog,
        protected AuditTrail $auditTrail,
    ) {}

    /**
     * @param  array<string, mixed>  $settings
     */
    public function enroll(Client $client, array $settings, ?User $user = null): DisputePlan
    {
        $mailingMethod = (string) ($settings['mailing_method'] ?? 'certified');
        $mode = $this->catalog->find((string) $settings['playbook_key'], $mailingMethod);

        if (! $mode) {
            throw ValidationException::withMessages(['playbook_key' => 'Select a supported dispute mode.']);
        }

        return DB::transaction(function () use ($client, $settings, $user, $mode, $mailingMethod): DisputePlan {
            $existing = $client->disputePlans()
                ->whereIn('status', ['active', 'sleeping'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'playbook_key' => 'This client already has an active or sleeping dispute plan.',
                ]);
            }

            $startedAt = now();
            $executionMode = (string) ($settings['execution_mode'] ?? 'review');
            $letterReview = (bool) ($settings['letter_review'] ?? true);
            $budgetCapCents = isset($settings['budget_cap_cents'])
                ? max(0, (int) $settings['budget_cap_cents'])
                : null;

            $plan = $client->disputePlans()->create([
                'playbook_key' => $settings['playbook_key'],
                'playbook_version' => $this->catalog->version(),
                'display_name' => $mode['name'],
                'status' => 'active',
                'execution_mode' => $executionMode,
                'mailing_method' => $mailingMethod,
                'letter_review' => $letterReview,
                'budget_cap_cents' => $budgetCapCents,
                'spent_cents' => 0,
                'current_round' => 1,
                'consent_name' => $settings['consent_name'],
                'consented_at' => $startedAt,
                'consent_payload' => [
                    'accepted' => true,
                    'playbook_key' => $settings['playbook_key'],
                    'playbook_version' => $this->catalog->version(),
                    'execution_mode' => $executionMode,
                    'letter_review' => $letterReview,
                    'mailing_method' => $mailingMethod,
                    'budget_cap_cents' => $budgetCapCents,
                    'ip_address' => $settings['ip_address'] ?? null,
                    'user_agent' => $settings['user_agent'] ?? null,
                ],
                'started_at' => $startedAt,
                'next_report_due_at' => $this->firstReportDueAt($mode, $startedAt),
                'metadata' => [
                    'source' => $settings['source'] ?? 'client_dossier',
                    'enrolled_by_user_id' => $user?->getKey(),
                ],
            ]);

            collect($mode['steps'])->each(function (array $step, int $index) use ($plan, $startedAt, $executionMode, $letterReview): void {
                $isMailing = in_array($step['action_type'], self::MAILING_ACTIONS, true);

                $plan->steps()->create([
                    'step_key' => $step['key'],
                    'sequence' => $index + 1,
                    'round' => max(0, (int) ($step['round'] ?? 1)),
                    'title' => $step['title'],
                    'description' => $step['description'] ?? null,
                    'action_type' => $step['action_type'],
                    'status' => 'pending',
                    'scheduled_for' => $startedAt->copy()->addDays(max(0, (int) ($step['day'] ?? 0))),
                    'estimated_letter_count' => max(0, (int) ($step['letter_count'] ?? 0)),
                    'estimated_cost_cents' => max(0, (int) ($step['estimated_cost_cents'] ?? 0)),
                    'requires_review' => $isMailing && ($letterReview || $executionMode === 'review'),
                    'metadata' => array_filter([
                        'depends_on' => $step['depends_on'] ?? null,
                        'scheduled_day' => max(0, (int) ($step['day'] ?? 0)),
                    ]),
                ]);
            });

            $this->auditTrail->record(
                $user,
                'dispute_plan.enrolled',
                "Started {$plan->display_name} for {$client->display_name} with recorded client consent.",
                $plan,
                [
                    'client_id' => $client->getKey(),
                    'playbook_key' => $plan->playbook_key,
                    'execution_mode' => $plan->execution_mode,
                    'letter_review' => $plan->letter_review,
                    'budget_cap_cents' => $plan->budget_cap_cents,
                ],
            );

            return $plan->load('steps');
        });
    }

    /**
     * @param  array<string, mixed>  $controls
     */
    public function updateControls(DisputePlan $plan, array $controls, ?User $user = null): DisputePlan
    {
        $before = $plan->only(['status', 'execution_mode', 'letter_review', 'mailing_method', 'budget_cap_cents']);
        $updates = [];

        foreach (['status', 'execution_mode', 'mailing_method'] as $field) {
            if (array_key_exists($field, $controls)) {
                $updates[$field] = $controls[$field];
            }
        }

        if (array_key_exists('letter_review', $controls)) {
            $updates['letter_review'] = (bool) $controls['letter_review'];
        }

        if (array_key_exists('budget_cap_cents', $controls)) {
            $updates['budget_cap_cents'] = $controls['budget_cap_cents'] === null
                ? null
                : max(0, (int) $controls['budget_cap_cents']);
        }

        if (($updates['status'] ?? null) === 'sleeping') {
            $updates['paused_at'] = now();
        } elseif (($updates['status'] ?? null) === 'active') {
            $updates['paused_at'] = null;
        }

        $plan->update($updates);

        if (array_key_exists('letter_review', $updates) || array_key_exists('execution_mode', $updates)) {
            $plan->steps()
                ->whereIn('status', ['pending', 'budget_blocked'])
                ->whereIn('action_type', self::MAILING_ACTIONS)
                ->update([
                    'requires_review' => $plan->fresh()->letter_review || $plan->fresh()->execution_mode === 'review',
                ]);
        }

        if (array_key_exists('mailing_method', $updates)) {
            $rate = $this->catalog->mailingRate((string) $updates['mailing_method']);
            $plan->steps()
                ->whereIn('status', ['pending', 'budget_blocked'])
                ->get()
                ->each(fn (DisputePlanStep $step) => $step->update([
                    'estimated_cost_cents' => $step->estimated_letter_count * $rate,
                ]));
        }

        $this->auditTrail->record(
            $user,
            'dispute_plan.controls_updated',
            "Updated {$plan->display_name} controls for {$plan->client->display_name}.",
            $plan,
            ['before' => $before, 'after' => $plan->fresh()->only(array_keys($before))],
        );

        return $plan->fresh(['steps', 'clocks']);
    }

    public function runDue(?DisputePlan $onlyPlan = null): int
    {
        $plans = DisputePlan::query()
            ->with(['client', 'steps'])
            ->where('status', 'active')
            ->when($onlyPlan, fn ($query) => $query->whereKey($onlyPlan->getKey()))
            ->get();
        $queued = 0;

        foreach ($plans as $plan) {
            foreach ($plan->steps->whereIn('status', ['pending', 'budget_blocked'])->sortBy('sequence') as $step) {
                if ($step->scheduled_for->isFuture() || ! $this->dependencySatisfied($plan, $step)) {
                    continue;
                }

                if ($step->estimated_cost_cents > 0 && ! $this->fitsBudget($plan, $step)) {
                    $step->update(['status' => 'budget_blocked']);
                    $this->ensureTask($plan, $step, 'Budget approval required', 'high');

                    continue;
                }

                $status = $step->requires_review ? 'review_ready' : 'queued';
                $step->update(['status' => $status, 'queued_at' => now()]);
                $this->ensureTask(
                    $plan,
                    $step,
                    $step->requires_review ? 'Review before release' : 'Automation queue ready',
                    $step->requires_review ? 'high' : 'normal',
                );
                $queued++;
            }
        }

        $queued += $this->processDueClocks($onlyPlan);

        return $queued;
    }

    public function completeStep(DisputePlan $plan, DisputePlanStep $step, ?User $user = null, ?int $actualCostCents = null): DisputePlanStep
    {
        abort_unless($step->dispute_plan_id === $plan->getKey(), 404);

        return DB::transaction(function () use ($plan, $step, $user, $actualCostCents): DisputePlanStep {
            $lockedPlan = DisputePlan::query()->lockForUpdate()->findOrFail($plan->getKey());
            $lockedStep = DisputePlanStep::query()->lockForUpdate()->findOrFail($step->getKey());

            if ($lockedStep->status === 'completed') {
                return $lockedStep;
            }

            $actual = $actualCostCents ?? $lockedStep->estimated_cost_cents;
            $actual = max(0, $actual);

            if ($actual > 0 && $lockedPlan->budget_cap_cents !== null && $lockedPlan->spent_cents + $actual > $lockedPlan->budget_cap_cents) {
                throw ValidationException::withMessages([
                    'actual_cost' => 'Completing this step would exceed the client budget cap.',
                ]);
            }

            $completedAt = now();
            $lockedStep->update([
                'status' => 'completed',
                'completed_at' => $completedAt,
                'actual_cost_cents' => $actual,
            ]);
            $lockedPlan->increment('spent_cents', $actual);
            $lockedPlan->update(['current_round' => max($lockedPlan->current_round, $lockedStep->round)]);

            if ($lockedStep->action_type === 'bureau_dispute') {
                $this->startBureauClocks($lockedPlan, $lockedStep, $completedAt);
            }

            if ($lockedStep->action_type === 'report_reimport') {
                $this->continueFortyFiveDayLoop($lockedPlan, $lockedStep, $completedAt);
            }

            Task::query()
                ->where('source', 'dispute_plan')
                ->where('client_id', $lockedPlan->client_id)
                ->where('title', $this->taskTitle($lockedPlan, $lockedStep))
                ->whereNotIn('status', ['complete', 'completed', 'done'])
                ->update(['status' => 'completed']);

            $this->auditTrail->record(
                $user,
                'dispute_plan.step_completed',
                "Completed {$lockedStep->title} for {$lockedPlan->client->display_name}.",
                $lockedStep,
                ['plan_id' => $lockedPlan->getKey(), 'actual_cost_cents' => $actual],
            );

            return $lockedStep->fresh();
        });
    }

    public function recordClockResult(DisputeBureauClock $clock, string $result, ?User $user = null): DisputeBureauClock
    {
        $status = $result === 'response_received' ? 'responded' : 'verified';
        $clock->update([
            'status' => $status,
            'responded_at' => $result === 'response_received' ? now() : $clock->responded_at,
            'metadata' => [...($clock->metadata ?? []), 'result' => $result, 'verified_at' => now()->toIso8601String()],
        ]);

        $this->auditTrail->record(
            $user,
            'dispute_plan.clock_recorded',
            "Recorded {$clock->bureau} {$clock->clock_type} result.",
            $clock,
            ['result' => $result, 'plan_id' => $clock->dispute_plan_id],
        );

        return $clock->fresh();
    }

    /**
     * @param  array<string, mixed>  $mode
     */
    protected function firstReportDueAt(array $mode, CarbonInterface $startedAt): ?CarbonInterface
    {
        $day = collect($mode['steps'] ?? [])->firstWhere('action_type', 'report_reimport')['day'] ?? null;

        return $day === null ? null : $startedAt->copy()->addDays((int) $day);
    }

    protected function dependencySatisfied(DisputePlan $plan, DisputePlanStep $step): bool
    {
        $dependency = data_get($step->metadata, 'depends_on');

        return ! $dependency || $plan->steps->firstWhere('step_key', $dependency)?->status === 'completed';
    }

    protected function fitsBudget(DisputePlan $plan, DisputePlanStep $candidate): bool
    {
        if ($plan->budget_cap_cents === null) {
            return false;
        }

        $committed = $plan->steps
            ->whereIn('status', ['review_ready', 'queued'])
            ->where('id', '!=', $candidate->getKey())
            ->sum('estimated_cost_cents');

        return $plan->spent_cents + $committed + $candidate->estimated_cost_cents <= $plan->budget_cap_cents;
    }

    protected function ensureTask(DisputePlan $plan, DisputePlanStep $step, string $instruction, string $priority): void
    {
        Task::query()->firstOrCreate([
            'client_id' => $plan->client_id,
            'title' => $this->taskTitle($plan, $step),
            'source' => 'dispute_plan',
        ], [
            'assigned_to' => $plan->client->assigned_to,
            'details' => "{$instruction}. {$step->title}. Estimated mailing budget: $".number_format($step->estimated_cost_cents / 100, 2).'.',
            'status' => 'open',
            'priority' => $priority,
            'due_at' => $step->scheduled_for,
        ]);
    }

    protected function taskTitle(DisputePlan $plan, DisputePlanStep $step): string
    {
        return "[{$plan->display_name}] {$step->title}";
    }

    protected function startBureauClocks(DisputePlan $plan, DisputePlanStep $step, CarbonInterface $sentAt): void
    {
        foreach (['experian', 'transunion', 'equifax'] as $bureau) {
            foreach (['remarks' => 9, 'response' => 30] as $clockType => $days) {
                DisputeBureauClock::query()->firstOrCreate([
                    'dispute_plan_step_id' => $step->getKey(),
                    'bureau' => $bureau,
                    'clock_type' => $clockType,
                ], [
                    'dispute_plan_id' => $plan->getKey(),
                    'status' => 'running',
                    'sent_at' => $sentAt,
                    'due_at' => $sentAt->copy()->addDays($days),
                    'metadata' => ['source' => 'completed_bureau_dispute_step'],
                ]);
            }
        }
    }

    protected function processDueClocks(?DisputePlan $onlyPlan = null): int
    {
        $clocks = DisputeBureauClock::query()
            ->with(['plan.client', 'step'])
            ->where('status', 'running')
            ->where('due_at', '<=', now())
            ->when($onlyPlan, fn ($query) => $query->where('dispute_plan_id', $onlyPlan->getKey()))
            ->get();
        $processed = 0;

        foreach ($clocks as $clock) {
            if ($clock->plan->status !== 'active') {
                continue;
            }

            $clock->update(['status' => 'overdue', 'flagged_at' => now()]);
            $title = $clock->clock_type === 'remarks'
                ? "Verify {$clock->bureau} Day 9 dispute remarks"
                : "Review {$clock->bureau} bureau response deadline";
            $details = $clock->clock_type === 'remarks'
                ? 'The Day 9 check is due. Verify the current report evidence before alleging a missing dispute remark or releasing a demand letter.'
                : 'No response has been logged. Verify delivery, response receipt, and the applicable deadline before releasing a leverage letter.';

            Task::query()->firstOrCreate([
                'client_id' => $clock->plan->client_id,
                'title' => "[{$clock->plan->display_name}] {$title}",
                'source' => 'dispute_clock',
            ], [
                'assigned_to' => $clock->plan->client->assigned_to,
                'details' => $details,
                'status' => 'open',
                'priority' => 'high',
                'due_at' => $clock->due_at,
            ]);
            $processed++;
        }

        return $processed;
    }

    protected function continueFortyFiveDayLoop(DisputePlan $plan, DisputePlanStep $completedStep, CarbonInterface $completedAt): void
    {
        $nextRound = max(1, $completedStep->round + 1);
        $rate = $this->catalog->mailingRate($plan->mailing_method);
        $nextDisputeKey = "round_{$nextRound}_unresolved";
        $nextDispute = $plan->steps()->firstOrCreate([
            'step_key' => $nextDisputeKey,
        ], [
            'sequence' => ((int) $plan->steps()->max('sequence')) + 1,
            'round' => $nextRound,
            'title' => "Prepare Round {$nextRound} for unresolved reporting",
            'action_type' => 'bureau_dispute',
            'status' => 'pending',
            'scheduled_for' => $completedAt->copy()->addDay(),
            'estimated_letter_count' => max(1, $plan->steps()->where('action_type', 'bureau_dispute')->latest('round')->value('estimated_letter_count') ?? 6),
            'estimated_cost_cents' => 0,
            'requires_review' => $plan->letter_review || $plan->execution_mode === 'review',
            'metadata' => ['depends_on' => $completedStep->step_key, 'generated_by' => '45_day_loop'],
        ]);

        if ($nextDispute->estimated_cost_cents === 0) {
            $nextDispute->update(['estimated_cost_cents' => $nextDispute->estimated_letter_count * $rate]);
        }

        $reportKey = "round_{$nextRound}_report_refresh";
        $report = $plan->steps()->firstOrCreate([
            'step_key' => $reportKey,
        ], [
            'sequence' => ((int) $plan->steps()->max('sequence')) + 1,
            'round' => $nextRound,
            'title' => "Reimport report and log Round {$nextRound} outcomes",
            'action_type' => 'report_reimport',
            'status' => 'pending',
            'scheduled_for' => $completedAt->copy()->addDays(45),
            'estimated_letter_count' => 0,
            'estimated_cost_cents' => 0,
            'requires_review' => false,
            'metadata' => ['depends_on' => $nextDisputeKey, 'generated_by' => '45_day_loop'],
        ]);

        $plan->update([
            'current_round' => $nextRound,
            'last_report_imported_at' => $completedAt,
            'next_report_due_at' => $report->scheduled_for,
        ]);
    }
}
