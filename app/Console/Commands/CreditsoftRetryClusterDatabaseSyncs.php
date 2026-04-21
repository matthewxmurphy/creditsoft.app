<?php

namespace App\Console\Commands;

use App\Services\CreditsoftClusterDatabaseSyncService;
use Illuminate\Console\Command;

class CreditsoftRetryClusterDatabaseSyncs extends Command
{
    protected $signature = 'creditsoft:cluster-db:retry
        {--limit=100 : Maximum queued database events to retry in one run}';

    protected $description = 'Retry queued cluster database sync events for server nodes that were offline.';

    public function handle(CreditsoftClusterDatabaseSyncService $clusterDatabaseSyncService): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $result = $clusterDatabaseSyncService->retryQueuedSyncs($limit);

        if (($result['processed'] ?? 0) === 0) {
            $this->info('No queued cluster database sync events are due.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Processed %d queued cluster database event%s: %d delivered, %d still queued.',
            $result['processed'],
            (int) $result['processed'] === 1 ? '' : 's',
            $result['delivered'],
            $result['queued'],
        ));

        foreach ((array) ($result['results'] ?? []) as $delivery) {
            $label = (string) ($delivery['label'] ?? 'peer');
            $status = (string) ($delivery['status'] ?? 'unknown');
            $table = (string) ($delivery['table_name'] ?? 'table');
            $record = (string) ($delivery['record_key'] ?? 'record');
            $detail = (string) ($delivery['message'] ?? $delivery['remote_status'] ?? '');

            $this->line(sprintf(
                'Cluster peer %s: %s %s:%s%s',
                $label,
                $status,
                $table,
                $record,
                $detail !== '' ? " ({$detail})" : '',
            ));
        }

        return self::SUCCESS;
    }
}
