<?php

namespace App\Console\Commands;

use App\Services\CreditsoftClusterLicenseSyncService;
use Illuminate\Console\Command;

class CreditsoftSyncClusterLicense extends Command
{
    protected $signature = 'creditsoft:cluster-license:sync
        {--no-deliver : Queue the license sync without trying immediate delivery}';

    protected $description = 'Queue the current office license state to every configured server node.';

    public function handle(CreditsoftClusterLicenseSyncService $licenseSync): int
    {
        $result = $licenseSync->queueCurrentLicenseSync(deliverNow: ! $this->option('no-deliver'));

        $this->info(sprintf(
            'Queued license sync for %d peer%s: %d delivered, %d still queued.',
            $result['queued'],
            (int) $result['queued'] === 1 ? '' : 's',
            $result['delivered'],
            $result['remaining'],
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
