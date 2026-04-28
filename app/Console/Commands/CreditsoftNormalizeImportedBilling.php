<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\ImportedBillingHistoryNormalizer;
use Illuminate\Console\Command;

class CreditsoftNormalizeImportedBilling extends Command
{
    protected $signature = 'creditsoft:normalize-imported-billing
        {--client= : Normalize one client by id, cuid, or email}
        {--chunk=100 : Number of clients to scan per chunk}
        {--dry-run : Report what would be written without changing the database}';

    protected $description = 'Normalize imported ActivePay/FailedPay markers into billing profiles and payment rows.';

    public function handle(ImportedBillingHistoryNormalizer $normalizer): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $clientKey = trim((string) $this->option('client'));

        if ($clientKey !== '') {
            $client = Client::query()
                ->with(['billingProfile', 'payments'])
                ->whereKey(is_numeric($clientKey) ? (int) $clientKey : -1)
                ->orWhere('cuid', $clientKey)
                ->orWhereRaw('lower(email) = ?', [strtolower($clientKey)])
                ->first();

            if (! $client) {
                $this->error("Client not found: {$clientKey}");

                return self::FAILURE;
            }

            $summary = $normalizer->normalizeClient($client, $dryRun);
        } else {
            $summary = $normalizer->normalizeAll($dryRun, (int) $this->option('chunk'));
        }

        $this->table(
            ['Metric', 'Count'],
            collect($summary)->map(fn (int $value, string $key): array => [
                str_replace('_', ' ', $key),
                $value,
            ])->values()->all(),
        );

        $this->info($dryRun
            ? 'Dry run complete. No billing rows were changed.'
            : 'Imported billing normalization complete.');

        return self::SUCCESS;
    }
}
