<?php

namespace App\Services;

use App\Models\AutomationDiscovery;
use App\Models\BrowserCapture;
use App\Models\BureauSnapshot;
use App\Models\CaseBrief;
use App\Models\CaseNote;
use App\Models\CashAppPaymentRequest;
use App\Models\Client;
use App\Models\ClientBillingProfile;
use App\Models\ClientDocument;
use App\Models\ClientPayment;
use App\Models\ClientProviderAccount;
use App\Models\ClusterDatabaseSyncOutbox;
use App\Models\EmployeeActivitySample;
use App\Models\EmployeeProfile;
use App\Models\EmployeeReview;
use App\Models\EmployeeWeeklyReport;
use App\Models\LetterDraft;
use App\Models\ManagedLetterTemplate;
use App\Models\MetricSnapshot;
use App\Models\MigrationOperatorCapture;
use App\Models\OfficeBillingSetting;
use App\Models\OfficeCashAppSetting;
use App\Models\OfficeCrmUserLink;
use App\Models\OfficeSocialSetting;
use App\Models\OfficeZelleSetting;
use App\Models\OutboundSignal;
use App\Models\PayrollRecord;
use App\Models\ReportingCycle;
use App\Models\SopRun;
use App\Models\SopTemplate;
use App\Models\Task;
use App\Models\Tradeline;
use App\Models\User;
use App\Models\UserApiKey;
use App\Models\ViolationCandidate;
use App\Models\ZellePaymentMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CreditsoftClusterDatabaseSyncService
{
    /**
     * @var array<int, class-string<Model>>
     */
    protected array $syncableModels = [
        AutomationDiscovery::class,
        BrowserCapture::class,
        BureauSnapshot::class,
        CaseBrief::class,
        CaseNote::class,
        CashAppPaymentRequest::class,
        Client::class,
        ClientBillingProfile::class,
        ClientDocument::class,
        ClientPayment::class,
        ClientProviderAccount::class,
        EmployeeActivitySample::class,
        EmployeeProfile::class,
        EmployeeReview::class,
        EmployeeWeeklyReport::class,
        LetterDraft::class,
        ManagedLetterTemplate::class,
        MetricSnapshot::class,
        MigrationOperatorCapture::class,
        OfficeBillingSetting::class,
        OfficeCashAppSetting::class,
        OfficeCrmUserLink::class,
        OfficeSocialSetting::class,
        OfficeZelleSetting::class,
        OutboundSignal::class,
        PayrollRecord::class,
        ReportingCycle::class,
        SopRun::class,
        SopTemplate::class,
        Task::class,
        Tradeline::class,
        User::class,
        UserApiKey::class,
        ViolationCandidate::class,
        ZellePaymentMessage::class,
    ];

    protected static int $recordingPaused = 0;

    public function __construct(
        protected OfficeBackupFilesystemSettingsService $filesystemSettings,
        protected InstallerState $installerState,
    ) {
    }

    public static function recordingIsPaused(): bool
    {
        return static::$recordingPaused > 0;
    }

    public static function withoutRecording(callable $callback): mixed
    {
        static::$recordingPaused++;

        try {
            return $callback();
        } finally {
            static::$recordingPaused = max(0, static::$recordingPaused - 1);
        }
    }

    public function queueModelMutation(Model $model, string $operation): void
    {
        if (static::recordingIsPaused() || ! $this->modelIsSyncable($model) || $this->isNoiseOnlyMutation($model)) {
            return;
        }

        if (! Schema::hasTable('cluster_database_sync_outboxes')) {
            return;
        }

        $stored = $this->filesystemSettings->stored();
        $clusterEnabled = (bool) Arr::get($stored, 'cluster.enabled', false);
        $sharedSecret = trim((string) Arr::get($stored, 'cluster.shared_secret', ''));
        $peers = $this->enabledPeers($stored);

        if (! $clusterEnabled || $sharedSecret === '' || $peers->isEmpty()) {
            return;
        }

        $recordKey = (string) $model->getKey();

        if ($recordKey === '') {
            return;
        }

        $eventUuid = (string) Str::uuid();
        $payload = $this->payloadForModel($model, $operation, $eventUuid, $sharedSecret);

        foreach ($peers as $peer) {
            ClusterDatabaseSyncOutbox::query()->updateOrCreate(
                [
                    'event_uuid' => $eventUuid,
                    'peer_base_url' => $peer['base_url'],
                ],
                [
                    'source_node' => $payload['source_node'],
                    'peer_label' => $peer['label'],
                    'model_type' => $payload['model_type'],
                    'table_name' => $payload['table_name'],
                    'record_key' => $payload['record_key'],
                    'operation' => $payload['operation'],
                    'payload' => $payload,
                    'status' => 'queued',
                    'last_error' => null,
                    'next_attempt_at' => now(),
                    'delivered_at' => null,
                ],
            );
        }
    }

    /**
     * @return array{processed:int,delivered:int,queued:int,results:array<int,array<string,mixed>>}
     */
    public function retryQueuedSyncs(int $limit = 100): array
    {
        if (! Schema::hasTable('cluster_database_sync_outboxes')) {
            return [
                'processed' => 0,
                'delivered' => 0,
                'queued' => 0,
                'results' => [],
            ];
        }

        $limit = max(1, min(500, $limit));
        $queuedSyncs = ClusterDatabaseSyncOutbox::query()
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
                    'table_name' => $sync->table_name,
                    'record_key' => $sync->record_key,
                    'operation' => $sync->operation,
                    'status' => 'delivered',
                    'remote_status' => $delivery['remote_status'] ?? 'applied',
                ];

                continue;
            }

            $queued++;
            $sync->forceFill([
                'status' => 'queued',
                'attempts' => $attempts,
                'last_error' => $this->shortError((string) ($delivery['message'] ?? 'Peer did not accept the database sync event.')),
                'next_attempt_at' => $this->nextRetryAt($attempts),
            ])->save();

            $results[] = [
                'label' => $sync->peer_label,
                'base_url' => $sync->peer_base_url,
                'table_name' => $sync->table_name,
                'record_key' => $sync->record_key,
                'operation' => $sync->operation,
                'status' => 'queued',
                'message' => (string) ($delivery['message'] ?? 'Peer did not accept the database sync event.'),
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
    public function receiveClusterEvent(string $sharedSecret, array $payload): array
    {
        $stored = $this->filesystemSettings->stored();
        $expectedSecret = trim((string) Arr::get($stored, 'cluster.shared_secret', ''));

        abort_if(! (bool) Arr::get($stored, 'cluster.enabled', false), 403, 'Cluster database sync is not enabled on this office.');
        abort_if($expectedSecret === '' || ! hash_equals($expectedSecret, trim($sharedSecret)), 403, 'Cluster database sync secret mismatch.');

        return static::withoutRecording(fn () => $this->applyRemoteEvent($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function applyRemoteEvent(array $payload): array
    {
        $modelType = (string) ($payload['model_type'] ?? '');
        $table = (string) ($payload['table_name'] ?? '');
        $primaryKey = (string) ($payload['primary_key'] ?? 'id');
        $recordKey = (string) ($payload['record_key'] ?? '');
        $operation = (string) ($payload['operation'] ?? 'upsert');

        if (! in_array($modelType, $this->syncableModels, true)) {
            throw new RuntimeException('Cluster database sync model is not allowed.');
        }

        /** @var Model $model */
        $model = new $modelType;

        if ($table === '' || $table !== $model->getTable() || ! Schema::hasTable($table)) {
            throw new RuntimeException('Cluster database sync table is not available on this node.');
        }

        if ($primaryKey === '' || $primaryKey !== $model->getKeyName() || $recordKey === '') {
            throw new RuntimeException('Cluster database sync record key is invalid.');
        }

        $attributes = $this->filterAttributesForTable($table, (array) ($payload['attributes'] ?? []));

        if (! array_key_exists($primaryKey, $attributes)) {
            $attributes[$primaryKey] = $recordKey;
        }

        return DB::transaction(function () use ($payload, $modelType, $table, $primaryKey, $recordKey, $operation, $attributes): array {
            $existing = DB::table($table)->where($primaryKey, $recordKey)->first();

            if ($operation === 'delete') {
                return $this->applyRemoteDelete($table, $primaryKey, $recordKey, $attributes, $existing);
            }

            if ($existing && $this->incomingIsStale($existing, $attributes)) {
                return [
                    'status' => 'skipped_stale',
                    'table_name' => $table,
                    'record_key' => $recordKey,
                ];
            }

            if ($existing) {
                DB::table($table)->where($primaryKey, $recordKey)->update(Arr::except($attributes, [$primaryKey]));
            } else {
                DB::table($table)->insert($attributes);
                $this->syncPostgresSequence($table, $primaryKey);
            }

            $fileRestored = $modelType === ClientDocument::class
                && is_array($payload['file_payload'] ?? null)
                && $this->restoreClientDocumentFile($attributes, (array) $payload['file_payload']);

            return [
                'status' => $existing ? 'updated' : 'inserted',
                'table_name' => $table,
                'record_key' => $recordKey,
                'file_restored' => $fileRestored,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function applyRemoteDelete(string $table, string $primaryKey, string $recordKey, array $attributes, mixed $existing): array
    {
        if (! $existing) {
            return [
                'status' => 'already_missing',
                'table_name' => $table,
                'record_key' => $recordKey,
            ];
        }

        if (Schema::hasColumn($table, 'deleted_at') && ! blank($attributes['deleted_at'] ?? null)) {
            if ($this->incomingIsStale($existing, $attributes)) {
                return [
                    'status' => 'skipped_stale',
                    'table_name' => $table,
                    'record_key' => $recordKey,
                ];
            }

            DB::table($table)->where($primaryKey, $recordKey)->update(Arr::except($attributes, [$primaryKey]));

            return [
                'status' => 'soft_deleted',
                'table_name' => $table,
                'record_key' => $recordKey,
            ];
        }

        DB::table($table)->where($primaryKey, $recordKey)->delete();

        return [
            'status' => 'deleted',
            'table_name' => $table,
            'record_key' => $recordKey,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function deliverPayloadToPeer(string $baseUrl, array $payload): array
    {
        try {
            $response = Http::timeout(120)
                ->acceptJson()
                ->post(rtrim($baseUrl, '/').'/api/v1/cluster-db-events/receive', $payload);

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'remote_status' => $response->json('data.status'),
                ];
            }

            return [
                'ok' => false,
                'http_status' => $response->status(),
                'message' => (string) ($response->json('message') ?? 'Peer rejected the database sync event.'),
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
        }
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

    /**
     * @return array<string, mixed>
     */
    protected function payloadForModel(Model $model, string $operation, string $eventUuid, string $sharedSecret): array
    {
        $payload = [
            'shared_secret' => $sharedSecret,
            'event_uuid' => $eventUuid,
            'source_node' => $this->sourceNodeLabel(),
            'model_type' => $model::class,
            'table_name' => $model->getTable(),
            'primary_key' => $model->getKeyName(),
            'record_key' => (string) $model->getKey(),
            'operation' => in_array($operation, ['upsert', 'delete'], true) ? $operation : 'upsert',
            'occurred_at' => now()->toIso8601String(),
            'attributes' => $this->serializeAttributes($model),
        ];

        if ($model instanceof ClientDocument && $operation !== 'delete') {
            $filePayload = $this->clientDocumentFilePayload($model);

            if ($filePayload !== null) {
                $payload['file_payload'] = $filePayload;
            }
        }

        return $payload;
    }

    /**
     * @return array{file_name:string,mime_type:?string,file_size:int,sha256:string,contents_base64:string}|null
     */
    protected function clientDocumentFilePayload(ClientDocument $document): ?array
    {
        $path = (string) $document->file_path;

        if ($path === '' || ! File::exists($path) || ! File::isFile($path)) {
            return null;
        }

        $contents = File::get($path);

        return [
            'file_name' => (string) ($document->file_name ?: basename($path)),
            'mime_type' => $document->mime_type,
            'file_size' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'contents_base64' => base64_encode($contents),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $filePayload
     */
    protected function restoreClientDocumentFile(array $attributes, array $filePayload): bool
    {
        $path = trim((string) ($attributes['file_path'] ?? ''));
        $encoded = (string) ($filePayload['contents_base64'] ?? '');

        if ($path === '' || $encoded === '') {
            return false;
        }

        $contents = base64_decode($encoded, true);

        if ($contents === false) {
            return false;
        }

        $expectedHash = trim((string) ($filePayload['sha256'] ?? ''));

        if ($expectedHash !== '' && ! hash_equals($expectedHash, hash('sha256', $contents))) {
            throw new RuntimeException('Cluster document file payload failed checksum validation.');
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeAttributes(Model $model): array
    {
        return collect($model->getAttributes())
            ->map(fn (mixed $value): mixed => $this->normalizeAttributeValue($value))
            ->all();
    }

    protected function normalizeAttributeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toDateTimeString();
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function filterAttributesForTable(string $table, array $attributes): array
    {
        $columns = Schema::getColumnListing($table);

        return collect($attributes)
            ->only($columns)
            ->map(fn (mixed $value): mixed => $this->normalizeIncomingValue($value))
            ->all();
    }

    protected function normalizeIncomingValue(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function incomingIsStale(object $existing, array $attributes): bool
    {
        $incomingUpdatedAt = $this->parseTimestamp($attributes['updated_at'] ?? $attributes['deleted_at'] ?? null);
        $existingUpdatedAt = $this->parseTimestamp($existing->updated_at ?? $existing->deleted_at ?? null);

        return $incomingUpdatedAt !== null
            && $existingUpdatedAt !== null
            && $existingUpdatedAt->greaterThan($incomingUpdatedAt);
    }

    protected function parseTimestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    protected function modelIsSyncable(Model $model): bool
    {
        return in_array($model::class, $this->syncableModels, true);
    }

    protected function isNoiseOnlyMutation(Model $model): bool
    {
        if (! $model instanceof User || ! $model->exists) {
            return false;
        }

        $changed = array_keys($model->getChanges());

        if ($changed === []) {
            return false;
        }

        return collect($changed)
            ->every(fn (string $key): bool => in_array($key, ['last_seen_at', 'last_login_at', 'remember_token', 'updated_at'], true));
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

    protected function syncPostgresSequence(string $table, string $primaryKey): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        try {
            $wrappedTable = DB::getQueryGrammar()->wrapTable($table);
            $wrappedPrimaryKey = DB::getQueryGrammar()->wrap($primaryKey);

            DB::statement(
                "SELECT setval(pg_get_serial_sequence(?, ?), COALESCE((SELECT MAX({$wrappedPrimaryKey}) FROM {$wrappedTable}), 1), true)",
                [$table, $primaryKey],
            );
        } catch (QueryException) {
            // UUID/non-serial keys do not need sequence repair.
        }
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
