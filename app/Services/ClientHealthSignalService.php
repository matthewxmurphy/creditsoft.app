<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class ClientHealthSignalService
{
    public function __construct(
        protected CreditsoftAiService $aiService,
        protected ImportedPaymentMarkerParser $paymentMarkerParser,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function signal(Client $client): array
    {
        $client->loadMissing(['billingProfile', 'payments', 'providerAccounts']);

        $profile = $client->billingProfile;
        /** @var Collection<int, ClientPayment> $payments */
        $payments = $client->payments instanceof Collection
            ? $client->payments
            : collect();
        $latestPayment = $payments
            ->sortByDesc(fn (ClientPayment $payment) => $payment->paid_at ?? $payment->created_at)
            ->first();
        $paidPayments = $payments
            ->filter(fn (ClientPayment $payment): bool => $this->textLooksPaid($payment->status));
        $livePaidPayments = $paidPayments
            ->reject(fn (ClientPayment $payment): bool => $this->isImportedHistoryPayment($payment));
        $latePayments = $payments
            ->filter(fn (ClientPayment $payment): bool => $this->textMatchesLate(
                $payment->status,
                $payment->notes,
                $payment->metadata,
            ));

        $amount = $profile?->amount !== null ? (float) $profile->amount : null;
        $nextDueAt = $profile?->next_due_at;
        $lastPaidAt = $profile?->last_paid_at ?? $latestPayment?->paid_at;
        $profileStatus = (string) ($profile?->status ?? '');
        $profileMetadata = $profile?->metadata ?? [];
        $clientMetadata = $client->metadata ?? [];
        $importedHistoryOnly = $this->isImportedHistoryOnly($profileMetadata);
        $recurringActiveForHealth = $profile && ! $importedHistoryOnly && $profile->isRecurringActive();
        $standingNextDueAt = $importedHistoryOnly ? null : $nextDueAt;
        $importedPaymentMarkers = $this->importedPaymentMarkers($clientMetadata);
        $latestImportedPayment = $importedPaymentMarkers
            ->sortByDesc(fn (array $marker): int => $marker['occurred_at'] instanceof CarbonInterface
                ? $marker['occurred_at']->getTimestamp()
                : 0)
            ->first();
        $latestImportedPaidAt = $importedPaymentMarkers
            ->filter(fn (array $marker): bool => $marker['status'] === 'paid')
            ->map(fn (array $marker): ?CarbonInterface => $marker['occurred_at'] instanceof CarbonInterface ? $marker['occurred_at'] : null)
            ->filter()
            ->sortByDesc(fn (CarbonInterface $date): int => $date->getTimestamp())
            ->first();
        $latestPaidEvidenceAt = collect([$lastPaidAt, $latestImportedPaidAt])
            ->filter()
            ->map(fn (mixed $date): CarbonInterface => $date instanceof CarbonInterface ? $date : Carbon::parse($date))
            ->sortByDesc(fn (CarbonInterface $date): int => $date->getTimestamp())
            ->first();
        $stalePaymentCutoff = now()->subDays(62);
        $hasFutureStandingDue = $standingNextDueAt && $standingNextDueAt->isFuture();
        $stalePaymentHistory = $latestPaidEvidenceAt instanceof CarbonInterface
            && $latestPaidEvidenceAt->lt($stalePaymentCutoff)
            && ! $hasFutureStandingDue;
        $latestPaymentIsRecentPaid = $this->textLooksPaid($latestPayment?->status)
            && (
                ! $latestPayment?->paid_at
                || $latestPayment->paid_at->gte($stalePaymentCutoff)
            );
        $providerPaymentBlocked = $this->providerPaymentBlocked($client);
        $importedPaidCount = $importedPaymentMarkers
            ->filter(fn (array $marker): bool => $marker['status'] === 'paid')
            ->count();
        $importedLateCount = $importedPaymentMarkers
            ->filter(fn (array $marker): bool => $marker['status'] === 'failed')
            ->count();
        $profileIsBillable = $profile && ! $this->textContains(['cancelled', 'canceled', 'ended'], $profileStatus);
        $hasLateHistory = $latePayments->isNotEmpty()
            || $importedLateCount > 0
            || $this->textMatchesLate(data_get($profileMetadata, 'history'), data_get($profileMetadata, 'payment_history'), $profile?->notes);
        $ownerAccount = filter_var(
            data_get($profileMetadata, 'owner_account')
                ?? data_get($profileMetadata, 'manual_billing.owner_account')
                ?? data_get($clientMetadata, 'owner_account'),
            FILTER_VALIDATE_BOOLEAN,
        );
        $proBonoAccount = filter_var(
            data_get($profileMetadata, 'pro_bono')
                ?? data_get($profileMetadata, 'manual_billing.pro_bono')
                ?? data_get($clientMetadata, 'billing.pro_bono'),
            FILTER_VALIDATE_BOOLEAN,
        );
        $isBehind = $this->textMatchesLate($profileStatus)
            || ($profileIsBillable && $standingNextDueAt && $standingNextDueAt->isPast())
            || $this->textMatchesLate($latestPayment?->status)
            || (($latestImportedPayment['status'] ?? null) === 'failed')
            || $providerPaymentBlocked
            || $stalePaymentHistory;
        $looksCurrent = ! $isBehind && (
            ($standingNextDueAt && $standingNextDueAt->isFuture())
            || $latestPaymentIsRecentPaid
            || (($latestImportedPayment['status'] ?? null) === 'paid' && ! $stalePaymentHistory)
            || ($recurringActiveForHealth && $lastPaidAt && ! $stalePaymentHistory)
        );
        $explicitVip = $this->textContains([
            'diamond',
            'excellent',
            'great customer',
            'vip',
        ], data_get($clientMetadata, 'tags'), data_get($clientMetadata, 'labels'), data_get($clientMetadata, 'vip'), $profileMetadata, $profile?->notes);
        $isVip = $looksCurrent
            && ! $hasLateHistory
            && ($explicitVip
                || $livePaidPayments->count() >= 3
                || ($recurringActiveForHealth && $livePaidPayments->isNotEmpty()));

        $base = [
            'amount_label' => $amount !== null ? '$'.number_format($amount, 2) : null,
            'last_paid_label' => $this->dateLabel($lastPaidAt),
            'next_due_label' => $this->dateLabel($nextDueAt),
            'paid_payment_count' => $paidPayments->count() + $importedPaidCount,
            'late_payment_count' => $latePayments->count() + $importedLateCount,
            'imported_payment_count' => $importedPaymentMarkers->count(),
            'imported_payment_label' => $this->importedPaymentLabel($latestImportedPayment),
            'updated_at' => now()->toIso8601String(),
        ];

        if ($ownerAccount || $proBonoAccount) {
            $label = $ownerAccount ? 'Owner account' : 'Pro bono';
            $detail = $ownerAccount
                ? 'Internal owner account; client payment is not required for report monitoring.'
                : 'Pro bono account; client payment is intentionally waived.';

            return $this->withFingerprint([
                ...$base,
                'state' => $ownerAccount ? 'owner' : 'pro_bono',
                'label' => $label,
                'detail' => $detail,
                'reason' => $detail.' CreditSoft keeps the file active without marking billing behind.',
                'reason_code' => $ownerAccount ? 'owner_internal' : 'pro_bono_manual',
                'tone' => $ownerAccount ? 'blue' : 'green',
                'color' => $ownerAccount ? 'blue' : 'green',
                'score' => $ownerAccount ? 100 : 90,
                'score_label' => $ownerAccount ? '100/100' : '90/100',
            ]);
        }

        if (! $profile && $payments->isEmpty() && $importedPaymentMarkers->isEmpty()) {
            return $this->withFingerprint([
                ...$base,
                'state' => 'unknown',
                'label' => 'No billing record',
                'detail' => 'No imported or manual billing history is on file yet.',
                'reason' => 'The client does not have billing profile or payment history saved.',
                'reason_code' => 'no_billing_record',
                'tone' => 'stone',
                'color' => 'stone',
                'score' => 50,
                'score_label' => '50/100',
            ]);
        }

        if ($isBehind) {
            $detail = $providerPaymentBlocked
                ? 'Saved monitoring access needs client payment or reactivation before reports can be pulled.'
                : ($stalePaymentHistory && $latestPaidEvidenceAt instanceof CarbonInterface
                    ? 'Last payment on file was '.$latestPaidEvidenceAt->format('M j, Y').'; no current billing evidence is saved.'
                    : (($latestImportedPayment['status'] ?? null) === 'failed'
                ? 'Imported payment history shows '.$this->importedPaymentLabel($latestImportedPayment).'.'
                : ($standingNextDueAt
                    ? 'Payment due since '.$standingNextDueAt->format('M j, Y').'.'
                    : 'Billing status indicates the client owes or is past due.')));

            return $this->withFingerprint([
                ...$base,
                'state' => 'behind',
                'label' => 'Behind',
                'detail' => $detail,
                'reason' => $detail.' The internal score is red until billing and monitoring access are caught up.',
                'reason_code' => $providerPaymentBlocked ? 'provider_payment_blocked' : ($stalePaymentHistory ? 'payment_stale' : 'payment_behind'),
                'tone' => 'red',
                'color' => 'red',
                'score' => 25,
                'score_label' => '25/100',
            ]);
        }

        if ($looksCurrent && $hasLateHistory) {
            return $this->withFingerprint([
                ...$base,
                'state' => 'current_watch',
                'label' => 'Current watch',
                'detail' => 'Currently good, but this client has late or failed payment history.',
                'reason' => 'Payment history is current now, but prior late or failed billing markers keep this client on watch.',
                'reason_code' => 'current_with_late_history',
                'tone' => 'yellow',
                'color' => 'yellow',
                'score' => 68,
                'score_label' => '68/100',
            ]);
        }

        if ($hasLateHistory) {
            return $this->withFingerprint([
                ...$base,
                'state' => 'late_history',
                'label' => 'Late history',
                'detail' => 'Past billing records show late or failed payment activity.',
                'reason' => 'Late, failed, returned, or missed payment markers were found in billing history.',
                'reason_code' => 'late_history',
                'tone' => 'yellow',
                'color' => 'yellow',
                'score' => 55,
                'score_label' => '55/100',
            ]);
        }

        if ($isVip) {
            return $this->withFingerprint([
                ...$base,
                'state' => 'vip',
                'label' => 'Diamond VIP',
                'detail' => 'Clean current payment history with strong recurring or repeat-payment evidence.',
                'reason' => 'Payment history is current and clean, with repeat or recurring paid activity and no late-payment markers.',
                'reason_code' => 'diamond_vip',
                'tone' => 'blue',
                'color' => 'blue',
                'score' => 96,
                'score_label' => '96/100',
            ]);
        }

        if ($looksCurrent) {
            $detail = $standingNextDueAt
                ? 'Paid up through '.$standingNextDueAt->format('M j, Y').'.'
                : (($latestImportedPayment['status'] ?? null) === 'paid'
                    ? 'Imported payment history shows '.$this->importedPaymentLabel($latestImportedPayment).'.'
                    : 'Payment history is current.');

            return $this->withFingerprint([
                ...$base,
                'state' => 'current',
                'label' => 'Current',
                'detail' => $detail,
                'reason' => $detail.' No late-payment markers were found.',
                'reason_code' => 'current_clean',
                'tone' => 'green',
                'color' => 'green',
                'score' => 84,
                'score_label' => '84/100',
            ]);
        }

        return $this->withFingerprint([
            ...$base,
            'state' => 'unknown',
            'label' => 'Billing unknown',
            'detail' => 'Billing exists, but the current payment state is not clear yet.',
            'reason' => 'Billing records exist, but the system cannot confidently classify the current payment state.',
            'reason_code' => 'billing_unclear',
            'tone' => 'stone',
            'color' => 'stone',
            'score' => 50,
            'score_label' => '50/100',
        ]);
    }

    /**
     * Recompute, persist, and note only when payment or billing data changes the signal.
     *
     * @return array<string, mixed>
     */
    public function sync(Client $client, ?User $actor = null): array
    {
        $client->loadMissing(['billingProfile', 'payments', 'providerAccounts']);

        $metadata = $client->metadata ?? [];
        $previous = data_get($metadata, 'client_health');
        $previous = is_array($previous) ? $previous : null;
        $signal = $this->signal($client);

        if (($previous['fingerprint'] ?? null) === ($signal['fingerprint'] ?? null)) {
            return $signal;
        }

        data_set($metadata, 'client_health', [
            ...$signal,
            'previous_state' => $previous['state'] ?? null,
            'previous_score' => $previous['score'] ?? null,
        ]);

        $client->forceFill(['metadata' => $metadata])->saveQuietly();

        $freshClient = $client->fresh();

        if ($freshClient && $this->shouldCreateNote($previous, $signal)) {
            $this->createAutomaticNote($freshClient, $signal, $previous, $actor);
        }

        return $signal;
    }

    /**
     * @param  array<string, mixed>|null  $previous
     * @param  array<string, mixed>  $signal
     */
    protected function shouldCreateNote(?array $previous, array $signal): bool
    {
        if (($signal['state'] ?? 'unknown') === 'unknown' && $previous === null) {
            return false;
        }

        if (($previous['fingerprint'] ?? null) === ($signal['fingerprint'] ?? null)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $profileMetadata
     */
    protected function isImportedHistoryOnly(array $profileMetadata): bool
    {
        return data_get($profileMetadata, 'imports.imported_billing.payment_markers.history_only') === true
            || data_get($profileMetadata, 'imports.disputefox.payment_markers.history_only') === true
            || in_array(data_get($profileMetadata, 'source'), [
                'imported_payment_markers',
                'disputefox_payment_markers',
            ], true);
    }

    protected function isImportedHistoryPayment(ClientPayment $payment): bool
    {
        return in_array(data_get($payment->metadata ?? [], 'source'), [
            'imported_payment_marker',
            'disputefox_payment_marker',
        ], true);
    }

    protected function providerPaymentBlocked(Client $client): bool
    {
        $providers = $client->relationLoaded('providerAccounts')
            ? $client->providerAccounts
            : collect();

        return $providers->contains(function ($provider): bool {
            $status = strtolower(trim((string) $provider->status));

            return in_array($status, ['needs_client_payment', 'needs_reactivation'], true)
                || $this->textContains([
                    'needs client payment',
                    'needs current smartcredit access payment',
                    'reactivation',
                ], $provider->notes, $provider->metadata);
        });
    }

    /**
     * @param  array<string, mixed>|null  $previous
     * @param  array<string, mixed>  $signal
     */
    protected function createAutomaticNote(Client $client, array $signal, ?array $previous, ?User $actor = null): void
    {
        $actor ??= auth()->user();
        $note = $this->fallbackNote($signal, $previous);
        $aiMeta = ['source' => 'heuristic'];

        try {
            $generated = $this->aiService->generateClientHealthNote($client, $signal, $previous);
            $content = trim((string) ($generated['content'] ?? ''));

            if ($content !== '') {
                $note = $content;
                $aiMeta = [
                    'source' => 'ai',
                    ...($generated['meta'] ?? []),
                ];
            }
        } catch (Throwable) {
            // Keep billing writes fast and reliable even when the AI lane is offline.
        }

        $client->notes()->create([
            'user_id' => $actor?->getKey(),
            'visibility' => 'working_note',
            'note' => $note,
            'sync_eligible' => false,
            'ai_summary' => json_encode([
                'kind' => 'client_health_signal',
                'fingerprint' => $signal['fingerprint'] ?? null,
                'previous_state' => $previous['state'] ?? null,
                'state' => $signal['state'] ?? null,
                'score' => $signal['score'] ?? null,
                'note_source' => $aiMeta,
            ], JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $previous
     * @param  array<string, mixed>  $signal
     */
    protected function fallbackNote(array $signal, ?array $previous): string
    {
        $changedFrom = $previous
            ? sprintf(' from %s', (string) ($previous['label'] ?? $previous['state'] ?? 'the prior signal'))
            : '';

        return sprintf(
            'Client health changed%s to %s (%s). %s',
            $changedFrom,
            (string) ($signal['label'] ?? 'Billing signal'),
            (string) ($signal['score_label'] ?? (($signal['score'] ?? '50').'/100')),
            (string) ($signal['reason'] ?? $signal['detail'] ?? 'Billing history caused the color change.'),
        );
    }

    /**
     * @param  array<string, mixed>  $signal
     * @return array<string, mixed>
     */
    protected function withFingerprint(array $signal): array
    {
        $fingerprintPayload = [
            'state' => $signal['state'] ?? null,
            'tone' => $signal['tone'] ?? null,
            'score' => $signal['score'] ?? null,
            'reason_code' => $signal['reason_code'] ?? null,
            'paid_payment_count' => $signal['paid_payment_count'] ?? 0,
            'late_payment_count' => $signal['late_payment_count'] ?? 0,
        ];

        return [
            ...$signal,
            'fingerprint' => sha1(json_encode($fingerprintPayload) ?: ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return Collection<int, array{status:string,label:string,amount:?float,occurred_at:?CarbonInterface,raw:string,path:string}>
     */
    protected function importedPaymentMarkers(array $metadata): Collection
    {
        return $this->paymentMarkerParser->markersFromMetadata($metadata);
    }

    /**
     * @param  array{status?:string,label?:string,amount?:?float,occurred_at?:?CarbonInterface}|null  $marker
     */
    protected function importedPaymentLabel(?array $marker): ?string
    {
        if (! $marker) {
            return null;
        }

        $parts = [
            $marker['label'] ?? (($marker['status'] ?? null) === 'failed' ? 'Failed payment' : 'Payment'),
            isset($marker['occurred_at']) && $marker['occurred_at'] instanceof CarbonInterface
                ? $marker['occurred_at']->format('M j, Y')
                : null,
            isset($marker['amount']) && $marker['amount'] !== null
                ? '$'.number_format((float) $marker['amount'], 2)
                : null,
        ];

        return implode(' ', array_filter($parts));
    }

    protected function dateLabel(mixed $date): ?string
    {
        if (! $date) {
            return null;
        }

        $carbon = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        return $carbon->format('M j, Y');
    }

    protected function textMatchesLate(mixed ...$values): bool
    {
        return $this->textContains([
            'behind',
            'chargeback',
            'declined',
            'failed',
            'late',
            'missed',
            'nonpayment',
            'nsf',
            'overdue',
            'past due',
            'past_due',
            'returned',
            'unpaid',
        ], ...$values);
    }

    protected function textLooksPaid(mixed ...$values): bool
    {
        return $this->textContains([
            'authorized',
            'captured',
            'complete',
            'completed',
            'confirmed',
            'paid',
            'success',
            'succeeded',
        ], ...$values);
    }

    /**
     * @param  list<string>  $needles
     */
    protected function textContains(array $needles, mixed ...$values): bool
    {
        $text = Str::of(collect($values)
            ->filter(fn ($value): bool => filled($value))
            ->map(fn ($value): string => is_scalar($value) ? (string) $value : (json_encode($value) ?: ''))
            ->implode(' '))
            ->lower()
            ->replace(['-', '_'], ' ')
            ->squish()
            ->value();

        if ($text === '') {
            return false;
        }

        return collect($needles)
            ->map(fn (string $needle): string => Str::of($needle)->replace(['-', '_'], ' ')->lower()->value())
            ->contains(fn (string $needle): bool => $needle !== '' && str_contains($text, $needle));
    }
}
