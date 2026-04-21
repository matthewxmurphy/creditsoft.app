<?php

use App\Http\Controllers\Api\V1\OpenApiDocumentController;
use App\Http\Controllers\Api\V1\PublicApiDocsController;
use App\Http\Controllers\BriefController;
use App\Http\Controllers\BrowserCaptureController;
use App\Http\Controllers\BrowserCompanionController;
use App\Http\Controllers\BureauSnapshotController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CfoController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientDocumentController;
use App\Http\Controllers\ClientImportController;
use App\Http\Controllers\ClientProviderAccountController;
use App\Http\Controllers\ComparisonController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HrController;
use App\Http\Controllers\ImportedReviewController;
use App\Http\Controllers\InstallerController;
use App\Http\Controllers\Internal\AiAssistantController;
use App\Http\Controllers\Internal\ConfigReloadController;
use App\Http\Controllers\Internal\CreditsoftBackupController;
use App\Http\Controllers\Internal\CreditsoftCrmLaunchController;
use App\Http\Controllers\Internal\CreditsoftDiagnosticsController;
use App\Http\Controllers\Internal\CreditsoftUpdateController;
use App\Http\Controllers\LetterDraftController;
use App\Http\Controllers\MigrationOperatorController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ReportingCycleController;
use App\Http\Controllers\Settings\CtoController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ViolationController;
use App\Models\Client;
use App\Models\ClientBillingProfile;
use App\Models\MetricSnapshot;
use App\Models\Task;
use App\Models\ViolationCandidate;
use App\Services\ApiDocsHostService;
use App\Services\OfficeImpactStatsService;
use App\Services\OperationalReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::get('/', function (Request $request) {
    if (app(ApiDocsHostService::class)->shouldServeDocsAtRoot($request)) {
        return app(PublicApiDocsController::class)($request, app(ApiDocsHostService::class));
    }

    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    $scheme = $request->header('x-forwarded-proto') ?: $request->getScheme();
    $host = $request->header('x-forwarded-host') ?: $request->getHttpHost();
    $origin = rtrim("{$scheme}://{$host}{$request->getBaseUrl()}", '/');
    $impact = app(OfficeImpactStatsService::class)->summary();

    $billingProfiles = ClientBillingProfile::query()->get();
    $mrr = round(
        $billingProfiles
            ->filter(fn (ClientBillingProfile $profile) => $profile->isRecurringActive())
            ->sum(fn (ClientBillingProfile $profile) => $profile->monthlyRecurringAmount()),
        2,
    );

    return inertia('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
        'preview' => [
            'origin' => $origin,
            'login_url' => "{$origin}/login",
            'installer_url' => "{$origin}/install",
            'api_url' => "{$origin}/api/v1",
            'metrics' => [
                'clients' => Client::query()->count(),
                'open_violations' => ViolationCandidate::query()->whereIn('status', ['open', 'confirmed'])->count(),
                'open_tasks' => Task::query()->whereIn('status', ['open', 'in_progress'])->count() + app(OperationalReminderService::class)->activeCount(),
                'mrr' => $mrr ?: (float) (MetricSnapshot::query()->where('key', 'mrr')->latest('bucket_date')->value('value') ?? 0),
            ],
            'impact' => $impact,
        ],
    ]);
})->name('home');

Route::get('openapi.yaml', function (Request $request, ApiDocsHostService $docsHost, OpenApiDocumentController $controller) {
    abort_unless($docsHost->shouldServeDocsAtRoot($request), 404);

    return $controller();
})->name('api.docs.public-spec');

Route::get('install', [InstallerController::class, 'show'])->name('install.show');
Route::post('install/config', [InstallerController::class, 'saveConfig'])->name('install.config');
Route::post('install/license-check', [InstallerController::class, 'checkLicense'])->name('install.license-check');
Route::post('install/logo', [InstallerController::class, 'uploadLogo'])->name('install.logo');
Route::get('install/intranet-node', [InstallerController::class, 'downloadIntranetNode'])
    ->name('install.intranet-node.download');
Route::get('install/intranet-client', [InstallerController::class, 'downloadIntranetClient'])
    ->name('install.intranet-client.download');
Route::get('install/browser-companion', [BrowserCompanionController::class, 'download'])
    ->name('install.browser-companion.download');
Route::redirect('ops', '/violations')->name('ops.redirect');
Route::get('internal/diagnostics/summary', [CreditsoftDiagnosticsController::class, 'show'])->name('internal.diagnostics.summary');
Route::get('internal/diagnostics/bandwidth', [CreditsoftDiagnosticsController::class, 'bandwidth'])->name('internal.diagnostics.bandwidth');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('calendar/social', [CalendarController::class, 'social'])->name('calendar.social');
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('migration-operator', [MigrationOperatorController::class, 'index'])->name('migration-operator.index');
    Route::post('migration-operator/api-key', [MigrationOperatorController::class, 'issueKey'])->name('migration-operator.api-key');
    Route::get('migration-operator/download', [MigrationOperatorController::class, 'download'])->name('migration-operator.download');
    Route::post('migration-operator/captures/{capture}/import-letter-template', [MigrationOperatorController::class, 'importCapture'])->name('migration-operator.captures.import-letter-template');
    Route::get('billing', [DashboardController::class, 'billing'])->name('billing.index');
    Route::put('dashboard/billing-settings', [DashboardController::class, 'updateBillingSettings'])->name('dashboard.billing-settings.update');
    Route::post('dashboard/billing-profiles', [DashboardController::class, 'storeBillingProfile'])->name('dashboard.billing-profiles.store');
    Route::post('dashboard/payments', [DashboardController::class, 'storePayment'])->name('dashboard.payments.store');
    Route::put('dashboard/zelle-settings', [DashboardController::class, 'updateZelleSettings'])->name('dashboard.zelle-settings.update');
    Route::post('dashboard/zelle/sync', [DashboardController::class, 'syncZellePayments'])->name('dashboard.zelle.sync');
    Route::put('dashboard/cash-app-settings', [DashboardController::class, 'updateCashAppSettings'])->name('dashboard.cash-app-settings.update');
    Route::post('dashboard/cash-app/requests', [DashboardController::class, 'createCashAppRequest'])->name('dashboard.cash-app.requests.store');
    Route::post('dashboard/cash-app/requests/{cashAppPaymentRequest}/sync', [DashboardController::class, 'syncCashAppRequest'])->name('dashboard.cash-app.requests.sync');
    Route::get('cto', [CtoController::class, 'edit'])->name('cto.index');
    Route::post('cto/public-speed', [CtoController::class, 'updatePublicSpeed'])->name('cto.public-speed.update');
    Route::post('cto/performance-recommendations', [CtoController::class, 'performanceRecommendations'])
        ->middleware('throttle:12,1')
        ->name('cto.performance-recommendations.generate');
    Route::post('cto/performance-action', [CtoController::class, 'performanceAction'])
        ->middleware('throttle:20,1')
        ->name('cto.performance-action.apply');
    Route::get('hr', [HrController::class, 'index'])->name('hr.index');
    Route::post('hr/profiles', [HrController::class, 'storeProfile'])->name('hr.profiles.store');
    Route::post('hr/reviews', [HrController::class, 'storeReview'])->name('hr.reviews.store');
    Route::post('hr/activity-samples', [HrController::class, 'storeActivitySample'])
        ->middleware('throttle:180,1')
        ->name('hr.activity-samples.store');
    Route::post('hr/weekly-reports/generate', [HrController::class, 'generateWeeklyReport'])->name('hr.weekly-reports.generate');
    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::post('payroll/records', [PayrollController::class, 'storeRecord'])->name('payroll.records.store');

    Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
    Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('clients/import', [ClientImportController::class, 'index'])->name('clients.import.index');
    Route::post('clients/import/disputefox', [ClientImportController::class, 'importDisputeFox'])->name('clients.import.disputefox');
    Route::post('clients/assign-unassigned', [ClientImportController::class, 'assignUnassigned'])->name('clients.assign-unassigned');
    Route::post('clients/{client}/promote', [ClientController::class, 'promoteLead'])->name('clients.promote-lead');
    Route::post('clients/{client}/fire', [ClientController::class, 'fireClient'])->name('clients.fire');
    Route::post('clients/{client}/graduate', [ClientController::class, 'graduateClient'])->name('clients.graduate');
    Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
    Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::post('clients/{client}/end-relationship', [ClientController::class, 'endRelationship'])->name('clients.end-relationship');
    Route::get('clients/{client}/audit', [ClientController::class, 'timeline'])->name('clients.audit');
    Route::get('clients/{client}/timeline', fn (Client $client) => redirect()->route('clients.audit', $client));
    Route::post('clients/{client}/providers', [ClientProviderAccountController::class, 'store'])->name('clients.providers.store');
    Route::get('clients/{client}/providers/{providerAccount}/credentials', [ClientProviderAccountController::class, 'credentials'])->name('clients.providers.credentials');
    Route::delete('clients/{client}/providers/{providerAccount}', [ClientProviderAccountController::class, 'destroy'])->name('clients.providers.destroy');
    Route::post('clients/{client}/cycles', [ReportingCycleController::class, 'store'])->name('clients.cycles.store');
    Route::post('clients/{client}/snapshots', [BureauSnapshotController::class, 'store'])->name('clients.snapshots.store');
    Route::post('clients/{client}/browser-captures', [BrowserCaptureController::class, 'store'])->name('clients.browser-captures.store');
    Route::delete('clients/{client}/browser-captures/prune-duplicates', [BrowserCaptureController::class, 'pruneDuplicates'])->name('clients.browser-captures.prune-duplicates');
    Route::delete('clients/{client}/browser-captures/{browserCapture}', [BrowserCaptureController::class, 'destroy'])->name('clients.browser-captures.destroy');
    Route::get('clients/{client}/documents/{document}/download', [ClientDocumentController::class, 'download'])->name('clients.documents.download');
    Route::post('clients/{client}/cycles/{reportingCycle}/import-review/review', [ImportedReviewController::class, 'markReviewed'])->name('clients.import-review.review');
    Route::post('clients/{client}/cycles/{reportingCycle}/import-review/dispute', [ImportedReviewController::class, 'startDispute'])->name('clients.import-review.dispute');
    Route::get('clients/{client}/compare', [ComparisonController::class, 'show'])->name('clients.compare');
    Route::get('clients/{client}/violations', [ViolationController::class, 'index'])->name('clients.violations.index');
    Route::post('clients/{client}/violations', [ViolationController::class, 'store'])->name('clients.violations.store');
    Route::post('clients/{client}/violations/scan', [ViolationController::class, 'scan'])->name('clients.violations.scan');
    Route::patch('clients/{client}/violations/{violationCandidate}', [ViolationController::class, 'update'])->name('clients.violations.update');
    Route::get('clients/{client}/notes', [NoteController::class, 'index'])->name('clients.notes.index');
    Route::post('clients/{client}/notes', [NoteController::class, 'store'])->name('clients.notes.store');
    Route::get('clients/{client}/briefs', [BriefController::class, 'index'])->name('clients.briefs.index');
    Route::post('clients/{client}/briefs', [BriefController::class, 'store'])->name('clients.briefs.store');
    Route::post('clients/{client}/briefs/ai-draft', [BriefController::class, 'generateAiDraft'])->name('clients.briefs.ai-draft');
    Route::get('clients/{client}/letters', [LetterDraftController::class, 'index'])->name('clients.letters.index');
    Route::post('clients/{client}/letters', [LetterDraftController::class, 'store'])->name('clients.letters.store');
    Route::post('clients/{client}/letters/ai-draft', [LetterDraftController::class, 'generateAiDraft'])->name('clients.letters.ai-draft');
    Route::patch('clients/{client}/letters/{letterDraft}', [LetterDraftController::class, 'update'])->name('clients.letters.update');

    Route::get('inbox', [TaskController::class, 'inbox'])->name('inbox.index');
    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::get('violations', [ViolationController::class, 'queue'])->name('violations.index');

    Route::get('cfo', [CfoController::class, 'index'])->name('cfo.index');
    Route::get('integrations/crm/launch', CreditsoftCrmLaunchController::class)->name('integrations.crm.launch');
    Route::get('browser-companion/download', [BrowserCompanionController::class, 'download'])
        ->name('browser-companion.download');

    Route::post('internal/config/reload', ConfigReloadController::class)->name('internal.config.reload');
    Route::post('internal/backups/run', [CreditsoftBackupController::class, 'run'])->name('internal.backups.run');
    Route::get('internal/backups/download/{filename}', [CreditsoftBackupController::class, 'download'])->name('internal.backups.download');
    Route::get('internal/updates/download', [CreditsoftUpdateController::class, 'download'])->name('internal.updates.download');
    Route::get('internal/updates/check', [CreditsoftUpdateController::class, 'recover'])->name('internal.updates.check.recover');
    Route::post('internal/updates/check', [CreditsoftUpdateController::class, 'check'])->name('internal.updates.check');
    Route::get('internal/updates/apply', [CreditsoftUpdateController::class, 'recover'])->name('internal.updates.apply.recover');
    Route::post('internal/updates/apply', [CreditsoftUpdateController::class, 'apply'])->name('internal.updates.apply');
    Route::post('internal/ai/chat', [AiAssistantController::class, 'store'])->name('internal.ai.chat');
});

require __DIR__.'/settings.php';
