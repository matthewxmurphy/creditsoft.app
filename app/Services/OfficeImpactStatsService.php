<?php

namespace App\Services;

use App\Models\BrowserCapture;
use App\Models\Client;
use App\Models\ReportingCycle;
use App\Models\Tradeline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OfficeImpactStatsService
{
    public function __construct(
        protected ClientScoreTimeline $scoreTimeline,
    ) {
    }

    /**
     * @return array{
     *     clients_served:int,
     *     active_clients:int,
     *     debt_removed:float,
     *     negative_items_removed:int,
     *     average_score_lift:int,
     *     minimum_score_lift:int,
     *     maximum_score_lift:int,
     *     clients_with_score_gain:int,
     *     average_client_lifespan_months:float,
     *     longest_client_lifespan_months:float,
     *     graduated_clients:int,
     *     ended_for_nonpayment:int,
     *     ended_other:int,
     *     unknown_outcome_clients:int
     * }
     */
    public function summary(): array
    {
        $clients = Client::query()
            ->with([
                'reportingCycles.bureauSnapshots.tradelines',
                'reportingCycles.browserCaptures',
                'browserCaptures',
                'billingProfile',
                'payments',
            ])
            ->get();

        $debtRemoved = 0.0;
        $negativeItemsRemoved = 0;
        $scoreGains = [];
        $lifespans = [];
        $graduatedClients = 0;
        $endedForNonpayment = 0;
        $endedOther = 0;
        $unknownOutcomeClients = 0;

        foreach ($clients as $client) {
            $impact = $this->removedNegativeImpact($client);
            $debtRemoved += $impact['debt_removed'];
            $negativeItemsRemoved += $impact['negative_items_removed'];

            $scoreLift = $this->scoreLift($client);
            if ($scoreLift > 0) {
                $scoreGains[] = $scoreLift;
            }

            $lifespans[] = $this->clientLifespanMonths($client);

            match ($this->engagementOutcome($client)) {
                'graduated' => $graduatedClients++,
                'nonpayment' => $endedForNonpayment++,
                'other_ended' => $endedOther++,
                'unknown_ended' => $unknownOutcomeClients++,
                default => null,
            };
        }

        return [
            'clients_served' => $clients->count(),
            'active_clients' => $clients->filter(fn (Client $client) => $this->isActiveClient($client))->count(),
            'debt_removed' => round($debtRemoved, 2),
            'negative_items_removed' => $negativeItemsRemoved,
            'average_score_lift' => $scoreGains === [] ? 0 : (int) round(array_sum($scoreGains) / count($scoreGains)),
            'minimum_score_lift' => $scoreGains === [] ? 0 : (int) min($scoreGains),
            'maximum_score_lift' => $scoreGains === [] ? 0 : (int) max($scoreGains),
            'clients_with_score_gain' => count($scoreGains),
            'average_client_lifespan_months' => $lifespans === [] ? 0.0 : round(array_sum($lifespans) / count($lifespans), 1),
            'longest_client_lifespan_months' => $lifespans === [] ? 0.0 : round(max($lifespans), 1),
            'graduated_clients' => $graduatedClients,
            'ended_for_nonpayment' => $endedForNonpayment,
            'ended_other' => $endedOther,
            'unknown_outcome_clients' => $unknownOutcomeClients,
        ];
    }

    /**
     * @return array{debt_removed:float,negative_items_removed:int}
     */
    protected function removedNegativeImpact(Client $client): array
    {
        $cycles = $client->reportingCycles
            ->sortBy(fn (ReportingCycle $cycle) => $cycle->started_at?->timestamp ?? $cycle->created_at?->timestamp ?? 0)
            ->values();

        if ($cycles->count() < 2) {
            return [
                'debt_removed' => 0.0,
                'negative_items_removed' => 0,
            ];
        }

        $first = $this->cycleAccountIndex($cycles->first());
        $latest = $this->cycleAccountIndex($cycles->last());

        $removedKeys = collect($first)
            ->filter(fn (array $account) => $account['negative'])
            ->filter(fn (array $account, string $key) => ! isset($latest[$key]) || ! ($latest[$key]['negative'] ?? false));

        return [
            'debt_removed' => round($removedKeys->sum(fn (array $account) => $account['balance']), 2),
            'negative_items_removed' => $removedKeys->count(),
        ];
    }

    /**
     * @return array<string, array{negative:bool,balance:float}>
     */
    protected function cycleAccountIndex(ReportingCycle $cycle): array
    {
        $tradelines = $cycle->bureauSnapshots->flatMap(fn ($snapshot) => $snapshot->tradelines);

        if ($tradelines->isNotEmpty()) {
            return $tradelines
                ->groupBy(fn (Tradeline $tradeline) => $tradeline->normalized_key)
                ->map(fn (Collection $group) => [
                    'negative' => $group->contains(fn (Tradeline $tradeline) => $tradeline->positive_classification === false),
                    'balance' => round(
                        $group
                            ->map(fn (Tradeline $tradeline) => (float) ($tradeline->balance ?? 0))
                            ->max() ?? 0,
                        2,
                    ),
                ])
                ->all();
        }

        $capture = $cycle->browserCaptures
            ->sortByDesc(fn (BrowserCapture $browserCapture) => $browserCapture->imported_at?->timestamp ?? 0)
            ->first(fn (BrowserCapture $browserCapture) => data_get($browserCapture->metadata, 'smartcredit.profile') === 'three_bureau_report');

        $rows = collect(data_get($capture?->metadata, 'smartcredit.account_matrix', []))
            ->filter(fn ($row) => is_array($row) && filled($row['name'] ?? null))
            ->values();

        return $rows
            ->mapWithKeys(function (array $row, int $index): array {
                $key = $this->smartCreditRowKey($row, $index);

                return [$key => [
                    'negative' => (bool) ($row['negative'] ?? false),
                    'balance' => $this->smartCreditBalance($row),
                ]];
            })
            ->all();
    }

    protected function smartCreditRowKey(array $row, int $index): string
    {
        $name = Str::of((string) ($row['name'] ?? 'account'))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->toString();

        $category = Str::of((string) ($row['category'] ?? 'account'))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->toString();

        return trim($name.'-'.$category.'-'.$index, '-');
    }

    protected function smartCreditBalance(array $row): float
    {
        $evidence = collect($row['evidence'] ?? [])
            ->first(function ($entry): bool {
                $label = Str::lower((string) data_get($entry, 'label'));
                $key = Str::lower((string) data_get($entry, 'key'));

                return str_contains($label, 'balance') || str_contains($key, 'balance');
            });

        if (! is_array($evidence)) {
            return 0.0;
        }

        $value = (string) ($evidence['value'] ?? '');
        preg_match('/-?\$?\s*([0-9,]+(?:\.[0-9]{1,2})?)/', $value, $matches);

        return isset($matches[1]) ? round((float) str_replace(',', '', $matches[1]), 2) : 0.0;
    }

    protected function scoreLift(Client $client): int
    {
        $timeline = $this->scoreTimeline->build($client);
        $scores = collect($timeline['points'] ?? [])
            ->pluck('credit')
            ->filter(fn ($score) => is_numeric($score))
            ->map(fn ($score) => (int) $score)
            ->values();

        if ($scores->count() < 2) {
            return 0;
        }

        return max(0, $scores->last() - $scores->first());
    }

    protected function clientLifespanMonths(Client $client): float
    {
        $startedAt = $this->startedAt($client);
        $endedAt = $this->isActiveClient($client) ? now() : $this->endedAt($client);

        return round(max(0, $startedAt->diffInDays($endedAt)) / 30.4375, 1);
    }

    protected function isActiveClient(Client $client): bool
    {
        return in_array((string) $client->status, ['lead', 'intake', 'active', 'active_review', 'at_risk', 'monitoring'], true);
    }

    protected function endedAt(Client $client): Carbon
    {
        $candidates = collect([
            data_get($client->metadata, 'ended_at'),
            $client->payments->max('paid_at'),
            $client->billingProfile?->last_paid_at,
            $client->reportingCycles->max('reviewed_at'),
            $client->reportingCycles->max('started_at'),
            $client->browserCaptures->max('imported_at'),
            $client->updated_at,
        ])->filter();

        /** @var Carbon|null $endedAt */
        $endedAt = $candidates->sortByDesc(fn ($value) => $value?->timestamp ?? 0)->first();

        return $endedAt instanceof Carbon ? $endedAt : Carbon::parse($endedAt ?? now());
    }

    protected function startedAt(Client $client): Carbon
    {
        $candidates = collect([
            $client->reportingCycles->min('started_at'),
            $client->billingProfile?->started_at,
            $client->payments->min('paid_at'),
            $client->browserCaptures->min('imported_at'),
            $client->created_at,
        ])->filter();

        /** @var Carbon|null $startedAt */
        $startedAt = $candidates->sortBy(fn ($value) => $value?->timestamp ?? PHP_INT_MAX)->first();

        return $startedAt instanceof Carbon ? $startedAt : Carbon::parse($startedAt ?? now());
    }

    protected function engagementOutcome(Client $client): string
    {
        if ($this->isActiveClient($client)) {
            return 'active';
        }

        $reason = Str::lower(trim((string) (
            data_get($client->metadata, 'engagement_outcome')
            ?? data_get($client->metadata, 'ended_reason')
            ?? data_get($client->billingProfile?->metadata, 'engagement_outcome')
            ?? data_get($client->billingProfile?->metadata, 'ended_reason')
            ?? ''
        )));

        if ($reason !== '') {
            if (Str::contains($reason, ['graduated', 'resolved', 'completed', 'complete', 'goal met', 'no longer needed help', 'finished', 'success'])) {
                return 'graduated';
            }

            if (Str::contains($reason, ['nonpayment', 'no payment', 'failed payment', 'payment failure', 'chargeback'])) {
                return 'nonpayment';
            }

            if (Str::contains($reason, ['terminated', 'cancelled', 'canceled', 'ended early', 'ghosted', 'lost contact', 'no response'])) {
                return 'other_ended';
            }
        }

        return match ((string) $client->status) {
            'resolved' => 'graduated',
            'closed', 'archived', 'terminated' => 'unknown_ended',
            default => 'active',
        };
    }
}
