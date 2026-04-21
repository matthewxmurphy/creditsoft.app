<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class CreditsoftDatabaseBackupService
{
    public function __construct(
        protected OfficeBackupFilesystemSettingsService $filesystemSettings,
        protected CreditsoftClusterBackupService $clusterBackupService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function run(string $target): array
    {
        $target = in_array($target, ['local', 'wasabi', 'dropbox', 'google_drive'], true)
            ? $target
            : 'local';

        $archivePath = $this->createArchive($target);
        $messages = [];
        $remotePath = null;
        $handoffPath = null;

        if ($target === 'wasabi') {
            $uploadResult = $this->attemptWasabiUpload($archivePath);
            $messages = [...$messages, ...$uploadResult['messages']];
            $remotePath = $uploadResult['remote_path'];
        }

        if (in_array($target, ['dropbox', 'google_drive'], true)) {
            $handoffPath = $this->stageExternalHandoff($target, $archivePath);
            $messages[] = sprintf(
                '%s sync is still a staged handoff lane, so CreditSoft saved the database backup locally and copied it into the %s handoff folder.',
                $target === 'dropbox' ? 'Dropbox' : 'Google Drive',
                $target === 'dropbox' ? 'Dropbox' : 'Google Drive'
            );
        }

        if ($target === 'local') {
            $messages[] = in_array((string) config('database.default', 'pgsql'), ['pgsql', 'postgres', 'postgresql'], true)
                ? 'CreditSoft saved a local PostgreSQL backup for this office and included the CRM database when it was reachable.'
                : 'CreditSoft saved a local database backup for this office.';
        }

        $clusterResult = $this->clusterBackupService->pushToPeers($archivePath);
        $messages = [...$messages, ...$clusterResult['messages']];

        return [
            'target' => $target,
            'archive_path' => $archivePath,
            'remote_path' => $remotePath,
            'handoff_path' => $handoffPath,
            'messages' => $messages,
            'cluster_deliveries' => $clusterResult['deliveries'],
        ];
    }

    protected function createArchive(string $target): string
    {
        $connection = (string) config('database.default', 'pgsql');

        return match ($connection) {
            'pgsql', 'postgres', 'postgresql' => $this->createPostgresArchive($target),
            default => throw new RuntimeException(sprintf(
                'The current %s database driver is not wired into the footer backup lane yet.',
                $connection
            )),
        };
    }

    protected function createPostgresArchive(string $target): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required for the database backup lane.');
        }

        $timestamp = now()->format('Ymd-His');
        $filename = sprintf('creditsoft-db-%s-%s.zip', $timestamp, $target);
        $archiveDirectory = storage_path('app/private/database-backups/local');
        $archivePath = $archiveDirectory.DIRECTORY_SEPARATOR.$filename;
        $tempDirectory = storage_path('app/private/database-backups/tmp/'.uniqid('pgsql-', true));
        $manifest = [
            'product' => 'CreditSoft Intranet',
            'database_driver' => 'pgsql',
            'created_at' => now()->toIso8601String(),
            'requested_target' => $target,
            'version' => (string) config('creditsoft.updates.current_version', 'unknown'),
            'build' => (string) config('creditsoft.updates.current_build', ''),
            'databases' => [],
            'client_documents' => [
                'stored_in_database' => false,
                'storage_mode' => 'filesystem',
                'path' => rtrim((string) config('creditsoft.document_path', storage_path('app/private/client-documents')), DIRECTORY_SEPARATOR),
                'included_in_this_backup' => false,
            ],
            'archive' => [
                'format' => 'zip',
                'compression_method' => $this->zipCompressionMethodName(),
                'compression_level' => $this->zipCompressionLevel(),
            ],
        ];

        File::ensureDirectoryExists($archiveDirectory);
        File::ensureDirectoryExists($tempDirectory);

        try {
            $creditsoftSpec = $this->creditsoftPostgresSpec();
            $creditsoftDump = $tempDirectory.DIRECTORY_SEPARATOR.'creditsoft.sql';
            $this->dumpPostgresDatabase($creditsoftSpec, $creditsoftDump);
            $manifest['databases'][] = $this->databaseManifestEntry($creditsoftSpec, true, 'database/postgres/creditsoft.sql');

            $crmDump = null;
            $crmSpec = $this->crmPostgresSpec();

            if ($crmSpec !== null) {
                $crmDump = $tempDirectory.DIRECTORY_SEPARATOR.'crm.sql';

                try {
                    $this->dumpPostgresDatabase($crmSpec, $crmDump);
                    $manifest['databases'][] = $this->databaseManifestEntry($crmSpec, true, 'database/postgres/crm.sql', optional: true);
                } catch (Throwable $exception) {
                    $manifest['databases'][] = [
                        ...$this->databaseManifestEntry($crmSpec, false, null, optional: true),
                        'error' => $exception->getMessage(),
                    ];
                    $crmDump = null;
                }
            }

            $zip = new ZipArchive();

            if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('CreditSoft could not create the database backup archive.');
            }

            $archiveEntries = ['database/postgres/creditsoft.sql'];
            $zip->addFile($creditsoftDump, 'database/postgres/creditsoft.sql');

            if ($crmDump !== null && is_file($crmDump)) {
                $zip->addFile($crmDump, 'database/postgres/crm.sql');
                $archiveEntries[] = 'database/postgres/crm.sql';
            }

            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $archiveEntries[] = 'manifest.json';

            $this->applyZipCompression($zip, $archiveEntries);
            $zip->close();

            return $archivePath;
        } finally {
            File::deleteDirectory($tempDirectory);
        }
    }

    /**
     * @param  array<int, string>  $entries
     */
    protected function applyZipCompression(ZipArchive $zip, array $entries): void
    {
        $method = $this->zipCompressionMethod();
        $level = $this->zipCompressionLevel();

        foreach ($entries as $entry) {
            $zip->setCompressionName($entry, $method, $level);
        }
    }

    protected function zipCompressionMethod(): int
    {
        return ZipArchive::CM_DEFLATE;
    }

    protected function zipCompressionMethodName(): string
    {
        return 'deflate';
    }

    protected function zipCompressionLevel(): int
    {
        $level = (int) config('backup.backup.destination.compression_level', 9);

        return max(0, min(9, $level));
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
    protected function dumpPostgresDatabase(array $spec, string $dumpPath): void
    {
        if ($spec['database'] === '') {
            throw new RuntimeException(sprintf('CreditSoft could not resolve the %s PostgreSQL database name.', $spec['label']));
        }

        $process = new Process([
            'pg_dump',
            '--host',
            $spec['host'],
            '--port',
            $spec['port'],
            '--username',
            $spec['username'],
            '--dbname',
            $spec['database'],
            '--clean',
            '--if-exists',
            '--no-owner',
            '--no-privileges',
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
                'CreditSoft could not dump the %s PostgreSQL database%s',
                $spec['label'],
                $error !== '' ? ': '.$error : '.'
            ));
        }
    }

    /**
     * @param  array{label:string, host:string, port:string, database:string, username:string, password:string}  $spec
     * @return array<string, mixed>
     */
    protected function databaseManifestEntry(array $spec, bool $included, ?string $path, bool $optional = false): array
    {
        return [
            'label' => $spec['label'],
            'database' => $spec['database'],
            'username' => $spec['username'],
            'host' => $spec['host'],
            'port' => $spec['port'],
            'included' => $included,
            'optional' => $optional,
            'path' => $path,
        ];
    }

    /**
     * @return array{messages: array<int, string>, remote_path: ?string}
     */
    protected function attemptWasabiUpload(string $archivePath): array
    {
        $settings = $this->filesystemSettings->load();

        if (! ($settings['wasabi']['enabled'] ?? false)) {
            return [
                'messages' => ['Wasabi is not configured yet, so CreditSoft saved the database backup locally instead.'],
                'remote_path' => null,
            ];
        }

        if (! class_exists(\League\Flysystem\AwsS3V3\PortableVisibilityConverter::class)) {
            return [
                'messages' => ['Wasabi credentials are present, but the S3 adapter is not installed on this office yet, so CreditSoft saved the database backup locally instead.'],
                'remote_path' => null,
            ];
        }

        $remotePath = 'database-backups/'.basename($archivePath);

        try {
            Storage::disk('wasabi')->put($remotePath, File::get($archivePath));
        } catch (Throwable $exception) {
            return [
                'messages' => ['Wasabi upload failed, so CreditSoft kept the database backup locally: '.$exception->getMessage()],
                'remote_path' => null,
            ];
        }

        return [
            'messages' => ['CreditSoft saved the database backup locally and pushed a copy into the Wasabi archive lane.'],
            'remote_path' => $remotePath,
        ];
    }

    protected function stageExternalHandoff(string $target, string $archivePath): string
    {
        $directory = storage_path(sprintf('app/private/backup-handoff/%s', $target));

        File::ensureDirectoryExists($directory);

        $destination = $directory.DIRECTORY_SEPARATOR.basename($archivePath);
        File::copy($archivePath, $destination);

        return $destination;
    }
}
