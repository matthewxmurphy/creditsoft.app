<?php

namespace App\Console\Commands;

use App\Services\DisputePlanEngine;
use Illuminate\Console\Command;

class CreditsoftRunDisputePlans extends Command
{
    protected $signature = 'creditsoft:dispute-plans:run';

    protected $description = 'Queue due dispute playbook steps and flag due bureau clocks.';

    public function handle(DisputePlanEngine $engine): int
    {
        $count = $engine->runDue();
        $this->info("Processed {$count} dispute plan item(s).");

        return self::SUCCESS;
    }
}
