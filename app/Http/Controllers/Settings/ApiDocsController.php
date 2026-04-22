<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\ConnectivityLaneService;
use App\Services\CreditsoftApiAccess;
use App\Services\EnvironmentEditor;
use App\Services\TailscaleStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiDocsController extends Controller
{
    public function edit(
        Request $request,
        CreditsoftApiAccess $apiAccess,
        TailscaleStatusService $tailscaleStatus,
        ConnectivityLaneService $laneService,
    ): Response
    {
        $tailscale = $tailscaleStatus->current();
        $apiUrls = $laneService->apiUrls($request, $apiAccess, $tailscale);
        $configuredPublicApiStatus = $apiAccess->configuredPublicApiBaseStatus();
        $metaCallbackSource = $apiAccess->publicApiBaseSource();
        $recommendedBridgeMode = filled($tailscale['dns_name'] ?? null) || filled($tailscale['ipv4'] ?? null)
            ? 'tailscale'
            : ($apiAccess->ngrokPublicBaseUrl() ? 'ngrok' : 'local');
        $recommendedBridgeTarget = $recommendedBridgeMode === 'tailscale'
            ? ($apiUrls['tailnet_base_url'] ?? $apiUrls['local_base_url'])
            : ($recommendedBridgeMode === 'ngrok'
                ? ($apiAccess->ngrokPublicBaseUrl() ? rtrim((string) $apiAccess->ngrokPublicBaseUrl(), '/').'/api/v1' : $apiUrls['local_base_url'])
                : $apiUrls['local_base_url']);

        return Inertia::render('settings/ApiDocs', [
            'apiAccess' => [
                'embedded_url' => url('/api/docs/frame'),
                'spec_url' => url('/api/v1/openapi.yaml'),
                ...$apiUrls,
                'public_api_base_url' => $apiAccess->rawConfiguredPublicApiBaseUrl(),
                'normalized_public_api_base_url' => $configuredPublicApiStatus['normalized_base_url'],
                'configured_public_api_status' => $configuredPublicApiStatus,
                'meta_callback_url' => $apiAccess->publicMetaOauthCallbackUrl() ?? route('social.meta.callback'),
                'meta_callback_source' => $metaCallbackSource === 'api_domain'
                    ? 'api_domain'
                    : ($metaCallbackSource === 'ngrok' ? 'ngrok' : 'local'),
                'masked_token' => $apiAccess->maskedToken(),
                'website_bridge' => [
                    'recommended_mode' => $recommendedBridgeMode,
                    'recommended_target_url' => $recommendedBridgeTarget,
                    'tailscale_running' => (bool) ($tailscale['running'] ?? false),
                    'tailscale_dns_name' => $tailscale['dns_name'] ?? null,
                    'ngrok_base_url' => $apiAccess->ngrokPublicBaseUrl(),
                    'updates_feed_url' => (string) config('creditsoft.updates.feed_url'),
                    'dropin_path' => '/api/v1/index.php',
                    'wordpress_plugin_path' => 'wp-content/plugins/creditsoft-api-bridge/',
                    'wordpress_plugin_zip_url' => url('/downloads/creditsoft-api-bridge.zip'),
                ],
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/',
                        'summary' => 'Read the API overview and confirm the live base URL for this host.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/office-stats',
                        'summary' => 'Read website-safe office impact metrics for proof cards and dashboards.',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/clients',
                        'summary' => 'Create a lead or client record from a website form or lead manager.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/clients/picker',
                        'summary' => 'Return a compact recent-client list for the browser companion, with assigned clients prioritized for the current token owner.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/browser-companion/next-account',
                        'summary' => 'Return the next SmartCredit-ready client/provider account, prioritizing assigned clients for the current token owner.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/clients/search',
                        'summary' => 'Search local clients by email or name before posting companion captures.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/clients/{cuid}',
                        'summary' => 'Read the saved client profile and contact fields from a portal or lead manager.',
                    ],
                    [
                        'method' => 'PATCH',
                        'path' => '/clients/{cuid}',
                        'summary' => 'Update contact info or case basics while preserving a local audit trail.',
                    ],
                    [
                        'method' => 'PATCH',
                        'path' => '/clients/{cuid}/status',
                        'summary' => 'Update the case stage or score and preserve the status-change audit trail.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/clients/{cuid}/cycles',
                        'summary' => 'List reporting cycles and import counts for a client.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/clients/{cuid}/score-history',
                        'summary' => 'Read score and status changes from the audit-visible client history.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/clients/{cuid}/status',
                        'summary' => 'Read the current case stage, score snapshot, and reporting-cycle summary.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/clients/{cuid}/notes',
                        'summary' => 'List case notes that are available to the portal or lead manager.',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/clients/{cuid}/notes',
                        'summary' => 'Create a note and write a local audit trail entry.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/clients/{cuid}/violations',
                        'summary' => 'List confirmed or pending violation candidates for the client.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/clients/{cuid}/letters',
                        'summary' => 'Read approved dispute and follow-up letters for the client.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/clients/{cuid}/briefs',
                        'summary' => 'Read shareable case briefs that are safe to expose outside the operator workspace.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/clients/{cuid}/tasks',
                        'summary' => 'List tasks for the client and assigned review work.',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/clients/{cuid}/tasks',
                        'summary' => 'Create a task and write an audit trail entry.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/clients/{cuid}/documents',
                        'summary' => 'List portal-safe case documents that are available outside the operator workspace.',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/clients/{cuid}/documents',
                        'summary' => 'Upload a client document and write a local audit trail entry.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/clients/{cuid}/browser-captures',
                        'summary' => 'List uploaded browser captures and saved evidence files.',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/clients/{cuid}/browser-captures',
                        'summary' => 'Upload browser evidence or a Safari webarchive and audit the import.',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/browser-companion/intake',
                        'summary' => 'Resolve a client by email or name and ingest a browser companion capture into the right cycle.',
                    ],
                ],
            ],
        ]);
    }

    public function update(
        Request $request,
        EnvironmentEditor $editor,
        CreditsoftApiAccess $apiAccess,
    ): RedirectResponse
    {
        $existingPublicApiBaseUrl = $apiAccess->rawConfiguredPublicApiBaseUrl();
        $validated = $request->validate([
            'public_api_base_url' => ['nullable', 'url', 'max:255'],
        ]);

        $rawPublicApiBaseUrl = trim((string) ($validated['public_api_base_url'] ?? ''));
        $editor->setMany([
            'CREDITSOFT_API_PUBLIC_BASE_URL' => $rawPublicApiBaseUrl,
        ]);
        $apiAccess->forgetPublicApiBaseStatusCache($existingPublicApiBaseUrl);
        $apiAccess->forgetPublicApiBaseStatusCache($rawPublicApiBaseUrl);

        $configuredStatus = $apiAccess->inspectPublicApiBaseUrl($rawPublicApiBaseUrl);

        Inertia::flash('toast', [
            'type' => $rawPublicApiBaseUrl !== '' && $configuredStatus['state'] !== 'verified' ? 'warning' : 'success',
            'message' => $rawPublicApiBaseUrl !== ''
                ? ($configuredStatus['state'] === 'verified'
                    ? 'Website bridge domain saved and verified. Meta callbacks can stay on this stable host.'
                    : 'Website bridge domain saved, but CreditSoft could not verify /api/v1/meta/callback there yet. Meta will keep using ngrok or localhost until that host is actually live.')
                : 'Public API base cleared. CreditSoft will fall back to ngrok or localhost for Meta callbacks.',
        ]);

        return redirect()->route('api-docs.edit');
    }
}
