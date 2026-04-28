<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\AiController;
use App\Http\Controllers\Settings\ApiDocsController;
use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\BackupFilesystemController;
use App\Http\Controllers\Settings\ConnectivityController;
use App\Http\Controllers\Settings\GrowthController;
use App\Http\Controllers\Settings\LicenseController;
use App\Http\Controllers\Settings\SocialController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\UserManagerController;
use App\Http\Middleware\AuthenticateOrAllowLocalBypass;
use Illuminate\Support\Facades\Route;

Route::middleware([AuthenticateOrAllowLocalBypass::class])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('settings/profile/api-keys', [ProfileController::class, 'storeApiKey'])->name('profile.api-keys.store');
    Route::delete('settings/profile/api-keys/{userApiKey}', [ProfileController::class, 'destroyApiKey'])->name('profile.api-keys.destroy');
    Route::get('settings/license', [LicenseController::class, 'edit'])->name('settings.license');
    Route::post('settings/license', [LicenseController::class, 'update'])->name('settings.license.update');
    Route::get('settings/ai', [AiController::class, 'edit'])->name('ai.edit');
    Route::put('settings/ai', [AiController::class, 'update'])->name('ai.update');
    Route::get('settings/api', [ApiDocsController::class, 'edit'])->name('api-docs.edit');
    Route::put('settings/api', [ApiDocsController::class, 'update'])->name('api-docs.update');
    Route::get('settings/filesystem', [BackupFilesystemController::class, 'edit'])->name('backup-filesystem.edit');
    Route::get('settings/social', [SocialController::class, 'edit'])->name('social.edit');
    Route::get('settings/social/{section}', [SocialController::class, 'edit'])
        ->whereIn('section', ['readiness', 'facebook', 'instagram', 'threads', 'creator-challenge', 'whatsapp', 'publishing', 'ads'])
        ->name('social.section');
    Route::get('settings/users', [UserManagerController::class, 'index'])->name('users.index');
    Route::get('settings/connectivity', [ConnectivityController::class, 'edit'])->name('connectivity.edit');
    Route::redirect('settings/cto', '/cto')->name('cto.edit');
    Route::get('settings/growth', [GrowthController::class, 'edit'])->name('growth.edit');
    Route::put('settings/connectivity', [ConnectivityController::class, 'update'])->name('connectivity.update');
    Route::put('settings/filesystem', [BackupFilesystemController::class, 'update'])->name('backup-filesystem.update');
    Route::put('settings/social', [SocialController::class, 'update'])->name('social.update');
    Route::post('settings/social/import-website-tracking', [SocialController::class, 'importWebsiteTracking'])->name('social.import-website-tracking');
    Route::post('settings/social/creator-challenge/sync', [SocialController::class, 'syncCreatorChallenge'])->name('social.creator-challenge.sync');
    Route::post('settings/social/whatsapp/sync', [SocialController::class, 'syncWhatsAppAssets'])->name('social.whatsapp.sync');
    Route::post('settings/social/api-test', [SocialController::class, 'runMetaApiTest'])->name('social.api-test');
    Route::post('settings/social/threads/api-test', [SocialController::class, 'runThreadsApiTest'])->name('social.threads.api-test');
    Route::put('settings/growth', [GrowthController::class, 'update'])->name('growth.update');
    Route::get('settings/social/meta/connect', [SocialController::class, 'connectMeta'])->name('social.meta.connect');
    Route::get('settings/social/meta/callback', [SocialController::class, 'handleMetaCallback'])->name('social.meta.callback');
    Route::get('settings/social/threads/connect', [SocialController::class, 'connectThreads'])->name('social.threads.connect');
    Route::get('settings/social/threads/callback', [SocialController::class, 'handleThreadsCallback'])->name('social.threads.callback');
    Route::post('settings/growth/activity-import', [GrowthController::class, 'importActivity'])->name('growth.activity-import');
    Route::post('settings/connectivity/website-key', [ConnectivityController::class, 'storeWebsiteKey'])->name('connectivity.website-key.store');
    Route::post('settings/connectivity/api-keys', [ConnectivityController::class, 'storeApiKey'])->name('connectivity.api-keys.store');
    Route::delete('settings/connectivity/api-keys/{userApiKey}', [ConnectivityController::class, 'destroyApiKey'])->name('connectivity.api-keys.destroy');
    Route::post('settings/users', [UserManagerController::class, 'store'])->name('users.store');
    Route::put('settings/users/{user}', [UserManagerController::class, 'update'])->name('users.update');
    Route::delete('settings/users/{user}', [UserManagerController::class, 'destroy'])->name('users.destroy');
    Route::redirect('api/docs', '/settings/api')->name('api.docs');
    Route::view('api/docs/frame', 'api-docs', [
        'specUrl' => url('/api/v1/openapi.yaml'),
    ])->name('api.docs.frame');
});

Route::middleware([AuthenticateOrAllowLocalBypass::class, 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');
    Route::get('settings/appearance', [AppearanceController::class, 'edit'])->name('appearance.edit');
    Route::put('settings/appearance', [AppearanceController::class, 'update'])->name('appearance.update');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');
});
