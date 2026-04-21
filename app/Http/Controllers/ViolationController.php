<?php

namespace App\Http\Controllers;

use App\Creditsoft\Config\YamlConfigLoader;
use App\Models\Client;
use App\Models\ReportingCycle;
use App\Models\ViolationCandidate;
use App\Services\AuditTrail;
use App\Services\CreditReportComparisonService;
use App\Services\OfficeGrowthRuntime;
use App\Services\Metro2Scanner;
use App\Services\ReportFeedbackSignalService;
use App\Services\ViolationLegalReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ViolationController extends Controller
{
    public function queue(): Response
    {
        $queue = ViolationCandidate::query()
            ->with('client', 'reportingCycle')
            ->whereIn('status', ['open', 'confirmed']);

        return Inertia::render('violations/Index', [
            'stats' => [
                'total' => (clone $queue)->count(),
                'open' => (clone $queue)->where('status', 'open')->count(),
                'confirmed' => (clone $queue)->where('status', 'confirmed')->count(),
                'high' => (clone $queue)->where('severity', 'high')->count(),
            ],
            'violations' => $queue
                ->orderByRaw("case severity when 'high' then 3 when 'medium' then 2 else 1 end desc")
                ->orderByDesc('priority_score')
                ->latest()
                ->get()
                ->map(fn (ViolationCandidate $violation) => [
                    'id' => $violation->getKey(),
                    'title' => $violation->title,
                    'severity' => $violation->severity,
                    'priority_score' => $violation->priority_score,
                    'status' => $violation->status,
                    'rule_key' => $violation->rule_key,
                    'bureau' => $violation->bureau,
                    'next_action' => $violation->next_action,
                    'created_at' => optional($violation->created_at)->toIso8601String(),
                    'client' => $violation->client
                        ? [
                            'id' => $violation->client->getKey(),
                            'display_name' => $violation->client->display_name,
                            'first_name' => $violation->client->first_name,
                            'last_name' => $violation->client->last_name,
                        ]
                        : null,
                    'reporting_cycle' => $violation->reportingCycle
                        ? [
                            'id' => $violation->reportingCycle->getKey(),
                            'cycle_label' => $violation->reportingCycle->cycle_label,
                        ]
                        : null,
                ])
                ->values(),
        ]);
    }

    public function index(
        Request $request,
        Client $client,
        YamlConfigLoader $loader,
        Metro2Scanner $scanner,
        CreditReportComparisonService $comparisonService,
        ViolationLegalReviewService $legalReview,
        OfficeGrowthRuntime $growth,
    ): Response {
        $selectedCycle = $client->reportingCycles()
            ->when($request->integer('cycle'), fn ($query, $cycleId) => $query->whereKey($cycleId))
            ->latest('started_at')
            ->first();

        if ($selectedCycle) {
            $scanner->queueSuggestions($selectedCycle, $request->user());
        }

        $comparisonRows = $selectedCycle
            ? collect($comparisonService->comparisonRows($selectedCycle))
                ->filter(fn (array $row) => filled($row['mismatches'] ?? []))
                ->values()
                ->all()
            : [];

        $config = $loader->load();

        return Inertia::render('clients/Violations', [
            'client' => $client,
            'violations' => $client->violations()
                ->when($selectedCycle, fn ($query) => $query->where('reporting_cycle_id', $selectedCycle->getKey()))
                ->with('reportingCycle', 'tradeline')
                ->orderByDesc('priority_score')
                ->latest()
                ->get()
                ->map(fn (ViolationCandidate $violation) => [
                    'id' => $violation->getKey(),
                    'title' => $violation->title,
                    'severity' => $violation->severity,
                    'priority_score' => $violation->priority_score,
                    'status' => $violation->status,
                    'rule_key' => $violation->rule_key,
                    'bureau' => $violation->bureau,
                    'next_action' => $violation->next_action,
                    'evidence' => $violation->evidence ?? [],
                    'legal_frameworks' => $legalReview->frameworksFor(
                        $violation->rule_key,
                        $violation->evidence ?? [],
                        $violation->title,
                    ),
                ])
                ->values(),
            'cycles' => $client->reportingCycles()->latest('started_at')->get(['id', 'cycle_label']),
            'rules' => $config['violation_rules.yaml']['rules'] ?? [],
            'creditReasonOptions' => $growth->creditReasons(),
            'selectedCycleId' => $selectedCycle?->getKey(),
            'selectedCycle' => $selectedCycle?->only(['id', 'cycle_label']),
            'suggestions' => $selectedCycle ? $scanner->suggestionsForCycle($selectedCycle) : [],
            'comparisonRows' => $comparisonRows,
        ]);
    }

    public function store(Request $request, Client $client, AuditTrail $auditTrail, YamlConfigLoader $loader): RedirectResponse
    {
        $validated = $request->validate([
            'reporting_cycle_id' => ['nullable', 'integer', 'exists:reporting_cycles,id'],
            'rule_key' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'severity' => ['required', 'in:low,medium,high'],
            'bureau' => ['nullable', 'in:experian,transunion,equifax'],
            'dispute_reason' => ['nullable', 'string', 'max:1000'],
            'next_action' => ['nullable', 'string'],
            'evidence' => ['nullable', 'string'],
        ]);
        $config = $loader->load();
        $rule = collect(($config['violation_rules.yaml']['rules'] ?? []))
            ->firstWhere('key', $validated['rule_key']);

        $violation = $client->violations()->create([
            ...$validated,
            'priority_score' => $this->priorityScore($validated['severity'], (int) data_get($rule, 'priority_boost', 0)),
            'status' => 'open',
            'evidence' => $this->buildEvidenceEntries(
                $validated['dispute_reason'] ?? null,
                $validated['evidence'] ?? null,
            ),
        ]);

        $auditTrail->record(
            $request->user(),
            'violation.created',
            "Logged violation candidate {$violation->title}.",
            $violation,
        );

        return redirect()->route('clients.violations.index', $client);
    }

    public function scan(
        Request $request,
        Client $client,
        Metro2Scanner $scanner,
        AuditTrail $auditTrail,
        ReportFeedbackSignalService $reportFeedbackSignals,
    ): RedirectResponse
    {
        $validated = $request->validate([
            'reporting_cycle_id' => ['required', 'integer', 'exists:reporting_cycles,id'],
        ]);

        /** @var ReportingCycle $cycle */
        $cycle = $client->reportingCycles()->findOrFail($validated['reporting_cycle_id']);
        $queued = $scanner->queueSuggestions($cycle, $request->user());

        $auditTrail->record(
            $request->user(),
            'metro2.scan.queued',
            "Queued {$queued->count()} Metro2 suggestions for {$cycle->cycle_label}.",
            $cycle,
            ['queued_count' => $queued->count()],
        );

        $reportFeedbackSignals->queueMetro2ScanQueued($cycle, $queued);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $queued->count() > 0
                ? "Queued {$queued->count()} suggested findings."
                : 'No new findings were added. Existing suggestions are already in the queue.',
        ]);

        return back();
    }

    public function update(Request $request, Client $client, ViolationCandidate $violationCandidate, AuditTrail $auditTrail): RedirectResponse
    {
        abort_unless($violationCandidate->client_id === $client->getKey(), 404);

        $validated = $request->validate([
            'status' => ['required', 'in:open,confirmed,resolved'],
        ]);

        $violationCandidate->update([
            'status' => $validated['status'],
            'confirmed_at' => $validated['status'] === 'confirmed' ? now() : null,
            'confirmed_by' => $validated['status'] === 'confirmed' ? $request->user()?->getKey() : null,
        ]);

        $auditTrail->record(
            $request->user(),
            'violation.updated',
            "Marked {$violationCandidate->title} as {$validated['status']}.",
            $violationCandidate,
        );

        return redirect()->route('clients.violations.index', $client);
    }

    protected function priorityScore(string $severity, int $priorityBoost = 0): int
    {
        $base = match ($severity) {
            'high' => 78,
            'medium' => 54,
            default => 32,
        };

        return min($base + $priorityBoost, 100);
    }

    /**
     * @return list<array{detail:string,source:string}>
     */
    protected function buildEvidenceEntries(?string $disputeReason, ?string $evidence): array
    {
        $entries = [];

        if (filled($disputeReason)) {
            $entries[] = [
                'detail' => trim((string) $disputeReason),
                'source' => 'office_credit_reason',
            ];
        }

        if (filled($evidence)) {
            $entries[] = [
                'detail' => trim((string) $evidence),
                'source' => 'manual_evidence',
            ];
        }

        return $entries;
    }
}
