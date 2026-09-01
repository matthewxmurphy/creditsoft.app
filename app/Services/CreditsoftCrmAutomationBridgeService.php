<?php

namespace App\Services;

use App\Models\CaseNote;
use App\Models\Client;
use App\Models\CrmAutomationEvent;
use App\Models\OutboundSignal;
use App\Models\Task;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CreditsoftCrmAutomationBridgeService
{
    public function __construct(
        protected CreditsoftAiService $aiService,
        protected AuditTrail $auditTrail,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleTwentyWebhook(array $payload, ?User $actor = null): CrmAutomationEvent
    {
        $event = $this->normalizeTwentyEvent($payload);

        return DB::transaction(function () use ($payload, $event, $actor): CrmAutomationEvent {
            /** @var CrmAutomationEvent $record */
            $record = CrmAutomationEvent::query()->firstOrNew([
                'idempotency_key' => $event['idempotency_key'],
            ]);

            if ($record->exists && $record->status === 'processed') {
                return $record;
            }

            $record->fill([
                'provider' => 'twenty',
                'external_event_id' => $event['external_event_id'],
                'event_type' => $event['event_type'],
                'object_type' => $event['object_type'],
                'object_id' => $event['object_id'],
                'status' => 'received',
                'priority' => 'normal',
                'payload' => $payload,
                'last_error' => null,
                'failed_at' => null,
            ])->save();

            try {
                $client = $this->resolveClient($payload);
                $signals = $this->signalsFor($client, $payload);
                $fallback = $this->fallbackDecision($client, $event, $signals, $payload);
                $decision = $this->decisionFor($client, $event, $signals, $fallback);
                $effects = $this->applyDecision($record, $client, $decision, $actor);

                $record->forceFill([
                    'client_id' => $client?->getKey(),
                    'status' => 'processed',
                    'priority' => $decision['priority'] ?? 'normal',
                    'signals' => $signals,
                    'decision' => [
                        ...$decision,
                        'effects' => $effects,
                    ],
                    'processed_at' => now(),
                    'failed_at' => null,
                    'last_error' => null,
                ])->save();

                $this->auditTrail->record(
                    $actor,
                    'crm.automation.processed',
                    'Processed CRM automation event '.$record->event_type.'.',
                    $client ?? $record,
                    [
                        'event_id' => $record->getKey(),
                        'client_id' => $client?->getKey(),
                        'campaign_key' => $decision['campaign_key'] ?? null,
                        'channel' => $decision['channel'] ?? null,
                    ],
                );
            } catch (Throwable $exception) {
                $record->forceFill([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'last_error' => Str::limit($exception->getMessage(), 1000),
                ])->save();

                throw new RuntimeException('CreditSoft could not process the CRM automation event. '.$exception->getMessage(), previous: $exception);
            }

            return $record->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{external_event_id:?string,idempotency_key:string,event_type:string,object_type:?string,object_id:?string}
     */
    protected function normalizeTwentyEvent(array $payload): array
    {
        $eventType = $this->firstString($payload, [
            'event',
            'eventName',
            'event_name',
            'type',
            'name',
            'action',
            'operation',
            'data.event',
            'data.type',
            'payload.event',
        ]) ?: 'crm.webhook.received';

        $objectType = $this->firstString($payload, [
            'object',
            'objectType',
            'object_type',
            'objectName',
            'object_name',
            'entity',
            'data.object',
            'data.objectType',
            'data.objectName',
            'record.objectName',
            'payload.objectName',
        ]);

        $objectId = $this->firstString($payload, [
            'recordId',
            'record_id',
            'objectId',
            'object_id',
            'id',
            'data.recordId',
            'data.record_id',
            'data.objectId',
            'data.id',
            'record.id',
            'payload.recordId',
        ]);

        $externalId = $this->firstString($payload, [
            'eventId',
            'event_id',
            'webhookId',
            'webhook_id',
            'id',
            'data.eventId',
            'data.event_id',
        ]);

        $objectType = $objectType !== null ? Str::of($objectType)->snake()->value() : null;
        $basis = json_encode([
            'provider' => 'twenty',
            'external_event_id' => $externalId,
            'event_type' => $eventType,
            'object_type' => $objectType,
            'object_id' => $objectId,
            'payload_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES) ?: ''),
        ], JSON_UNESCAPED_SLASHES) ?: '';

        return [
            'external_event_id' => $externalId,
            'idempotency_key' => hash('sha256', $basis),
            'event_type' => Str::limit($eventType, 255, ''),
            'object_type' => $objectType ? Str::limit($objectType, 255, '') : null,
            'object_id' => $objectId ? Str::limit($objectId, 255, '') : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveClient(array $payload): ?Client
    {
        $cuid = $this->firstString($payload, [
            'client_cuid',
            'clientCuid',
            'creditsoft.cuid',
            'createdByContext.creditsoft.cuid',
            'updatedByContext.creditsoft.cuid',
            'data.creditsoft.cuid',
            'record.creditsoft.cuid',
            'record.createdByContext.creditsoft.cuid',
            'record.updatedByContext.creditsoft.cuid',
        ]);

        if (! $cuid) {
            $cuid = collect($this->flattenPayloadStrings($payload))
                ->first(fn (string $value): bool => preg_match('/\bc_[a-z0-9]{6,}\b/i', $value) === 1);
        }

        if ($cuid) {
            $client = Client::query()->whereRaw('lower(cuid) = ?', [strtolower($cuid)])->first();

            if ($client) {
                return $client;
            }
        }

        $clientId = $this->firstString($payload, [
            'client_id',
            'clientId',
            'creditsoft.client_id',
            'createdByContext.creditsoft.client_id',
            'updatedByContext.creditsoft.client_id',
            'data.creditsoft.client_id',
            'data.createdByContext.creditsoft.client_id',
            'data.updatedByContext.creditsoft.client_id',
            'record.creditsoft.client_id',
            'record.createdByContext.creditsoft.client_id',
            'record.updatedByContext.creditsoft.client_id',
        ]);

        if (is_numeric($clientId)) {
            $client = Client::query()->find((int) $clientId);

            if ($client) {
                return $client;
            }
        }

        foreach ($this->emailsFromPayload($payload) as $email) {
            $client = Client::query()
                ->whereRaw('lower(email) = ?', [$email])
                ->orWhereRaw('lower(secondary_email) = ?', [$email])
                ->first();

            if ($client) {
                return $client;
            }
        }

        foreach ($this->phonesFromPayload($payload) as $phone) {
            $client = Client::query()->where('phone', $phone)->first();

            if ($client) {
                return $client;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function signalsFor(?Client $client, array $payload): array
    {
        if (! $client) {
            return [
                'matched_client' => false,
                'emails_found' => $this->emailsFromPayload($payload),
                'phones_found' => $this->phonesFromPayload($payload),
            ];
        }

        $client->loadMissing('billingProfile', 'payments', 'documents', 'providerAccounts');
        $latestCycle = $client->reportingCycles()->withCount('bureauSnapshots')->first();
        $usableDocuments = $client->documents
            ->filter(fn ($document): bool => filled($document->file_path) && (int) $document->file_size > 0);
        $documentText = $usableDocuments
            ->map(fn ($document): string => Str::lower(trim(($document->category ?? '').' '.($document->title ?? '').' '.($document->file_name ?? ''))))
            ->implode(' | ');
        $missingDocuments = collect([
            'photo_id' => ! Str::contains($documentText, ['driver', 'license', 'photo id', 'photo-of-drivers-license']),
            'proof_of_address' => ! Str::contains($documentText, ['proof of address', 'utility', 'address']),
            'ssn_or_w2' => ! Str::contains($documentText, ['social security', 'ssn', 'w2', 'w-2']),
        ])->filter()->keys()->values()->all();
        $lastPayment = $client->payments->first();
        $providerReadyCount = $client->providerAccounts
            ->filter(fn ($account): bool => method_exists($account, 'hasStoredPassword') && $account->hasStoredPassword())
            ->count();

        return [
            'matched_client' => true,
            'client_id' => $client->getKey(),
            'client_cuid' => $client->cuid,
            'client_status' => $client->status,
            'has_email' => filled($client->email),
            'has_phone' => filled($client->phone),
            'current_score' => $client->current_score,
            'latest_report_cycle' => $latestCycle?->cycle_label,
            'latest_report_started_at' => optional($latestCycle?->started_at)?->toDateString(),
            'latest_report_age_days' => $latestCycle?->started_at ? now()->diffInDays($latestCycle->started_at) : null,
            'latest_report_bureau_snapshot_count' => $latestCycle?->bureau_snapshots_count,
            'document_count' => $usableDocuments->count(),
            'missing_documents' => $missingDocuments,
            'provider_accounts' => $client->providerAccounts->count(),
            'provider_ready_accounts' => $providerReadyCount,
            'billing_status' => $client->billingProfile?->status,
            'billing_amount' => $client->billingProfile?->amount,
            'next_due_at' => optional($client->billingProfile?->next_due_at)?->toDateTimeString(),
            'last_payment_amount' => $lastPayment?->amount,
            'last_payment_status' => $lastPayment?->status,
            'last_payment_at' => optional($lastPayment?->paid_at)?->toDateTimeString(),
        ];
    }

    /**
     * @param  array{event_type:string,object_type:?string,object_id:?string}  $event
     * @param  array<string, mixed>  $signals
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function fallbackDecision(?Client $client, array $event, array $signals, array $payload): array
    {
        $fingerprint = Str::lower($event['event_type'].' '.$event['object_type'].' '.json_encode($payload, JSON_UNESCAPED_SLASHES));

        if (! $client) {
            return [
                'campaign_key' => 'webform-submitted-routing',
                'title' => 'Match CRM event to a CreditSoft client',
                'summary' => 'Twenty sent an event that CreditSoft could not match to a local client.',
                'next_action' => 'Review the CRM record, then attach it to the right CreditSoft client or lead.',
                'priority' => 'normal',
                'channel' => 'task',
                'draft_message' => '',
                'crm_note' => 'CreditSoft received this CRM event but could not match it to a local client yet.',
                'confidence' => 'medium',
            ];
        }

        if (Str::contains($fingerprint, ['sms', 'text reply'])) {
            return $this->decision('sms-reply-received', 'Classify SMS reply', 'A CRM/SMS reply was received and needs handling.', 'Classify the reply, confirm consent status, and draft the next short response.', 'high', 'sms_draft', 'CreditSoft received your message. We are reviewing it and will follow up with the next step.');
        }

        if (Str::contains($fingerprint, ['payment failed', 'failed payment', 'short pay', 'short_pays', 'balance due'])) {
            return $this->decision('payment-failed-recovery', 'Review payment issue', 'A CRM event looks like a failed, short, or balance-due payment.', 'Check Cash App, Zelle, cash/pro bono notes, then decide whether to send a payment follow-up.', 'high', 'email_draft', '');
        }

        if (Str::contains($fingerprint, ['payment', 'invoice', 'paid']) && ! Str::contains($fingerprint, ['failed', 'short'])) {
            return $this->decision('payment-successful-next-step', 'Turn payment into next client step', 'A payment-related CRM event arrived.', 'Confirm report access and required documents so paid clients do not sit idle.', 'normal', 'task', '');
        }

        if (($signals['client_status'] ?? null) === 'lead') {
            return $this->decision('new-lead-nurture', 'Start lead nurture follow-up', 'A CRM event touched an active lead.', 'Send a warm follow-up and ask for one concrete next step.', 'normal', filled($client->phone) ? 'sms_draft' : 'email_draft', 'Hi '.$client->first_name.', this is CreditSoft. I saw your request and can help you with the next step. What is a good time for a quick callback?');
        }

        if (($signals['missing_documents'] ?? []) !== []) {
            return $this->decision('new-client-onboarding', 'Request missing onboarding documents', 'The client is missing one or more required onboarding documents.', 'Ask for the missing documents and review any uploaded files for quality.', 'normal', filled($client->phone) ? 'sms_draft' : 'email_draft', 'Hi '.$client->first_name.', we still need a readable ID, proof of address, or SSN/W2 document before the next credit repair step. Please upload it in the portal when you can.');
        }

        $reportAge = $signals['latest_report_age_days'] ?? null;

        if ($reportAge === null || (is_numeric($reportAge) && (int) $reportAge >= 30) || (int) ($signals['latest_report_bureau_snapshot_count'] ?? 0) < 1) {
            return $this->decision('monthly-report-refresh', 'Queue current report pull', 'The client needs a current usable credit report before campaign follow-up.', 'Queue SmartCredit, IdentityIQ, or the configured provider once and avoid duplicate same-day loops.', 'high', 'task', '');
        }

        return $this->decision('status-field-automation-guardrails', 'Log CRM activity and keep lane clean', 'A CRM event arrived for a matched client.', 'Write a timeline note and keep status movement audit-friendly.', 'low', 'note', '');
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>  $signals
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    protected function decisionFor(?Client $client, array $event, array $signals, array $fallback): array
    {
        if (! (bool) config('creditsoft.integrations.crm.automation.use_ai', true)) {
            return [
                ...$fallback,
                'ai_used' => false,
            ];
        }

        try {
            $ai = $this->aiService->generateCrmAutomationPlan($client, $event, $signals, $fallback);

            return [
                ...$fallback,
                ...Arr::except($ai, ['meta']),
                'ai_used' => true,
                'ai_meta' => $ai['meta'] ?? [],
            ];
        } catch (Throwable $exception) {
            return [
                ...$fallback,
                'ai_used' => false,
                'ai_error' => Str::limit($exception->getMessage(), 240),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $decision
     * @return array<string, mixed>
     */
    protected function applyDecision(CrmAutomationEvent $event, ?Client $client, array $decision, ?User $actor): array
    {
        $effects = [];
        $details = trim(implode("\n\n", array_filter([
            'Next action: '.($decision['next_action'] ?? 'Review the CRM event.'),
            'Summary: '.($decision['summary'] ?? 'CreditSoft processed a CRM automation event.'),
            'Campaign: '.($decision['campaign_key'] ?? 'none'),
            'CRM event: '.$event->event_type.($event->object_type ? ' / '.$event->object_type : '').($event->object_id ? ' / '.$event->object_id : ''),
        ])));

        if ((bool) config('creditsoft.integrations.crm.automation.create_tasks', true) && ($decision['channel'] ?? 'task') !== 'note') {
            $task = Task::query()->firstOrCreate(
                [
                    'client_id' => $client?->getKey(),
                    'source' => 'crm_automation',
                    'title' => (string) ($decision['title'] ?? 'CRM automation review'),
                    'status' => 'open',
                ],
                [
                    'assigned_to' => $actor?->getKey() ?? $client?->assigned_to,
                    'details' => $details,
                    'priority' => $decision['priority'] ?? 'normal',
                    'due_at' => now()->addDay(),
                ],
            );

            $effects['task_id'] = $task->getKey();
        }

        if ($client && (bool) config('creditsoft.integrations.crm.automation.create_notes', true)) {
            $note = CaseNote::query()->firstOrCreate(
                [
                    'client_id' => $client->getKey(),
                    'visibility' => 'working_note',
                    'note' => 'CRM automation #'.$event->getKey().': '.($decision['crm_note'] ?? $decision['summary'] ?? 'CreditSoft processed a CRM event.'),
                ],
                [
                    'user_id' => $actor?->getKey(),
                    'sync_eligible' => false,
                    'ai_summary' => $decision['summary'] ?? null,
                ],
            );

            $effects['note_id'] = $note->getKey();
        }

        if (
            $client
            && (bool) config('creditsoft.integrations.crm.automation.queue_outbound_drafts', true)
            && in_array($decision['channel'] ?? null, ['email_draft', 'sms_draft'], true)
            && filled($decision['draft_message'] ?? null)
        ) {
            $signal = OutboundSignal::create([
                'client_id' => $client->getKey(),
                'event_type' => 'crm_automation.'.$decision['channel'],
                'visibility' => 'internal',
                'payload' => [
                    'crm_automation_event_id' => $event->getKey(),
                    'campaign_key' => $decision['campaign_key'] ?? null,
                    'channel' => $decision['channel'],
                    'draft_message' => $decision['draft_message'],
                    'next_action' => $decision['next_action'] ?? null,
                ],
                'sanitized_payload' => [
                    'client_id' => $client->getKey(),
                    'campaign_key' => $decision['campaign_key'] ?? null,
                    'channel' => $decision['channel'],
                    'message_preview' => Str::limit((string) $decision['draft_message'], 180),
                ],
                'status' => 'pending',
                'queued_at' => now(),
            ]);

            $effects['outbound_signal_id'] = $signal->getKey();
        }

        return $effects;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decision(string $campaignKey, string $title, string $summary, string $nextAction, string $priority, string $channel, string $draftMessage): array
    {
        return [
            'campaign_key' => $campaignKey,
            'title' => $title,
            'summary' => $summary,
            'next_action' => $nextAction,
            'priority' => $priority,
            'channel' => $channel,
            'draft_message' => $draftMessage,
            'crm_note' => $summary.' Next: '.$nextAction,
            'confidence' => 'rule',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $paths
     */
    protected function firstString(array $payload, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    protected function emailsFromPayload(array $payload): array
    {
        return collect($this->flattenPayloadStrings($payload))
            ->flatMap(function (string $value): array {
                preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches);

                return $matches[0] ?? [];
            })
            ->map(fn (string $email): string => strtolower(trim($email)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    protected function phonesFromPayload(array $payload): array
    {
        return collect($this->flattenPayloadStrings($payload))
            ->flatMap(function (string $value): array {
                preg_match_all('/(?:\+?1[\s.\-]?)?(?:\(?\d{3}\)?[\s.\-]?)\d{3}[\s.\-]?\d{4}/', $value, $matches);

                return $matches[0] ?? [];
            })
            ->map(fn (string $phone): ?string => PhoneNumber::normalize($phone))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    protected function flattenPayloadStrings(mixed $value): array
    {
        if (is_scalar($value)) {
            $string = trim((string) $value);

            return $string !== '' ? [$string] : [];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->flatMap(fn (mixed $entry): array => $this->flattenPayloadStrings($entry))
            ->values()
            ->all();
    }
}
