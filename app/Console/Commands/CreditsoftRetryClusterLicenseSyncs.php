<?php

namespace App\Console\Commands;

use App\Services\CreditsoftClusterLicenseSyncService;
use Illuminate\Console\Command;

class CreditsoftRetryClusterLicenseSyncs extends Command
{
    protected $signature = 'creditsoft:cluster-license:retry
        {--limit=25 : Maximum queued license events to retry in one run}';

    protected $description = 'Retry queued cluster license sync events for server nodes that were offline.';

    public function handle(CreditsoftClusterLicenseSyncService $licenseSync): int
    {
        $limit = max(1, min(100, (int) $this->option('limit')));
        $result = $licenseSync->retryQueuedSyncs($limit);

        if (($result['processed'] ?? 0) === 0) {
            $this->info('No queued cluster license sync events are due.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Processed %d queued cluster license event%s: %d delivered, %d still queued.',
            $result['processed'],
            (int) $result['processed'] === 1 ? '' : 's',
            $result['delivered'],
            $result['queued'],
        ));

        foreach ((array) ($result['results'] ?? []) as $delivery) {
            $label = (string) ($delivery['label'] ?? 'peer');
            $status = (string) ($delivery['status'] ?? 'unknown');
            $detail = (string) ($delivery['message'] ?? $delivery['remote_status'] ?? '');

            $this->line(sprintf(
                'Cluster peer %s: %s%s',
                $label,
                $status,
                $detail !== '' ? " ({$detail})" : '',
            ));
        }

        return self::SUCCESS;
    }
}
