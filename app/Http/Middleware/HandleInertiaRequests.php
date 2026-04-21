<?php

namespace App\Http\Middleware;

use App\Models\Client;
use App\Models\Task;
use App\Models\ViolationCandidate;
use App\Services\CreditsoftStorageHealthService;
use App\Services\CreditsoftUpdateFeed;
use App\Services\InstallerState;
use App\Services\LicenseStateService;
use App\Services\OfficeBackupFilesystemSettingsService;
use App\Services\OperationalReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $licenseState = app(LicenseStateService::class);
        $updateFeed = app(CreditsoftUpdateFeed::class);
        $backupFilesystemSettings = app(OfficeBackupFilesystemSettingsService::class)->load();
        $storageHealth = app(CreditsoftStorageHealthService::class)->current();
        $operationalReminders = app(OperationalReminderService::class);
        $installerState = app(InstallerState::class)->read();
        $loginAccounts = collect(config('creditsoft.access.login_accounts', []))
            ->filter(fn (array $account) => (bool) ($account['readonly'] ?? false))
            ->map(fn (array $account) => [
                'label' => $account['label'] ?? str((string) ($account['role'] ?? ''))->replace('_', ' ')->title()->value(),
                'email' => $account['email'] ?? null,
                'password' => $account['password'] ?? null,
                'readonly' => (bool) ($account['readonly'] ?? false),
                'description' => $account['description'] ?? null,
            ])
            ->values()
            ->all();

        $badgeCounts = $user
            ? [
                'clients' => Client::query()->count(),
                'inbox' => $operationalReminders->activeCount(),
                'tasks' => Task::query()->whereIn('status', ['open', 'in_progress'])->count(),
                'violations' => ViolationCandidate::query()->whereIn('status', ['open', 'confirmed'])->count(),
            ]
            : [
                'clients' => 0,
                'inbox' => 0,
                'tasks' => 0,
                'violations' => 0,
            ];

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'clientNavigator' => $user ? $this->clientNavigator($request) : null,
            'auth' => [
                'user' => $user
                    ? [
                        'id' => $user->getKey(),
                        'name' => $user->name,
                        'email' => $user->email,
                        'gravatar_url' => $user->gravatar_url,
                        'last_seen_at' => optional($user->last_seen_at)?->toIso8601String(),
                        'last_login_at' => optional($user->last_login_at)?->toIso8601String(),
                    ]
                    : null,
                'role' => $user?->primaryRole(),
                'role_label' => $user?->primaryRoleLabel(),
                'roles' => $user?->assignedRoleNames() ?? [],
                'role_labels' => $user?->assignedRoleLabels() ?? [],
                'read_only_demo' => $user?->isReadOnlyDemo() ?? false,
                'can_manage_users' => $user?->canManageUsers() ?? false,
                'can_view_user_directory' => $user?->canViewUserDirectory() ?? false,
                'can_edit_users' => $user?->canEditUsers() ?? false,
            ],
            'creditsoft' => [
                'config' => [
                    'path' => (string) config('creditsoft.config_path'),
                    'files' => [],
                ],
                'license' => $licenseState->current(),
                'updates' => $updateFeed->current(),
                'ai' => [
                    'defaultProvider' => config('ai.default'),
                    'needsSetup' => false,
                    'catalog' => [
                        'default_provider' => config('ai.default'),
                        'providers' => [],
                        'tasks' => [],
                    ],
                ],
                'ui' => [
                    'review_label_style' => (string) config('creditsoft.ui.review_label_style', '10'),
                ],
                'branding' => [
                    'company_name' => data_get($installerState, 'company_name') ?: config('app.name', 'CreditSoft'),
                    'logo_name' => data_get($installerState, 'branding.logo_name'),
                    'logo_url' => data_get($installerState, 'branding.logo_url'),
                    'uploaded_at' => data_get($installerState, 'branding.uploaded_at'),
                ],
                'connectivity' => [
                    'local' => [
                        'enabled' => is_dir((string) data_get($backupFilesystemSettings, 'local.private_path'))
                            || is_writable(dirname((string) data_get($backupFilesystemSettings, 'local.private_path', storage_path('app/private')))),
                    ],
                    'tailscale' => [
                        'enabled' => (bool) config('creditsoft.tunnels.tailscale.required', false),
                    ],
                    'ngrok' => [
                        'enabled' => (bool) config('creditsoft.tunnels.ngrok.enabled', false),
                        'api_only' => (bool) config('creditsoft.tunnels.ngrok.api_only', true),
                    ],
                    'wasabi' => [
                        'enabled' => filled((string) config('filesystems.disks.wasabi.key', ''))
                            && filled((string) config('filesystems.disks.wasabi.secret', ''))
                            && filled((string) config('filesystems.disks.wasabi.bucket', '')),
                    ],
                    'dropbox' => [
                        'enabled' => (bool) data_get($backupFilesystemSettings, 'dropbox.enabled', false),
                    ],
                    'google_drive' => [
                        'enabled' => (bool) data_get($backupFilesystemSettings, 'google_drive.enabled', false),
                    ],
                ],
                'storage' => $storageHealth,
                'crm' => [
                    'enabled' => (bool) config('creditsoft.integrations.crm.enabled', false),
                    'configured' => filled((string) config('creditsoft.integrations.crm.base_url', '')),
                    'base_url' => filled((string) config('creditsoft.integrations.crm.base_url', ''))
                        ? rtrim((string) config('creditsoft.integrations.crm.base_url'), '/')
                        : null,
                    'mode' => 'sidecar',
                ],
                'badges' => [
                    'clients' => $badgeCounts['clients'],
                    'inbox' => $badgeCounts['inbox'],
                    'tasks' => $badgeCounts['tasks'],
                    'violations' => $badgeCounts['violations'],
                ],
                'access' => [
                    'login_accounts' => $loginAccounts,
                ],
            ],
        ];
    }

    /**
     * @return array{
     *     current_id:int,
     *     position:int,
     *     total:int,
     *     current_view:string,
     *     previous:?array{id:int,first_name:?string,last_name:?string,display_name:string,status:?string,status_label:string,href:string,source_kind:string,client_health:mixed},
     *     next:?array{id:int,first_name:?string,last_name:?string,display_name:string,status:?string,status_label:string,href:string,source_kind:string,client_health:mixed},
     *     options:list<array{id:int,first_name:?string,last_name:?string,display_name:string,status:?string,status_label:string,href:string,source_kind:string,client_health:mixed}>
     * }|null
     */
    protected function clientNavigator(Request $request): ?array
    {
        $routeClient = $request->route('client');

        if (! $routeClient && preg_match('#^clients/(\d+)(?:/|$)#', trim($request->path(), '/'), $matches)) {
            $routeClient = $matches[1];
        }

        if (! $routeClient instanceof Client && $routeClient) {
            $routeClient = Client::query()->find($routeClient);
        }

        if (! $routeClient instanceof Client) {
            return null;
        }

        $routeClient->loadMissing(['providerAccounts', 'billingProfile', 'payments']);
        $currentView = $this->clientNavigatorView($request, $routeClient);

        $clients = Client::query()
            ->select(['id', 'first_name', 'last_name', 'status', 'metadata'])
            ->with([
                'providerAccounts:id,client_id,login_email,login_username,login_password,status',
                'billingProfile:id,client_id,status,last_paid_at,next_due_at',
                'payments:id,client_id,status,paid_at',
            ])
            ->orderByRaw("lower(nullif(trim(concat_ws(' ', first_name, last_name)), '')) asc nulls last")
            ->orderBy('id')
            ->get()
            ->filter(
                fn (Client $client): bool => $this->clientMatchesNavigatorView($client, $currentView)
            )
            ->values();

        if ($clients->isEmpty()) {
            return null;
        }

        $currentIndex = $clients->search(
            fn (Client $client): bool => $client->getKey() === $routeClient->getKey(),
        );

        $items = $clients
            ->map(fn (Client $client): array => $this->clientNavigatorItem($client))
            ->values();

        return [
            'current_id' => $routeClient->getKey(),
            'current_view' => $currentView,
            'position' => $currentIndex === false ? 0 : $currentIndex + 1,
            'total' => $items->count(),
            'previous' => $currentIndex !== false && $currentIndex > 0 ? $items->get($currentIndex - 1) : null,
            'next' => $currentIndex !== false && $currentIndex < $items->count() - 1 ? $items->get($currentIndex + 1) : null,
            'options' => $items->all(),
        ];
    }

    protected function clientNavigatorView(Request $request, Client $routeClient): string
    {
        $requested = strtolower(trim((string) $request->query('view', '')));

        if (in_array($requested, ['clients', 'leads', 'terminated', 'fired', 'canceled', 'graduated', 'all'], true)) {
            return $requested;
        }

        return match ($this->clientNavigatorSourceKind($routeClient)) {
            'lead' => 'leads',
            'terminated' => 'terminated',
            'fired' => 'fired',
            'canceled' => 'canceled',
            'graduated' => 'graduated',
            default => 'clients',
        };
    }

    protected function clientMatchesNavigatorView(Client $client, string $view): bool
    {
        if ($view === 'all') {
            return true;
        }

        $kind = $this->clientNavigatorSourceKind($client);

        return match ($view) {
            'leads' => $kind === 'lead',
            'terminated' => $kind === 'terminated',
            'fired' => $kind === 'fired',
            'canceled' => $kind === 'canceled',
            'graduated' => $kind === 'graduated',
            default => $kind === 'client',
        };
    }

    protected function clientNavigatorSourceKind(Client $client): string
    {
        $metadata = $client->metadata ?? [];
        $status = strtolower(trim((string) $client->status));
        $reason = strtolower(trim((string) data_get($metadata, 'ended_reason', '')));
        $outcome = strtolower(trim((string) data_get($metadata, 'engagement_outcome', '')));

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
            $status === 'fired'
            || $reason === 'fired'
            || $outcome === 'fired'
            || data_get($metadata, 'fired_at') !== null
        ) {
            return 'fired';
        }

        if ($this->clientHasTerminatedSignal($client)) {
            return 'terminated';
        }

        if ($this->clientLooksLikeLead($client)) {
            return 'lead';
        }

        return 'client';
    }

    protected function clientLooksLikeLead(Client $client): bool
    {
        $metadata = $client->metadata ?? [];
        $sourceKind = strtolower(trim((string) (data_get($metadata, 'crm.source_kind') ?: data_get($metadata, 'source_kind') ?: '')));

        return strtolower(trim((string) $client->status)) === 'lead'
            || $sourceKind === 'lead'
            || data_get($metadata, 'imports.disputefox.lists.leads') !== null
            || str_contains(
                strtolower((string) data_get($metadata, 'imports.disputefox.regular_companion_sync.source_page_url', '')),
                'type=leads'
            );
    }

    protected function clientHasTerminatedSignal(Client $client): bool
    {
        $metadata = $client->metadata ?? [];
        $status = strtolower(trim((string) $client->status));
        $reason = strtolower(trim((string) data_get($metadata, 'ended_reason', '')));
        $outcome = strtolower(trim((string) data_get($metadata, 'engagement_outcome', '')));
        $rawStatus = strtolower(trim((string) data_get($metadata, 'imports.disputefox.lists.clients.raw_row.Status', '')));
        $rawClientStatus = strtolower(trim((string) data_get($metadata, 'imports.disputefox.lists.clients.raw_row.Client Status', '')));
        $rawStage = strtolower(trim(implode(' ', [
            (string) data_get($metadata, 'imports.disputefox.lists.clients.raw_row.Stage in Processs', ''),
            (string) data_get($metadata, 'imports.disputefox.lists.clients.raw_row.Stage in Process', ''),
        ])));

        if (
            $status === 'terminated'
            || in_array($reason, ['nonpayment', 'unresponsive', 'compliance_risk', 'abusive_behavior', 'other', 'terminated', 'closed', 'archived'], true)
            || in_array($outcome, ['terminated', 'closed', 'archived'], true)
            || in_array($rawStatus, ['closed', 'archived', 'terminated'], true)
            || in_array($rawClientStatus, ['closed', 'archived', 'terminated'], true)
            || str_contains($rawStage, 'closed')
            || str_contains($rawStage, 'archived')
            || str_contains($rawStage, 'terminated')
            || $this->clientIsLegacyImportedProfileWithoutActiveClientList($client)
            || $this->clientHasInactiveServiceSignal($client)
        ) {
            return true;
        }

        if (! $this->clientLooksLikeLead($client)) {
            return false;
        }

        return $this->clientHasProviderLogin($client)
            || $this->clientHasBillingSignal($client)
            || $this->clientHasStaleImportedDate($client);
    }

    protected function clientHasProviderLogin(Client $client): bool
    {
        return $client->providerAccounts->contains(
            fn ($provider): bool => filled($provider->login_email)
                || filled($provider->login_username)
                || filled($provider->login_password)
        );
    }

    protected function clientHasBillingSignal(Client $client): bool
    {
        $metadataText = json_encode($client->metadata ?? []);

        if (is_string($metadataText) && str_contains($metadataText, 'ActivePay')) {
            return true;
        }

        if (is_string($metadataText) && str_contains($metadataText, 'FailedPay')) {
            return true;
        }

        if ($client->payments->isNotEmpty()) {
            return true;
        }

        return $client->billingProfile !== null
            && (
                filled($client->billingProfile->status)
                || $client->billingProfile->last_paid_at !== null
                || $client->billingProfile->next_due_at !== null
            );
    }

    protected function clientHasStaleImportedDate(Client $client): bool
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
            } catch (\Throwable) {
                continue;
            }
        }

        return false;
    }

    protected function clientHasInactiveServiceSignal(Client $client): bool
    {
        if ($client->providerAccounts->contains(function ($provider): bool {
            $status = strtolower(trim((string) $provider->status));
            $text = strtolower(trim(($provider->notes ?? '').' '.(json_encode($provider->metadata ?? []) ?: '')));

            return in_array($status, ['needs_client_payment', 'needs_reactivation'], true)
                || str_contains($text, 'reactivation')
                || str_contains($text, 'needs_client_payment')
                || str_contains($text, 'smartcredit_reactivation');
        })) {
            return true;
        }

        $latestPaymentAt = $client->payments
            ->pluck('paid_at')
            ->filter()
            ->sortDesc()
            ->first();
        $lastPaidAt = collect([$client->billingProfile?->last_paid_at, $latestPaymentAt])
            ->filter()
            ->map(fn ($date): Carbon => $date instanceof Carbon ? $date : Carbon::parse($date))
            ->sortByDesc(fn (Carbon $date): int => $date->getTimestamp())
            ->first();
        $hasFutureDue = $client->billingProfile?->next_due_at instanceof Carbon
            && $client->billingProfile->next_due_at->gte(now()->startOfDay());

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

    /**
     * @return array{id:int,first_name:?string,last_name:?string,display_name:string,status:?string,status_label:string,href:string,source_kind:string,client_health:mixed}
     */
    protected function clientNavigatorItem(Client $client): array
    {
        $displayName = trim($client->display_name) ?: "Client {$client->getKey()}";
        $status = $client->status ? (string) $client->status : null;

        return [
            'id' => $client->getKey(),
            'first_name' => filled($client->first_name) ? (string) $client->first_name : null,
            'last_name' => filled($client->last_name) ? (string) $client->last_name : null,
            'display_name' => $displayName,
            'status' => $status,
            'status_label' => $status ? str_replace('_', ' ', $status) : 'client',
            'href' => route('clients.show', $client, false),
            'source_kind' => $this->clientNavigatorSourceKind($client),
            'client_health' => data_get($client->metadata ?? [], 'client_health'),
        ];
    }
}
