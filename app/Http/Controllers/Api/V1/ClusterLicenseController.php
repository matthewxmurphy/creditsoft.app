<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditTrail;
use App\Services\CreditsoftClusterLicenseSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClusterLicenseController extends Controller
{
    public function store(
        Request $request,
        CreditsoftClusterLicenseSyncService $licenseSync,
        AuditTrail $auditTrail,
    ): JsonResponse {
        $validated = $request->validate([
            'shared_secret' => ['required', 'string'],
            'event_uuid' => ['required', 'uuid'],
            'source_node' => ['nullable', 'string', 'max:255'],
            'license_key' => ['nullable', 'string', 'max:255'],
            'license' => ['required', 'array'],
            'license.valid' => ['nullable', 'boolean'],
            'license.status' => ['nullable', 'string', 'max:80'],
            'license.mode' => ['nullable', 'string', 'max:80'],
            'license.requested_mode' => ['nullable', 'string', 'max:80'],
            'license.message' => ['nullable', 'string', 'max:1000'],
            'license.checked_at' => ['nullable', 'date'],
            'license.last_verified_at' => ['nullable', 'date'],
            'license.masked_key' => ['nullable', 'string', 'max:255'],
            'license.plan' => ['nullable', 'string', 'max:255'],
            'license.plan_key' => ['nullable', 'string', 'max:255'],
            'license.features' => ['nullable', 'array'],
            'license.expires_at' => ['nullable', 'date'],
            'license.expired_at' => ['nullable', 'date'],
            'license.grace_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'license.grace_ends_at' => ['nullable', 'date'],
            'license.access_state' => ['nullable', 'string', 'max:80'],
            'license.can_access_workspace' => ['nullable', 'boolean'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $result = $licenseSync->receiveClusterLicense(
            sharedSecret: (string) $validated['shared_secret'],
            payload: $validated,
        );

        $auditTrail->record(
            null,
            'system.cluster_license.received',
            'Applied a cluster-synced office license from another server node.',
            null,
            [
                'source_node' => trim((string) ($validated['source_node'] ?? 'Peer office')),
                'event_uuid' => $validated['event_uuid'],
                'status' => $result['status'] ?? 'applied',
                'license_status' => $result['license_status'] ?? null,
                'plan' => $result['plan'] ?? null,
            ],
        );

        return response()->json([
            'ok' => true,
            'message' => 'Applied cluster license sync.',
            'data' => $result,
        ]);
    }
}
