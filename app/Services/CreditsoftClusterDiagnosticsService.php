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
        $nodes = [[
            'label' => $officeLabel,
            'base_url' => config('app.url'),
            'license_key' => null,
            'source' => 'local',
            'online' => true,
            'error' => null,
            'summary' => $localSummary,
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

            $node = [
                'label' => (string) data_get($peer, 'label', 'Cluster peer'),
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
                    } else {
                        $node['error'] = (string) ($response->json('message') ?? 'Peer did not return diagnostics.');
                    }
                } catch (\Throwable $exception) {
                    $node['error'] = $exception->getMessage();
                    $node['connection'] = $this->offlineConnectionProbe('Timed out');
                }
            }

            $nodes[] = $node;
        }

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
            'network_rx_bytes' => $onlineNodes->sum(fn (array $node): int => (int) data_get($node, 'summary.network.rx_bytes', 0)),
            'network_tx_bytes' => $onlineNodes->sum(fn (array $node): int => (int) data_get($node, 'summary.network.tx_bytes', 0)),
        ];

        return [
            'enabled' => $clusterEnabled,
            'office_label' => $officeLabel,
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
        return [
            'tested' => true,
            'status_label' => 'Loopback',
            'latency_ms' => 0.0,
            'latency_label' => 'Local',
            'transfer_bytes' => 0,
            'transfer_label' => 'Local',
            'throughput_bps' => 0,
            'throughput_label' => 'Local',
            'measured_at_label' => now()->format('g:i A'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function remoteConnectionProbe(float $durationMs, int $bytes, int $status, ?array $bandwidthProbe = null): array
    {
        $transferBytes = (int) data_get($bandwidthProbe, 'bytes', $bytes);
        $throughput = (int) data_get($bandwidthProbe, 'throughput_bytes_per_second', 0);

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
            'measured_at_label' => now()->format('g:i A'),
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
        return [
            'tested' => false,
            'status_label' => $label,
            'latency_ms' => null,
            'latency_label' => 'No response',
            'transfer_bytes' => 0,
            'transfer_label' => '0 B',
            'throughput_bps' => 0,
            'throughput_label' => '0 B/s',
            'measured_at_label' => now()->format('g:i A'),
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
