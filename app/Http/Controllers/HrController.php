<?php

namespace App\Http\Controllers;

use App\Models\AuditEntry;
use App\Models\Client;
use App\Models\EmployeeActivitySample;
use App\Models\EmployeeProfile;
use App\Models\EmployeeReview;
use App\Models\EmployeeWeeklyReport;
use App\Models\Task;
use App\Models\User;
use App\Services\CreditsoftAiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class HrController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();

        abort_unless($actor?->canViewUserDirectory(), 403);

        $users = $this->visibleUsersFor($actor);
        $userIds = $users->pluck('id')->all();
        $activityEntries = $this->activityEntriesFor($userIds);
        $activity = $this->activityFor($activityEntries);
        $activitySamples = $this->activitySamplesFor($userIds);
        $inputActivity = $this->inputActivityFor($activitySamples);
        $activityEntriesByUser = $activityEntries->groupBy('user_id');
        $activitySamplesByUser = $activitySamples->groupBy('user_id');
        $timezone = (string) config('app.timezone', 'America/Los_Angeles');
        $activityDates = collect(range(29, 0))
            ->map(fn (int $daysAgo) => now($timezone)->subDays($daysAgo)->startOfDay())
            ->values();
        $clientCounts = Client::query()
            ->selectRaw('assigned_to, count(*) as aggregate')
            ->whereIn('assigned_to', $userIds)
            ->groupBy('assigned_to')
            ->pluck('aggregate', 'assigned_to');
        $taskCounts = Task::query()
            ->selectRaw('assigned_to, status, count(*) as aggregate')
            ->whereIn('assigned_to', $userIds)
            ->groupBy('assigned_to', 'status')
            ->get()
            ->groupBy('assigned_to');

        $profiles = EmployeeProfile::query()
            ->whereIn('user_id', $userIds)
            ->get()
            ->keyBy('user_id');

        $reviews = EmployeeReview::query()
            ->with(['user', 'reviewer'])
            ->whereIn('user_id', $userIds)
            ->latest()
            ->take(20)
            ->get();

        $weeklyReports = EmployeeWeeklyReport::query()
            ->with(['user', 'generator'])
            ->whereIn('user_id', $userIds)
            ->latest('generated_at')
            ->latest()
            ->take(16)
            ->get();

        $staff = $users
            ->map(function (User $user) use ($activity, $inputActivity, $clientCounts, $taskCounts, $profiles, $activityEntriesByUser, $activitySamplesByUser, $activityDates, $timezone) {
                $userActivity = $activity[$user->id] ?? [
                    'total' => 0,
                    'api' => 0,
                    'ai' => 0,
                    'manual' => 0,
                ];
                $input = $inputActivity[$user->id] ?? [
                    'capture_count' => 0,
                    'active_minutes' => 0,
                    'keypresses' => 0,
                    'clicks' => 0,
                    'mouse_moves' => 0,
                    'scrolls' => 0,
                    'focuses' => 0,
                    'form_submits' => 0,
                ];
                $tasks = $taskCounts->get($user->id, collect());
                $doneTasks = (int) ($tasks->firstWhere('status', 'done')?->aggregate ?? 0);
                $openTasks = (int) $tasks
                    ->filter(fn ($row) => in_array($row->status, ['open', 'in_progress'], true))
                    ->sum('aggregate');
                $assignedClients = (int) ($clientCounts[$user->id] ?? 0);
                $memberActivityEntries = $activityEntriesByUser->get($user->id, collect());
                $memberActivitySamples = $activitySamplesByUser->get($user->id, collect());
                $firstSeenAt = collect([
                    $memberActivityEntries->sortBy('created_at')->first()?->created_at,
                    $memberActivitySamples->sortBy('sampled_at')->first()?->sampled_at,
                    $user->last_seen_at,
                ])
                    ->filter()
                    ->sortBy(fn ($date) => Carbon::parse($date)->getTimestamp())
                    ->first();
                $score = ($userActivity['api'] * 2)
                    + ($userActivity['ai'] * 2)
                    + $userActivity['manual']
                    + ($doneTasks * 5)
                    + $assignedClients
                    + (int) floor($input['active_minutes'] / 10)
                    + (int) floor($input['keypresses'] / 40)
                    + (int) floor($input['clicks'] / 25);

                $profile = $profiles->get($user->id);
                $sourceOwnerIntake = (array) data_get($profile?->metadata, 'source_owner_intake', []);
                $needsSetup = $sourceOwnerIntake !== [] && ! $user->hasWorkspaceAccess();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'gravatar_url' => $user->gravatar_url,
                    'role_label' => $user->primaryRoleLabel(),
                    'roles' => $user->assignedRoleLabels(),
                    'manager_name' => $user->manager?->name,
                    'first_seen_at' => $firstSeenAt ? Carbon::parse($firstSeenAt)->toIso8601String() : null,
                    'last_seen_at' => optional($user->last_seen_at)?->toIso8601String(),
                    'assigned_clients' => $assignedClients,
                    'open_tasks' => $openTasks,
                    'done_tasks' => $doneTasks,
                    'needs_setup' => $needsSetup,
                    'setup_note' => $needsSetup
                        ? 'Finish email, role, payroll, and login setup before assigning clients.'
                        : null,
                    'source_owner_name' => $sourceOwnerIntake['source_name'] ?? null,
                    'activity_daily' => $this->dailyActivityFor($memberActivityEntries, $memberActivitySamples, $activityDates, $timezone),
                    'activity' => [
                        ...$userActivity,
                        ...$input,
                        'score' => $score,
                    ],
                    'profile' => $this->profilePayload($profile),
                ];
            })
            ->sortByDesc(fn (array $user) => $user['activity']['score'])
            ->values();

        return Inertia::render('hr/Index', [
            'can_manage_hr' => $actor->canManageUsers(),
            'summary' => [
                'staff' => $staff->count(),
                'open_reviews' => EmployeeReview::query()->whereIn('user_id', $userIds)->where('status', 'open')->count(),
                'write_ups' => EmployeeReview::query()->whereIn('user_id', $userIds)->where('review_type', 'write_up')->count(),
                'onboarding_active' => $profiles
                    ->filter(fn (EmployeeProfile $profile) => in_array($profile->onboarding_status, ['invited', 'active'], true))
                    ->count(),
                'api_actions' => $staff->sum('activity.api'),
                'ai_actions' => $staff->sum('activity.ai'),
                'manual_actions' => $staff->sum('activity.manual'),
                'active_minutes' => $staff->sum('activity.active_minutes'),
                'keypresses' => $staff->sum('activity.keypresses'),
                'clicks' => $staff->sum('activity.clicks'),
            ],
            'staff' => $staff,
            'activity_charts' => $this->activityCharts($users, $activityEntries),
            'weekly_reports' => $weeklyReports->map(fn (EmployeeWeeklyReport $report) => [
                'id' => $report->id,
                'employee_name' => $report->user?->name,
                'generated_by_name' => $report->generator?->name,
                'period_start' => optional($report->period_start)?->toDateString(),
                'period_end' => optional($report->period_end)?->toDateString(),
                'title' => $report->title,
                'summary' => $report->summary,
                'strengths' => $report->strengths ?? [],
                'risks' => $report->risks ?? [],
                'coaching_notes' => $report->coaching_notes,
                'next_week_focus' => $report->next_week_focus ?? [],
                'ai_provider' => $report->ai_provider,
                'ai_model' => $report->ai_model,
                'status' => $report->status,
                'generated_at' => optional($report->generated_at ?? $report->updated_at)?->toIso8601String(),
            ]),
            'current_week' => [
                'period_start' => now()->startOfWeek()->toDateString(),
                'period_end' => now()->endOfWeek()->toDateString(),
            ],
            'reviews' => $reviews->map(fn (EmployeeReview $review) => [
                'id' => $review->id,
                'employee_name' => $review->user?->name,
                'reviewer_name' => $review->reviewer?->name,
                'review_type' => $review->review_type,
                'title' => $review->title,
                'body' => $review->body,
                'rating' => $review->rating,
                'status' => $review->status,
                'occurred_on' => optional($review->occurred_on)?->toDateString(),
                'due_on' => optional($review->due_on)?->toDateString(),
                'created_at' => optional($review->created_at)?->toIso8601String(),
            ]),
            'employee_options' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values(),
        ]);
    }

    public function storeProfile(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canManageUsers(), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:80'],
            'department' => ['nullable', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:120'],
            'employment_type' => ['nullable', 'string', 'max:80'],
            'timezone' => ['nullable', 'string', 'max:80'],
            'onboarding_status' => ['required', Rule::in(['not_started', 'invited', 'active', 'complete', 'paused'])],
            'onboarding_started_at' => ['nullable', 'date'],
            'onboarding_completed_at' => ['nullable', 'date'],
            'pay_method' => ['nullable', 'string', 'max:80'],
            'pay_destination' => ['nullable', 'string', 'max:255'],
            'pay_currency' => ['required', 'string', 'size:3'],
            'payroll_notes' => ['nullable', 'string', 'max:4000'],
        ]);

        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $validated['user_id']],
            collect($validated)
                ->except('user_id')
                ->map(fn ($value) => $value === '' ? null : $value)
                ->all(),
        );

        return back()->with('status', 'Employee profile updated.');
    }

    public function storeReview(Request $request): RedirectResponse
    {
        $actor = $request->user();

        abort_unless($actor?->canViewUserDirectory(), 403);

        $visibleIds = $this->visibleUsersFor($actor)->pluck('id')->all();
        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::in($visibleIds)],
            'review_type' => ['required', Rule::in(['review', 'write_up', 'coaching', 'onboarding'])],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:8000'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'status' => ['required', Rule::in(['open', 'acknowledged', 'closed'])],
            'occurred_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date'],
        ]);

        EmployeeReview::query()->create([
            ...collect($validated)->map(fn ($value) => $value === '' ? null : $value)->all(),
            'reviewer_id' => $actor->id,
        ]);

        return back()->with('status', 'HR note saved.');
    }

    public function storeActivityCapture(Request $request): \Illuminate\Http\JsonResponse
    {
        $actor = $request->user();

        abort_unless($actor, 403);

        $validated = $request->validate([
            'captured_at' => ['nullable', 'date'],
            'sampled_at' => ['nullable', 'date'],
            'route_path' => ['nullable', 'string', 'max:255'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'session_uuid' => ['nullable', 'string', 'max:64'],
            'active_ms' => ['nullable', 'integer', 'min:0', 'max:300000'],
            'keypress_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'click_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'mouse_move_count' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'scroll_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'focus_count' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'form_submit_count' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'metadata' => ['nullable', 'array'],
            'metadata.visibility' => ['nullable', 'string', 'max:40'],
        ]);

        $counts = collect([
            'active_ms',
            'keypress_count',
            'click_count',
            'mouse_move_count',
            'scroll_count',
            'focus_count',
            'form_submit_count',
        ])->sum(fn (string $key): int => (int) ($validated[$key] ?? 0));

        if ($counts <= 0) {
            return response()->json(['ok' => true, 'stored' => false]);
        }

        EmployeeActivitySample::query()->create([
            'user_id' => $actor->id,
            'sampled_at' => isset($validated['captured_at']) || isset($validated['sampled_at'])
                ? Carbon::parse((string) ($validated['captured_at'] ?? $validated['sampled_at']))
                : now(),
            'route_path' => $this->cleanRoutePath($validated['route_path'] ?? null),
            'page_title' => isset($validated['page_title']) ? str($validated['page_title'])->limit(120)->value() : null,
            'session_uuid' => isset($validated['session_uuid']) ? str($validated['session_uuid'])->limit(64, '')->value() : null,
            'active_ms' => (int) ($validated['active_ms'] ?? 0),
            'keypress_count' => (int) ($validated['keypress_count'] ?? 0),
            'click_count' => (int) ($validated['click_count'] ?? 0),
            'mouse_move_count' => (int) ($validated['mouse_move_count'] ?? 0),
            'scroll_count' => (int) ($validated['scroll_count'] ?? 0),
            'focus_count' => (int) ($validated['focus_count'] ?? 0),
            'form_submit_count' => (int) ($validated['form_submit_count'] ?? 0),
            'metadata' => [
                'visibility' => Arr::get($validated, 'metadata.visibility'),
                'collector' => 'creditsoft_intranet_browser',
                'content_policy' => 'counts_only_no_typed_text',
            ],
        ]);

        return response()->json(['ok' => true, 'stored' => true]);
    }

    public function generateWeeklyReport(Request $request, CreditsoftAiService $ai): RedirectResponse
    {
        $actor = $request->user();

        abort_unless($actor?->canViewUserDirectory(), 403);

        $visibleIds = $this->visibleUsersFor($actor)->pluck('id')->all();
        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::in($visibleIds)],
            'period_start' => ['nullable', 'date'],
        ]);

        $employee = User::query()->with('roles')->findOrFail((int) $validated['user_id']);
        $periodStart = filled($validated['period_start'] ?? null)
            ? Carbon::parse((string) $validated['period_start'])->startOfDay()
            : now()->startOfWeek()->startOfDay();
        $periodEnd = $periodStart->copy()->addDays(6)->endOfDay();
        $periodLabel = $periodStart->toFormattedDateString().' - '.$periodEnd->toFormattedDateString();
        $metrics = $this->weeklyMetricsFor($employee, $periodStart, $periodEnd);
        $status = 'generated';
        $aiError = null;

        try {
            $report = $ai->generateHrWeeklyReport($employee->name, $periodLabel, $metrics);
        } catch (Throwable $throwable) {
            $report = $this->fallbackWeeklyReport($employee, $periodLabel, $metrics);
            $status = 'local_fallback';
            $aiError = class_basename($throwable);
        }

        EmployeeWeeklyReport::query()->updateOrCreate(
            [
                'user_id' => $employee->id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ],
            [
                'generated_by' => $actor->id,
                'title' => $report['title'],
                'summary' => $report['summary'],
                'strengths' => $report['strengths'],
                'risks' => $report['risks'],
                'coaching_notes' => $report['coaching_notes'],
                'next_week_focus' => $report['next_week_focus'],
                'ai_provider' => Arr::get($report, 'meta.provider'),
                'ai_model' => Arr::get($report, 'meta.model'),
                'status' => $status,
                'generated_at' => now(),
                'metadata' => [
                    'metrics' => $metrics,
                    'privacy' => 'Input activity metrics are aggregate counts only; typed content is not collected.',
                    'ai_error' => $aiError,
                ],
            ],
        );

        return back()->with(
            'status',
            $status === 'generated'
                ? 'Weekly HR report generated.'
                : 'Weekly HR report saved as a local draft because the AI lane is not available.',
        );
    }

    protected function fallbackWeeklyReport(User $employee, string $periodLabel, array $metrics): array
    {
        $tasks = $metrics['tasks'] ?? [];
        $audit = $metrics['audit_activity'] ?? [];
        $input = $metrics['input_activity'] ?? [];
        $reviews = $metrics['reviews'] ?? [];
        $doneThisWeek = (int) ($tasks['done_this_week'] ?? 0);
        $openTasks = (int) ($tasks['open'] ?? 0);
        $activeMinutes = (float) ($input['active_minutes'] ?? 0);
        $keypresses = (int) ($input['keypresses'] ?? 0);
        $clicks = (int) ($input['clicks'] ?? 0);
        $activityCaptures = (int) ($input['capture_count'] ?? 0);
        $activityTotal = (int) ($audit['total'] ?? 0);

        $strengths = collect([
            $doneThisWeek > 0 ? "Completed {$doneThisWeek} task(s) during the selected week." : null,
            $activityTotal > 0 ? "Recorded {$activityTotal} audited intranet action(s) tied to work activity." : null,
            $activeMinutes > 0 ? 'Generated aggregate activity signals from active intranet time without collecting typed content.' : null,
            ($keypresses + $clicks) > 0 ? "Logged {$keypresses} key entry count(s) and {$clicks} click count(s) as productivity signals." : null,
        ])->filter()->values()->all();

        if ($strengths === []) {
            $strengths[] = 'No strong positive work signal was recorded yet for this period.';
        }

        $risks = collect([
            $openTasks > 0 ? "Has {$openTasks} open or in-progress task(s) that need continued movement." : null,
            $activityCaptures === 0 ? 'No browser activity captures were recorded for this week, so input-signal scoring is incomplete.' : null,
            ((int) ($reviews['open'] ?? 0)) > 0 ? 'Has open HR review item(s) that should be acknowledged or closed.' : null,
            ((int) ($reviews['write_ups_this_week'] ?? 0)) > 0 ? 'Has write-up activity during the selected week.' : null,
        ])->filter()->values()->all();

        if ($risks === []) {
            $risks[] = 'No major risk signal was detected from the available metrics.';
        }

        $nextFocus = collect([
            $openTasks > 0 ? 'Close, update, or reassign open tasks before the next review cycle.' : 'Keep task completion notes current as work is finished.',
            $activityCaptures === 0 ? 'Confirm the intranet browser is open during work sessions so activity counts can build a fair baseline.' : 'Keep work sessions consistent so activity baselines become more useful over time.',
            'Use HR notes for context when numbers alone do not explain performance.',
        ])->values()->all();

        return [
            'title' => 'Weekly HR performance draft',
            'summary' => "{$employee->name} was reviewed for {$periodLabel}. The local draft used task status, audit activity, HR notes, and aggregate input counts only.",
            'strengths' => $strengths,
            'risks' => $risks,
            'coaching_notes' => 'This report is a local draft because the configured AI lane was unavailable. Review the metrics and add manager context before using it as an official HR note.',
            'next_week_focus' => $nextFocus,
            'meta' => [
                'provider' => 'local_rule_summary',
                'model' => 'creditsoft-local-hr-v1',
            ],
        ];
    }

    protected function visibleUsersFor(User $actor): Collection
    {
        $users = User::query()
            ->with(['roles', 'manager'])
            ->orderBy('name')
            ->get();

        if ($actor->canManageUsers()) {
            return $users;
        }

        $allowed = collect([$actor->id]);
        $changed = true;

        while ($changed) {
            $changed = false;

            $users
                ->filter(fn (User $user) => $user->manager_id && $allowed->contains($user->manager_id) && ! $allowed->contains($user->id))
                ->each(function (User $user) use (&$allowed, &$changed) {
                    $allowed->push($user->id);
                    $changed = true;
                });
        }

        return $users->whereIn('id', $allowed->all())->values();
    }

    protected function activitySamplesFor(array $userIds, int $days = 30): Collection
    {
        return EmployeeActivitySample::query()
            ->whereIn('user_id', $userIds)
            ->where('sampled_at', '>=', now()->subDays($days))
            ->orderBy('sampled_at')
            ->get();
    }

    protected function inputActivityFor(Collection $samples): array
    {
        return $samples
            ->groupBy('user_id')
            ->map(fn (Collection $rows): array => [
                'capture_count' => $rows->count(),
                'active_minutes' => round(((int) $rows->sum('active_ms')) / 60000, 1),
                'keypresses' => (int) $rows->sum('keypress_count'),
                'clicks' => (int) $rows->sum('click_count'),
                'mouse_moves' => (int) $rows->sum('mouse_move_count'),
                'scrolls' => (int) $rows->sum('scroll_count'),
                'focuses' => (int) $rows->sum('focus_count'),
                'form_submits' => (int) $rows->sum('form_submit_count'),
            ])
            ->all();
    }

    protected function weeklyMetricsFor(User $employee, Carbon $periodStart, Carbon $periodEnd): array
    {
        $auditEntries = AuditEntry::query()
            ->where('user_id', $employee->id)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->get(['user_id', 'event', 'summary', 'context', 'created_at']);
        $activity = $this->activityFor($auditEntries)[$employee->id] ?? [
            'total' => 0,
            'api' => 0,
            'ai' => 0,
            'manual' => 0,
        ];
        $input = $this->inputActivityFor(
            EmployeeActivitySample::query()
                ->where('user_id', $employee->id)
                ->whereBetween('sampled_at', [$periodStart, $periodEnd])
                ->get()
        )[$employee->id] ?? [
            'capture_count' => 0,
            'active_minutes' => 0,
            'keypresses' => 0,
            'clicks' => 0,
            'mouse_moves' => 0,
            'scrolls' => 0,
            'focuses' => 0,
            'form_submits' => 0,
        ];
        $tasks = Task::query()
            ->where('assigned_to', $employee->id)
            ->get(['status', 'updated_at']);
        $profile = EmployeeProfile::query()->where('user_id', $employee->id)->first();

        return [
            'period' => [
                'start' => $periodStart->toDateString(),
                'end' => $periodEnd->toDateString(),
            ],
            'role' => $employee->primaryRoleLabel(),
            'department' => $profile?->department,
            'title' => $profile?->title,
            'assigned_clients' => Client::query()->where('assigned_to', $employee->id)->count(),
            'tasks' => [
                'open' => $tasks->whereIn('status', ['open', 'in_progress'])->count(),
                'done_total' => $tasks->where('status', 'done')->count(),
                'done_this_week' => $tasks
                    ->filter(fn (Task $task): bool => $task->status === 'done' && $task->updated_at?->between($periodStart, $periodEnd))
                    ->count(),
            ],
            'audit_activity' => $activity,
            'input_activity' => $input,
            'reviews' => [
                'open' => EmployeeReview::query()->where('user_id', $employee->id)->where('status', 'open')->count(),
                'write_ups_this_week' => EmployeeReview::query()
                    ->where('user_id', $employee->id)
                    ->where('review_type', 'write_up')
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->count(),
                'coaching_notes_this_week' => EmployeeReview::query()
                    ->where('user_id', $employee->id)
                    ->where('review_type', 'coaching')
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->count(),
            ],
            'privacy_note' => 'Key and click activity are aggregate counts from the intranet browser only. Typed text, field values, passwords, and screenshots are not collected.',
        ];
    }

    protected function activityEntriesFor(array $userIds): Collection
    {
        return AuditEntry::query()
            ->whereIn('user_id', $userIds)
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at')
            ->get(['user_id', 'event', 'summary', 'context', 'created_at']);
    }

    protected function activityFor(Collection $entries): array
    {
        $activity = [];

        $entries
            ->each(function (AuditEntry $entry) use (&$activity) {
                $userId = (int) $entry->user_id;
                $bucket = $this->classifyActivity($entry);

                $activity[$userId] ??= [
                    'total' => 0,
                    'api' => 0,
                    'ai' => 0,
                    'manual' => 0,
                ];

                $activity[$userId]['total']++;
                $activity[$userId][$bucket]++;
            });

        return $activity;
    }

    protected function activityCharts(Collection $users, Collection $entries): array
    {
        $timezone = (string) config('app.timezone', 'America/Los_Angeles');
        $palette = [
            '#d97706',
            '#2563eb',
            '#059669',
            '#dc2626',
            '#7c3aed',
            '#0891b2',
            '#be123c',
            '#4d7c0f',
        ];
        $visibleUsers = $users->take(8)->values();
        $labels = collect(range(29, 0))
            ->map(fn (int $daysAgo) => now($timezone)->subDays($daysAgo))
            ->values();
        $hourLabels = collect(range(0, 23))->map(fn (int $hour) => $this->hourLabel($hour))->all();
        $entriesByUser = $entries->groupBy('user_id');

        return [
            'timezone' => $timezone,
            'daily' => [
                'date_keys' => $labels->map(fn ($date) => $date->toDateString())->all(),
                'labels' => $labels->map(fn ($date) => $date->format('M j'))->all(),
                'series' => $visibleUsers
                    ->map(function (User $user, int $index) use ($entriesByUser, $labels, $palette, $timezone) {
                        $byDate = $entriesByUser
                            ->get($user->id, collect())
                            ->groupBy(fn (AuditEntry $entry) => $entry->created_at?->copy()->timezone($timezone)->toDateString() ?? 'unknown');

                        return [
                            'label' => $user->name,
                            'color' => $palette[$index % count($palette)],
                            'values' => $labels
                                ->map(fn ($date) => $byDate->get($date->toDateString(), collect())->count())
                                ->all(),
                        ];
                    })
                    ->values()
                    ->all(),
            ],
            'hourly' => [
                'labels' => $hourLabels,
                'series' => $visibleUsers
                    ->map(function (User $user, int $index) use ($entriesByUser, $palette, $timezone) {
                        $byHour = $entriesByUser
                            ->get($user->id, collect())
                            ->groupBy(fn (AuditEntry $entry) => (int) ($entry->created_at?->copy()->timezone($timezone)->format('G') ?? 0));

                        return [
                            'label' => $user->name,
                            'color' => $palette[$index % count($palette)],
                            'values' => collect(range(0, 23))
                                ->map(fn (int $hour) => $byHour->get($hour, collect())->count())
                                ->all(),
                        ];
                    })
                    ->values()
                    ->all(),
            ],
            'windows' => $visibleUsers
                ->map(fn (User $user, int $index) => $this->activityWindowFor(
                    $user,
                    $entriesByUser->get($user->id, collect()),
                    $palette[$index % count($palette)],
                    $timezone,
                ))
                ->values()
                ->all(),
        ];
    }

    protected function dailyActivityFor(Collection $entries, Collection $samples, Collection $dates, string $timezone): array
    {
        $entriesByDate = $entries->groupBy(
            fn (AuditEntry $entry) => $entry->created_at?->copy()->timezone($timezone)->toDateString() ?? 'unknown',
        );
        $samplesByDate = $samples->groupBy(
            fn (EmployeeActivitySample $sample) => $sample->sampled_at?->copy()->timezone($timezone)->toDateString() ?? 'unknown',
        );

        return $dates
            ->map(function ($date) use ($entriesByDate, $samplesByDate): array {
                $dateKey = $date->toDateString();
                $auditCount = $entriesByDate->get($dateKey, collect())->count();
                $inputCount = $samplesByDate->get($dateKey, collect())->count();

                return [
                    'date' => $dateKey,
                    'audit' => $auditCount,
                    'input' => $inputCount,
                    'total' => $auditCount + $inputCount,
                ];
            })
            ->all();
    }

    protected function activityWindowFor(User $user, Collection $entries, string $color, string $timezone): array
    {
        if ($entries->isEmpty()) {
            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'color' => $color,
                'events' => 0,
                'active_days' => 0,
                'work_window' => 'No activity yet',
                'peak_hour' => 'No activity yet',
                'last_activity_at' => null,
            ];
        }

        $localDates = $entries->map(fn (AuditEntry $entry) => $entry->created_at?->copy()->timezone($timezone));
        $hours = $localDates
            ->filter()
            ->map(fn ($date) => (int) $date->format('G'))
            ->values();
        $hourCounts = $hours->countBy();
        $peakHour = (int) $hourCounts->sortDesc()->keys()->first();
        $startHour = (int) $hours->min();
        $endHour = (int) $hours->max();

        $lastActivityAt = $entries->sortByDesc('created_at')->first()?->created_at;

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'color' => $color,
            'events' => $entries->count(),
            'active_days' => $localDates
                ->filter()
                ->map(fn ($date) => $date->toDateString())
                ->unique()
                ->count(),
            'work_window' => $this->hourLabel($startHour).' - '.$this->hourLabel($endHour),
            'peak_hour' => $this->hourLabel($peakHour),
            'last_activity_at' => optional($lastActivityAt)?->toIso8601String(),
        ];
    }

    protected function hourLabel(int $hour): string
    {
        if ($hour === 0) {
            return '12a';
        }

        if ($hour < 12) {
            return $hour.'a';
        }

        if ($hour === 12) {
            return '12p';
        }

        return ($hour - 12).'p';
    }

    protected function classifyActivity(AuditEntry $entry): string
    {
        $haystack = str($entry->event.' '.$entry->summary.' '.json_encode($entry->context ?? []))->lower()->value();

        if (str_contains($haystack, 'ai')) {
            return 'ai';
        }

        if (
            str_contains($haystack, 'api')
            || str_contains($haystack, 'companion')
            || str_contains($haystack, 'browser')
            || str_contains($haystack, 'webhook')
            || str_contains($haystack, 'migration')
            || str_contains($haystack, 'zelle')
            || str_contains($haystack, 'cash_app')
        ) {
            return 'api';
        }

        return 'manual';
    }

    protected function cleanRoutePath(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        $withoutQuery = strtok($path, '?') ?: $path;

        return str($withoutQuery)->limit(255, '')->value();
    }

    protected function profilePayload(?EmployeeProfile $profile): ?array
    {
        if (! $profile) {
            return null;
        }

        return [
            'legal_name' => $profile->legal_name,
            'preferred_name' => $profile->preferred_name,
            'phone' => $profile->phone,
            'emergency_contact_name' => $profile->emergency_contact_name,
            'emergency_contact_phone' => $profile->emergency_contact_phone,
            'department' => $profile->department,
            'title' => $profile->title,
            'employment_type' => $profile->employment_type,
            'timezone' => $profile->timezone,
            'onboarding_status' => $profile->onboarding_status,
            'onboarding_started_at' => optional($profile->onboarding_started_at)?->toIso8601String(),
            'onboarding_completed_at' => optional($profile->onboarding_completed_at)?->toIso8601String(),
            'pay_method' => $profile->pay_method,
            'pay_destination' => $profile->pay_destination,
            'pay_currency' => $profile->pay_currency,
            'payroll_notes' => $profile->payroll_notes,
        ];
    }
}
