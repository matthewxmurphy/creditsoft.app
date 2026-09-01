<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ApiDocsHostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiIndexController extends Controller
{
    public function __invoke(Request $request, ApiDocsHostService $docsHost): JsonResponse
    {
        $scheme = $request->header('x-forwarded-proto') ?: $request->getScheme();
        $host = $request->header('x-forwarded-host') ?: $request->getHttpHost();
        $origin = rtrim("{$scheme}://{$host}{$request->getBaseUrl()}", '/');
        $apiBase = "{$origin}/api/v1";
        $docsUrl = $docsHost->shouldServeDocsAtRoot($request)
            ? $origin
            : "{$origin}/settings/api";

        return response()->json([
            'data' => [
                'name' => 'CreditSoft Local Partner API',
                'version' => '1.0.0',
                'status' => 'ok',
                'base_url' => $apiBase,
                'docs_url' => $docsUrl,
                'spec_url' => "{$apiBase}/openapi.yaml",
                'features' => [
                    'browser_companion' => true,
                    'client_sync' => true,
                    'automation_discovery' => true,
                    'disputefox_credentials' => true,
                    'create_client_if_missing' => true,
                ],
                'authentication' => [
                    'type' => 'bearer',
                    'required_for' => [
                        'GET /office-stats',
                        'GET /client/handshake',
                        'POST /clients',
                        'GET /clients/picker',
                        'GET /clients/search',
                        'GET /clients/{cuid}',
                        'PATCH /clients/{cuid}',
                        'PATCH /clients/{cuid}/status',
                        'GET /clients/{cuid}/cycles',
                        'GET /clients/{cuid}/score-history',
                        'GET /clients/{cuid}/status',
                        'GET /clients/{cuid}/notes',
                        'POST /clients/{cuid}/notes',
                        'GET /clients/{cuid}/violations',
                        'GET /clients/{cuid}/letters',
                        'GET /clients/{cuid}/briefs',
                        'GET /clients/{cuid}/tasks',
                        'POST /clients/{cuid}/tasks',
                        'GET /clients/{cuid}/documents',
                        'POST /clients/{cuid}/documents',
                        'GET /clients/{cuid}/browser-captures',
                        'POST /clients/{cuid}/browser-captures',
                        'GET /browser-companion/next-account',
                        'POST /browser-companion/intake',
                        'POST /browser-companion/client-sync',
                        'POST /browser-companion/automation-discovery',
                        'POST /crm/twenty/webhook',
                    ],
                ],
                'endpoints' => [
                    [
                        'method' => 'GET',
                        'path' => '/',
                        'summary' => 'Read this API overview and discover the current host-specific base URLs.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/office-stats',
                        'summary' => 'Read aggregate-only office impact, lifecycle, customer city/state, business-location comparison, and seasonality statistics without customer identifiers.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/client/handshake',
                        'summary' => 'Return the logged-in staff device handshake: user roles, API key abilities, license state, PWA URLs, and local/tailnet/public connection lanes.',
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
                        'method' => 'POST',
                        'path' => '/browser-companion/client-sync',
                        'summary' => 'Sync detected DisputeFox account/profile fields into a client record when the browser companion feature is licensed.',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/browser-companion/automation-discovery',
                        'summary' => 'Stage reusable automation workflow discoveries from approved companion pages without storing raw customer DOM.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/clients/search',
                        'summary' => 'Search local clients by email or name before posting companion captures.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/clients/{cuid}',
                        'summary' => 'Read the current client profile and contact fields without opening the operator UI.',
                    ],
                    [
                        'method' => 'PATCH',
                        'path' => '/clients/{cuid}',
                        'summary' => 'Update client contact info or case basics while preserving an audit trail.',
                    ],
                    [
                        'method' => 'PATCH',
                        'path' => '/clients/{cuid}/status',
                        'summary' => 'Update the case stage or score and preserve the status-change audit trail.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/clients/{cuid}/cycles',
                        'summary' => 'List reporting cycles and import counts for the client.',
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
                        'summary' => 'List notes for a client.',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/clients/{cuid}/notes',
                        'summary' => 'Create a note and audit the write locally.',
                    ],
                    [
                        'method' => 'GET',
                        'path' => '/clients/{cuid}/violations',
                        'summary' => 'List violation candidates for a client.',
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
                        'summary' => 'List portal-safe case documents uploaded for the client.',
                    ],
                    [
                        'method' => 'POST',
                        'path' => '/clients/{cuid}/documents',
                        'summary' => 'Upload a client document and preserve a local audit trail.',
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
                    [
                        'method' => 'POST',
                        'path' => '/crm/twenty/webhook',
                        'summary' => 'Receive Twenty CRM webhooks and queue AI-assisted CreditSoft tasks, notes, and outbound drafts.',
                    ],
                ],
            ],
        ]);
    }
}
