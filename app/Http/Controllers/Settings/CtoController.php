<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\AuditTrail;
use App\Services\CreditsoftAiService;
use App\Services\CtoAdvisorActionService;
use App\Services\CreditsoftClusterDiagnosticsService;
use App\Services\CreditsoftSystemDiagnosticsService;
use App\Services\OfficeBackupFilesystemSettingsService;
use App\Services\PublicInternetSpeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Inertia\Inertia;
use Inertia\Response;

class CtoController extends Controller
{
    public function edit(
        CreditsoftSystemDiagnosticsService $diagnostics,
        CreditsoftClusterDiagnosticsService $clusterDiagnostics,
        PublicInternetSpeedService $publicSpeed,
    ): Response {
        $diagnostics->ensureFreshSnapshot();

        return Inertia::render('settings/Cto', [
            'diagnostics' => [
                'current' => $diagnostics->current(),
                'history' => $diagnostics->history(),
                'cluster' => $clusterDiagnostics->overview(),
            ],
            'public_speed' => $publicSpeed->summary(),
        ]);
    }

    public function updatePublicSpeed(Request $request, PublicInternetSpeedService $publicSpeed): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:fast,speedtest'],
            'download_mbps' => ['required', 'numeric', 'min:0', 'max:100000'],
            'upload_mbps' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'latency_ms' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        $publicSpeed->save((string) $validated['provider'], $validated);

        return back()->with('status', 'Internet speed result saved.');
    }

    public function performanceRecommendations(
        Request $request,
        CreditsoftSystemDiagnosticsService $diagnostics,
        CreditsoftClusterDiagnosticsService $clusterDiagnostics,
        PublicInternetSpeedService $publicSpeed,
        CreditsoftAiService $ai,
        AuditTrail $auditTrail,
    ): JsonResponse {
        $diagnostics->ensureFreshSnapshot();

        $context = $this->performanceRecommendationContext(
            $diagnostics->current(),
            $diagnostics->history(),
            $clusterDiagnostics->overview(),
            $publicSpeed->summary(),
        );

        try {
            $result = $ai->generateCtoPerformanceRecommendations($context);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $auditTrail->record(
            $request->user(),
            'cto.performance_recommendations.generated',
            'CTO performance recommendations generated via OpenRouter.',
            null,
            [
                'provider' => $result['meta']['provider'] ?? 'openrouter_creditsoft',
                'model' => $result['meta']['model'] ?? null,
                'bottleneck' => $result['bottleneck'] ?? null,
                'recommendation_count' => count($result['recommendations'] ?? []),
            ],
        );

        return response()->json($result);
    }

    public function performanceAction(
        Request $request,
        CreditsoftClusterDiagnosticsService $clusterDiagnostics,
        OfficeBackupFilesystemSettingsService $filesystemSettings,
        CtoAdvisorActionService $advisorActions,
        AuditTrail $auditTrail,
    ): JsonResponse {
        $validated = $request->validate([
            'action' => ['required', 'in:memory_saver_profile,prefer_healthy_node,ram_action_note'],
            'target_base_url' => ['nullable', 'string', 'max:1024'],
            'target_label' => ['nullable', 'string', 'max:120'],
        ]);

        $action = (string) $validated['action'];
        $cluster = $clusterDiagnostics->overview();
        $peerResults = [];

        if ($action === 'prefer_healthy_node') {
            $preferredNode = $this->healthiestNode($cluster);

            if (! $preferredNode) {
                return response()->json([
                    'message' => 'No online cluster node was available to prefer.',
                ], 422);
            }

            $payload = $this->routerPreferencePayload($preferredNode);
            $result = $advisorActions->applyLocal($action, $payload);
            $peerResults = $this->broadcastPeerAction($filesystemSettings, $cluster, $action, $payload);
        } else {
            $targetNode = $this->targetNode(
                $cluster,
                (string) ($validated['target_base_url'] ?? ''),
                (string) ($validated['target_label'] ?? ''),
            );
            $payload = [
                'target_label' => (string) (
                    $targetNode['label']
                    ?? $validated['target_label']
                    ?? data_get($cluster, 'office_label', 'this office node')
                ),
            ];

            if (($targetNode['source'] ?? 'local') === 'peer') {
                $result = $this->postPeerAction($filesystemSettings, $targetNode, $action, $payload);
            } else {
                $result = $advisorActions->applyLocal($action, $payload);
            }
        }

        $auditTrail->record(
            $request->user(),
            'cto.performance_action.applied',
            'CTO performance action requested.',
            null,
            [
                'action' => $action,
                'ok' => (bool) data_get($result, 'ok', false),
                'target_label' => data_get($result, 'target_label') ?? data_get($result, 'preferred_label'),
                'peer_result_count' => count($peerResults),
            ],
        );

        return response()->json([
            ...$result,
            'peer_results' => $peerResults,
        ]);
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $history
     * @param  array<string, mixed>  $cluster
     * @param  array<string, mixed>  $publicSpeed
     * @return array<string, mixed>
     */
    protected function performanceRecommendationContext(
        array $current,
        array $history,
        array $cluster,
        array $publicSpeed,
    ): array {
        return [
            'current_node' => [
                'office_label' => data_get($current, 'machine.office_label'),
                'os_family' => data_get($current, 'machine.os_family'),
                'architecture' => data_get($current, 'machine.architecture'),
                'cpu_cores' => data_get($current, 'machine.cpu_cores'),
                'php_version' => data_get($current, 'machine.php_version'),
                'app_version' => data_get($current, 'machine.app_version'),
                'database_driver' => data_get($current, 'machine.database_driver'),
                'database_version' => data_get($current, 'machine.database_version'),
                'opcache_status' => data_get($current, 'machine.opcache_status'),
                'load' => data_get($current, 'load'),
                'memory' => data_get($current, 'memory'),
                'swap' => data_get($current, 'swap'),
                'disk' => data_get($current, 'disk'),
                'network' => data_get($current, 'network'),
                'database_size' => data_get($current, 'storage.database.size_label'),
                'document_count' => data_get($current, 'storage.documents.count'),
                'client_storage' => data_get($current, 'client_storage'),
                'memory_interpretation' => $this->memoryInterpretation($current),
            ],
            'advisor_rules' => [
                'apple_silicon_memory' => 'For macOS or Apple Silicon nodes, do not recommend adding RAM from high used memory alone. Treat memory_pressure, available bytes, swap used, swapins, and swapouts as the evidence.',
                'm4_pro_role_fit' => 'An M4 Pro test node is a valid secondary server node when memory pressure is healthy, swap is low, and the service is online; compare workload role fit instead of judging only against the Ryzen capacity.',
                'router_actions' => 'Prefer healthiest node may move client traffic, memory saver may reduce app/runtime footprint, and RAM action is only for sustained pressure that software cannot relieve.',
            ],
            'history_window' => [
                'label' => data_get($history, 'window_label'),
                'load_series' => data_get($history, 'load.series'),
                'memory_series' => data_get($history, 'memory.series'),
                'swap_series' => data_get($history, 'swap.series'),
                'disk_series' => data_get($history, 'disk.series'),
                'network_series' => data_get($history, 'network.series'),
            ],
            'cluster' => [
                'enabled' => data_get($cluster, 'enabled'),
                'office_label' => data_get($cluster, 'office_label'),
                'peer_count' => data_get($cluster, 'peer_count'),
                'online_count' => data_get($cluster, 'online_count'),
                'connection' => data_get($cluster, 'connection'),
                'totals' => data_get($cluster, 'totals'),
                'nodes' => collect(data_get($cluster, 'nodes', []))
                    ->map(fn (array $node): array => [
                        'label' => $node['label'] ?? null,
                        'source' => $node['source'] ?? null,
                        'online' => $node['online'] ?? false,
                        'connection' => $node['connection'] ?? null,
                        'summary' => $node['summary'] ?? null,
                        'memory_interpretation' => $this->memoryInterpretation((array) ($node['summary'] ?? [])),
                        'error' => $node['error'] ?? null,
                    ])
                    ->values()
                    ->all(),
            ],
            'public_speed' => [
                'average' => data_get($publicSpeed, 'average'),
                'fast' => data_get($publicSpeed, 'providers.fast'),
                'speedtest' => data_get($publicSpeed, 'providers.speedtest'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $cluster
     * @return array<string, mixed>|null
     */
    protected function healthiestNode(array $cluster): ?array
    {
        return collect((array) data_get($cluster, 'nodes', []))
            ->filter(fn (array $node): bool => (bool) data_get($node, 'online', false) && is_array(data_get($node, 'summary')))
            ->sortByDesc(fn (array $node): float => $this->nodeCapacityScore($node))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function nodeCapacityScore(array $node): float
    {
        $summary = (array) data_get($node, 'summary', []);
        $memoryAvailable = (float) data_get($summary, 'memory.available_bytes', data_get($summary, 'memory.free_bytes', 0));
        $memoryTotal = max((float) data_get($node, 'summary.memory.total_bytes', 0), 1.0);
        $swapUsed = (float) data_get($node, 'summary.swap.used_bytes', 0);
        $loadOne = (float) data_get($node, 'summary.load.one', 0);
        $cpuCores = max((int) data_get($node, 'summary.machine.cpu_cores', 1), 1);
        $latency = (float) data_get($node, 'connection.latency_ms', 0);
        $pressureFreePercent = data_get($summary, 'memory.pressure_free_percent');
        $availableRatio = is_numeric($pressureFreePercent)
            ? max(min(((float) $pressureFreePercent) / 100, 1.0), 0.0)
            : max(min($memoryAvailable / $memoryTotal, 1.0), 0.0);
        $healthyMacBonus = $this->isHealthyAppleSiliconMac($summary) ? 180 : 0;
        $swapPenalty = $this->isHealthyAppleSiliconMac($summary) ? 25 : 75;

        return ($availableRatio * 1000)
            + $healthyMacBonus
            - ($swapUsed / 1073741824 * $swapPenalty)
            - ($loadOne / $cpuCores * 50)
            - ($latency / 10);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function memoryInterpretation(array $summary): string
    {
        $osFamily = strtolower((string) data_get($summary, 'machine.os_family', ''));
        $architecture = strtolower((string) data_get($summary, 'machine.architecture', ''));
        $pressureLevel = strtolower((string) data_get($summary, 'memory.pressure_level', ''));
        $pressureFreePercent = data_get($summary, 'memory.pressure_free_percent');
        $swapUsed = (int) data_get($summary, 'swap.used_bytes', 0);
        $swapouts = (int) data_get($summary, 'memory.swapouts', 0);
        $isMac = str_contains($osFamily, 'mac') || in_array($architecture, ['arm64', 'aarch64'], true);

        if ($isMac && $pressureLevel === 'healthy' && $swapUsed === 0 && $swapouts === 0) {
            return 'Healthy macOS/Apple Silicon pressure: high cached or raw used memory is not a RAM-upgrade signal.';
        }

        if ($isMac && in_array($pressureLevel, ['watch', 'pressured', 'critical'], true)) {
            return 'macOS pressure is the decision point here; reduce service footprint or rebalance traffic before calling the M4 hardware undersized.';
        }

        if (is_numeric($pressureFreePercent)) {
            return 'Use available memory and pressure instead of raw used memory for this node.';
        }

        return 'Use available memory, swap, load, and latency together before choosing hardware or routing changes.';
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function isHealthyAppleSiliconMac(array $summary): bool
    {
        $osFamily = strtolower((string) data_get($summary, 'machine.os_family', ''));
        $architecture = strtolower((string) data_get($summary, 'machine.architecture', ''));
        $pressureLevel = strtolower((string) data_get($summary, 'memory.pressure_level', ''));
        $swapUsed = (int) data_get($summary, 'swap.used_bytes', 0);

        return (str_contains($osFamily, 'mac') || in_array($architecture, ['arm64', 'aarch64'], true))
            && $pressureLevel === 'healthy'
            && $swapUsed === 0;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    protected function routerPreferencePayload(array $node): array
    {
        return [
            'preferred_label' => (string) data_get($node, 'label', 'CreditSoft node'),
            'preferred_base_url' => (string) data_get($node, 'base_url', ''),
            'preferred_api_base_url' => $this->apiBaseUrl((string) data_get($node, 'base_url', '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $cluster
     * @return array<string, mixed>|null
     */
    protected function targetNode(array $cluster, string $targetBaseUrl, string $targetLabel): ?array
    {
        $targetBaseUrl = rtrim($targetBaseUrl, '/');
        $targetLabel = trim($targetLabel);

        return collect((array) data_get($cluster, 'nodes', []))
            ->first(function (array $node) use ($targetBaseUrl, $targetLabel): bool {
                $baseUrl = rtrim((string) data_get($node, 'base_url', ''), '/');
                $label = trim((string) data_get($node, 'label', ''));

                return ($targetBaseUrl !== '' && $baseUrl === $targetBaseUrl)
                    || ($targetLabel !== '' && strcasecmp($label, $targetLabel) === 0);
            });
    }

    /**
     * @param  array<string, mixed>  $cluster
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    protected function broadcastPeerAction(
        OfficeBackupFilesystemSettingsService $filesystemSettings,
        array $cluster,
        string $action,
        array $payload,
    ): array {
        return collect((array) data_get($cluster, 'nodes', []))
            ->filter(fn (array $node): bool => ($node['source'] ?? '') === 'peer' && (bool) data_get($node, 'online', false))
            ->map(fn (array $node): array => $this->postPeerAction($filesystemSettings, $node, $action, $payload))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function postPeerAction(
        OfficeBackupFilesystemSettingsService $filesystemSettings,
        array $node,
        string $action,
        array $payload,
    ): array {
        $stored = $filesystemSettings->stored();
        $sharedSecret = (string) data_get($stored, 'cluster.shared_secret', '');
        $baseUrl = rtrim((string) data_get($node, 'base_url', ''), '/');

        if ($sharedSecret === '' || $baseUrl === '') {
            return [
                'ok' => false,
                'action' => $action,
                'target_label' => (string) data_get($node, 'label', 'Cluster peer'),
                'message' => 'Cluster shared secret or target URL is missing.',
            ];
        }

        try {
            $response = Http::timeout(12)
                ->acceptJson()
                ->withHeaders([
                    'X-Creditsoft-Cluster-Secret' => $sharedSecret,
                    'X-Creditsoft-Cluster-License' => (string) data_get($node, 'license_key', ''),
                ])
                ->post($baseUrl.'/api/v1/cluster-cto-actions/apply', [
                    'action' => $action,
                    ...$payload,
                ]);

            if ($response->ok() && is_array($response->json('data'))) {
                return [
                    ...$response->json('data'),
                    'target_label' => (string) data_get($response->json('data'), 'target_label', data_get($node, 'label', 'Cluster peer')),
                ];
            }

            return [
                'ok' => false,
                'action' => $action,
                'target_label' => (string) data_get($node, 'label', 'Cluster peer'),
                'message' => (string) ($response->json('message') ?? 'Peer did not accept the CTO action.'),
                'status' => $response->status(),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'action' => $action,
                'target_label' => (string) data_get($node, 'label', 'Cluster peer'),
                'message' => $exception->getMessage(),
            ];
        }
    }

    protected function apiBaseUrl(string $value): string
    {
        $parts = parse_url($value);

        if (! is_array($parts) || blank($parts['scheme'] ?? null) || blank($parts['host'] ?? null)) {
            return '';
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = rtrim((string) ($parts['path'] ?? ''), '/');

        if ($path === '' || $path === '/') {
            $path = '/api/v1';
        } elseif ($path === '/api') {
            $path = '/api/v1';
        } elseif (! str_ends_with($path, '/api/v1')) {
            $path .= '/api/v1';
        }

        return "{$scheme}://{$host}{$port}{$path}";
    }
}
