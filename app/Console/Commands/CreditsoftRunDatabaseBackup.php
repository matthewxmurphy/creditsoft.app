<?php

namespace App\Console\Commands;

use App\Services\CreditsoftDatabaseBackupService;
use Illuminate\Console\Command;

class CreditsoftRunDatabaseBackup extends Command
{
    protected $signature = 'creditsoft:database-backup {target=local : local, wasabi, dropbox, or google_drive}';

    protected $description = 'Create a CreditSoft database backup and mirror it to configured cluster peers.';

    public function handle(CreditsoftDatabaseBackupService $backupService): int
    {
        $target = (string) $this->argument('target');
        $result = $backupService->run($target);

        $this->info(sprintf('Created database backup: %s', $result['archive_path']));

        foreach ((array) $result['messages'] as $message) {
            $this->line($message);
        }

        foreach ((array) ($result['cluster_deliveries'] ?? []) as $delivery) {
            $status = (string) ($delivery['status'] ?? 'unknown');
            $label = (string) ($delivery['label'] ?? 'peer');

            $this->line(sprintf('Cluster peer %s: %s', $label, $status));
        }

        return self::SUCCESS;
    }
}
