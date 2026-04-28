<?php

namespace App\Services;

use App\Models\BrowserCapture;
use App\Models\ReportingCycle;
use App\Models\Tradeline;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CreditReportComparisonService
{
    public function __construct(
        protected SmartCreditCaptureParser $smartCreditCaptureParser,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function comparisonRows(ReportingCycle $cycle): array
    {
        $cycle->loadMissing('bureauSnapshots.tradelines', 'browserCaptures');

        $tradelines = $cycle->bureauSnapshots->flatMap(fn ($snapshot) => $snapshot->tradelines);

        if ($tradelines->isNotEmpty()) {
            return $this->comparisonRowsFromTradelines($tradelines);
        }

        $smartCreditCapture = $this->latestSmartCreditCapture($cycle);

        if ($smartCreditCapture) {
            return $this->comparisonRowsFromSmartCredit($smartCreditCapture);
        }

        return [];
    }

    /**
     * @param  Collection<int, Tradeline>  $tradelines
     * @return list<array<string, mixed>>
     */
    protected function comparisonRowsFromTradelines(Collection $tradelines): array
    {
        /** @var Collection<string, Collection<int, Tradeline>> $grouped */
        $grouped = $tradelines->groupBy(fn (Tradeline $tradeline) => $tradeline->normalized_key);

        return $grouped
            ->map(function (Collection $tradelines, string $key): array {
                $byBureau = [
                    'experian' => null,
                    'transunion' => null,
                    'equifax' => null,
                ];

                $duplicates = [];

                foreach ($tradelines->groupBy(fn (Tradeline $tradeline) => $tradeline->bureauSnapshot->bureau) as $bureau => $entries) {
                    $byBureau[$bureau] = $this->serializeTradeline($entries->first());

                    if ($entries->count() > 1) {
                        $duplicates[] = $bureau;
                    }
                }

                $mismatches = array_values(array_filter([
                    $this->statusMismatch($byBureau) ? 'status_conflict' : null,
                    $this->paymentMismatch($byBureau) ? 'payment_status_conflict' : null,
                    $this->openStateMismatch($byBureau) ? 'open_state_conflict' : null,
                    $this->balanceMismatch($byBureau) ? 'balance_conflict' : null,
                    $this->dateMismatch($byBureau) ? 'date_conflict' : null,
                    count(array_filter($byBureau)) < 3 ? 'missing_bureau_entry' : null,
                    $duplicates !== [] ? 'duplicate_account' : null,
                ]));

                return [
                    'key' => $key,
                    'label' => $tradelines->first()?->creditor_name ?? 'Unknown account',
                    'duplicates' => $duplicates,
                    'mismatches' => $mismatches,
                    'severity' => count($mismatches) >= 3 ? 'high' : (count($mismatches) >= 1 ? 'medium' : 'low'),
                    'bureaus' => $byBureau,
                    'coverage_label' => null,
                ];
            })
            ->sortByDesc(fn (array $row) => count($row['mismatches']))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function reviewSummary(ReportingCycle $cycle): array
    {
        $cycle->loadMissing('bureauSnapshots.tradelines', 'client.reportingCycles.bureauSnapshots.tradelines', 'violationCandidates', 'browserCaptures');

        $tradelines = $cycle->bureauSnapshots->flatMap(fn ($snapshot) => $snapshot->tradelines);

        if ($tradelines->isEmpty()) {
            $smartCreditCapture = $this->latestSmartCreditCapture($cycle);

            if ($smartCreditCapture) {
                return $this->reviewSummaryFromSmartCredit($cycle, $smartCreditCapture);
            }
        }

        $previousCycle = $cycle->client
            ->reportingCycles
            ->where('id', '!=', $cycle->getKey())
            ->sortByDesc('started_at')
            ->first();

        $previousKeys = $previousCycle
            ? $previousCycle->bureauSnapshots->flatMap(fn ($snapshot) => $snapshot->tradelines)->pluck('normalized_key')->unique()
            : collect();

        return [
            'total_accounts' => $tradelines->count(),
            'open_accounts' => $tradelines->where('is_open', true)->count(),
            'closed_accounts' => $tradelines->where('is_open', false)->count(),
            'negative_accounts' => $tradelines->where('positive_classification', false)->count(),
            'positive_accounts' => $tradelines->where('positive_classification', true)->count(),
            'revolving_accounts' => $tradelines->where('is_revolving', true)->count(),
            'over_thirty_percent' => $tradelines
                ->filter(fn (Tradeline $tradeline) => ($tradeline->utilization_percent ?? 0) > 30)
                ->count(),
            'priority_disputes' => $cycle->violationCandidates->where('severity', 'high')->count(),
            'changed_since_last_cycle' => $tradelines
                ->pluck('normalized_key')
                ->unique()
                ->diff($previousKeys)
                ->count(),
        ];
    }

    protected function latestSmartCreditCapture(ReportingCycle $cycle): ?BrowserCapture
    {
        return $cycle->browserCaptures
            ->first(fn (BrowserCapture $capture) => data_get($capture->metadata, 'smartcredit.profile') === 'three_bureau_report');
    }

    /**
     * @return array<string, mixed>
     */
    protected function smartCreditPayload(BrowserCapture $capture): array
    {
        $payload = data_get($capture->metadata, 'smartcredit', []);
        $matrixCount = (int) data_get($payload, 'account_matrix_count', 0);
        $matrix = data_get($payload, 'account_matrix', []);

        if ($matrixCount > 0 && is_array($matrix) && count($matrix) >= $matrixCount) {
            return is_array($payload) ? $payload : [];
        }

        if (! is_string($capture->content_html) || trim($capture->content_html) === '') {
            return is_array($payload) ? $payload : [];
        }

        $parsed = $this->smartCreditCaptureParser->parse($capture->content_html, $capture->page_title, $capture->page_url);

        if (! is_array($parsed) || ($parsed['provider'] ?? null) !== 'smartcredit') {
            return is_array($payload) ? $payload : [];
        }

        return array_replace_recursive(is_array($payload) ? $payload : [], $parsed);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function comparisonRowsFromSmartCredit(BrowserCapture $capture): array
    {
        $rows = collect(data_get($this->smartCreditPayload($capture), 'account_matrix', []))
            ->filter(fn ($row) => is_array($row) && filled($row['name'] ?? null))
            ->values();

        return $rows
            ->map(function (array $row, int $index): array {
                $bureaus = $this->serializeSmartCreditBureaus($row);
                $mismatches = $this->smartCreditMismatchFlags($row, $bureaus);

                return [
                    'key' => $this->smartCreditRowKey($row, $index),
                    'label' => (string) ($row['name'] ?? 'Unknown account'),
                    'duplicates' => [],
                    'mismatches' => $mismatches,
                    'severity' => $this->smartCreditSeverity($row, $mismatches),
                    'bureaus' => $bureaus,
                    'coverage_label' => data_get($row, 'coverage_label'),
                ];
            })
            ->sortByDesc(fn (array $row) => count($row['mismatches']))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function reviewSummaryFromSmartCredit(ReportingCycle $cycle, BrowserCapture $capture): array
    {
        $payload = $this->smartCreditPayload($capture);
        $rows = collect(data_get($payload, 'account_matrix', []))
            ->filter(fn ($row) => is_array($row) && filled($row['name'] ?? null))
            ->values();

        $totalAccounts = (int) data_get($payload, 'account_matrix_count', $rows->count());
        $currentKeys = $rows->values()->map(fn (array $row, int $index) => $this->smartCreditRowKey($row, $index));
        $previousCapture = $cycle->client->browserCaptures()
            ->where('reporting_cycle_id', '!=', $cycle->getKey())
            ->where('metadata->smartcredit->profile', 'three_bureau_report')
            ->latest('imported_at')
            ->first();
        $previousPayload = $previousCapture ? $this->smartCreditPayload($previousCapture) : [];
        $previousKeys = collect(data_get($previousPayload, 'account_matrix', []))
            ->filter(fn ($row) => is_array($row) && filled($row['name'] ?? null))
            ->values()
            ->map(fn (array $row, int $index) => $this->smartCreditRowKey($row, $index));

        return [
            'total_accounts' => $totalAccounts,
            'open_accounts' => $rows->filter(fn (array $row) => $this->smartCreditOpenState((string) ($row['status'] ?? '')) === true)->count(),
            'closed_accounts' => $rows->filter(fn (array $row) => $this->smartCreditOpenState((string) ($row['status'] ?? '')) === false)->count(),
            'negative_accounts' => $rows->filter(fn (array $row) => (bool) ($row['negative'] ?? false))->count(),
            'positive_accounts' => $rows->filter(fn (array $row) => ! (bool) ($row['negative'] ?? false))->count(),
            'revolving_accounts' => $rows->filter(fn (array $row) => str_contains(Str::lower((string) ($row['category'] ?? '')), 'revolving'))->count(),
            'over_thirty_percent' => $rows->filter(fn (array $row) => $this->parsePercentValue($row['utilization'] ?? null) > 30)->count(),
            'priority_disputes' => $cycle->violationCandidates->where('severity', 'high')->count(),
            'changed_since_last_cycle' => $currentKeys->diff($previousKeys)->count(),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>|null>  $byBureau
     */
    protected function statusMismatch(array $byBureau): bool
    {
        return collect($byBureau)
            ->filter()
            ->pluck('account_status')
            ->filter()
            ->unique()
            ->count() > 1;
    }

    /**
     * @param  array<string, array<string, mixed>|null>  $byBureau
     */
    protected function balanceMismatch(array $byBureau): bool
    {
        return collect($byBureau)
            ->filter()
            ->pluck('balance')
            ->filter(fn ($value) => $value !== null)
            ->unique()
            ->count() > 1;
    }

    /**
     * @param  array<string, array<string, mixed>|null>  $byBureau
     */
    protected function paymentMismatch(array $byBureau): bool
    {
        return collect($byBureau)
            ->filter()
            ->pluck('payment_status')
            ->filter()
            ->unique()
            ->count() > 1;
    }

    /**
     * @param  array<string, array<string, mixed>|null>  $byBureau
     */
    protected function openStateMismatch(array $byBureau): bool
    {
        return collect($byBureau)
            ->filter()
            ->pluck('is_open')
            ->filter(fn ($value) => $value !== null)
            ->unique()
            ->count() > 1;
    }

    /**
     * @param  array<string, array<string, mixed>|null>  $byBureau
     */
    protected function dateMismatch(array $byBureau): bool
    {
        return collect($byBureau)
            ->filter()
            ->pluck('date_last_payment')
            ->filter()
            ->unique()
            ->count() > 1;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeTradeline(Tradeline $tradeline): array
    {
        return [
            'id' => $tradeline->getKey(),
            'creditor_name' => $tradeline->creditor_name,
            'account_type' => $tradeline->account_type,
            'is_revolving' => $tradeline->is_revolving,
            'balance' => $tradeline->balance,
            'credit_limit' => $tradeline->credit_limit,
            'utilization_percent' => $tradeline->utilization_percent,
            'account_status' => $tradeline->account_status,
            'payment_status' => $tradeline->payment_status,
            'is_open' => $tradeline->is_open,
            'date_last_payment' => optional($tradeline->date_last_payment)->toDateString(),
            'remarks' => $tradeline->remarks,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{experian:array<string, mixed>|null,transunion:array<string, mixed>|null,equifax:array<string, mixed>|null}
     */
    protected function serializeSmartCreditBureaus(array $row): array
    {
        $balanceEvidence = $this->smartCreditEvidenceByKey($row, 'balance');
        $paymentEvidence = $this->smartCreditEvidenceByKey($row, 'payment');
        $pastDueEvidence = $this->smartCreditEvidenceByKey($row, 'past_due');
        $status = (string) ($row['status'] ?? '');
        $category = (string) ($row['category'] ?? '');
        $balance = $this->parseMoneyValue($balanceEvidence['value'] ?? null);
        $creditLimit = $this->parseCreditLimitValue($balanceEvidence['value'] ?? null);
        $dateLastPayment = $this->parseDateValue($paymentEvidence['value'] ?? null);
        $paymentStatus = $paymentEvidence['value'] ?? $pastDueEvidence['value'] ?? null;
        $isOpen = $this->smartCreditOpenState($status);

        $payload = [];

        foreach (['experian', 'transunion', 'equifax'] as $bureau) {
            $coverageState = data_get($row, "coverage.{$bureau}", 'unknown');

            if ($coverageState === 'missing') {
                $payload[$bureau] = null;

                continue;
            }

            $payload[$bureau] = [
                'id' => null,
                'creditor_name' => $row['name'] ?? 'Unknown account',
                'account_type' => $category ?: null,
                'is_revolving' => str_contains(Str::lower($category), 'revolving'),
                'balance' => $balance,
                'credit_limit' => $creditLimit,
                'utilization_percent' => $this->parsePercentValue($row['utilization'] ?? null),
                'account_status' => $status !== '' ? $status : null,
                'payment_status' => $paymentStatus,
                'is_open' => $isOpen,
                'date_last_payment' => $dateLastPayment,
                'remarks' => data_get($row, 'coverage_label'),
                'coverage_state' => $coverageState,
                'coverage_label' => data_get($row, 'coverage_label'),
            ];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array<string, mixed>|null>  $bureaus
     * @return list<string>
     */
    protected function smartCreditMismatchFlags(array $row, array $bureaus): array
    {
        $coverageStates = collect(data_get($row, 'coverage', []))
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->values();
        $presentBureaus = collect($bureaus)->filter();

        $mismatches = array_values(array_filter([
            $coverageStates->contains('missing') ? 'missing_bureau_entry' : null,
            $presentBureaus->count() === 1 || $coverageStates->contains('only') ? 'single_bureau_reporting' : null,
            $this->smartCreditFieldMismatch($bureaus, 'account_status') ? 'status_conflict' : null,
            $this->smartCreditFieldMismatch($bureaus, 'payment_status') ? 'payment_status_conflict' : null,
            $this->smartCreditFieldMismatch($bureaus, 'balance') ? 'balance_conflict' : null,
            $this->smartCreditFieldMismatch($bureaus, 'date_last_payment', true) ? 'date_conflict' : null,
            (bool) ($row['negative'] ?? false) ? 'negative_reporting' : null,
        ]));

        return array_values(array_unique($mismatches));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function smartCreditSeverity(array $row, array $mismatches): string
    {
        if ((bool) ($row['negative'] ?? false) || count($mismatches) >= 3) {
            return 'high';
        }

        if ($mismatches !== []) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function smartCreditFieldMismatch(array $bureaus, string $field, bool $treatMissingAsConflict = false): bool
    {
        $presentBureaus = collect($bureaus)->filter();

        if ($presentBureaus->count() <= 1) {
            return false;
        }

        $values = $presentBureaus
            ->map(fn (array $bureau) => $bureau[$field] ?? null)
            ->values();
        $nonBlank = $values
            ->filter(fn ($value) => ! blank($value))
            ->values();

        if ($nonBlank->count() <= 1) {
            return $treatMissingAsConflict && $nonBlank->count() === 1 && $values->contains(fn ($value) => blank($value));
        }

        return $nonBlank
            ->map(fn ($value) => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value)
            ->unique()
            ->count() > 1;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function smartCreditEvidenceByKey(array $row, string $key): array
    {
        return collect($row['evidence'] ?? [])
            ->first(fn ($item) => is_array($item) && data_get($item, 'key') === $key) ?? [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function smartCreditRowKey(array $row, int $index): string
    {
        return 'smartcredit_'.sha1(json_encode([
            'index' => $index,
            'name' => $row['name'] ?? null,
            'status' => $row['status'] ?? null,
            'category' => $row['category'] ?? null,
            'coverage' => $row['coverage'] ?? [],
            'evidence' => $row['evidence'] ?? [],
        ]));
    }

    protected function parseMoneyValue(mixed $value): ?int
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        preg_match_all('/\\$([0-9,]+)/', $value, $matches);
        $amount = $matches[1][0] ?? null;

        if (! is_string($amount)) {
            return null;
        }

        return (int) str_replace(',', '', $amount);
    }

    protected function parseCreditLimitValue(mixed $value): ?int
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        preg_match_all('/\\$([0-9,]+)/', $value, $matches);
        $amounts = $matches[1] ?? [];
        $amount = count($amounts) > 1 ? end($amounts) : null;

        if (! is_string($amount)) {
            return null;
        }

        return (int) str_replace(',', '', $amount);
    }

    protected function parsePercentValue(mixed $value): ?int
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        preg_match('/([0-9]+)/', $value, $matches);
        $amount = $matches[1] ?? null;

        return is_string($amount) ? (int) $amount : null;
    }

    protected function parseDateValue(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        if (! preg_match('/(\\d{1,2}\\/\\d{1,2}\\/\\d{4})/', $value, $matches)) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::createFromFormat('n/j/Y', $matches[1])->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function smartCreditOpenState(string $status): ?bool
    {
        $normalized = Str::lower(trim($status));

        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, 'open')) {
            return true;
        }

        if (
            str_contains($normalized, 'closed')
            || str_contains($normalized, 'paid')
            || str_contains($normalized, 'chargeoff')
            || str_contains($normalized, 'charge-off')
            || str_contains($normalized, 'collection')
            || str_contains($normalized, 'derogatory')
        ) {
            return false;
        }

        return null;
    }
}
