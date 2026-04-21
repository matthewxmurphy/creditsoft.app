<?php

namespace App\Services;

use App\Models\AuditEntry;
use App\Models\Client;
use App\Models\SystemDiagnosticSnapshot;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreditsoftSystemDiagnosticsService
{
    public function __construct(
        protected CreditsoftStorageHealthService $storageHealth,
        protected InstallerState $installerState,
        protected OfficeBackupFilesystemSettingsService $filesystemSettings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $hostSnapshot = $this->hostSnapshot();
        $diskPath = storage_path('app/private');
        $disk = $this->diskStatsFromSnapshot($hostSnapshot) ?? $this->diskStats($diskPath);
        $memory = $this->metricStatsFromSnapshot($hostSnapshot, 'memory') ?? $this->memoryStats();
        $swap = $this->metricStatsFromSnapshot($hostSnapshot, 'swap') ?? $this->swapStats();
        $network = $this->networkStatsFromSnapshot($hostSnapshot) ?? $this->networkStats();
        $load = sys_getloadavg() ?: [0.0, 0.0, 0.0];
        $storage = $this->storageHealth->current();
        $clientStorage = $this->clientStorageStats((int) data_get($disk, 'free_bytes', 0), $storage);
        $staffActivity = $this->staffActivityStats();
        $storedFilesystem = $this->filesystemSettings->stored();

        return [
            'captured_at' => now()->toIso8601String(),
            'machine' => [
                'hostname' => (string) data_get($hostSnapshot, 'machine.hostname', gethostname() ?: php_uname('n')),
                'office_label' => (string) data_get(
                    $storedFilesystem,
                    'cluster.office_label',
                    data_get($this->installerState->read(), 'company_name', config('app.name', 'CreditSoft Office'))
                ),
                'os_family' => (string) data_get($hostSnapshot, 'machine.os_family', PHP_OS_FAMILY),
                'kernel' => (string) data_get($hostSnapshot, 'machine.kernel', php_uname('r')),
                'architecture' => (string) data_get($hostSnapshot, 'machine.architecture', php_uname('m')),
                'php_version' => PHP_VERSION,
                'php_sapi' => PHP_SAPI,
                'laravel_version' => app()->version(),
                'app_version' => (string) config('creditsoft.updates.current_version', 'unknown'),
                'app_build' => (string) config('creditsoft.updates.current_build', 'unknown'),
                'database_driver' => (string) config('database.default', 'pgsql'),
                'database_version' => $this->databaseVersion(),
                'opcache_status' => $this->opcacheStatus(),
                'cpu_cores' => max((int) data_get($hostSnapshot, 'machine.cpu_cores', $this->cpuCores()), 1),
                'diagnostics_source' => $hostSnapshot ? 'host_snapshot' : 'container',
                'diagnostics_captured_at' => data_get($hostSnapshot, 'captured_at'),
            ],
            'load' => [
                'one' => round((float) ($load[0] ?? 0), 2),
                'five' => round((float) ($load[1] ?? 0), 2),
                'fifteen' => round((float) ($load[2] ?? 0), 2),
            ],
            'memory' => $memory,
            'swap' => $swap,
            'disk' => $disk,
            'network' => [
                'rx_bytes' => $network['rx_bytes'],
                'tx_bytes' => $network['tx_bytes'],
                'rx_label' => $this->humanBytes($network['rx_bytes']),
                'tx_label' => $this->humanBytes($network['tx_bytes']),
            ],
            'storage' => $storage,
            'client_storage' => $clientStorage,
            'staff_activity' => $staffActivity,
        ];
    }

    public function ensureFreshSnapshot(int $maxAgeMinutes = 6): void
    {
        $latest = SystemDiagnosticSnapshot::query()->latest('captured_at')->first();

        if (! $latest || $latest->captured_at?->lt(now()->subMinutes($maxAgeMinutes))) {
            $this->captureSnapshot();
        }
    }

    public function captureSnapshot(): SystemDiagnosticSnapshot
    {
        $current = $this->current();

        return SystemDiagnosticSnapshot::query()->create([
            'captured_at' => $current['captured_at'],
            'hostname' => data_get($current, 'machine.hostname'),
            'cpu_cores' => data_get($current, 'machine.cpu_cores'),
            'load_one' => data_get($current, 'load.one'),
            'load_five' => data_get($current, 'load.five'),
            'load_fifteen' => data_get($current, 'load.fifteen'),
            'memory_total_bytes' => data_get($current, 'memory.total_bytes'),
            'memory_used_bytes' => data_get($current, 'memory.used_bytes'),
            'memory_free_bytes' => data_get($current, 'memory.free_bytes'),
            'swap_total_bytes' => data_get($current, 'swap.total_bytes'),
            'swap_used_bytes' => data_get($current, 'swap.used_bytes'),
            'swap_free_bytes' => data_get($current, 'swap.free_bytes'),
            'disk_total_bytes' => data_get($current, 'disk.total_bytes'),
            'disk_used_bytes' => data_get($current, 'disk.used_bytes'),
            'disk_free_bytes' => data_get($current, 'disk.free_bytes'),
            'network_rx_bytes' => data_get($current, 'network.rx_bytes'),
            'network_tx_bytes' => data_get($current, 'network.tx_bytes'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function history(int $limit = 72): array
    {
        $snapshots = SystemDiagnosticSnapshot::query()
            ->latest('captured_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $labels = $snapshots
            ->map(fn (SystemDiagnosticSnapshot $snapshot): string => optional($snapshot->captured_at)->format('g:i A') ?? 'Now')
            ->all();

        $previous = null;
        $networkRxSeries = [];
        $networkTxSeries = [];

        foreach ($snapshots as $snapshot) {
            if ($previous instanceof SystemDiagnosticSnapshot) {
                $rxDelta = max((int) $snapshot->network_rx_bytes - (int) $previous->network_rx_bytes, 0);
                $txDelta = max((int) $snapshot->network_tx_bytes - (int) $previous->network_tx_bytes, 0);
                $networkRxSeries[] = round($rxDelta / 1048576, 2);
                $networkTxSeries[] = round($txDelta / 1048576, 2);
            } else {
                $networkRxSeries[] = null;
                $networkTxSeries[] = null;
            }

            $previous = $snapshot;
        }

        return [
            'window_label' => 'Last 6 hours · 5 minute cadence',
            'labels' => $labels,
            'load' => [
                'series' => [
                    ['label' => '1 min spike', 'values' => $snapshots->pluck('load_one')->map(fn ($value) => $value !== null ? (float) $value : null)->all(), 'color' => '#f59e0b', 'type' => 'bar'],
                    ['label' => '5 min trend', 'values' => $snapshots->pluck('load_five')->map(fn ($value) => $value !== null ? (float) $value : null)->all(), 'color' => '#1d4ed8', 'type' => 'line'],
                    ['label' => '15 min trend', 'values' => $snapshots->pluck('load_fifteen')->map(fn ($value) => $value !== null ? (float) $value : null)->all(), 'color' => '#0f766e', 'type' => 'line'],
                ],
            ],
            'memory' => [
                'series' => [
                    ['label' => 'Used GB', 'values' => $snapshots->map(fn (SystemDiagnosticSnapshot $snapshot) => $this->bytesToGigabytes($snapshot->memory_used_bytes))->all(), 'color' => '#111827'],
                    ['label' => 'Free GB', 'values' => $snapshots->map(fn (SystemDiagnosticSnapshot $snapshot) => $this->bytesToGigabytes($snapshot->memory_free_bytes))->all(), 'color' => '#10b981'],
                ],
            ],
            'swap' => [
                'series' => [
                    ['label' => 'Used GB', 'values' => $snapshots->map(fn (SystemDiagnosticSnapshot $snapshot) => $this->bytesToGigabytes($snapshot->swap_used_bytes))->all(), 'color' => '#b45309'],
                    ['label' => 'Free GB', 'values' => $snapshots->map(fn (SystemDiagnosticSnapshot $snapshot) => $this->bytesToGigabytes($snapshot->swap_free_bytes))->all(), 'color' => '#0f766e'],
                ],
            ],
            'disk' => [
                'series' => [
                    ['label' => 'Used GB', 'values' => $snapshots->map(fn (SystemDiagnosticSnapshot $snapshot) => $this->bytesToGigabytes($snapshot->disk_used_bytes))->all(), 'color' => '#7c3aed'],
                    ['label' => 'Free GB', 'values' => $snapshots->map(fn (SystemDiagnosticSnapshot $snapshot) => $this->bytesToGigabytes($snapshot->disk_free_bytes))->all(), 'color' => '#f59e0b'],
                ],
            ],
            'network' => [
                'series' => [
                    ['label' => 'RX MB/5m', 'values' => $networkRxSeries, 'color' => '#0284c7'],
                    ['label' => 'TX MB/5m', 'values' => $networkTxSeries, 'color' => '#db2777'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function clusterSummary(): array
    {
        $current = $this->current();

        return [
            'captured_at' => data_get($current, 'captured_at'),
            'machine' => [
                'hostname' => data_get($current, 'machine.hostname'),
                'office_label' => data_get($current, 'machine.office_label'),
                'cpu_cores' => data_get($current, 'machine.cpu_cores'),
                'os_family' => data_get($current, 'machine.os_family'),
            ],
            'load' => data_get($current, 'load'),
            'memory' => Arr::only((array) data_get($current, 'memory', []), [
                'total_bytes',
                'used_bytes',
                'free_bytes',
                'available_bytes',
                'reclaimable_bytes',
                'raw_used_bytes',
                'file_backed_bytes',
                'anonymous_bytes',
                'compressor_bytes',
                'total_label',
                'used_label',
                'free_label',
                'available_label',
                'reclaimable_label',
                'raw_used_label',
                'compressor_label',
                'used_percent',
                'available_percent',
                'pressure_free_percent',
                'pressure_level',
                'pressure_label',
                'pageouts',
                'swapins',
                'swapouts',
                'platform_note',
            ]),
            'swap' => Arr::only((array) data_get($current, 'swap', []), ['total_bytes', 'used_bytes', 'free_bytes', 'total_label', 'used_label', 'free_label']),
            'disk' => Arr::only((array) data_get($current, 'disk', []), ['total_bytes', 'used_bytes', 'free_bytes', 'total_label', 'used_label', 'free_label', 'used_percent']),
            'network' => Arr::only((array) data_get($current, 'network', []), ['rx_bytes', 'tx_bytes', 'rx_label', 'tx_label']),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function hostSnapshot(): ?array
    {
        $path = (string) config('creditsoft.diagnostics.host_snapshot_path', '');

        if ($path === '' || ! is_readable($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return null;
        }

        $capturedAt = (string) data_get($decoded, 'captured_at', '');
        $capturedTimestamp = $capturedAt !== '' ? strtotime($capturedAt) : false;
        $maxAgeSeconds = max((int) config('creditsoft.diagnostics.host_snapshot_max_age_seconds', 900), 0);

        if ($maxAgeSeconds > 0 && (! $capturedTimestamp || $capturedTimestamp < (time() - $maxAgeSeconds))) {
            return null;
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    protected function diskStats(string $path): array
    {
        $total = max((int) @disk_total_space($path), 0);
        $free = max((int) @disk_free_space($path), 0);
        $used = max($total - $free, 0);

        return $this->formatDiskStats($path, $total, $used, $free);
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, mixed>|null
     */
    protected function diskStatsFromSnapshot(?array $snapshot): ?array
    {
        if (! $snapshot) {
            return null;
        }

        $disk = (array) data_get($snapshot, 'disk', []);
        $total = max((int) ($disk['total_bytes'] ?? 0), 0);

        if ($total <= 0) {
            return null;
        }

        $free = max((int) ($disk['free_bytes'] ?? 0), 0);
        $used = max((int) ($disk['used_bytes'] ?? ($total - $free)), 0);
        $path = (string) ($disk['path'] ?? storage_path('app/private'));

        if ($free <= 0 && $used <= $total) {
            $free = max($total - $used, 0);
        }

        return $this->formatDiskStats($path, $total, $used, $free);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatDiskStats(string $path, int $total, int $used, int $free): array
    {
        return [
            'path' => $path,
            'total_bytes' => $total,
            'used_bytes' => $used,
            'free_bytes' => $free,
            'total_label' => $this->humanBytes($total),
            'used_label' => $this->humanBytes($used),
            'free_label' => $this->humanBytes($free),
            'used_percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0.0,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, mixed>|null
     */
    protected function metricStatsFromSnapshot(?array $snapshot, string $key): ?array
    {
        if (! $snapshot) {
            return null;
        }

        $metric = (array) data_get($snapshot, $key, []);
        $total = max((int) ($metric['total_bytes'] ?? 0), 0);

        if ($total <= 0) {
            return null;
        }

        $free = max((int) ($metric['free_bytes'] ?? 0), 0);
        $used = max((int) ($metric['used_bytes'] ?? ($total - $free)), 0);

        if ($free <= 0 && $used <= $total) {
            $free = max($total - $used, 0);
        }

        $result = [
            'total_bytes' => $total,
            'used_bytes' => $used,
            'free_bytes' => $free,
            'total_label' => $this->humanBytes($total),
            'used_label' => $this->humanBytes($used),
            'free_label' => $this->humanBytes($free),
            'used_percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0.0,
        ];

        foreach ([
            'available_bytes',
            'reclaimable_bytes',
            'raw_used_bytes',
            'file_backed_bytes',
            'anonymous_bytes',
            'compressor_bytes',
        ] as $field) {
            if (array_key_exists($field, $metric)) {
                $value = max((int) $metric[$field], 0);
                $result[$field] = $value;
                $result[str_replace('_bytes', '_label', $field)] = $this->humanBytes($value);
            }
        }

        if (array_key_exists('available_bytes', $result)) {
            $result['available_percent'] = $total > 0
                ? round(((int) $result['available_bytes'] / $total) * 100, 1)
                : 0.0;
        }

        foreach (['pressure_free_percent'] as $field) {
            if (array_key_exists($field, $metric)) {
                $result[$field] = round((float) $metric[$field], 1);
            }
        }

        foreach (['pageouts', 'swapins', 'swapouts'] as $field) {
            if (array_key_exists($field, $metric)) {
                $result[$field] = max((int) $metric[$field], 0);
            }
        }

        foreach (['pressure_level', 'pressure_label', 'platform_note'] as $field) {
            if (array_key_exists($field, $metric)) {
                $result[$field] = (string) $metric[$field];
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return array{rx_bytes:int,tx_bytes:int}|null
     */
    protected function networkStatsFromSnapshot(?array $snapshot): ?array
    {
        if (! $snapshot) {
            return null;
        }

        $network = (array) data_get($snapshot, 'network', []);

        if (! array_key_exists('rx_bytes', $network) && ! array_key_exists('tx_bytes', $network)) {
            return null;
        }

        return [
            'rx_bytes' => max((int) ($network['rx_bytes'] ?? 0), 0),
            'tx_bytes' => max((int) ($network['tx_bytes'] ?? 0), 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function clientStorageStats(int $diskFreeBytes, array $storage): array
    {
        $rows = Client::query()
            ->leftJoin('client_documents', 'clients.id', '=', 'client_documents.client_id')
            ->selectRaw("
                clients.id,
                clients.first_name,
                clients.last_name,
                clients.cuid,
                COALESCE(SUM(client_documents.file_size), 0) as document_bytes,
                COUNT(client_documents.id) as document_count,
                COALESCE(SUM(CASE WHEN client_documents.file_path IS NOT NULL AND client_documents.file_path <> '' THEN 1 ELSE 0 END), 0) as file_backed_document_count
            ")
            ->groupBy('clients.id', 'clients.first_name', 'clients.last_name', 'clients.cuid')
            ->get();

        $clientCount = $rows->count();
        $totalBytes = (int) $rows->sum(fn ($row): int => (int) $row->document_bytes);
        $documentCount = (int) $rows->sum(fn ($row): int => (int) $row->document_count);
        $fileBackedDocumentCount = (int) $rows->sum(fn ($row): int => (int) $row->file_backed_document_count);
        $fileBackedClientCount = $rows->filter(fn ($row): bool => (int) $row->file_backed_document_count > 0)->count();
        $documentCoveragePercent = $clientCount > 0 ? round(($fileBackedClientCount / $clientCount) * 100, 1) : 0.0;
        $metadataOnlyDocumentCount = max($documentCount - $fileBackedDocumentCount, 0);
        $averageBytes = $clientCount > 0 ? (int) round($totalBytes / $clientCount) : 0;
        $databaseBytes = max((int) data_get($storage, 'database.size_bytes', 0), 0);
        $databaseAverageBytes = $clientCount > 0 ? (int) round($databaseBytes / $clientCount) : 0;
        $estimateReady = $clientCount > 0 && $fileBackedDocumentCount > 0 && $totalBytes > 0 && $documentCoveragePercent >= 25.0;
        $estimatedFootprintBytes = $estimateReady ? max($averageBytes + $databaseAverageBytes, 1024 * 1024) : 0;
        $reservedDiskBytes = (int) min(round($diskFreeBytes * 0.15), 20 * 1024 * 1024 * 1024);
        $usableDiskBytes = max($diskFreeBytes - $reservedDiskBytes, 0);
        $fileRows = $rows->filter(fn ($row): bool => (int) $row->file_backed_document_count > 0 || (int) $row->document_bytes > 0);
        $biggest = $fileRows->sortByDesc(fn ($row): int => (int) $row->document_bytes)->first();
        $smallest = $fileRows->sortBy(fn ($row): int => (int) $row->document_bytes)->first();

        return [
            'client_count' => $clientCount,
            'document_count' => $documentCount,
            'file_backed_document_count' => $fileBackedDocumentCount,
            'file_backed_client_count' => $fileBackedClientCount,
            'metadata_only_document_count' => $metadataOnlyDocumentCount,
            'document_coverage_percent' => $documentCoveragePercent,
            'total_bytes' => $totalBytes,
            'total_label' => $this->humanBytes($totalBytes),
            'average_bytes' => $averageBytes,
            'average_label' => $this->humanBytes($averageBytes),
            'database_average_bytes' => $databaseAverageBytes,
            'database_average_label' => $this->humanBytes($databaseAverageBytes),
            'estimated_client_footprint_bytes' => $estimatedFootprintBytes,
            'estimated_client_footprint_label' => $estimateReady ? $this->humanBytes($estimatedFootprintBytes) : 'Pending files',
            'estimated_more_clients' => $estimateReady ? intdiv($usableDiskBytes, max($estimatedFootprintBytes, 1)) : null,
            'estimate_ready' => $estimateReady,
            'estimate_note' => $estimateReady
                ? 'Approximate extra clients this office can hold based on current database plus real document storage per client.'
                : sprintf(
                    'Storage estimate paused: %s document records are staged, %s have local files, and only %s of %s clients have file-backed documents.',
                    number_format($documentCount),
                    number_format($fileBackedDocumentCount),
                    number_format($fileBackedClientCount),
                    number_format($clientCount)
                ),
            'biggest' => $this->formatClientStorageRow($biggest),
            'smallest' => $this->formatClientStorageRow($smallest),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function staffActivityStats(): array
    {
        $windowStart = now()->subDays(7);
        $eventCounts = AuditEntry::query()
            ->select('user_id', DB::raw('count(*) as aggregate'))
            ->whereNotNull('user_id')
            ->where('created_at', '>=', $windowStart)
            ->groupBy('user_id')
            ->pluck('aggregate', 'user_id');

        $rows = User::query()
            ->with('roles')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $user->hasWorkspaceAccess() || $user->canAccessOpsPanel() || $user->roles->isEmpty())
            ->map(function (User $user) use ($eventCounts): array {
                $lastSeen = $user->last_seen_at;

                return [
                    'id' => $user->getKey(),
                    'label' => $user->name,
                    'role_label' => $user->primaryRoleLabel() ?? 'Staff',
                    'event_count' => (int) ($eventCounts[$user->getKey()] ?? 0),
                    'last_seen_at' => optional($lastSeen)?->toIso8601String(),
                    'last_seen_label' => $lastSeen ? $lastSeen->diffForHumans() : 'Not seen yet',
                    'last_seen_sort' => $lastSeen ? $lastSeen->getTimestamp() : 0,
                ];
            })
            ->values();

        $mostActive = $rows
            ->sort(function (array $left, array $right): int {
                return [$right['event_count'], $right['last_seen_sort']] <=> [$left['event_count'], $left['last_seen_sort']];
            })
            ->first();
        $leastActive = $rows
            ->sort(function (array $left, array $right): int {
                return [$left['event_count'], $left['last_seen_sort']] <=> [$right['event_count'], $right['last_seen_sort']];
            })
            ->first();

        return [
            'window_label' => 'Last 7 days',
            'staff_count' => $rows->count(),
            'total_events' => (int) $rows->sum('event_count'),
            'most_active' => $this->formatStaffActivityRow($mostActive),
            'least_active' => $this->formatStaffActivityRow($leastActive),
        ];
    }

    protected function databaseVersion(): string
    {
        try {
            return match ((string) config('database.default', 'pgsql')) {
                'mysql', 'mariadb' => (string) (DB::selectOne('select version() as version')->version ?? 'Unknown'),
                'pgsql' => (string) (DB::selectOne('show server_version')->server_version ?? 'Unknown'),
                default => 'Unknown',
            };
        } catch (\Throwable) {
            return 'Unknown';
        }
    }

    protected function opcacheStatus(): string
    {
        if (! extension_loaded('Zend OPcache')) {
            return 'Not installed';
        }

        $enabled = PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server'
            ? (bool) ini_get('opcache.enable_cli')
            : (bool) ini_get('opcache.enable');

        return $enabled ? 'Enabled' : 'Disabled';
    }

    /**
     * @param  object|null  $row
     * @return array<string, mixed>|null
     */
    protected function formatClientStorageRow(?object $row): ?array
    {
        if (! $row) {
            return null;
        }

        $name = trim(sprintf('%s %s', (string) ($row->first_name ?? ''), (string) ($row->last_name ?? '')));

        return [
            'label' => $name !== '' ? $name : ((string) ($row->cuid ?? 'Unknown client')),
            'cuid' => (string) ($row->cuid ?? ''),
            'bytes' => (int) ($row->document_bytes ?? 0),
            'size_label' => $this->humanBytes((int) ($row->document_bytes ?? 0)),
            'document_count' => (int) ($row->document_count ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $row
     * @return array<string, mixed>|null
     */
    protected function formatStaffActivityRow(?array $row): ?array
    {
        if (! $row) {
            return null;
        }

        unset($row['last_seen_sort']);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    protected function memoryStats(): array
    {
        return PHP_OS_FAMILY === 'Darwin'
            ? $this->macMemoryStats()
            : $this->linuxMemoryStats();
    }

    /**
     * @return array<string, mixed>
     */
    protected function swapStats(): array
    {
        return PHP_OS_FAMILY === 'Darwin'
            ? $this->macSwapStats()
            : $this->linuxSwapStats();
    }

    /**
     * @return array{rx_bytes:int,tx_bytes:int}
     */
    protected function networkStats(): array
    {
        return PHP_OS_FAMILY === 'Darwin'
            ? $this->macNetworkStats()
            : $this->linuxNetworkStats();
    }

    /**
     * @return array<string, mixed>
     */
    protected function macMemoryStats(): array
    {
        $total = max((int) $this->runCommand('sysctl -n hw.memsize'), 0);
        $vmStat = $this->runCommand('vm_stat');
        $pageSize = 4096;
        $pages = [];

        foreach (preg_split('/\R/', $vmStat) as $line) {
            if (preg_match('/page size of (\d+) bytes/i', $line, $matches)) {
                $pageSize = (int) $matches[1];
            }

            if (preg_match('/Pages ([A-Za-z\s]+):\s+(\d+)\./', $line, $matches)) {
                $pages[strtolower(trim($matches[1]))] = (int) $matches[2];
            }
        }

        $freePages = (int) ($pages['free'] ?? 0) + (int) ($pages['speculative'] ?? 0);
        $reclaimablePages = $freePages + (int) ($pages['inactive'] ?? 0) + (int) ($pages['purgeable'] ?? 0);
        $free = $freePages * $pageSize;
        $reclaimable = $reclaimablePages * $pageSize;
        $pressureFreePercent = null;
        $memoryPressure = $this->runCommand('memory_pressure');

        if (preg_match('/System-wide memory free percentage:\s+([0-9.]+)%/i', $memoryPressure, $matches)) {
            $pressureFreePercent = (float) $matches[1];
        }

        $available = $pressureFreePercent !== null
            ? (int) round($total * ($pressureFreePercent / 100))
            : $reclaimable;
        $available = max(min($available, $total), $free);
        $used = max($total - $available, 0);
        $rawUsed = max($total - $free, 0);
        $pressureLevel = 'unknown';

        if ($pressureFreePercent !== null) {
            $pressureLevel = match (true) {
                $pressureFreePercent >= 25 => 'healthy',
                $pressureFreePercent >= 15 => 'watch',
                $pressureFreePercent >= 8 => 'pressured',
                default => 'critical',
            };
        }

        return [
            'total_bytes' => $total,
            'used_bytes' => $used,
            'free_bytes' => max($free, 0),
            'available_bytes' => $available,
            'reclaimable_bytes' => max($reclaimable, 0),
            'raw_used_bytes' => $rawUsed,
            'compressor_bytes' => (int) ($pages['occupied by compressor'] ?? 0) * $pageSize,
            'total_label' => $this->humanBytes($total),
            'used_label' => $this->humanBytes($used),
            'free_label' => $this->humanBytes(max($free, 0)),
            'available_label' => $this->humanBytes($available),
            'reclaimable_label' => $this->humanBytes(max($reclaimable, 0)),
            'raw_used_label' => $this->humanBytes($rawUsed),
            'compressor_label' => $this->humanBytes((int) ($pages['occupied by compressor'] ?? 0) * $pageSize),
            'used_percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0.0,
            'available_percent' => $total > 0 ? round(($available / $total) * 100, 1) : 0.0,
            'pressure_free_percent' => $pressureFreePercent,
            'pressure_level' => $pressureLevel,
            'pressure_label' => ucfirst($pressureLevel),
            'platform_note' => 'macOS available memory uses memory_pressure when available; high cached memory is not treated as a hardware failure.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function linuxMemoryStats(): array
    {
        $meminfo = $this->parseMeminfo();
        $total = (int) ($meminfo['MemTotal'] ?? 0) * 1024;
        $available = (int) ($meminfo['MemAvailable'] ?? $meminfo['MemFree'] ?? 0) * 1024;
        $used = max($total - $available, 0);

        return [
            'total_bytes' => $total,
            'used_bytes' => $used,
            'free_bytes' => max($available, 0),
            'available_bytes' => max($available, 0),
            'total_label' => $this->humanBytes($total),
            'used_label' => $this->humanBytes($used),
            'free_label' => $this->humanBytes(max($available, 0)),
            'available_label' => $this->humanBytes(max($available, 0)),
            'used_percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0.0,
            'available_percent' => $total > 0 ? round(($available / $total) * 100, 1) : 0.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function macSwapStats(): array
    {
        $output = $this->runCommand('sysctl vm.swapusage');

        if (preg_match('/total = ([0-9.]+[KMGTP])\s+used = ([0-9.]+[KMGTP])\s+free = ([0-9.]+[KMGTP])/i', $output, $matches)) {
            $total = $this->parseHumanSize($matches[1]);
            $used = $this->parseHumanSize($matches[2]);
            $free = $this->parseHumanSize($matches[3]);

            return [
                'total_bytes' => $total,
                'used_bytes' => $used,
                'free_bytes' => $free,
                'total_label' => $this->humanBytes($total),
                'used_label' => $this->humanBytes($used),
                'free_label' => $this->humanBytes($free),
            ];
        }

        return [
            'total_bytes' => 0,
            'used_bytes' => 0,
            'free_bytes' => 0,
            'total_label' => '0 B',
            'used_label' => '0 B',
            'free_label' => '0 B',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function linuxSwapStats(): array
    {
        $meminfo = $this->parseMeminfo();
        $total = (int) ($meminfo['SwapTotal'] ?? 0) * 1024;
        $free = (int) ($meminfo['SwapFree'] ?? 0) * 1024;
        $used = max($total - $free, 0);

        return [
            'total_bytes' => $total,
            'used_bytes' => $used,
            'free_bytes' => $free,
            'total_label' => $this->humanBytes($total),
            'used_label' => $this->humanBytes($used),
            'free_label' => $this->humanBytes($free),
        ];
    }

    /**
     * @return array{rx_bytes:int,tx_bytes:int}
     */
    protected function macNetworkStats(): array
    {
        $output = $this->runCommand('netstat -ibn');
        $ibytesIndex = null;
        $obytesIndex = null;
        $perInterface = [];

        foreach (preg_split('/\R/', $output) as $line) {
            $columns = preg_split('/\s+/', trim($line));

            if (! is_array($columns) || $columns === [] || $columns[0] === '') {
                continue;
            }

            if ($columns[0] === 'Name') {
                $ibytesIndex = array_search('Ibytes', $columns, true);
                $obytesIndex = array_search('Obytes', $columns, true);
                continue;
            }

            if ($ibytesIndex === false || $obytesIndex === false || $ibytesIndex === null || $obytesIndex === null) {
                continue;
            }

            $name = (string) ($columns[0] ?? '');

            if ($name === '' || str_starts_with($name, 'lo')) {
                continue;
            }

            $rx = isset($columns[$ibytesIndex]) ? (int) preg_replace('/\D+/', '', $columns[$ibytesIndex]) : 0;
            $tx = isset($columns[$obytesIndex]) ? (int) preg_replace('/\D+/', '', $columns[$obytesIndex]) : 0;

            $perInterface[$name] = [
                'rx' => max($rx, (int) ($perInterface[$name]['rx'] ?? 0)),
                'tx' => max($tx, (int) ($perInterface[$name]['tx'] ?? 0)),
            ];
        }

        return [
            'rx_bytes' => array_sum(array_column($perInterface, 'rx')),
            'tx_bytes' => array_sum(array_column($perInterface, 'tx')),
        ];
    }

    /**
     * @return array{rx_bytes:int,tx_bytes:int}
     */
    protected function linuxNetworkStats(): array
    {
        $path = '/proc/net/dev';

        if (! is_file($path)) {
            return ['rx_bytes' => 0, 'tx_bytes' => 0];
        }

        $rx = 0;
        $tx = 0;

        foreach (preg_split('/\R/', (string) file_get_contents($path)) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $stats] = array_map('trim', explode(':', $line, 2));

            if ($name === '' || str_starts_with($name, 'lo')) {
                continue;
            }

            $columns = preg_split('/\s+/', trim($stats));

            if (! is_array($columns) || count($columns) < 9) {
                continue;
            }

            $rx += (int) $columns[0];
            $tx += (int) $columns[8];
        }

        return ['rx_bytes' => $rx, 'tx_bytes' => $tx];
    }

    protected function cpuCores(): int
    {
        $output = PHP_OS_FAMILY === 'Darwin'
            ? $this->runCommand('sysctl -n hw.ncpu')
            : $this->runCommand('nproc');

        return max((int) trim($output), 1);
    }

    /**
     * @return array<string, int>
     */
    protected function parseMeminfo(): array
    {
        $path = '/proc/meminfo';

        if (! is_file($path)) {
            return [];
        }

        $values = [];

        foreach (preg_split('/\R/', (string) file_get_contents($path)) as $line) {
            if (preg_match('/^([A-Za-z_]+):\s+(\d+)/', $line, $matches)) {
                $values[$matches[1]] = (int) $matches[2];
            }
        }

        return $values;
    }

    protected function runCommand(string $command): string
    {
        if (! function_exists('shell_exec')) {
            return '';
        }

        $output = @shell_exec($command.' 2>/dev/null');

        return is_string($output) ? trim($output) : '';
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

    protected function parseHumanSize(string $value): int
    {
        if (! preg_match('/^([0-9.]+)\s*([KMGTP])$/i', trim($value), $matches)) {
            return 0;
        }

        $number = (float) $matches[1];
        $unit = strtoupper($matches[2]);
        $powers = ['K' => 1, 'M' => 2, 'G' => 3, 'T' => 4, 'P' => 5];

        return (int) round($number * (1024 ** ($powers[$unit] ?? 0)));
    }

    protected function bytesToGigabytes(?int $bytes): ?float
    {
        if ($bytes === null) {
            return null;
        }

        return round($bytes / 1073741824, 2);
    }
}
