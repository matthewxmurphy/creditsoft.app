<?php

use App\Models\Client;
use App\Models\ClientProfileSnapshot;
use App\Services\ClientProfileSnapshotService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$service = app(ClientProfileSnapshotService::class);
$count = 0;

Client::query()
    ->whereDoesntHave('profileSnapshots')
    ->chunkById(100, function ($clients) use ($service, &$count): void {
        foreach ($clients as $client) {
            $service->record(
                $client,
                'migration',
                [],
                ['backfilled_at' => now()->toIso8601String()],
                ['backfilled_from_clients_table' => true],
            );
            $count++;
        }
    });

echo 'backfilled='.$count.' total='.ClientProfileSnapshot::query()->count().PHP_EOL;
