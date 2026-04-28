<?php

namespace App\Console\Commands;

use App\Services\CreditsoftClusterApiKeyService;
use Illuminate\Console\Command;

class CreditsoftRetryClusterApiKeySyncs extends Command
{
    protected $signature = 'creditsoft:api-key:retry-cluster-syncs
        {--limit=25 : Maximum queued peer deliveries to retry in one run}';

    protected $description = 'Retry queued cluster API key syncs for server nodes that were offline.';

    public function handle(CreditsoftClusterApiKeyService $clusterApiKeyService): int
    {
        $limit = max(1, min(100, (int) $this->option('limit')));
        $result = $clusterApiKeyService->retryQueuedSyncs($limit);

        if (($result['processed'] ?? 0) === 0) {
            $this->info('No queued cluster API key syncs are due.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Processed %d queued cluster API key sync%s: %d delivered, %d still queued.',
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
