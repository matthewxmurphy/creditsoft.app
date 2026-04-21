<?php

namespace App\Services;

use App\Models\ClientDocument;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CreditsoftStorageHealthService
{
    public function __construct(
        protected OfficeBackupFilesystemSettingsService $filesystemSettings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $databaseDriver = (string) config('database.default', 'pgsql');
        $databasePath = $this->databaseLocationLabel($databaseDriver);
        $databaseBytes = $this->databaseSizeBytes($databaseDriver);

        $backupDirectory = storage_path('app/private/database-backups/local');
        $backupFiles = is_dir($backupDirectory)
            ? collect(File::files($backupDirectory))
                ->sortByDesc(fn (\SplFileInfo $file): int => $file->getMTime())
                ->values()
            : collect();
        $lastBackup = $backupFiles->first();

        $stored = $this->filesystemSettings->stored();
        $clusterPeers = collect((array) Arr::get($stored, 'cluster.peers', []))
            ->filter(fn (mixed $peer): bool => is_array($peer) && (bool) Arr::get($peer, 'enabled', true) && filled((string) Arr::get($peer, 'base_url', '')))
            ->values();

        $documentPath = rtrim((string) config('creditsoft.document_path', storage_path('app/private/client-documents')), DIRECTORY_SEPARATOR);
        $documentRecordCount = ClientDocument::query()->count();
        $fileBackedDocumentCount = ClientDocument::query()
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->count();
        $documentBytes = (int) ClientDocument::query()->sum(DB::raw('COALESCE(file_size, 0)'));
        $filesystemDocuments = $this->documentFilesystemStats($documentPath);

        return [
            'database' => [
                'driver' => $databaseDriver,
                'driver_label' => $this->databaseDriverLabel($databaseDriver),
                'path' => $databasePath,
                'size_bytes' => $databaseBytes,
                'size_label' => $this->humanBytes($databaseBytes),
                'last_backup_at' => $lastBackup ? date(DATE_ATOM, $lastBackup->getMTime()) : null,
                'last_backup_label' => $lastBackup ? $this->formatTimestamp($lastBackup->getMTime()) : null,
                'backup_count' => $backupFiles->count(),
            ],
            'documents' => [
                'stored_in_database' => false,
                'storage_mode' => 'filesystem',
                'path' => $documentPath,
                'count' => $documentRecordCount,
                'record_count' => $documentRecordCount,
                'file_backed_count' => $fileBackedDocumentCount,
                'metadata_only_count' => max($documentRecordCount - $fileBackedDocumentCount, 0),
                'file_size_bytes' => $documentBytes,
                'file_size_label' => $this->humanBytes($documentBytes),
                'filesystem_file_count' => $filesystemDocuments['count'],
                'filesystem_size_bytes' => $filesystemDocuments['bytes'],
                'filesystem_size_label' => $this->humanBytes($filesystemDocuments['bytes']),
            ],
            'cluster' => [
                'enabled' => (bool) Arr::get($stored, 'cluster.enabled', false),
                'peer_count' => $clusterPeers->count(),
            ],
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

    protected function formatTimestamp(int $timestamp): string
    {
        return date('M j, Y g:i A', $timestamp);
    }

    protected function databaseDriverLabel(string $driver): string
    {
        return match ($driver) {
            'pgsql', 'postgres', 'postgresql' => 'PostgreSQL',
            'mysql' => 'MySQL',
            'mariadb' => 'MariaDB',
            default => strtoupper($driver),
        };
    }

    protected function databaseLocationLabel(string $driver): ?string
    {
        $connection = (array) config("database.connections.{$driver}", []);
        $database = trim((string) Arr::get($connection, 'database', ''));
        $host = trim((string) Arr::get($connection, 'host', ''));
        $port = trim((string) Arr::get($connection, 'port', ''));

        if ($database === '') {
            return null;
        }

        if ($host === '') {
            return $database;
        }

        return $port !== '' ? "{$host}:{$port}/{$database}" : "{$host}/{$database}";
    }

    protected function databaseSizeBytes(string $driver): int
    {
        try {
            if (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
                return max((int) (DB::selectOne('select pg_database_size(current_database()) as size_bytes')->size_bytes ?? 0), 0);
            }

            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $database = (string) config("database.connections.{$driver}.database", '');

                if ($database === '') {
                    return 0;
                }

                $row = DB::selectOne(
                    'select coalesce(sum(data_length + index_length), 0) as size_bytes from information_schema.tables where table_schema = ?',
                    [$database],
                );

                return max((int) ($row->size_bytes ?? 0), 0);
            }
        } catch (\Throwable) {
            return 0;
        }

        return 0;
    }

    /**
     * @return array{count:int,bytes:int}
     */
    protected function documentFilesystemStats(string $path): array
    {
        if ($path === '' || ! is_dir($path)) {
            return ['count' => 0, 'bytes' => 0];
        }

        try {
            $files = File::allFiles($path);
        } catch (\Throwable) {
            return ['count' => 0, 'bytes' => 0];
        }

        $bytes = 0;

        foreach ($files as $file) {
            $bytes += max((int) $file->getSize(), 0);
        }

        return [
            'count' => count($files),
            'bytes' => $bytes,
        ];
    }
}
