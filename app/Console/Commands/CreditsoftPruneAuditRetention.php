<?php

namespace App\Console\Commands;

use App\Models\AuditEntry;
use App\Models\BrowserCapture;
use App\Services\AuditRetentionPolicy;
use App\Services\BrowserCaptureCleanupService;
use Illuminate\Console\Command;

class CreditsoftPruneAuditRetention extends Command
{
    protected $signature = 'creditsoft:prune-audit-retention';

    protected $description = 'Prune expired audit entries and soft-deleted browser captures beyond retention.';

    public function handle(
        BrowserCaptureCleanupService $browserCaptureCleanup,
        AuditRetentionPolicy $auditRetentionPolicy,
    ): int
    {
        $retentionDays = $auditRetentionPolicy->effectiveDays();
        $cutoff = now()->subDays($retentionDays);

        $expiredAuditCount = AuditEntry::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $expiredCaptureCount = 0;

        BrowserCapture::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($captures) use ($browserCaptureCleanup, &$expiredCaptureCount): void {
                foreach ($captures as $capture) {
                    $browserCaptureCleanup->purgeCapture($capture);
                    $expiredCaptureCount++;
                }
            });

        $this->info("Pruned {$expiredAuditCount} audit entries older than {$retentionDays} days.");
        $this->info("Purged {$expiredCaptureCount} soft-deleted browser captures older than {$retentionDays} days.");

        return self::SUCCESS;
    }
}
