<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
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
                'seed_rows_hidden' => 0,
                'stale_rows_hidden' => 0,
            ];

            if ($dryRun) {
                return $summary;
            }

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

            return $summary;
        });
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

    protected function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
