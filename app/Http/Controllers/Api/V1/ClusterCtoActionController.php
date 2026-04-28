<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CtoAdvisorActionService;
use App\Services\OfficeBackupFilesystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClusterCtoActionController extends Controller
{
    public function store(
        Request $request,
        OfficeBackupFilesystemSettingsService $filesystemSettings,
        CtoAdvisorActionService $advisorActions,
    ): JsonResponse {
        if (! $this->allowsClusterRequest($request, $filesystemSettings)) {
            return response()->json([
                'ok' => false,
                'message' => 'Cluster CTO action access denied.',
            ], 403);
        }

        $validated = $request->validate([
            'action' => ['required', 'in:memory_saver_profile,prefer_healthy_node,ram_action_note'],
            'target_label' => ['nullable', 'string', 'max:120'],
            'preferred_label' => ['nullable', 'string', 'max:120'],
            'preferred_base_url' => ['nullable', 'string', 'max:1024'],
            'preferred_api_base_url' => ['nullable', 'string', 'max:1024'],
        ]);

        return response()->json([
            'ok' => true,
            'data' => $advisorActions->applyLocal((string) $validated['action'], $validated),
        ]);
    }

    protected function allowsClusterRequest(
        Request $request,
        OfficeBackupFilesystemSettingsService $filesystemSettings,
    ): bool {
        $expectedSecret = (string) data_get($filesystemSettings->stored(), 'cluster.shared_secret', '');
        $providedSecret = (string) $request->header('X-Creditsoft-Cluster-Secret', '');

        return $expectedSecret !== '' && hash_equals($expectedSecret, $providedSecret);
    }
}
