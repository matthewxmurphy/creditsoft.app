<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditTrail;
use App\Services\CreditsoftClusterBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClusterBackupController extends Controller
{
    public function store(
        Request $request,
        CreditsoftClusterBackupService $clusterBackupService,
        AuditTrail $auditTrail,
    ): JsonResponse {
        $result = $clusterBackupService->receive(
            (string) $request->input('shared_secret', ''),
            trim((string) $request->input('source_office', 'Peer office')),
            strtoupper(trim((string) $request->input('source_license_key', ''))),
            trim((string) $request->input('source_masked_key', '')),
            $request->file('archive'),
        );

        $auditTrail->record(
            null,
            'system.cluster_backup.received',
            'Stored a cluster backup from another office.',
            null,
            [
                'source_office' => $result['source_office'],
                'source_key' => $result['source_key'],
                'stored_path' => $result['stored_path'],
            ],
        );

        return response()->json([
            'ok' => true,
            'message' => sprintf('Stored cluster backup from %s.', $result['source_office']),
        ]);
    }
}
