<?php

namespace App\Services;

use App\Models\ClusterApiKeySyncOutbox;
use App\Models\User;
use App\Models\UserApiKey;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CreditsoftClusterApiKeyService
{
    public function __construct(
        protected OfficeBackupFilesystemSettingsService $filesystemSettings,
        protected InstallerState $installerState,
    ) {
    }

    /**
     * @param  array<int, string>  $abilities
     * @return array{token:string,local:array<string,mixed>,deliveries:array<int,array<string,mixed>>,messages:array<int,string>}
     */
    public function issueClusterKey(string $name, string $userEmail, array $abilities = ['partner_api'], bool $revokeExisting = true): array
    {
        $token = 'csoft_u_'.Str::random(48);
        $local = $this->installLocalKey($name, $token, $userEmail, $abilities, $revokeExisting);
        $cluster = $this->pushToPeers($name, $token, $userEmail, $abilities, $revokeExisting);

        return [
            'token' => $token,
            'local' => $local,
            'deliveries' => $cluster['deliveries'],
            'messages' => $cluster['messages'],
        ];
    }

    /**
     * @param  array<int, string>  $abilities
     * @return array<string, mixed>
     */
    public function receiveClusterKey(string $sharedSecret, string $name, string $token, string $userEmail, array $abilities = ['partner_api'], bool $revokeExisting = true): array
    {
        $stored = $this->filesystemSettings->stored();
        $expectedSecret = trim((string) Arr::get($stored, 'cluster.shared_secret', ''));

        abort_if(! (bool) Arr::get($stored, 'cluster.enabled', false), 403, 'Cluster API key sync is not enabled on this office.');
        abort_if($expectedSecret === '' || ! hash_equals($expectedSecret, trim($sharedSecret)), 403, 'Cluster API key sync secret mismatch.');

        return $this->installLocalKey($name, $token, $userEmail, $abilities, $revokeExisting);
    }

    /**
     * @param  array<int, string>  $abilities
     * @return array<string, mixed>
     */
    public function installLocalKey(string $name, string $token, string $userEmail, array $abilities = ['partner_api'], bool $revokeExisting = true): array
    {
        $name = trim($name);
        $token = trim($token);
        $userEmail = strtolower(trim($userEmail));
        $abilities = $this->normalizeAbilities($abilities);

        if ($name === '') {
            throw new RuntimeException('API key name is required.');
        }

        if (! str_starts_with($token, 'csoft_u_') || strlen($token) < 24) {
            throw new RuntimeException('A cluster API key must be a personal CreditSoft API token.');
        }

        if ($userEmail === '') {
            throw new RuntimeException('API key owner email is required.');
        }

        $user = User::query()
            ->where('email', $userEmail)
            ->first();

        if (! $user) {
            throw new RuntimeException("API key owner {$userEmail} does not exist on this node.");
        }

        $hash = hash('sha256', $token);
        $existingSameToken = UserApiKey::query()
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->first();

        if ($existingSameToken) {
            $existingSameToken->forceFill([
                'user_id' => $user->getKey(),
                'name' => $name,
                'abilities' => $abilities,
            ])->save();

            return [
                'status' => 'already_present',
                'name' => $name,
                'user_email' => $userEmail,
                'token_prefix' => substr($token, 0, 12),
                'token_suffix' => substr($token, -4),
                'abilities' => $abilities,
            ];
        }

        if ($revokeExisting) {
            UserApiKey::query()
                ->where('user_id', $user->getKey())
                ->where('name', $name)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }

        UserApiKey::query()->create([
            'user_id' => $user->getKey(),
            'name' => $name,
            'token_prefix' => substr($token, 0, 12),
            'token_suffix' => substr($token, -4),
            'token_hash' => $hash,
            'abilities' => $abilities,
        ]);

        return [
            'status' => 'installed',
            'name' => $name,
            'user_email' => $userEmail,
            'token_prefix' => substr($token, 0, 12),
            'token_suffix' => substr($token, -4),
            'abilities' => $abilities,
        ];
    }

    /**
     * @param  array<int, string>  $abilities
     * @return array{messages:array<int,string>,deliveries:array<int,array<string,mixed>>}
     */
    protected function pushToPeers(string $name, string $token, string $userEmail, array $abilities, bool $revokeExisting): array
    {
        $stored = $this->filesystemSettings->stored();
        $clusterEnabled = (bool) Arr::get($stored, 'cluster.enabled', false);
        $sharedSecret = trim((string) Arr::get($stored, 'cluster.shared_secret', ''));
        $peers = collect((array) Arr::get($stored, 'cluster.peers', []))
            ->filter(fn (mixed $peer): bool => is_array($peer) && (bool) Arr::get($peer, 'enabled', true) && filled((string) Arr::get($peer, 'base_url', '')))
            ->values();

        if (! $clusterEnabled || $peers->isEmpty()) {
            return [
                'messages' => ['No enabled cluster peers were configured, so the API key was installed on this node only.'],
                'deliveries' => [],
            ];
        }

        if ($sharedSecret === '') {
            return [
                'messages' => ['Cluster API key sync is enabled, but the shared cluster secret is empty.'],
                'deliveries' => [],
            ];
        }

        $sourceOffice = trim((string) Arr::get(
            $stored,
            'cluster.office_label',
            (string) Arr::get($this->installerState->read(), 'company_name', config('app.name', 'CreditSoft Office'))
        ));

        $deliveries = [];
        $successful = 0;
        $queued = 0;
        $normalizedAbilities = $this->normalizeAbilities($abilities);

        foreach ($peers as $peer) {
            $label = trim((string) Arr::get($peer, 'label', 'Peer office')) ?: 'Peer office';
            $baseUrl = rtrim(trim((string) Arr::get($peer, 'base_url', '')), '/');

            if ($baseUrl === '') {
                continue;
            }

            $payload = [
                'shared_secret' => $sharedSecret,
                'source_office' => $sourceOffice,
                'name' => $name,
                'token' => $token,
                'user_email' => $userEmail,
                'abilities' => $normalizedAbilities,
                'revoke_existing' => $revokeExisting,
            ];

            $delivery = $this->deliverPayloadToPeer($baseUrl, $payload);

            if ((bool) ($delivery['ok'] ?? false)) {
                $successful++;
                $this->markQueuedPeerSyncDelivered($baseUrl, $name, substr($token, -4));
                $deliveries[] = [
                    'label' => $label,
                    'base_url' => $baseUrl,
                    'status' => 'delivered',
                    'remote_status' => $delivery['remote_status'] ?? 'installed',
                ];

                continue;
            }

            $queued++;
            $queuedSync = $this->queuePeerSync(
                label: $label,
                baseUrl: $baseUrl,
                name: $name,
                tokenSuffix: substr($token, -4),
                payload: $payload,
                message: (string) ($delivery['message'] ?? 'Peer did not accept the API key sync.'),
            );

            $deliveries[] = [
                'label' => $label,
                'base_url' => $baseUrl,
                'status' => 'queued',
                'queue_id' => $queuedSync->getKey(),
                'next_attempt_at' => optional($queuedSync->next_attempt_at)->toIso8601String(),
                'http_status' => $delivery['http_status'] ?? null,
                'message' => (string) ($delivery['message'] ?? 'Peer did not accept the API key sync.'),
            ];
        }

        $messages = [];

        if ($successful > 0) {
            $messages[] = $successful === 1
                ? 'CreditSoft synced this API key to 1 cluster office.'
                : sprintf('CreditSoft synced this API key to %d cluster offices.', $successful);
        }

        $queuedLabels = collect($deliveries)
            ->where('status', 'queued')
            ->pluck('label')
            ->filter()
            ->values();

        if ($queuedLabels->isNotEmpty()) {
            $messages[] = $queued === 1
                ? 'CreditSoft queued this API key for 1 offline cluster office: '.$queuedLabels->implode(', ').'.'
                : sprintf('CreditSoft queued this API key for %d offline cluster offices: %s.', $queued, $queuedLabels->implode(', '));
        }

        return [
            'messages' => $messages,
            'deliveries' => $deliveries,
        ];
    }

    /**
     * @return array{processed:int,delivered:int,queued:int,results:array<int,array<string,mixed>>}
     */
    public function retryQueuedSyncs(int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        $queuedSyncs = ClusterApiKeySyncOutbox::query()
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
                    'remote_status' => $delivery['remote_status'] ?? 'installed',
                ];

                continue;
            }

            $queued++;
            $sync->forceFill([
                'status' => 'queued',
                'attempts' => $attempts,
                'last_error' => $this->shortError((string) ($delivery['message'] ?? 'Peer did not accept the API key sync.')),
                'next_attempt_at' => $this->nextRetryAt($attempts),
            ])->save();

            $results[] = [
                'label' => $sync->peer_label,
                'base_url' => $sync->peer_base_url,
                'status' => 'queued',
                'message' => (string) ($delivery['message'] ?? 'Peer did not accept the API key sync.'),
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
    protected function deliverPayloadToPeer(string $baseUrl, array $payload): array
    {
        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->post(rtrim($baseUrl, '/').'/api/v1/cluster-api-keys/receive', $payload);

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'remote_status' => $response->json('data.status'),
                ];
            }

            return [
                'ok' => false,
                'http_status' => $response->status(),
                'message' => (string) ($response->json('message') ?? 'Peer rejected the API key sync.'),
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function queuePeerSync(string $label, string $baseUrl, string $name, string $tokenSuffix, array $payload, string $message): ClusterApiKeySyncOutbox
    {
        $queuedSync = ClusterApiKeySyncOutbox::query()
            ->where('peer_base_url', $baseUrl)
            ->where('key_name', $name)
            ->where('token_suffix', $tokenSuffix)
            ->where('status', 'queued')
            ->latest('id')
            ->first();

        if (! $queuedSync) {
            $queuedSync = new ClusterApiKeySyncOutbox([
                'peer_base_url' => $baseUrl,
                'key_name' => $name,
                'token_suffix' => $tokenSuffix,
            ]);
        }

        $queuedSync->forceFill([
            'peer_label' => $label,
            'payload' => $payload,
            'status' => 'queued',
            'last_error' => $this->shortError($message),
            'next_attempt_at' => now()->addMinute(),
        ])->save();

        return $queuedSync;
    }

    protected function markQueuedPeerSyncDelivered(string $baseUrl, string $name, string $tokenSuffix): void
    {
        ClusterApiKeySyncOutbox::query()
            ->where('peer_base_url', $baseUrl)
            ->where('key_name', $name)
            ->where('token_suffix', $tokenSuffix)
            ->where('status', 'queued')
            ->update([
                'status' => 'delivered',
                'last_error' => null,
                'next_attempt_at' => null,
                'delivered_at' => now(),
            ]);
    }

    protected function nextRetryAt(int $attempts): mixed
    {
        $minutes = min(60, max(1, 2 ** min(6, $attempts)));

        return now()->addMinutes($minutes);
    }

    protected function shortError(string $message): string
    {
        return Str::limit($message, 2000, '');
    }

    /**
     * @param  array<int, string>  $abilities
     * @return array<int, string>
     */
    protected function normalizeAbilities(array $abilities): array
    {
        $normalized = collect($abilities)
            ->map(fn (mixed $ability): string => trim((string) $ability))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : ['partner_api'];
    }
}
