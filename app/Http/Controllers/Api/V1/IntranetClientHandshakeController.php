<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserApiKey;
use App\Services\ConnectivityLaneService;
use App\Services\CreditsoftApiAccess;
use App\Services\LicenseStateService;
use App\Services\OfficeBackupFilesystemSettingsService;
use App\Services\TailscaleStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntranetClientHandshakeController extends Controller
{
    public function __invoke(
        Request $request,
        CreditsoftApiAccess $apiAccess,
        TailscaleStatusService $tailscaleStatus,
        ConnectivityLaneService $laneService,
        LicenseStateService $licenseState,
        OfficeBackupFilesystemSettingsService $backupSettings,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user, 403);

        /** @var UserApiKey|null $apiKey */
        $apiKey = $request->attributes->get('creditsoft_api_key');
        $detectedTailscale = $tailscaleStatus->current();
        $apiUrls = $laneService->apiUrls($request, $apiAccess, $detectedTailscale);
        $portalUrls = $laneService->portalUrls($request, $apiAccess, $detectedTailscale);
        $license = $licenseState->current();
        $backup = $backupSettings->load();

        return response()->json([
            'data' => [
                'name' => 'CreditSoft Intranet Client Handshake',
                'status' => 'ok',
                'generated_at' => now()->toIso8601String(),
                'user' => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->assignedRoleNames(),
                    'role_labels' => $user->assignedRoleLabels(),
                    'can_manage_users' => $user->canManageUsers(),
                    'can_view_user_directory' => $user->canViewUserDirectory(),
                    'can_edit_users' => $user->canEditUsers(),
                    'can_access_ops_panel' => $user->canAccessOpsPanel(),
                ],
                'api_key' => $apiKey ? [
                    'id' => $apiKey->getKey(),
                    'name' => $apiKey->name,
                    'masked_token' => $apiKey->masked_token,
                    'abilities' => array_values((array) ($apiKey->abilities ?? [])),
                    'last_used_at' => optional($apiKey->last_used_at)?->toIso8601String(),
                    'created_at' => optional($apiKey->created_at)?->toIso8601String(),
                ] : null,
                'license' => [
                    'access_state' => (string) ($license['access_state'] ?? 'pending'),
                    'can_access_workspace' => (bool) ($license['can_access_workspace'] ?? true),
                    'plan_key' => $license['plan_key'] ?? null,
                    'plan_label' => $license['plan_label'] ?? $license['plan'] ?? null,
                    'features' => (array) ($license['features'] ?? []),
                    'message' => (string) ($license['message'] ?? ''),
                ],
                'lanes' => [
                    'api' => [
                        ...$apiUrls,
                        'dashboard_urls' => $this->dashboardUrls($apiUrls),
                    ],
                    'portal' => [
                        ...$portalUrls,
                        'dashboard_urls' => $this->dashboardUrls($portalUrls),
                    ],
                ],
                'tunnels' => [
                    'tailscale' => $detectedTailscale,
                    'ngrok' => [
                        'enabled' => $apiAccess->ngrokEnabled(),
                        'api_only' => $apiAccess->ngrokApiOnly(),
                        'public_base_url' => $apiAccess->ngrokPublicBaseUrl(),
                    ],
                ],
                'pwa' => [
                    'manifest_url' => url('/manifest.webmanifest?v=3'),
                    'start_path' => '/dashboard?source=intranet-client',
                    'service_worker_url' => url('/sw.js?v=4'),
                    'installable_origins' => [
                        'http://127.0.0.1',
                        'http://localhost',
                        'https://*',
                    ],
                ],
                'integrations' => [
                    'crm' => [
                        'enabled' => (bool) config('creditsoft.integrations.crm.enabled', false),
                        'configured' => filled((string) config('creditsoft.integrations.crm.base_url', '')),
                        'base_url' => $this->nullableString(config('creditsoft.integrations.crm.base_url')),
                        'mode' => 'sidecar',
                    ],
                ],
                'backups' => [
                    'archive_destination' => (string) data_get($backup, 'archive_destination', 'local'),
                    'external_handoff_lane' => (string) data_get($backup, 'external_handoff_lane', 'none'),
                    'cluster' => $this->clusterSummary((array) data_get($backup, 'cluster', [])),
                ],
                'next_actions' => [
                    'Use preferred API lane for API calls.',
                    'Open the matching dashboard URL for the user interface.',
                    'Install the PWA only from localhost or HTTPS origins.',
                    'Use the CRM as a sidecar window until sync is explicitly enabled.',
                ],
            ],
        ]);
    }

    /**
     * @param array{local_base_url:string,public_base_url?:?string,tailnet_base_url?:?string,preferred_base_url:string} $lanes
     * @return array{local:string,public:?string,tailnet:?string,preferred:string}
     */
    protected function dashboardUrls(array $lanes): array
    {
        return [
            'local' => $this->dashboardUrl((string) $lanes['local_base_url']),
            'public' => $this->dashboardUrl($lanes['public_base_url'] ?? null),
            'tailnet' => $this->dashboardUrl($lanes['tailnet_base_url'] ?? null),
            'preferred' => $this->dashboardUrl((string) $lanes['preferred_base_url']),
        ];
    }

    protected function dashboardUrl(?string $baseUrl): ?string
    {
        $origin = $this->originFromBaseUrl($baseUrl);

        return $origin ? rtrim($origin, '/').'/dashboard?source=intranet-client' : null;
    }

    protected function originFromBaseUrl(?string $baseUrl): ?string
    {
        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            return null;
        }

        $parts = parse_url($baseUrl);

        if (! is_array($parts) || blank($parts['scheme'] ?? null) || blank($parts['host'] ?? null)) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return "{$scheme}://{$host}{$port}";
    }

    protected function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @param array<string, mixed> $cluster
     * @return array<string, mixed>
     */
    protected function clusterSummary(array $cluster): array
    {
        $peers = collect((array) data_get($cluster, 'peers', []))
            ->filter(fn (mixed $peer): bool => is_array($peer))
            ->values();
        $enabledPeers = $peers
            ->filter(fn (array $peer): bool => (bool) data_get($peer, 'enabled', true) && filled((string) data_get($peer, 'base_url', '')))
            ->values();

        return [
            'enabled' => (bool) data_get($cluster, 'enabled', false),
            'office_label' => (string) data_get($cluster, 'office_label', 'CreditSoft Office'),
            'has_shared_secret' => (bool) data_get($cluster, 'has_shared_secret', false),
            'peer_count' => $peers->count(),
            'enabled_peer_count' => $enabledPeers->count(),
            'peers' => $enabledPeers
                ->map(fn (array $peer): array => [
                    'label' => (string) data_get($peer, 'label', ''),
                    'base_url' => (string) data_get($peer, 'base_url', ''),
                ])
                ->all(),
        ];
    }
}
