<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\UserApiKey;
use App\Services\AuditTrail;
use App\Services\ConnectivityLaneService;
use App\Services\CreditsoftApiAccess;
use App\Services\EnvironmentEditor;
use App\Services\InstallerState;
use App\Services\InstallationFeedbackPolicy;
use App\Services\NgrokConfigService;
use App\Services\NgrokTunnelService;
use App\Services\TailscaleCredentialService;
use App\Services\TailscaleStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConnectivityController extends Controller
{
    public function edit(
        Request $request,
        CreditsoftApiAccess $apiAccess,
        TailscaleStatusService $tailscaleStatus,
        TailscaleCredentialService $tailscaleCredentials,
        ConnectivityLaneService $laneService,
        NgrokConfigService $ngrokConfig,
        NgrokTunnelService $ngrokTunnel,
        InstallationFeedbackPolicy $feedbackPolicy,
    ): Response
    {
        $detectedTailscale = $tailscaleStatus->current();
        $configuredHostname = trim((string) config('creditsoft.tunnels.tailscale.hostname'));
        $configuredTailnet = trim((string) config('creditsoft.tunnels.tailscale.tailnet'));
        $ngrokRuntime = $ngrokConfig->current();

        if (
            $apiAccess->ngrokEnabled()
            && ($ngrokRuntime['host_authtoken_saved'] ?? false)
            && ($ngrokRuntime['validated'] ?? false)
            && ! ($ngrokRuntime['running'] ?? false)
        ) {
            $ngrokTunnel->ensureRunning($request->getPort(), (string) ($ngrokRuntime['config_path'] ?? ''));
            $ngrokRuntime = $ngrokConfig->current();
        }

        $apiUrls = $laneService->apiUrls($request, $apiAccess, $detectedTailscale);
        $portalUrls = $laneService->portalUrls($request, $apiAccess, $detectedTailscale);

        return Inertia::render('settings/Connectivity', [
            'tunnels' => [
                'tailscale' => [
                    'required' => $this->asBool(config('creditsoft.tunnels.tailscale.required'), true),
                    'hostname' => $this->preferredHostname($configuredHostname, $detectedTailscale),
                    'tailnet' => $this->preferredTailnet($configuredTailnet, $detectedTailscale),
                ],
                'ngrok' => [
                    'enabled' => $apiAccess->ngrokEnabled(),
                    'api_only' => $apiAccess->ngrokApiOnly(),
                    'has_authtoken' => filled((string) config('creditsoft.tunnels.ngrok.authtoken')),
                    'has_api_key' => filled((string) config('creditsoft.tunnels.ngrok.api_key')),
                    'public_base_url' => $apiAccess->ngrokPublicBaseUrl(),
                ],
            ],
            'runtime' => [
                'tailscale' => [
                    ...$detectedTailscale,
                    'credentials' => $tailscaleCredentials->current(),
                ],
                'ngrok' => $ngrokRuntime,
            ],
            'apiAccess' => [
                'enabled' => $apiAccess->isEnabled(),
                'legacy_masked_token' => $apiAccess->maskedToken(),
                'generated_legacy_token' => $request->session()->get('generated_api_token'),
                'generated_personal_token' => $request->session()->get('generated_personal_api_token'),
                'personal_keys' => $request->user()
                    ? $apiAccess->activeKeysFor($request->user())
                        ->map(fn (UserApiKey $key) => [
                            'id' => $key->getKey(),
                            'name' => $key->name,
                            'masked_token' => $key->masked_token,
                            'last_used_at' => optional($key->last_used_at)?->toIso8601String(),
                            'created_at' => optional($key->created_at)?->toIso8601String(),
                        ])
                        ->values()
                        ->all()
                    : [],
                ...$apiUrls,
                'docs_url' => url('/settings/api'),
                'spec_url' => url('/api/v1/openapi.yaml'),
            ],
            'portalAccess' => [
                ...$portalUrls,
                'login_url' => rtrim((string) $portalUrls['preferred_base_url'], '/').'/login',
                'dashboard_url' => rtrim((string) $portalUrls['preferred_base_url'], '/').'/dashboard',
                'installer_url' => rtrim((string) $portalUrls['preferred_base_url'], '/').'/install',
                'home_url' => rtrim((string) $portalUrls['preferred_base_url'], '/').'/',
            ],
            'feedback' => $feedbackPolicy->current(),
        ]);
    }

    public function update(
        Request $request,
        EnvironmentEditor $editor,
        CreditsoftApiAccess $apiAccess,
        AuditTrail $auditTrail,
        NgrokConfigService $ngrokConfig,
        NgrokTunnelService $ngrokTunnel,
        InstallerState $installerState,
    ): RedirectResponse {
        $validated = $request->validate([
            'tailscale_required' => ['required', 'boolean'],
            'tailscale_hostname' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9][a-z0-9.-]*$/i'],
            'tailscale_tailnet' => ['nullable', 'string', 'max:255'],
            'tailscale_api_key' => ['nullable', 'string', 'max:1000'],
            'tailscale_api_key_expires_at' => ['nullable', 'date_format:Y-m-d'],
            'ngrok_enabled' => ['required', 'boolean'],
            'ngrok_api_only' => ['required', 'boolean'],
            'ngrok_authtoken' => ['nullable', 'string', 'max:500'],
            'ngrok_api_key' => ['nullable', 'string', 'max:500'],
            'api_enabled' => ['required', 'boolean'],
            'rotate_api_token' => ['nullable', 'boolean'],
            'report_feedback_enabled' => ['required', 'boolean'],
        ]);

        $newToken = null;
        $existingNgrokAuthtoken = trim((string) config('creditsoft.tunnels.ngrok.authtoken'));
        $submittedNgrokAuthtoken = trim((string) ($validated['ngrok_authtoken'] ?? ''));
        $resolvedNgrokAuthtoken = $submittedNgrokAuthtoken !== '' ? $submittedNgrokAuthtoken : $existingNgrokAuthtoken;
        $existingNgrokApiKey = trim((string) config('creditsoft.tunnels.ngrok.api_key'));
        $submittedNgrokApiKey = trim((string) ($validated['ngrok_api_key'] ?? ''));
        $resolvedNgrokApiKey = $submittedNgrokApiKey !== '' ? $submittedNgrokApiKey : $existingNgrokApiKey;
        $existingTailscaleApiKey = trim((string) config('creditsoft.tunnels.tailscale.api_key'));
        $submittedTailscaleApiKey = trim((string) ($validated['tailscale_api_key'] ?? ''));
        $resolvedTailscaleApiKey = $submittedTailscaleApiKey !== '' ? $submittedTailscaleApiKey : $existingTailscaleApiKey;

        if (($validated['api_enabled'] ?? false) && (($validated['rotate_api_token'] ?? false) || ! $apiAccess->hasToken())) {
            $newToken = $apiAccess->issueToken();
        }

        $editor->setMany([
            'CREDITSOFT_TAILSCALE_REQUIRED' => ($validated['tailscale_required'] ?? false) ? 'true' : 'false',
            'CREDITSOFT_TAILSCALE_HOSTNAME' => (string) $validated['tailscale_hostname'],
            'CREDITSOFT_TAILSCALE_TAILNET' => (string) ($validated['tailscale_tailnet'] ?? ''),
            'CREDITSOFT_TAILSCALE_API_KEY' => $resolvedTailscaleApiKey,
            'CREDITSOFT_TAILSCALE_API_KEY_EXPIRES_AT' => (string) ($validated['tailscale_api_key_expires_at'] ?? ''),
            'CREDITSOFT_NGROK_ENABLED' => ($validated['ngrok_enabled'] ?? false) ? 'true' : 'false',
            'CREDITSOFT_NGROK_API_ONLY' => ($validated['ngrok_api_only'] ?? true) ? 'true' : 'false',
            'CREDITSOFT_NGROK_AUTHTOKEN' => $resolvedNgrokAuthtoken,
            'CREDITSOFT_NGROK_API_KEY' => $resolvedNgrokApiKey,
            'CREDITSOFT_NGROK_DOMAIN' => '',
            'CREDITSOFT_API_ENABLED' => ($validated['api_enabled'] ?? false) ? 'true' : 'false',
            'CREDITSOFT_API_TOKEN' => $newToken ?? ($apiAccess->currentToken() ?? ''),
        ]);

        $installerState->merge([
            'report_feedback_enabled' => (bool) $validated['report_feedback_enabled'],
        ]);

        $ngrokStatus = $ngrokConfig->syncCredentials($resolvedNgrokAuthtoken, $resolvedNgrokApiKey);
        $ngrokTunnelStatus = null;

        if (($validated['ngrok_enabled'] ?? false) && filled($resolvedNgrokAuthtoken) && ($ngrokStatus['validated'] ?? false)) {
            $ngrokTunnelStatus = $ngrokTunnel->ensureRunning($request->getPort(), (string) ($ngrokStatus['config_path'] ?? ''));
        }

        if ($newToken) {
            $request->session()->flash('generated_api_token', $newToken);
        }

        $auditTrail->record(
            $request->user(),
            'settings.connectivity.updated',
            'Updated tunnel and partner API settings.',
            null,
            [
                'tailscale_required' => $validated['tailscale_required'],
                'tailscale_hostname' => $validated['tailscale_hostname'],
                'tailscale_api_key_saved' => filled($resolvedTailscaleApiKey),
                'tailscale_api_key_expires_at' => $validated['tailscale_api_key_expires_at'] ?? null,
                'ngrok_enabled' => $validated['ngrok_enabled'],
                'ngrok_api_only' => $validated['ngrok_api_only'],
                'ngrok_host_config_path' => $ngrokStatus['config_path'] ?? null,
                'ngrok_host_config_saved' => $ngrokStatus['host_authtoken_saved'] ?? false,
                'ngrok_api_key_saved' => $ngrokStatus['host_api_key_saved'] ?? false,
                'ngrok_host_config_validated' => $ngrokStatus['validated'] ?? false,
                'api_enabled' => $validated['api_enabled'],
                'api_token_rotated' => (bool) $newToken,
                'report_feedback_enabled' => (bool) $validated['report_feedback_enabled'],
            ],
        );

        Inertia::flash('toast', [
            'type' => (
                (filled($resolvedNgrokAuthtoken) && ! ($ngrokStatus['host_authtoken_saved'] ?? false))
                || (filled($resolvedNgrokApiKey) && ! ($ngrokStatus['host_api_key_saved'] ?? false))
            ) ? 'warning' : 'success',
            'message' => $newToken
                ? 'Connectivity settings updated, the ngrok host file was synced, and a new API token was generated.'
                : (($ngrokTunnelStatus['running'] ?? false)
                    ? 'Connectivity settings updated and ngrok is live.'
                    : (($ngrokStatus['message'] ?? null) ?: 'Connectivity settings updated.')),
        ]);

        return redirect()->route('connectivity.edit');
    }

    public function storeApiKey(
        Request $request,
        CreditsoftApiAccess $apiAccess,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        abort_unless($user, 403);

        $name = trim((string) ($validated['name'] ?? ''));
        if ($name === '') {
            $name = 'Personal API key';
        }

        $plainToken = $apiAccess->issueUserToken($user, $name, ['partner_api', 'browser_companion', 'intranet_client']);
        $request->session()->flash('generated_personal_api_token', $plainToken);

        $auditTrail->record(
            $user,
            'settings.api_key.created',
            "Generated personal API key {$name}.",
            null,
            [
                'key_name' => $name,
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Personal API key generated. Copy it now; the full value is only shown once.',
        ]);

        return redirect()->route('connectivity.edit');
    }

    public function storeWebsiteKey(
        Request $request,
        CreditsoftApiAccess $apiAccess,
        EnvironmentEditor $editor,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user, 403);

        $newToken = $apiAccess->issueToken();

        $editor->setMany([
            'CREDITSOFT_API_TOKEN' => $newToken,
            'CREDITSOFT_API_ENABLED' => $apiAccess->isEnabled() ? 'true' : 'false',
        ]);

        $request->session()->flash('generated_api_token', $newToken);

        $auditTrail->record(
            $user,
            'settings.api_key.website_rotated',
            'Generated a new website and portal API key.',
            null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'New website and portal API key generated. Copy it now; the full value is only shown once.',
        ]);

        return redirect()->route('connectivity.edit');
    }

    public function destroyApiKey(
        Request $request,
        UserApiKey $userApiKey,
        CreditsoftApiAccess $apiAccess,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user, 403);

        if ($userApiKey->user_id !== $user->getKey() && ! $user->hasRole('owner_admin')) {
            abort(403);
        }

        $apiAccess->revokeUserKey($userApiKey);

        $auditTrail->record(
            $user,
            'settings.api_key.revoked',
            "Revoked personal API key {$userApiKey->name}.",
            null,
            [
                'key_id' => $userApiKey->getKey(),
                'key_name' => $userApiKey->name,
                'key_owner_user_id' => $userApiKey->user_id,
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Personal API key revoked.',
        ]);

        return redirect()->route('connectivity.edit');
    }

    protected function asBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
        }

        return $default;
    }

    /**
     * @param  array<string, mixed>  $detectedTailscale
     */
    protected function preferredHostname(string $configuredHostname, array $detectedTailscale): string
    {
        if ($configuredHostname !== '' && $configuredHostname !== 'creditsoft-intranet') {
            return $configuredHostname;
        }

        $detectedHostname = is_string($detectedTailscale['hostname'] ?? null)
            ? trim((string) $detectedTailscale['hostname'])
            : '';

        if ($detectedHostname !== '') {
            return $detectedHostname;
        }

        return $configuredHostname !== '' ? $configuredHostname : 'creditsoft-intranet';
    }

    /**
     * @param  array<string, mixed>  $detectedTailscale
     */
    protected function preferredTailnet(string $configuredTailnet, array $detectedTailscale): string
    {
        if ($configuredTailnet !== '') {
            return $configuredTailnet;
        }

        return (string) ($detectedTailscale['tailnet'] ?? '');
    }
}
