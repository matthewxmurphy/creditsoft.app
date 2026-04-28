<?php

namespace App\Services;

use App\Models\ClusterLicenseSyncOutbox;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CreditsoftClusterLicenseSyncService
{
    public function __construct(
        protected OfficeBackupFilesystemSettingsService $filesystemSettings,
        protected InstallerState $installerState,
    ) {
    }

    /**
     * @return array{queued:int,delivered:int,remaining:int,results:array<int,array<string,mixed>>}
     */
    public function queueCurrentLicenseSync(bool $deliverNow = true): array
    {
        if (! Schema::hasTable('cluster_license_sync_outboxes')) {
            return [
                'queued' => 0,
                'delivered' => 0,
                'remaining' => 0,
                'results' => [],
            ];
        }

        $stored = $this->filesystemSettings->stored();
        $sharedSecret = trim((string) Arr::get($stored, 'cluster.shared_secret', ''));
        $peers = $this->enabledPeers($stored);

        if (! (bool) Arr::get($stored, 'cluster.enabled', false) || $sharedSecret === '' || $peers->isEmpty()) {
            return [
                'queued' => 0,
                'delivered' => 0,
                'remaining' => 0,
                'results' => [],
            ];
        }

        $state = $this->installerState->read();
        $license = $this->safeLicensePayload((array) Arr::get($state, 'license', []));

        if (! (bool) Arr::get($license, 'valid', false)) {
            return [
                'queued' => 0,
                'delivered' => 0,
                'remaining' => 0,
                'results' => [[
                    'status' => 'skipped',
                    'message' => 'Current office license is not valid, so it was not mirrored to peers.',
                ]],
            ];
        }

        $eventUuid = (string) Str::uuid();
        $payload = [
            'shared_secret' => $sharedSecret,
            'event_uuid' => $eventUuid,
            'source_node' => $this->sourceNodeLabel(),
            'license_key' => trim((string) Arr::get($state, 'license_key', '')),
            'license' => $license,
            'occurred_at' => now()->toIso8601String(),
        ];

        foreach ($peers as $peer) {
            ClusterLicenseSyncOutbox::query()->updateOrCreate(
                [
                    'event_uuid' => $eventUuid,
                    'peer_base_url' => $peer['base_url'],
                ],
                [
                    'source_node' => $payload['source_node'],
                    'peer_label' => $peer['label'],
                    'payload' => $payload,
                    'status' => 'queued',
                    'last_error' => null,
                    'next_attempt_at' => now(),
                    'delivered_at' => null,
                ],
            );
        }

        $retry = $deliverNow ? $this->retryQueuedSyncs($peers->count()) : [
            'delivered' => 0,
            'queued' => $peers->count(),
            'results' => [],
        ];

        return [
            'queued' => $peers->count(),
            'delivered' => (int) ($retry['delivered'] ?? 0),
            'remaining' => (int) ($retry['queued'] ?? $peers->count()),
            'results' => (array) ($retry['results'] ?? []),
        ];
    }

    /**
     * @return array{processed:int,delivered:int,queued:int,results:array<int,array<string,mixed>>}
     */
    public function retryQueuedSyncs(int $limit = 25): array
    {
        if (! Schema::hasTable('cluster_license_sync_outboxes')) {
            return [
                'processed' => 0,
                'delivered' => 0,
                'queued' => 0,
                'results' => [],
            ];
        }

        $limit = max(1, min(100, $limit));
        $queuedSyncs = ClusterLicenseSyncOutbox::query()
            ->where('status', 'queued')
            ->where(function ($query): void {
                $query
                    ->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', now());
            })
            ->orderByRaw('next_attempt_at is null desc')
            ->orderBy('next_attempt_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $results = [];
        $delivered = 0;
        $queued = 0;

        foreach ($queuedSyncs as $sync) {
            $payload = (array) $sync->payload;
            $delivery = $this->deliverPayloadToPeer($sync->peer_base_url, $payload);
            $attempts = ((int) $sync->attempts) + 1;

            if ((bool) ($delivery['ok'] ?? false)) {
                $delivered++;
                $sync->forceFill([
                    'status' => 'delivered',
                    'attempts' => $attempts,
                    'last_error' => null,
                    'next_attempt_at' => null,
                    'delivered_at' => now(),
                ])->save();

                $results[] = [
                    'label' => $sync->peer_label,
                    'base_url' => $sync->peer_base_url,
                    'status' => 'delivered',
                    'remote_status' => $delivery['remote_status'] ?? 'applied',
                ];

                continue;
            }

            $queued++;
            $sync->forceFill([
                'status' => 'queued',
                'attempts' => $attempts,
                'last_error' => $this->shortError((string) ($delivery['message'] ?? 'Peer did not accept the license sync event.')),
                'next_attempt_at' => $this->nextRetryAt($attempts),
            ])->save();

            $results[] = [
                'label' => $sync->peer_label,
                'base_url' => $sync->peer_base_url,
                'status' => 'queued',
                'message' => (string) ($delivery['message'] ?? 'Peer did not accept the license sync event.'),
            ];
        }

        return [
            'processed' => $queuedSyncs->count(),
            'delivered' => $delivered,
            'queued' => $queued,
            'results' => $results,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function receiveClusterLicense(string $sharedSecret, array $payload): array
    {
        $stored = $this->filesystemSettings->stored();
        $expectedSecret = trim((string) Arr::get($stored, 'cluster.shared_secret', ''));

        abort_if(! (bool) Arr::get($stored, 'cluster.enabled', false), 403, 'Cluster license sync is not enabled on this office.');
        abort_if($expectedSecret === '' || ! hash_equals($expectedSecret, trim($sharedSecret)), 403, 'Cluster license sync secret mismatch.');

        $license = $this->safeLicensePayload((array) Arr::get($payload, 'license', []));

        if (! (bool) Arr::get($license, 'valid', false)) {
            throw new RuntimeException('Cluster license sync refused an invalid license payload.');
        }

        $licenseKey = trim((string) Arr::get($payload, 'license_key', ''));
        $updates = ['license' => $license];

        if ($licenseKey !== '') {
            $updates['license_key'] = $licenseKey;
        }

        $this->installerState->merge($updates);

        return [
            'status' => 'applied',
            'license_status' => (string) Arr::get($license, 'status', 'valid'),
            'plan' => Arr::get($license, 'plan'),
            'plan_key' => Arr::get($license, 'plan_key'),
            'masked_key' => Arr::get($license, 'masked_key'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function deliverPayloadToPeer(string $baseUrl, array $payload): array
    {
        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->post(rtrim($baseUrl, '/').'/api/v1/cluster-license/receive', $payload);

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'remote_status' => $response->json('data.status'),
                ];
            }

            return [
                'ok' => false,
                'http_status' => $response->status(),
                'message' => (string) ($response->json('message') ?? 'Peer rejected the license sync event.'),
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $license
     * @return array<string, mixed>
     */
    protected function safeLicensePayload(array $license): array
    {
        return Arr::only($license, [
            'valid',
            'status',
            'mode',
            'requested_mode',
            'message',
            'checked_at',
            'last_verified_at',
            'remote_unreachable',
            'verification_fail_started_at',
            'masked_key',
            'plan',
            'plan_key',
            'features',
            'expires_at',
            'expired_at',
            'grace_days',
            'grace_ends_at',
            'access_state',
            'can_access_workspace',
        ]);
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    protected function enabledPeers(array $stored): \Illuminate\Support\Collection
    {
        return collect((array) Arr::get($stored, 'cluster.peers', []))
            ->filter(fn (mixed $peer): bool => is_array($peer) && (bool) Arr::get($peer, 'enabled', true) && filled((string) Arr::get($peer, 'base_url', '')))
            ->map(fn (array $peer): array => [
                'label' => trim((string) Arr::get($peer, 'label', 'Peer office')) ?: 'Peer office',
                'base_url' => rtrim(trim((string) Arr::get($peer, 'base_url', '')), '/'),
            ])
            ->filter(fn (array $peer): bool => $peer['base_url'] !== '')
            ->values();
    }

    protected function sourceNodeLabel(): string
    {
        $state = $this->installerState->read();

        return trim((string) Arr::get($state, 'tailscale_hostname'))
            ?: trim((string) Arr::get($state, 'company_name'))
            ?: gethostname()
            ?: 'CreditSoft office';
    }

    protected function nextRetryAt(int $attempts): \Carbon\CarbonInterface
    {
        $minutes = min(max($attempts, 1), 30);

        return now()->addMinutes($minutes);
    }

    protected function shortError(string $message): string
    {
        return Str::limit($message, 500, '...');
    }
}
