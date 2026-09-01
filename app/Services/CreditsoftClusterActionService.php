<?php

namespace App\Services;

use App\Models\ClusterActionOutbox;
use App\Models\ClusterActionReceipt;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CreditsoftClusterActionService
{
    public function __construct(
        protected OfficeBackupFilesystemSettingsService $filesystemSettings,
        protected InstallerState $installerState,
        protected CtoAdvisorActionService $ctoAdvisorActions,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{action_uuid:string,action:string,source_node:string,cluster_node_count:int,peer_count:int,local:array<string,mixed>,peer_results:array<int,array<string,mixed>>,delivered:int,queued:int,messages:array<int,string>}
     */
    public function dispatchEverywhere(string $action, array $payload = []): array
    {
        $action = $this->normalizeAction($action);
        $actionUuid = (string) Str::uuid();
        $sourceNode = $this->sourceNodeLabel();
        $local = $this->applyLocalAction($action, $payload, $actionUuid, $sourceNode);
        $peers = $this->enabledPeers();
        $peerResults = [];
        $delivered = 0;
        $queued = 0;

        foreach ($peers as $peer) {
            $delivery = $this->deliverPayloadToPeer($peer['base_url'], [
                'action_uuid' => $actionUuid,
                'source_node' => $sourceNode,
                'action' => $action,
                'payload' => $payload,
            ]);

            if ((bool) ($delivery['ok'] ?? false)) {
                $delivered++;
                $this->markQueuedPeerActionDelivered($peer['base_url'], $actionUuid);
                $peerResults[] = [
                    'label' => $peer['label'],
                    'base_url' => $peer['base_url'],
                    'status' => 'delivered',
                    'remote_status' => $delivery['remote_status'] ?? 'applied',
                    'message' => $delivery['message'] ?? null,
                ];

                continue;
            }

            $queued++;
            $queuedAction = $this->queuePeerAction(
                label: $peer['label'],
                baseUrl: $peer['base_url'],
                actionUuid: $actionUuid,
                sourceNode: $sourceNode,
                action: $action,
                payload: $payload,
                message: (string) ($delivery['message'] ?? 'Peer did not accept the cluster action.'),
            );

            $peerResults[] = [
                'label' => $peer['label'],
                'base_url' => $peer['base_url'],
                'status' => 'queued',
                'queue_id' => $queuedAction->getKey(),
                'next_attempt_at' => optional($queuedAction->next_attempt_at)->toIso8601String(),
                'http_status' => $delivery['http_status'] ?? null,
                'message' => (string) ($delivery['message'] ?? 'Peer did not accept the cluster action.'),
            ];
        }

        return [
            'action_uuid' => $actionUuid,
            'action' => $action,
            'source_node' => $sourceNode,
            'cluster_node_count' => 1 + $peers->count(),
            'peer_count' => $peers->count(),
            'local' => $local,
            'peer_results' => $peerResults,
            'delivered' => $delivered,
            'queued' => $queued,
            'messages' => $this->messagesForDispatch($peers->count(), $delivered, $queued, $peerResults),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function receiveClusterAction(string $sharedSecret, string $actionUuid, string $sourceNode, string $action, array $payload): array
    {
        $stored = $this->filesystemSettings->stored();
        $expectedSecret = trim((string) Arr::get($stored, 'cluster.shared_secret', ''));

        abort_if(! (bool) Arr::get($stored, 'cluster.enabled', false), 403, 'Cluster action sync is not enabled on this office.');
        abort_if($expectedSecret === '' || ! hash_equals($expectedSecret, trim($sharedSecret)), 403, 'Cluster action sync secret mismatch.');

        $action = $this->normalizeAction($action);
        $actionUuid = trim($actionUuid);
        $sourceNode = trim($sourceNode);

        if ($actionUuid === '') {
            throw new RuntimeException('Cluster action UUID is required.');
        }

        if (Schema::hasTable('cluster_action_receipts')) {
            $existingReceipt = ClusterActionReceipt::query()
                ->where('action_uuid', $actionUuid)
                ->first();

            if ($existingReceipt) {
                return [
                    'ok' => true,
                    'status' => 'already_applied',
                    'action' => $action,
                    'action_uuid' => $actionUuid,
                    'result' => $existingReceipt->result,
                ];
            }
        }

        $result = $this->applyLocalAction($action, $payload, $actionUuid, $sourceNode);

        if (Schema::hasTable('cluster_action_receipts')) {
            ClusterActionReceipt::query()->create([
                'action_uuid' => $actionUuid,
                'source_node' => $sourceNode !== '' ? $sourceNode : null,
                'action' => $action,
                'result' => $result,
                'received_at' => now(),
            ]);
        }

        return [
            'ok' => true,
            'status' => (bool) ($result['ok'] ?? false) ? 'applied' : 'rejected',
            'action' => $action,
            'action_uuid' => $actionUuid,
            'result' => $result,
        ];
    }

    /**
     * @return array{processed:int,delivered:int,queued:int,results:array<int,array<string,mixed>>}
     */
    public function retryQueuedActions(int $limit = 50): array
    {
        if (! Schema::hasTable('cluster_action_outboxes')) {
            return [
                'processed' => 0,
                'delivered' => 0,
                'queued' => 0,
                'results' => [],
            ];
        }

        $limit = max(1, min(250, $limit));
        $queuedActions = ClusterActionOutbox::query()
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

        foreach ($queuedActions as $action) {
            try {
                $payload = (array) $action->payload;
            } catch (Throwable $exception) {
                $queued++;
                $attempts = ((int) $action->attempts) + 1;
                $action->forceFill([
                    'status' => 'queued',
                    'attempts' => $attempts,
                    'last_error' => $this->shortError('Could not decrypt queued cluster action payload: '.$exception->getMessage()),
                    'next_attempt_at' => now()->addDay(),
                ])->save();

                $results[] = [
                    'label' => $action->peer_label,
                    'base_url' => $action->peer_base_url,
                    'action' => $action->action,
                    'status' => 'queued',
                    'message' => 'Could not decrypt queued cluster action payload; moved behind newer actions.',
                ];

                continue;
            }

            $delivery = $this->deliverPayloadToPeer($action->peer_base_url, [
                'action_uuid' => $action->action_uuid,
                'source_node' => $action->source_node,
                'action' => $action->action,
                'payload' => $payload,
            ]);
            $attempts = ((int) $action->attempts) + 1;

            if ((bool) ($delivery['ok'] ?? false)) {
                $delivered++;
                $action->forceFill([
                    'status' => 'delivered',
                    'attempts' => $attempts,
                    'last_error' => null,
                    'next_attempt_at' => null,
                    'delivered_at' => now(),
                ])->save();

                $results[] = [
                    'label' => $action->peer_label,
                    'base_url' => $action->peer_base_url,
                    'action' => $action->action,
                    'status' => 'delivered',
                    'remote_status' => $delivery['remote_status'] ?? 'applied',
                ];

                continue;
            }

            $queued++;
            $action->forceFill([
                'status' => 'queued',
                'attempts' => $attempts,
                'last_error' => $this->shortError((string) ($delivery['message'] ?? 'Peer did not accept the cluster action.')),
                'next_attempt_at' => $this->nextRetryAt($attempts),
            ])->save();

            $results[] = [
                'label' => $action->peer_label,
                'base_url' => $action->peer_base_url,
                'action' => $action->action,
                'status' => 'queued',
                'message' => (string) ($delivery['message'] ?? 'Peer did not accept the cluster action.'),
            ];
        }

        return [
            'processed' => $queuedActions->count(),
            'delivered' => $delivered,
            'queued' => $queued,
            'results' => $results,
        ];
    }

    /**
     * @return Collection<int, array{label:string,base_url:string,license_key:string}>
     */
    public function enabledPeers(): Collection
    {
        $stored = $this->filesystemSettings->stored();

        if (! (bool) Arr::get($stored, 'cluster.enabled', false)) {
            return collect();
        }

        return collect((array) Arr::get($stored, 'cluster.peers', []))
            ->filter(fn (mixed $peer): bool => is_array($peer) && (bool) Arr::get($peer, 'enabled', true) && filled((string) Arr::get($peer, 'base_url', '')))
            ->map(fn (array $peer): array => [
                'label' => trim((string) Arr::get($peer, 'label', 'Peer office')) ?: 'Peer office',
                'base_url' => rtrim(trim((string) Arr::get($peer, 'base_url', '')), '/'),
                'license_key' => trim((string) Arr::get($peer, 'license_key', '')),
            ])
            ->filter(fn (array $peer): bool => $peer['base_url'] !== '')
            ->values();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function applyLocalAction(string $action, array $payload, string $actionUuid, string $sourceNode): array
    {
        return match ($action) {
            'cto.memory_saver_profile' => $this->ctoAdvisorActions->applyLocal('memory_saver_profile', $payload),
            'cto.prefer_healthy_node' => $this->ctoAdvisorActions->applyLocal('prefer_healthy_node', $payload),
            'cto.ram_action_note' => $this->ctoAdvisorActions->applyLocal('ram_action_note', $payload),
            default => [
                'ok' => false,
                'action' => $action,
                'action_uuid' => $actionUuid,
                'source_node' => $sourceNode,
                'message' => 'Unknown cluster action.',
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function deliverPayloadToPeer(string $baseUrl, array $payload): array
    {
        $stored = $this->filesystemSettings->stored();
        $sharedSecret = trim((string) Arr::get($stored, 'cluster.shared_secret', ''));

        if ($sharedSecret === '') {
            return [
                'ok' => false,
                'message' => 'Cluster shared secret is missing.',
            ];
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withHeaders([
                    'X-Creditsoft-Cluster-Secret' => $sharedSecret,
                ])
                ->post(rtrim($baseUrl, '/').'/api/v1/cluster-actions/apply', $payload);

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'remote_status' => $response->json('data.status') ?? $response->json('status') ?? 'applied',
                    'message' => $response->json('message'),
                ];
            }

            return [
                'ok' => false,
                'http_status' => $response->status(),
                'message' => (string) ($response->json('message') ?? 'Peer rejected the cluster action.'),
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
    protected function queuePeerAction(
        string $label,
        string $baseUrl,
        string $actionUuid,
        string $sourceNode,
        string $action,
        array $payload,
        string $message,
    ): ClusterActionOutbox {
        $queuedAction = ClusterActionOutbox::query()
            ->where('action_uuid', $actionUuid)
            ->where('peer_base_url', $baseUrl)
            ->first();

        if (! $queuedAction) {
            $queuedAction = new ClusterActionOutbox([
                'action_uuid' => $actionUuid,
                'peer_base_url' => $baseUrl,
            ]);
        }

        $queuedAction->forceFill([
            'source_node' => $sourceNode,
            'peer_label' => $label,
            'action' => $action,
            'payload' => $payload,
            'status' => 'queued',
            'last_error' => $this->shortError($message),
            'next_attempt_at' => now()->addMinute(),
        ])->save();

        return $queuedAction;
    }

    protected function markQueuedPeerActionDelivered(string $baseUrl, string $actionUuid): void
    {
        if (! Schema::hasTable('cluster_action_outboxes')) {
            return;
        }

        ClusterActionOutbox::query()
            ->where('peer_base_url', $baseUrl)
            ->where('action_uuid', $actionUuid)
            ->where('status', 'queued')
            ->update([
                'status' => 'delivered',
                'last_error' => null,
                'next_attempt_at' => null,
                'delivered_at' => now(),
            ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $peerResults
     * @return array<int, string>
     */
    protected function messagesForDispatch(int $peerCount, int $delivered, int $queued, array $peerResults): array
    {
        if ($peerCount === 0) {
            return ['No enabled cluster peers were configured, so this action ran on this office node only.'];
        }

        $messages = [];

        if ($delivered > 0) {
            $messages[] = $delivered === 1
                ? 'CreditSoft applied this action to 1 peer office.'
                : sprintf('CreditSoft applied this action to %d peer offices.', $delivered);
        }

        $queuedLabels = collect($peerResults)
            ->where('status', 'queued')
            ->pluck('label')
            ->filter()
            ->values();

        if ($queuedLabels->isNotEmpty()) {
            $messages[] = $queued === 1
                ? 'CreditSoft queued this action for 1 offline peer office: '.$queuedLabels->implode(', ').'.'
                : sprintf('CreditSoft queued this action for %d offline peer offices: %s.', $queued, $queuedLabels->implode(', '));
        }

        return $messages;
    }

    protected function normalizeAction(string $action): string
    {
        $action = trim($action);

        return match ($action) {
            'memory_saver_profile' => 'cto.memory_saver_profile',
            'prefer_healthy_node' => 'cto.prefer_healthy_node',
            'ram_action_note' => 'cto.ram_action_note',
            default => $action,
        };
    }

    protected function sourceNodeLabel(): string
    {
        $stored = $this->filesystemSettings->stored();

        return trim((string) Arr::get(
            $stored,
            'cluster.office_label',
            (string) Arr::get($this->installerState->read(), 'company_name', config('app.name', 'CreditSoft Office')),
        )) ?: config('app.name', 'CreditSoft Office');
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
}
