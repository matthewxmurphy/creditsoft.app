<?php

namespace App\Services;

use App\Models\BrowserCapture;
use App\Models\Client;
use App\Models\ReportingCycle;
use App\Models\Tradeline;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OfficeImpactStatsService
{
    public function __construct(
        protected ClientScoreTimeline $scoreTimeline,
        protected InstallerState $installerState,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $clients = Client::query()
            ->with([
                'reportingCycles.bureauSnapshots.tradelines',
                'reportingCycles.browserCaptures' => fn ($query) => $query
                    ->select([
                        'id',
                        'client_id',
                        'reporting_cycle_id',
                        'page_title',
                        'metadata',
                        'imported_at',
                        'deleted_at',
                    ]),
                'browserCaptures' => fn ($query) => $query
                    ->select([
                        'id',
                        'client_id',
                        'reporting_cycle_id',
                        'page_title',
                        'metadata',
                        'imported_at',
                        'deleted_at',
                    ]),
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
        $clientSignals = collect();

        foreach ($clients as $client) {
            $impact = $this->removedNegativeImpact($client);
            $debtRemoved += $impact['debt_removed'];
            $negativeItemsRemoved += $impact['negative_items_removed'];

            $scoreLift = $this->scoreLift($client);
            if ($scoreLift > 0) {
                $scoreGains[] = $scoreLift;
            }

            $startedAt = $this->startedAt($client);
            $isActive = $this->isActiveClient($client);
            $endedAt = $isActive ? null : $this->endedAt($client);
            $lifespanMonths = $this->clientLifespanMonths($client, $startedAt, $endedAt);
            $outcome = $this->engagementOutcome($client);

            $lifespans[] = $lifespanMonths;
            $clientSignals->push([
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'active' => $isActive,
                'outcome' => $outcome,
                'city' => $this->normalizedCity($client->city),
                'state' => $this->normalizedState($client->state),
                'lifespan_months' => $lifespanMonths,
            ]);

            match ($outcome) {
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
            'privacy' => [
                'contains_customer_identifiers' => false,
                'fields_excluded' => ['name', 'email', 'phone', 'address', 'postal_code', 'date_of_birth', 'ssn', 'notes', 'documents', 'attachments'],
                'description' => 'Aggregate office statistics only. No person-level customer records are returned.',
            ],
            'client_age' => $this->clientAgeStats($clientSignals),
            'lifecycle' => $this->lifecycleStats($clientSignals),
            'geography' => $this->geographyStats($clientSignals),
            'seasonality' => $this->seasonalityStats($clientSignals),
            'forecast' => $this->forecastStats($clientSignals),
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

    protected function clientLifespanMonths(Client $client, ?CarbonInterface $startedAt = null, ?CarbonInterface $endedAt = null): float
    {
        $startedAt ??= $this->startedAt($client);
        $endedAt ??= $this->isActiveClient($client) ? now() : $this->endedAt($client);

        return round(max(0, $startedAt->diffInDays($endedAt)) / 30.4375, 1);
    }

    protected function isActiveClient(Client $client): bool
    {
        return in_array(Str::lower((string) $client->status), ['lead', 'intake', 'active', 'active_review', 'at_risk', 'monitoring'], true);
    }

    protected function endedAt(Client $client): CarbonInterface
    {
        $candidates = collect([
            data_get($client->metadata, 'ended_at'),
            data_get($client->metadata, 'companion.last_status_event.detected_at'),
            data_get($client->metadata, 'imports.disputefox.lists.clients.raw_row.Ended'),
            data_get($client->metadata, 'imports.disputefox.lists.clients.raw_row.Closed Date'),
            data_get($client->metadata, 'imports.disputefox.lists.clients.raw_row.Date Closed'),
            data_get($client->metadata, 'imports.disputefox.lists.clients.raw_row.Terminated Date'),
            data_get($client->metadata, 'imports.disputefox.lists.clients.raw_row.Cancel Date'),
            data_get($client->metadata, 'imports.disputefox.lists.clients.raw_row.Cancelled Date'),
            $client->payments->max('paid_at'),
            $client->billingProfile?->last_paid_at,
            $client->reportingCycles->max('reviewed_at'),
            $client->reportingCycles->max('started_at'),
            $client->browserCaptures->max('imported_at'),
            $client->updated_at,
        ])
            ->map(fn ($value): ?CarbonInterface => $this->parseDateCandidate($value))
            ->filter();

        /** @var CarbonInterface|null $endedAt */
        $endedAt = $candidates->sortByDesc(fn (CarbonInterface $value): int => $value->timestamp)->first();

        return $endedAt ?? now();
    }

    protected function startedAt(Client $client): CarbonInterface
    {
        $candidates = collect([
            data_get($client->metadata, 'imports.disputefox.lists.clients.raw_row.Started'),
            data_get($client->metadata, 'imports.disputefox.lists.clients.raw_row.Start Date'),
            data_get($client->metadata, 'imports.disputefox.lists.clients.raw_row.Client Since'),
            data_get($client->metadata, 'imports.disputefox.lists.clients.raw_row.Added Date'),
            data_get($client->metadata, 'imports.disputefox.lists.leads.raw_row.Added Date'),
            data_get($client->metadata, 'imports.disputefox.lists.leads.raw_row.Created'),
            $client->reportingCycles->min('started_at'),
            $client->billingProfile?->started_at,
            $client->payments->min('paid_at'),
            $client->browserCaptures->min('imported_at'),
            $client->created_at,
        ])
            ->map(fn ($value): ?CarbonInterface => $this->parseDateCandidate($value))
            ->filter();

        /** @var CarbonInterface|null $startedAt */
        $startedAt = $candidates->sortBy(fn (CarbonInterface $value): int => $value->timestamp)->first();

        return $startedAt ?? now();
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

        return match (Str::lower((string) $client->status)) {
            'resolved', 'graduated', 'finished' => 'graduated',
            'canceled', 'cancelled', 'fired' => 'other_ended',
            'closed', 'archived', 'terminated', 'inactive' => 'unknown_ended',
            default => 'unknown_ended',
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $signals
     * @return array<string, mixed>
     */
    protected function clientAgeStats(Collection $signals): array
    {
        $ages = $signals
            ->map(fn (array $signal): int => $this->daysSince($signal['started_at'] ?? null))
            ->filter(fn (int $days): bool => $days >= 0)
            ->values();

        $activeAges = $signals
            ->filter(fn (array $signal): bool => (bool) ($signal['active'] ?? false))
            ->map(fn (array $signal): int => $this->daysSince($signal['started_at'] ?? null))
            ->filter(fn (int $days): bool => $days >= 0)
            ->values();

        $oldestDays = (int) ($ages->max() ?? 0);
        $oldestActiveDays = (int) ($activeAges->max() ?? 0);
        $newestDays = (int) ($ages->min() ?? 0);

        return [
            'oldest_client_age_days' => $oldestDays,
            'oldest_client_age_months' => $this->daysToMonths($oldestDays),
            'oldest_active_client_age_days' => $oldestActiveDays,
            'oldest_active_client_age_months' => $this->daysToMonths($oldestActiveDays),
            'newest_client_age_days' => $newestDays,
            'newest_client_age_months' => $this->daysToMonths($newestDays),
            'average_current_age_months' => $ages->isEmpty() ? 0.0 : $this->daysToMonths((int) round($ages->avg())),
            'average_active_age_months' => $activeAges->isEmpty() ? 0.0 : $this->daysToMonths((int) round($activeAges->avg())),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $signals
     * @return array<string, mixed>
     */
    protected function lifecycleStats(Collection $signals): array
    {
        $served = $signals->count();
        $active = $signals->filter(fn (array $signal): bool => (bool) ($signal['active'] ?? false))->count();
        $ended = max(0, $served - $active);
        $endedSignals = $signals->reject(fn (array $signal): bool => (bool) ($signal['active'] ?? false))->values();
        $endedLifespans = $endedSignals
            ->pluck('lifespan_months')
            ->filter(fn ($months): bool => is_numeric($months))
            ->map(fn ($months): float => (float) $months)
            ->values();

        return [
            'clients_served' => $served,
            'active_clients' => $active,
            'ended_clients' => $ended,
            'ended_rate_percent' => $this->percent($ended, $served),
            'outcome_mix' => $this->outcomeMix($signals),
            'average_ended_lifespan_months' => $endedLifespans->isEmpty() ? 0.0 : round((float) $endedLifespans->avg(), 1),
            'ended_by_month' => $this->monthSeries($endedSignals, 'ended_at'),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $signals
     * @return array<string, mixed>
     */
    protected function geographyStats(Collection $signals): array
    {
        $businessLocation = $this->businessLocation();
        $businessState = $businessLocation['state'] ?? null;

        $states = $signals
            ->groupBy(fn (array $signal): string => (string) ($signal['state'] ?? 'Unknown'))
            ->map(function (Collection $group, string $state) use ($businessState): array {
                $ended = $group->reject(fn (array $signal): bool => (bool) ($signal['active'] ?? false));
                $lifespans = $group
                    ->pluck('lifespan_months')
                    ->filter(fn ($months): bool => is_numeric($months))
                    ->map(fn ($months): float => (float) $months)
                    ->values();

                return [
                    'state' => $state,
                    'clients_served' => $group->count(),
                    'active_clients' => $group->filter(fn (array $signal): bool => (bool) ($signal['active'] ?? false))->count(),
                    'ended_clients' => $ended->count(),
                    'ended_for_nonpayment' => $group->filter(fn (array $signal): bool => ($signal['outcome'] ?? null) === 'nonpayment')->count(),
                    'same_as_business_state' => $businessState !== null && $state === $businessState,
                    'average_lifespan_months' => $lifespans->isEmpty() ? 0.0 : round((float) $lifespans->avg(), 1),
                ];
            })
            ->sortBy([
                ['clients_served', 'desc'],
                ['state', 'asc'],
            ])
            ->values();

        $cities = $signals
            ->groupBy(fn (array $signal): string => ((string) ($signal['city'] ?? 'Unknown')).'|'.((string) ($signal['state'] ?? 'Unknown')))
            ->map(function (Collection $group, string $locationKey) use ($businessLocation): array {
                [$city, $state] = array_pad(explode('|', $locationKey, 2), 2, 'Unknown');
                $ended = $group->reject(fn (array $signal): bool => (bool) ($signal['active'] ?? false));
                $lifespans = $group
                    ->pluck('lifespan_months')
                    ->filter(fn ($months): bool => is_numeric($months))
                    ->map(fn ($months): float => (float) $months)
                    ->values();
                $businessCity = $businessLocation['city'] ?? null;
                $businessState = $businessLocation['state'] ?? null;

                return [
                    'city' => $city,
                    'state' => $state,
                    'clients_served' => $group->count(),
                    'active_clients' => $group->filter(fn (array $signal): bool => (bool) ($signal['active'] ?? false))->count(),
                    'ended_clients' => $ended->count(),
                    'ended_for_nonpayment' => $group->filter(fn (array $signal): bool => ($signal['outcome'] ?? null) === 'nonpayment')->count(),
                    'same_as_business_city' => $businessCity !== null
                        && $city === $businessCity
                        && ($businessState === null || $state === $businessState),
                    'same_as_business_state' => $businessState !== null && $state === $businessState,
                    'average_lifespan_months' => $lifespans->isEmpty() ? 0.0 : round((float) $lifespans->avg(), 1),
                ];
            })
            ->sortBy([
                ['clients_served', 'desc'],
                ['state', 'asc'],
                ['city', 'asc'],
            ])
            ->values();

        return [
            'business_location' => $businessLocation,
            'states_represented' => $states->reject(fn (array $state): bool => $state['state'] === 'Unknown')->count(),
            'unknown_state_clients' => (int) ($states->firstWhere('state', 'Unknown')['clients_served'] ?? 0),
            'cities_represented' => $cities->reject(fn (array $city): bool => $city['city'] === 'Unknown')->count(),
            'unknown_city_clients' => (int) $cities
                ->filter(fn (array $city): bool => $city['city'] === 'Unknown')
                ->sum('clients_served'),
            'states' => $states->all(),
            'top_states' => $states->take(8)->values()->all(),
            'cities' => $cities->all(),
            'top_cities' => $cities->take(12)->values()->all(),
            'customer_city_vs_business_location' => $this->customerCityVsBusinessLocation($signals, $businessLocation),
        ];
    }

    /**
     * @return array{configured:bool,city:?string,state:?string,label:?string}
     */
    protected function businessLocation(): array
    {
        $state = $this->installerState->read();
        $city = $this->normalizedCity(data_get($state, 'business_city'));
        $stateCode = $this->normalizedState(data_get($state, 'business_state'));
        $hasCity = $city !== 'Unknown';
        $hasState = $stateCode !== 'Unknown';

        return [
            'configured' => $hasCity || $hasState,
            'city' => $hasCity ? $city : null,
            'state' => $hasState ? $stateCode : null,
            'label' => match (true) {
                $hasCity && $hasState => "{$city}, {$stateCode}",
                $hasCity => $city,
                $hasState => $stateCode,
                default => null,
            },
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $signals
     * @param  array<string, mixed>  $businessLocation
     * @return array<string, mixed>
     */
    protected function customerCityVsBusinessLocation(Collection $signals, array $businessLocation): array
    {
        $total = $signals->count();
        $businessCity = $businessLocation['city'] ?? null;
        $businessState = $businessLocation['state'] ?? null;
        $knownCitySignals = $signals->filter(fn (array $signal): bool => ($signal['city'] ?? 'Unknown') !== 'Unknown');
        $knownStateSignals = $signals->filter(fn (array $signal): bool => ($signal['state'] ?? 'Unknown') !== 'Unknown');

        $sameCity = $businessCity === null
            ? 0
            : $knownCitySignals
                ->filter(fn (array $signal): bool => ($signal['city'] ?? null) === $businessCity
                    && ($businessState === null || ($signal['state'] ?? null) === $businessState))
                ->count();

        $sameState = $businessState === null
            ? 0
            : $knownStateSignals
                ->filter(fn (array $signal): bool => ($signal['state'] ?? null) === $businessState)
                ->count();

        $outsideState = $businessState === null
            ? 0
            : $knownStateSignals
                ->reject(fn (array $signal): bool => ($signal['state'] ?? null) === $businessState)
                ->count();

        return [
            'business_location_configured' => (bool) ($businessLocation['configured'] ?? false),
            'same_city_clients' => $sameCity,
            'same_city_percent' => $this->percent($sameCity, $total),
            'same_state_clients' => $sameState,
            'same_state_percent' => $this->percent($sameState, $total),
            'outside_business_state_clients' => $outsideState,
            'outside_business_state_percent' => $this->percent($outsideState, $total),
            'unknown_city_clients' => $signals->filter(fn (array $signal): bool => ($signal['city'] ?? 'Unknown') === 'Unknown')->count(),
            'unknown_state_clients' => $signals->filter(fn (array $signal): bool => ($signal['state'] ?? 'Unknown') === 'Unknown')->count(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $signals
     * @return array<string, mixed>
     */
    protected function seasonalityStats(Collection $signals): array
    {
        $endedSignals = $signals->reject(fn (array $signal): bool => (bool) ($signal['active'] ?? false))->values();

        return [
            'started_by_month' => $this->monthSeries($signals, 'started_at'),
            'ended_by_month' => $this->monthSeries($endedSignals, 'ended_at'),
            'started_by_calendar_month' => $this->calendarMonthMix($signals, 'started_at'),
            'ended_by_calendar_month' => $this->calendarMonthMix($endedSignals, 'ended_at'),
            'started_by_quarter' => $this->quarterMix($signals, 'started_at'),
            'ended_by_quarter' => $this->quarterMix($endedSignals, 'ended_at'),
            'peak_start_months' => $this->peakCalendarMonths($signals, 'started_at'),
            'peak_end_months' => $this->peakCalendarMonths($endedSignals, 'ended_at'),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $signals
     * @return array<string, mixed>
     */
    protected function forecastStats(Collection $signals): array
    {
        $now = now();
        $last90Starts = $this->countBetween($signals, 'started_at', $now->copy()->subDays(90), $now);
        $previous90Starts = $this->countBetween($signals, 'started_at', $now->copy()->subDays(180), $now->copy()->subDays(90));
        $last12MonthStarts = $this->countBetween($signals, 'started_at', $now->copy()->subMonths(12), $now);
        $last12MonthEnds = $this->countBetween(
            $signals->reject(fn (array $signal): bool => (bool) ($signal['active'] ?? false))->values(),
            'ended_at',
            $now->copy()->subMonths(12),
            $now,
        );

        return [
            'last_90_day_starts' => $last90Starts,
            'previous_90_day_starts' => $previous90Starts,
            'new_client_velocity' => $last90Starts <=> $previous90Starts,
            'new_client_velocity_label' => $this->trendLabel($last90Starts, $previous90Starts),
            'average_monthly_starts_last_12' => round($last12MonthStarts / 12, 1),
            'expected_starts_next_90_days' => (int) round(($last12MonthStarts / 12) * 3),
            'ended_clients_last_12_months' => $last12MonthEnds,
            'seasonal_peak_months' => $this->peakCalendarMonths($signals, 'started_at'),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $signals
     * @return array<int, array{outcome:string,count:int,share_percent:float}>
     */
    protected function outcomeMix(Collection $signals): array
    {
        $total = $signals->count();

        return $signals
            ->countBy(fn (array $signal): string => (string) ($signal['outcome'] ?? 'unknown_ended'))
            ->map(fn (int $count, string $outcome): array => [
                'outcome' => $outcome,
                'count' => $count,
                'share_percent' => $this->percent($count, $total),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $signals
     * @return array<int, array{month:string,count:int}>
     */
    protected function monthSeries(Collection $signals, string $dateKey, int $months = 24): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $index = [];

        for ($cursor = $start->copy(); $cursor->lte(now()->startOfMonth()); $cursor = $cursor->copy()->addMonth()) {
            $index[$cursor->format('Y-m')] = 0;
        }

        foreach ($signals as $signal) {
            $date = $signal[$dateKey] ?? null;

            if (! $date instanceof CarbonInterface) {
                continue;
            }

            $key = $date->format('Y-m');

            if (array_key_exists($key, $index)) {
                $index[$key]++;
            }
        }

        return collect($index)
            ->map(fn (int $count, string $month): array => [
                'month' => $month,
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $signals
     * @return array<int, array{month:int,label:string,count:int,share_percent:float}>
     */
    protected function calendarMonthMix(Collection $signals, string $dateKey): array
    {
        $counts = array_fill(1, 12, 0);

        foreach ($signals as $signal) {
            $date = $signal[$dateKey] ?? null;

            if ($date instanceof CarbonInterface) {
                $counts[(int) $date->format('n')]++;
            }
        }

        $total = array_sum($counts);

        return collect($counts)
            ->map(fn (int $count, int $month): array => [
                'month' => $month,
                'label' => Carbon::create(null, $month, 1)->format('M'),
                'count' => $count,
                'share_percent' => $this->percent($count, $total),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $signals
     * @return array<int, array{quarter:string,count:int,share_percent:float}>
     */
    protected function quarterMix(Collection $signals, string $dateKey): array
    {
        $counts = [
            'Q1' => 0,
            'Q2' => 0,
            'Q3' => 0,
            'Q4' => 0,
        ];

        foreach ($signals as $signal) {
            $date = $signal[$dateKey] ?? null;

            if (! $date instanceof CarbonInterface) {
                continue;
            }

            $counts['Q'.$date->quarter]++;
        }

        $total = array_sum($counts);

        return collect($counts)
            ->map(fn (int $count, string $quarter): array => [
                'quarter' => $quarter,
                'count' => $count,
                'share_percent' => $this->percent($count, $total),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $signals
     * @return array<int, array{month:int,label:string,count:int}>
     */
    protected function peakCalendarMonths(Collection $signals, string $dateKey): array
    {
        return collect($this->calendarMonthMix($signals, $dateKey))
            ->filter(fn (array $month): bool => $month['count'] > 0)
            ->sortBy([
                ['count', 'desc'],
                ['month', 'asc'],
            ])
            ->take(3)
            ->map(fn (array $month): array => Arr::only($month, ['month', 'label', 'count']))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $signals
     */
    protected function countBetween(Collection $signals, string $dateKey, CarbonInterface $start, CarbonInterface $end): int
    {
        return $signals
            ->filter(function (array $signal) use ($dateKey, $start, $end): bool {
                $date = $signal[$dateKey] ?? null;

                return $date instanceof CarbonInterface
                    && $date->gte($start)
                    && $date->lt($end);
            })
            ->count();
    }

    protected function daysSince(mixed $date): int
    {
        if (! $date instanceof CarbonInterface) {
            return 0;
        }

        return (int) floor(max(0, $date->diffInDays(now())));
    }

    protected function daysToMonths(int $days): float
    {
        return round(max(0, $days) / 30.4375, 1);
    }

    protected function percent(int $count, int $total): float
    {
        return $total <= 0 ? 0.0 : round(($count / $total) * 100, 1);
    }

    protected function trendLabel(int $current, int $previous): string
    {
        if ($current > $previous) {
            return 'up';
        }

        if ($current < $previous) {
            return 'down';
        }

        return 'flat';
    }

    protected function parseDateCandidate(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function normalizedState(?string $state): string
    {
        $value = Str::upper(trim((string) $state));

        if ($value === '') {
            return 'Unknown';
        }

        $clean = preg_replace('/[^A-Z ]/', '', $value) ?: $value;
        $map = $this->stateMap();

        if (isset($map[$clean])) {
            return $map[$clean];
        }

        if (strlen($clean) === 2 && in_array($clean, $map, true)) {
            return $clean;
        }

        return 'Unknown';
    }

    protected function normalizedCity(?string $city): string
    {
        $clean = trim((string) $city);

        if ($clean === '') {
            return 'Unknown';
        }

        $clean = preg_replace("/[^A-Za-z .'-]/", '', $clean) ?: '';
        $clean = trim((string) preg_replace('/\s+/', ' ', $clean));

        if ($clean === '') {
            return 'Unknown';
        }

        return Str::of($clean)->lower()->title()->toString();
    }

    /**
     * @return array<string, string>
     */
    protected function stateMap(): array
    {
        return [
            'ALABAMA' => 'AL',
            'ALASKA' => 'AK',
            'ARIZONA' => 'AZ',
            'ARKANSAS' => 'AR',
            'CALIFORNIA' => 'CA',
            'COLORADO' => 'CO',
            'CONNECTICUT' => 'CT',
            'DELAWARE' => 'DE',
            'FLORIDA' => 'FL',
            'GEORGIA' => 'GA',
            'HAWAII' => 'HI',
            'IDAHO' => 'ID',
            'ILLINOIS' => 'IL',
            'INDIANA' => 'IN',
            'IOWA' => 'IA',
            'KANSAS' => 'KS',
            'KENTUCKY' => 'KY',
            'LOUISIANA' => 'LA',
            'MAINE' => 'ME',
            'MARYLAND' => 'MD',
            'MASSACHUSETTS' => 'MA',
            'MICHIGAN' => 'MI',
            'MINNESOTA' => 'MN',
            'MISSISSIPPI' => 'MS',
            'MISSOURI' => 'MO',
            'MONTANA' => 'MT',
            'NEBRASKA' => 'NE',
            'NEVADA' => 'NV',
            'NEW HAMPSHIRE' => 'NH',
            'NEW JERSEY' => 'NJ',
            'NEW MEXICO' => 'NM',
            'NEW YORK' => 'NY',
            'NORTH CAROLINA' => 'NC',
            'NORTH DAKOTA' => 'ND',
            'OHIO' => 'OH',
            'OKLAHOMA' => 'OK',
            'OREGON' => 'OR',
            'PENNSYLVANIA' => 'PA',
            'RHODE ISLAND' => 'RI',
            'SOUTH CAROLINA' => 'SC',
            'SOUTH DAKOTA' => 'SD',
            'TENNESSEE' => 'TN',
            'TEXAS' => 'TX',
            'UTAH' => 'UT',
            'VERMONT' => 'VT',
            'VIRGINIA' => 'VA',
            'WASHINGTON' => 'WA',
            'WEST VIRGINIA' => 'WV',
            'WISCONSIN' => 'WI',
            'WYOMING' => 'WY',
            'DISTRICT OF COLUMBIA' => 'DC',
        ];
    }
}
