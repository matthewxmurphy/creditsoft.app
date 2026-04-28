<?php

namespace App\Http\Controllers;

use App\Models\BrowserCapture;
use App\Models\Client;
use App\Models\ReportingCycle;
use App\Models\ViolationCandidate;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ImportedReviewController extends Controller
{
    public function markReviewed(Request $request, Client $client, ReportingCycle $reportingCycle, AuditTrail $auditTrail): RedirectResponse
    {
        abort_unless($reportingCycle->client_id === $client->getKey(), 404);

        $validated = $request->validate([
            'row_signature' => ['required', 'string', 'max:255'],
            'row_name' => ['required', 'string', 'max:255'],
        ]);

        $this->storeReviewSignature($reportingCycle, $validated['row_signature']);
        $auditTrail->record(
            $request->user(),
            'imported-review.reviewed',
            "Marked {$validated['row_name']} as reviewed.",
            $reportingCycle,
            ['row_signature' => $validated['row_signature']],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$validated['row_name']} marked as reviewed.",
        ]);

        return back();
    }

    public function startDispute(Request $request, Client $client, ReportingCycle $reportingCycle, AuditTrail $auditTrail): RedirectResponse
    {
        abort_unless($reportingCycle->client_id === $client->getKey(), 404);

        $validated = $request->validate([
            'row_signature' => ['required', 'string', 'max:255'],
            'row_name' => ['required', 'string', 'max:255'],
            'row_status' => ['nullable', 'string', 'max:255'],
            'row_category' => ['nullable', 'string', 'max:255'],
            'dispute_reason' => ['nullable', 'string', 'max:1000'],
            'flags' => ['array'],
            'flags.*' => ['string', 'max:120'],
        ]);

        $this->storeReviewSignature($reportingCycle, $validated['row_signature']);
        $this->storeDisputeSignature($reportingCycle, $validated['row_signature']);

        $existingViolation = $client->violations()
            ->where('reporting_cycle_id', $reportingCycle->getKey())
            ->get()
            ->first(function (ViolationCandidate $violation) use ($validated): bool {
                return data_get($violation->evidence, '0.signature') === $validated['row_signature'];
            });

        $flags = collect($validated['flags'] ?? [])
            ->filter(fn ($flag) => is_string($flag) && $flag !== '')
            ->values()
            ->all();

        if (! $existingViolation) {
            $severity = $this->severityForFlags($flags);
            $title = count($flags) > 0
                ? sprintf('%s · %s', $validated['row_name'], implode(' / ', $flags))
                : sprintf('%s · Imported dispute item', $validated['row_name']);

            $existingViolation = $client->violations()->create([
                'reporting_cycle_id' => $reportingCycle->getKey(),
                'rule_key' => 'imported_review_dispute',
                'title' => $title,
                'severity' => $severity,
                'priority_score' => $this->priorityScore($severity),
                'status' => 'open',
                'bureau' => null,
                'next_action' => 'Draft dispute from imported provider review.',
                'evidence' => [[
                    'signature' => $validated['row_signature'],
                    'source' => 'smartcredit_import',
                    'detail' => implode('; ', array_filter([
                        filled($validated['dispute_reason'] ?? null) ? trim((string) $validated['dispute_reason']) : null,
                        implode('; ', $flags),
                    ])),
                    'row_name' => $validated['row_name'],
                    'row_status' => $validated['row_status'] ?? null,
                    'row_category' => $validated['row_category'] ?? null,
                    'dispute_reason' => $validated['dispute_reason'] ?? null,
                ]],
            ]);
        }

        $auditTrail->record(
            $request->user(),
            'imported-review.dispute-started',
            "Started dispute review for {$validated['row_name']}.",
            $existingViolation,
            ['row_signature' => $validated['row_signature']],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Dispute started for {$validated['row_name']}.",
        ]);

        return back();
    }

    protected function storeReviewSignature(ReportingCycle $cycle, string $signature): void
    {
        $state = $cycle->review_metadata ?? [];
        $reviewed = collect($state['reviewed_signatures'] ?? [])
            ->push($signature)
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->unique()
            ->values()
            ->all();

        $state['reviewed_signatures'] = $reviewed;
        $cycle->review_metadata = $state;
        $this->syncReviewedAt($cycle, count($reviewed));
        $cycle->save();
    }

    protected function storeDisputeSignature(ReportingCycle $cycle, string $signature): void
    {
        $state = $cycle->review_metadata ?? [];
        $disputed = collect($state['dispute_signatures'] ?? [])
            ->push($signature)
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->unique()
            ->values()
            ->all();

        $state['dispute_signatures'] = $disputed;
        $cycle->review_metadata = $state;
        $cycle->save();
    }

    protected function syncReviewedAt(ReportingCycle $cycle, int $reviewedCount): void
    {
        $totalRows = $this->smartCreditRowCount($cycle);

        if ($totalRows > 0 && $reviewedCount >= $totalRows) {
            $cycle->reviewed_at = now();

            return;
        }

        $cycle->reviewed_at = null;
    }

    protected function smartCreditRowCount(ReportingCycle $cycle): int
    {
        /** @var BrowserCapture|null $capture */
        $capture = $cycle->browserCaptures()
            ->where('metadata->smartcredit->profile', 'three_bureau_report')
            ->latest('imported_at')
            ->first();

        return (int) data_get($capture?->metadata, 'smartcredit.account_matrix_count', 0);
    }

    /**
     * @param  list<string>  $flags
     */
    protected function severityForFlags(array $flags): string
    {
        if (collect($flags)->contains(fn ($flag) => in_array($flag, ['Negative reporting', 'Missing bureau'], true))) {
            return 'high';
        }

        if ($flags !== []) {
            return 'medium';
        }

        return 'low';
    }

    protected function priorityScore(string $severity): int
    {
        return match ($severity) {
            'high' => 82,
            'medium' => 58,
            default => 34,
        };
    }
}
