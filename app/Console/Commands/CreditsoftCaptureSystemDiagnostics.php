<?php

namespace App\Console\Commands;

use App\Services\CreditsoftSystemDiagnosticsService;
use Illuminate\Console\Command;

class CreditsoftCaptureSystemDiagnostics extends Command
{
    protected $signature = 'creditsoft:diagnostics:capture';

    protected $description = 'Capture a local system diagnostics snapshot for the CTO page.';

    public function handle(CreditsoftSystemDiagnosticsService $diagnostics): int
    {
        $snapshot = $diagnostics->captureSnapshot();

        $this->info(sprintf(
            'Captured diagnostics for %s at %s.',
            $snapshot->hostname ?: 'this machine',
            optional($snapshot->captured_at)->format('M j, Y g:i A') ?? 'now'
        ));

        return self::SUCCESS;
    }
}
