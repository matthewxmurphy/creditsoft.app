<?php

use App\Http\Controllers\Api\V1\ApiIndexController;
use App\Http\Controllers\Api\V1\AutomationDiscoveryController;
use App\Http\Controllers\Api\V1\ClusterApiKeyController;
use App\Http\Controllers\Api\V1\ClusterBackupController;
use App\Http\Controllers\Api\V1\ClusterCtoActionController;
use App\Http\Controllers\Api\V1\ClusterDatabaseSyncController;
use App\Http\Controllers\Api\V1\ClusterLicenseController;
use App\Http\Controllers\Api\V1\ClientPortalController;
use App\Http\Controllers\Api\V1\MigrationOperatorController;
use App\Http\Controllers\Api\V1\OfficeImpactStatsController;
use App\Http\Controllers\Api\V1\IntranetClientHandshakeController;
use App\Http\Controllers\Api\V1\OpenApiDocumentController;
use App\Http\Controllers\Settings\SocialController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/', ApiIndexController::class)->name('api.v1.index');
    Route::get('openapi.yaml', OpenApiDocumentController::class)->name('api.v1.docs');
    Route::get('meta/callback', [SocialController::class, 'handlePublicMetaCallback'])->name('api.v1.social.meta.callback');
    Route::get('threads/callback', [SocialController::class, 'handlePublicThreadsCallback'])->name('api.v1.social.threads.callback');
    Route::match(['get', 'post'], 'meta/deauthorize', [SocialController::class, 'handlePublicMetaDeauthorize'])->name('api.v1.social.meta.deauthorize');
    Route::match(['get', 'post'], 'meta/data-deletion', [SocialController::class, 'handlePublicMetaDataDeletion'])->name('api.v1.social.meta.data-deletion');
    Route::get('meta/data-deletion/{confirmationCode}', [SocialController::class, 'showPublicMetaDataDeletionStatus'])->name('api.v1.social.meta.data-deletion.status');
    Route::post('cluster-backups/receive', [ClusterBackupController::class, 'store'])->name('api.v1.cluster-backups.receive');
    Route::post('cluster-api-keys/receive', [ClusterApiKeyController::class, 'store'])->name('api.v1.cluster-api-keys.receive');
    Route::post('cluster-db-events/receive', [ClusterDatabaseSyncController::class, 'store'])->name('api.v1.cluster-db-events.receive');
    Route::post('cluster-license/receive', [ClusterLicenseController::class, 'store'])->name('api.v1.cluster-license.receive');
    Route::post('cluster-cto-actions/apply', [ClusterCtoActionController::class, 'store'])->name('api.v1.cluster-cto-actions.apply');

    Route::middleware('creditsoft.api')->group(function (): void {
        Route::get('office-stats', OfficeImpactStatsController::class)->name('api.v1.office-stats');
        Route::middleware('creditsoft.api.ability:intranet_client')
            ->get('client/handshake', IntranetClientHandshakeController::class)
            ->name('api.v1.client.handshake');
        Route::middleware('creditsoft.api.ability:migration_operator')->prefix('migration-operator')->group(function (): void {
            Route::get('ping', [MigrationOperatorController::class, 'ping'])->name('api.v1.migration-operator.ping');
            Route::post('captures', [MigrationOperatorController::class, 'storeCapture'])->name('api.v1.migration-operator.captures.store');
            Route::post('letter-templates', [MigrationOperatorController::class, 'importLetterTemplate'])->name('api.v1.migration-operator.letter-templates.store');
            Route::post('clients/sync', [MigrationOperatorController::class, 'syncClientProfile'])->name('api.v1.migration-operator.clients.sync');
        });
        Route::post('clients', [ClientPortalController::class, 'store'])->name('api.v1.clients.store');
        Route::post('portal-events', [ClientPortalController::class, 'storePortalEvent'])->name('api.v1.portal-events.store');
        Route::middleware([
            'creditsoft.api.ability:browser_companion',
            'creditsoft.license.feature:browser_companion',
        ])->group(function (): void {
            Route::get('clients/picker', [ClientPortalController::class, 'companionClients'])->name('api.v1.clients.picker');
            Route::get('browser-companion/next-account', [ClientPortalController::class, 'companionNextAccount'])->name('api.v1.browser-companion.next-account');
            Route::post('browser-companion/provider-status', [ClientPortalController::class, 'updateCompanionProviderStatus'])->name('api.v1.browser-companion.provider-status');
            Route::post('browser-companion/intake', [ClientPortalController::class, 'storeCompanionCapture'])->name('api.v1.browser-companion.intake');
            Route::post('browser-companion/client-sync', [ClientPortalController::class, 'syncCompanionClientProfile'])->name('api.v1.browser-companion.client-sync');
            Route::post('browser-companion/client-document', [ClientPortalController::class, 'storeCompanionClientDocument'])->name('api.v1.browser-companion.client-document');
            Route::post('browser-companion/automation-discovery', [AutomationDiscoveryController::class, 'store'])->name('api.v1.browser-companion.automation-discovery');
        });
        Route::get('clients/search', [ClientPortalController::class, 'search'])->name('api.v1.clients.search');
        Route::get('clients/{clientCuid}', [ClientPortalController::class, 'show'])->name('api.v1.clients.show');
        Route::patch('clients/{clientCuid}', [ClientPortalController::class, 'update'])->name('api.v1.clients.update');
        Route::patch('clients/{clientCuid}/status', [ClientPortalController::class, 'updateStatus'])->name('api.v1.clients.status.update');
        Route::get('clients/{clientCuid}/cycles', [ClientPortalController::class, 'cycles'])->name('api.v1.clients.cycles');
        Route::get('clients/{clientCuid}/score-history', [ClientPortalController::class, 'scoreHistory'])->name('api.v1.clients.score-history');
        Route::get('clients/{clientCuid}/status', [ClientPortalController::class, 'status'])->name('api.v1.clients.status');
        Route::get('clients/{clientCuid}/notes', [ClientPortalController::class, 'notes'])->name('api.v1.clients.notes');
        Route::post('clients/{clientCuid}/notes', [ClientPortalController::class, 'storeNote'])->name('api.v1.clients.notes.store');
        Route::get('clients/{clientCuid}/violations', [ClientPortalController::class, 'violations'])->name('api.v1.clients.violations');
        Route::get('clients/{clientCuid}/letters', [ClientPortalController::class, 'letters'])->name('api.v1.clients.letters');
        Route::get('clients/{clientCuid}/briefs', [ClientPortalController::class, 'briefs'])->name('api.v1.clients.briefs');
        Route::get('clients/{clientCuid}/tasks', [ClientPortalController::class, 'tasks'])->name('api.v1.clients.tasks');
        Route::post('clients/{clientCuid}/tasks', [ClientPortalController::class, 'storeTask'])->name('api.v1.clients.tasks.store');
        Route::post('clients/{clientCuid}/portal-events', [ClientPortalController::class, 'storePortalEvent'])->name('api.v1.clients.portal-events.store');
        Route::get('clients/{clientCuid}/documents', [ClientPortalController::class, 'documents'])->name('api.v1.clients.documents');
        Route::post('clients/{clientCuid}/documents', [ClientPortalController::class, 'storeDocument'])->name('api.v1.clients.documents.store');
        Route::get('clients/{clientCuid}/browser-captures', [ClientPortalController::class, 'browserCaptures'])->name('api.v1.clients.browser-captures');
        Route::post('clients/{clientCuid}/browser-captures', [ClientPortalController::class, 'storeBrowserCapture'])->name('api.v1.clients.browser-captures.store');
    });
});
