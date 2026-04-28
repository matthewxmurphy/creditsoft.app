<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\AuditTrail;
use App\Services\CreditsoftApiAccess;
use App\Services\OfficeSocialSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SocialController extends Controller
{
    protected const META_OAUTH_STATE_CACHE_PREFIX = 'creditsoft.meta_oauth_state:';
    protected const META_OAUTH_RETURN_URL_SESSION_KEY = 'creditsoft.meta_oauth_return_url';
    protected const THREADS_OAUTH_STATE_PREFIX = 'threads_';
    protected const THREADS_OAUTH_STATE_CACHE_PREFIX = 'creditsoft.threads_oauth_state:';
    protected const THREADS_OAUTH_RETURN_URL_SESSION_KEY = 'creditsoft.threads_oauth_return_url';

    public function edit(
        Request $request,
        OfficeSocialSettingsService $settings,
        CreditsoftApiAccess $apiAccess,
        ?string $section = null,
    ): Response {
        $current = $settings->load();
        $configuredPublicApiStatus = $apiAccess->configuredPublicApiBaseStatus(allowLiveCheck: false);
        $callbackSource = $apiAccess->publicApiBaseSource();
        $redirectUri = $this->metaCallbackUrl($apiAccess);
        $connectState = Str::random(40);
        $threadsRedirectUri = $this->threadsCallbackUrl($apiAccess);
        $threadsConnectState = self::THREADS_OAUTH_STATE_PREFIX.Str::random(32);

        $this->storeMetaConnectState($request, $connectState);
        $this->storeThreadsConnectState($request, $apiAccess, $threadsConnectState);

        return Inertia::render('settings/Social', [
            'section' => $this->normalizeSocialSection($section),
            'settings' => $current,
            'meta' => [
                'connect_ready' => trim((string) data_get($current, 'meta.app_id')) !== '' && trim((string) data_get($current, 'meta.app_secret')) !== '',
                'connect_url' => $settings->buildMetaConnectUrl($redirectUri, $connectState),
                'callback_url' => $redirectUri,
                'api_callback_url' => $apiAccess->publicMetaCallbackUrl() ?? route('api.v1.social.meta.callback'),
                'deauthorize_url' => $this->publicMetaEndpointUrl($apiAccess, 'deauthorize', 'deauthorize.php'),
                'data_deletion_url' => $this->publicMetaEndpointUrl($apiAccess, 'data-deletion', 'data-deletion.php'),
                'allowed_domains' => $this->metaAllowedDomains($redirectUri),
                'callback_mode' => $callbackSource === 'api_domain'
                    ? 'api_domain'
                    : ($callbackSource === 'ngrok' ? 'public' : 'local'),
                'configured_callback_status' => $configuredPublicApiStatus,
                'callback_notice' => $this->metaCallbackNotice($request),
                'scopes' => $settings->metaScopes($current),
            ],
            'threads_auth' => [
                'connect_ready' => (
                    trim((string) data_get($current, 'threads.app_id')) !== ''
                    || trim((string) data_get($current, 'meta.app_id')) !== ''
                ) && (
                    trim((string) data_get($current, 'threads.app_secret')) !== ''
                    || trim((string) data_get($current, 'meta.app_secret')) !== ''
                ),
                'connect_url' => $settings->buildThreadsConnectUrl($threadsRedirectUri, $threadsConnectState),
                'callback_url' => $threadsRedirectUri,
                'scopes' => $settings->threadsScopes(),
            ],
        ]);
    }

    public function update(Request $request, OfficeSocialSettingsService $settings, AuditTrail $auditTrail): RedirectResponse
    {
        $saved = $settings->save($request->all());

        $auditTrail->record(
            $request->user(),
            'settings.social.updated',
            'Updated social, Meta, and campaign settings.',
            null,
            [
                'publishing_enabled' => (bool) data_get($saved, 'publishing.enabled', false),
                'ads_enabled' => (bool) data_get($saved, 'ads.enabled', false),
                'whatsapp_enabled' => (bool) data_get($saved, 'whatsapp.enabled', false),
                'whatsapp_lead_handoff_enabled' => (bool) data_get($saved, 'whatsapp.lead_handoff_enabled', false),
                'creator_challenge_enabled' => (bool) data_get($saved, 'creator_challenge.enabled', false),
                'creator_challenge_weekly_tracking' => (bool) data_get($saved, 'creator_challenge.track_weekly_challenge', false),
                'creator_challenge_leaderboard_depth' => (string) data_get($saved, 'creator_challenge.leaderboard_depth', ''),
                'meta_connected' => data_get($saved, 'meta.connection_status') === 'connected',
                'page_count' => count((array) data_get($saved, 'meta.available_pages', [])),
                'ad_account_count' => count((array) data_get($saved, 'meta.available_ad_accounts', [])),
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Social and Meta settings saved.',
        ]);

        return redirect()->back();
    }

    public function importWebsiteTracking(
        Request $request,
        OfficeSocialSettingsService $settings,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $result = $settings->importFromWebsiteTracking();

        if (! $result['success']) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $result['message'],
            ]);

            return redirect()->back();
        }

        $saved = $result['settings'] ?? $settings->load();

        $auditTrail->record(
            $request->user(),
            'settings.social.website_tracking_imported',
            'Imported Meta website admin settings into the office social lane.',
            null,
            [
                'imported_fields' => (int) ($result['imported_fields'] ?? 0),
                'page_id' => (string) data_get($saved, 'meta.page_id', ''),
                'pixel_id' => (string) data_get($saved, 'website_signals.meta_pixel_id', ''),
                'default_ad_account_id' => (string) data_get($saved, 'meta.default_ad_account_id', ''),
                'callback_status' => (string) data_get($saved, 'meta.connection_status', ''),
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $result['message'],
        ]);

        return redirect()->back();
    }

    public function syncCreatorChallenge(
        Request $request,
        OfficeSocialSettingsService $settings,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $result = $settings->syncCreatorChallengeLiveMetrics();
        $saved = $result['settings'] ?? $settings->load();

        $auditTrail->record(
            $request->user(),
            'settings.social.creator_challenge_synced',
            $result['success']
                ? 'Synchronized live Meta creator challenge metrics.'
                : 'Live Meta creator challenge sync failed.',
            null,
            [
                'success' => (bool) $result['success'],
                'status' => (string) data_get($saved, 'creator_challenge.live_sync.status', ''),
                'page_id' => (string) data_get($saved, 'creator_challenge.live_sync.page.id', ''),
                'post_count' => (string) data_get($saved, 'creator_challenge.live_sync.totals.posts', '0'),
                'last_error' => (string) data_get($saved, 'creator_challenge.live_sync.last_error', ''),
            ],
        );

        Inertia::flash('toast', [
            'type' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return redirect()->back();
    }

    public function syncWhatsAppAssets(
        Request $request,
        OfficeSocialSettingsService $settings,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $result = $settings->syncWhatsAppAssets();
        $saved = $result['settings'] ?? $settings->load();

        $auditTrail->record(
            $request->user(),
            'settings.social.whatsapp_assets_synced',
            $result['success']
                ? 'Synchronized WhatsApp Business assets from Meta.'
                : 'WhatsApp Business asset sync failed or returned a non-production number.',
            null,
            [
                'success' => (bool) $result['success'],
                'status' => (string) data_get($saved, 'whatsapp.connection_status', ''),
                'business_account_count' => count((array) data_get($saved, 'whatsapp.available_business_accounts', [])),
                'phone_number_count' => count((array) data_get($saved, 'whatsapp.available_phone_numbers', [])),
                'selected_phone_number_id' => (string) data_get($saved, 'whatsapp.phone_number_id', ''),
                'last_error' => (string) data_get($saved, 'whatsapp.last_error', ''),
            ],
        );

        Inertia::flash('toast', [
            'type' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return redirect()->back();
    }

    public function runMetaApiTest(
        Request $request,
        OfficeSocialSettingsService $settings,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $result = $settings->runMetaApiTestSuite();
        $saved = $result['settings'] ?? $settings->load();
        $results = (array) data_get($saved, 'meta.api_test.results', []);
        $statusCount = static fn (string $status): int => count(array_filter(
            $results,
            fn (mixed $item): bool => is_array($item) && (string) ($item['status'] ?? '') === $status,
        ));

        $auditTrail->record(
            $request->user(),
            'settings.social.meta_api_tested',
            $result['success']
                ? 'Ran Meta API testing calls.'
                : 'Meta API testing calls found blockers.',
            null,
            [
                'success' => (bool) $result['success'],
                'status' => (string) data_get($saved, 'meta.api_test.status', ''),
                'passed_count' => $statusCount('passed'),
                'failed_count' => $statusCount('failed'),
                'manual_count' => $statusCount('manual_required'),
                'skipped_count' => $statusCount('skipped'),
                'last_error' => (string) data_get($saved, 'meta.api_test.last_error', ''),
            ],
        );

        Inertia::flash('toast', [
            'type' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return redirect()->back();
    }

    public function runThreadsApiTest(
        Request $request,
        OfficeSocialSettingsService $settings,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $result = $settings->runThreadsApiTestSuite();
        $saved = $result['settings'] ?? $settings->load();
        $results = (array) data_get($saved, 'threads.api_test.results', []);
        $statusCount = static fn (string $status): int => count(array_filter(
            $results,
            fn (mixed $item): bool => is_array($item) && (string) ($item['status'] ?? '') === $status,
        ));

        $auditTrail->record(
            $request->user(),
            'settings.social.threads_api_tested',
            $result['success']
                ? 'Ran Threads API testing calls.'
                : 'Threads API testing calls found blockers.',
            null,
            [
                'success' => (bool) $result['success'],
                'status' => (string) data_get($saved, 'threads.api_test.status', ''),
                'passed_count' => $statusCount('passed'),
                'failed_count' => $statusCount('failed'),
                'manual_count' => $statusCount('manual_required'),
                'skipped_count' => $statusCount('skipped'),
                'threads_user_id' => (string) data_get($saved, 'threads.user_id', ''),
                'last_error' => (string) data_get($saved, 'threads.api_test.last_error', ''),
            ],
        );

        Inertia::flash('toast', [
            'type' => $result['success'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return redirect()->back();
    }

    public function connectThreads(
        Request $request,
        OfficeSocialSettingsService $settings,
        CreditsoftApiAccess $apiAccess,
    ): RedirectResponse {
        $state = self::THREADS_OAUTH_STATE_PREFIX.Str::random(32);

        $this->storeThreadsConnectState($request, $apiAccess, $state);

        $connectUrl = $settings->buildThreadsConnectUrl($this->threadsCallbackUrl($apiAccess), $state);

        if (! $connectUrl) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Add the Threads App ID first, or let Threads use the saved Meta App ID.',
            ]);

            return redirect()->back();
        }

        return redirect()->away($connectUrl);
    }

    public function handleThreadsCallback(
        Request $request,
        OfficeSocialSettingsService $settings,
        AuditTrail $auditTrail,
        CreditsoftApiAccess $apiAccess,
    ): RedirectResponse {
        $statePayload = $this->consumeThreadsConnectState(
            $request,
            trim((string) $request->query('state', '')),
        );

        return $this->completeThreadsCallback(
            $request,
            $settings,
            $auditTrail,
            $this->threadsCallbackUrl($apiAccess),
            $statePayload,
            route('social.section', ['section' => 'threads']),
        );
    }

    public function handlePublicThreadsCallback(
        Request $request,
        OfficeSocialSettingsService $settings,
        AuditTrail $auditTrail,
        CreditsoftApiAccess $apiAccess,
    ): RedirectResponse {
        $statePayload = $this->consumeThreadsConnectState(
            $request,
            trim((string) $request->query('state', '')),
        );

        return $this->completeThreadsCallback(
            $request,
            $settings,
            $auditTrail,
            $this->threadsCallbackUrlFromPublicRequest($request, $apiAccess),
            $statePayload,
            $this->publicThreadsFallbackReturnUrl($apiAccess),
        );
    }

    public function connectMeta(
        Request $request,
        OfficeSocialSettingsService $settings,
        CreditsoftApiAccess $apiAccess,
    ): RedirectResponse {
        $current = $settings->load();

        if (trim((string) data_get($current, 'meta.business_login_config_id')) === '') {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Save the Facebook Login for Business Configuration ID before reconnecting Meta.',
            ]);

            return redirect()->back();
        }

        $state = Str::random(40);

        $this->storeMetaConnectState($request, $state);

        $connectUrl = $settings->buildMetaConnectUrl($this->metaCallbackUrl($apiAccess), $state);

        if (! $connectUrl) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Add the Meta App ID first.',
            ]);

            return redirect()->back();
        }

        return redirect()->away($connectUrl);
    }

    public function handleMetaCallback(
        Request $request,
        OfficeSocialSettingsService $settings,
        AuditTrail $auditTrail,
        CreditsoftApiAccess $apiAccess,
    ): RedirectResponse {
        $statePayload = $this->consumeMetaConnectState(
            $request,
            trim((string) $request->query('state', '')),
        );

        return $this->completeMetaCallback(
            $request,
            $settings,
            $auditTrail,
            route('social.meta.callback'),
            $statePayload,
            route('social.edit'),
        );
    }

    public function handlePublicMetaCallback(
        Request $request,
        OfficeSocialSettingsService $settings,
        AuditTrail $auditTrail,
        CreditsoftApiAccess $apiAccess,
    ): RedirectResponse {
        $statePayload = $this->consumeMetaConnectState(
            $request,
            trim((string) $request->query('state', '')),
        );

        $fallbackReturnUrl = $this->metaReturnUrlFromPayload(
            $statePayload,
            $this->publicMetaFallbackReturnUrl($apiAccess),
        );

        return $this->completeMetaCallback(
            $request,
            $settings,
            $auditTrail,
            $this->metaCallbackUrlFromPublicRequest($request, $apiAccess),
            $statePayload,
            $fallbackReturnUrl,
        );
    }

    public function handlePublicMetaDeauthorize(Request $request, AuditTrail $auditTrail): JsonResponse
    {
        $signedRequest = trim((string) $request->input('signed_request', ''));

        if ($signedRequest !== '' || $request->isMethod('post')) {
            $auditTrail->record(
                null,
                'settings.social.meta_deauthorize_requested',
                'Meta sent a deauthorization callback.',
                null,
                [
                    'has_signed_request' => $signedRequest !== '',
                    'signed_request_hash' => $signedRequest !== '' ? sha1($signedRequest) : '',
                    'ip' => (string) $request->ip(),
                ],
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Meta deauthorization callback received.',
        ]);
    }

    public function handlePublicMetaDataDeletion(
        Request $request,
        AuditTrail $auditTrail,
        CreditsoftApiAccess $apiAccess,
    ): JsonResponse {
        $signedRequest = trim((string) $request->input('signed_request', ''));
        $confirmationCode = 'cs-meta-delete-'.Str::lower(Str::random(24));

        Cache::put($this->metaDataDeletionCacheKey($confirmationCode), [
            'received_at' => now()->toIso8601String(),
            'signed_request_hash' => $signedRequest !== '' ? sha1($signedRequest) : '',
            'ip' => (string) $request->ip(),
        ], now()->addDays(30));

        $auditTrail->record(
            null,
            'settings.social.meta_data_deletion_requested',
            'Meta sent a data deletion request callback.',
            null,
            [
                'confirmation_code' => $confirmationCode,
                'has_signed_request' => $signedRequest !== '',
                'signed_request_hash' => $signedRequest !== '' ? sha1($signedRequest) : '',
                'ip' => (string) $request->ip(),
            ],
        );

        return response()->json([
            'url' => $this->publicMetaDataDeletionStatusUrl($apiAccess, $confirmationCode),
            'confirmation_code' => $confirmationCode,
        ]);
    }

    public function showPublicMetaDataDeletionStatus(string $confirmationCode): JsonResponse
    {
        $record = Cache::get($this->metaDataDeletionCacheKey($confirmationCode));

        return response()->json([
            'success' => true,
            'confirmation_code' => $confirmationCode,
            'status' => is_array($record) ? 'received' : 'unknown',
            'message' => is_array($record)
                ? 'CreditSoft received the Meta data deletion request.'
                : 'CreditSoft does not have an active deletion request for this confirmation code.',
        ]);
    }

    protected function completeMetaCallback(
        Request $request,
        OfficeSocialSettingsService $settings,
        AuditTrail $auditTrail,
        string $redirectUri,
        ?array $statePayload,
        string $fallbackRedirect,
    ): RedirectResponse {
        $returnUrl = $this->metaReturnUrlFromPayload($statePayload, $fallbackRedirect);
        $auditUser = $request->hasSession(true) ? $request->user() : null;

        if ($statePayload === null) {
            $auditTrail->record(
                null,
                'settings.social.meta_callback_failed',
                'Meta callback returned without a matching CreditSoft login state.',
                null,
                [
                    'reason' => 'missing_state',
                    'redirect_uri' => $redirectUri,
                    'has_code' => trim((string) $request->query('code', '')) !== '',
                    'ip' => (string) $request->ip(),
                ],
            );

            return $this->redirectWithMetaNotice(
                $fallbackRedirect,
                'missing_state',
                'Meta came back, but CreditSoft could not match the login state. Start Connect Meta again and finish it in the same browser window.',
            );
        }

        $code = trim((string) $request->query('code', ''));

        if ($code === '') {
            $message = trim((string) $request->query('error_description', ''));
            $error = trim((string) $request->query('error', ''));

            if ($message === '') {
                $message = $error !== ''
                    ? 'Meta stopped the login with error: '.$error
                    : 'Meta came back without an authorization code.';
            }

            $auditTrail->record(
                $auditUser,
                'settings.social.meta_callback_failed',
                'Meta callback returned without an authorization code.',
                null,
                [
                    'reason' => 'missing_code',
                    'meta_error' => $error,
                    'redirect_uri' => $redirectUri,
                ],
            );

            return $this->redirectWithMetaNotice($returnUrl, 'failed', $message);
        }

        $result = $settings->handleMetaAuthorizationCode($code, $redirectUri);

        if (! $result['success']) {
            $message = (string) ($result['message'] ?? 'Meta login did not complete.');

            $auditTrail->record(
                $auditUser,
                'settings.social.meta_callback_failed',
                'Meta callback could not finish the token exchange.',
                null,
                [
                    'reason' => 'token_exchange_failed',
                    'message' => $message,
                    'redirect_uri' => $redirectUri,
                    'debug' => (array) ($result['debug'] ?? []),
                ],
            );

            return $this->redirectWithMetaNotice($returnUrl, 'failed', $message);
        }

        $saved = $result['settings'] ?? $settings->load();

        $auditTrail->record(
            $auditUser,
            'settings.social.meta_connected',
            'Connected Meta business login and synchronized page/ad account options.',
            null,
            [
                'page_count' => count((array) data_get($saved, 'meta.available_pages', [])),
                'ad_account_count' => count((array) data_get($saved, 'meta.available_ad_accounts', [])),
                'page_id' => (string) data_get($saved, 'meta.page_id', ''),
                'default_ad_account_id' => (string) data_get($saved, 'meta.default_ad_account_id', ''),
            ],
        );

        if ($request->hasSession(true)) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Meta account connected. Page and ad account options are ready.',
            ]);
        }

        return $this->redirectWithMetaNotice(
            $returnUrl,
            'connected',
            'Meta account connected. Page and ad account options are ready.',
        );
    }

    protected function completeThreadsCallback(
        Request $request,
        OfficeSocialSettingsService $settings,
        AuditTrail $auditTrail,
        string $redirectUri,
        ?array $statePayload,
        string $fallbackRedirect,
    ): RedirectResponse {
        $returnUrl = $this->metaReturnUrlFromPayload($statePayload, $fallbackRedirect);
        $auditUser = $request->hasSession(true) ? $request->user() : null;

        if ($statePayload === null) {
            $auditTrail->record(
                null,
                'settings.social.threads_callback_failed',
                'Threads callback returned without a matching CreditSoft login state.',
                null,
                [
                    'reason' => 'missing_state',
                    'redirect_uri' => $redirectUri,
                    'has_code' => trim((string) $request->query('code', '')) !== '',
                    'ip' => (string) $request->ip(),
                ],
            );

            return $this->redirectWithMetaNotice(
                $fallbackRedirect,
                'failed',
                'Threads came back, but CreditSoft could not match the login state. Start Threads auth again from the intranet.',
            );
        }

        $code = trim((string) $request->query('code', ''));

        if ($code === '') {
            $message = trim((string) $request->query('error_description', ''));
            $error = trim((string) $request->query('error', ''));

            if ($message === '') {
                $message = $error !== ''
                    ? 'Threads stopped the login with error: '.$error
                    : 'Threads came back without an authorization code.';
            }

            $auditTrail->record(
                $auditUser,
                'settings.social.threads_callback_failed',
                'Threads callback returned without an authorization code.',
                null,
                [
                    'reason' => 'missing_code',
                    'threads_error' => $error,
                    'redirect_uri' => $redirectUri,
                ],
            );

            return $this->redirectWithMetaNotice($returnUrl, 'failed', $message);
        }

        $result = $settings->handleThreadsAuthorizationCode($code, $redirectUri);

        if (! $result['success']) {
            $message = (string) ($result['message'] ?? 'Threads login did not complete.');

            $auditTrail->record(
                $auditUser,
                'settings.social.threads_callback_failed',
                'Threads callback could not finish the token exchange.',
                null,
                [
                    'reason' => 'token_exchange_failed',
                    'message' => $message,
                    'redirect_uri' => $redirectUri,
                    'debug' => (array) ($result['debug'] ?? []),
                ],
            );

            return $this->redirectWithMetaNotice($returnUrl, 'failed', $message);
        }

        $saved = $result['settings'] ?? $settings->load();
        $results = (array) data_get($saved, 'threads.api_test.results', []);

        $auditTrail->record(
            $auditUser,
            'settings.social.threads_connected',
            'Connected Threads OAuth and ran the Threads API smoke test.',
            null,
            [
                'status' => (string) data_get($saved, 'threads.api_test.status', ''),
                'threads_user_id' => (string) data_get($saved, 'threads.user_id', ''),
                'passed_count' => count(array_filter(
                    $results,
                    fn (mixed $item): bool => is_array($item) && (string) ($item['status'] ?? '') === 'passed',
                )),
            ],
        );

        if ($request->hasSession(true)) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Threads connected and CreditSoft ran the API smoke test.',
            ]);
        }

        return $this->redirectWithMetaNotice(
            $returnUrl,
            'connected',
            'Threads connected and CreditSoft ran the API smoke test.',
        );
    }

    protected function metaCallbackUrl(CreditsoftApiAccess $apiAccess): string
    {
        $publicCallbackUrl = $apiAccess->publicMetaOauthCallbackUrl();

        if ($publicCallbackUrl) {
            return $publicCallbackUrl;
        }

        return route('social.meta.callback');
    }

    protected function threadsCallbackUrl(CreditsoftApiAccess $apiAccess): string
    {
        $publicCallbackUrl = $apiAccess->publicMetaRootEndpointUrl('oauth.php');

        if ($publicCallbackUrl) {
            return $publicCallbackUrl;
        }

        $publicApiBaseUrl = $apiAccess->publicApiBaseUrl();

        if ($publicApiBaseUrl) {
            return rtrim($publicApiBaseUrl, '/').'/threads/callback';
        }

        return route('social.threads.callback');
    }

    protected function normalizeSocialSection(?string $section): string
    {
        $section = trim((string) $section);

        return in_array($section, ['readiness', 'facebook', 'instagram', 'threads', 'creator-challenge', 'whatsapp', 'publishing', 'ads'], true)
            ? $section
            : 'overview';
    }

    protected function metaCallbackUrlFromPublicRequest(Request $request, CreditsoftApiAccess $apiAccess): string
    {
        $forwardedCallback = trim((string) $request->header('X-CreditSoft-Public-Meta-Callback', ''));
        $forwardedHost = trim((string) $request->header('X-Forwarded-Host', ''));

        if ($forwardedCallback !== '') {
            $parts = parse_url($forwardedCallback);
            $host = is_array($parts) ? trim((string) ($parts['host'] ?? '')) : '';
            $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';

            if (in_array($scheme, ['https', 'http'], true) && $this->isAllowedPublicCallbackHost($host, $forwardedHost, $apiAccess)) {
                return $forwardedCallback;
            }
        }

        $forwardedUri = trim((string) $request->header('X-Forwarded-Uri', ''));
        $forwardedProto = strtolower(trim((string) $request->header('X-Forwarded-Proto', 'https')));
        $forwardedPath = (string) (parse_url($forwardedUri, PHP_URL_PATH) ?: '');

        if ($forwardedHost !== ''
            && in_array($forwardedProto, ['https', 'http'], true)
            && $this->isAllowedPublicCallbackHost($forwardedHost, '', $apiAccess)
            && $forwardedPath === '/oauth.php') {
            return $forwardedProto.'://'.$forwardedHost.'/oauth.php';
        }

        return $apiAccess->publicMetaCallbackUrl() ?? $this->metaCallbackUrl($apiAccess);
    }

    protected function threadsCallbackUrlFromPublicRequest(Request $request, CreditsoftApiAccess $apiAccess): string
    {
        $forwardedCallback = trim((string) $request->header('X-CreditSoft-Public-Meta-Callback', ''));
        $forwardedHost = trim((string) $request->header('X-Forwarded-Host', ''));

        if ($forwardedCallback !== '') {
            $parts = parse_url($forwardedCallback);
            $host = is_array($parts) ? trim((string) ($parts['host'] ?? '')) : '';
            $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';

            if (in_array($scheme, ['https', 'http'], true) && $this->isAllowedPublicCallbackHost($host, $forwardedHost, $apiAccess)) {
                return $forwardedCallback;
            }
        }

        return $this->threadsCallbackUrl($apiAccess);
    }

    protected function isAllowedPublicCallbackHost(string $host, string $forwardedHost, CreditsoftApiAccess $apiAccess): bool
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            return false;
        }

        $allowedHosts = array_filter(array_map(
            static fn (?string $url): string => strtolower(trim((string) parse_url((string) $url, PHP_URL_HOST))),
            [
                $apiAccess->publicMetaOauthCallbackUrl(),
                $apiAccess->publicMetaCallbackUrl(),
                $apiAccess->publicApiBaseUrl(),
                $apiAccess->configuredPublicApiBaseUrl(),
            ],
        ));

        if ($forwardedHost !== '') {
            $allowedHosts[] = strtolower(trim($forwardedHost));
        }

        return in_array($host, array_values(array_unique($allowedHosts)), true);
    }

    protected function publicMetaEndpointUrl(CreditsoftApiAccess $apiAccess, string $endpoint, string $rootFilename): string
    {
        $rootEndpoint = $apiAccess->publicMetaRootEndpointUrl($rootFilename);

        if ($rootEndpoint) {
            return $rootEndpoint;
        }

        $publicApiBaseUrl = $apiAccess->publicApiBaseUrl();

        return $publicApiBaseUrl
            ? rtrim($publicApiBaseUrl, '/').'/meta/'.$endpoint
            : url('/api/v1/meta/'.$endpoint);
    }

    protected function publicMetaDataDeletionStatusUrl(CreditsoftApiAccess $apiAccess, string $confirmationCode): string
    {
        $publicApiBaseUrl = $apiAccess->publicApiBaseUrl();

        return ($publicApiBaseUrl
            ? rtrim($publicApiBaseUrl, '/')
            : url('/api/v1')).'/meta/data-deletion/'.$confirmationCode;
    }

    /**
     * @return array<int, string>
     */
    protected function metaAllowedDomains(string $callbackUrl): array
    {
        $scheme = parse_url($callbackUrl, PHP_URL_SCHEME);
        $host = parse_url($callbackUrl, PHP_URL_HOST);
        $port = parse_url($callbackUrl, PHP_URL_PORT);

        if (! is_string($scheme) || ! is_string($host) || trim($host) === '') {
            return [];
        }

        $scheme = strtolower($scheme);
        $host = trim($host);
        $portSuffix = is_int($port) ? ':'.$port : '';
        $domains = [$scheme.'://'.$host.$portSuffix.'/'];

        if (str_starts_with($host, 'www.')) {
            $domains[] = $scheme.'://'.substr($host, 4).$portSuffix.'/';
        } else {
            $domains[] = $scheme.'://www.'.$host.$portSuffix.'/';
        }

        return array_values(array_unique(array_filter($domains)));
    }

    protected function storeMetaConnectState(Request $request, string $state): void
    {
        $returnUrl = $this->socialEditUrlForRequest($request);

        $request->session()->put('creditsoft.meta_oauth_state', $state);
        $request->session()->put(self::META_OAUTH_RETURN_URL_SESSION_KEY, $returnUrl);
        Cache::put($this->metaOauthStateCacheKey($state), ['return_url' => $returnUrl], now()->addMinutes(30));
    }

    protected function storeThreadsConnectState(Request $request, CreditsoftApiAccess $apiAccess, string $state): void
    {
        $returnUrl = $this->publicThreadsFallbackReturnUrl($apiAccess);

        if ($request->hasSession(true)) {
            $request->session()->put('creditsoft.threads_oauth_state', $state);
            $request->session()->put(self::THREADS_OAUTH_RETURN_URL_SESSION_KEY, $returnUrl);
        }

        Cache::put($this->threadsOauthStateCacheKey($state), ['return_url' => $returnUrl], now()->addMinutes(30));
    }

    protected function consumeMetaConnectState(Request $request, string $incomingState): ?array
    {
        if ($incomingState === '') {
            return null;
        }

        $expectedState = '';
        $sessionReturnUrl = '';

        if ($request->hasSession(true)) {
            $expectedState = (string) $request->session()->pull('creditsoft.meta_oauth_state', '');
            $sessionReturnUrl = trim((string) $request->session()->pull(self::META_OAUTH_RETURN_URL_SESSION_KEY, ''));
        }

        if ($expectedState !== '' && hash_equals($expectedState, $incomingState)) {
            Cache::forget($this->metaOauthStateCacheKey($incomingState));

            return [
                'return_url' => $sessionReturnUrl !== '' ? $sessionReturnUrl : route('social.edit'),
            ];
        }

        $cached = Cache::pull($this->metaOauthStateCacheKey($incomingState));

        if (is_array($cached)) {
            return $cached;
        }

        return null;
    }

    protected function consumeThreadsConnectState(Request $request, string $incomingState): ?array
    {
        if ($incomingState === '' || ! str_starts_with($incomingState, self::THREADS_OAUTH_STATE_PREFIX)) {
            return null;
        }

        $expectedState = '';
        $sessionReturnUrl = '';

        if ($request->hasSession(true)) {
            $expectedState = (string) $request->session()->pull('creditsoft.threads_oauth_state', '');
            $sessionReturnUrl = trim((string) $request->session()->pull(self::THREADS_OAUTH_RETURN_URL_SESSION_KEY, ''));
        }

        if ($expectedState !== '' && hash_equals($expectedState, $incomingState)) {
            Cache::forget($this->threadsOauthStateCacheKey($incomingState));

            return [
                'return_url' => $sessionReturnUrl !== '' ? $sessionReturnUrl : route('social.section', ['section' => 'threads']),
            ];
        }

        $cached = Cache::pull($this->threadsOauthStateCacheKey($incomingState));

        if (is_array($cached)) {
            return $cached;
        }

        return null;
    }

    protected function metaReturnUrlFromPayload(?array $payload, ?string $fallback = null): string
    {
        $returnUrl = trim((string) data_get($payload, 'return_url', ''));

        return $returnUrl !== '' ? $returnUrl : ($fallback ?: route('social.edit'));
    }

    protected function publicMetaFallbackReturnUrl(CreditsoftApiAccess $apiAccess): string
    {
        $publicOfficeUrl = $apiAccess->ngrokPublicBaseUrl();

        if ($publicOfficeUrl) {
            return rtrim($publicOfficeUrl, '/').route('social.edit', [], false);
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '' && ! str_contains($appUrl, 'localhost')) {
            return $appUrl.route('social.edit', [], false);
        }

        return 'http://127.0.0.1:8001'.route('social.edit', [], false);
    }

    protected function publicThreadsFallbackReturnUrl(CreditsoftApiAccess $apiAccess): string
    {
        $publicOfficeUrl = $apiAccess->ngrokPublicBaseUrl();

        if ($publicOfficeUrl) {
            return rtrim($publicOfficeUrl, '/').route('social.section', ['section' => 'threads'], false);
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '' && ! str_contains($appUrl, 'localhost')) {
            return $appUrl.route('social.section', ['section' => 'threads'], false);
        }

        return 'http://127.0.0.1:8001'.route('social.section', ['section' => 'threads'], false);
    }

    protected function socialEditUrlForRequest(Request $request): string
    {
        $host = trim((string) $request->getHost());

        if ($host === '') {
            return route('social.edit');
        }

        $referer = trim((string) $request->headers->get('referer', ''));

        if ($referer !== '') {
            $refererHost = trim((string) parse_url($referer, PHP_URL_HOST));
            $refererPath = trim((string) parse_url($referer, PHP_URL_PATH));

            if ($refererHost === $host
                && str_starts_with($refererPath, '/settings/social')
                && ! str_starts_with($refererPath, '/settings/social/meta')) {
                return $referer;
            }
        }

        return rtrim($request->getSchemeAndHttpHost(), '/').route('social.edit', [], false);
    }

    /**
     * @return array{type: string, message: string}|null
     */
    protected function metaCallbackNotice(Request $request): ?array
    {
        $status = trim((string) $request->query('meta_oauth', ''));

        if ($status === '') {
            return null;
        }

        $message = trim((string) $request->query('meta_message', ''));

        if ($message === '') {
            $message = match ($status) {
                'connected' => 'Meta account connected. Page and ad account options are ready.',
                'missing_state' => 'Meta came back, but CreditSoft could not match the login state. Try Connect Meta again in the same browser window.',
                default => 'Meta login did not complete. Check the setup details below and try reconnecting.',
            };
        }

        return [
            'type' => $status === 'connected' ? 'success' : 'error',
            'message' => Str::limit($message, 260, ''),
        ];
    }

    protected function redirectWithMetaNotice(string $url, string $status, string $message): RedirectResponse
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return redirect()->to($url.$separator.http_build_query([
            'meta_oauth' => $status,
            'meta_message' => Str::limit($message, 260, ''),
        ]));
    }

    protected function metaOauthStateCacheKey(string $state): string
    {
        return self::META_OAUTH_STATE_CACHE_PREFIX.$state;
    }

    protected function threadsOauthStateCacheKey(string $state): string
    {
        return self::THREADS_OAUTH_STATE_CACHE_PREFIX.$state;
    }

    protected function metaDataDeletionCacheKey(string $confirmationCode): string
    {
        return 'creditsoft.meta_data_deletion:'.$confirmationCode;
    }
}
