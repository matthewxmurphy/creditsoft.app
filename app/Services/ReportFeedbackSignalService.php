<?php

namespace App\Services;

use App\Models\BureauSnapshot;
use App\Models\OutboundSignal;
use App\Models\ReportingCycle;
use App\Models\ViolationCandidate;
use Carbon\CarbonInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportFeedbackSignalService
{
    public function __construct(
        protected CreditReportComparisonService $comparisonService,
        protected InstallationFeedbackPolicy $feedbackPolicy,
        protected ReportFeedbackSignalBuilder $reportFeedbackSignalBuilder,
        protected SignalSanitizer $signalSanitizer,
    ) {
    }

    public function queueSnapshotImported(BureauSnapshot $snapshot): ?OutboundSignal
    {
        $snapshot->loadMissing('tradelines', 'reportingCycle.client.reportingCycles.bureauSnapshots.tradelines', 'reportingCycle.bureauSnapshots.tradelines', 'reportingCycle.violationCandidates');

        $cycle = $snapshot->reportingCycle;

        if (! $cycle || ! $cycle->client) {
            return null;
        }

        if (! $this->feedbackPolicy->reportFeedbackEnabled()) {
            return null;
        }

        $recordedAt = $snapshot->imported_at ?? now();
        $summary = $this->comparisonService->reviewSummary($cycle);

        $payload = $this->reportFeedbackSignalBuilder->buildSnapshotImported(
            $cycle->client,
            $cycle,
            $snapshot,
            $summary,
        );

        return $this->queueSignal(
            clientId: $cycle->client->getKey(),
            eventType: 'report_feedback.snapshot_imported',
            payload: $payload,
            recordedAt: $recordedAt,
        );
    }

    /**
     * @param  Collection<int, ViolationCandidate>  $queued
     */
    public function queueMetro2ScanQueued(ReportingCycle $cycle, Collection $queued): ?OutboundSignal
    {
        $cycle->loadMissing('client.reportingCycles.bureauSnapshots.tradelines', 'bureauSnapshots.tradelines', 'violationCandidates');

        if (! $cycle->client) {
            return null;
        }

        if (! $this->feedbackPolicy->reportFeedbackEnabled()) {
            return null;
        }

        $recordedAt = now();
        $summary = $this->comparisonService->reviewSummary($cycle);

        $payload = $this->reportFeedbackSignalBuilder->buildMetro2ScanQueued(
            $cycle->client,
            $cycle,
            $queued,
            $summary,
        );

        return $this->queueSignal(
            clientId: $cycle->client->getKey(),
            eventType: 'report_feedback.metro2_scan_queued',
            payload: $payload,
            recordedAt: $recordedAt,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function queueSignal(int $clientId, string $eventType, array $payload, CarbonInterface $recordedAt): ?OutboundSignal
    {
        $sanitizedPayload = $this->signalSanitizer->sanitize($payload, 'creditsoft.report_feedback_allowlist');

        if ($sanitizedPayload === []) {
            return null;
        }

        return OutboundSignal::create([
            'client_id' => $clientId,
            'event_type' => $eventType,
            'visibility' => 'aggregate_report_feedback',
            'payload' => $payload,
            'sanitized_payload' => $sanitizedPayload,
            'status' => 'pending',
            'queued_at' => $recordedAt,
        ]);
    }
}
