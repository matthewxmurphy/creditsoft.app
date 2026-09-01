<?php

namespace App\Http\Controllers;

use App\Models\AuditEntry;
use App\Models\BrowserCapture;
use App\Models\Client;
use App\Models\ClientBillingProfile;
use App\Models\ClientPayment;
use App\Models\ClientProviderAccount;
use App\Models\MigrationOperatorCapture;
use App\Models\SopTemplate;
use App\Models\User;
use App\Services\AuditRetentionPolicy;
use App\Services\AuditTrail;
use App\Services\BrowserCaptureCleanupService;
use App\Services\BrowserCompanionBundle;
use App\Services\ClientAssignmentService;
use App\Services\ClientHealthSignalService;
use App\Services\ClientProfileSnapshotService;
use App\Services\ClientScoreTimeline;
use App\Services\CreditReportComparisonService;
use App\Services\CreditsoftAiRegistry;
use App\Services\DisputePlanPresenter;
use App\Services\LicenseStateService;
use App\Services\OfficeGrowthRuntime;
use App\Services\SmartCreditCaptureParser;
use App\Support\ClientName;
use App\Support\MailingAddress;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ClientController extends Controller
{
    /**
     * @return list<array{key:string,label:string,description:string,outcome:string}>
     */
    protected function relationshipReasonOptions(): array
    {
        return [
            [
                'key' => 'nonpayment',
                'label' => 'Nonpayment',
                'description' => 'The client stopped paying or repeatedly failed billing.',
                'outcome' => 'terminated',
            ],
            [
                'key' => 'fired',
                'label' => 'Fired',
                'description' => 'The office fired the client and kept the dossier for records.',
                'outcome' => 'terminated',
            ],
            [
                'key' => 'unresponsive',
                'label' => 'Unresponsive',
                'description' => 'The client stopped responding and the file could not keep moving.',
                'outcome' => 'terminated',
            ],
            [
                'key' => 'compliance_risk',
                'label' => 'Compliance risk',
                'description' => 'The relationship had to end because the file or behavior created compliance risk.',
                'outcome' => 'terminated',
            ],
            [
                'key' => 'abusive_behavior',
                'label' => 'Abusive behavior',
                'description' => 'The office chose to end the relationship because of the client’s behavior.',
                'outcome' => 'terminated',
            ],
            [
                'key' => 'requested_cancellation',
                'label' => 'Client requested cancellation',
                'description' => 'The client asked to stop service before the case was finished.',
                'outcome' => 'canceled',
            ],
            [
                'key' => 'no_longer_needed_help',
                'label' => 'No longer needed help',
                'description' => 'The client was finished and no longer needed active service.',
                'outcome' => 'graduated',
            ],
            [
                'key' => 'goals_met',
                'label' => 'Goals met',
                'description' => 'The office completed the work and the relationship ended cleanly.',
                'outcome' => 'graduated',
            ],
            [
                'key' => 'other',
                'label' => 'Other',
                'description' => 'Use notes to explain a reason that does not fit the main lanes.',
                'outcome' => 'terminated',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    protected function normalizeClientNameFields(array $fields): array
    {
        $fields = ClientName::normalizeFields($fields);

        if (array_key_exists('phone', $fields)) {
            $fields['phone'] = PhoneNumber::normalize($fields['phone']);
        }

        return $fields;
    }

    public function index(
        Request $request,
        ClientAssignmentService $assignments,
        OfficeGrowthRuntime $growth,
        ClientHealthSignalService $clientHealth,
    ): Response {
        $view = $this->rosterView((string) $request->query('view', 'clients'));
        $search = Str::of((string) $request->query('search', $request->query('q', '')))
            ->squish()
            ->limit(120, '')
            ->value();
        $perPage = min(100, max(10, (int) $request->integer('per_page', 25)));
        $sort = $this->rosterSort((string) $request->query('sort', 'newest'));
        $direction = $this->rosterSortDirection((string) $request->query('direction', $sort === 'newest' ? 'desc' : 'asc'));
        $companyProfile = $growth->companyProfile();
        $crmFields = collect([...$growth->crmFields('lead'), ...$growth->crmFields('client')])
            ->unique('key')
            ->values()
            ->all();
        $disputeFoxCaptureIndex = $this->disputeFoxCaptureSourceIndex();

        $clientQuery = Client::query()
            ->select('clients.*')
            ->addSelect([
                'assigned_user_sort_name' => User::query()
                    ->select('name')
                    ->whereColumn('users.id', 'clients.assigned_to')
                    ->limit(1),
            ])
            ->with([
                'assignedUser',
                'billingProfile',
                'documents:id,client_id,title,file_size,metadata',
                'payments',
                'providerAccounts',
                'reportingCycles' => fn ($query) => $query->latest('started_at')->limit(2),
            ])
            ->withCount([
                'documents as document_record_count',
                'documents as document_file_count' => fn (Builder $query) => $query->where('file_size', '>', 0),
                'documents as metadata_only_document_count' => fn (Builder $query) => $query
                    ->where(fn (Builder $scope) => $scope
                        ->whereNull('file_size')
                        ->orWhere('file_size', '<=', 0)),
                'providerAccounts as provider_account_count',
                'reportingCycles as reporting_cycle_count',
            ])
            ->withMax('reportingCycles as latest_reporting_cycle_started_at', 'started_at')
            ->withSum('documents as document_file_size_bytes', 'file_size');
        $this->applyRosterViewFilter($clientQuery, $view, $disputeFoxCaptureIndex);
        $this->applyRosterSearch($clientQuery, $search);
        $this->applyRosterSort($clientQuery, $sort, $direction);

        $clients = $clientQuery
            ->paginate($perPage)
            ->withQueryString();

        $clients->setCollection($clients->getCollection()->map(function (Client $client) use ($disputeFoxCaptureIndex, $clientHealth): array {
            $importAudit = $this->clientImportAudit($client, $disputeFoxCaptureIndex);
            $healthSignal = $clientHealth->signal($client);
            $documentBytes = max((int) ($client->document_file_size_bytes ?? 0), 0);
            $documentCount = max((int) ($client->document_record_count ?? 0), 0);
            $fileCount = max((int) ($client->document_file_count ?? 0), 0);
            $metadataOnlyCount = max((int) ($client->metadata_only_document_count ?? 0), 0);
            $requiredDocumentStatus = $this->requiredDocumentStatus($client);

            return [
                'id' => $client->getKey(),
                'cuid' => $client->cuid,
                'display_name' => $client->display_name,
                'status' => $client->status,
                'current_score' => $client->current_score,
                'assigned_user' => $client->assignedUser?->name,
                'cycle_count' => max((int) ($client->reporting_cycle_count ?? $client->reportingCycles->count()), 0),
                'latest_cycle' => $client->reportingCycles->first()?->cycle_label,
                'client_health' => $healthSignal,
                'billing_signal' => $healthSignal,
                'import_audit' => $importAudit,
                'source_kind' => $importAudit['source_kind'],
                'document_storage' => [
                    'document_count' => $documentCount,
                    'file_count' => $fileCount,
                    'metadata_only_count' => $metadataOnlyCount,
                    'file_size_bytes' => $documentBytes,
                    'file_size_label' => $this->humanBytes($documentBytes),
                    'has_files' => $fileCount > 0 && $documentBytes > 0,
                    'missing_required' => $requiredDocumentStatus['missing'],
                    'missing_required_count' => count($requiredDocumentStatus['missing']),
                    'present_required' => $requiredDocumentStatus['present'],
                ],
            ];
        }));
        $clientCount = $this->rosterCount('clients', $disputeFoxCaptureIndex);
        $leadCount = $this->rosterCount('leads', $disputeFoxCaptureIndex);
        $terminatedCount = $this->rosterCount('terminated', $disputeFoxCaptureIndex);
        $firedCount = $this->rosterCount('fired', $disputeFoxCaptureIndex);
        $canceledCount = $this->rosterCount('canceled', $disputeFoxCaptureIndex);
        $graduatedCount = $this->rosterCount('graduated', $disputeFoxCaptureIndex);
        $totalCount = $this->rosterCount('all', $disputeFoxCaptureIndex);
        $pulseClientCount = (clone $this->rosterBaseQuery())
            ->where(function (Builder $query) use ($disputeFoxCaptureIndex): void {
                $this->applyRosterViewFilter($query, 'clients', $disputeFoxCaptureIndex);
            })
            ->whereRaw("(metadata::jsonb #> '{imports,disputefox}') is not null")
            ->count();
        $stagedPulseLeads = $this->pulseCaptureRowCount('pulse_leads');
        $stagedDisputeFoxAffiliates = $this->disputeFoxAffiliateCount();
        $displayLeadCount = $leadCount;

        return Inertia::render('clients/Index', [
            'clients' => $clients->items(),
            'filters' => [
                'view' => $view,
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'pagination' => [
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
                'from' => $clients->firstItem(),
                'to' => $clients->lastItem(),
                'has_more_pages' => $clients->hasMorePages(),
            ],
            'importSummary' => [
                'total' => $totalCount,
                'clients' => $clientCount,
                'pulse_clients' => $pulseClientCount,
                'leads' => $leadCount,
                'listed_leads' => $stagedPulseLeads,
                'display_leads' => $displayLeadCount,
                'terminated' => $terminatedCount,
                'fired' => $firedCount,
                'canceled' => $canceledCount,
                'graduated' => $graduatedCount,
                'pulse_leads' => $stagedPulseLeads,
                'pulse_affiliates' => $stagedDisputeFoxAffiliates,
                'affiliates' => $stagedDisputeFoxAffiliates,
                'pulse_imported' => (clone $this->rosterBaseQuery())
                    ->whereRaw("(metadata::jsonb #> '{imports,disputefox}') is not null")
                    ->count(),
                'pulse_report_pulled' => $this->pulseReportPulledCount(),
                'provider_accounts' => (int) DB::table('client_provider_accounts')->count(),
                'provider_ready' => $this->providerReadyCount(),
                'needs_provider_credentials' => $this->needsProviderCredentialsCount(),
                'document_storage' => $this->documentStorageSummary(),
            ],
            'staff' => $assignments->staffOptions(),
            'assignmentModes' => $assignments->createModes(),
            'crmFields' => $crmFields,
            'affiliates' => $growth->affiliates(),
            'companyProfile' => [
                'allow_round_robin' => (bool) ($companyProfile['allow_round_robin'] ?? true),
                'assigned_only_default' => (bool) ($companyProfile['assigned_only_default'] ?? false),
            ],
        ]);
    }

    protected function rosterView(string $view): string
    {
        return in_array($view, ['clients', 'leads', 'terminated', 'fired', 'canceled', 'graduated', 'all'], true) ? $view : 'clients';
    }

    protected function rosterSort(string $sort): string
    {
        return in_array($sort, ['newest', 'person', 'status', 'provider', 'files', 'score', 'owner', 'cycle'], true)
            ? $sort
            : 'newest';
    }

    protected function rosterSortDirection(string $direction): string
    {
        return strtolower($direction) === 'desc' ? 'desc' : 'asc';
    }

    protected function applyRosterSort(Builder $query, string $sort, string $direction): void
    {
        $direction = $this->rosterSortDirection($direction);
        $providerAccountCountSql = '(select count(*) from client_provider_accounts where client_provider_accounts.client_id = clients.id)';
        $documentFileSizeSql = '(select coalesce(sum(file_size), 0) from client_documents where client_documents.client_id = clients.id)';
        $documentFileCountSql = '(select count(*) from client_documents where client_documents.client_id = clients.id and file_size > 0)';
        $documentRecordCountSql = '(select count(*) from client_documents where client_documents.client_id = clients.id)';
        $assignedUserNameSql = '(select name from users where users.id = clients.assigned_to limit 1)';
        $latestCycleSql = '(select max(started_at) from reporting_cycles where reporting_cycles.client_id = clients.id)';
        $cycleCountSql = '(select count(*) from reporting_cycles where reporting_cycles.client_id = clients.id)';

        if ($sort === 'person') {
            $query
                ->orderByRaw("lower(coalesce(last_name, '')) {$direction}")
                ->orderByRaw("lower(coalesce(first_name, '')) {$direction}")
                ->orderByRaw("lower(coalesce(middle_name, '')) {$direction}")
                ->orderBy('clients.id');

            return;
        }

        if ($sort === 'status') {
            $query->orderByRaw("lower(coalesce(status, '')) {$direction}");
        } elseif ($sort === 'provider') {
            $query->orderByRaw("{$providerAccountCountSql} {$direction}");
        } elseif ($sort === 'files') {
            $query
                ->orderByRaw("{$documentFileSizeSql} {$direction}")
                ->orderByRaw("{$documentFileCountSql} {$direction}")
                ->orderByRaw("{$documentRecordCountSql} {$direction}");
        } elseif ($sort === 'score') {
            $query
                ->orderByRaw('current_score is null asc')
                ->orderBy('current_score', $direction);
        } elseif ($sort === 'owner') {
            $query->orderByRaw("lower(coalesce({$assignedUserNameSql}, '')) {$direction}");
        } elseif ($sort === 'cycle') {
            $query
                ->orderByRaw("{$latestCycleSql} is null asc")
                ->orderByRaw("{$latestCycleSql} {$direction}")
                ->orderByRaw("{$cycleCountSql} {$direction}");
        } else {
            $query->latest('clients.id');

            return;
        }

        $query
            ->orderByRaw("lower(coalesce(last_name, '')) asc")
            ->orderByRaw("lower(coalesce(first_name, '')) asc")
            ->orderByRaw("lower(coalesce(middle_name, '')) asc")
            ->orderBy('clients.id');
    }

    protected function rosterBaseQuery(): Builder
    {
        return Client::query();
    }

    /**
     * @return array{client_count:int,file_client_count:int,document_count:int,file_count:int,metadata_only_count:int,total_bytes:int,total_label:string}
     */
    protected function documentStorageSummary(): array
    {
        $row = DB::table('client_documents')
            ->selectRaw('
                count(distinct client_id) as client_count,
                count(distinct case when coalesce(file_size, 0) > 0 then client_id else null end) as file_client_count,
                count(*) as document_count,
                coalesce(sum(case when coalesce(file_size, 0) > 0 then 1 else 0 end), 0) as file_count,
                coalesce(sum(case when coalesce(file_size, 0) <= 0 then 1 else 0 end), 0) as metadata_only_count,
                coalesce(sum(coalesce(file_size, 0)), 0) as total_bytes
            ')
            ->first();
        $totalBytes = max((int) ($row->total_bytes ?? 0), 0);

        return [
            'client_count' => max((int) ($row->client_count ?? 0), 0),
            'file_client_count' => max((int) ($row->file_client_count ?? 0), 0),
            'document_count' => max((int) ($row->document_count ?? 0), 0),
            'file_count' => max((int) ($row->file_count ?? 0), 0),
            'metadata_only_count' => max((int) ($row->metadata_only_count ?? 0), 0),
            'total_bytes' => $totalBytes,
            'total_label' => $this->humanBytes($totalBytes),
        ];
    }

    protected function humanBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return number_format($value, $power === 0 ? 0 : 1).' '.$units[$power];
    }

    /**
     * @return array{present:list<string>,missing:list<string>}
     */
    protected function requiredDocumentStatus(Client $client): array
    {
        $requirements = [
            'Photo ID' => ['photo id', 'photo of drivers license', 'driver license', 'drivers license', 'driver’s license', 'photo'],
            'Proof of address' => ['proof of address', 'utility bill', 'bill', 'lease', 'address verification'],
            'W2/SS card' => ['w2', 'w-2', 'social security card', 'ss card'],
        ];
        $matches = array_fill_keys(array_keys($requirements), false);

        $client->documents
            ->filter(fn ($document): bool => (int) ($document->file_size ?? 0) > 0)
            ->each(function ($document) use (&$matches, $requirements): void {
                $metadata = is_array($document->metadata ?? null) ? $document->metadata : [];
                $haystack = Str::lower(implode(' ', array_filter([
                    (string) ($document->title ?? ''),
                    (string) data_get($metadata, 'source_title', ''),
                    (string) data_get($metadata, 'document_type', ''),
                    (string) data_get($metadata, 'imports.disputefox.document.doc_name', ''),
                    (string) data_get($metadata, 'imports.disputefox.document.title', ''),
                ])));

                foreach ($requirements as $label => $needles) {
                    if ($matches[$label]) {
                        continue;
                    }

                    foreach ($needles as $needle) {
                        if (str_contains($haystack, $needle)) {
                            $matches[$label] = true;

                            break;
                        }
                    }
                }
            });

        return [
            'present' => array_values(array_filter(
                array_keys($matches),
                fn (string $label): bool => (bool) $matches[$label],
            )),
            'missing' => array_values(array_filter(
                array_keys($matches),
                fn (string $label): bool => ! (bool) $matches[$label],
            )),
        ];
    }

    protected function applyRosterViewFilter(Builder $query, string $view, array $disputeFoxCaptureIndex = []): void
    {
        if ($view === 'all') {
            return;
        }

        if ($view === 'fired') {
            $query->whereRaw($this->firedPredicateSql());

            return;
        }

        if ($view === 'terminated') {
            $query->whereRaw($this->terminatedPredicateSql());

            return;
        }

        if ($view === 'canceled') {
            $query->whereRaw($this->canceledPredicateSql());

            return;
        }

        if ($view === 'graduated') {
            $query->whereRaw($this->graduatedPredicateSql());

            return;
        }

        if ($view === 'leads') {
            $query->whereRaw('('.$this->leadPredicateSql().')');
            $query->whereRaw('not ('.$this->endedRelationshipPredicateSql().')');

            return;
        }

        $query->whereRaw('('.$this->activeClientPredicateSql().')');
        $query->whereRaw('not ('.$this->leadPredicateSql().')');
        $query->whereRaw('not ('.$this->endedRelationshipPredicateSql().')');
    }

    protected function activeClientPredicateSql(): string
    {
        return implode(' or ', [
            "(metadata::jsonb #> '{imports,disputefox,lists,clients}') is not null",
            "(
                (metadata::jsonb #> '{imports,disputefox}') is null
                and lower(coalesce(metadata::jsonb #>> '{crm,source_kind}', metadata::jsonb #>> '{source_kind}', '')) = 'client'
            )",
            "(
                (metadata::jsonb #> '{imports,disputefox}') is null
                and lower(coalesce(status, '')) in ('active', 'active_review', 'monitoring')
                and (".$this->activeClientEvidenceExistsSql().')
            )',
            "(
                (metadata::jsonb #> '{imports,disputefox}') is null
                and exists (
                    select 1
                    from client_billing_profiles
                    where client_billing_profiles.client_id = clients.id
                      and client_billing_profiles.next_due_at >= current_date
                )
            )",
        ]);
    }

    protected function leadPredicateSql(): string
    {
        return implode(' or ', [
            "(
                (metadata::jsonb #> '{imports,disputefox,lists,clients}') is null
                and lower(coalesce(status, '')) = 'lead'
            )",
            "(
                (metadata::jsonb #> '{imports,disputefox,lists,clients}') is null
                and coalesce(metadata::jsonb #>> '{crm,source_kind}', '') = 'lead'
            )",
            "(
                (metadata::jsonb #> '{imports,disputefox,lists,clients}') is null
                and coalesce(metadata::jsonb #>> '{source_kind}', '') = 'lead'
            )",
            "(
                (metadata::jsonb #> '{imports,disputefox,lists,clients}') is null
                and (metadata::jsonb #> '{imports,disputefox,lists,leads}') is not null
            )",
            "lower(coalesce(metadata::jsonb #>> '{imports,disputefox,regular_companion_sync,source_page_url}', '')) like '%type=leads%'",
            "(
                (metadata::jsonb #> '{imports,disputefox,lists,clients}') is null
                and (metadata::jsonb #> '{imports,disputefox,lists,leads}') is null
                and metadata::text ilike '%Lead Status%'
            )",
        ]);
    }

    protected function firedPredicateSql(): string
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

    protected function terminatedPredicateSql(): string
    {
        $leadWithHistorySql = '(('.$this->leadPredicateSql().') and ('
            .implode(' or ', [
                $this->providerLoginExistsSql(),
                $this->billingSignalExistsSql(),
                $this->staleImportedLeadSql(),
            ])
            .'))';
        $notFiredOrFinal = implode(' and ', [
            'not ('.$this->firedPredicateSql().')',
            'not ('.$this->canceledPredicateSql().')',
            'not ('.$this->graduatedPredicateSql().')',
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
                "(
                    lower(coalesce(status, '')) not in ('active', 'active_review', 'monitoring')
                    and ".$this->inactiveServicePredicateSql().'
                )',
                $this->legacyImportedProfileWithoutActiveClientSql(),
            ])
            .") and {$notFiredOrFinal}";
    }

    protected function activeClientEvidenceExistsSql(): string
    {
        return '('.implode(' or ', [
            $this->providerLoginExistsSql(),
            $this->billingSignalExistsSql(),
            'exists (
                select 1
                from client_documents
                where client_documents.client_id = clients.id
            )',
        ]).')';
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

    protected function inactiveServicePredicateSql(): string
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

    protected function canceledPredicateSql(): string
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

    protected function graduatedPredicateSql(): string
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

    protected function endedRelationshipPredicateSql(): string
    {
        return implode(' or ', [
            $this->terminatedPredicateSql(),
            $this->firedPredicateSql(),
            $this->canceledPredicateSql(),
            $this->graduatedPredicateSql(),
        ]);
    }

    protected function orWhereLegacyLeadCaptureRows(Builder $query, array $disputeFoxCaptureIndex): void
    {
        foreach ($this->legacyLeadCaptureLookups($disputeFoxCaptureIndex) as $lookup) {
            if ($lookup['values'] === []) {
                continue;
            }

            $query->orWhereIn(DB::raw($lookup['expression']), $lookup['values']);
        }
    }

    protected function excludeLegacyLeadCaptureRows(Builder $query, array $disputeFoxCaptureIndex): void
    {
        foreach ($this->legacyLeadCaptureLookups($disputeFoxCaptureIndex) as $lookup) {
            if ($lookup['values'] === []) {
                continue;
            }

            $query->whereNotIn(DB::raw("coalesce({$lookup['expression']}, '')"), $lookup['values']);
        }
    }

    /**
     * @return list<array{expression:string,values:list<string>}>
     */
    protected function legacyLeadCaptureLookups(array $disputeFoxCaptureIndex): array
    {
        $sourceRecordIds = array_keys((array) data_get($disputeFoxCaptureIndex, 'leads.source_record_ids', []));
        $sourceRecordIntIds = array_keys((array) data_get($disputeFoxCaptureIndex, 'leads.source_record_int_ids', []));

        return [
            [
                'expression' => "metadata::jsonb #>> '{imports,disputefox,source_record_id}'",
                'values' => $sourceRecordIds,
            ],
            [
                'expression' => "metadata::jsonb #>> '{imports,disputefox,regular_companion_sync,source_record_id}'",
                'values' => $sourceRecordIds,
            ],
            [
                'expression' => "metadata::jsonb #>> '{imports,disputefox,source_record_int_id}'",
                'values' => $sourceRecordIntIds,
            ],
            [
                'expression' => "metadata::jsonb #>> '{imports,disputefox,regular_companion_sync,source_record_int_id}'",
                'values' => $sourceRecordIntIds,
            ],
        ];
    }

    protected function applyRosterSearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $terms = collect(preg_split('/\s+/', Str::lower($search)) ?: [])
            ->map(fn (string $term): string => trim($term))
            ->filter()
            ->take(6)
            ->values();

        foreach ($terms as $term) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

            $query->where(function (Builder $query) use ($like): void {
                $query
                    ->whereRaw('lower(coalesce(first_name, \'\')) like ?', [$like])
                    ->orWhereRaw('lower(coalesce(middle_name, \'\')) like ?', [$like])
                    ->orWhereRaw('lower(coalesce(last_name, \'\')) like ?', [$like])
                    ->orWhereRaw('lower(coalesce(name_suffix, \'\')) like ?', [$like])
                    ->orWhereRaw("lower(concat_ws(' ', first_name, middle_name, last_name, name_suffix)) like ?", [$like])
                    ->orWhereRaw('lower(coalesce(email, \'\')) like ?', [$like])
                    ->orWhereRaw('lower(coalesce(secondary_email, \'\')) like ?', [$like])
                    ->orWhereRaw('lower(coalesce(phone, \'\')) like ?', [$like])
                    ->orWhereRaw('lower(coalesce(cuid, \'\')) like ?', [$like])
                    ->orWhereRaw('lower(coalesce(status, \'\')) like ?', [$like])
                    ->orWhereRaw("lower(coalesce(metadata::jsonb #>> '{ended_notes}', '')) like ?", [$like])
                    ->orWhereRaw("lower(coalesce(metadata::jsonb #>> '{fired_reason}', '')) like ?", [$like])
                    ->orWhereRaw("lower(coalesce(metadata::jsonb #>> '{archive_notes}', '')) like ?", [$like])
                    ->orWhereHas('assignedUser', fn (Builder $query) => $query
                        ->whereRaw('lower(coalesce(name, \'\')) like ?', [$like]))
                    ->orWhereHas('providerAccounts', fn (Builder $query) => $query
                        ->whereRaw('lower(coalesce(provider_label, \'\')) like ?', [$like])
                        ->orWhereRaw('lower(coalesce(provider_key, \'\')) like ?', [$like])
                        ->orWhereRaw('lower(coalesce(login_email, \'\')) like ?', [$like])
                        ->orWhereRaw('lower(coalesce(login_username, \'\')) like ?', [$like]));
            });
        }
    }

    protected function rosterCount(string $view, array $disputeFoxCaptureIndex = []): int
    {
        $query = $this->rosterBaseQuery();
        $this->applyRosterViewFilter($query, $view, $disputeFoxCaptureIndex);

        return $query->count();
    }

    protected function pulseReportPulledCount(): int
    {
        return Client::query()
            ->whereRaw("metadata::text ~* '\"Report Pulled\"\\s*:\\s*\"Yes\"'")
            ->count();
    }

    protected function providerReadyCount(): int
    {
        return Client::query()
            ->whereHas('providerAccounts', function (Builder $query): void {
                $query->whereIn('status', ['connected', 'import_only'])
                    ->where(function (Builder $query): void {
                        $query
                            ->whereRaw("coalesce(login_email, '') <> ''")
                            ->orWhereRaw("coalesce(login_username, '') <> ''");
                    })
                    ->whereRaw("coalesce(login_password, '') <> ''");
            })
            ->count();
    }

    protected function needsProviderCredentialsCount(): int
    {
        return Client::query()
            ->whereRaw("(metadata::jsonb #> '{imports,disputefox}') is not null")
            ->whereDoesntHave('providerAccounts', function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query
                        ->whereRaw("coalesce(login_email, '') <> ''")
                        ->orWhereRaw("coalesce(login_username, '') <> ''");
                })->whereRaw("coalesce(login_password, '') <> ''");
            })
            ->count();
    }

    protected function pulseCaptureRowCount(string $captureType): int
    {
        return (int) MigrationOperatorCapture::query()
            ->where('source_system', 'disputefox')
            ->where('capture_type', $captureType)
            ->get()
            ->map(function (MigrationOperatorCapture $capture): int {
                return (int) data_get($capture->metadata ?? [], 'row_count', 0);
            })
            ->max();
    }

    protected function disputeFoxAffiliateCount(): int
    {
        $directAffiliateCount = $this->pulseCaptureRowCount('pulse_affiliates');

        if ($directAffiliateCount > 0) {
            return $directAffiliateCount;
        }

        $leadCapture = MigrationOperatorCapture::query()
            ->where('source_system', 'disputefox')
            ->where('capture_type', 'pulse_leads')
            ->latest()
            ->first();

        return collect(data_get($leadCapture?->metadata ?? [], 'records', []))
            ->map(fn (array $record): string => trim((string) data_get($record, 'values.Affiliate', '')))
            ->filter()
            ->unique(fn (string $name): string => Str::lower($name))
            ->count();
    }

    /**
     * @return array{
     *     clients:array{source_record_ids:array<string, true>,source_record_int_ids:array<string, true>},
     *     leads:array{source_record_ids:array<string, true>,source_record_int_ids:array<string, true>}
     * }
     */
    protected function disputeFoxCaptureSourceIndex(): array
    {
        $index = [
            'clients' => ['source_record_ids' => [], 'source_record_int_ids' => []],
            'leads' => ['source_record_ids' => [], 'source_record_int_ids' => []],
        ];
        $captureKinds = [
            'clients' => 'pulse_clients',
            'leads' => 'pulse_leads',
        ];

        MigrationOperatorCapture::query()
            ->where('source_system', 'disputefox')
            ->whereIn('capture_type', array_values($captureKinds))
            ->latest('id')
            ->get()
            ->each(function (MigrationOperatorCapture $capture) use (&$index, $captureKinds): void {
                $kind = array_search($capture->capture_type, $captureKinds, true);

                if (! is_string($kind) || ! isset($index[$kind])) {
                    return;
                }

                collect(data_get($capture->metadata ?? [], 'records', []))
                    ->each(function (array $record) use (&$index, $kind): void {
                        foreach ($this->disputeFoxSourceRecordIds($record) as $sourceRecordId) {
                            $index[$kind]['source_record_ids'][$sourceRecordId] = true;
                        }

                        foreach ($this->disputeFoxSourceRecordIntIds($record) as $sourceRecordIntId) {
                            $index[$kind]['source_record_int_ids'][$sourceRecordIntId] = true;
                        }
                    });
            });

        return $index;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    protected function disputeFoxSourceRecordIds(array $payload): array
    {
        $ids = [
            data_get($payload, 'source_record_id'),
            data_get($payload, 'imports.disputefox.source_record_id'),
            data_get($payload, 'regular_companion_sync.source_record_id'),
        ];
        $profileUrl = (string) (
            data_get($payload, 'profile_url')
            ?: data_get($payload, 'regular_companion_sync.source_page_url')
            ?: ''
        );

        if ($profileUrl !== '') {
            parse_str((string) parse_url($profileUrl, PHP_URL_QUERY), $query);
            $ids[] = $query['id'] ?? null;
        }

        return collect($ids)
            ->map(fn ($id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    protected function disputeFoxSourceRecordIntIds(array $payload): array
    {
        return collect([
            data_get($payload, 'source_record_int_id'),
            data_get($payload, 'imports.disputefox.source_record_int_id'),
            data_get($payload, 'regular_companion_sync.source_record_int_id'),
        ])
            ->map(fn ($id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     pulse_imported:bool,
     *     report_pulled:?bool,
     *     report_pulled_label:string,
     *     profile_synced_at:?string,
     *     list_synced_at:?string,
     *     source_kind:string,
     *     source_lane:?string,
     *     profile_url:?string,
     *     pulse_credentials_saved:bool,
     *     provider_ready:bool,
     *     needs_provider_credentials:bool,
     *     providers:list<array{label:string,key:string,status:string,login_identifier:?string,login_saved:bool,password_saved:bool,security_answer_saved:bool,ready:bool,last_imported_at:?string}>
     * }
     */
    protected function clientImportAudit(Client $client, array $disputeFoxCaptureIndex = []): array
    {
        $metadata = $client->metadata ?? [];
        $pulse = (array) data_get($metadata, 'imports.disputefox', []);
        $profileSync = (array) data_get($pulse, 'regular_companion_sync', []);
        $listSync = (array) (
            data_get($pulse, 'lists.clients')
            ?: data_get($pulse, 'lists.leads')
            ?: []
        );
        $rawRow = (array) data_get($listSync, 'raw_row', []);
        $sourceAssignedTo = (string) (
            data_get($profileSync, 'source_assigned_to')
            ?: data_get($listSync, 'source_assigned_to')
            ?: data_get($rawRow, 'Assigned To')
            ?: data_get($rawRow, 'Agent')
            ?: data_get($rawRow, 'Owner')
            ?: ''
        );
        $matchedAssignedTo = data_get($profileSync, 'matched_assigned_to')
            ?: data_get($listSync, 'matched_assigned_to');
        $reportPulled = $this->pulseReportPulledStatus($rawRow);

        if ($reportPulled === null && $pulse !== []) {
            $reportPulled = $this->pulseReportPulledStatus($pulse);
        }

        $providers = $client->providerAccounts
            ->map(function ($provider): array {
                $loginSaved = filled($provider->login_email) || filled($provider->login_username);
                $loginIdentifier = $provider->login_email ?: $provider->login_username;
                $passwordSaved = $provider->hasStoredPassword();
                $securityAnswerSaved = $provider->hasStoredSecurityAnswer();
                $ready = in_array((string) $provider->status, ['connected', 'import_only'], true) && $loginSaved && $passwordSaved;

                if ($provider->provider_key === 'identityiq') {
                    $ready = $ready && $securityAnswerSaved;
                }

                return [
                    'label' => $this->providerDisplayLabel((string) $provider->provider_label, (string) $provider->provider_key),
                    'key' => $provider->provider_key,
                    'status' => $provider->status,
                    'login_identifier' => $loginIdentifier,
                    'login_saved' => $loginSaved,
                    'password_saved' => $passwordSaved,
                    'security_answer_saved' => $securityAnswerSaved,
                    'ready' => $ready,
                    'last_imported_at' => optional($provider->last_imported_at)?->toDateTimeString(),
                    'credential_health' => $this->providerCredentialHealth($provider),
                ];
            })
            ->values()
            ->all();
        $providerReady = collect($providers)->contains(fn (array $provider): bool => (bool) $provider['ready']);

        $sourceKind = $this->endedRelationshipKind($client)
            ?: (Str::lower((string) $client->status) === 'lead'
                ? 'lead'
                : $this->disputeFoxSourceKind($metadata, $pulse, $disputeFoxCaptureIndex));

        if (
            $sourceKind === 'lead'
            && ! in_array(Str::lower((string) $client->status), ['lead', 'intake'], true)
            && data_get($pulse, 'lists.leads') === null
            && (string) data_get($metadata, 'crm.source_kind', '') !== 'lead'
            && (string) data_get($metadata, 'source_kind', '') !== 'lead'
        ) {
            $sourceKind = 'client';
        }

        return [
            'pulse_imported' => $pulse !== [],
            'report_pulled' => $reportPulled,
            'report_pulled_label' => match ($reportPulled) {
                true => 'Yes',
                false => 'No',
                default => 'Unknown',
            },
            'profile_synced_at' => data_get($profileSync, 'synced_at'),
            'list_synced_at' => data_get($listSync, 'synced_at'),
            'source_kind' => $sourceKind,
            'source_lane' => data_get($listSync, 'page_title'),
            'source_assigned_to' => trim($sourceAssignedTo) !== '' ? trim($sourceAssignedTo) : null,
            'matched_assigned_to' => $matchedAssignedTo,
            'profile_url' => data_get($listSync, 'profile_url') ?: data_get($profileSync, 'source_page_url'),
            'pulse_credentials_saved' => (bool) data_get($profileSync, 'credentials_saved', false),
            'provider_ready' => $providerReady,
            'needs_provider_credentials' => $pulse !== [] && ! $providerReady,
            'providers' => $providers,
        ];
    }

    protected function providerDisplayLabel(string $providerLabel, string $providerKey): string
    {
        $label = trim($providerLabel);

        if ($label !== '') {
            return $label;
        }

        return Str::of($providerKey)->replace('_', ' ')->headline()->value() ?: 'Provider';
    }

    protected function endedRelationshipKind(Client $client): ?string
    {
        $metadata = $client->metadata ?? [];
        $status = Str::lower((string) $client->status);
        $reason = Str::lower((string) data_get($metadata, 'ended_reason', ''));
        $outcome = Str::lower((string) data_get($metadata, 'engagement_outcome', ''));
        $explicitSourceKind = Str::lower((string) (
            data_get($metadata, 'crm.source_kind')
            ?: data_get($metadata, 'source_kind')
        ));

        if (
            in_array($status, ['canceled', 'cancelled'], true)
            || in_array($reason, ['requested_cancellation', 'canceled', 'cancelled'], true)
            || in_array($outcome, ['requested_cancellation', 'canceled', 'cancelled'], true)
        ) {
            return 'canceled';
        }

        if (
            in_array($status, ['resolved', 'graduated', 'finished'], true)
            || in_array($reason, ['goals_met', 'no_longer_needed_help', 'graduated', 'finished'], true)
            || in_array($outcome, ['goals_met', 'no_longer_needed_help', 'graduated', 'finished'], true)
        ) {
            return 'graduated';
        }

        if (
            in_array($status, ['fired'], true)
            || $reason === 'fired'
            || $outcome === 'fired'
            || data_get($metadata, 'fired_at') !== null
        ) {
            return 'fired';
        }

        if (
            $explicitSourceKind === 'client'
            && in_array($status, ['active', 'active_review', 'intake', 'monitoring'], true)
            && $reason === ''
            && $outcome === ''
        ) {
            return null;
        }

        if (
            $this->clientHasRecoverableTerminationMarker($client)
            || $this->leadHasInactiveHistorySignal($client)
        ) {
            return 'terminated';
        }

        return null;
    }

    protected function clientHasRecoverableTerminationMarker(Client $client): bool
    {
        $metadata = $client->metadata ?? [];
        $status = Str::lower((string) $client->status);
        $reason = Str::lower((string) data_get($metadata, 'ended_reason', ''));
        $outcome = Str::lower((string) data_get($metadata, 'engagement_outcome', ''));
        $rawStatus = Str::lower((string) data_get($metadata, 'imports.disputefox.lists.clients.raw_row.Status', ''));
        $rawClientStatus = Str::lower((string) data_get($metadata, 'imports.disputefox.lists.clients.raw_row.Client Status', ''));
        $rawStage = Str::lower(trim(implode(' ', [
            (string) data_get($metadata, 'imports.disputefox.lists.clients.raw_row.Stage in Processs', ''),
            (string) data_get($metadata, 'imports.disputefox.lists.clients.raw_row.Stage in Process', ''),
        ])));

        return in_array($status, ['terminated'], true)
            || in_array($reason, ['nonpayment', 'unresponsive', 'compliance_risk', 'abusive_behavior', 'other', 'terminated', 'closed', 'archived'], true)
            || in_array($outcome, ['terminated', 'closed', 'archived'], true)
            || in_array($rawStatus, ['closed', 'archived', 'terminated'], true)
            || in_array($rawClientStatus, ['closed', 'archived', 'terminated'], true)
            || Str::contains($rawStage, ['closed', 'archived', 'terminated'])
            || $this->clientIsLegacyImportedProfileWithoutActiveClientList($client)
            || $this->clientHasInactiveServiceSignal($client);
    }

    protected function leadHasInactiveHistorySignal(Client $client): bool
    {
        return $this->clientLooksLikeLead($client) && (
            $this->leadHasProviderLogin($client)
            || $this->leadHasBillingSignal($client)
            || $this->leadIsStaleImported($client)
        );
    }

    protected function clientLooksLikeLead(Client $client): bool
    {
        $metadata = $client->metadata ?? [];
        $status = Str::lower((string) $client->status);
        $explicitSourceKind = Str::lower((string) (
            data_get($metadata, 'crm.source_kind')
            ?: data_get($metadata, 'source_kind')
        ));

        if ($explicitSourceKind === 'client' && $status !== 'lead') {
            return false;
        }

        return $status === 'lead'
            || (string) data_get($metadata, 'crm.source_kind', '') === 'lead'
            || (string) data_get($metadata, 'source_kind', '') === 'lead'
            || data_get($metadata, 'imports.disputefox.lists.leads') !== null
            || str_contains(
                Str::lower((string) data_get($metadata, 'imports.disputefox.regular_companion_sync.source_page_url', '')),
                'type=leads'
            );
    }

    protected function leadHasProviderLogin(Client $client): bool
    {
        $providerAccounts = $client->relationLoaded('providerAccounts')
            ? $client->providerAccounts
            : ClientProviderAccount::query()
                ->where('client_id', $client->getKey())
                ->get();

        return $providerAccounts->contains(
            fn (ClientProviderAccount $provider): bool => filled($provider->login_email)
                || filled($provider->login_username)
                || filled($provider->login_password)
        );
    }

    protected function leadHasBillingSignal(Client $client): bool
    {
        $metadataText = json_encode($client->metadata ?? []);

        if (is_string($metadataText) && Str::contains($metadataText, ['ActivePay', 'FailedPay'])) {
            return true;
        }

        $payments = $client->relationLoaded('payments')
            ? $client->payments
            : $client->payments()->limit(1)->get();

        if ($payments->isNotEmpty()) {
            return true;
        }

        $profile = $client->relationLoaded('billingProfile')
            ? $client->billingProfile
            : $client->billingProfile()->first();

        return $profile !== null
            && (
                filled($profile->status)
                || $profile->last_paid_at !== null
                || $profile->next_due_at !== null
            );
    }

    protected function leadIsStaleImported(Client $client): bool
    {
        $metadata = $client->metadata ?? [];
        $dates = [
            data_get($metadata, 'imports.disputefox.lists.leads.raw_row.Added Date'),
            data_get($metadata, 'imports.disputefox.lists.clients.raw_row.Started'),
        ];
        $cutoff = now()->subDays(180);

        foreach ($dates as $date) {
            if (! is_string($date) || trim($date) === '') {
                continue;
            }

            try {
                if (Carbon::parse($date)->startOfDay()->lt($cutoff)) {
                    return true;
                }
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        return false;
    }

    protected function clientHasInactiveServiceSignal(Client $client): bool
    {
        $providerAccounts = $client->relationLoaded('providerAccounts')
            ? $client->providerAccounts
            : ClientProviderAccount::query()
                ->where('client_id', $client->getKey())
                ->get();

        if ($providerAccounts->contains(function (ClientProviderAccount $provider): bool {
            $status = Str::lower((string) $provider->status);
            $text = Str::lower(trim(($provider->notes ?? '').' '.(json_encode($provider->metadata ?? []) ?: '')));

            return in_array($status, ['needs_client_payment', 'needs_reactivation'], true)
                || Str::contains($text, ['reactivation', 'needs_client_payment', 'smartcredit_reactivation']);
        })) {
            return true;
        }

        $profile = $client->relationLoaded('billingProfile')
            ? $client->billingProfile
            : $client->billingProfile()->first();
        $payments = $client->relationLoaded('payments')
            ? $client->payments
            : $client->payments()->get();
        $latestPaymentAt = $payments
            ->pluck('paid_at')
            ->filter()
            ->sortDesc()
            ->first();
        $lastPaidAt = collect([$profile?->last_paid_at, $latestPaymentAt])
            ->filter()
            ->map(fn ($date): Carbon => $date instanceof Carbon ? $date : Carbon::parse($date))
            ->sortByDesc(fn (Carbon $date): int => $date->getTimestamp())
            ->first();
        $hasFutureDue = $profile?->next_due_at instanceof Carbon
            && $profile->next_due_at->gte(now()->startOfDay());

        return $lastPaidAt instanceof Carbon
            && $lastPaidAt->lt(now()->subDays(90))
            && ! $hasFutureDue;
    }

    protected function clientIsLegacyImportedProfileWithoutActiveClientList(Client $client): bool
    {
        $metadata = $client->metadata ?? [];

        return data_get($metadata, 'imports.disputefox.regular_companion_sync') !== null
            && data_get($metadata, 'imports.disputefox.lists.clients') === null
            && data_get($metadata, 'imports.disputefox.lists.leads') === null
            && ! in_array((string) data_get($metadata, 'crm.source_kind'), ['client'], true)
            && ! in_array((string) data_get($metadata, 'source_kind'), ['client'], true);
    }

    protected function disputeFoxSourceKind(array $metadata, array $pulse, array $disputeFoxCaptureIndex = []): string
    {
        $explicitSourceKind = (string) (
            data_get($metadata, 'crm.source_kind')
            ?: data_get($metadata, 'source_kind')
        );

        if (in_array($explicitSourceKind, ['lead', 'client'], true)) {
            return $explicitSourceKind;
        }

        if (data_get($pulse, 'lists.leads')) {
            return 'lead';
        }

        if (data_get($pulse, 'lists.clients')) {
            return 'client';
        }

        $sourceRecordIds = $this->disputeFoxSourceRecordIds($pulse);
        $sourceRecordIntIds = $this->disputeFoxSourceRecordIntIds($pulse);

        $appearsInClients = collect($sourceRecordIds)->contains(fn (string $id): bool => (bool) data_get($disputeFoxCaptureIndex, "clients.source_record_ids.{$id}", false))
            || collect($sourceRecordIntIds)->contains(fn (string $id): bool => (bool) data_get($disputeFoxCaptureIndex, "clients.source_record_int_ids.{$id}", false));
        $appearsInLeads = collect($sourceRecordIds)->contains(fn (string $id): bool => (bool) data_get($disputeFoxCaptureIndex, "leads.source_record_ids.{$id}", false))
            || collect($sourceRecordIntIds)->contains(fn (string $id): bool => (bool) data_get($disputeFoxCaptureIndex, "leads.source_record_int_ids.{$id}", false));

        if ($appearsInLeads && ! $appearsInClients) {
            return 'lead';
        }

        if ($appearsInClients) {
            return 'client';
        }

        $pageUrl = Str::lower((string) data_get($pulse, 'regular_companion_sync.source_page_url', ''));

        if (str_contains($pageUrl, 'type=leads')) {
            return 'lead';
        }

        if (str_contains($pageUrl, 'type=clients')) {
            return 'client';
        }

        $fieldText = collect((array) data_get($pulse, 'regular_companion_sync.field_values', []))
            ->map(fn (array $field): string => implode(' ', [
                (string) ($field['label'] ?? ''),
                (string) ($field['name'] ?? ''),
                (string) ($field['id'] ?? ''),
                (string) ($field['value'] ?? ''),
            ]))
            ->implode(' ');
        $fieldText = Str::lower($fieldText);

        if (str_contains($fieldText, 'lead status')) {
            return 'lead';
        }

        if (str_contains($fieldText, 'client status')) {
            return 'client';
        }

        return 'client';
    }

    protected function pulseReportPulledStatus(array $payload): ?bool
    {
        $direct = data_get($payload, 'Report Pulled');

        if (is_string($direct) && trim($direct) !== '') {
            return Str::lower(trim($direct)) === 'yes';
        }

        $encoded = json_encode($payload);

        if (! is_string($encoded) || $encoded === '') {
            return null;
        }

        if (preg_match('/"Report Pulled"\s*:\s*"Yes"/i', $encoded) === 1) {
            return true;
        }

        if (preg_match('/"Report Pulled"\s*:\s*"No"/i', $encoded) === 1) {
            return false;
        }

        return null;
    }

    public function store(
        Request $request,
        AuditTrail $auditTrail,
        ClientAssignmentService $assignments,
        OfficeGrowthRuntime $growth,
    ): RedirectResponse {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'name_suffix' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'current_score' => ['nullable', 'integer', 'min:300', 'max:850'],
            'status' => ['required', 'string', 'max:50'],
            'assignment_mode' => ['required', 'string', 'in:single_user,split_evenly'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'assignment_user_ids' => ['nullable', 'array'],
            'assignment_user_ids.*' => ['integer', 'exists:users,id'],
            'goals' => ['nullable', 'string'],
            'affiliate_key' => ['nullable', 'string', 'max:255'],
            'crm_values' => ['nullable', 'array'],
            'return_to_roster' => ['nullable', 'boolean'],
        ]);

        $validated = MailingAddress::normalizeFields($this->normalizeClientNameFields($validated));

        try {
            $assignedTo = $assignments->resolveForCreate(
                $validated['assignment_mode'],
                isset($validated['assigned_to']) ? (int) $validated['assigned_to'] : null,
                $validated['assignment_user_ids'] ?? [],
            );
        } catch (InvalidArgumentException $exception) {
            $field = $validated['assignment_mode'] === ClientAssignmentService::MODE_SPLIT_EVENLY
                ? 'assignment_user_ids'
                : 'assigned_to';

            throw ValidationException::withMessages([
                $field => $exception->getMessage(),
            ]);
        }

        $affiliate = $growth->affiliateByKey($validated['affiliate_key'] ?? null);
        $crmValues = $this->sanitizeCrmValues($validated['crm_values'] ?? [], collect([
            ...$growth->crmFields('lead'),
            ...$growth->crmFields('client'),
        ])->unique('key')->values()->all());

        $client = Client::create([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'name_suffix' => $validated['name_suffix'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'current_score' => $validated['current_score'] ?? null,
            'status' => $validated['status'],
            'assigned_to' => $assignedTo,
            'goals' => $validated['goals'] ?? null,
            'cuid' => 'c_'.Str::lower(Str::random(10)),
            'metadata' => array_filter([
                'intake' => array_filter([
                    'affiliate' => $affiliate ? [
                        'key' => $affiliate['key'],
                        'label' => $affiliate['label'],
                        'email' => $affiliate['email'],
                        'company' => $affiliate['company'],
                        'assigned_to' => $affiliate['assigned_to'],
                    ] : null,
                    'crm' => $crmValues !== [] ? $crmValues : null,
                ]),
            ]),
        ]);

        $auditTrail->record(
            $request->user(),
            'client.created',
            "Created client dossier for {$client->display_name}.",
            $client,
        );

        if ($request->boolean('return_to_roster')) {
            return redirect()
                ->route('clients.index')
                ->with('success', "{$client->display_name} saved.");
        }

        return redirect()->route('clients.show', $client);
    }

    public function update(
        Request $request,
        Client $client,
        AuditTrail $auditTrail,
        ClientProfileSnapshotService $profileSnapshots,
    ): RedirectResponse {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'name_suffix' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'secondary_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date'],
            'ssn' => ['nullable', 'string', 'max:32'],
            'current_score' => ['nullable', 'integer', 'min:300', 'max:850'],
            'goals' => ['nullable', 'string'],
        ]);

        $profileFields = [
            'first_name',
            'middle_name',
            'last_name',
            'name_suffix',
            'email',
            'secondary_email',
            'phone',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postal_code',
            'date_of_birth',
            'current_score',
            'goals',
        ];
        $before = Arr::only($client->toArray(), $profileFields);
        $previousSsn = trim((string) ($client->ssn ?? ''));

        foreach ($validated as $key => $value) {
            if (is_string($value)) {
                $validated[$key] = trim($value) !== '' ? trim($value) : null;
            }
        }

        if (array_key_exists('ssn', $validated)) {
            $ssn = preg_replace('/\D+/', '', (string) ($validated['ssn'] ?? '')) ?? '';

            if ($ssn === '') {
                unset($validated['ssn']);
            } elseif (strlen($ssn) < 4) {
                throw ValidationException::withMessages([
                    'ssn' => 'Enter at least the last 4 digits.',
                ]);
            } else {
                $validated['ssn'] = $ssn;
            }
        }

        if (is_string($validated['state'] ?? null) && strlen((string) $validated['state']) <= 2) {
            $validated['state'] = Str::upper((string) $validated['state']);
        }

        $validated = $this->normalizeClientNameFields($validated);

        $client->fill($validated);
        $client->save();

        $client->refresh();
        $after = Arr::only($client->toArray(), array_keys($before));
        $changes = [];

        foreach ($after as $field => $value) {
            if (($before[$field] ?? null) !== $value) {
                $changes[$field] = [
                    'before' => $before[$field] ?? null,
                    'after' => $value,
                ];
            }
        }

        if (array_key_exists('ssn', $validated)) {
            $currentSsn = trim((string) ($client->ssn ?? ''));
            $changes['ssn'] = [
                'before' => $previousSsn !== '' ? 'ending '.Str::substr($previousSsn, -4) : null,
                'after' => $currentSsn !== '' ? 'ending '.Str::substr($currentSsn, -4) : null,
            ];
        }

        if ($changes !== []) {
            $auditTrail->record(
                $request->user(),
                'client.profile.updated',
                "Updated personal and contact info for {$client->display_name}.",
                $client,
                ['changes' => $changes],
            );

            $profileSnapshots->recordIfTrackedFieldsChanged(
                $client,
                array_keys($changes),
                'office',
                [
                    'updated_by_user_id' => $request->user()?->getKey(),
                    'updated_by_name' => $request->user()?->name,
                ],
                ['changes' => $changes],
            );
        }

        return back()->with('success', "{$client->display_name} profile saved.");
    }

    public function storeManualBilling(
        Request $request,
        Client $client,
        AuditTrail $auditTrail,
        ClientHealthSignalService $clientHealth,
    ): RedirectResponse {
        abort_unless($request->user()?->canManageUsers(), 403);

        $validated = $request->validate([
            'kind' => ['required', 'string', 'in:cash,zelle,cash_app,check,manual,pro_bono,owner_comp'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'paid_at' => ['nullable', 'date'],
            'billing_interval' => ['required', 'string', 'in:monthly,annual,lifetime,one_time'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $kind = (string) $validated['kind'];
        $isComped = in_array($kind, ['pro_bono', 'owner_comp'], true);
        $amount = $isComped ? 0.0 : (float) ($validated['amount'] ?? 0);

        if (! $isComped && $amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Enter the amount paid, or choose Pro bono / Owner comp.',
            ]);
        }

        $paidAt = isset($validated['paid_at'])
            ? Carbon::parse($validated['paid_at'])->startOfDay()
            : now();
        $currency = Str::upper((string) ($validated['currency'] ?? 'USD'));
        $interval = $isComped ? 'lifetime' : (string) $validated['billing_interval'];
        $kindLabel = match ($kind) {
            'cash_app' => 'Cash App',
            'owner_comp' => 'Owner comp',
            'pro_bono' => 'Pro bono',
            default => Str::headline($kind),
        };

        DB::transaction(function () use ($client, $validated, $kind, $isComped, $amount, $paidAt, $currency, $interval, $kindLabel, $request, $auditTrail, $clientHealth): void {
            $profile = ClientBillingProfile::query()->firstOrNew([
                'client_id' => $client->getKey(),
            ]);
            $profileMetadata = $profile->metadata ?? [];
            data_set($profileMetadata, 'manual_billing.kind', $kind);
            data_set($profileMetadata, 'manual_billing.recorded_at', now()->toIso8601String());
            data_set($profileMetadata, 'manual_billing.recorded_by_user_id', $request->user()?->getKey());
            data_set($profileMetadata, 'manual_billing.pro_bono', $kind === 'pro_bono');
            data_set($profileMetadata, 'manual_billing.owner_account', $kind === 'owner_comp');
            data_set($profileMetadata, 'pro_bono', $kind === 'pro_bono');
            data_set($profileMetadata, 'owner_account', $kind === 'owner_comp');

            $profile->fill([
                'status' => 'active',
                'amount' => $amount,
                'currency' => $currency,
                'billing_interval' => $interval,
                'started_at' => $profile->started_at ?? $paidAt->toDateString(),
                'last_paid_at' => $paidAt,
                'next_due_at' => $this->manualBillingNextDueAt($interval, $paidAt, $kind),
                'gateway_name' => $isComped ? 'internal' : $kind,
                'notes' => trim((string) ($validated['notes'] ?? '')) ?: "{$kindLabel} billing recorded from the client dossier.",
                'metadata' => $profileMetadata,
            ])->save();

            $payment = ClientPayment::query()->create([
                'client_id' => $client->getKey(),
                'client_billing_profile_id' => $profile->getKey(),
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'paid',
                'paid_at' => $paidAt,
                'gateway_name' => $isComped ? 'internal' : $kind,
                'reference' => sprintf('manual-%s-%s', $client->cuid ?: $client->getKey(), now()->format('YmdHis')),
                'notes' => trim((string) ($validated['notes'] ?? '')) ?: "{$kindLabel} billing recorded from the client dossier.",
                'metadata' => [
                    'source' => 'manual_client_billing',
                    'kind' => $kind,
                    'pro_bono' => $kind === 'pro_bono',
                    'owner_account' => $kind === 'owner_comp',
                    'recorded_by_user_id' => $request->user()?->getKey(),
                    'recorded_at' => now()->toIso8601String(),
                ],
            ]);

            $metadata = $client->metadata ?? [];
            data_set($metadata, 'crm.source_kind', 'client');
            data_set($metadata, 'source_kind', 'client');
            data_set($metadata, 'billing.manual_kind', $kind);
            data_set($metadata, 'billing.last_manual_payment_id', $payment->getKey());
            data_set($metadata, 'billing.pro_bono', $kind === 'pro_bono');
            data_set($metadata, 'owner_account', $kind === 'owner_comp');
            data_forget($metadata, 'ended_at');
            data_forget($metadata, 'ended_reason');
            data_forget($metadata, 'engagement_outcome');

            $client->forceFill([
                'status' => in_array((string) $client->status, ['terminated', 'canceled', 'cancelled', 'fired', 'graduated'], true)
                    ? 'active_review'
                    : $client->status,
                'metadata' => $metadata,
            ])->save();

            $clientHealth->sync($client->fresh());

            $auditTrail->record(
                $request->user(),
                'client.billing.manual_recorded',
                "Recorded {$kindLabel} billing for {$client->display_name}.",
                $client,
                [
                    'kind' => $kind,
                    'amount' => $amount,
                    'currency' => $currency,
                    'payment_id' => $payment->getKey(),
                    'billing_profile_id' => $profile->getKey(),
                ],
            );
        });

        return back()->with('success', "{$kindLabel} billing saved for {$client->display_name}.");
    }

    protected function manualBillingNextDueAt(string $interval, Carbon $paidAt, string $kind): ?Carbon
    {
        if (in_array($kind, ['pro_bono', 'owner_comp'], true) || in_array($interval, ['lifetime', 'one_time'], true)) {
            return null;
        }

        return match ($interval) {
            'annual' => $paidAt->copy()->addYear(),
            default => $paidAt->copy()->addMonth(),
        };
    }

    public function promoteLead(
        Request $request,
        Client $client,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $metadata = $client->metadata ?? [];
        $leadListMetadata = data_get($metadata, 'imports.disputefox.lists.leads');

        if ($leadListMetadata) {
            data_set($metadata, 'imports.disputefox.promoted_from_lead', $leadListMetadata);
            data_set($metadata, 'imports.disputefox.lists.clients', [
                ...(array) $leadListMetadata,
                'promoted_from_lead_at' => now()->toIso8601String(),
                'promoted_by_user_id' => $request->user()?->getKey(),
            ]);
            data_forget($metadata, 'imports.disputefox.lists.leads');
        }

        data_set($metadata, 'crm.source_kind', 'client');
        data_set($metadata, 'source_kind', 'client');
        data_set($metadata, 'promoted_from_lead_at', now()->toIso8601String());

        if ((string) $client->status === 'lead') {
            $client->status = 'intake';
        }

        $client->metadata = $metadata;
        $client->save();

        $auditTrail->record(
            $request->user(),
            'client.promoted_from_lead',
            "Promoted {$client->display_name} from lead to client.",
            $client,
        );

        $redirectRoute = $request->string('return_to')->toString() === 'inbox'
            ? 'inbox.index'
            : 'clients.index';

        return redirect()
            ->route($redirectRoute)
            ->with('success', "{$client->display_name} moved to clients.");
    }

    public function reviewLead(
        Request $request,
        Client $client,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        if (! $this->clientLooksLikeLead($client)) {
            throw ValidationException::withMessages([
                'client' => 'Only leads can be cleared from the inbox this way.',
            ]);
        }

        $metadata = $client->metadata ?? [];
        data_set($metadata, 'inbox.reviewed_at', now()->toIso8601String());
        data_set($metadata, 'inbox.reviewed_by_user_id', $request->user()?->getKey());
        data_set($metadata, 'inbox.reviewed_action', 'reviewed');

        $client->forceFill([
            'metadata' => $metadata,
        ])->save();

        $auditTrail->record(
            $request->user(),
            'client.lead_reviewed',
            "Marked lead {$client->display_name} reviewed in the inbox.",
            $client,
            [
                'reviewed_at' => data_get($metadata, 'inbox.reviewed_at'),
            ],
        );

        $redirectRoute = $request->string('return_to')->toString() === 'inbox'
            ? 'inbox.index'
            : 'clients.index';
        $redirectParameters = $redirectRoute === 'clients.index' ? ['view' => 'leads'] : [];

        return redirect()
            ->route($redirectRoute, $redirectParameters)
            ->with('success', "{$client->display_name} marked reviewed.");
    }

    public function fireClient(Request $request, Client $client, AuditTrail $auditTrail): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $reason = trim((string) $validated['reason']);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Add the reason this client is being fired or archived.',
            ]);
        }

        $client->loadMissing('providerAccounts');

        if ($this->clientImportAudit($client, $this->disputeFoxCaptureSourceIndex())['source_kind'] === 'lead') {
            throw ValidationException::withMessages([
                'client' => 'Leads can be deleted. Only clients can be fired.',
            ]);
        }

        $metadata = $client->metadata ?? [];
        data_set($metadata, 'crm.source_kind', 'client');
        data_set($metadata, 'source_kind', 'client');
        data_set($metadata, 'ended_at', now()->toDateString());
        data_set($metadata, 'ended_reason', 'fired');
        data_set($metadata, 'ended_notes', $reason);
        data_set($metadata, 'engagement_outcome', 'fired');
        data_set($metadata, 'fired_reason', $reason);
        data_set($metadata, 'fired_at', now()->toIso8601String());
        data_set($metadata, 'fired_by_user_id', $request->user()?->getKey());
        data_set($metadata, 'archive_notes', $reason);

        $client->forceFill([
            'status' => 'terminated',
            'metadata' => $metadata,
        ])->save();

        $auditTrail->record(
            $request->user(),
            'client.fired',
            "Fired {$client->display_name} and archived the searchable dossier.",
            $client,
            [
                'status' => 'terminated',
                'ended_reason' => 'fired',
                'ended_at' => data_get($metadata, 'ended_at'),
                'fired_reason' => $reason,
                'searchable_archive' => true,
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$client->display_name} moved to Fired. The dossier remains searchable.",
        ]);

        return redirect()
            ->route('clients.index', ['view' => 'fired'])
            ->with('success', "{$client->display_name} moved to Fired. The dossier remains searchable.");
    }

    public function graduateClient(Request $request, Client $client, AuditTrail $auditTrail): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $client->loadMissing('providerAccounts');

        if ($this->clientImportAudit($client, $this->disputeFoxCaptureSourceIndex())['source_kind'] === 'lead') {
            throw ValidationException::withMessages([
                'client' => 'Leads should be promoted before they can graduate.',
            ]);
        }

        $notes = trim((string) ($validated['notes'] ?? ''));
        $metadata = $client->metadata ?? [];
        data_set($metadata, 'crm.source_kind', 'client');
        data_set($metadata, 'source_kind', 'client');
        data_set($metadata, 'ended_at', now()->toDateString());
        data_set($metadata, 'ended_reason', 'goals_met');
        data_set($metadata, 'ended_notes', $notes !== '' ? $notes : 'Graduated from the client roster.');
        data_set($metadata, 'engagement_outcome', 'graduated');
        data_set($metadata, 'graduated_at', now()->toIso8601String());
        data_set($metadata, 'graduated_by_user_id', $request->user()?->getKey());

        $client->forceFill([
            'status' => 'graduated',
            'metadata' => $metadata,
        ])->save();

        $auditTrail->record(
            $request->user(),
            'client.graduated',
            "Graduated {$client->display_name} and archived the active relationship.",
            $client,
            [
                'status' => 'graduated',
                'ended_reason' => 'goals_met',
                'ended_at' => data_get($metadata, 'ended_at'),
                'graduated_notes' => $notes !== '' ? $notes : null,
                'searchable_archive' => true,
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$client->display_name} moved to Graduated. The dossier remains searchable.",
        ]);

        return redirect()
            ->route('clients.index', ['view' => 'graduated'])
            ->with('success', "{$client->display_name} moved to Graduated. The dossier remains searchable.");
    }

    public function destroy(Request $request, Client $client, AuditTrail $auditTrail): RedirectResponse
    {
        $client->loadMissing('providerAccounts');

        if (! $this->clientLooksLikeLead($client)) {
            throw ValidationException::withMessages([
                'client' => 'Only leads can be deleted from the roster. Fire clients instead.',
            ]);
        }

        $clientName = $client->display_name ?: $client->cuid;
        $clientId = $client->getKey();

        DB::transaction(function () use ($request, $client, $clientName, $clientId, $auditTrail): void {
            $auditTrail->record(
                $request->user(),
                'client.lead_deleted',
                "Deleted lead {$clientName} from the roster.",
                $client,
                [
                    'client_id' => $clientId,
                    'status' => $client->status,
                ],
            );

            $client->delete();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$clientName} deleted from Leads.",
        ]);

        $redirectRoute = $request->string('return_to')->toString() === 'inbox'
            ? 'inbox.index'
            : 'clients.index';
        $redirectParameters = $redirectRoute === 'clients.index' ? ['view' => 'leads'] : [];

        return redirect()
            ->route($redirectRoute, $redirectParameters)
            ->with('success', "{$clientName} deleted from Leads.");
    }

    public function show(
        Request $request,
        Client $client,
        CreditReportComparisonService $comparisonService,
        CreditsoftAiRegistry $registry,
        BrowserCompanionBundle $browserCompanion,
        ClientScoreTimeline $scoreTimeline,
        BrowserCaptureCleanupService $browserCaptureCleanup,
        SmartCreditCaptureParser $smartCreditCaptureParser,
        LicenseStateService $licenseState,
        OfficeGrowthRuntime $growth,
        ClientHealthSignalService $clientHealth,
        DisputePlanPresenter $disputePlans,
    ): Response {
        $client->load([
            'assignedUser',
            'billingProfile',
            'reportingCycles.bureauSnapshots.tradelines',
            'providerAccounts',
            'notes' => fn ($query) => $query->latest()->limit(5),
            'briefs' => fn ($query) => $query->latest()->limit(3),
            'letters' => fn ($query) => $query->latest()->limit(3),
            'violations' => fn ($query) => $query->latest()->limit(6),
            'tasks' => fn ($query) => $query->latest('due_at')->limit(6),
            'portalEvents' => fn ($query) => $query->latest('occurred_at')->latest()->limit(8),
            'profileSnapshots' => fn ($query) => $query->latest('recorded_at')->limit(10),
            'sopRuns.template',
            'browserCaptures' => fn ($query) => $query->latest('imported_at')->limit(5),
            'documents' => fn ($query) => $query->with('reportingCycle')->latest('uploaded_at')->limit(50),
        ]);

        $latestCycle = $client->reportingCycles->sortByDesc('started_at')->first();
        $latestThreeBureauCapture = $client->browserCaptures()
            ->where('metadata->smartcredit->profile', 'three_bureau_report')
            ->latest('imported_at')
            ->first();
        $reviewMetadata = is_array($latestCycle?->review_metadata) ? $latestCycle->review_metadata : [];
        $clientPayload = $client->toArray();
        $ssn = trim((string) ($client->ssn ?? ''));
        $clientPayload['date_of_birth'] = optional($client->date_of_birth)?->toDateString();
        $clientPayload['ssn_last_four'] = $ssn !== '' ? Str::substr($ssn, -4) : null;
        unset($clientPayload['ssn']);
        $user = $request->user();
        $canViewCustomerDocuments = $user
            && ! $user->isReadOnlyDemo()
            && $user->hasAnyRole(['owner_admin', 'admin', 'manager', 'case_manager']);
        $documentRecords = $client->documents;
        $availableDocumentRecords = $documentRecords
            ->filter(fn ($document): bool => filled($document->file_path) && File::exists($document->file_path))
            ->values();
        $reportDocumentCount = $availableDocumentRecords
            ->filter(function ($document): bool {
                $category = Str::lower((string) $document->category);
                $text = Str::lower(implode(' ', array_filter([
                    $document->title,
                    $document->file_name,
                    $document->notes,
                    $document->reportingCycle?->cycle_label,
                ])));

                return in_array($category, ['audit_report', 'credit_report', 'credit_reports', 'credit_report_pdf', 'progress', 'progress_report', 'client_progress'], true)
                    || Str::contains($text, ['audit report', 'credit report', '3-bureau', '3 bureau', 'progress report', 'client progress']);
            })
            ->count();
        $documentFileCount = $availableDocumentRecords->count();
        $documentTotalBytes = (int) $availableDocumentRecords->sum(fn ($document): int => max((int) ($document->file_size ?? 0), 0));
        $healthSignal = $clientHealth->signal($client);
        $importAudit = $this->clientImportAudit($client, $this->disputeFoxCaptureSourceIndex());
        $clientPayload['client_health'] = $healthSignal;
        $clientPayload['billing_signal'] = $healthSignal;
        $clientPayload['document_access'] = [
            'can_view_files' => $canViewCustomerDocuments,
            'document_count' => $availableDocumentRecords->count(),
            'file_count' => $documentFileCount,
            'report_count' => $reportDocumentCount,
            'client_file_count' => max($availableDocumentRecords->count() - $reportDocumentCount, 0),
            'total_bytes' => $documentTotalBytes,
            'total_label' => $this->humanBytes($documentTotalBytes),
        ];
        $clientPayload['import_audit'] = $importAudit;
        $clientPayload['source_kind'] = $importAudit['source_kind'];
        $clientPayload['browser_captures'] = $client->browserCaptures->map(fn ($capture) => [
            'id' => $capture->getKey(),
            'reporting_cycle_id' => $capture->reporting_cycle_id,
            'source_type' => $capture->source_type,
            'browser_name' => $capture->browser_name,
            'page_title' => $capture->page_title,
            'page_url' => $capture->page_url,
            'file_name' => $capture->file_name,
            'archive_format' => $capture->archive_format,
            'content_html' => $capture->content_html,
            'extracted_text' => $capture->extracted_text,
            'metadata' => $this->hydrateBrowserCaptureMetadata($capture->metadata ?? [], $capture->content_html, $capture->page_title, $capture->page_url, $smartCreditCaptureParser),
            'imported_at' => optional($capture->imported_at)?->toIso8601String(),
        ])->values()->all();
        $clientPayload['documents'] = $canViewCustomerDocuments ? $availableDocumentRecords->map(function ($document) use ($client): array {
            $fileAvailable = filled($document->file_path) && File::exists($document->file_path);

            return [
                'id' => $document->getKey(),
                'title' => $document->title,
                'category' => $document->category,
                'notes' => $document->notes,
                'file_name' => $document->file_name,
                'mime_type' => $document->mime_type,
                'file_size' => $document->file_size,
                'uploaded_at' => optional($document->uploaded_at)?->toIso8601String(),
                'reporting_cycle_id' => $document->reporting_cycle_id,
                'reporting_cycle' => $document->reportingCycle?->cycle_label,
                'file_available' => $fileAvailable,
                'download_url' => $fileAvailable
                    ? route('clients.documents.download', [$client, $document])
                    : null,
            ];
        })->values()->all() : [];
        $clientPayload['portal_events'] = $client->portalEvents->map(fn ($event) => [
            'id' => $event->getKey(),
            'source' => $event->source,
            'event_type' => $event->event_type,
            'tool_key' => $event->tool_key,
            'title' => $event->title,
            'summary' => $event->summary,
            'message' => $event->message,
            'score' => $event->score,
            'status' => $event->status,
            'occurred_at' => optional($event->occurred_at)?->toIso8601String(),
        ])->values()->all();
        $clientPayload['profile_snapshots'] = $client->profileSnapshots
            ->map(fn ($snapshot): array => [
                'id' => $snapshot->getKey(),
                'client_cuid' => $snapshot->client_cuid,
                'source' => $snapshot->source,
                'source_label' => $snapshot->source_label,
                'is_current' => $snapshot->is_current,
                'recorded_at' => optional($snapshot->recorded_at)?->toIso8601String(),
                'mailing_label' => $snapshot->mailing_label,
                'mailing_barcode' => $snapshot->mailing_barcode,
                'mailing_barcode_symbology' => $snapshot->mailing_barcode_symbology,
                'address_fingerprint' => $snapshot->address_fingerprint,
                'changed_fields' => $snapshot->changed_fields ?? [],
            ])
            ->values()
            ->all();

        return Inertia::render('clients/Show', [
            'client' => $clientPayload,
            'cycles' => $client->reportingCycles->map(fn ($cycle) => [
                'id' => $cycle->getKey(),
                'cycle_label' => $cycle->cycle_label,
                'started_at' => optional($cycle->started_at)->toDateString(),
                'reviewed_at' => optional($cycle->reviewed_at)?->toDateTimeString(),
                'snapshot_count' => $cycle->bureauSnapshots->count(),
            ])->values(),
            'latestSummary' => $latestCycle ? $comparisonService->reviewSummary($latestCycle) : null,
            'sopTemplates' => SopTemplate::query()->where('is_active', true)->get(),
            'browserCaptureTask' => collect($registry->catalog()['tasks'] ?? [])->firstWhere('key', 'browser_intake'),
            'browserCompanion' => [
                ...$browserCompanion->summary(),
                'enabled' => $licenseState->allows('browser_companion'),
                'download_url' => $licenseState->allows('browser_companion')
                    ? route('browser-companion.download')
                    : null,
            ],
            'browserCaptureDuplicates' => $browserCaptureCleanup->duplicateSummary($client),
            'scoreTimeline' => $scoreTimeline->build($client),
            'reviewState' => [
                'cycle_id' => $latestCycle?->getKey(),
                'reviewed_signatures' => collect($reviewMetadata['reviewed_signatures'] ?? [])->filter()->values()->all(),
                'dispute_signatures' => collect($reviewMetadata['dispute_signatures'] ?? [])->filter()->values()->all(),
                'total_rows' => (int) data_get($latestThreeBureauCapture?->metadata, 'smartcredit.account_matrix_count', 0),
            ],
            'relationship' => [
                'can_end' => in_array((string) $client->status, ['lead', 'intake', 'active', 'active_review', 'at_risk', 'monitoring'], true),
                'can_delete_lead' => $this->clientLooksLikeLead($client),
                'ended_at' => data_get($client->metadata, 'ended_at'),
                'ended_reason' => data_get($client->metadata, 'ended_reason'),
                'ended_notes' => data_get($client->metadata, 'ended_notes'),
                'reason_options' => $this->relationshipReasonOptions(),
            ],
            'creditReasonOptions' => $growth->creditReasons(),
            'providers' => $client->providerAccounts->map(fn ($provider) => [
                'id' => $provider->getKey(),
                'provider_key' => $provider->provider_key,
                'provider_label' => $provider->provider_label,
                'login_email' => $provider->login_email,
                'login_username' => $provider->login_username,
                'status' => $provider->status,
                'last_imported_at' => optional($provider->last_imported_at)?->toDateTimeString(),
                'notes' => $provider->notes,
                'has_stored_password' => $provider->hasStoredPassword(),
                'has_stored_security_answer' => $provider->hasStoredSecurityAnswer(),
                'metadata' => $provider->metadata,
                'credential_health' => $this->providerCredentialHealth($provider),
            ])->values(),
            'providerCatalog' => collect(config('creditsoft.client_providers.catalog', []))->values(),
            'providerStatuses' => collect(config('creditsoft.client_providers.statuses', []))->values(),
            'disputePlanCatalog' => $disputePlans->catalog(),
            'disputePlan' => $disputePlans->activeFor($client),
        ]);
    }

    /**
     * @return array{
     *     blocked:bool,
     *     invalidated_at:?string,
     *     invalidated_reason:?string,
     *     last_updated_at:?string,
     *     login_updated_at:?string,
     *     password_updated_at:?string,
     *     security_answer_updated_at:?string,
     *     history:list<array<string, mixed>>
     * }
     */
    protected function providerCredentialHealth(ClientProviderAccount $provider): array
    {
        $metadata = $provider->metadata ?? [];
        $lastEvent = data_get($metadata, 'credentials.last_event', []);
        $legacyInvalid = data_get($metadata, 'companion.credentials.invalid')
            ?: data_get($metadata, 'smartcredit.invalid_credentials')
            ?: [];
        $lastStatusEvent = data_get($metadata, 'companion.last_status_event', []);
        $history = data_get($metadata, 'credentials.history', []);

        if (! is_array($lastEvent)) {
            $lastEvent = [];
        }

        if (! is_array($legacyInvalid)) {
            $legacyInvalid = [];
        }

        if (! is_array($lastStatusEvent)) {
            $lastStatusEvent = [];
        }

        if (! is_array($history)) {
            $history = [];
        }

        $lastInvalidEvent = data_get($lastEvent, 'event') === 'invalid_credentials' ? $lastEvent : [];
        $invalidatedAt = data_get($metadata, 'credentials.invalidated_at')
            ?: data_get($lastInvalidEvent, 'occurred_at')
            ?: data_get($legacyInvalid, 'detected_at')
            ?: data_get($lastStatusEvent, 'detected_at');
        $invalidatedReason = data_get($metadata, 'credentials.invalidated_reason')
            ?: data_get($lastInvalidEvent, 'reason')
            ?: data_get($legacyInvalid, 'reason')
            ?: data_get($lastStatusEvent, 'reason');

        return [
            'blocked' => in_array((string) $provider->status, ['needs_credentials', 'blocked', 'disconnected'], true),
            'invalidated_at' => $invalidatedAt ?: null,
            'invalidated_reason' => $invalidatedReason ?: null,
            'last_updated_at' => data_get($metadata, 'credentials.last_updated_at'),
            'login_updated_at' => data_get($metadata, 'credentials.login_updated_at'),
            'password_updated_at' => data_get($metadata, 'credentials.password_updated_at'),
            'security_answer_updated_at' => data_get($metadata, 'credentials.security_answer_updated_at'),
            'history' => collect($history)
                ->filter(fn ($entry): bool => is_array($entry))
                ->map(fn (array $entry): array => Arr::only($entry, [
                    'event',
                    'occurred_at',
                    'source',
                    'reason',
                    'login_changed',
                    'login_saved',
                    'password_saved',
                    'security_answer_saved',
                    'worker_id',
                ]))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $submitted
     * @param  list<array{label:string,key:string,type:string,target:string,required:bool}>  $fields
     * @return array<string, string|bool>
     */
    protected function sanitizeCrmValues(array $submitted, array $fields): array
    {
        $allowedFields = collect($fields)->keyBy('key');

        return collect($submitted)
            ->mapWithKeys(function ($value, $key) use ($allowedFields): array {
                $field = $allowedFields->get((string) $key);

                if (! $field) {
                    return [];
                }

                if (($field['type'] ?? 'text') === 'checkbox') {
                    return [(string) $key => (bool) $value];
                }

                $normalized = trim((string) $value);

                return $normalized !== '' ? [(string) $key => $normalized] : [];
            })
            ->all();
    }

    public function endRelationship(Request $request, Client $client, AuditTrail $auditTrail): RedirectResponse
    {
        $reasonOptions = collect($this->relationshipReasonOptions());
        $validated = $request->validate([
            'ended_reason' => ['required', 'string', 'in:'.$reasonOptions->pluck('key')->implode(',')],
            'ended_notes' => ['nullable', 'string'],
            'ended_at' => ['required', 'date'],
        ]);

        $selectedReason = $reasonOptions->firstWhere('key', $validated['ended_reason']);
        $outcomeStatus = match ((string) $validated['ended_reason']) {
            'requested_cancellation' => 'canceled',
            'goals_met', 'no_longer_needed_help' => 'graduated',
            default => 'terminated',
        };

        $client->forceFill([
            'status' => $outcomeStatus,
            'metadata' => array_replace_recursive($client->metadata ?? [], [
                'ended_at' => $validated['ended_at'],
                'ended_reason' => $validated['ended_reason'],
                'ended_notes' => $validated['ended_notes'] ?: null,
                'engagement_outcome' => $validated['ended_reason'],
            ]),
        ])->save();

        $auditTrail->record(
            $request->user(),
            'client.relationship_ended',
            "Ended relationship for {$client->display_name} as {$validated['ended_reason']}.",
            $client,
            [
                'ended_reason' => $validated['ended_reason'],
                'ended_at' => $validated['ended_at'],
                'status' => $outcomeStatus,
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => match ($outcomeStatus) {
                'graduated' => 'Client moved to Graduated without deleting the dossier.',
                'canceled' => 'Client moved to Canceled without deleting the dossier.',
                default => 'Client marked fired/terminated without deleting the dossier.',
            },
        ]);

        return redirect()->route('clients.show', $client);
    }

    public function timeline(
        Client $client,
        AuditRetentionPolicy $auditRetentionPolicy,
        ClientHealthSignalService $clientHealth,
    ): Response {
        $retentionDays = $auditRetentionPolicy->effectiveDays();
        $restoreCutoff = now()->subDays($retentionDays);

        return Inertia::render('clients/Timeline', [
            'client' => $client,
            'clientHealth' => $clientHealth->signal($client),
            'retentionDays' => $retentionDays,
            'entries' => AuditEntry::query()
                ->where(function ($query) use ($client): void {
                    $query->where('auditable_type', $client->getMorphClass())
                        ->where('auditable_id', $client->getKey());
                })
                ->where('created_at', '>=', $restoreCutoff)
                ->with('user')
                ->latest()
                ->get()
                ->map(function (AuditEntry $entry) use ($client, $restoreCutoff) {
                    $restoreIds = $this->restorableBrowserCaptureIds($entry);
                    $restorableCount = empty($restoreIds)
                        ? 0
                        : BrowserCapture::onlyTrashed()
                            ->where('client_id', $client->getKey())
                            ->whereIn('id', $restoreIds)
                            ->count();

                    return [
                        'id' => $entry->getKey(),
                        'event' => $entry->event,
                        'summary' => $entry->summary,
                        'created_at' => optional($entry->created_at)?->toIso8601String(),
                        'user' => $entry->user ? ['name' => $entry->user->name] : null,
                        'can_restore' => $entry->created_at?->greaterThanOrEqualTo($restoreCutoff) && $restorableCount > 0,
                        'restore_label' => count($restoreIds) > 1 ? 'Restore captures' : 'Restore capture',
                    ];
                })
                ->values(),
        ]);
    }

    /**
     * @return list<int>
     */
    protected function restorableBrowserCaptureIds(AuditEntry $entry): array
    {
        return match ($entry->event) {
            'browser_capture.deleted' => collect([data_get($entry->context, 'capture_id')])
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
            'browser_capture.pruned_duplicates' => collect(data_get($entry->context, 'capture_ids', []))
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    protected function hydrateBrowserCaptureMetadata(
        array $metadata,
        ?string $html,
        ?string $pageTitle,
        ?string $pageUrl,
        SmartCreditCaptureParser $smartCreditCaptureParser,
    ): array {
        $providerKey = $metadata['provider_key'] ?? data_get($metadata, 'provider_capture.provider') ?? data_get($metadata, 'smartcredit.provider');

        if ($providerKey !== 'smartcredit' || ! is_string($html) || trim($html) === '') {
            return $metadata;
        }

        $missingSmartCreditBreakdown = empty(data_get($metadata, 'smartcredit.bureau_scores'))
            || empty(data_get($metadata, 'smartcredit.account_matrix'));
        $truncatedSmartCreditMatrix = (int) data_get($metadata, 'smartcredit.account_matrix_count', 0) > count(data_get($metadata, 'smartcredit.account_matrix', []));

        if (! $missingSmartCreditBreakdown && ! $truncatedSmartCreditMatrix) {
            return $metadata;
        }

        $parsed = $smartCreditCaptureParser->parse($html, $pageTitle, $pageUrl);

        if (! is_array($parsed) || ($parsed['provider'] ?? null) !== 'smartcredit') {
            return $metadata;
        }

        $metadata['smartcredit'] = array_replace_recursive($metadata['smartcredit'] ?? [], $parsed);
        $metadata['provider_capture'] = array_replace_recursive($metadata['provider_capture'] ?? [], $parsed);
        $metadata['import_profile'] = $parsed['profile'] ?? ($metadata['import_profile'] ?? null);

        return $metadata;
    }
}
