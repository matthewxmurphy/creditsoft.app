<?php

use App\Console\Commands\CreditsoftCaptureSystemDiagnostics;
use App\Console\Commands\CreditsoftPruneAuditRetention;
use App\Console\Commands\CreditsoftReconcileDisputeFoxDocuments;
use App\Console\Commands\CreditsoftReloadConfig;
use App\Console\Commands\CreditsoftRetryClusterActions;
use App\Console\Commands\CreditsoftRetryClusterApiKeySyncs;
use App\Console\Commands\CreditsoftRetryClusterDatabaseSyncs;
use App\Console\Commands\CreditsoftRetryClusterLicenseSyncs;
use App\Console\Commands\CreditsoftRunDisputePlans;
use App\Console\Commands\CreditsoftSyncCrmRoster;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:run --disable-notifications')->twiceDaily(6, 18);
Schedule::command('backup:monitor')->dailyAt('18:20');
Schedule::command(CreditsoftCaptureSystemDiagnostics::class)->everyFiveMinutes();
Schedule::command(CreditsoftReconcileDisputeFoxDocuments::class)->everyFiveMinutes();
Schedule::command(CreditsoftSyncCrmRoster::class)->everyFiveMinutes()->withoutOverlapping();
Schedule::command(CreditsoftRetryClusterActions::class)->everyMinute()->withoutOverlapping();
Schedule::command(CreditsoftRetryClusterDatabaseSyncs::class)->everyMinute()->withoutOverlapping();
Schedule::command(CreditsoftRetryClusterApiKeySyncs::class)->everyMinute()->withoutOverlapping();
Schedule::command(CreditsoftRetryClusterLicenseSyncs::class)->everyMinute()->withoutOverlapping();
Schedule::command(CreditsoftRunDisputePlans::class)->everyFiveMinutes()->withoutOverlapping();
Schedule::command(CreditsoftReloadConfig::class)->dailyAt('02:15');
Schedule::command(CreditsoftPruneAuditRetention::class)->dailyAt('02:40');
