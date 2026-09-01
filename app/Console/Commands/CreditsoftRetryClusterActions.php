<?php

namespace App\Console\Commands;

use App\Services\CreditsoftClusterActionService;
use Illuminate\Console\Command;

class CreditsoftRetryClusterActions extends Command
{
    protected $signature = 'creditsoft:cluster-actions:retry
        {--limit=50 : Maximum queued cluster actions to retry in one run}';

    protected $description = 'Retry queued cluster button/action events for server nodes that were offline.';

    public function handle(CreditsoftClusterActionService $clusterActions): int
    {
        $limit = max(1, min(250, (int) $this->option('limit')));
        $result = $clusterActions->retryQueuedActions($limit);

        if (($result['processed'] ?? 0) === 0) {
            $this->info('No queued cluster actions are due.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Processed %d queued cluster action%s: %d delivered, %d still queued.',
            $result['processed'],
            (int) $result['processed'] === 1 ? '' : 's',
            $result['delivered'],
            $result['queued'],
        ));

        foreach ((array) ($result['results'] ?? []) as $delivery) {
            $label = (string) ($delivery['label'] ?? 'peer');
            $status = (string) ($delivery['status'] ?? 'unknown');
            $action = (string) ($delivery['action'] ?? 'action');
            $detail = (string) ($delivery['message'] ?? $delivery['remote_status'] ?? '');

            $this->line(sprintf(
                'Cluster peer %s: %s %s%s',
                $label,
                $status,
                $action,
                $detail !== '' ? " ({$detail})" : '',
            ));
        }

        return self::SUCCESS;
    }
}
