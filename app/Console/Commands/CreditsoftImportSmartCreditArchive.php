<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SmartCreditArchiveImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CreditsoftImportSmartCreditArchive extends Command
{
    protected $signature = 'creditsoft:import-smartcredit-archive
        {directory=SmartCredit : Directory containing SmartCredit HTML saves}
        {--user= : CreditSoft user email to assign as the import actor}';

    protected $description = 'Import SmartCredit HTML archives into client dossiers';

    public function handle(SmartCreditArchiveImporter $importer): int
    {
        $directory = (string) base_path((string) $this->argument('directory'));
        $userEmail = $this->option('user');
        $actor = is_string($userEmail) && $userEmail !== ''
            ? User::query()->where('email', $userEmail)->first()
            : User::query()
                ->where('email', (string) config('creditsoft.access.owner.email', ''))
                ->orWhereHas('roles', fn ($query) => $query->where('name', 'owner_admin'))
                ->first();

        if (! is_dir($directory)) {
            $this->error("Directory not found: {$directory}");

            return self::FAILURE;
        }

        /** @var Collection<int, \App\Models\Client> $clients */
        $clients = $importer->importDirectory($directory, $actor);

        if ($clients->isEmpty()) {
            $this->warn('No SmartCredit HTML files were found to import.');

            return self::SUCCESS;
        }

        $clients->each(function ($client): void {
            $this->line("Imported {$client->display_name} ({$client->cuid})");
        });

        return self::SUCCESS;
    }
}
