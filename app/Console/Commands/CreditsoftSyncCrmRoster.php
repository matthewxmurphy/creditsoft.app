<?php

namespace App\Console\Commands;

use App\Services\CreditsoftCrmRosterBridgeService;
use Illuminate\Console\Command;

class CreditsoftSyncCrmRoster extends Command
{
    protected $signature = 'creditsoft:sync-crm-roster
        {--dry-run : Count what would sync without changing the CRM sidecar}
        {--keep-unmanaged : Do not hide unmarked stock/demo CRM rows}
        {--include-terminated : Sync terminated clients as recovery opportunities instead of leaving them out of CRM}';

    protected $description = 'Sync CreditSoft clients, leads, and affiliates into the white-label CRM sidecar.';

    public function handle(CreditsoftCrmRosterBridgeService $bridge): int
    {
        $summary = $bridge->sync(
            dryRun: (bool) $this->option('dry-run'),
            hideUnmanagedSeedRows: ! (bool) $this->option('keep-unmanaged'),
            includeTerminated: (bool) $this->option('include-terminated'),
        );

        $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
