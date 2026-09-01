<?php

namespace App\Services;

use App\Models\CaseNote;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\ClientPayment;
use App\Models\CrmAutomationEvent;
use App\Models\OutboundSignal;
use App\Models\Task;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CreditsoftCrmRosterBridgeService
{
    protected const SOURCE = 'creditsoft_roster_bridge';

    protected const NAMESPACE_UUID = '2a1e3dd3-2b7f-4d22-a3e2-31b6690fbda8';

    public function __construct(
        protected OfficeGrowthRuntime $growth,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function sync(bool $dryRun = false, bool $hideUnmanagedSeedRows = true, bool $includeTerminated = false): array
    {
        if (! (bool) config('creditsoft.integrations.crm.enabled', false)) {
            return [
                'enabled' => false,
                'skipped' => true,
                'reason' => 'crm_disabled',
            ];
        }

        return $this->withCrmConnection(function (Connection $connection) use ($dryRun, $hideUnmanagedSeedRows, $includeTerminated): array {
            $workspace = $connection->table('core.workspace')
                ->orderByDesc('createdAt')
                ->first(['id', 'databaseSchema']);
            $workspaceId = (string) ($workspace?->id ?? '');
            $schema = (string) ($workspace?->databaseSchema ?? '');

            if ($workspaceId === '' || ! preg_match('/^workspace_[a-z0-9_]+$/', $schema)) {
                return [
                    'enabled' => true,
                    'skipped' => true,
                    'reason' => 'workspace_missing',
                ];
            }

            $clients = $this->clientsForCrm()->get();
            $leads = $this->leadsForCrm()->get();
            $terminated = $this->terminatedForCrm()->get();
            $fired = $this->firedForCrm()->get();
            $canceled = $this->canceledForCrm()->get();
            $graduated = $this->graduatedForCrm()->get();
            $terminatedRecovery = $includeTerminated ? $terminated : collect();
            $affiliates = collect($this->growth->affiliates());
            $summary = [
                'enabled' => true,
                'skipped' => false,
                'dry_run' => $dryRun,
                'include_terminated_recovery' => $includeTerminated,
                'workspace_id' => $workspaceId,
                'schema' => $schema,
                'clients_seen' => $clients->count(),
                'leads_seen' => $leads->count(),
                'terminated_seen' => $terminated->count(),
                'fired_seen' => $fired->count(),
                'canceled_seen' => $canceled->count(),
                'graduated_seen' => $graduated->count(),
                'affiliates_seen' => $affiliates->count(),
                'clients_synced' => 0,
                'leads_synced' => 0,
                'terminated_synced' => 0,
                'fired_synced' => 0,
                'canceled_synced' => 0,
                'graduated_synced' => 0,
                'terminated_recovery_opportunities_synced' => 0,
                'affiliates_synced' => 0,
                'campaign_templates_synced' => 0,
                'activity_tasks_seen' => 0,
                'activity_tasks_synced' => 0,
                'activity_notes_seen' => 0,
                'activity_notes_synced' => 0,
                'activity_outbound_drafts_seen' => 0,
                'activity_outbound_drafts_synced' => 0,
                'activity_payments_seen' => 0,
                'activity_payments_synced' => 0,
                'activity_documents_seen' => 0,
                'activity_documents_synced' => 0,
                'activity_ai_events_seen' => 0,
                'activity_ai_events_synced' => 0,
                'activity_rows_skipped_missing_crm_target' => 0,
                'seed_rows_hidden' => 0,
                'stale_rows_hidden' => 0,
            ];

            if ($dryRun) {
                return $summary;
            }

            $this->alignAffiliateObjectLabels($connection);
            $summary['campaign_templates_synced'] = $this->syncCampaignTemplates($connection, $schema, $workspaceId);

            if ($hideUnmanagedSeedRows) {
                foreach (['person', 'opportunity', 'company'] as $table) {
                    $summary['seed_rows_hidden'] += $this->hideUnmanagedSeedRows($connection, $schema, $table);
                }
            }

            $activeIds = [
                'client_person' => [],
                'terminated_person' => [],
                'fired_person' => [],
                'canceled_person' => [],
                'graduated_person' => [],
                'lead_opportunity' => [],
                'terminated_opportunity' => [],
                'company' => [],
            ];

            foreach ($clients->values() as $position => $client) {
                /** @var Client $client */
                $id = $this->syncClientPerson($connection, $schema, $client, $position, 'client', 'Active client');
                $activeIds['client_person'][] = $id;
                $summary['clients_synced']++;
            }

            foreach ($terminated->values() as $position => $client) {
                /** @var Client $client */
                $id = $this->syncClientPerson($connection, $schema, $client, $position + $clients->count(), 'terminated', 'Terminated');
                $activeIds['terminated_person'][] = $id;
                $summary['terminated_synced']++;
            }

            foreach ($fired->values() as $position => $client) {
                /** @var Client $client */
                $id = $this->syncClientPerson($connection, $schema, $client, $position + $clients->count() + $terminated->count(), 'fired', 'Fired');
                $activeIds['fired_person'][] = $id;
                $summary['fired_synced']++;
            }

            foreach ($canceled->values() as $position => $client) {
                /** @var Client $client */
                $id = $this->syncClientPerson($connection, $schema, $client, $position + $clients->count() + $terminated->count() + $fired->count(), 'canceled', 'Canceled');
                $activeIds['canceled_person'][] = $id;
                $summary['canceled_synced']++;
            }

            foreach ($graduated->values() as $position => $client) {
                /** @var Client $client */
                $id = $this->syncClientPerson($connection, $schema, $client, $position + $clients->count() + $terminated->count() + $fired->count() + $canceled->count(), 'graduated', 'Graduated');
                $activeIds['graduated_person'][] = $id;
                $summary['graduated_synced']++;
            }

            foreach ($leads->values() as $position => $lead) {
                /** @var Client $lead */
                $id = $this->syncLeadOpportunity($connection, $schema, $lead, $position);
                $activeIds['lead_opportunity'][] = $id;
                $summary['leads_synced']++;
            }

            foreach ($terminatedRecovery->values() as $position => $client) {
                /** @var Client $client */
                $id = $this->syncTerminatedOpportunity($connection, $schema, $client, $position);
                $activeIds['terminated_opportunity'][] = $id;
                $summary['terminated_recovery_opportunities_synced']++;
            }

            foreach ($affiliates->values() as $position => $affiliate) {
                $id = $this->syncAffiliateCompany($connection, $schema, $affiliate, $position);
                $activeIds['company'][] = $id;
                $summary['affiliates_synced']++;
            }

            $summary['stale_rows_hidden'] += $this->hideStaleCreditsoftRows($connection, $schema, 'person', 'client', $activeIds['client_person']);
            $summary['stale_rows_hidden'] += $this->hideStaleCreditsoftRows($connection, $schema, 'person', 'terminated', $activeIds['terminated_person']);
            $summary['stale_rows_hidden'] += $this->hideStaleCreditsoftRows($connection, $schema, 'person', 'fired', $activeIds['fired_person']);
            $summary['stale_rows_hidden'] += $this->hideStaleCreditsoftRows($connection, $schema, 'person', 'canceled', $activeIds['canceled_person']);
            $summary['stale_rows_hidden'] += $this->hideStaleCreditsoftRows($connection, $schema, 'person', 'graduated', $activeIds['graduated_person']);
            $summary['stale_rows_hidden'] += $this->hideStaleCreditsoftRows($connection, $schema, 'opportunity', 'lead', $activeIds['lead_opportunity']);
            $summary['stale_rows_hidden'] += $this->hideStaleCreditsoftRows($connection, $schema, 'opportunity', 'terminated', $activeIds['terminated_opportunity']);
            $summary['stale_rows_hidden'] += $this->hideStaleCreditsoftRows($connection, $schema, 'company', 'affiliate', $activeIds['company']);

            $summary = array_merge($summary, $this->syncActivityRecords($connection, $schema));

            return $summary;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function syncCampaignTemplatesOnly(bool $dryRun = false): array
    {
        if (! (bool) config('creditsoft.integrations.crm.enabled', false)) {
            return [
                'enabled' => false,
                'skipped' => true,
                'reason' => 'crm_disabled',
            ];
        }

        return $this->withCrmConnection(function (Connection $connection) use ($dryRun): array {
            $workspace = $connection->table('core.workspace')
                ->orderByDesc('createdAt')
                ->first(['id', 'databaseSchema']);
            $workspaceId = (string) ($workspace?->id ?? '');
            $schema = (string) ($workspace?->databaseSchema ?? '');

            if ($workspaceId === '' || ! preg_match('/^workspace_[a-z0-9_]+$/', $schema)) {
                return [
                    'enabled' => true,
                    'skipped' => true,
                    'reason' => 'workspace_missing',
                ];
            }

            $summary = [
                'enabled' => true,
                'skipped' => false,
                'dry_run' => $dryRun,
                'workspace_id' => $workspaceId,
                'schema' => $schema,
                'campaign_templates_seen' => count($this->campaignTemplates()),
                'campaign_templates_synced' => 0,
            ];

            if ($dryRun) {
                return $summary;
            }

            $summary['campaign_templates_synced'] = $this->syncCampaignTemplates($connection, $schema, $workspaceId);

            return $summary;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function syncActivityOnly(bool $dryRun = false): array
    {
        if (! (bool) config('creditsoft.integrations.crm.enabled', false)) {
            return [
                'enabled' => false,
                'skipped' => true,
                'reason' => 'crm_disabled',
            ];
        }

        return $this->withCrmConnection(function (Connection $connection) use ($dryRun): array {
            $workspace = $connection->table('core.workspace')
                ->orderByDesc('createdAt')
                ->first(['id', 'databaseSchema']);
            $workspaceId = (string) ($workspace?->id ?? '');
            $schema = (string) ($workspace?->databaseSchema ?? '');

            if ($workspaceId === '' || ! preg_match('/^workspace_[a-z0-9_]+$/', $schema)) {
                return [
                    'enabled' => true,
                    'skipped' => true,
                    'reason' => 'workspace_missing',
                ];
            }

            return array_merge([
                'enabled' => true,
                'skipped' => false,
                'dry_run' => $dryRun,
                'workspace_id' => $workspaceId,
                'schema' => $schema,
            ], $this->syncActivityRecords($connection, $schema, $dryRun));
        });
    }

    /**
     * @return array<string, int>
     */
    protected function syncActivityRecords(Connection $connection, string $schema, bool $dryRun = false): array
    {
        $summary = [
            'activity_tasks_seen' => 0,
            'activity_tasks_synced' => 0,
            'activity_notes_seen' => 0,
            'activity_notes_synced' => 0,
            'activity_outbound_drafts_seen' => 0,
            'activity_outbound_drafts_synced' => 0,
            'activity_payments_seen' => 0,
            'activity_payments_synced' => 0,
            'activity_documents_seen' => 0,
            'activity_documents_synced' => 0,
            'activity_ai_events_seen' => 0,
            'activity_ai_events_synced' => 0,
            'activity_rows_skipped_missing_crm_target' => 0,
        ];
        $activeActivityIds = [
            'task' => [],
            'taskTarget' => [],
            'note' => [],
            'noteTarget' => [],
            'timelineActivity' => [],
        ];

        $tasks = Task::query()
            ->with('client')
            ->whereNotNull('client_id')
            ->where('updated_at', '>=', now()->subDays(120))
            ->latest('updated_at')
            ->limit(250)
            ->get();

        $summary['activity_tasks_seen'] = $tasks->count();
        foreach ($tasks as $task) {
            if ($dryRun) {
                continue;
            }

            $synced = $this->syncTaskActivity($connection, $schema, $task);
            $summary[$synced ? 'activity_tasks_synced' : 'activity_rows_skipped_missing_crm_target']++;
            if ($synced && $task->client) {
                $activeActivityIds['task'][] = $this->activityUuid('task', $task, $task->client);
                $activeActivityIds['taskTarget'][] = $this->activityUuid('task-target', $task, $task->client);
            }
        }

        $notes = CaseNote::query()
            ->with('client')
            ->whereIn('visibility', ['shareable_case_brief', 'working_note'])
            ->where('updated_at', '>=', now()->subDays(120))
            ->latest('updated_at')
            ->limit(250)
            ->get();

        $summary['activity_notes_seen'] = $notes->count();
        foreach ($notes as $note) {
            if ($dryRun) {
                continue;
            }

            $synced = $this->syncNoteActivity($connection, $schema, $note);
            $summary[$synced ? 'activity_notes_synced' : 'activity_rows_skipped_missing_crm_target']++;
            if ($synced && $note->client) {
                $activeActivityIds['note'][] = $this->activityUuid('case-note', $note, $note->client);
                $activeActivityIds['noteTarget'][] = $this->activityUuid('case-note-target', $note, $note->client);
            }
        }

        $signals = OutboundSignal::query()
            ->with('client')
            ->whereNotNull('client_id')
            ->where('updated_at', '>=', now()->subDays(120))
            ->latest('updated_at')
            ->limit(250)
            ->get();

        $summary['activity_outbound_drafts_seen'] = $signals->count();
        foreach ($signals as $signal) {
            if ($dryRun) {
                continue;
            }

            $synced = $this->syncOutboundDraftActivity($connection, $schema, $signal);
            $summary[$synced ? 'activity_outbound_drafts_synced' : 'activity_rows_skipped_missing_crm_target']++;
            if ($synced && $signal->client) {
                $activeActivityIds['task'][] = $this->activityUuid('outbound-signal', $signal, $signal->client);
                $activeActivityIds['taskTarget'][] = $this->activityUuid('outbound-signal-target', $signal, $signal->client);
            }
        }

        $payments = ClientPayment::query()
            ->with('client')
            ->whereNotNull('client_id')
            ->where(function (Builder $query): void {
                $query
                    ->where('updated_at', '>=', now()->subDays(180))
                    ->orWhere('paid_at', '>=', now()->subDays(180));
            })
            ->orderByRaw('coalesce(paid_at, updated_at, created_at) desc')
            ->limit(250)
            ->get();

        $summary['activity_payments_seen'] = $payments->count();
        foreach ($payments as $payment) {
            if ($dryRun) {
                continue;
            }

            $synced = $this->syncPaymentActivity($connection, $schema, $payment);
            $summary[$synced ? 'activity_payments_synced' : 'activity_rows_skipped_missing_crm_target']++;
            if ($synced && $payment->client) {
                $activeActivityIds['timelineActivity'][] = $this->activityUuid('payment', $payment, $payment->client);
            }
        }

        $documents = ClientDocument::query()
            ->with('client')
            ->whereNotNull('client_id')
            ->where(function (Builder $query): void {
                $query
                    ->where('updated_at', '>=', now()->subDays(180))
                    ->orWhere('uploaded_at', '>=', now()->subDays(180));
            })
            ->orderByRaw('coalesce(uploaded_at, updated_at, created_at) desc')
            ->limit(250)
            ->get();

        $summary['activity_documents_seen'] = $documents->count();
        foreach ($documents as $document) {
            if ($dryRun) {
                continue;
            }

            $synced = $this->syncDocumentActivity($connection, $schema, $document);
            $summary[$synced ? 'activity_documents_synced' : 'activity_rows_skipped_missing_crm_target']++;
            if ($synced && $document->client) {
                $activeActivityIds['timelineActivity'][] = $this->activityUuid('document', $document, $document->client);
            }
        }

        $automationEvents = CrmAutomationEvent::query()
            ->with('client')
            ->whereNotNull('client_id')
            ->where('updated_at', '>=', now()->subDays(120))
            ->latest('updated_at')
            ->limit(250)
            ->get();

        $summary['activity_ai_events_seen'] = $automationEvents->count();
        foreach ($automationEvents as $event) {
            if ($dryRun) {
                continue;
            }

            $synced = $this->syncAutomationEventActivity($connection, $schema, $event);
            $summary[$synced ? 'activity_ai_events_synced' : 'activity_rows_skipped_missing_crm_target']++;
            if ($synced && $event->client) {
                $activeActivityIds['timelineActivity'][] = $this->activityUuid('automation-event', $event, $event->client);
            }
        }

        if (! $dryRun) {
            foreach ($activeActivityIds as $table => $ids) {
                $this->hideStaleActivityRows($connection, $schema, $table, array_values(array_unique($ids)));
            }
        }

        return $summary;
    }

    protected function syncTaskActivity(Connection $connection, string $schema, Task $task): bool
    {
        $target = $this->crmTargetForClient($connection, $schema, $task->client);

        if (! $target) {
            return false;
        }

        $taskId = $this->activityUuid('task', $task, $task->client);
        $context = $this->activityContext('task', $task->getKey(), $task->client);
        $status = match (Str::lower((string) $task->status)) {
            'done', 'closed', 'complete', 'completed' => 'DONE',
            'doing', 'in_progress', 'in-progress' => 'IN_PROGRESS',
            default => 'TODO',
        };
        $body = $this->activityMarkdown([
            'CreditSoft task',
            $task->details,
            'Priority: '.Str::of((string) $task->priority)->replace('_', ' ')->headline(),
            'Source: '.Str::of((string) $task->source)->replace('_', ' ')->headline(),
            $task->due_at ? 'Due: '.$task->due_at->toDayDateTimeString() : null,
        ]);

        $this->upsertRow($connection, $schema, 'task', [
            'id' => $taskId,
            'title' => Str::limit((string) $task->title, 180, ''),
            'bodyV2Markdown' => $body,
            'bodyV2Blocknote' => null,
            'dueAt' => $task->due_at?->toIso8601String(),
            'status' => $status,
            'createdByName' => 'CreditSoft',
            'createdByContext' => $this->json($context),
            'updatedByName' => 'CreditSoft',
            'updatedByContext' => $this->json($context),
            'deletedAt' => null,
        ], [
            'title',
            'bodyV2Markdown',
            'bodyV2Blocknote',
            'dueAt',
            'status',
            'updatedByName',
            'updatedByContext',
            'deletedAt',
        ]);

        $this->syncTaskTarget($connection, $schema, $taskId, $this->activityUuid('task-target', $task, $task->client), $target, $context);

        return true;
    }

    protected function syncNoteActivity(Connection $connection, string $schema, CaseNote $note): bool
    {
        $target = $this->crmTargetForClient($connection, $schema, $note->client);

        if (! $target) {
            return false;
        }

        $noteId = $this->activityUuid('case-note', $note, $note->client);
        $context = $this->activityContext('case_note', $note->getKey(), $note->client);
        $body = $this->activityMarkdown([
            'CreditSoft case note',
            $note->ai_summary ? 'AI summary: '.$note->ai_summary : null,
            $note->note,
            'Visibility: '.Str::of((string) $note->visibility)->replace('_', ' ')->headline(),
        ]);

        $this->upsertRow($connection, $schema, 'note', [
            'id' => $noteId,
            'title' => Str::limit('CreditSoft note - '.Str::of((string) $note->visibility)->replace('_', ' ')->headline(), 180, ''),
            'bodyV2Markdown' => $body,
            'bodyV2Blocknote' => null,
            'createdByName' => 'CreditSoft',
            'createdByContext' => $this->json($context),
            'updatedByName' => 'CreditSoft',
            'updatedByContext' => $this->json($context),
            'deletedAt' => null,
        ], [
            'title',
            'bodyV2Markdown',
            'bodyV2Blocknote',
            'updatedByName',
            'updatedByContext',
            'deletedAt',
        ]);

        $this->syncNoteTarget($connection, $schema, $noteId, $this->activityUuid('case-note-target', $note, $note->client), $target, $context);

        return true;
    }

    protected function syncOutboundDraftActivity(Connection $connection, string $schema, OutboundSignal $signal): bool
    {
        $target = $this->crmTargetForClient($connection, $schema, $signal->client);

        if (! $target) {
            return false;
        }

        $payload = (array) ($signal->sanitized_payload ?: $signal->payload ?: []);
        $draft = (string) (
            data_get($payload, 'draft_message')
            ?: data_get($payload, 'message')
            ?: data_get($payload, 'body')
            ?: data_get($payload, 'text')
            ?: ''
        );
        $channel = (string) (data_get($payload, 'channel') ?: Str::afterLast((string) $signal->event_type, '.'));
        $taskId = $this->activityUuid('outbound-signal', $signal, $signal->client);
        $context = $this->activityContext('outbound_signal', $signal->getKey(), $signal->client);
        $body = $this->activityMarkdown([
            'CreditSoft outbound draft',
            'Channel: '.Str::of($channel !== '' ? $channel : 'message')->replace('_', ' ')->headline(),
            'Event: '.$signal->event_type,
            $draft !== '' ? "Draft:\n".$draft : 'Draft payload: '.$this->safeJsonSnippet($payload),
            'Status: '.Str::of((string) $signal->status)->replace('_', ' ')->headline(),
        ]);

        $this->upsertRow($connection, $schema, 'task', [
            'id' => $taskId,
            'title' => Str::limit('Review outbound draft - '.Str::of((string) $signal->event_type)->replace(['.', '_'], ' ')->headline(), 180, ''),
            'bodyV2Markdown' => $body,
            'bodyV2Blocknote' => null,
            'dueAt' => null,
            'status' => 'TODO',
            'createdByName' => 'CreditSoft',
            'createdByContext' => $this->json($context),
            'updatedByName' => 'CreditSoft',
            'updatedByContext' => $this->json($context),
            'deletedAt' => null,
        ], [
            'title',
            'bodyV2Markdown',
            'bodyV2Blocknote',
            'dueAt',
            'status',
            'updatedByName',
            'updatedByContext',
            'deletedAt',
        ]);

        $this->syncTaskTarget($connection, $schema, $taskId, $this->activityUuid('outbound-signal-target', $signal, $signal->client), $target, $context);

        return true;
    }

    protected function syncPaymentActivity(Connection $connection, string $schema, ClientPayment $payment): bool
    {
        $target = $this->crmTargetForClient($connection, $schema, $payment->client);

        if (! $target) {
            return false;
        }

        $label = trim(implode(' ', array_filter([
            'Payment',
            (string) $payment->status !== '' ? Str::of((string) $payment->status)->replace('_', ' ')->lower()->value() : null,
            $this->formatMoney((float) $payment->amount, (string) $payment->currency),
        ])));

        $this->syncTimelineActivity($connection, $schema, [
            'id' => $this->activityUuid('payment', $payment, $payment->client),
            'happens_at' => $payment->paid_at?->toIso8601String() ?: $payment->updated_at?->toIso8601String() ?: now()->toIso8601String(),
            'name' => $label,
            'properties' => [
                'type' => 'payment',
                'status' => $payment->status,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency ?: 'USD',
                'gateway' => $payment->gateway_name,
                'reference' => $payment->reference,
                'notes' => $payment->notes,
            ],
            'context' => $this->activityContext('payment', $payment->getKey(), $payment->client),
        ], $target);

        return true;
    }

    protected function syncDocumentActivity(Connection $connection, string $schema, ClientDocument $document): bool
    {
        $target = $this->crmTargetForClient($connection, $schema, $document->client);

        if (! $target) {
            return false;
        }

        $title = trim((string) $document->title) ?: trim((string) $document->file_name) ?: 'Client document';

        $this->syncTimelineActivity($connection, $schema, [
            'id' => $this->activityUuid('document', $document, $document->client),
            'happens_at' => $document->uploaded_at?->toIso8601String() ?: $document->updated_at?->toIso8601String() ?: now()->toIso8601String(),
            'name' => 'Document uploaded - '.Str::limit($title, 120, ''),
            'properties' => [
                'type' => 'document',
                'title' => $title,
                'category' => $document->category,
                'file_name' => $document->file_name,
                'mime_type' => $document->mime_type,
                'file_size' => $document->file_size,
                'portal_visible' => (bool) $document->portal_visible,
            ],
            'context' => $this->activityContext('document', $document->getKey(), $document->client),
        ], $target);

        return true;
    }

    protected function syncAutomationEventActivity(Connection $connection, string $schema, CrmAutomationEvent $event): bool
    {
        $target = $this->crmTargetForClient($connection, $schema, $event->client);

        if (! $target) {
            return false;
        }

        $campaign = (string) (data_get($event->decision, 'campaign_key') ?: data_get($event->decision, 'title') ?: $event->event_type);

        $this->syncTimelineActivity($connection, $schema, [
            'id' => $this->activityUuid('automation-event', $event, $event->client),
            'happens_at' => $event->processed_at?->toIso8601String() ?: $event->updated_at?->toIso8601String() ?: now()->toIso8601String(),
            'name' => 'AI workflow decision - '.Str::of($campaign)->replace(['_', '-'], ' ')->headline(),
            'properties' => [
                'type' => 'crm_automation_event',
                'event_type' => $event->event_type,
                'object_type' => $event->object_type,
                'status' => $event->status,
                'priority' => $event->priority,
                'campaign_key' => data_get($event->decision, 'campaign_key'),
                'channel' => data_get($event->decision, 'channel'),
                'summary' => data_get($event->decision, 'summary'),
                'next_action' => data_get($event->decision, 'next_action'),
            ],
            'context' => $this->activityContext('crm_automation_event', $event->getKey(), $event->client),
        ], $target);

        return true;
    }

    /**
     * @param  array{id:string,happens_at:string,name:string,properties:array<string,mixed>,context:array<string,mixed>}  $activity
     * @param  array{record_id:string,cached_name:string,target_person_id:?string,target_opportunity_id:?string}  $target
     */
    protected function syncTimelineActivity(Connection $connection, string $schema, array $activity, array $target): void
    {
        $this->upsertRow($connection, $schema, 'timelineActivity', [
            'id' => $activity['id'],
            'happensAt' => $activity['happens_at'],
            'name' => Str::limit($activity['name'], 180, ''),
            'properties' => $this->json($activity['properties']),
            'linkedRecordCachedName' => $target['cached_name'],
            'linkedRecordId' => $target['record_id'],
            'targetPersonId' => $target['target_person_id'],
            'targetOpportunityId' => $target['target_opportunity_id'],
            'createdByName' => 'CreditSoft',
            'createdByContext' => $this->json($activity['context']),
            'updatedByName' => 'CreditSoft',
            'updatedByContext' => $this->json($activity['context']),
            'deletedAt' => null,
        ], [
            'happensAt',
            'name',
            'properties',
            'linkedRecordCachedName',
            'linkedRecordId',
            'targetPersonId',
            'targetOpportunityId',
            'updatedByName',
            'updatedByContext',
            'deletedAt',
        ]);
    }

    /**
     * @param  array{record_id:string,cached_name:string,target_person_id:?string,target_opportunity_id:?string}  $target
     * @param  array<string, mixed>  $context
     */
    protected function syncTaskTarget(Connection $connection, string $schema, string $taskId, string $targetId, array $target, array $context): void
    {
        $this->upsertRow($connection, $schema, 'taskTarget', [
            'id' => $targetId,
            'taskId' => $taskId,
            'targetPersonId' => $target['target_person_id'],
            'targetOpportunityId' => $target['target_opportunity_id'],
            'targetCompanyId' => null,
            'createdByName' => 'CreditSoft',
            'createdByContext' => $this->json($context),
            'updatedByName' => 'CreditSoft',
            'updatedByContext' => $this->json($context),
            'deletedAt' => null,
        ], [
            'taskId',
            'targetPersonId',
            'targetOpportunityId',
            'targetCompanyId',
            'updatedByName',
            'updatedByContext',
            'deletedAt',
        ]);
    }

    /**
     * @param  array{record_id:string,cached_name:string,target_person_id:?string,target_opportunity_id:?string}  $target
     * @param  array<string, mixed>  $context
     */
    protected function syncNoteTarget(Connection $connection, string $schema, string $noteId, string $targetId, array $target, array $context): void
    {
        $this->upsertRow($connection, $schema, 'noteTarget', [
            'id' => $targetId,
            'noteId' => $noteId,
            'targetPersonId' => $target['target_person_id'],
            'targetOpportunityId' => $target['target_opportunity_id'],
            'targetCompanyId' => null,
            'createdByName' => 'CreditSoft',
            'createdByContext' => $this->json($context),
            'updatedByName' => 'CreditSoft',
            'updatedByContext' => $this->json($context),
            'deletedAt' => null,
        ], [
            'noteId',
            'targetPersonId',
            'targetOpportunityId',
            'targetCompanyId',
            'updatedByName',
            'updatedByContext',
            'deletedAt',
        ]);
    }

    /**
     * @return array{record_id:string,cached_name:string,target_person_id:?string,target_opportunity_id:?string}|null
     */
    protected function crmTargetForClient(Connection $connection, string $schema, ?Client $client): ?array
    {
        if (! $client || trim((string) $client->cuid) === '') {
            return null;
        }

        $personId = $this->existingCreditsoftId($connection, $schema, 'person', (string) $client->cuid);
        $opportunityId = $this->existingCreditsoftId($connection, $schema, 'opportunity', (string) $client->cuid);
        $recordId = $personId ?: $opportunityId;

        if (! $recordId) {
            return null;
        }

        return [
            'record_id' => $recordId,
            'cached_name' => trim((string) $client->display_name) ?: trim((string) $client->first_name.' '.$client->last_name) ?: 'CreditSoft client '.$client->getKey(),
            'target_person_id' => $personId,
            'target_opportunity_id' => $personId ? null : $opportunityId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function activityContext(string $kind, mixed $id, ?Client $client): array
    {
        return [
            'source' => self::SOURCE,
            'creditsoft' => [
                'kind' => 'activity',
                'activity_kind' => $kind,
                'activity_id' => is_scalar($id) ? (string) $id : null,
                'client_id' => $client?->getKey(),
                'cuid' => $client?->cuid,
                'synced_at' => now()->toIso8601String(),
            ],
        ];
    }

    protected function activityUuid(string $kind, Model $model, ?Client $client): string
    {
        return $this->uuidFor(implode(':', [
            'crm-activity',
            $kind,
            $client?->cuid ?: 'clientless',
            $model->getKey(),
        ]));
    }

    /**
     * @param  list<string>  $activeIds
     */
    protected function hideStaleActivityRows(Connection $connection, string $schema, string $table, array $activeIds): int
    {
        $params = [
            'CreditSoft',
            $this->json([
                'source' => self::SOURCE,
                'action' => 'hide_stale_creditsoft_activity',
                'synced_at' => now()->toIso8601String(),
            ]),
            'activity',
        ];
        $idFilter = '';

        if ($activeIds !== []) {
            $idFilter = ' and "id" not in ('.implode(', ', array_fill(0, count($activeIds), '?')).')';
            array_push($params, ...$activeIds);
        }

        return $connection->update(
            'update '.$this->qualifiedTable($schema, $table).'
             set "deletedAt" = now(),
                 "updatedAt" = now(),
                 "updatedByName" = ?,
                 "updatedByContext" = ?::jsonb
             where "deletedAt" is null
               and coalesce(
                    jsonb_extract_path_text("createdByContext", \'creditsoft\', \'kind\'),
                    jsonb_extract_path_text("updatedByContext", \'creditsoft\', \'kind\'),
                    \'\'
               ) = ?'
            .$idFilter,
            $params,
        );
    }

    /**
     * @param  list<string|null|\Stringable>  $parts
     */
    protected function activityMarkdown(array $parts): string
    {
        $clean = collect($parts)
            ->map(fn (mixed $part): string => trim((string) $part))
            ->filter()
            ->values();

        return Str::limit($clean->implode("\n\n"), 6000, "\n\n...");
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function safeJsonSnippet(array $payload): string
    {
        if ($payload === []) {
            return 'No draft payload was available.';
        }

        return Str::limit(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}', 1200, '...');
    }

    protected function formatMoney(float $amount, string $currency = 'USD'): string
    {
        $currency = strtoupper(trim($currency) ?: 'USD');

        return $currency.' '.number_format($amount, 2);
    }

    protected function alignAffiliateObjectLabels(Connection $connection): void
    {
        $companyObjectId = $connection->table('core.objectMetadata')
            ->where('nameSingular', 'company')
            ->value('id');

        if (! is_string($companyObjectId) || $companyObjectId === '') {
            return;
        }

        $connection->table('core.objectMetadata')
            ->where('id', $companyObjectId)
            ->update([
                'labelSingular' => 'Affiliate',
                'labelPlural' => 'Affiliates',
                'updatedAt' => now(),
            ]);

        $connection->table('core.view')
            ->where('objectMetadataId', $companyObjectId)
            ->where('name', 'like', '%Company%')
            ->update([
                'name' => DB::raw('replace(name, \'Company\', \'Affiliate\')'),
                'updatedAt' => now(),
            ]);
    }

    protected function syncCampaignTemplates(Connection $connection, string $schema, string $workspaceId): int
    {
        $this->alignCampaignObjectLabels($connection);

        $count = 0;

        foreach ($this->campaignTemplates() as $position => $campaign) {
            $this->syncCampaignTemplate($connection, $schema, $workspaceId, $campaign, $position);
            $count++;
        }

        return $count;
    }

    protected function alignCampaignObjectLabels(Connection $connection): void
    {
        $workflowObjectId = $connection->table('core.objectMetadata')
            ->where('nameSingular', 'workflow')
            ->value('id');

        if (! is_string($workflowObjectId) || $workflowObjectId === '') {
            return;
        }

        $connection->table('core.objectMetadata')
            ->where('id', $workflowObjectId)
            ->update([
                'labelSingular' => 'Campaign',
                'labelPlural' => 'Campaigns',
                'description' => 'CreditSoft lead nurture, onboarding, payment, report-pull, and referral follow-up campaigns.',
                'icon' => 'IconSpeakerphone',
                'updatedAt' => now(),
            ]);

        $connection->table('core.view')
            ->where('objectMetadataId', $workflowObjectId)
            ->where('name', 'like', '%Workflow%')
            ->update([
                'name' => DB::raw('replace(name, \'Workflow\', \'Campaign\')'),
                'icon' => 'IconSpeakerphone',
                'updatedAt' => now(),
            ]);

        $this->alignCampaignNavigation($connection, $workflowObjectId);
    }

    protected function alignCampaignNavigation(Connection $connection, string $workflowObjectId): void
    {
        $objectIdsByName = $connection->table('core.objectMetadata')
            ->whereIn('nameSingular', ['person', 'opportunity', 'company', 'workflow', 'task', 'note', 'dashboard'])
            ->pluck('id', 'nameSingular');

        foreach ([
            'person' => ['position' => 0, 'color' => 'blue'],
            'opportunity' => ['position' => 1, 'color' => 'red'],
            'company' => ['position' => 2, 'color' => 'blue'],
            'workflow' => ['position' => 3, 'color' => 'orange'],
            'task' => ['position' => 4, 'color' => 'turquoise'],
            'note' => ['position' => 5, 'color' => 'turquoise'],
            'dashboard' => ['position' => 6, 'color' => 'gray'],
        ] as $name => $settings) {
            $objectId = $objectIdsByName[$name] ?? null;

            if (! is_string($objectId) || $objectId === '') {
                continue;
            }

            $connection->table('core.navigationMenuItem')
                ->where('targetObjectMetadataId', $objectId)
                ->update([
                    'folderId' => null,
                    'position' => $settings['position'],
                    'color' => $settings['color'],
                    'updatedAt' => now(),
                ]);
        }

        $folderId = $connection->table('core.navigationMenuItem')
            ->where('type', 'FOLDER')
            ->where(function ($query) use ($workflowObjectId): void {
                $query
                    ->where('name', 'Workflows')
                    ->orWhereIn('id', function ($query) use ($workflowObjectId): void {
                        $query
                            ->select('folderId')
                            ->from('core.navigationMenuItem')
                            ->where('targetObjectMetadataId', $workflowObjectId)
                            ->whereNotNull('folderId');
                    });
            })
            ->value('id');

        if (is_string($folderId) && $folderId !== '') {
            $connection->table('core.navigationMenuItem')
                ->where('id', $folderId)
                ->update([
                    'name' => 'Automation History',
                    'position' => 99,
                    'color' => 'gray',
                    'icon' => 'IconHistoryToggle',
                    'updatedAt' => now(),
                ]);
        }
    }

    /**
     * @param  array{
     *     key: string,
     *     name: string,
     *     trigger: string,
     *     audience: string,
     *     goal: string,
     *     ai_guidance: string,
     *     steps: list<array{day: int, title: string, action: string, intelligence: string}>
     * }  $campaign
     */
    protected function syncCampaignTemplate(Connection $connection, string $schema, string $workspaceId, array $campaign, int $position): void
    {
        $workflowId = $this->uuidFor('crm-campaign:'.$campaign['key']);
        $versionId = $this->uuidFor('crm-campaign-version:'.$campaign['key'].':v1');
        $context = $this->json([
            'source' => self::SOURCE,
            'creditsoft' => [
                'kind' => 'campaign_template',
                'campaign_key' => $campaign['key'],
                'goal' => $campaign['goal'],
                'audience' => $campaign['audience'],
                'synced_at' => now()->toIso8601String(),
            ],
        ]);
        $workflowTable = $this->qualifiedTable($schema, 'workflow');
        $versionTable = $this->qualifiedTable($schema, 'workflowVersion');
        $statusType = $this->qualifiedType($schema, 'workflow_statuses_enum');
        $versionStatusType = $this->qualifiedType($schema, 'workflowVersion_status_enum');

        $connection->statement(
            'insert into '.$workflowTable.' (
                "id", "name", "lastPublishedVersionId", "statuses", "position",
                "createdByName", "createdByContext", "updatedByName", "updatedByContext"
             ) values (
                ?, ?, ?, ?::'.$statusType.'[], ?,
                ?, ?::jsonb, ?, ?::jsonb
             )
             on conflict ("id") do update set
                "name" = excluded."name",
                "lastPublishedVersionId" = excluded."lastPublishedVersionId",
                "statuses" = excluded."statuses",
                "position" = excluded."position",
                "updatedByName" = excluded."updatedByName",
                "updatedByContext" = excluded."updatedByContext",
                "updatedAt" = now(),
                "deletedAt" = null',
            [
                $workflowId,
                $campaign['name'],
                $versionId,
                '{DRAFT}',
                $position + 10,
                'CreditSoft',
                $context,
                'CreditSoft',
                $context,
            ],
        );

        $connection->statement(
            'insert into '.$versionTable.' (
                "id", "name", "trigger", "steps", "status", "position", "workflowId",
                "createdByName", "createdByContext", "updatedByName", "updatedByContext"
             ) values (
                ?, ?, ?::jsonb, ?::jsonb, ?::'.$versionStatusType.', ?, ?,
                ?, ?::jsonb, ?, ?::jsonb
             )
             on conflict ("id") do update set
                "name" = excluded."name",
                "trigger" = excluded."trigger",
                "steps" = excluded."steps",
                "status" = excluded."status",
                "position" = excluded."position",
                "workflowId" = excluded."workflowId",
                "updatedByName" = excluded."updatedByName",
                "updatedByContext" = excluded."updatedByContext",
                "updatedAt" = now(),
                "deletedAt" = null',
            [
                $versionId,
                'Starter sequence',
                $this->json($this->campaignTrigger($campaign)),
                $this->json($this->campaignSteps($campaign)),
                'DRAFT',
                1,
                $workflowId,
                'CreditSoft',
                $context,
                'CreditSoft',
                $context,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $campaign
     * @return array<string, mixed>
     */
    protected function campaignTrigger(array $campaign): array
    {
        $firstStepId = $this->uuidFor('crm-campaign-step:'.$campaign['key'].':0');

        return [
            'name' => 'Start when '.$campaign['trigger'],
            'type' => 'MANUAL',
            'settings' => [
                'availability' => ['type' => 'GLOBAL'],
                'icon' => 'IconSpeakerphone',
                'creditsoft' => [
                    'audience' => $campaign['audience'],
                    'goal' => $campaign['goal'],
                    'ai_guidance' => $campaign['ai_guidance'],
                    'start_condition' => $campaign['trigger'],
                    'mode' => 'starter_draft',
                ],
                'outputSchema' => [],
            ],
            'nextStepIds' => [$firstStepId],
        ];
    }

    /**
     * @param  array<string, mixed>  $campaign
     * @return list<array<string, mixed>>
     */
    protected function campaignSteps(array $campaign): array
    {
        $steps = [];
        $stepDefinitions = array_values($campaign['steps']);

        foreach ($stepDefinitions as $index => $step) {
            $stepId = $this->uuidFor('crm-campaign-step:'.$campaign['key'].':'.$index);
            $nextStepId = $index < count($stepDefinitions) - 1
                ? $this->uuidFor('crm-campaign-step:'.$campaign['key'].':'.($index + 1))
                : null;

            $steps[] = [
                'id' => $stepId,
                'name' => sprintf('Day %d: %s', $step['day'], $step['title']),
                'type' => 'CREATE_RECORD',
                'valid' => false,
                'position' => [
                    'x' => 220,
                    'y' => 130 + ($index * 140),
                ],
                'settings' => [
                    'input' => [
                        'objectName' => 'task',
                        'objectRecord' => [
                            'title' => $step['title'],
                            'bodyV2Markdown' => implode("\n\n", [
                                '**Campaign template step**',
                                'Action: '.$step['action'],
                                'AI guidance: '.$step['intelligence'],
                                'Operator check: personalize the message before enabling this draft.',
                            ]),
                            'status' => 'TODO',
                        ],
                    ],
                    'creditsoft' => [
                        'delay_days' => $step['day'],
                        'channel' => $step['action'],
                        'intelligence' => $step['intelligence'],
                        'starter_template' => true,
                    ],
                    'outputSchema' => [],
                    'errorHandlingOptions' => [
                        'retryOnFailure' => ['value' => false],
                        'continueOnFailure' => ['value' => true],
                    ],
                ],
                '__typename' => 'WorkflowAction',
                'nextStepIds' => $nextStepId ? [$nextStepId] : null,
            ];
        }

        return $steps;
    }

    /**
     * @return list<array{
     *     key: string,
     *     name: string,
     *     trigger: string,
     *     audience: string,
     *     goal: string,
     *     ai_guidance: string,
     *     steps: list<array{day: int, title: string, action: string, intelligence: string}>
     * }>
     */
    protected function campaignTemplates(): array
    {
        $creditsoftTemplates = [
            [
                'key' => 'new-lead-nurture',
                'name' => 'Starter: New Lead Nurture',
                'trigger' => 'a new website or portal lead lands in CreditSoft',
                'audience' => 'Unconverted leads who asked for help but have not booked or paid yet.',
                'goal' => 'Turn a fresh lead into a booked consultation without sounding automated.',
                'ai_guidance' => 'Reference the pain point they shared, keep the first response short, and ask for one concrete next step: booking, report access, or a callback window.',
                'steps' => [
                    ['day' => 0, 'title' => 'Send warm first response', 'action' => 'Email/SMS draft', 'intelligence' => 'Mention their name, city if known, and the credit goal they submitted. Ask for a good callback time.'],
                    ['day' => 1, 'title' => 'Create callback task', 'action' => 'Task', 'intelligence' => 'If there is no reply, call once and leave a short voicemail that points them back to the portal.'],
                    ['day' => 3, 'title' => 'Send proof and trust follow-up', 'action' => 'Email draft', 'intelligence' => 'Explain the Metro2-first review in plain language and avoid promising deletion or score outcomes.'],
                    ['day' => 7, 'title' => 'Final soft nudge', 'action' => 'Email/SMS draft', 'intelligence' => 'Give them a simple choice: book now, pause for later, or reply with the blocker.'],
                ],
            ],
            [
                'key' => 'paid-no-report',
                'name' => 'Starter: Paid But No Report',
                'trigger' => 'payment exists but no fresh 3-bureau report is attached',
                'audience' => 'Paid clients who still need SmartCredit, IdentityIQ, or another provider connected.',
                'goal' => 'Get report access quickly so disputes and Metro2 review can start.',
                'ai_guidance' => 'Be direct and helpful. Explain that payment alone does not let the office review bureau data; the report connection is the next blocker.',
                'steps' => [
                    ['day' => 0, 'title' => 'Send report access checklist', 'action' => 'Email/SMS draft', 'intelligence' => 'Link to the portal and ask for provider login or upload, but never ask them to text passwords.'],
                    ['day' => 1, 'title' => 'Staff check for saved monitoring access', 'action' => 'Task', 'intelligence' => 'Look for existing provider access before bothering the client again.'],
                    ['day' => 2, 'title' => 'Send blocker reminder', 'action' => 'Email draft', 'intelligence' => 'Frame it as protecting their timeline: no report means no accurate dispute plan.'],
                    ['day' => 5, 'title' => 'Escalate to owner review', 'action' => 'Task', 'intelligence' => 'Owner decides whether to pause billing, mark pro bono, or call personally.'],
                ],
            ],
            [
                'key' => 'new-client-onboarding',
                'name' => 'Starter: New Client Onboarding',
                'trigger' => 'a lead becomes an active client',
                'audience' => 'New paid or pro bono clients after intake.',
                'goal' => 'Collect documents, confirm address/contact data, and set expectations before the first disputes.',
                'ai_guidance' => 'Make the client feel guided. Keep instructions visual and concrete: ID, proof of address, SSN/W2 card if needed, and current mailing address.',
                'steps' => [
                    ['day' => 0, 'title' => 'Send portal welcome and document checklist', 'action' => 'Email/SMS draft', 'intelligence' => 'Tell them to photograph ID close-up, landscape, no glare, all corners visible.'],
                    ['day' => 1, 'title' => 'Verify identity and address packet', 'action' => 'Task', 'intelligence' => 'Check that uploads are readable before the first bureau letter.'],
                    ['day' => 3, 'title' => 'Confirm first review timeline', 'action' => 'Email draft', 'intelligence' => 'Explain what CreditSoft is reviewing and when they should expect the first update.'],
                    ['day' => 10, 'title' => 'First progress touch', 'action' => 'Task', 'intelligence' => 'If nothing changed yet, explain the process instead of leaving silence.'],
                ],
            ],
            [
                'key' => 'monthly-report-refresh',
                'name' => 'Starter: Monthly Report Pull',
                'trigger' => 'a monitored client is due for a new monthly report',
                'audience' => 'Active monitored clients with report data older than the current cycle.',
                'goal' => 'Keep report pulls current without looping over accounts that already ran today.',
                'ai_guidance' => 'Use the newest successful report as fallback if a provider returns no data; do not mark the client stale just because one pull failed.',
                'steps' => [
                    ['day' => 0, 'title' => 'Queue provider report pull', 'action' => 'Task', 'intelligence' => 'Check SmartCredit, IdentityIQ, and configured providers in priority order.'],
                    ['day' => 0, 'title' => 'Skip if already processed today', 'action' => 'Rule/task', 'intelligence' => 'Avoid duplicate provider logins and repeated captures.'],
                    ['day' => 1, 'title' => 'Review failed or no-data pulls', 'action' => 'Task', 'intelligence' => 'Flag missing credentials separately from provider returning no new report.'],
                    ['day' => 2, 'title' => 'Notify client only if action is needed', 'action' => 'Email/SMS draft', 'intelligence' => 'Do not bother clients when the system already has a usable recent report.'],
                ],
            ],
            [
                'key' => 'stale-lead-reactivation',
                'name' => 'Starter: Stale Lead Reactivation',
                'trigger' => 'a lead has no reply after seven days',
                'audience' => 'Older leads who never booked or never completed intake.',
                'goal' => 'Recover interested people without sounding desperate or spammy.',
                'ai_guidance' => 'Use a helpful reason to reopen the conversation, such as updated report review availability, not a fake urgency line.',
                'steps' => [
                    ['day' => 7, 'title' => 'Send helpful re-open', 'action' => 'Email draft', 'intelligence' => 'Ask if they still want help reading the report or if timing changed.'],
                    ['day' => 14, 'title' => 'Offer quick fit check', 'action' => 'SMS draft', 'intelligence' => 'Keep it under two sentences with a yes/no reply path.'],
                    ['day' => 21, 'title' => 'Archive or future-follow-up decision', 'action' => 'Task', 'intelligence' => 'Archive junk/test leads; keep real leads with a seasonal reminder.'],
                ],
            ],
            [
                'key' => 'affiliate-referral-follow-up',
                'name' => 'Starter: Affiliate Referral Follow-up',
                'trigger' => 'an affiliate sends a lead or needs a referral status update',
                'audience' => 'Affiliates, referral partners, tax preparers, mortgage brokers, and local businesses.',
                'goal' => 'Keep referral partners warm and informed without exposing private client details.',
                'ai_guidance' => 'Thank the partner, confirm receipt, share only non-sensitive status, and ask for the next warm handoff.',
                'steps' => [
                    ['day' => 0, 'title' => 'Thank affiliate for referral', 'action' => 'Email/SMS draft', 'intelligence' => 'Confirm the referral was received and avoid mentioning private bureau details.'],
                    ['day' => 2, 'title' => 'Check whether lead booked', 'action' => 'Task', 'intelligence' => 'If booked, send a generic thanks; if not, ask partner whether a warm intro helps.'],
                    ['day' => 14, 'title' => 'Send referral partner check-in', 'action' => 'Email draft', 'intelligence' => 'Ask for similar clients and remind them of the kind of client CreditSoft can help.'],
                    ['day' => 30, 'title' => 'Owner relationship touch', 'action' => 'Task', 'intelligence' => 'Owner calls high-value partners instead of leaving the relationship to automation.'],
                ],
            ],
        ];

        return array_merge($creditsoftTemplates, $this->autoFoxStyleCampaignTemplates());
    }

    /**
     * @return list<array{
     *     key: string,
     *     name: string,
     *     trigger: string,
     *     audience: string,
     *     goal: string,
     *     ai_guidance: string,
     *     steps: list<array{day: int, title: string, action: string, intelligence: string}>
     * }>
     */
    protected function autoFoxStyleCampaignTemplates(): array
    {
        return [
            [
                'key' => 'affiliate-welcome-instructions',
                'name' => 'Starter: Affiliate Welcome & Instructions',
                'trigger' => 'a new affiliate or referral partner is added',
                'audience' => 'Referral partners who need to know how to send qualified clients.',
                'goal' => 'Teach affiliates what CreditSoft accepts, how warm handoffs work, and how status updates stay private.',
                'ai_guidance' => 'Keep the tone partner-friendly. Explain the referral lane, the kind of client that is a fit, and what not to promise.',
                'steps' => [
                    ['day' => 0, 'title' => 'Send affiliate welcome packet', 'action' => 'Email draft', 'intelligence' => 'Include referral link, payout or thank-you rules if configured, and the best client profile.'],
                    ['day' => 1, 'title' => 'Create partner intro call task', 'action' => 'Task', 'intelligence' => 'Owner or staff explains expectations before the affiliate starts sending unqualified leads.'],
                    ['day' => 7, 'title' => 'Ask for first warm handoff', 'action' => 'Email/SMS draft', 'intelligence' => 'Give them a simple message they can forward to a potential client.'],
                    ['day' => 30, 'title' => 'Review referral quality', 'action' => 'Task', 'intelligence' => 'Check lead quality, conversion rate, and whether the partner needs a tighter script.'],
                ],
            ],
            [
                'key' => 'invoice-created',
                'name' => 'Starter: Billing - New Invoice Created',
                'trigger' => 'a manual or recurring invoice is created',
                'audience' => 'Clients who need a clear payment link and service context.',
                'goal' => 'Send the invoice clearly without making billing feel detached from the client journey.',
                'ai_guidance' => 'Mention what the invoice covers, payment options, and who to contact if the amount looks wrong.',
                'steps' => [
                    ['day' => 0, 'title' => 'Send invoice notice', 'action' => 'Email/SMS draft', 'intelligence' => 'Include due date, amount, and portal link; avoid sounding like a collections notice.'],
                    ['day' => 0, 'title' => 'Tag billing follow-up queue', 'action' => 'Update field/task', 'intelligence' => 'Mark billing state so the dashboard can separate new invoices from late payments.'],
                    ['day' => 2, 'title' => 'Check unpaid invoice', 'action' => 'Task', 'intelligence' => 'Only follow up if it remains unpaid and no payment arrangement exists.'],
                ],
            ],
            [
                'key' => 'invoice-due-reminder',
                'name' => 'Starter: Billing - Invoice Due Reminder',
                'trigger' => 'an invoice is due in one to five days',
                'audience' => 'Clients with an upcoming due date.',
                'goal' => 'Reduce missed payments with friendly reminders before the invoice becomes late.',
                'ai_guidance' => 'Use a helpful reminder tone and avoid threat language. Mention the exact due date if known.',
                'steps' => [
                    ['day' => -5, 'title' => 'Send early upcoming payment reminder', 'action' => 'Email draft', 'intelligence' => 'For recurring clients, mention that service continues smoothly when payment stays current.'],
                    ['day' => -2, 'title' => 'Send short SMS reminder', 'action' => 'SMS draft', 'intelligence' => 'Keep it concise and include the portal payment link.'],
                    ['day' => 0, 'title' => 'Same-day payment check', 'action' => 'Task', 'intelligence' => 'Staff checks whether this is paid, cash/pro bono, or needs a manual note.'],
                ],
            ],
            [
                'key' => 'payment-failed-recovery',
                'name' => 'Starter: Billing - Payment Failed Recovery',
                'trigger' => 'a payment fails or is short paid',
                'audience' => 'Clients whose payment did not clear or only partially covered the invoice.',
                'goal' => 'Recover the payment while preserving the relationship and preventing report work from drifting.',
                'ai_guidance' => 'Be clear about balance due. Separate true failed payments from intentional short pays, test payments, and pro bono clients.',
                'steps' => [
                    ['day' => 0, 'title' => 'Send payment issue notice', 'action' => 'Email/SMS draft', 'intelligence' => 'Mention the balance due and invite them to update payment method in the portal.'],
                    ['day' => 1, 'title' => 'Check for alternate payment proof', 'action' => 'Task', 'intelligence' => 'Look for Cash App, Zelle, cash, or owner-approved pro bono notes before marking the client behind.'],
                    ['day' => 2, 'title' => 'Pause-sensitive-work decision', 'action' => 'Task', 'intelligence' => 'Owner decides whether disputes continue, pause, or move to payment plan.'],
                    ['day' => 5, 'title' => 'Final billing follow-up', 'action' => 'Email draft', 'intelligence' => 'Give a concrete path: pay, arrange terms, or ask for help.'],
                ],
            ],
            [
                'key' => 'payment-successful-next-step',
                'name' => 'Starter: Billing - Payment Successful',
                'trigger' => 'a payment is successful',
                'audience' => 'Clients who paid and should move to the next operational step.',
                'goal' => 'Convert payment into the right next action instead of leaving the client sitting in paid-but-waiting.',
                'ai_guidance' => 'Use payment success as an operational trigger: report access, documents, current address, or dispute review.',
                'steps' => [
                    ['day' => 0, 'title' => 'Send payment received note', 'action' => 'Email/SMS draft', 'intelligence' => 'Thank them and name the next blocker if one exists.'],
                    ['day' => 0, 'title' => 'Check report and document readiness', 'action' => 'Task', 'intelligence' => 'If no fresh report or documents exist, route to the right follow-up campaign.'],
                    ['day' => 1, 'title' => 'Queue first review', 'action' => 'Task', 'intelligence' => 'If everything is ready, assign Metro2/account review.'],
                ],
            ],
            [
                'key' => 'agreement-signature-reminder',
                'name' => 'Starter: Agreement Signature Reminder',
                'trigger' => 'a client has portal access but has not signed the agreement',
                'audience' => 'New clients blocked at agreement signature.',
                'goal' => 'Get the agreement signed before document upload, report pulls, and letters continue.',
                'ai_guidance' => 'Explain that signature protects both the client and the office. Keep it procedural, not scary.',
                'steps' => [
                    ['day' => 0, 'title' => 'Send agreement link', 'action' => 'Email/SMS draft', 'intelligence' => 'Include portal link and make it clear they can sign on mobile.'],
                    ['day' => 1, 'title' => 'Reminder if not signed', 'action' => 'Email/SMS draft', 'intelligence' => 'Ask if they had trouble opening the portal before assuming avoidance.'],
                    ['day' => 3, 'title' => 'Staff call for signature blocker', 'action' => 'Task', 'intelligence' => 'Call only if the client is otherwise ready and signature is the blocker.'],
                ],
            ],
            [
                'key' => 'agreement-signed-handoff',
                'name' => 'Starter: Agreement Signed Handoff',
                'trigger' => 'a client signs the agreement',
                'audience' => 'Clients who just completed the first legal/portal step.',
                'goal' => 'Move the client from signed agreement into document and monitoring readiness.',
                'ai_guidance' => 'Celebrate the completed step briefly and route them to the next missing item.',
                'steps' => [
                    ['day' => 0, 'title' => 'Send signed agreement confirmation', 'action' => 'Email/SMS draft', 'intelligence' => 'Thank them and confirm the next step: documents or report access.'],
                    ['day' => 0, 'title' => 'Update onboarding status', 'action' => 'Update field/task', 'intelligence' => 'Move status/folder to onboarding in progress so staff can filter the queue.'],
                    ['day' => 1, 'title' => 'Review missing intake items', 'action' => 'Task', 'intelligence' => 'Check ID, proof of address, SSN/W2 card, and monitoring login.'],
                ],
            ],
            [
                'key' => 'required-documents-reminder',
                'name' => 'Starter: Required Documents Reminder',
                'trigger' => 'required documents are missing after onboarding starts',
                'audience' => 'Clients who need ID, proof of address, or SSN/W2 documents uploaded.',
                'goal' => 'Get readable documents without making the client re-upload everything later.',
                'ai_guidance' => 'Coach photo quality: phone sideways, close enough to read, no glare, full document visible.',
                'steps' => [
                    ['day' => 0, 'title' => 'Send document upload checklist', 'action' => 'Email/SMS draft', 'intelligence' => 'List exact missing document types and upload link.'],
                    ['day' => 1, 'title' => 'Send photo quality coaching', 'action' => 'SMS draft', 'intelligence' => 'Tell them to retake blurry ID photos before staff wastes time reviewing them.'],
                    ['day' => 3, 'title' => 'Staff document chase task', 'action' => 'Task', 'intelligence' => 'Call or message only for missing required documents, not already-approved ones.'],
                    ['day' => 7, 'title' => 'Owner review for stalled intake', 'action' => 'Task', 'intelligence' => 'Decide whether to pause work, mark inactive intake, or help the client directly.'],
                ],
            ],
            [
                'key' => 'required-documents-uploaded-qa',
                'name' => 'Starter: Required Documents Uploaded QA',
                'trigger' => 'all required documents are uploaded',
                'audience' => 'Clients who submitted their intake documents.',
                'goal' => 'Verify documents are usable before letters, notarization, or bureau mail happens.',
                'ai_guidance' => 'Treat upload complete as review needed, not automatically approved. Flag unreadable images quickly.',
                'steps' => [
                    ['day' => 0, 'title' => 'Create document review task', 'action' => 'Task', 'intelligence' => 'Check file size, readability, ID edges, proof-of-address date, and client name match.'],
                    ['day' => 0, 'title' => 'Send received confirmation', 'action' => 'Email/SMS draft', 'intelligence' => 'Tell client the office received uploads and will ask only if something is unreadable.'],
                    ['day' => 1, 'title' => 'Approve or request replacement', 'action' => 'Task', 'intelligence' => 'Approve usable docs; otherwise send a specific replacement request, not generic missing-docs copy.'],
                ],
            ],
            [
                'key' => 'monitoring-info-added',
                'name' => 'Starter: Monitoring Info Added',
                'trigger' => 'a client adds credit monitoring login information',
                'audience' => 'Clients whose report provider credentials are now available.',
                'goal' => 'Pull the credit report quickly and avoid repeated provider loops.',
                'ai_guidance' => 'Queue the correct provider once, avoid repeat login loops, and mark the reason if capture fails.',
                'steps' => [
                    ['day' => 0, 'title' => 'Queue report provider capture', 'action' => 'Task', 'intelligence' => 'Run SmartCredit, IdentityIQ, or configured provider based on saved account type.'],
                    ['day' => 0, 'title' => 'Skip duplicate same-day provider attempts', 'action' => 'Rule/task', 'intelligence' => 'Do not keep logging into the same account after a successful capture today.'],
                    ['day' => 1, 'title' => 'Review failed capture reason', 'action' => 'Task', 'intelligence' => 'Separate wrong credentials, MFA, provider outage, no data, and already-current report.'],
                ],
            ],
            [
                'key' => 'portal-message-received',
                'name' => 'Starter: Portal Message Received',
                'trigger' => 'a new portal message arrives from a client',
                'audience' => 'Clients who asked a question or sent information through the portal.',
                'goal' => 'Make sure client messages do not sit unanswered inside the inbox.',
                'ai_guidance' => 'Summarize the message, classify urgency, and route billing/report/legal questions differently.',
                'steps' => [
                    ['day' => 0, 'title' => 'Create inbox triage task', 'action' => 'Task', 'intelligence' => 'Tag message as billing, report access, documents, dispute result, appointment, or general.'],
                    ['day' => 0, 'title' => 'Draft response', 'action' => 'Email/portal draft', 'intelligence' => 'Answer the actual question and mention what staff will do next.'],
                    ['day' => 1, 'title' => 'Escalate unanswered message', 'action' => 'Task', 'intelligence' => 'Owner review if no staff response was logged within one business day.'],
                ],
            ],
            [
                'key' => 'sms-reply-received',
                'name' => 'Starter: SMS Reply Received',
                'trigger' => 'a client replies to an SMS',
                'audience' => 'Clients who answer automation or staff text messages.',
                'goal' => 'Turn SMS replies into handled tasks instead of loose conversation fragments.',
                'ai_guidance' => 'Classify whether the reply is consent, a question, a payment issue, STOP/unsubscribe, or a document/report blocker.',
                'steps' => [
                    ['day' => 0, 'title' => 'Classify SMS reply', 'action' => 'Task', 'intelligence' => 'Handle STOP/unsubscribe immediately and avoid sending more automated SMS.'],
                    ['day' => 0, 'title' => 'Draft short response', 'action' => 'SMS draft', 'intelligence' => 'Keep reply short and move complex items to portal or phone call.'],
                    ['day' => 1, 'title' => 'Follow unresolved SMS thread', 'action' => 'Task', 'intelligence' => 'Make sure a staff member actually closed the loop.'],
                ],
            ],
            [
                'key' => 'client-uploaded-document',
                'name' => 'Starter: Client Uploaded Document',
                'trigger' => 'a client uploads any document',
                'audience' => 'Clients whose portal upload needs sorting and review.',
                'goal' => 'Classify new documents, attach them to the right client area, and request replacements when needed.',
                'ai_guidance' => 'Detect document type when possible: driver license, proof of address, SSN/W2, credit report, bureau letter, creditor statement, or other.',
                'steps' => [
                    ['day' => 0, 'title' => 'Classify upload', 'action' => 'Task', 'intelligence' => 'Move it into the right category and avoid leaving it as staged metadata.'],
                    ['day' => 0, 'title' => 'Check image/PDF quality', 'action' => 'Task', 'intelligence' => 'Reject tiny icons, web thumbnails, blank downloads, and unreadable photos.'],
                    ['day' => 1, 'title' => 'Request replacement if needed', 'action' => 'Email/SMS draft', 'intelligence' => 'Be specific: what file is bad and how to retake it.'],
                ],
            ],
            [
                'key' => 'document-deleted-from-portal',
                'name' => 'Starter: Document Deleted From Portal',
                'trigger' => 'a client deletes a portal document',
                'audience' => 'Clients whose required document may have disappeared.',
                'goal' => 'Prevent accidental deletion from silently breaking letters or identity verification.',
                'ai_guidance' => 'Check whether the file was replaced, intentionally removed, or still required for active disputes.',
                'steps' => [
                    ['day' => 0, 'title' => 'Create deletion review task', 'action' => 'Task', 'intelligence' => 'Compare current document requirements against what was deleted.'],
                    ['day' => 0, 'title' => 'Restore or mark replacement needed', 'action' => 'Task', 'intelligence' => 'If backups/history have the file, restore or keep audit history instead of losing proof.'],
                    ['day' => 1, 'title' => 'Ask client for replacement if required', 'action' => 'Email/SMS draft', 'intelligence' => 'Only message if the deleted document is still required.'],
                ],
            ],
            [
                'key' => 'letters-due-prep',
                'name' => 'Starter: Letters Due Prep',
                'trigger' => 'letters are due or dispute expiration is approaching',
                'audience' => 'Active clients whose next dispute round needs preparation.',
                'goal' => 'Prepare letters before the due date instead of rushing the mailing queue.',
                'ai_guidance' => 'Check current report, prior disputes, bureau response dates, address, and supporting documents before drafting.',
                'steps' => [
                    ['day' => -5, 'title' => 'Review upcoming letter queue', 'action' => 'Task', 'intelligence' => 'Find clients with due letters and verify data freshness.'],
                    ['day' => -3, 'title' => 'Draft next-round letters', 'action' => 'Task', 'intelligence' => 'Use newest usable report and avoid repeating stale dispute language blindly.'],
                    ['day' => -1, 'title' => 'Owner/mail QA', 'action' => 'Task', 'intelligence' => 'Check address, bureau, creditor, attachments, and USPS/print settings.'],
                ],
            ],
            [
                'key' => 'letters-printed-or-mailed',
                'name' => 'Starter: Letters Printed or Mailed',
                'trigger' => 'letters are printed, downloaded, mailed, or sent to print/mail',
                'audience' => 'Clients with a dispute round leaving the office.',
                'goal' => 'Log the mailing event, tracking, and expected response window.',
                'ai_guidance' => 'Tell the client what happened without promising a bureau outcome. Record certified/tracking data if available.',
                'steps' => [
                    ['day' => 0, 'title' => 'Log mailing/tracking details', 'action' => 'Task', 'intelligence' => 'Attach PDF, mail class, tracking, bureau/creditor, and sent date.'],
                    ['day' => 0, 'title' => 'Send round sent update', 'action' => 'Email/SMS draft', 'intelligence' => 'Explain that letters were sent and when the next status check should happen.'],
                    ['day' => 30, 'title' => 'Schedule response check', 'action' => 'Task', 'intelligence' => 'Create a reminder to inspect bureau responses and client mail.'],
                ],
            ],
            [
                'key' => 'round-one-sent-campaign',
                'name' => 'Starter: Client Step 04 - Round 1 Sent',
                'trigger' => 'round one dispute letters are sent',
                'audience' => 'Clients entering the first active dispute waiting period.',
                'goal' => 'Keep clients informed during the first waiting period so they do not feel abandoned.',
                'ai_guidance' => 'Set expectations, ask them to upload any bureau mail they receive, and keep the message compliant.',
                'steps' => [
                    ['day' => 0, 'title' => 'Send round one sent update', 'action' => 'Email/SMS draft', 'intelligence' => 'Name the round, not the exact legal strategy, and ask client to watch mail.'],
                    ['day' => 14, 'title' => 'Mid-round check-in', 'action' => 'Email draft', 'intelligence' => 'Ask if any bureau or creditor mail arrived.'],
                    ['day' => 30, 'title' => 'Request responses and refresh report', 'action' => 'Email/SMS draft', 'intelligence' => 'Prompt upload of results and queue report refresh if due.'],
                ],
            ],
            [
                'key' => 'round-one-score-update',
                'name' => 'Starter: Client Step 05 - Round 1 Score Update',
                'trigger' => 'round one results or a new score are entered',
                'audience' => 'Clients with fresh scores or round one responses.',
                'goal' => 'Explain progress clearly and decide what to dispute next.',
                'ai_guidance' => 'Show movement without overclaiming. Compare newest report to prior usable report and call out unknowns.',
                'steps' => [
                    ['day' => 0, 'title' => 'Review score/report changes', 'action' => 'Task', 'intelligence' => 'Use newest report but fall back to prior report if the new pull has no bureau data.'],
                    ['day' => 0, 'title' => 'Draft score update', 'action' => 'Email draft', 'intelligence' => 'Explain changes in plain language and avoid making score promises.'],
                    ['day' => 1, 'title' => 'Plan next dispute action', 'action' => 'Task', 'intelligence' => 'Decide whether to escalate, repeat with new evidence, or wait for another response.'],
                ],
            ],
            [
                'key' => 'round-two-sent-campaign',
                'name' => 'Starter: Client Step 06 - Round 2 Sent',
                'trigger' => 'round two dispute letters are sent',
                'audience' => 'Clients moving beyond first-round responses.',
                'goal' => 'Frame round two as a specific escalation based on results, not a generic repeat.',
                'ai_guidance' => 'Reference bureau responses, missing verification, or updated evidence. Avoid promising deletion.',
                'steps' => [
                    ['day' => 0, 'title' => 'Send round two sent update', 'action' => 'Email/SMS draft', 'intelligence' => 'Explain that the next round responds to what came back from round one.'],
                    ['day' => 7, 'title' => 'Check for new mail or portal uploads', 'action' => 'Task', 'intelligence' => 'Make sure client uploads any new bureau or creditor letters.'],
                    ['day' => 30, 'title' => 'Queue round two results review', 'action' => 'Task', 'intelligence' => 'Compare response dates, report changes, and letter trail.'],
                ],
            ],
            [
                'key' => 'results-entered-next-action',
                'name' => 'Starter: Results Entered Next Action',
                'trigger' => 'dispute results are entered',
                'audience' => 'Clients with bureau/creditor responses recorded.',
                'goal' => 'Turn entered results into the next action rather than storing data with no follow-up.',
                'ai_guidance' => 'Classify each result: deleted, verified, updated, frivolous, no response, stall letter, or needs evidence.',
                'steps' => [
                    ['day' => 0, 'title' => 'Classify results', 'action' => 'Task', 'intelligence' => 'Separate wins, partial updates, and items needing escalation.'],
                    ['day' => 0, 'title' => 'Draft client results summary', 'action' => 'Email draft', 'intelligence' => 'Explain what changed and what happens next without legal overstatement.'],
                    ['day' => 2, 'title' => 'Queue next-round strategy', 'action' => 'Task', 'intelligence' => 'Use result codes and evidence gaps to decide the next letter set.'],
                ],
            ],
            [
                'key' => 'score-added-client-update',
                'name' => 'Starter: New Score Added Client Update',
                'trigger' => 'a new credit score is added',
                'audience' => 'Clients with updated score data.',
                'goal' => 'Send a useful score update and route meaningful changes into reporting.',
                'ai_guidance' => 'Do not celebrate raw score changes without context. Mention bureau differences and report date.',
                'steps' => [
                    ['day' => 0, 'title' => 'Compare score movement', 'action' => 'Task', 'intelligence' => 'Compare Experian, Equifax, and TransUnion to prior usable scores.'],
                    ['day' => 0, 'title' => 'Draft score note', 'action' => 'Email/SMS draft', 'intelligence' => 'Keep it readable and mention that scores can move for many reasons.'],
                    ['day' => 1, 'title' => 'Update progress snapshot', 'action' => 'Task', 'intelligence' => 'Save report date, provider, and score source for future trend analysis.'],
                ],
            ],
            [
                'key' => 'webform-submitted-routing',
                'name' => 'Starter: Webform Submitted Routing',
                'trigger' => 'a client, lead, or affiliate webform is submitted',
                'audience' => 'New submissions coming from public forms, portal forms, or affiliate forms.',
                'goal' => 'Validate the submission, reject junk, and route real people to the right lane.',
                'ai_guidance' => 'Check Turnstile, DNS/mail server validity, duplicate client, city/state, phone normalization, and whether this is lead, client, or affiliate.',
                'steps' => [
                    ['day' => 0, 'title' => 'Validate submission quality', 'action' => 'Task/rule', 'intelligence' => 'Reject fake email domains, obvious gibberish, and missing contact fields before creating noise.'],
                    ['day' => 0, 'title' => 'Route to correct owner', 'action' => 'Update field/task', 'intelligence' => 'Assign sales, owner, affiliate follow-up, or portal setup based on form type.'],
                    ['day' => 0, 'title' => 'Send appropriate confirmation', 'action' => 'Email/SMS draft', 'intelligence' => 'Use different copy for leads, active clients, and affiliates.'],
                ],
            ],
            [
                'key' => 'task-reminder-due-same-day',
                'name' => 'Starter: Task or Reminder Due Same Day',
                'trigger' => 'a task or reminder is due within the next 1 to 23 hours',
                'audience' => 'Staff and owner tasks that must not slip today.',
                'goal' => 'Surface same-day tasks before they are missed.',
                'ai_guidance' => 'Prioritize by client risk: payment, letters due, unanswered client message, missing report, or owner decision.',
                'steps' => [
                    ['day' => 0, 'title' => 'Send same-day task digest', 'action' => 'Task/notification', 'intelligence' => 'Summarize due tasks by urgency and owner.'],
                    ['day' => 0, 'title' => 'Escalate overdue client-facing task', 'action' => 'Task', 'intelligence' => 'Escalate messages, payment decisions, or letter deadlines before end of day.'],
                    ['day' => 1, 'title' => 'Review missed tasks', 'action' => 'Task', 'intelligence' => 'Find process gaps if tasks rolled over without action.'],
                ],
            ],
            [
                'key' => 'credit-card-added-from-portal',
                'name' => 'Starter: Credit Card Added From Portal',
                'trigger' => 'a client adds or updates a payment card from the portal',
                'audience' => 'Clients who supplied card information for billing.',
                'goal' => 'Confirm billing readiness and make sure the client is not still marked behind.',
                'ai_guidance' => 'Never expose card details. Check billing status, next invoice date, and whether a failed payment should be retried.',
                'steps' => [
                    ['day' => 0, 'title' => 'Confirm card update received', 'action' => 'Email/SMS draft', 'intelligence' => 'Say card was updated without showing card data.'],
                    ['day' => 0, 'title' => 'Retry eligible failed payment', 'action' => 'Task', 'intelligence' => 'Only retry if policy allows and client expects it.'],
                    ['day' => 1, 'title' => 'Clear billing blocker if paid', 'action' => 'Update field/task', 'intelligence' => 'Remove payment failed status only after payment is actually good.'],
                ],
            ],
            [
                'key' => 'status-field-automation-guardrails',
                'name' => 'Starter: Status Field Automation Guardrails',
                'trigger' => 'payment, agreement, document, monitoring, or message events should update status/folder/process fields',
                'audience' => 'Office automation rules that keep queues clean.',
                'goal' => 'Move clients through the right internal lane without hard-coding bad statuses.',
                'ai_guidance' => 'Use field updates as audit-friendly routing. Keep reason notes so staff can tell why a client moved.',
                'steps' => [
                    ['day' => 0, 'title' => 'Map event to status/folder', 'action' => 'Update field/task', 'intelligence' => 'Examples: paid -> process ready, agreement signed -> onboarding, docs uploaded -> document QA, monitoring added -> report pull.'],
                    ['day' => 0, 'title' => 'Write timeline reason', 'action' => 'Task', 'intelligence' => 'Every automated movement needs a reason in the client timeline.'],
                    ['day' => 7, 'title' => 'Audit bad lane movement', 'action' => 'Task', 'intelligence' => 'Look for clients incorrectly moved to terminated, behind, or ready-for-processing.'],
                ],
            ],
        ];
    }

    protected function clientsForCrm(): Builder
    {
        return Client::query()
            ->with(['assignedUser', 'billingProfile', 'providerAccounts'])
            ->where(function (Builder $query): void {
                $query
                    ->whereRaw("(metadata::jsonb #> '{imports,disputefox,lists,clients}') is not null")
                    ->orWhereRaw("lower(coalesce(metadata::jsonb #>> '{crm,source_kind}', metadata::jsonb #>> '{source_kind}', '')) = 'client'")
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->whereRaw("(metadata::jsonb #> '{imports,disputefox}') is null")
                            ->whereIn('status', ['active', 'active_review', 'monitoring', 'intake']);
                    });
            })
            ->whereRaw("lower(coalesce(cuid, '')) not like 'c_demo%'")
            ->whereRaw('not ('.$this->endedClientSql().')')
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    protected function leadsForCrm(): Builder
    {
        return Client::query()
            ->with(['assignedUser', 'billingProfile', 'providerAccounts'])
            ->where(function (Builder $query): void {
                $query
                    ->where('status', 'lead')
                    ->orWhereRaw("lower(coalesce(metadata::jsonb #>> '{crm,source_kind}', metadata::jsonb #>> '{source_kind}', '')) = 'lead'")
                    ->orWhereRaw("(metadata::jsonb #> '{imports,disputefox,lists,leads}') is not null");
            })
            ->whereRaw("lower(coalesce(cuid, '')) not like 'c_demo%'")
            ->whereRaw('not ('.$this->endedClientSql().')')
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    protected function terminatedForCrm(): Builder
    {
        return Client::query()
            ->with(['assignedUser', 'billingProfile', 'providerAccounts'])
            ->whereRaw('('.$this->terminatedRecoverySql().')')
            ->whereRaw("lower(coalesce(cuid, '')) not like 'c_demo%'")
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    protected function firedForCrm(): Builder
    {
        return Client::query()
            ->with(['assignedUser', 'billingProfile', 'providerAccounts'])
            ->whereRaw('('.$this->firedSql().')')
            ->whereRaw("lower(coalesce(cuid, '')) not like 'c_demo%'")
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    protected function canceledForCrm(): Builder
    {
        return Client::query()
            ->with(['assignedUser', 'billingProfile', 'providerAccounts'])
            ->whereRaw('('.$this->canceledSql().')')
            ->whereRaw('not ('.$this->firedSql().')')
            ->whereRaw("lower(coalesce(cuid, '')) not like 'c_demo%'")
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    protected function graduatedForCrm(): Builder
    {
        return Client::query()
            ->with(['assignedUser', 'billingProfile', 'providerAccounts'])
            ->whereRaw('('.$this->graduatedSql().')')
            ->whereRaw('not ('.$this->firedSql().')')
            ->whereRaw('not ('.$this->canceledSql().')')
            ->whereRaw("lower(coalesce(cuid, '')) not like 'c_demo%'")
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    protected function endedClientSql(): string
    {
        return implode(' or ', [
            $this->terminatedRecoverySql(),
            $this->firedSql(),
            $this->canceledSql(),
            $this->graduatedSql(),
        ]);
    }

    protected function terminatedRecoverySql(): string
    {
        $leadWithHistorySql = '(('.$this->leadSql().') and ('
            .implode(' or ', [
                $this->providerLoginExistsSql(),
                $this->billingSignalExistsSql(),
                $this->staleImportedLeadSql(),
            ])
            .'))';
        $notFinalSql = implode(' and ', [
            'not ('.$this->firedSql().')',
            'not ('.$this->canceledSql().')',
            'not ('.$this->graduatedSql().')',
        ]);

        return '('
            .implode(' or ', [
                "lower(coalesce(status, '')) = 'terminated'",
                "lower(coalesce(metadata::jsonb #>> '{ended_reason}', '')) in ('nonpayment', 'unresponsive', 'compliance_risk', 'abusive_behavior', 'other', 'terminated', 'closed', 'archived')",
                "lower(coalesce(metadata::jsonb #>> '{engagement_outcome}', '')) in ('terminated', 'closed', 'archived')",
                "lower(coalesce(metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Status}', '')) in ('closed', 'archived', 'terminated')",
                "lower(coalesce(metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Client Status}', '')) in ('closed', 'archived', 'terminated')",
                "lower(coalesce(metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Stage in Processs}', '')) similar to '%(closed|archived|terminated)%'",
                "lower(coalesce(metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Stage in Process}', '')) similar to '%(closed|archived|terminated)%'",
                $leadWithHistorySql,
                $this->inactiveServiceSql(),
                $this->legacyImportedProfileWithoutActiveClientSql(),
            ])
            .") and {$notFinalSql}";
    }

    protected function leadSql(): string
    {
        return implode(' or ', [
            "lower(coalesce(status, '')) = 'lead'",
            "coalesce(metadata::jsonb #>> '{crm,source_kind}', '') = 'lead'",
            "coalesce(metadata::jsonb #>> '{source_kind}', '') = 'lead'",
            "(metadata::jsonb #> '{imports,disputefox,lists,leads}') is not null",
            "lower(coalesce(metadata::jsonb #>> '{imports,disputefox,regular_companion_sync,source_page_url}', '')) like '%type=leads%'",
            "(
                (metadata::jsonb #> '{imports,disputefox,lists,clients}') is null
                and (metadata::jsonb #> '{imports,disputefox,lists,leads}') is null
                and metadata::text ilike '%Lead Status%'
            )",
        ]);
    }

    protected function firedSql(): string
    {
        return implode(' or ', [
            "lower(coalesce(status, '')) = 'fired'",
            "lower(coalesce(metadata::jsonb #>> '{engagement_outcome}', '')) = 'fired'",
            "lower(coalesce(metadata::jsonb #>> '{ended_reason}', '')) = 'fired'",
            "lower(coalesce(metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Status}', '')) = 'fired'",
            "lower(coalesce(metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Client Status}', '')) = 'fired'",
            "lower(coalesce(metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Stage in Processs}', '')) similar to '%(fired)%'",
            "lower(coalesce(metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Stage in Process}', '')) similar to '%(fired)%'",
            "(metadata::jsonb #> '{fired_at}') is not null",
        ]);
    }

    protected function canceledSql(): string
    {
        return implode(' or ', [
            "lower(coalesce(status, '')) in ('canceled', 'cancelled')",
            "lower(coalesce(metadata::jsonb #>> '{ended_reason}', '')) in ('requested_cancellation', 'canceled', 'cancelled')",
            "lower(coalesce(metadata::jsonb #>> '{engagement_outcome}', '')) in ('requested_cancellation', 'canceled', 'cancelled')",
            "lower(coalesce(metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Status}', '')) in ('canceled', 'cancelled')",
            "lower(coalesce(metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Client Status}', '')) in ('canceled', 'cancelled')",
            "lower(coalesce(metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Stage in Processs}', '')) similar to '%(canceled|cancelled)%'",
            "lower(coalesce(metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Stage in Process}', '')) similar to '%(canceled|cancelled)%'",
        ]);
    }

    protected function graduatedSql(): string
    {
        return implode(' or ', [
            "lower(coalesce(status, '')) in ('resolved', 'graduated', 'finished')",
            "lower(coalesce(metadata::jsonb #>> '{ended_reason}', '')) in ('goals_met', 'no_longer_needed_help', 'graduated', 'finished')",
            "lower(coalesce(metadata::jsonb #>> '{engagement_outcome}', '')) in ('goals_met', 'no_longer_needed_help', 'graduated', 'finished')",
            "lower(coalesce(metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Status}', '')) in ('resolved', 'graduated', 'finished')",
            "lower(coalesce(metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Client Status}', '')) in ('resolved', 'graduated', 'finished')",
            "lower(coalesce(metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Stage in Processs}', '')) similar to '%(resolved|graduated|finished)%'",
            "lower(coalesce(metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Stage in Process}', '')) similar to '%(resolved|graduated|finished)%'",
        ]);
    }

    protected function providerLoginExistsSql(): string
    {
        return "exists (
            select 1
            from client_provider_accounts
            where client_provider_accounts.client_id = clients.id
              and (
                coalesce(client_provider_accounts.login_email, '') <> ''
                or coalesce(client_provider_accounts.login_username, '') <> ''
                or coalesce(client_provider_accounts.login_password, '') <> ''
              )
        )";
    }

    protected function billingSignalExistsSql(): string
    {
        return "(
            metadata::text ilike '%ActivePay%'
            or metadata::text ilike '%FailedPay%'
            or exists (
                select 1
                from client_payments
                where client_payments.client_id = clients.id
            )
            or exists (
                select 1
                from client_billing_profiles
                where client_billing_profiles.client_id = clients.id
                  and (
                    coalesce(client_billing_profiles.status, '') <> ''
                    or client_billing_profiles.last_paid_at is not null
                    or client_billing_profiles.next_due_at is not null
                  )
            )
        )";
    }

    protected function staleImportedLeadSql(): string
    {
        return "(
            (
                coalesce(metadata::jsonb #>> '{imports,disputefox,lists,leads,raw_row,Added Date}', '') ~ '^\\d{1,2}/\\d{1,2}/\\d{4}$'
                and to_date(metadata::jsonb #>> '{imports,disputefox,lists,leads,raw_row,Added Date}', 'MM/DD/YYYY') < current_date - interval '180 days'
            )
            or (
                coalesce(metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Started}', '') ~ '^\\d{1,2}/\\d{1,2}/\\d{4}$'
                and to_date(metadata::jsonb #>> '{imports,disputefox,lists,clients,raw_row,Started}', 'MM/DD/YYYY') < current_date - interval '180 days'
            )
        )";
    }

    protected function inactiveServiceSql(): string
    {
        return "(
            exists (
                select 1
                from client_provider_accounts
                where client_provider_accounts.client_id = clients.id
                  and (
                    lower(coalesce(client_provider_accounts.status, '')) in ('needs_client_payment', 'needs_reactivation')
                    or lower(coalesce(client_provider_accounts.notes, '')) like '%reactivation%'
                    or client_provider_accounts.metadata::text ilike '%needs_client_payment%'
                    or client_provider_accounts.metadata::text ilike '%smartcredit_reactivation%'
                  )
            )
            or exists (
                select 1
                from client_billing_profiles
                where client_billing_profiles.client_id = clients.id
                  and client_billing_profiles.last_paid_at < current_date - interval '90 days'
                  and (
                    client_billing_profiles.next_due_at is null
                    or client_billing_profiles.next_due_at < current_date - interval '30 days'
                  )
            )
            or (
                exists (
                    select 1
                    from client_payments
                    where client_payments.client_id = clients.id
                      and client_payments.paid_at is not null
                )
                and not exists (
                    select 1
                    from client_payments recent_client_payments
                    where recent_client_payments.client_id = clients.id
                      and recent_client_payments.paid_at >= current_date - interval '90 days'
                )
                and not exists (
                    select 1
                    from client_billing_profiles current_client_billing_profiles
                    where current_client_billing_profiles.client_id = clients.id
                      and current_client_billing_profiles.next_due_at >= current_date
                )
            )
        )";
    }

    protected function legacyImportedProfileWithoutActiveClientSql(): string
    {
        return "(
            (metadata::jsonb #> '{imports,disputefox,regular_companion_sync}') is not null
            and (metadata::jsonb #> '{imports,disputefox,lists,clients}') is null
            and (metadata::jsonb #> '{imports,disputefox,lists,leads}') is null
            and lower(coalesce(metadata::jsonb #>> '{crm,source_kind}', metadata::jsonb #>> '{source_kind}', '')) <> 'client'
        )";
    }

    protected function syncClientPerson(
        Connection $connection,
        string $schema,
        Client $client,
        int $position,
        string $kind = 'client',
        string $label = 'Active client',
    ): string {
        $email = Str::lower(trim((string) $client->email));
        $id = $this->existingCreditsoftId($connection, $schema, 'person', $client->cuid)
            ?: ($email !== '' ? $this->existingByColumn($connection, $schema, 'person', 'emailsPrimaryEmail', $email) : null)
            ?: $this->uuidFor('person:'.$client->cuid);
        $context = $this->contextForClient($client, $kind);

        $this->upsertRow($connection, $schema, 'person', [
            'id' => $id,
            'nameFirstName' => $client->first_name,
            'nameLastName' => $client->last_name,
            'emailsPrimaryEmail' => $email !== '' ? $email : null,
            'emailsAdditionalEmails' => $this->json([]),
            'jobTitle' => $this->clientSummary($client, $label),
            'phonesPrimaryPhoneNumber' => trim((string) $client->phone) ?: null,
            'phonesAdditionalPhones' => $this->json([]),
            'city' => trim((string) $client->city) ?: null,
            'position' => $position,
            'createdByName' => 'CreditSoft',
            'createdByContext' => $this->json($context),
            'updatedByName' => 'CreditSoft',
            'updatedByContext' => $this->json($context),
            'deletedAt' => null,
        ], [
            'nameFirstName',
            'nameLastName',
            'emailsPrimaryEmail',
            'emailsAdditionalEmails',
            'jobTitle',
            'phonesPrimaryPhoneNumber',
            'phonesAdditionalPhones',
            'city',
            'position',
            'updatedByName',
            'updatedByContext',
            'deletedAt',
        ]);

        return $id;
    }

    protected function syncLeadOpportunity(Connection $connection, string $schema, Client $lead, int $position): string
    {
        return $this->syncOpportunity($connection, $schema, $lead, 'lead', 'NEW', $position);
    }

    protected function syncTerminatedOpportunity(Connection $connection, string $schema, Client $client, int $position): string
    {
        return $this->syncOpportunity($connection, $schema, $client, 'terminated', 'SCREENING', $position);
    }

    protected function syncOpportunity(Connection $connection, string $schema, Client $client, string $kind, string $stage, int $position): string
    {
        $id = $this->existingCreditsoftId($connection, $schema, 'opportunity', $client->cuid)
            ?: $this->uuidFor($kind.':'.$client->cuid);
        $context = $this->contextForClient($client, $kind);

        $this->upsertRow($connection, $schema, 'opportunity', [
            'id' => $id,
            'name' => trim($client->display_name) !== '' ? $client->display_name : 'CreditSoft '.$kind,
            'amountAmountMicros' => $client->billingProfile ? (int) round(((float) $client->billingProfile->amount) * 1000000) : null,
            'amountCurrencyCode' => $client->billingProfile?->currency ?: 'USD',
            'stage' => $stage,
            'position' => $position,
            'createdByName' => 'CreditSoft',
            'createdByContext' => $this->json($context),
            'updatedByName' => 'CreditSoft',
            'updatedByContext' => $this->json($context),
            'deletedAt' => null,
        ], [
            'name',
            'amountAmountMicros',
            'amountCurrencyCode',
            'stage',
            'position',
            'updatedByName',
            'updatedByContext',
            'deletedAt',
        ]);

        return $id;
    }

    /**
     * @param  array<string, mixed>  $affiliate
     */
    protected function syncAffiliateCompany(Connection $connection, string $schema, array $affiliate, int $position): string
    {
        $key = trim((string) ($affiliate['key'] ?? ''));
        $name = trim((string) ($affiliate['company'] ?? '')) ?: trim(implode(' ', array_filter([
            $affiliate['first_name'] ?? null,
            $affiliate['last_name'] ?? null,
        ]))) ?: trim((string) ($affiliate['label'] ?? 'CreditSoft affiliate'));
        $email = Str::lower(trim((string) ($affiliate['email'] ?? '')));
        $domain = $email !== '' && str_contains($email, '@') ? Str::after($email, '@') : '';
        $id = $this->existingAffiliateId($connection, $schema, $key)
            ?: ($domain !== '' ? $this->existingByColumn($connection, $schema, 'company', 'domainNamePrimaryLinkUrl', $domain) : null)
            ?: $this->uuidFor('affiliate:'.($key !== '' ? $key : Str::lower($name)));
        $context = [
            'source' => self::SOURCE,
            'creditsoft' => [
                'kind' => 'affiliate',
                'affiliate_key' => $key !== '' ? $key : null,
                'synced_at' => now()->toIso8601String(),
            ],
        ];

        $this->upsertRow($connection, $schema, 'company', [
            'id' => $id,
            'name' => $name,
            'domainNamePrimaryLinkLabel' => $domain !== '' ? $domain : null,
            'domainNamePrimaryLinkUrl' => $domain !== '' ? $domain : null,
            'domainNameSecondaryLinks' => $this->json([]),
            'idealCustomerProfile' => false,
            'position' => $position,
            'createdByName' => 'CreditSoft',
            'createdByContext' => $this->json($context),
            'updatedByName' => 'CreditSoft',
            'updatedByContext' => $this->json($context),
            'deletedAt' => null,
        ], [
            'name',
            'domainNamePrimaryLinkLabel',
            'domainNamePrimaryLinkUrl',
            'domainNameSecondaryLinks',
            'idealCustomerProfile',
            'position',
            'updatedByName',
            'updatedByContext',
            'deletedAt',
        ]);

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    protected function contextForClient(Client $client, string $kind): array
    {
        return [
            'source' => self::SOURCE,
            'creditsoft' => [
                'kind' => $kind,
                'client_id' => $client->getKey(),
                'cuid' => $client->cuid,
                'status' => $client->status,
                'assigned_user' => $client->assignedUser?->name,
                'provider_count' => $client->providerAccounts->count(),
                'billing_status' => $client->billingProfile?->status,
                'synced_at' => now()->toIso8601String(),
            ],
        ];
    }

    protected function clientSummary(Client $client, string $label = 'Active client'): string
    {
        $parts = [
            'CreditSoft',
            $label,
            Str::of((string) $client->status)->replace('_', ' ')->headline()->value(),
        ];

        if ($client->assignedUser?->name) {
            $parts[] = 'Owner: '.$client->assignedUser->name;
        }

        return implode(' · ', array_filter($parts));
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $updateColumns
     */
    protected function upsertRow(Connection $connection, string $schema, string $table, array $values, array $updateColumns): void
    {
        $columns = array_keys($values);
        $columnSql = collect($columns)
            ->map(fn (string $column): string => $this->quoteIdentifier($column))
            ->implode(', ');
        $placeholders = collect($columns)
            ->map(fn (string $column): string => $this->placeholderFor($column))
            ->implode(', ');
        $updates = collect($updateColumns)
            ->map(fn (string $column): string => $this->quoteIdentifier($column).' = excluded.'.$this->quoteIdentifier($column))
            ->push('"updatedAt" = now()')
            ->implode(', ');
        $sql = sprintf(
            'insert into %s (%s) values (%s) on conflict ("id") do update set %s',
            $this->qualifiedTable($schema, $table),
            $columnSql,
            $placeholders,
            $updates,
        );

        $connection->statement($sql, array_values($values));
    }

    protected function placeholderFor(string $column): string
    {
        return in_array($column, [
            'emailsAdditionalEmails',
            'phonesAdditionalPhones',
            'domainNameSecondaryLinks',
            'createdByContext',
            'updatedByContext',
            'properties',
        ], true) ? '?::jsonb' : '?';
    }

    protected function hideUnmanagedSeedRows(Connection $connection, string $schema, string $table): int
    {
        return $connection->update(
            'update '.$this->qualifiedTable($schema, $table).'
             set "deletedAt" = now(),
                 "updatedAt" = now(),
                 "updatedByName" = ?,
                 "updatedByContext" = ?::jsonb
             where "deletedAt" is null
               and coalesce("createdByName", \'\') = ?
               and coalesce("createdByContext"::text, \'\') in (\'\', \'null\')
               and coalesce("updatedByContext"::text, \'\') in (\'\', \'null\')',
            [
                'CreditSoft',
                $this->json([
                    'source' => self::SOURCE,
                    'action' => 'hide_unmanaged_seed_row',
                    'synced_at' => now()->toIso8601String(),
                ]),
                'System',
            ],
        );
    }

    /**
     * @param  list<string>  $activeIds
     */
    protected function hideStaleCreditsoftRows(Connection $connection, string $schema, string $table, string $kind, array $activeIds): int
    {
        $params = [
            'CreditSoft',
            $this->json([
                'source' => self::SOURCE,
                'action' => 'hide_stale_creditsoft_row',
                'kind' => $kind,
                'synced_at' => now()->toIso8601String(),
            ]),
            self::SOURCE,
            $kind,
        ];
        $idFilter = '';

        if ($activeIds !== []) {
            $idFilter = ' and "id" not in ('.implode(', ', array_fill(0, count($activeIds), '?')).')';
            array_push($params, ...$activeIds);
        }

        return $connection->update(
            'update '.$this->qualifiedTable($schema, $table).'
             set "deletedAt" = now(),
                 "updatedAt" = now(),
                 "updatedByName" = ?,
                 "updatedByContext" = ?::jsonb
             where "deletedAt" is null
               and coalesce("createdByContext" #>> \'{source}\', "updatedByContext" #>> \'{source}\', \'\') = ?
               and coalesce("createdByContext" #>> \'{creditsoft,kind}\', "updatedByContext" #>> \'{creditsoft,kind}\', \'\') = ?'
            .$idFilter,
            $params,
        );
    }

    protected function existingCreditsoftId(Connection $connection, string $schema, string $table, string $cuid): ?string
    {
        return $this->scalar(
            $connection,
            'select id::text from '.$this->qualifiedTable($schema, $table).'
             where "createdByContext" #>> \'{creditsoft,cuid}\' = ?
                or "updatedByContext" #>> \'{creditsoft,cuid}\' = ?
             limit 1',
            [$cuid, $cuid],
        );
    }

    protected function existingAffiliateId(Connection $connection, string $schema, string $key): ?string
    {
        if ($key === '') {
            return null;
        }

        return $this->scalar(
            $connection,
            'select id::text from '.$this->qualifiedTable($schema, 'company').'
             where "createdByContext" #>> \'{creditsoft,affiliate_key}\' = ?
                or "updatedByContext" #>> \'{creditsoft,affiliate_key}\' = ?
             limit 1',
            [$key, $key],
        );
    }

    protected function existingByColumn(Connection $connection, string $schema, string $table, string $column, string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        return $this->scalar(
            $connection,
            'select id::text from '.$this->qualifiedTable($schema, $table).'
             where lower(coalesce('.$this->quoteIdentifier($column).", '')) = lower(?)
             limit 1",
            [$value],
        );
    }

    /**
     * @param  list<mixed>  $bindings
     */
    protected function scalar(Connection $connection, string $sql, array $bindings = []): ?string
    {
        $row = $connection->selectOne($sql, $bindings);

        if (! $row) {
            return null;
        }

        $values = get_object_vars($row);
        $value = reset($values);

        return is_scalar($value) && trim((string) $value) !== '' ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>|array<int, mixed>  $value
     */
    protected function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    protected function uuidFor(string $key): string
    {
        $namespace = str_replace('-', '', self::NAMESPACE_UUID);
        $hash = sha1(hex2bin($namespace).$key);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            '5'.substr($hash, 13, 3),
            dechex((hexdec($hash[16]) & 0x3) | 0x8).substr($hash, 17, 3),
            substr($hash, 20, 12),
        );
    }

    protected function withCrmConnection(callable $callback): mixed
    {
        $name = 'creditsoft_crm_sidecar_bridge';
        $database = config('creditsoft.integrations.crm.database', []);

        config([
            "database.connections.{$name}" => [
                'driver' => 'pgsql',
                'host' => (string) ($database['host'] ?? '127.0.0.1'),
                'port' => (string) ($database['port'] ?? '5432'),
                'database' => (string) ($database['database'] ?? 'crm'),
                'username' => (string) ($database['username'] ?? 'crm'),
                'password' => (string) ($database['password'] ?? ''),
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => 'core',
                'sslmode' => 'prefer',
            ],
        ]);

        DB::purge($name);

        try {
            return $callback(DB::connection($name));
        } catch (\Throwable $exception) {
            throw new RuntimeException('CreditSoft could not sync the CRM sidecar roster. '.$exception->getMessage(), previous: $exception);
        } finally {
            DB::disconnect($name);
        }
    }

    protected function qualifiedTable(string $schema, string $table): string
    {
        return $this->quoteIdentifier($schema).'.'.$this->quoteIdentifier($table);
    }

    protected function qualifiedType(string $schema, string $type): string
    {
        return $this->quoteIdentifier($schema).'.'.$this->quoteIdentifier($type);
    }

    protected function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
