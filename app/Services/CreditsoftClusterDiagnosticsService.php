<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CreditsoftClusterDiagnosticsService
{
    public function __construct(
        protected CreditsoftSystemDiagnosticsService $diagnostics,
        protected OfficeBackupFilesystemSettingsService $filesystemSettings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $stored = $this->filesystemSettings->stored();
        $clusterEnabled = (bool) data_get($stored, 'cluster.enabled', false);
        $sharedSecret = (string) data_get($stored, 'cluster.shared_secret', '');
        $officeLabel = (string) data_get($stored, 'cluster.office_label', data_get($this->diagnostics->current(), 'machine.office_label', 'CreditSoft Office'));

        $localSummary = $this->diagnostics->clusterSummary();
        $localLabel = $this->nodeDisplayLabel($localSummary, 'local', $officeLabel);
        $nodes = [[
            'label' => $localLabel,
            'detail_label' => $this->nodeDetailLabel($localSummary, 'local'),
            'base_url' => config('app.url'),
            'license_key' => null,
            'source' => 'local',
            'online' => true,
            'error' => null,
            'summary' => $this->displaySummary($localSummary, $localLabel),
            'connection' => $this->localConnectionProbe(),
        ]];

        foreach ((array) data_get($stored, 'cluster.peers', []) as $peer) {
            if (! is_array($peer) || ! ((bool) data_get($peer, 'enabled', true))) {
                continue;
            }

            $baseUrl = rtrim((string) data_get($peer, 'base_url', ''), '/');

            if ($baseUrl === '') {
                continue;
            }

            $configuredLabel = (string) data_get($peer, 'label', 'Cluster peer');
            $node = [
                'label' => $this->nodeDisplayLabel(null, 'peer', $configuredLabel),
                'detail_label' => null,
                'base_url' => $baseUrl,
                'license_key' => (string) data_get($peer, 'license_key', ''),
                'source' => 'peer',
                'online' => false,
                'error' => 'No shared secret configured for cluster sync.',
                'summary' => null,
                'connection' => $this->offlineConnectionProbe('Not tested'),
            ];

            if ($clusterEnabled && $sharedSecret !== '') {
                try {
                    $startedAt = microtime(true);
                    $response = Http::timeout(4)
                        ->acceptJson()
                        ->withHeaders([
                            'X-Creditsoft-Cluster-Secret' => $sharedSecret,
                            'X-Creditsoft-Cluster-License' => (string) data_get($peer, 'license_key', ''),
                        ])
                        ->get($baseUrl.'/internal/diagnostics/summary');
                    $durationMs = max((microtime(true) - $startedAt) * 1000, 0.1);
                    $bandwidthProbe = $response->ok()
                        ? $this->remoteBandwidthProbe($baseUrl, $sharedSecret, (string) data_get($peer, 'license_key', ''))
                        : null;
                    $node['connection'] = $this->remoteConnectionProbe(
                        $durationMs,
                        strlen($response->body()),
                        $response->status(),
                        $bandwidthProbe,
                    );

                    if ($response->ok() && is_array($response->json('data'))) {
                        $node['online'] = true;
                        $node['error'] = null;
                        $node['summary'] = $response->json('data');
                        $node['label'] = $this->nodeDisplayLabel((array) $node['summary'], 'peer', $configuredLabel);
                        $node['detail_label'] = $this->nodeDetailLabel((array) $node['summary'], 'peer');
                        $node['summary'] = $this->displaySummary((array) $node['summary'], (string) $node['label']);
                    } else {
                        $node['error'] = (string) ($response->json('message') ?? 'Peer did not return diagnostics.');
                    }
                } catch (\Throwable $exception) {
                    $node['error'] = $this->peerErrorMessage($exception);
                    $node['connection'] = $this->offlineConnectionProbe('Timed out');
                }
            }

            $nodes[] = $node;
        }

        $nodes = $this->collapseContainerSelfNode($nodes);

        $onlineNodes = collect($nodes)
            ->filter(fn (array $node): bool => (bool) $node['online'] && is_array($node['summary']))
            ->values();

        $totals = [
            'memory_total_bytes' => $onlineNodes->sum(fn (array $node): int => (int) data_get($node, 'summary.memory.total_bytes', 0)),
            'memory_used_bytes' => $onlineNodes->sum(fn (array $node): int => (int) data_get($node, 'summary.memory.used_bytes', 0)),
            'swap_total_bytes' => $onlineNodes->sum(fn (array $node): int => (int) data_get($node, 'summary.swap.total_bytes', 0)),
            'swap_used_bytes' => $onlineNodes->sum(fn (array $node): int => (int) data_get($node, 'summary.swap.used_bytes', 0)),
            'disk_total_bytes' => $onlineNodes->sum(fn (array $node): int => (int) data_get($node, 'summary.disk.total_bytes', 0)),
            'disk_used_bytes' => $onlineNodes->sum(fn (array $node): int => (int) data_get($node, 'summary.disk.used_bytes', 0)),
            'disk_free_bytes' => $onlineNodes->sum(fn (array $node): int => (int) data_get($node, 'summary.disk.free_bytes', 0)),
            'network_rx_bytes' => $onlineNodes->sum(fn (array $node): int => (int) data_get($node, 'summary.network.rx_bytes', 0)),
            'network_tx_bytes' => $onlineNodes->sum(fn (array $node): int => (int) data_get($node, 'summary.network.tx_bytes', 0)),
        ];

        return [
            'enabled' => $clusterEnabled,
            'office_label' => (string) data_get($nodes, '0.label', 'This office node'),
            'peer_count' => max(count($nodes) - 1, 0),
            'online_count' => $onlineNodes->count(),
            'connection' => $this->connectionOverview($nodes),
            'totals' => [
                ...$totals,
                'memory_total_label' => $this->humanBytes((int) $totals['memory_total_bytes']),
                'memory_used_label' => $this->humanBytes((int) $totals['memory_used_bytes']),
                'swap_total_label' => $this->humanBytes((int) $totals['swap_total_bytes']),
                'swap_used_label' => $this->humanBytes((int) $totals['swap_used_bytes']),
                'disk_total_label' => $this->humanBytes((int) $totals['disk_total_bytes']),
                'disk_used_label' => $this->humanBytes((int) $totals['disk_used_bytes']),
                'disk_free_label' => $this->humanBytes((int) $totals['disk_free_bytes']),
                'network_rx_label' => $this->humanBytes((int) $totals['network_rx_bytes']),
                'network_tx_label' => $this->humanBytes((int) $totals['network_tx_bytes']),
            ],
            'nodes' => $nodes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function localConnectionProbe(): array
    {
        $measuredAt = $this->measuredAt();

        return [
            'tested' => true,
            'status_label' => 'Local loopback',
            'latency_ms' => 0.0,
            'latency_label' => 'Local',
            'transfer_bytes' => 0,
            'transfer_label' => 'Local',
            'throughput_bps' => 0,
            'throughput_label' => 'Loopback',
            ...$measuredAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function remoteConnectionProbe(float $durationMs, int $bytes, int $status, ?array $bandwidthProbe = null): array
    {
        $transferBytes = (int) data_get($bandwidthProbe, 'bytes', $bytes);
        $throughput = (int) data_get($bandwidthProbe, 'throughput_bytes_per_second', 0);
        $measuredAt = $this->measuredAt();

        if ($throughput <= 0) {
            $seconds = max($durationMs / 1000, 0.001);
            $throughput = $bytes > 0 ? (int) round($bytes / $seconds) : 0;
        }

        return [
            'tested' => true,
            'status_label' => 'HTTP '.$status,
            'latency_ms' => round($durationMs, 1),
            'latency_label' => number_format($durationMs, $durationMs >= 100 ? 0 : 1).' ms',
            'transfer_bytes' => $transferBytes,
            'transfer_label' => $this->humanBytes($transferBytes),
            'throughput_bps' => $throughput,
            'throughput_label' => $this->humanBitRate($throughput),
            ...$measuredAt,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function remoteBandwidthProbe(string $baseUrl, string $sharedSecret, string $licenseKey): ?array
    {
        $bytes = max((int) config('creditsoft.diagnostics.cluster_probe_bytes', 16777216), 0);

        if ($bytes <= 0) {
            return null;
        }

        $bytes = min($bytes, 64 * 1024 * 1024);

        try {
            $startedAt = microtime(true);
            $response = Http::timeout(12)
                ->withHeaders([
                    'X-Creditsoft-Cluster-Secret' => $sharedSecret,
                    'X-Creditsoft-Cluster-License' => $licenseKey,
                ])
                ->get($baseUrl.'/internal/diagnostics/bandwidth', [
                    'bytes' => $bytes,
                    'nonce' => bin2hex(random_bytes(8)),
                ]);
            $durationMs = max((microtime(true) - $startedAt) * 1000, 0.1);

            if (! $response->ok()) {
                return null;
            }

            $receivedBytes = strlen($response->body());
            $seconds = max($durationMs / 1000, 0.001);

            return [
                'bytes' => $receivedBytes,
                'duration_ms' => round($durationMs, 1),
                'throughput_bytes_per_second' => $receivedBytes > 0 ? (int) round($receivedBytes / $seconds) : 0,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function offlineConnectionProbe(string $label): array
    {
        $measuredAt = $this->measuredAt();

        return [
            'tested' => false,
            'status_label' => $label,
            'latency_ms' => null,
            'latency_label' => 'No response',
            'transfer_bytes' => 0,
            'transfer_label' => '0 B',
            'throughput_bps' => 0,
            'throughput_label' => '0 B/s',
            ...$measuredAt,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $summary
     */
    protected function nodeDisplayLabel(?array $summary, string $source, string $configuredLabel): string
    {
        $role = $source === 'local' ? 'Local' : 'Remote';

        if ($summary) {
            $platform = $this->nodePlatformLabel($summary);

            return $platform !== ''
                ? $role.' '.$platform.' node'
                : ($source === 'local' ? 'Local office node' : 'Remote office node');
        }

        $normalized = strtolower($configuredLabel);

        if (str_contains($normalized, 'mac') || str_contains($normalized, 'apple') || str_contains($normalized, 'm4')) {
            return $role.' macOS node';
        }

        if (str_contains($normalized, 'linux') || str_contains($normalized, 'ubuntu') || str_contains($normalized, 'debian') || str_contains($normalized, 'ryzen')) {
            return $role.' Linux node';
        }

        if (str_contains($normalized, 'windows')) {
            return $role.' Windows node';
        }

        if (str_contains($normalized, 'bsd')) {
            return $role.' BSD node';
        }

        return $source === 'local' ? 'This office node' : 'Remote office node';
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function nodePlatformLabel(array $summary): string
    {
        $osFamily = trim((string) data_get($summary, 'machine.os_family', ''));
        $normalized = strtolower($osFamily);

        if (str_contains($normalized, 'mac')) {
            return 'macOS';
        }

        if (str_contains($normalized, 'linux')) {
            return 'Linux';
        }

        if (str_contains($normalized, 'windows')) {
            return 'Windows';
        }

        if (str_contains($normalized, 'bsd')) {
            return 'BSD';
        }

        return $osFamily;
    }

    /**
     * @param  array<string, mixed>|null  $summary
     */
    protected function nodeDetailLabel(?array $summary, string $source): ?string
    {
        if (! $summary) {
            return null;
        }

        $osFamily = trim((string) data_get($summary, 'machine.os_family', 'Node runtime'));
        $architecture = trim((string) data_get($summary, 'machine.architecture', ''));
        $cores = (int) data_get($summary, 'machine.cpu_cores', 0);
        $memoryLabel = trim((string) data_get($summary, 'memory.total_label', ''));
        $parts = [];

        if ($osFamily !== '') {
            $parts[] = $osFamily;
        }

        if ($architecture !== '') {
            $parts[] = $architecture;
        }

        if ($cores > 0) {
            $parts[] = $cores.' core'.($cores === 1 ? '' : 's');
        }

        if ($memoryLabel !== '') {
            $parts[] = $memoryLabel.' RAM';
        }

        if (
            $source === 'local'
            && str_contains(strtolower($osFamily), 'linux')
            && (string) data_get($summary, 'machine.diagnostics_source') === 'container'
        ) {
            array_unshift($parts, 'Container runtime');
        }

        return implode(' · ', array_unique($parts));
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    protected function collapseContainerSelfNode(array $nodes): array
    {
        $localIndex = collect($nodes)->search(
            fn (array $node): bool => ($node['source'] ?? null) === 'local'
                && (string) data_get($node, 'summary.machine.diagnostics_source') === 'container'
        );

        if ($localIndex === false) {
            return $nodes;
        }

        $selfPeerIndex = collect($nodes)->search(
            fn (array $node, int $index): bool => $index !== $localIndex
                && ($node['source'] ?? null) === 'peer'
                && (bool) ($node['online'] ?? false)
                && is_array($node['summary'] ?? null)
                && $this->looksLikeHostBackedSelfPeer($node)
        );

        if ($selfPeerIndex === false) {
            return $nodes;
        }

        $selfPeer = $nodes[$selfPeerIndex];
        $selfSummary = (array) data_get($selfPeer, 'summary', []);
        $selfLabel = $this->nodeDisplayLabel($selfSummary, 'local', (string) data_get($selfPeer, 'label', ''));

        $selfPeer['label'] = $selfLabel;
        $selfPeer['detail_label'] = $this->nodeDetailLabel($selfSummary, 'local');
        $selfPeer['source'] = 'local';
        $selfPeer['summary'] = $this->displaySummary($selfSummary, $selfLabel);
        $selfPeer['connection'] = [
            ...(array) data_get($selfPeer, 'connection', []),
            'status_label' => 'Tailnet self',
        ];

        unset($nodes[$localIndex], $nodes[$selfPeerIndex]);

        return array_values([$selfPeer, ...$nodes]);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function looksLikeHostBackedSelfPeer(array $node): bool
    {
        $osFamily = strtolower((string) data_get($node, 'summary.machine.os_family', ''));

        return str_contains($osFamily, 'mac')
            || str_contains($osFamily, 'darwin')
            || str_contains($osFamily, 'windows')
            || str_contains($osFamily, 'bsd');
    }

    protected function peerErrorMessage(\Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'cURL error 28') || str_contains(strtolower($message), 'timed out')) {
            return 'No response within 4 seconds from the tailnet endpoint.';
        }

        if (str_contains(strtolower($message), 'connection refused')) {
            return 'The tailnet endpoint is reachable, but the CreditSoft service is not accepting connections.';
        }

        return 'The tailnet endpoint did not return diagnostics.';
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    protected function displaySummary(array $summary, string $displayLabel): array
    {
        data_set($summary, 'machine.hostname', $displayLabel);
        data_set($summary, 'machine.office_label', $displayLabel);

        return $summary;
    }

    /**
     * @return array{measured_at:string,measured_at_label:string}
     */
    protected function measuredAt(): array
    {
        $timezone = (string) config('creditsoft.diagnostics.display_timezone', config('creditsoft.updates.release_timezone', 'America/Los_Angeles'));
        $now = now();

        return [
            'measured_at' => $now->toIso8601String(),
            'measured_at_label' => $now->copy()->timezone($timezone)->format('M j, g:i A T'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, mixed>
     */
    protected function connectionOverview(array $nodes): array
    {
        $remoteConnections = collect($nodes)
            ->filter(fn (array $node): bool => ($node['source'] ?? '') === 'peer')
            ->pluck('connection')
            ->filter(fn ($connection): bool => is_array($connection) && (bool) data_get($connection, 'tested', false))
            ->values();

        if ($remoteConnections->isEmpty()) {
            return [
                'tested_count' => 0,
                'summary_label' => 'No remote speed test yet',
                'fastest_latency_label' => 'Local only',
                'slowest_latency_label' => 'Local only',
                'best_throughput_label' => 'Local only',
            ];
        }

        $fastest = $remoteConnections
            ->sortBy(fn (array $connection): float => (float) data_get($connection, 'latency_ms', INF))
            ->first();
        $slowest = $remoteConnections
            ->sortByDesc(fn (array $connection): float => (float) data_get($connection, 'latency_ms', 0))
            ->first();
        $bestThroughput = $remoteConnections
            ->sortByDesc(fn (array $connection): int => (int) data_get($connection, 'throughput_bps', 0))
            ->first();

        return [
            'tested_count' => $remoteConnections->count(),
            'summary_label' => $remoteConnections->count().' remote probe'.($remoteConnections->count() === 1 ? '' : 's').' tested',
            'fastest_latency_label' => (string) data_get($fastest, 'latency_label', '—'),
            'slowest_latency_label' => (string) data_get($slowest, 'latency_label', '—'),
            'best_throughput_label' => (string) data_get($bestThroughput, 'throughput_label', '—'),
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

    protected function humanBitRate(int $bytesPerSecond): string
    {
        if ($bytesPerSecond <= 0) {
            return '0 bps';
        }

        $bitsPerSecond = $bytesPerSecond * 8;
        $units = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
        $power = min((int) floor(log($bitsPerSecond, 1000)), count($units) - 1);
        $value = $bitsPerSecond / (1000 ** $power);

        return number_format($value, $power === 0 ? 0 : 1).' '.$units[$power];
    }
}
