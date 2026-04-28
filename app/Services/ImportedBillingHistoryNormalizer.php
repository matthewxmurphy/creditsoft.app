<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientBillingProfile;
use App\Models\ClientPayment;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportedBillingHistoryNormalizer
{
    protected const PROFILE_SOURCE = 'imported_payment_markers';

    protected const LEGACY_PROFILE_SOURCE = 'disputefox_payment_markers';

    protected const PAYMENT_SOURCE = 'imported_payment_marker';

    protected const LEGACY_PAYMENT_SOURCE = 'disputefox_payment_marker';

    protected const GATEWAY_NAME = 'Imported Billing';

    public function __construct(
        protected ImportedPaymentMarkerParser $paymentMarkerParser,
        protected ClientHealthSignalService $healthSignal,
    ) {}

    /**
     * @return array<string, int>
     */
    public function normalizeAll(bool $dryRun = false, int $chunkSize = 100): array
    {
        $summary = $this->emptySummary();
        $query = Client::query()->with(['billingProfile', 'payments']);
        $this->constrainToMarkerClients($query);

        $query->chunkById(max(1, $chunkSize), function (Collection $clients) use (&$summary, $dryRun): void {
            foreach ($clients as $client) {
                /** @var Client $client */
                $summary = $this->mergeSummary(
                    $summary,
                    $this->normalizeClient($client, $dryRun),
                );
            }
        });

        return $summary;
    }

    /**
     * @return array<string, int>
     */
    public function normalizeClient(Client $client, bool $dryRun = false): array
    {
        $summary = $this->emptySummary();
        $summary['clients_scanned'] = 1;

        $markers = $this->paymentMarkerParser->markersFromMetadata($client->metadata ?? []);

        if ($markers->isEmpty()) {
            return $summary;
        }

        $summary['clients_with_markers'] = 1;
        $summary['markers_found'] = $markers->count();

        if ($dryRun) {
            $summary['profiles_created'] = $client->billingProfile ? 0 : 1;
            $summary['payments_created'] = $markers->filter(
                fn (array $marker): bool => ! $this->paymentForMarker($client, $marker),
            )->count();

            return $summary;
        }

        $profile = ClientBillingProfile::withoutEvents(
            fn (): ClientBillingProfile => $this->upsertProfile($client, $markers),
        );

        if ($profile->wasRecentlyCreated) {
            $summary['profiles_created']++;
        } elseif ($profile->wasChanged()) {
            $summary['profiles_updated']++;
        }

        ClientPayment::withoutEvents(function () use ($client, $profile, $markers, &$summary): void {
            foreach ($markers as $marker) {
                $payment = $this->upsertPayment($client, $profile, $marker);

                if ($payment->wasRecentlyCreated) {
                    $summary['payments_created']++;
                } elseif ($payment->wasChanged()) {
                    $summary['payments_updated']++;
                } else {
                    $summary['payments_unchanged']++;
                }
            }
        });

        $this->healthSignal->sync($client->fresh(['billingProfile', 'payments']) ?? $client);
        $summary['health_synced']++;

        return $summary;
    }

    protected function constrainToMarkerClients(Builder $query): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $query->where(function (Builder $query): void {
                $query
                    ->whereRaw('metadata::text ILIKE ?', ['%ActivePay%'])
                    ->orWhereRaw('metadata::text ILIKE ?', ['%FailedPay%'])
                    ->orWhereRaw('metadata::text ILIKE ?', ['%FailPay%']);
            });

            return;
        }

        $query->where(function (Builder $query): void {
            $query
                ->where('metadata', 'like', '%ActivePay%')
                ->orWhere('metadata', 'like', '%FailedPay%')
                ->orWhere('metadata', 'like', '%FailPay%');
        });
    }

    /**
     * @param  Collection<int, array{status:string,label:string,amount:?float,occurred_at:?CarbonInterface,raw:string,path:string}>  $markers
     */
    protected function upsertProfile(Client $client, Collection $markers): ClientBillingProfile
    {
        $profile = $client->billingProfile ?: new ClientBillingProfile([
            'client_id' => $client->getKey(),
        ]);
        $isImportedProfile = ! $profile->exists
            || in_array($profile->metadata['source'] ?? null, [
                self::PROFILE_SOURCE,
                self::LEGACY_PROFILE_SOURCE,
            ], true);
        $latestPaidAt = $this->latestMarkerDate($markers->where('status', 'paid'));
        $startedAt = $this->earliestMarkerDate($markers);
        $amount = $this->profileAmount($markers);
        $latestMarker = $this->latestMarker($markers);
        $metadata = $profile->metadata ?? [];
        $normalizedAt = data_get($metadata, 'imports.imported_billing.payment_markers.normalized_at')
            ?? data_get($metadata, 'imports.disputefox.payment_markers.normalized_at')
            ?? now()->toIso8601String();

        data_set($metadata, 'source', self::PROFILE_SOURCE);
        data_set($metadata, 'imports.imported_billing.payment_markers', [
            'normalized_at' => $normalizedAt,
            'history_only' => true,
            'markers' => $markers->map(fn (array $marker): array => $this->markerMetadata($marker))->all(),
            'paid_count' => $markers->where('status', 'paid')->count(),
            'failed_count' => $markers->where('status', 'failed')->count(),
        ]);
        data_forget($metadata, 'imports.disputefox.payment_markers');

        $payload = [
            'currency' => $profile->currency ?: 'USD',
            'billing_interval' => $profile->billing_interval ?: 'monthly',
            'gateway_name' => $this->profileGatewayName($profile->gateway_name),
            'notes' => $this->profileNotes($profile->notes),
            'metadata' => $metadata,
        ];

        if ($isImportedProfile) {
            $payload['status'] = ($latestMarker['status'] ?? null) === 'failed' ? 'paused' : 'active';
            $payload['amount'] = $amount;
            $payload['started_at'] = $startedAt;
            $payload['last_paid_at'] = $latestPaidAt;
            $payload['next_due_at'] = null;
        } else {
            $payload['last_paid_at'] = $this->newerDate($profile->last_paid_at, $latestPaidAt);
            $payload['next_due_at'] = $profile->next_due_at;
            $payload['amount'] = ((float) $profile->amount > 0 || $amount <= 0)
                ? $profile->amount
                : $amount;
        }

        $profile->fill($payload);
        $profile->save();

        return $profile;
    }

    /**
     * @param  array{status:string,label:string,amount:?float,occurred_at:?CarbonInterface,raw:string,path:string}  $marker
     */
    protected function upsertPayment(Client $client, ClientBillingProfile $profile, array $marker): ClientPayment
    {
        $payment = $this->paymentForMarker($client, $marker) ?: new ClientPayment();
        $payment->fill([
            'client_id' => $client->getKey(),
            'client_billing_profile_id' => $profile->getKey(),
            'amount' => $marker['amount'] ?? 0,
            'currency' => 'USD',
            'status' => $marker['status'],
            'paid_at' => $marker['occurred_at'],
            'gateway_name' => self::GATEWAY_NAME,
            'gateway_transaction_id' => null,
            'reference' => $this->referenceForMarker($client, $marker),
            'notes' => "{$marker['label']} marker normalized from imported roster metadata.",
            'metadata' => [
                'source' => self::PAYMENT_SOURCE,
                'imports' => [
                    'imported_billing' => [
                        'payment_marker' => $this->markerMetadata($marker),
                        'legacy_source_record_id' => data_get($client->metadata, 'imports.disputefox.source_record_id'),
                        'legacy_source_record_int_id' => data_get($client->metadata, 'imports.disputefox.source_record_int_id'),
                    ],
                ],
            ],
        ]);
        $payment->save();

        return $payment;
    }

    /**
     * @param  array{status:string,label:string,amount:?float,occurred_at:?CarbonInterface,raw:string,path:string}  $marker
     */
    protected function paymentForMarker(Client $client, array $marker): ?ClientPayment
    {
        return ClientPayment::query()
            ->where('client_id', $client->getKey())
            ->whereIn('reference', $this->referencesForMarker($client, $marker))
            ->first();
    }

    /**
     * @param  array{status:string,label:string,amount:?float,occurred_at:?CarbonInterface,raw:string,path:string}  $marker
     */
    protected function referenceForMarker(Client $client, array $marker): string
    {
        return 'imported-billing-marker:'.sha1($this->fingerprintForMarker($client, $marker));
    }

    /**
     * @param  array{status:string,label:string,amount:?float,occurred_at:?CarbonInterface,raw:string,path:string}  $marker
     * @return array<int, string>
     */
    protected function referencesForMarker(Client $client, array $marker): array
    {
        $fingerprint = $this->fingerprintForMarker($client, $marker);

        return [
            'imported-billing-marker:'.sha1($fingerprint),
            'disputefox-marker:'.sha1($fingerprint),
        ];
    }

    /**
     * @param  array{status:string,label:string,amount:?float,occurred_at:?CarbonInterface,raw:string,path:string}  $marker
     */
    protected function fingerprintForMarker(Client $client, array $marker): string
    {
        return implode('|', [
            $client->getKey(),
            data_get($client->metadata, 'imports.disputefox.source_record_id'),
            data_get($client->metadata, 'imports.disputefox.source_record_int_id'),
            $marker['path'],
            $marker['label'],
            $marker['amount'] ?? '',
            optional($marker['occurred_at'])->toDateString(),
            $marker['raw'],
        ]);
    }

    /**
     * @param  array{status:string,label:string,amount:?float,occurred_at:?CarbonInterface,raw:string,path:string}  $marker
     * @return array<string, mixed>
     */
    protected function markerMetadata(array $marker): array
    {
        return [
            'status' => $marker['status'],
            'label' => $marker['label'],
            'amount' => $marker['amount'],
            'occurred_at' => $marker['occurred_at']?->toIso8601String(),
            'path' => $marker['path'],
            'raw' => $marker['raw'],
        ];
    }

    /**
     * @param  Collection<int, array{status:string,label:string,amount:?float,occurred_at:?CarbonInterface,raw:string,path:string}>  $markers
     */
    protected function latestMarker(Collection $markers): ?array
    {
        return $markers
            ->sortByDesc(fn (array $marker): int => $marker['occurred_at'] instanceof CarbonInterface
                ? $marker['occurred_at']->getTimestamp()
                : PHP_INT_MIN)
            ->first();
    }

    /**
     * @param  Collection<int, array{status:string,label:string,amount:?float,occurred_at:?CarbonInterface,raw:string,path:string}>  $markers
     */
    protected function latestMarkerDate(Collection $markers): ?Carbon
    {
        $marker = $this->latestMarker($markers);
        $date = $marker['occurred_at'] ?? null;

        return $date instanceof CarbonInterface ? Carbon::parse($date) : null;
    }

    /**
     * @param  Collection<int, array{status:string,label:string,amount:?float,occurred_at:?CarbonInterface,raw:string,path:string}>  $markers
     */
    protected function earliestMarkerDate(Collection $markers): ?Carbon
    {
        $marker = $markers
            ->filter(fn (array $marker): bool => $marker['occurred_at'] instanceof CarbonInterface)
            ->sortBy(fn (array $marker): int => $marker['occurred_at']->getTimestamp())
            ->first();
        $date = $marker['occurred_at'] ?? null;

        return $date instanceof CarbonInterface ? Carbon::parse($date) : null;
    }

    /**
     * @param  Collection<int, array{status:string,label:string,amount:?float,occurred_at:?CarbonInterface,raw:string,path:string}>  $markers
     */
    protected function profileAmount(Collection $markers): float
    {
        return (float) ($markers
            ->pluck('amount')
            ->filter(fn ($amount): bool => is_numeric($amount) && (float) $amount > 0)
            ->map(fn ($amount): float => (float) $amount)
            ->sortDesc()
            ->first() ?? 0);
    }

    protected function profileGatewayName(?string $gatewayName): string
    {
        $name = trim((string) $gatewayName);

        return $name === '' || Str::lower($name) === 'disputefox'
            ? self::GATEWAY_NAME
            : $name;
    }

    protected function profileNotes(?string $notes): string
    {
        $value = trim((string) $notes);

        return $value === '' || Str::contains(Str::lower($value), 'disputefox')
            ? 'Imported from legacy billing history markers.'
            : $value;
    }

    protected function newerDate(mixed $existing, ?CarbonInterface $incoming): mixed
    {
        if (! $incoming) {
            return $existing;
        }

        if (! $existing) {
            return $incoming;
        }

        return Carbon::parse($existing)->greaterThan($incoming) ? $existing : $incoming;
    }

    /**
     * @return array<string, int>
     */
    protected function emptySummary(): array
    {
        return [
            'clients_scanned' => 0,
            'clients_with_markers' => 0,
            'markers_found' => 0,
            'profiles_created' => 0,
            'profiles_updated' => 0,
            'payments_created' => 0,
            'payments_updated' => 0,
            'payments_unchanged' => 0,
            'health_synced' => 0,
        ];
    }

    /**
     * @param  array<string, int>  $left
     * @param  array<string, int>  $right
     * @return array<string, int>
     */
    protected function mergeSummary(array $left, array $right): array
    {
        foreach ($right as $key => $value) {
            $left[$key] = ($left[$key] ?? 0) + $value;
        }

        return $left;
    }
}
