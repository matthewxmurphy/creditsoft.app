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
        $databaseDriver = (string) config('database.default', 'sqlite');
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
                'path' => rtrim((string) config('creditsoft.document_path', storage_path('app/private/client-documents')), DIRECTORY_SEPARATOR),
                'count' => ClientDocument::query()->count(),
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
            'sqlite' => 'SQLite',
            'pgsql', 'postgres', 'postgresql' => 'PostgreSQL',
            'mysql' => 'MySQL',
            'mariadb' => 'MariaDB',
            default => strtoupper($driver),
        };
    }

    protected function databaseLocationLabel(string $driver): ?string
    {
        if ($driver === 'sqlite') {
            return (string) config('database.connections.sqlite.database', database_path('database.sqlite'));
        }

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
            if ($driver === 'sqlite') {
                $databasePath = (string) config('database.connections.sqlite.database', database_path('database.sqlite'));

                return is_file($databasePath) ? (int) (File::size($databasePath) ?: 0) : 0;
            }

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
}
