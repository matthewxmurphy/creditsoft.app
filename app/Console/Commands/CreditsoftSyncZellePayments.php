<?php

namespace App\Console\Commands;

use App\Services\OfficeZellePaymentService;
use Illuminate\Console\Command;

class CreditsoftSyncZellePayments extends Command
{
    protected $signature = 'creditsoft:zelle-sync {--limit=100 : Maximum trusted Zelle payment candidates to process}';

    protected $description = 'Sync the office Zelle payment mailbox into the intranet billing ledger.';

    public function handle(OfficeZellePaymentService $zellePayments): int
    {
        $result = $zellePayments->syncInbox((int) $this->option('limit'));

        if (empty($result['success'])) {
            $this->error((string) ($result['error'] ?? 'Zelle sync failed.'));

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Zelle sync complete. Fetched %d, processed %d, needs review %d, skipped %d, deleted %d.',
            (int) ($result['fetched'] ?? 0),
            (int) ($result['processed'] ?? 0),
            (int) ($result['needs_review'] ?? 0),
            (int) ($result['skipped'] ?? 0),
            (int) ($result['deleted'] ?? 0),
        ));

        return self::SUCCESS;
    }
}
