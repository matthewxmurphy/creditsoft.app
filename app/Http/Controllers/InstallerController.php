<?php

namespace App\Http\Controllers;

use App\Services\AiProviderHealthService;
use App\Services\InstallerAdvertisementFeed;
use App\Services\InstallerBranding;
use App\Services\InstallerState;
use App\Services\LicenseCheckService;
use App\Services\BrowserCompanionBundle;
use App\Services\CreditsoftAiRegistry;
use App\Services\CreditsoftApiAccess;
use App\Services\CreditsoftClusterLicenseSyncService;
use App\Services\EnvironmentEditor;
use App\Services\IntranetClientInstallerBundle;
use App\Services\IntranetNodeInstallerBundle;
use App\Services\LicenseStateService;
use App\Services\NgrokConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InstallerController extends Controller
{
    public function show(
        InstallerAdvertisementFeed $advertisementFeed,
        InstallerState $installerState,
        BrowserCompanionBundle $browserCompanion,
        CreditsoftApiAccess $apiAccess,
        CreditsoftAiRegistry $aiRegistry,
        LicenseStateService $licenseState,
        NgrokConfigService $ngrokConfig,
        IntranetClientInstallerBundle $intranetClientInstaller,
        IntranetNodeInstallerBundle $intranetNodeInstaller,
    ): Response
    {
        abort_unless(config('creditsoft.installer.enabled', true), 404);

        $state = $installerState->read();
        $state['license'] = $licenseState->current();
        $bootstrap = $this->bootstrapState($state, $apiAccess, $aiRegistry, $ngrokConfig);

        return Inertia::render('install/Show', [
            'installer' => [
                'state' => $state,
                'steps' => $this->steps($state, $bootstrap),
                'advertisements' => $advertisementFeed->feed(),
                'licenseMode' => config('creditsoft.installer.license_mode', 'auto'),
                'licenseCheckConfigured' => count($this->licenseSources()) > 0,
                'licenseSources' => $this->licenseSources(),
                'portalUrl' => config('creditsoft.portal_url'),
                'bootstrap' => $bootstrap,
                'browserCompanion' => [
                    ...$browserCompanion->summary(),
                    'enabled' => $licenseState->allows('browser_companion'),
                    'download_url' => $licenseState->allows('browser_companion')
                        ? route('install.browser-companion.download')
                        : null,
                ],
                'intranetClientInstaller' => $intranetClientInstaller->summary($state),
                'intranetNodeInstaller' => $intranetNodeInstaller->summary($state),
            ],
        ]);
    }

    public function downloadIntranetNode(
        Request $request,
        IntranetNodeInstallerBundle $bundle,
        InstallerState $installerState,
        LicenseCheckService $licenseCheckService,
        LicenseStateService $licenseState,
    ): SymfonyResponse {
        abort_unless(config('creditsoft.installer.enabled', true), 404);

        $state = $installerState->read();
        $savedKey = Str::upper(trim((string) ($state['license_key'] ?? '')));

        if ($savedKey !== '') {
            $installerState->merge([
                'license' => $licenseCheckService->check($savedKey, [
                    'company_name' => $state['company_name'] ?? config('app.name', 'CreditSoft'),
                    'admin_email' => $state['admin_email'] ?? $request->user()?->email,
                    'tailscale_hostname' => $state['tailscale_hostname'] ?? config('creditsoft.tailscale_hostname'),
                ]),
            ]);
        }

        if ($licenseState->isLocked()) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'License grace ended. Renew the office license before generating a new intranet node installer.',
            ]);

            return redirect()->to($request->user() ? route('settings.license') : route('install.show'));
        }

        return response()->download(
            $bundle->build($installerState->read()),
            $bundle->downloadName(),
            ['Content-Type' => 'application/zip'],
        );
    }

    public function downloadIntranetClient(
        IntranetClientInstallerBundle $bundle,
    ): SymfonyResponse {
        abort_unless(config('creditsoft.installer.enabled', true), 404);

        return redirect()->away($bundle->publicDownloadUrl());
    }

    public function checkLicense(
        Request $request,
        InstallerState $installerState,
        LicenseCheckService $licenseCheckService,
        EnvironmentEditor $editor,
        CreditsoftClusterLicenseSyncService $licenseSync,
    ): RedirectResponse {
        abort_unless(config('creditsoft.installer.enabled', true), 404);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email:rfc', 'max:255'],
            'tailscale_hostname' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9][a-z0-9.-]*$/i'],
            'backup_destination' => ['required', 'in:wasabi,google_drive,dropbox,local_only'],
            'portal_sync_enabled' => ['nullable', 'boolean'],
            'report_feedback_enabled' => ['nullable', 'boolean'],
            'license_key' => ['required', 'string', 'max:255'],
        ]);

        $license = $licenseCheckService->check($validated['license_key'], [
            'company_name' => $validated['company_name'],
            'admin_email' => $validated['admin_email'],
            'tailscale_hostname' => $validated['tailscale_hostname'],
        ]);

        $editor->setMany([
            'CREDITSOFT_TAILSCALE_HOSTNAME' => (string) $validated['tailscale_hostname'],
        ]);

        $installerState->merge([
            'company_name' => $validated['company_name'],
            'admin_email' => $validated['admin_email'],
            'tailscale_hostname' => $validated['tailscale_hostname'],
            'backup_destination' => $validated['backup_destination'],
            'portal_sync_enabled' => $request->boolean('portal_sync_enabled'),
            'report_feedback_enabled' => $request->boolean('report_feedback_enabled'),
            'license_key' => Str::upper(trim((string) $validated['license_key'])),
            'license' => $license,
        ]);

        $licenseSync->queueCurrentLicenseSync();

        Inertia::flash('toast', [
            'type' => $license['valid'] ? 'success' : 'error',
            'message' => $license['message'],
        ]);

        return to_route('install.show');
    }

    public function saveConfig(
        Request $request,
        InstallerState $installerState,
        EnvironmentEditor $editor,
        CreditsoftApiAccess $apiAccess,
        NgrokConfigService $ngrokConfig,
        AiProviderHealthService $healthService,
    ): RedirectResponse {
        abort_unless(config('creditsoft.installer.enabled', true), 404);

        $validated = $request->validate([
            'ai_default_provider' => ['required', 'in:opencode_zen,openrouter_creditsoft,ollama_cloud'],
            'opencode_api_key' => ['nullable', 'string', 'max:500'],
            'openrouter_api_key' => ['nullable', 'string', 'max:500'],
            'ollama_cloud_api_key' => ['nullable', 'string', 'max:500'],
            'api_enabled' => ['required', 'boolean'],
            'website_api_token' => ['nullable', 'string', 'max:500'],
            'tailscale_required' => ['required', 'boolean'],
            'tailscale_tailnet' => ['nullable', 'string', 'max:255'],
            'tailscale_api_key' => ['nullable', 'string', 'max:1000'],
            'tailscale_api_key_expires_at' => ['nullable', 'date_format:Y-m-d'],
            'ngrok_enabled' => ['required', 'boolean'],
            'ngrok_api_only' => ['required', 'boolean'],
            'ngrok_authtoken' => ['nullable', 'string', 'max:500'],
            'ngrok_api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $currentApiToken = $apiAccess->currentToken();
        $submittedWebsiteToken = trim((string) ($validated['website_api_token'] ?? ''));
        $resolvedWebsiteToken = $submittedWebsiteToken !== '' ? $submittedWebsiteToken : ($currentApiToken ?? '');

        if (($validated['api_enabled'] ?? false) && $resolvedWebsiteToken === '') {
            $resolvedWebsiteToken = $apiAccess->issueToken();
        }

        $existingNgrokAuthtoken = trim((string) config('creditsoft.tunnels.ngrok.authtoken'));
        $submittedNgrokAuthtoken = trim((string) ($validated['ngrok_authtoken'] ?? ''));
        $resolvedNgrokAuthtoken = $submittedNgrokAuthtoken !== '' ? $submittedNgrokAuthtoken : $existingNgrokAuthtoken;

        $existingNgrokApiKey = trim((string) config('creditsoft.tunnels.ngrok.api_key'));
        $submittedNgrokApiKey = trim((string) ($validated['ngrok_api_key'] ?? ''));
        $resolvedNgrokApiKey = $submittedNgrokApiKey !== '' ? $submittedNgrokApiKey : $existingNgrokApiKey;

        $existingTailscaleApiKey = trim((string) config('creditsoft.tunnels.tailscale.api_key'));
        $submittedTailscaleApiKey = trim((string) ($validated['tailscale_api_key'] ?? ''));
        $resolvedTailscaleApiKey = $submittedTailscaleApiKey !== '' ? $submittedTailscaleApiKey : $existingTailscaleApiKey;

        $variables = [
            'CREDITSOFT_AI_DEFAULT_PROVIDER' => $validated['ai_default_provider'],
            'CREDITSOFT_TAILSCALE_REQUIRED' => ($validated['tailscale_required'] ?? false) ? 'true' : 'false',
            'CREDITSOFT_TAILSCALE_TAILNET' => (string) ($validated['tailscale_tailnet'] ?? ''),
            'CREDITSOFT_TAILSCALE_API_KEY' => $resolvedTailscaleApiKey,
            'CREDITSOFT_TAILSCALE_API_KEY_EXPIRES_AT' => (string) ($validated['tailscale_api_key_expires_at'] ?? ''),
            'CREDITSOFT_NGROK_ENABLED' => ($validated['ngrok_enabled'] ?? false) ? 'true' : 'false',
            'CREDITSOFT_NGROK_API_ONLY' => ($validated['ngrok_api_only'] ?? true) ? 'true' : 'false',
            'CREDITSOFT_NGROK_AUTHTOKEN' => $resolvedNgrokAuthtoken,
            'CREDITSOFT_NGROK_API_KEY' => $resolvedNgrokApiKey,
            'CREDITSOFT_API_ENABLED' => ($validated['api_enabled'] ?? false) ? 'true' : 'false',
            'CREDITSOFT_API_TOKEN' => $resolvedWebsiteToken,
        ];

        if (filled($validated['opencode_api_key'] ?? null)) {
            $variables['OPENCODE_API_KEY'] = (string) $validated['opencode_api_key'];
        }

        if (filled($validated['openrouter_api_key'] ?? null)) {
            $variables['OPENROUTER_API_KEY'] = (string) $validated['openrouter_api_key'];
        }

        if (filled($validated['ollama_cloud_api_key'] ?? null)) {
            $variables['OLLAMA_CLOUD_API_KEY'] = (string) $validated['ollama_cloud_api_key'];
        }

        $editor->setMany($variables);

        foreach ([
            'opencode_zen' => $variables['OPENCODE_API_KEY'] ?? null,
            'openrouter_creditsoft' => $variables['OPENROUTER_API_KEY'] ?? null,
            'ollama_cloud' => $variables['OLLAMA_CLOUD_API_KEY'] ?? null,
        ] as $provider => $key) {
            if (is_string($key) && trim($key) !== '') {
                $healthService->status($provider, $key, refresh: true);
            }
        }

        $ngrokStatus = $ngrokConfig->syncCredentials($resolvedNgrokAuthtoken, $resolvedNgrokApiKey);

        $installerState->merge([
            'ai_default_provider' => $validated['ai_default_provider'],
            'api_enabled' => (bool) $validated['api_enabled'],
            'tailscale_required' => (bool) $validated['tailscale_required'],
            'tailscale_tailnet' => (string) ($validated['tailscale_tailnet'] ?? ''),
            'tailscale_api_key_expires_at' => (string) ($validated['tailscale_api_key_expires_at'] ?? ''),
            'ngrok_enabled' => (bool) $validated['ngrok_enabled'],
            'ngrok_api_only' => (bool) $validated['ngrok_api_only'],
        ]);

        Inertia::flash('toast', [
            'type' => (filled($resolvedNgrokAuthtoken) && ! ($ngrokStatus['host_authtoken_saved'] ?? false))
                ? 'warning'
                : 'success',
            'message' => (filled($submittedWebsiteToken) || (($validated['api_enabled'] ?? false) && ! $currentApiToken))
                ? 'Installer bootstrap saved. API, AI, and tunnel settings are ready for the install pass.'
                : 'Installer bootstrap saved.',
        ]);

        return to_route('install.show');
    }

    public function uploadLogo(
        Request $request,
        InstallerState $installerState,
        InstallerBranding $installerBranding,
    ): RedirectResponse {
        abort_unless(config('creditsoft.installer.enabled', true), 404);

        $validated = $request->validate([
            'logo' => ['required', 'file', 'mimes:png,svg,jpg,jpeg', 'max:4096'],
        ]);

        $branding = $installerBranding->store($validated['logo']);

        $installerState->merge([
            'branding' => $branding,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Installer logo updated.',
        ]);

        return to_route('install.show');
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<int, array<string, string>>
     */
    private function steps(array $state, array $bootstrap): array
    {
        $profileReady = filled(data_get($state, 'company_name'))
            && filled(data_get($state, 'admin_email'))
            && filled(data_get($state, 'tailscale_hostname'));
        $brandingReady = filled(data_get($state, 'branding.logo_url'));
        $backupReady = filled(data_get($state, 'backup_destination'));
        $aiReady = collect(data_get($bootstrap, 'ai.providers', []))
            ->contains(fn (array $provider): bool => (bool) ($provider['configured'] ?? false));
        $apiReady = (bool) data_get($bootstrap, 'api.enabled', false)
            && (bool) data_get($bootstrap, 'api.token_saved', false);
        $secureAccessReady = ((bool) data_get($bootstrap, 'tailscale.required', true) && filled(data_get($bootstrap, 'tailscale.hostname')))
            || ((bool) data_get($bootstrap, 'ngrok.enabled', false) && filled(data_get($bootstrap, 'ngrok.masked_authtoken')));
        $licenseValid = (bool) data_get($state, 'license.valid', false);
        $licenseAccessState = (string) data_get($state, 'license.access_state', 'pending');
        $portalSyncEnabled = (bool) data_get($state, 'portal_sync_enabled', true);
        $reportFeedbackEnabled = (bool) data_get($state, 'report_feedback_enabled', false);
        $launchReady = $profileReady && $backupReady && $licenseValid;

        return [
            [
                'key' => 'profile',
                'title' => 'Set office profile',
                'description' => 'Name the installation, attach the admin mailbox, and pin the Tailscale hostname.',
                'status' => $profileReady ? 'complete' : 'in_progress',
            ],
            [
                'key' => 'branding',
                'title' => 'Brand the workspace',
                'description' => 'Upload the office logo so the installer, generated node package, and login screen use the office brand.',
                'status' => $brandingReady ? 'complete' : 'pending',
            ],
            [
                'key' => 'backup',
                'title' => 'Choose backup target',
                'description' => 'Default to Wasabi now, with Google Drive and Dropbox adapters ready for later.',
                'status' => $backupReady ? 'complete' : 'pending',
            ],
            [
                'key' => 'ai',
                'title' => 'Connect AI lanes',
                'description' => $aiReady
                    ? 'At least one drafting/review model is configured for this office.'
                    : 'Add OpenCode, OpenRouter, or Ollama Cloud before your first AI-assisted workflow.',
                'status' => $aiReady ? 'complete' : 'pending',
            ],
            [
                'key' => 'api',
                'title' => 'Bootstrap the partner API',
                'description' => $apiReady
                    ? 'Website and browser tooling can reach this office through a saved CreditSoft API key.'
                    : 'Generate or paste the website API key so portals and browser automation can pair with this office.',
                'status' => $apiReady ? 'complete' : 'pending',
            ],
            [
                'key' => 'access',
                'title' => 'Set remote access lanes',
                'description' => $secureAccessReady
                    ? 'Tailscale and/or ngrok details are in place for private office access and callbacks.'
                    : 'Finish Tailscale and ngrok setup so the office can be reached safely when needed.',
                'status' => $secureAccessReady ? 'complete' : 'pending',
            ],
            [
                'key' => 'portal',
                'title' => 'Review portal sync',
                'description' => $portalSyncEnabled
                    ? 'Approved briefs and sanitized case status can flow to the portal.'
                    : 'Keep portal sync off until you are ready to share approved briefs.',
                'status' => $portalSyncEnabled ? 'complete' : 'pending',
            ],
            [
                'key' => 'feedback',
                'title' => 'Privacy-safe report feedback',
                'description' => $reportFeedbackEnabled
                    ? 'Send John-Doe style report structure, score movement, and business timing only.'
                    : 'Keep analytics feedback off if this installation should stay fully local.',
                'status' => $reportFeedbackEnabled ? 'complete' : 'pending',
            ],
            [
                'key' => 'license',
                'title' => 'Run license check',
                'description' => $licenseAccessState === 'grace'
                    ? 'License expired, but the office is still inside the renewal grace window.'
                    : 'Validate the current office key before launch.',
                'status' => $licenseValid ? 'complete' : ($licenseAccessState === 'grace' ? 'in_progress' : 'in_progress'),
            ],
            [
                'key' => 'launch',
                'title' => 'Ready for launch',
                'description' => $launchReady
                    ? 'The installer profile is ready for the production install pass.'
                    : 'Finish the items above before the final install step.',
                'status' => $launchReady ? 'complete' : 'pending',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function bootstrapState(
        array $state,
        CreditsoftApiAccess $apiAccess,
        CreditsoftAiRegistry $aiRegistry,
        NgrokConfigService $ngrokConfig,
    ): array {
        $catalog = collect($aiRegistry->catalog()['providers'] ?? [])->keyBy('name');

        return [
            'ai' => [
                'default_provider' => (string) data_get($state, 'ai_default_provider', config('ai.default', 'openrouter_creditsoft')),
                'providers' => [
                    $this->aiProviderState('opencode_zen', 'OpenCode Zen', $catalog, (string) config('creditsoft.ai.providers.opencode_zen.key')),
                    $this->aiProviderState('openrouter_creditsoft', 'OpenRouter', $catalog, (string) config('ai.providers.openrouter_creditsoft.key')),
                    $this->aiProviderState('ollama_cloud', 'Ollama Cloud', $catalog, (string) config('ai.providers.ollama_cloud.key')),
                ],
            ],
            'api' => [
                'enabled' => (bool) data_get($state, 'api_enabled', config('creditsoft.api.enabled', true)),
                'masked_token' => $apiAccess->maskedToken(),
                'token_saved' => $apiAccess->hasToken(),
            ],
            'tailscale' => [
                'required' => (bool) data_get($state, 'tailscale_required', config('creditsoft.tunnels.tailscale.required', true)),
                'hostname' => (string) data_get($state, 'tailscale_hostname', config('creditsoft.tailscale_hostname')),
                'tailnet' => (string) data_get($state, 'tailscale_tailnet', config('creditsoft.tunnels.tailscale.tailnet', '')),
                'masked_api_key' => $this->maskSecret((string) config('creditsoft.tunnels.tailscale.api_key')),
                'api_key_saved' => filled((string) config('creditsoft.tunnels.tailscale.api_key')),
                'api_key_expires_at' => (string) data_get($state, 'tailscale_api_key_expires_at', config('creditsoft.tunnels.tailscale.api_key_expires_at', '')),
            ],
            'ngrok' => [
                'enabled' => (bool) data_get($state, 'ngrok_enabled', config('creditsoft.tunnels.ngrok.enabled', false)),
                'api_only' => (bool) data_get($state, 'ngrok_api_only', config('creditsoft.tunnels.ngrok.api_only', true)),
                'masked_authtoken' => $this->maskSecret((string) config('creditsoft.tunnels.ngrok.authtoken')),
                'masked_api_key' => $this->maskSecret((string) config('creditsoft.tunnels.ngrok.api_key')),
                'authtoken_saved' => filled((string) config('creditsoft.tunnels.ngrok.authtoken')),
                'api_key_saved' => filled((string) config('creditsoft.tunnels.ngrok.api_key')),
                'runtime' => Arr::only($ngrokConfig->current(), ['running', 'active_public_url', 'message']),
            ],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $catalog
     * @return array<string, mixed>
     */
    private function aiProviderState(string $key, string $fallbackLabel, $catalog, string $configuredKey): array
    {
        $provider = (array) ($catalog->get($key) ?? []);

        return [
            'key' => $key,
            'label' => (string) ($provider['label'] ?? $fallbackLabel),
            'configured' => (bool) ($provider['configured'] ?? filled($configuredKey)),
            'masked_key' => $this->maskSecret($configuredKey),
            'validation' => $provider['validation'] ?? null,
        ];
    }

    /**
     * @return array<int, array{label:string,url:string}>
     */
    private function licenseSources(): array
    {
        return collect([
            ['label' => 'API lane', 'url' => (string) config('creditsoft.installer.license_check_api_url', '')],
            ['label' => 'Portal JSON', 'url' => (string) config('creditsoft.installer.license_check_portal_url', '')],
            ['label' => 'Legacy endpoint', 'url' => (string) config('creditsoft.installer.license_check_url', '')],
        ])
            ->filter(fn (array $source): bool => trim($source['url']) !== '')
            ->unique('url')
            ->values()
            ->all();
    }

    private function maskSecret(?string $value): ?string
    {
        $secret = trim((string) $value);

        if ($secret === '') {
            return null;
        }

        $visible = min(4, max(strlen($secret) - 4, 1));

        return Str::mask($secret, '*', 0, max(strlen($secret) - $visible, 1));
    }
}
