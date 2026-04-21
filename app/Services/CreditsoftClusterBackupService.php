<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class CreditsoftClusterBackupService
{
    public function __construct(
        protected OfficeBackupFilesystemSettingsService $filesystemSettings,
        protected InstallerState $installerState,
    ) {
    }

    /**
     * @return array{messages: array<int, string>, deliveries: array<int, array{label:string,base_url:string,status:string}>}
     */
    public function pushToPeers(string $archivePath): array
    {
        $stored = $this->filesystemSettings->stored();
        $clusterEnabled = (bool) Arr::get($stored, 'cluster.enabled', false);
        $sharedSecret = trim((string) Arr::get($stored, 'cluster.shared_secret', ''));
        $peers = collect((array) Arr::get($stored, 'cluster.peers', []))
            ->filter(fn (mixed $peer): bool => is_array($peer) && (bool) Arr::get($peer, 'enabled', true) && filled((string) Arr::get($peer, 'base_url', '')))
            ->values();

        if (! $clusterEnabled || $peers->isEmpty()) {
            return [
                'messages' => [],
                'deliveries' => [],
            ];
        }

        if ($sharedSecret === '') {
            return [
                'messages' => ['Cluster backup is enabled, but the shared cluster secret is still empty.'],
                'deliveries' => [],
            ];
        }

        $installerState = $this->installerState->read();
        $sourceOffice = trim((string) Arr::get(
            $stored,
            'cluster.office_label',
            (string) Arr::get($installerState, 'company_name', config('app.name', 'CreditSoft Office'))
        ));
        $sourceLicenseKey = strtoupper(trim((string) Arr::get($installerState, 'license_key', '')));
        $sourceMaskedKey = trim((string) Arr::get($installerState, 'license.masked_key', ''));

        $deliveries = [];
        $successful = 0;

        foreach ($peers as $peer) {
            $label = trim((string) Arr::get($peer, 'label', 'Peer office')) ?: 'Peer office';
            $baseUrl = rtrim(trim((string) Arr::get($peer, 'base_url', '')), '/');

            if ($baseUrl === '') {
                continue;
            }

            try {
                $response = Http::timeout(20)
                    ->acceptJson()
                    ->attach('archive', File::get($archivePath), basename($archivePath))
                    ->post($baseUrl.'/api/v1/cluster-backups/receive', [
                        'shared_secret' => $sharedSecret,
                        'source_office' => $sourceOffice,
                        'source_license_key' => $sourceLicenseKey,
                        'source_masked_key' => $sourceMaskedKey,
                    ]);

                if ($response->successful()) {
                    $successful++;
                    $deliveries[] = [
                        'label' => $label,
                        'base_url' => $baseUrl,
                        'status' => 'delivered',
                    ];

                    continue;
                }

                $deliveries[] = [
                    'label' => $label,
                    'base_url' => $baseUrl,
                    'status' => 'failed',
                ];
            } catch (Throwable) {
                $deliveries[] = [
                    'label' => $label,
                    'base_url' => $baseUrl,
                    'status' => 'failed',
                ];
            }
        }

        $messages = [];

        if ($successful > 0) {
            $messages[] = $successful === 1
                ? 'CreditSoft mirrored this database backup to 1 cluster office.'
                : sprintf('CreditSoft mirrored this database backup to %d cluster offices.', $successful);
        }

        $failed = collect($deliveries)
            ->where('status', 'failed')
            ->pluck('label')
            ->filter()
            ->values();

        if ($failed->isNotEmpty()) {
            $messages[] = 'Some cluster peers could not be reached: '.$failed->implode(', ').'.';
        }

        return [
            'messages' => $messages,
            'deliveries' => $deliveries,
        ];
    }

    /**
     * @return array{stored_path:string,source_office:string,source_key:string,restore:array<string,mixed>}
     */
    public function receive(string $sharedSecret, string $sourceOffice, string $sourceLicenseKey, string $sourceMaskedKey, mixed $uploadedFile): array
    {
        $stored = $this->filesystemSettings->stored();
        $expectedSecret = trim((string) Arr::get($stored, 'cluster.shared_secret', ''));

        abort_if(! (bool) Arr::get($stored, 'cluster.enabled', false), 403, 'Cluster backup is not enabled on this office.');
        abort_if($expectedSecret === '' || ! hash_equals($expectedSecret, trim($sharedSecret)), 403, 'Cluster backup secret mismatch.');
        abort_unless($uploadedFile instanceof \Illuminate\Http\UploadedFile && $uploadedFile->isValid(), 422, 'Backup archive upload is missing.');

        $safeSourceKey = Str::slug($sourceMaskedKey !== '' ? $sourceMaskedKey : ($sourceLicenseKey !== '' ? $sourceLicenseKey : $sourceOffice), '-');
        $directory = storage_path('app/private/database-backups/cluster/'.($safeSourceKey !== '' ? $safeSourceKey : 'peer-office'));

        File::ensureDirectoryExists($directory);

        $filename = now()->format('Ymd-His').'-'.Str::slug($sourceOffice !== '' ? $sourceOffice : 'peer-office').'-'.basename((string) $uploadedFile->getClientOriginalName());
        $uploadedFile->move($directory, $filename);

        $storedPath = $directory.DIRECTORY_SEPARATOR.$filename;
        $restore = $this->restoreIncomingBackupIfEnabled($storedPath);

        File::put($storedPath.'.json', json_encode([
            'received_at' => now()->toIso8601String(),
            'source_office' => $sourceOffice,
            'source_license_key' => $sourceLicenseKey,
            'source_masked_key' => $sourceMaskedKey,
            'file_name' => $filename,
            'restore' => $restore,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'stored_path' => $storedPath,
            'source_office' => $sourceOffice,
            'source_key' => $sourceMaskedKey !== '' ? $sourceMaskedKey : $sourceLicenseKey,
            'restore' => $restore,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function restoreIncomingBackupIfEnabled(string $archivePath): array
    {
        if (! (bool) config('creditsoft.cluster.restore_incoming_backups', false)) {
            return [
                'status' => 'disabled',
                'message' => 'Incoming cluster backup restore is disabled on this office.',
            ];
        }

        try {
            return $this->restoreIncomingBackup($archivePath);
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function restoreIncomingBackup(string $archivePath): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to restore cluster backups.');
        }

        $zip = new ZipArchive();

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('CreditSoft could not open the incoming cluster backup archive.');
        }

        $zipOpen = true;
        $manifestJson = $zip->getFromName('manifest.json');
        $manifest = is_string($manifestJson) ? json_decode($manifestJson, true) : null;

        if (! is_array($manifest)) {
            $zip->close();
            $zipOpen = false;

            throw new RuntimeException('The incoming cluster backup archive is missing a readable manifest.');
        }

        $createdAt = trim((string) data_get($manifest, 'created_at', ''));
        $createdTimestamp = $createdAt !== '' ? strtotime($createdAt) : false;

        if ($createdTimestamp === false) {
            $zip->close();
            $zipOpen = false;

            throw new RuntimeException('The incoming cluster backup archive is missing a valid creation timestamp.');
        }

        $markerPath = (string) config('creditsoft.cluster.restore_marker_path', storage_path('app/private/database-backups/cluster-restore-state.json'));
        $marker = is_file($markerPath) ? json_decode((string) File::get($markerPath), true) : [];
        $latestTimestamp = strtotime((string) data_get($marker, 'latest_created_at', ''));

        if ($latestTimestamp !== false && $createdTimestamp <= $latestTimestamp) {
            $zip->close();
            $zipOpen = false;

            return [
                'status' => 'skipped',
                'message' => 'Incoming cluster backup was not newer than the last restored archive.',
                'latest_created_at' => data_get($marker, 'latest_created_at'),
                'archive_created_at' => $createdAt,
            ];
        }

        $tempDirectory = storage_path('app/private/database-backups/tmp/'.uniqid('cluster-restore-', true));

        try {
            File::ensureDirectoryExists($tempDirectory);

            if (! $zip->extractTo($tempDirectory)) {
                throw new RuntimeException('CreditSoft could not extract the incoming cluster backup archive.');
            }

            $zip->close();
            $zipOpen = false;

            $restored = [];

            foreach ((array) data_get($manifest, 'databases', []) as $database) {
                if (! (bool) data_get($database, 'included', false)) {
                    continue;
                }

                $label = (string) data_get($database, 'label', '');
                $path = (string) data_get($database, 'path', '');
                $dumpPath = $path !== '' ? $tempDirectory.DIRECTORY_SEPARATOR.$path : '';

                if ($dumpPath === '' || ! is_file($dumpPath)) {
                    continue;
                }

                $spec = match ($label) {
                    'creditsoft' => $this->creditsoftPostgresSpec(),
                    'crm' => $this->crmPostgresSpec(),
                    default => null,
                };

                if ($spec === null) {
                    continue;
                }

                $this->restorePostgresDatabase($spec, $dumpPath);
                $restored[] = $label;
            }

            File::ensureDirectoryExists(dirname($markerPath));
            File::put($markerPath, json_encode([
                'restored_at' => now()->toIso8601String(),
                'latest_created_at' => $createdAt,
                'archive_path' => $archivePath,
                'databases' => $restored,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return [
                'status' => 'restored',
                'message' => $restored === []
                    ? 'Incoming cluster backup was newer, but no supported database dumps were present.'
                    : 'Incoming cluster backup was restored into this office.',
                'archive_created_at' => $createdAt,
                'databases' => $restored,
            ];
        } finally {
            if ($zipOpen) {
                $zip->close();
            }

            File::deleteDirectory($tempDirectory);
        }
    }

    /**
     * @return array{label:string, host:string, port:string, database:string, username:string, password:string}
     */
    protected function creditsoftPostgresSpec(): array
    {
        $connection = (array) config('database.connections.pgsql', []);

        return [
            'label' => 'creditsoft',
            'host' => (string) data_get($connection, 'host', '127.0.0.1'),
            'port' => (string) data_get($connection, 'port', '5432'),
            'database' => (string) data_get($connection, 'database', 'creditsoft'),
            'username' => (string) data_get($connection, 'username', 'creditsoft'),
            'password' => (string) data_get($connection, 'password', ''),
        ];
    }

    /**
     * @return array{label:string, host:string, port:string, database:string, username:string, password:string}|null
     */
    protected function crmPostgresSpec(): ?array
    {
        $database = trim((string) env('CRM_PG_DATABASE', 'crm'));
        $username = trim((string) env('CRM_PG_USER', 'crm'));

        if ($database === '' || $username === '') {
            return null;
        }

        $creditsoft = $this->creditsoftPostgresSpec();

        return [
            'label' => 'crm',
            'host' => (string) env('CRM_PG_HOST', $creditsoft['host']),
            'port' => (string) env('CRM_PG_PORT', $creditsoft['port']),
            'database' => $database,
            'username' => $username,
            'password' => (string) env('CRM_PG_PASSWORD', ''),
        ];
    }

    /**
     * @param  array{label:string, host:string, port:string, database:string, username:string, password:string}  $spec
     */
    protected function restorePostgresDatabase(array $spec, string $dumpPath): void
    {
        if ($spec['database'] === '') {
            throw new RuntimeException(sprintf('CreditSoft could not resolve the %s PostgreSQL database name.', $spec['label']));
        }

        $process = new Process([
            'psql',
            '--host',
            $spec['host'],
            '--port',
            $spec['port'],
            '--username',
            $spec['username'],
            '--dbname',
            $spec['database'],
            '--set',
            'ON_ERROR_STOP=1',
            '--file',
            $dumpPath,
        ]);

        $process->setEnv([
            'PGPASSWORD' => $spec['password'],
        ]);
        $process->setTimeout(900);
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput() ?: $process->getOutput());

            throw new RuntimeException(sprintf(
                'CreditSoft could not restore the %s PostgreSQL database%s',
                $spec['label'],
                $error !== '' ? ': '.$error : '.'
            ));
        }
    }
}
