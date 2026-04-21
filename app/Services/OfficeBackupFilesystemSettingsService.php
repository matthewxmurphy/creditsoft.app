<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class OfficeBackupFilesystemSettingsService
{
    public function __construct(
        protected InstallerState $installerState,
    ) {
    }

    public function load(): array
    {
        $stored = $this->readStored();
        $wasabiKey = (string) config('filesystems.disks.wasabi.key', '');
        $wasabiSecret = (string) config('filesystems.disks.wasabi.secret', '');
        $wasabiBucket = (string) config('filesystems.disks.wasabi.bucket', '');
        $dropboxSecret = (string) Arr::get($stored, 'dropbox.app_secret', '');
        $dropboxRefreshToken = (string) Arr::get($stored, 'dropbox.refresh_token', '');
        $googleClientSecret = (string) Arr::get($stored, 'google_drive.client_secret', '');
        $googleRefreshToken = (string) Arr::get($stored, 'google_drive.refresh_token', '');
        $clusterSharedSecret = (string) Arr::get($stored, 'cluster.shared_secret', '');

        return [
            'archive_destination' => in_array((string) Arr::get($stored, 'archive_destination', $this->defaultArchiveDestination()), ['local', 'wasabi'], true)
                ? (string) Arr::get($stored, 'archive_destination', $this->defaultArchiveDestination())
                : $this->defaultArchiveDestination(),
            'external_handoff_lane' => in_array((string) Arr::get($stored, 'external_handoff_lane', $this->defaultExternalHandoffLane()), ['none', 'dropbox', 'google_drive'], true)
                ? (string) Arr::get($stored, 'external_handoff_lane', $this->defaultExternalHandoffLane())
                : $this->defaultExternalHandoffLane(),
            'local' => [
                'private_path' => storage_path('app/private'),
                'backup_temp_path' => (string) config('backup.backup.temporary_directory'),
                'archive_disks' => array_values((array) config('backup.backup.destination.disks', ['local'])),
            ],
            'cluster' => [
                'enabled' => (bool) Arr::get($stored, 'cluster.enabled', false),
                'office_label' => (string) Arr::get(
                    $stored,
                    'cluster.office_label',
                    (string) Arr::get($this->installerState->read(), 'company_name', config('app.name', 'CreditSoft Office'))
                ),
                'shared_secret' => '',
                'has_shared_secret' => filled($clusterSharedSecret),
                'masked_shared_secret' => $this->mask($clusterSharedSecret),
                'peers' => $this->normalizePeers(Arr::get($stored, 'cluster.peers', [])),
            ],
            'wasabi' => [
                'enabled' => $this->wasabiConfigured(),
                'access_key_id' => '',
                'secret_key' => '',
                'bucket' => $wasabiBucket,
                'region' => (string) config('filesystems.disks.wasabi.region', 'us-east-1'),
                'endpoint' => (string) config('filesystems.disks.wasabi.endpoint', 'https://s3.us-east-1.wasabisys.com'),
                'use_path_style_endpoint' => (bool) config('filesystems.disks.wasabi.use_path_style_endpoint', true),
                'has_access_key_id' => filled($wasabiKey),
                'has_secret_key' => filled($wasabiSecret),
                'masked_access_key_id' => $this->mask($wasabiKey),
                'masked_secret_key' => $this->mask($wasabiSecret),
            ],
            'dropbox' => [
                'enabled' => (bool) Arr::get($stored, 'dropbox.enabled', false),
                'app_key' => (string) Arr::get($stored, 'dropbox.app_key', ''),
                'app_secret' => '',
                'refresh_token' => '',
                'root_folder' => (string) Arr::get($stored, 'dropbox.root_folder', '/CreditSoft'),
                'sync_mode' => (string) Arr::get($stored, 'dropbox.sync_mode', 'exports'),
                'has_app_secret' => filled($dropboxSecret),
                'has_refresh_token' => filled($dropboxRefreshToken),
                'masked_app_secret' => $this->mask($dropboxSecret),
                'masked_refresh_token' => $this->mask($dropboxRefreshToken),
            ],
            'google_drive' => [
                'enabled' => (bool) Arr::get($stored, 'google_drive.enabled', false),
                'client_id' => (string) Arr::get($stored, 'google_drive.client_id', ''),
                'client_secret' => '',
                'refresh_token' => '',
                'folder_id' => (string) Arr::get($stored, 'google_drive.folder_id', ''),
                'shared_drive_id' => (string) Arr::get($stored, 'google_drive.shared_drive_id', ''),
                'sync_mode' => (string) Arr::get($stored, 'google_drive.sync_mode', 'client_documents'),
                'has_client_secret' => filled($googleClientSecret),
                'has_refresh_token' => filled($googleRefreshToken),
                'masked_client_secret' => $this->mask($googleClientSecret),
                'masked_refresh_token' => $this->mask($googleRefreshToken),
            ],
        ];
    }

    public function save(array $input, EnvironmentEditor $editor): array
    {
        $stored = $this->readStored();
        $archiveDestination = (string) Arr::get($input, 'archive_destination', $this->defaultArchiveDestination());
        $externalHandoffLane = (string) Arr::get($input, 'external_handoff_lane', $this->defaultExternalHandoffLane());

        if (! in_array($archiveDestination, ['local', 'wasabi'], true)) {
            $archiveDestination = $this->defaultArchiveDestination();
        }

        if (! in_array($externalHandoffLane, ['none', 'dropbox', 'google_drive'], true)) {
            $externalHandoffLane = 'none';
        }

        $wasabiAccessKey = trim((string) Arr::get($input, 'wasabi.access_key_id'));
        $wasabiSecretKey = trim((string) Arr::get($input, 'wasabi.secret_key'));

        if ($wasabiAccessKey === '') {
            $wasabiAccessKey = (string) config('filesystems.disks.wasabi.key', '');
        }

        if ($wasabiSecretKey === '') {
            $wasabiSecretKey = (string) config('filesystems.disks.wasabi.secret', '');
        }

        $dropboxAppSecret = trim((string) Arr::get($input, 'dropbox.app_secret'));
        $dropboxRefreshToken = trim((string) Arr::get($input, 'dropbox.refresh_token'));
        $googleClientSecret = trim((string) Arr::get($input, 'google_drive.client_secret'));
        $googleRefreshToken = trim((string) Arr::get($input, 'google_drive.refresh_token'));
        $clusterSharedSecret = trim((string) Arr::get($input, 'cluster.shared_secret'));
        $clusterOfficeLabel = trim((string) Arr::get($input, 'cluster.office_label'));

        $clean = [
            'archive_destination' => $archiveDestination,
            'external_handoff_lane' => $externalHandoffLane,
            'cluster' => [
                'enabled' => (bool) Arr::get($input, 'cluster.enabled', false),
                'office_label' => $clusterOfficeLabel !== ''
                    ? $clusterOfficeLabel
                    : (string) Arr::get($stored, 'cluster.office_label', Arr::get($this->installerState->read(), 'company_name', config('app.name', 'CreditSoft Office'))),
                'shared_secret' => $clusterSharedSecret !== ''
                    ? $clusterSharedSecret
                    : (string) Arr::get($stored, 'cluster.shared_secret', ''),
                'peers' => $this->normalizePeers(Arr::get($input, 'cluster.peers', [])),
            ],
            'dropbox' => [
                'enabled' => (bool) Arr::get($input, 'dropbox.enabled', false),
                'app_key' => trim((string) Arr::get($input, 'dropbox.app_key')),
                'app_secret' => $dropboxAppSecret !== '' ? $dropboxAppSecret : (string) Arr::get($stored, 'dropbox.app_secret', ''),
                'refresh_token' => $dropboxRefreshToken !== '' ? $dropboxRefreshToken : (string) Arr::get($stored, 'dropbox.refresh_token', ''),
                'root_folder' => trim((string) Arr::get($input, 'dropbox.root_folder', '/CreditSoft')) ?: '/CreditSoft',
                'sync_mode' => $this->normalizeSyncMode((string) Arr::get($input, 'dropbox.sync_mode', 'exports')),
            ],
            'google_drive' => [
                'enabled' => (bool) Arr::get($input, 'google_drive.enabled', false),
                'client_id' => trim((string) Arr::get($input, 'google_drive.client_id')),
                'client_secret' => $googleClientSecret !== '' ? $googleClientSecret : (string) Arr::get($stored, 'google_drive.client_secret', ''),
                'refresh_token' => $googleRefreshToken !== '' ? $googleRefreshToken : (string) Arr::get($stored, 'google_drive.refresh_token', ''),
                'folder_id' => trim((string) Arr::get($input, 'google_drive.folder_id')),
                'shared_drive_id' => trim((string) Arr::get($input, 'google_drive.shared_drive_id')),
                'sync_mode' => $this->normalizeSyncMode((string) Arr::get($input, 'google_drive.sync_mode', 'client_documents')),
            ],
        ];

        File::ensureDirectoryExists(dirname($this->storagePath()));
        File::put($this->storagePath(), json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $editor->setMany([
            'WASABI_ACCESS_KEY_ID' => $wasabiAccessKey,
            'WASABI_SECRET_ACCESS_KEY' => $wasabiSecretKey,
            'WASABI_BUCKET' => trim((string) Arr::get($input, 'wasabi.bucket')),
            'WASABI_DEFAULT_REGION' => trim((string) Arr::get($input, 'wasabi.region', 'us-east-1')) ?: 'us-east-1',
            'WASABI_ENDPOINT' => trim((string) Arr::get($input, 'wasabi.endpoint', 'https://s3.us-east-1.wasabisys.com')) ?: 'https://s3.us-east-1.wasabisys.com',
            'WASABI_USE_PATH_STYLE_ENDPOINT' => (bool) Arr::get($input, 'wasabi.use_path_style_endpoint', true) ? 'true' : 'false',
            'BACKUP_DESTINATION_DISKS' => $archiveDestination === 'wasabi' ? 'local,wasabi' : 'local',
        ]);

        $this->installerState->merge([
            'backup_destination' => $this->resolveInstallerBackupDestination($archiveDestination, $externalHandoffLane),
        ]);

        return $this->load();
    }

    public function stored(): array
    {
        return $this->readStored();
    }

    protected function readStored(): array
    {
        $path = $this->storagePath();

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function storagePath(): string
    {
        return storage_path('app/private/office-backup-filesystem-settings.json');
    }

    protected function defaultArchiveDestination(): string
    {
        return $this->wasabiConfigured() ? 'wasabi' : 'local';
    }

    protected function defaultExternalHandoffLane(): string
    {
        return match ((string) Arr::get($this->installerState->read(), 'backup_destination', 'local_only')) {
            'dropbox' => 'dropbox',
            'google_drive' => 'google_drive',
            default => 'none',
        };
    }

    protected function resolveInstallerBackupDestination(string $archiveDestination, string $externalHandoffLane): string
    {
        if ($externalHandoffLane !== 'none') {
            return $externalHandoffLane;
        }

        return $archiveDestination === 'wasabi' ? 'wasabi' : 'local_only';
    }

    protected function normalizeSyncMode(string $mode): string
    {
        return in_array($mode, ['exports', 'client_documents', 'everything'], true)
            ? $mode
            : 'exports';
    }

    /**
     * @param  mixed  $peers
     * @return array<int, array{label:string,base_url:string,license_key:string,enabled:bool}>
     */
    protected function normalizePeers(mixed $peers): array
    {
        if (! is_array($peers)) {
            return [];
        }

        return collect($peers)
            ->map(function (mixed $peer): array {
                $peer = is_array($peer) ? $peer : [];

                return [
                    'label' => trim((string) Arr::get($peer, 'label')),
                    'base_url' => rtrim(trim((string) Arr::get($peer, 'base_url')), '/'),
                    'license_key' => strtoupper(trim((string) Arr::get($peer, 'license_key'))),
                    'enabled' => (bool) Arr::get($peer, 'enabled', true),
                ];
            })
            ->filter(fn (array $peer): bool => $peer['label'] !== '' || $peer['base_url'] !== '' || $peer['license_key'] !== '')
            ->values()
            ->all();
    }

    protected function wasabiConfigured(): bool
    {
        return filled((string) config('filesystems.disks.wasabi.key', ''))
            && filled((string) config('filesystems.disks.wasabi.secret', ''))
            && filled((string) config('filesystems.disks.wasabi.bucket', ''));
    }

    protected function mask(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (strlen($value) <= 8) {
            return str_repeat('*', max(strlen($value) - 2, 1)).substr($value, -2);
        }

        return substr($value, 0, 4).str_repeat('*', max(strlen($value) - 8, 4)).substr($value, -4);
    }
}
