<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditTrail;
use App\Services\CreditsoftClusterApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClusterApiKeyController extends Controller
{
    public function store(
        Request $request,
        CreditsoftClusterApiKeyService $clusterApiKeyService,
        AuditTrail $auditTrail,
    ): JsonResponse {
        $validated = $request->validate([
            'shared_secret' => ['required', 'string'],
            'source_office' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'token' => ['required', 'string', 'max:255'],
            'user_email' => ['required', 'email', 'max:255'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', 'max:120'],
            'revoke_existing' => ['nullable', 'boolean'],
        ]);

        $result = $clusterApiKeyService->receiveClusterKey(
            sharedSecret: (string) $validated['shared_secret'],
            name: (string) $validated['name'],
            token: (string) $validated['token'],
            userEmail: (string) $validated['user_email'],
            abilities: (array) ($validated['abilities'] ?? ['partner_api']),
            revokeExisting: (bool) ($validated['revoke_existing'] ?? true),
        );

        $auditTrail->record(
            null,
            'system.cluster_api_key.received',
            'Installed a cluster-synced API key from another office.',
            null,
            [
                'source_office' => trim((string) ($validated['source_office'] ?? 'Peer office')),
                'name' => $result['name'] ?? $validated['name'],
                'user_email' => $result['user_email'] ?? $validated['user_email'],
                'token_prefix' => $result['token_prefix'] ?? null,
                'token_suffix' => $result['token_suffix'] ?? null,
                'status' => $result['status'] ?? 'installed',
                'abilities' => $result['abilities'] ?? [],
            ],
        );

        return response()->json([
            'ok' => true,
            'message' => sprintf('Installed API key %s.', $validated['name']),
            'data' => $result,
        ]);
    }
}
