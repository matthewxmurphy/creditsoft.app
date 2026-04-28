<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

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
     * @return array{stored_path:string,source_office:string,source_key:string}
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

        File::put($storedPath.'.json', json_encode([
            'received_at' => now()->toIso8601String(),
            'source_office' => $sourceOffice,
            'source_license_key' => $sourceLicenseKey,
            'source_masked_key' => $sourceMaskedKey,
            'file_name' => $filename,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'stored_path' => $storedPath,
            'source_office' => $sourceOffice,
            'source_key' => $sourceMaskedKey !== '' ? $sourceMaskedKey : $sourceLicenseKey,
        ];
    }
}
