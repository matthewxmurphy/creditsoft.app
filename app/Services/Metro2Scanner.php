<?php

namespace App\Services;

use App\Creditsoft\Config\YamlConfigLoader;
use App\Models\ReportingCycle;
use App\Models\Tradeline;
use App\Models\User;
use App\Models\ViolationCandidate;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Metro2Scanner
{
    public function __construct(
        protected CreditReportComparisonService $comparisonService,
        protected YamlConfigLoader $loader,
        protected ViolationLegalReviewService $legalReview,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function suggestionsForCycle(ReportingCycle $cycle): array
    {
        $cycle->loadMissing('client', 'bureauSnapshots.tradelines', 'violationCandidates');

        $config = $this->loader->load();
        $rules = collect(($config['violation_rules.yaml']['rules'] ?? []))
            ->keyBy('key');

        /** @var Collection<string, bool> $existing */
        $existing = $cycle->violationCandidates
            ->mapWithKeys(fn (ViolationCandidate $violation) => [
                $this->fingerprint($violation->rule_key, $violation->title, $violation->bureau) => true,
            ]);

        $suggestions = collect();

        foreach ($this->comparisonService->comparisonRows($cycle) as $row) {
            /** @var Collection<string, array<string, mixed>> $presentBureaus */
            $presentBureaus = collect($row['bureaus'])->filter();

            if (in_array('status_conflict', $row['mismatches'], true)) {
                $suggestions->push($this->makeSuggestion(
                    $rules,
                    $existing,
                    'metro2_status_conflict',
                    "{$row['label']} status conflict across bureaus",
                    null,
                    $row['label'],
                    $this->formatEvidenceMap($presentBureaus, 'account_status', 'Status'),
                ));
            }

            if (in_array('payment_status_conflict', $row['mismatches'], true)) {
                $suggestions->push($this->makeSuggestion(
                    $rules,
                    $existing,
                    'metro2_payment_status_conflict',
                    "{$row['label']} payment status conflict across bureaus",
                    null,
                    $row['label'],
                    $this->formatEvidenceMap($presentBureaus, 'payment_status', 'Payment status'),
                ));
            }

            if (in_array('open_state_conflict', $row['mismatches'], true)) {
                $suggestions->push($this->makeSuggestion(
                    $rules,
                    $existing,
                    'metro2_open_state_conflict',
                    "{$row['label']} open and closed states conflict",
                    null,
                    $row['label'],
                    $this->formatOpenStateEvidence($presentBureaus),
                ));
            }

            if (in_array('balance_conflict', $row['mismatches'], true)) {
                $suggestions->push($this->makeSuggestion(
                    $rules,
                    $existing,
                    'metro2_balance_conflict',
                    "{$row['label']} balance mismatch across bureaus",
                    null,
                    $row['label'],
                    $this->formatEvidenceMap($presentBureaus, 'balance', 'Balance'),
                ));
            }

            if (
                in_array('date_conflict', $row['mismatches'], true)
                || $presentBureaus->contains(fn (array $bureau) => blank($bureau['date_last_payment'] ?? null))
            ) {
                $suggestions->push($this->makeSuggestion(
                    $rules,
                    $existing,
                    'metro2_missing_key_dates',
                    "{$row['label']} has inconsistent or missing key dates",
                    null,
                    $row['label'],
                    $this->formatEvidenceMap($presentBureaus, 'date_last_payment', 'Last payment'),
                ));
            }

            if (in_array('missing_bureau_entry', $row['mismatches'], true)) {
                $missingBureaus = collect(['experian', 'transunion', 'equifax'])
                    ->reject(fn (string $bureau) => $presentBureaus->has($bureau))
                    ->map(fn (string $bureau) => $this->evidenceItem(
                        detail: Str::headline($bureau).' is missing this tradeline.',
                        bureau: $bureau,
                        field: 'presence',
                        missing: true,
                    ))
                    ->values()
                    ->all();

                $suggestions->push($this->makeSuggestion(
                    $rules,
                    $existing,
                    'metro2_missing_bureau_entry',
                    "{$row['label']} is missing from one or more bureaus",
                    null,
                    $row['label'],
                    $missingBureaus,
                ));
            }

            if (in_array('duplicate_account', $row['mismatches'], true)) {
                $duplicates = collect($row['duplicates'])
                    ->map(fn (string $bureau) => $this->evidenceItem(
                        detail: Str::headline($bureau).' contains duplicate tradeline entries.',
                        bureau: $bureau,
                        field: 'duplicate_entry',
                        tradelineId: data_get($row, "bureaus.{$bureau}.id"),
                    ))
                    ->values()
                    ->all();

                $suggestions->push($this->makeSuggestion(
                    $rules,
                    $existing,
                    'duplicate_account',
                    "{$row['label']} appears more than once",
                    null,
                    $row['label'],
                    $duplicates,
                ));
            }

            if ($presentBureaus->contains(fn (array $bureau) => blank($bureau['account_status'] ?? null) || blank($bureau['creditor_name'] ?? null))) {
                $suggestions->push($this->makeSuggestion(
                    $rules,
                    $existing,
                    'unverifiable_item',
                    "{$row['label']} is incomplete or unverifiable",
                    null,
                    $row['label'],
                    $this->missingFieldEvidence($presentBureaus, [
                        'account_status' => 'Status',
                        'creditor_name' => 'Creditor name',
                    ]),
                ));
            }

            if (
                $presentBureaus->contains(fn (array $bureau) => (bool) ($bureau['is_revolving'] ?? false))
                && $presentBureaus->contains(fn (array $bureau) => blank($bureau['credit_limit'] ?? null) || (float) ($bureau['credit_limit'] ?? 0) <= 0)
            ) {
                $suggestions->push($this->makeSuggestion(
                    $rules,
                    $existing,
                    'metro2_missing_credit_limit',
                    "{$row['label']} is missing a revolving limit",
                    null,
                    $row['label'],
                    $this->formatEvidenceMap($presentBureaus, 'credit_limit', 'Credit limit'),
                ));
            }
        }

        foreach ($cycle->bureauSnapshots as $snapshot) {
            foreach ($snapshot->tradelines as $tradeline) {
                $searchText = Str::lower(trim("{$tradeline->account_status} {$tradeline->remarks}"));

                if ($tradeline->is_open && Str::contains($searchText, 'collection')) {
                    $suggestions->push($this->makeSuggestion(
                        $rules,
                        $existing,
                        'stale_collection_open',
                        "{$tradeline->creditor_name} collection still shows open",
                        $snapshot->bureau,
                        $tradeline->creditor_name,
                        [
                            $this->evidenceItem(
                                detail: Str::headline($snapshot->bureau).' still reports this collection as open.',
                                bureau: $snapshot->bureau,
                                field: 'account_status',
                                tradelineId: $tradeline->getKey(),
                                value: $tradeline->account_status,
                            ),
                            $this->evidenceItem(
                                detail: 'Remarks: '.($tradeline->remarks ?: 'No remarks supplied.'),
                                bureau: $snapshot->bureau,
                                field: 'remarks',
                                tradelineId: $tradeline->getKey(),
                                value: $tradeline->remarks,
                            ),
                        ],
                        tradelineId: $tradeline->getKey(),
                    ));
                }

                if ($tradeline->is_revolving && ($tradeline->utilization_percent ?? 0) > 30) {
                    $suggestions->push($this->makeSuggestion(
                        $rules,
                        $existing,
                        'utilization_over_threshold',
                        "{$tradeline->creditor_name} utilization is over 30%",
                        $snapshot->bureau,
                        $tradeline->creditor_name,
                        [
                            $this->evidenceItem(
                                detail: 'Utilization: '.number_format((float) $tradeline->utilization_percent, 2).'%',
                                bureau: $snapshot->bureau,
                                field: 'utilization_percent',
                                tradelineId: $tradeline->getKey(),
                                value: $tradeline->utilization_percent,
                            ),
                            $this->evidenceItem(
                                detail: 'Balance: '.number_format((float) ($tradeline->balance ?? 0), 2),
                                bureau: $snapshot->bureau,
                                field: 'balance',
                                tradelineId: $tradeline->getKey(),
                                value: $tradeline->balance,
                            ),
                            $this->evidenceItem(
                                detail: 'Limit: '.number_format((float) ($tradeline->credit_limit ?? 0), 2),
                                bureau: $snapshot->bureau,
                                field: 'credit_limit',
                                tradelineId: $tradeline->getKey(),
                                value: $tradeline->credit_limit,
                            ),
                        ],
                        tradelineId: $tradeline->getKey(),
                        priorityAdjustment: min((int) max(((float) ($tradeline->utilization_percent ?? 0)) - 30, 0), 25),
                    ));
                }
            }
        }

        return $suggestions
            ->unique(fn (array $suggestion) => $suggestion['fingerprint'])
            ->sortByDesc(fn (array $suggestion) => $suggestion['priority_score'])
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\ViolationCandidate>
     */
    public function queueSuggestions(ReportingCycle $cycle, ?User $user = null): Collection
    {
        return collect($this->suggestionsForCycle($cycle))
            ->reject(fn (array $suggestion) => $suggestion['already_logged'])
            ->map(function (array $suggestion) use ($cycle, $user): ViolationCandidate {
                return ViolationCandidate::firstOrCreate([
                    'client_id' => $cycle->client_id,
                    'reporting_cycle_id' => $cycle->getKey(),
                    'rule_key' => $suggestion['rule_key'],
                    'title' => $suggestion['title'],
                    'bureau' => $suggestion['bureau'],
                ], [
                    'tradeline_id' => $suggestion['tradeline_id'],
                    'severity' => $suggestion['severity'],
                    'priority_score' => $suggestion['priority_score'],
                    'status' => 'open',
                    'next_action' => $suggestion['next_action'],
                    'evidence' => array_values($suggestion['evidence']),
                    'confirmed_by' => null,
                    'confirmed_at' => null,
                ]);
            })
            ->filter(fn (ViolationCandidate $candidate) => $candidate->wasRecentlyCreated)
            ->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<string, array<string, mixed>>  $rules
     * @param  \Illuminate\Support\Collection<string, bool>  $existing
     * @param  list<array<string, mixed>>  $evidence
     * @return array<string, mixed>
     */
    protected function makeSuggestion(
        Collection $rules,
        Collection $existing,
        string $ruleKey,
        string $title,
        ?string $bureau,
        ?string $accountLabel,
        array $evidence,
        ?int $tradelineId = null,
        int $priorityAdjustment = 0,
    ): array {
        $rule = $rules->get($ruleKey, []);
        $fingerprint = $this->fingerprint($ruleKey, $title, $bureau);

        return [
            'fingerprint' => $fingerprint,
            'rule_key' => $ruleKey,
            'title' => $title,
            'bureau' => $bureau,
            'account_label' => $accountLabel,
            'severity' => data_get($rule, 'severity', 'medium'),
            'category' => data_get($rule, 'category', 'metro2'),
            'description' => data_get($rule, 'description'),
            'reference' => data_get($rule, 'reference'),
            'next_action' => data_get($rule, 'next_action', 'Review the report evidence and prepare the next dispute step.'),
            'evidence' => array_values(array_filter($evidence)),
            'legal_frameworks' => $this->legalReview->frameworksFor($ruleKey, $evidence, $title),
            'tradeline_id' => $tradelineId,
            'priority_score' => $this->priorityScore($rule, count($evidence), $priorityAdjustment),
            'already_logged' => $existing->has($fingerprint),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<string, array<string, mixed>>  $bureaus
     * @return list<array<string, mixed>>
     */
    protected function formatEvidenceMap(Collection $bureaus, string $field, string $label): array
    {
        return $bureaus
            ->map(function (array $bureau, string $bureauName) use ($field, $label): array {
                $value = $bureau[$field] ?? null;

                return $this->evidenceItem(
                    detail: Str::headline($bureauName)." {$label}: ".(blank($value) ? 'missing' : (string) $value),
                    bureau: $bureauName,
                    field: $field,
                    tradelineId: $bureau['id'] ?? null,
                    value: is_scalar($value) || $value === null ? $value : null,
                    missing: blank($value),
                );
            })
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<string, array<string, mixed>>  $bureaus
     * @param  array<string, string>  $fields
     * @return list<array<string, mixed>>
     */
    protected function missingFieldEvidence(Collection $bureaus, array $fields): array
    {
        return $bureaus
            ->flatMap(function (array $bureau, string $bureauName) use ($fields): array {
                $details = [];

                foreach ($fields as $field => $label) {
                    if (blank($bureau[$field] ?? null)) {
                        $details[] = $this->evidenceItem(
                            detail: Str::headline($bureauName)." {$label} is missing.",
                            bureau: $bureauName,
                            field: $field,
                            tradelineId: $bureau['id'] ?? null,
                            missing: true,
                        );
                    }
                }

                return $details;
            })
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<string, array<string, mixed>>  $bureaus
     * @return list<array<string, mixed>>
     */
    protected function formatOpenStateEvidence(Collection $bureaus): array
    {
        return $bureaus
            ->map(fn (array $bureau, string $bureauName) => $this->evidenceItem(
                detail: Str::headline($bureauName).' open state: '.($bureau['is_open'] ? 'open' : 'closed'),
                bureau: $bureauName,
                field: 'is_open',
                tradelineId: $bureau['id'] ?? null,
                value: $bureau['is_open'] ?? null,
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function evidenceItem(
        string $detail,
        ?string $bureau = null,
        ?string $field = null,
        ?int $tradelineId = null,
        mixed $value = null,
        bool $missing = false,
    ): array {
        return array_filter([
            'detail' => $detail,
            'bureau' => $bureau,
            'field' => $field,
            'tradeline_id' => $tradelineId,
            'value' => $value,
            'missing' => $missing ?: null,
        ], fn (mixed $value, string $key) => ! ($value === null && $key !== 'detail'), ARRAY_FILTER_USE_BOTH);
    }

    protected function fingerprint(string $ruleKey, string $title, ?string $bureau): string
    {
        return implode('|', [$ruleKey, $title, $bureau ?: 'all']);
    }

    protected function severityWeight(string $severity): int
    {
        return match ($severity) {
            'high' => 3,
            'medium' => 2,
            default => 1,
        };
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function priorityScore(array $rule, int $evidenceCount, int $priorityAdjustment = 0): int
    {
        $severityBase = match (data_get($rule, 'severity', 'medium')) {
            'high' => 78,
            'medium' => 54,
            default => 32,
        };

        $categoryBonus = match (data_get($rule, 'category')) {
            'metro2' => 10,
            'compliance' => 7,
            'strategy' => 2,
            default => 0,
        };

        $score = $severityBase
            + $categoryBonus
            + (int) data_get($rule, 'priority_boost', 0)
            + min($evidenceCount * 3, 12)
            + $priorityAdjustment;

        return (int) min($score, 100);
    }
}
