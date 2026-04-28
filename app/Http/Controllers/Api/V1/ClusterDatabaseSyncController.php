<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditTrail;
use App\Services\CreditsoftClusterDatabaseSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClusterDatabaseSyncController extends Controller
{
    public function store(
        Request $request,
        CreditsoftClusterDatabaseSyncService $clusterDatabaseSyncService,
        AuditTrail $auditTrail,
    ): JsonResponse {
        $validated = $request->validate([
            'shared_secret' => ['required', 'string'],
            'event_uuid' => ['required', 'uuid'],
            'source_node' => ['nullable', 'string', 'max:255'],
            'model_type' => ['required', 'string', 'max:255'],
            'table_name' => ['required', 'string', 'max:255'],
            'primary_key' => ['required', 'string', 'max:120'],
            'record_key' => ['required', 'string', 'max:120'],
            'operation' => ['required', 'string', 'max:24'],
            'occurred_at' => ['nullable', 'date'],
            'attributes' => ['nullable', 'array'],
            'file_payload' => ['nullable', 'array'],
            'file_payload.file_name' => ['nullable', 'string', 'max:255'],
            'file_payload.mime_type' => ['nullable', 'string', 'max:255'],
            'file_payload.file_size' => ['nullable', 'integer', 'min:0'],
            'file_payload.sha256' => ['nullable', 'string', 'max:128'],
            'file_payload.contents_base64' => ['nullable', 'string'],
        ]);

        $result = $clusterDatabaseSyncService->receiveClusterEvent(
            sharedSecret: (string) $validated['shared_secret'],
            payload: $validated,
        );

        $auditTrail->record(
            null,
            'system.cluster_database_event.received',
            'Applied a cluster database sync event from another office.',
            null,
            [
                'source_node' => trim((string) ($validated['source_node'] ?? 'Peer office')),
                'event_uuid' => $validated['event_uuid'],
                'table_name' => $validated['table_name'],
                'record_key' => $validated['record_key'],
                'operation' => $validated['operation'],
                'status' => $result['status'] ?? 'applied',
            ],
        );

        return response()->json([
            'ok' => true,
            'message' => 'Applied cluster database sync event.',
            'data' => $result,
        ]);
    }
}
