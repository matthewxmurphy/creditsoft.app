<?php

use App\Console\Commands\CreditsoftPruneAuditRetention;
use App\Console\Commands\CreditsoftCaptureSystemDiagnostics;
use App\Console\Commands\CreditsoftReloadConfig;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('backup:run --disable-notifications')->twiceDaily(6, 18);
Schedule::command('backup:monitor')->dailyAt('18:20');
Schedule::command(CreditsoftCaptureSystemDiagnostics::class)->everyFiveMinutes();
Schedule::command(CreditsoftReloadConfig::class)->dailyAt('02:15');
Schedule::command(CreditsoftPruneAuditRetention::class)->dailyAt('02:40');
