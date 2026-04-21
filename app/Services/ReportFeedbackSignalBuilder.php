<?php

namespace App\Services;

use App\Models\BureauSnapshot;
use App\Models\BrowserCapture;
use App\Models\Client;
use App\Models\ReportingCycle;
use App\Models\ViolationCandidate;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReportFeedbackSignalBuilder
{
    public function buildSnapshotImported(
        Client $client,
        ReportingCycle $cycle,
        BureauSnapshot $snapshot,
        array $summary,
    ): array {
        $recordedAt = $snapshot->imported_at ?? now();
        $previousSnapshot = $cycle->bureauSnapshots
            ->where('id', '!=', $snapshot->getKey())
            ->sortByDesc(fn (BureauSnapshot $entry) => optional($entry->imported_at)?->timestamp ?? 0)
            ->first();

        return array_filter([
            'installation_id' => $this->installationId(),
            'org_scoped_person_id' => $this->orgScopedPersonId($client),
            'event_type' => 'report_feedback.snapshot_imported',
            'recorded_at' => $recordedAt->toIso8601String(),
            'provider_key' => 'credit_report',
            'provider_profile' => 'snapshot_import',
            'source_type' => $snapshot->source,
            'client_stage' => $client->status,
            'business_timing' => array_filter([
                'days_as_customer' => $this->daysSince($client->created_at),
                'lead_to_conversion_days' => $this->leadToConversionDays($client),
                'days_since_cycle_start' => $this->daysSince($cycle->started_at),
                'hours_since_cycle_start' => $this->hoursBetween($cycle->started_at, $recordedAt),
                'hours_to_first_snapshot' => $previousSnapshot === null
                    ? $this->hoursBetween($cycle->started_at, $recordedAt)
                    : null,
                'hours_since_previous_snapshot' => $previousSnapshot?->imported_at
                    ? $this->hoursBetween($previousSnapshot->imported_at, $recordedAt)
                    : null,
            ], fn ($value) => $value !== null),
            'report_snapshot' => array_filter([
                'snapshot_tradelines' => (int) $snapshot->tradelines->count(),
                'cycle_snapshots' => (int) $cycle->bureauSnapshots->count(),
                'total_accounts' => $this->numeric($summary['total_accounts'] ?? null),
                'open_accounts' => $this->numeric($summary['open_accounts'] ?? null),
                'closed_accounts' => $this->numeric($summary['closed_accounts'] ?? null),
                'negative_accounts' => $this->numeric($summary['negative_accounts'] ?? null),
                'positive_accounts' => $this->numeric($summary['positive_accounts'] ?? null),
                'revolving_accounts' => $this->numeric($summary['revolving_accounts'] ?? null),
                'over_thirty_percent' => $this->numeric($summary['over_thirty_percent'] ?? null),
                'priority_disputes' => $this->numeric($summary['priority_disputes'] ?? null),
                'changed_since_last_cycle' => $this->numeric($summary['changed_since_last_cycle'] ?? null),
            ], fn ($value) => $value !== null && $value !== [] && $value !== ''),
            'bureau_coverage' => $this->cycleBureauCoverage($cycle, $snapshot->bureau),
            'provider_warnings' => $this->cycleProviderWarnings($cycle, [
                $snapshot->source === 'manual' ? 'manual_snapshot_entry' : null,
            ]),
            'graph_kind' => 'report_comparison',
        ], fn ($value) => $value !== null && $value !== [] && $value !== '');
    }

    /**
     * @param  Collection<int, ViolationCandidate>  $queued
     */
    public function buildMetro2ScanQueued(
        Client $client,
        ReportingCycle $cycle,
        Collection $queued,
        array $summary,
    ): array {
        $recordedAt = now();
        $latestSnapshot = $cycle->bureauSnapshots
            ->sortByDesc(fn (BureauSnapshot $snapshot) => optional($snapshot->imported_at)?->timestamp ?? 0)
            ->first();

        return array_filter([
            'installation_id' => $this->installationId(),
            'org_scoped_person_id' => $this->orgScopedPersonId($client),
            'event_type' => 'report_feedback.metro2_scan_queued',
            'recorded_at' => $recordedAt->toIso8601String(),
            'provider_key' => 'credit_report',
            'provider_profile' => 'metro2_scan',
            'source_type' => 'internal_scan',
            'client_stage' => $client->status,
            'business_timing' => array_filter([
                'days_as_customer' => $this->daysSince($client->created_at),
                'lead_to_conversion_days' => $this->leadToConversionDays($client),
                'days_since_cycle_start' => $this->daysSince($cycle->started_at),
                'hours_since_cycle_start' => $this->hoursBetween($cycle->started_at, $recordedAt),
                'hours_since_latest_snapshot' => $latestSnapshot?->imported_at
                    ? $this->hoursBetween($latestSnapshot->imported_at, $recordedAt)
                    : null,
            ], fn ($value) => $value !== null),
            'report_snapshot' => array_filter([
                'total_accounts' => $this->numeric($summary['total_accounts'] ?? null),
                'open_accounts' => $this->numeric($summary['open_accounts'] ?? null),
                'closed_accounts' => $this->numeric($summary['closed_accounts'] ?? null),
                'negative_accounts' => $this->numeric($summary['negative_accounts'] ?? null),
                'positive_accounts' => $this->numeric($summary['positive_accounts'] ?? null),
                'revolving_accounts' => $this->numeric($summary['revolving_accounts'] ?? null),
                'over_thirty_percent' => $this->numeric($summary['over_thirty_percent'] ?? null),
                'priority_disputes' => $this->numeric($summary['priority_disputes'] ?? null),
                'changed_since_last_cycle' => $this->numeric($summary['changed_since_last_cycle'] ?? null),
            ], fn ($value) => $value !== null && $value !== [] && $value !== ''),
            'bureau_coverage' => $this->cycleBureauCoverage($cycle),
            'provider_warnings' => $this->cycleProviderWarnings($cycle, [
                $queued->isEmpty() ? 'no_new_findings' : null,
            ]),
            'metro2_snapshot' => [
                'queued_suggestions' => (int) $queued->count(),
                'open_violations' => (int) $cycle->violationCandidates()
                    ->whereIn('status', ['open', 'confirmed'])
                    ->count(),
                'rule_counts' => $queued
                    ->countBy(fn (ViolationCandidate $candidate) => (string) $candidate->rule_key)
                    ->sortKeys()
                    ->all(),
                'severity_counts' => $queued
                    ->countBy(fn (ViolationCandidate $candidate) => (string) $candidate->severity)
                    ->sortKeys()
                    ->all(),
                'bureau_counts' => $queued
                    ->filter(fn (ViolationCandidate $candidate) => filled($candidate->bureau))
                    ->countBy(fn (ViolationCandidate $candidate) => (string) $candidate->bureau)
                    ->sortKeys()
                    ->all(),
            ],
            'graph_kind' => 'metro2_queue',
        ], fn ($value) => $value !== null && $value !== [] && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $providerCapture
     * @return array<string, mixed>
     */
    public function build(
        Client $client,
        ReportingCycle $cycle,
        BrowserCapture $capture,
        array $providerCapture,
    ): array {
        $creditScore = $this->numeric(data_get($providerCapture, 'scores.credit'));

        return array_filter([
            'installation_id' => $this->installationId(),
            'org_scoped_person_id' => $this->orgScopedPersonId($client),
            'event_type' => 'report_feedback.capture_imported',
            'recorded_at' => now()->toIso8601String(),
            'provider_key' => (string) ($providerCapture['provider'] ?? 'unknown'),
            'provider_profile' => (string) ($providerCapture['profile'] ?? 'generic'),
            'source_type' => $capture->source_type,
            'cycle_label' => $cycle->cycle_label,
            'client_stage' => $client->status,
            'business_timing' => array_filter([
                'days_as_customer' => $this->daysSince($client->created_at),
                'lead_to_conversion_days' => $this->leadToConversionDays($client),
                'days_since_cycle_start' => $this->daysSince($cycle->started_at),
            ], fn ($value) => $value !== null),
            'score_snapshot' => array_filter([
                'current_score' => $client->current_score,
                'provider_credit_score' => $creditScore,
                'score_band' => $creditScore !== null ? $this->scoreBand($creditScore) : null,
                'history_points' => $this->numeric(data_get($providerCapture, 'score_history_count')),
            ], fn ($value) => $value !== null),
            'report_snapshot' => $this->reportSnapshot($providerCapture),
            'bureau_coverage' => array_filter([
                'reported_bureaus' => array_values(array_filter((array) data_get($providerCapture, 'bureau_coverage.reported_bureaus', []))),
                'missing_bureaus' => array_values(array_filter((array) data_get($providerCapture, 'bureau_coverage.missing_bureaus', []))),
                'coverage' => data_get($providerCapture, 'bureau_coverage.coverage'),
                'bureau' => data_get($providerCapture, 'bureau'),
            ], fn ($value) => $value !== null && $value !== [] && $value !== ''),
            'provider_warnings' => array_values(array_filter((array) data_get($providerCapture, 'provider_warnings', []))),
            'graph_kind' => data_get($providerCapture, 'graph_kind'),
        ], fn ($value) => $value !== null && $value !== [] && $value !== '');
    }

    protected function installationId(): string
    {
        return 'inst_'.substr(hash('sha256', (string) config('app.key')), 0, 24);
    }

    protected function orgScopedPersonId(Client $client): string
    {
        return 'person_'.substr(hash_hmac('sha256', $client->cuid, (string) config('app.key')), 0, 24);
    }

    /**
     * @param  array<string, mixed>  $providerCapture
     * @return array<string, mixed>
     */
    protected function reportSnapshot(array $providerCapture): array
    {
        $summary = match ((string) ($providerCapture['provider'] ?? '')) {
            'smartcredit' => $this->smartCreditSummary($providerCapture),
            'credit_karma' => $this->creditKarmaSummary($providerCapture),
            default => [],
        };

        return array_filter($summary, fn ($value) => $value !== null && $value !== [] && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $providerCapture
     * @return array<string, mixed>
     */
    protected function smartCreditSummary(array $providerCapture): array
    {
        $profile = (string) ($providerCapture['profile'] ?? 'generic');

        if ($profile === 'three_bureau_report') {
            $summary = (array) data_get($providerCapture, 'summary', []);

            return [
                'as_of_date' => data_get($providerCapture, 'as_of_date'),
                'total_accounts' => $this->numeric(data_get($summary, 'total_accounts.value')),
                'open_accounts' => $this->numeric(data_get($summary, 'open_accounts.value')),
                'closed_accounts' => $this->numeric(data_get($summary, 'closed_accounts.value')),
                'delinquent_accounts' => $this->numeric(data_get($summary, 'delinquent_accounts.value')),
                'derogatory_accounts' => $this->numeric(data_get($summary, 'derogatory_accounts.value')),
                'balances_total' => $this->numeric(data_get($summary, 'balances.value')),
                'payments_total' => $this->numeric(data_get($summary, 'payments.value')),
                'inquiries_total' => $this->numeric(data_get($summary, 'inquiries.value')),
                'account_preview_count' => $this->numeric(data_get($providerCapture, 'account_preview_count')),
            ];
        }

        if ($profile === 'score_tracker') {
            return [
                'as_of_date' => data_get($providerCapture, 'as_of_date'),
                'score_history_count' => $this->numeric(data_get($providerCapture, 'score_history_count')),
                'credit_score' => $this->numeric(data_get($providerCapture, 'scores.credit')),
                'auto_score' => $this->numeric(data_get($providerCapture, 'scores.auto')),
                'insurance_score' => $this->numeric(data_get($providerCapture, 'scores.insurance')),
            ];
        }

        if ($profile === 'smart_credit_report') {
            return [
                'as_of_date' => data_get($providerCapture, 'as_of_date'),
                'account_count' => $this->numeric(data_get($providerCapture, 'account_count')),
                'negative_account_count' => $this->numeric(data_get($providerCapture, 'negative_account_count')),
            ];
        }

        return [
            'label' => data_get($providerCapture, 'label'),
        ];
    }

    /**
     * @param  array<string, mixed>  $providerCapture
     * @return array<string, mixed>
     */
    protected function creditKarmaSummary(array $providerCapture): array
    {
        return [
            'as_of_date' => data_get($providerCapture, 'as_of_date'),
            'credit_score' => $this->numeric(data_get($providerCapture, 'scores.credit')),
            'available_report_dates_count' => count((array) data_get($providerCapture, 'available_report_dates', [])),
            'section_notice_keys' => array_keys((array) data_get($providerCapture, 'section_notices', [])),
        ];
    }

    protected function leadToConversionDays(Client $client): ?int
    {
        $explicitDays = data_get($client->metadata, 'lead_to_conversion_days');

        if (is_numeric($explicitDays)) {
            return max(0, (int) $explicitDays);
        }

        $leadStartedAt = data_get($client->metadata, 'lead_started_at');

        if (! is_string($leadStartedAt) || blank($leadStartedAt) || ! $client->created_at instanceof CarbonInterface) {
            return null;
        }

        try {
            $leadStarted = Carbon::parse($leadStartedAt);
        } catch (\Throwable) {
            return null;
        }

        return max(0, $leadStarted->diffInDays($client->created_at));
    }

    protected function daysSince(mixed $date): ?int
    {
        if (! $date instanceof CarbonInterface) {
            return null;
        }

        return max(0, $date->diffInDays(now()));
    }

    protected function hoursBetween(mixed $from, mixed $to): ?int
    {
        if (! $from instanceof CarbonInterface || ! $to instanceof CarbonInterface) {
            return null;
        }

        return max(0, $from->diffInHours($to));
    }

    /**
     * @return array<string, mixed>
     */
    protected function cycleBureauCoverage(ReportingCycle $cycle, ?string $currentBureau = null): array
    {
        $reported = $cycle->bureauSnapshots
            ->pluck('bureau')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $all = ['equifax', 'experian', 'transunion'];
        $missing = array_values(array_diff($all, $reported));

        return array_filter([
            'reported_bureaus' => $reported,
            'missing_bureaus' => $missing,
            'coverage' => $this->coverageLabel($reported),
            'bureau' => $currentBureau,
        ], fn ($value) => $value !== null && $value !== [] && $value !== '');
    }

    /**
     * @param  list<string>  $reported
     */
    protected function coverageLabel(array $reported): string
    {
        return match (count($reported)) {
            3 => 'full_three_bureau',
            2 => 'two_bureau_only',
            1 => 'single_bureau_only',
            default => 'no_bureau_data',
        };
    }

    /**
     * @param  list<string|null>  $extraWarnings
     * @return list<string>
     */
    protected function cycleProviderWarnings(ReportingCycle $cycle, array $extraWarnings = []): array
    {
        $warnings = [
            ...$extraWarnings,
            ...($cycle->bureauSnapshots->pluck('bureau')->filter()->unique()->count() < 3 ? ['missing_bureau_data'] : []),
        ];

        return array_values(array_unique(array_filter($warnings)));
    }

    protected function scoreBand(int $score): string
    {
        return match (true) {
            $score >= 800 => 'exceptional',
            $score >= 740 => 'very_good',
            $score >= 670 => 'good',
            $score >= 580 => 'fair',
            default => 'poor',
        };
    }

    protected function numeric(mixed $value): int|float|null
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = trim(Str::lower($value));

        if ($normalized === '' || str_contains($normalized, "can't be calculated")) {
            return null;
        }

        $normalized = str_replace(['$', ',', '%'], '', $normalized);

        if (! is_numeric($normalized)) {
            return null;
        }

        return str_contains($normalized, '.') ? (float) $normalized : (int) $normalized;
    }
}
