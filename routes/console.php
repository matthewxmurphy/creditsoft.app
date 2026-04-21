<?php

use App\Console\Commands\CreditsoftPruneAuditRetention;
use App\Console\Commands\CreditsoftCaptureSystemDiagnostics;
use App\Console\Commands\CreditsoftReloadConfig;
use App\Console\Commands\CreditsoftRetryClusterDatabaseSyncs;
use App\Console\Commands\CreditsoftRetryClusterApiKeySyncs;
use App\Console\Commands\CreditsoftRunDatabaseBackup;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:run --disable-notifications')->twiceDaily(6, 18);
Schedule::command('backup:monitor')->dailyAt('18:20');

if ((bool) config('creditsoft.cluster.scheduled_database_backups', true)) {
    Schedule::command(CreditsoftRunDatabaseBackup::class, ['local'])
        ->everyFiveMinutes()
        ->withoutOverlapping(10);
}

Schedule::command(CreditsoftCaptureSystemDiagnostics::class)->everyFiveMinutes();
Schedule::command(CreditsoftRetryClusterApiKeySyncs::class)
    ->everyMinute()
    ->withoutOverlapping(10);
Schedule::command(CreditsoftRetryClusterDatabaseSyncs::class)
    ->everyMinute()
    ->withoutOverlapping(10);
Schedule::command(CreditsoftReloadConfig::class)->dailyAt('02:15');
Schedule::command(CreditsoftPruneAuditRetention::class)->dailyAt('02:40');
