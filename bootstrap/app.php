<?php

use App\Http\Middleware\EnsureCreditsoftApiToken;
use App\Http\Middleware\EnsureCreditsoftApiAbility;
use App\Http\Middleware\EnsureCreditsoftLicenseFeature;
use App\Http\Middleware\EnsureNgrokCallbackLane;
use App\Http\Middleware\EnsureCreditsoftWorkspaceAccess;
use App\Http\Middleware\EnforceCreditsoftLicenseAccess;
use App\Http\Middleware\AllowLocalAuthBypass;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RestrictNgrokPortalExposure;
use App\Http\Middleware\RestrictReadOnlyDemoWrites;
use App\Http\Middleware\TrackUserPresence;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Env;

Env::disablePutenv();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->alias([
            'creditsoft.api' => EnsureCreditsoftApiToken::class,
            'creditsoft.api.ability' => EnsureCreditsoftApiAbility::class,
            'creditsoft.license.feature' => EnsureCreditsoftLicenseFeature::class,
        ]);

        $middleware->web(append: [
            AllowLocalAuthBypass::class,
            EnsureNgrokCallbackLane::class,
            EnsureCreditsoftWorkspaceAccess::class,
            EnforceCreditsoftLicenseAccess::class,
            RestrictReadOnlyDemoWrites::class,
            RestrictNgrokPortalExposure::class,
            HandleAppearance::class,
            TrackUserPresence::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
