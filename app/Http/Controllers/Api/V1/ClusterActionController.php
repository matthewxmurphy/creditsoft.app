<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CreditsoftClusterActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClusterActionController extends Controller
{
    public function store(Request $request, CreditsoftClusterActionService $clusterActions): JsonResponse
    {
        $validated = $request->validate([
            'action_uuid' => ['required', 'uuid'],
            'source_node' => ['nullable', 'string', 'max:255'],
            'action' => ['required', 'string', 'max:160'],
            'payload' => ['nullable', 'array'],
        ]);

        $result = $clusterActions->receiveClusterAction(
            (string) $request->header('X-Creditsoft-Cluster-Secret', ''),
            (string) $validated['action_uuid'],
            (string) ($validated['source_node'] ?? ''),
            (string) $validated['action'],
            (array) ($validated['payload'] ?? []),
        );

        return response()->json([
            'ok' => (bool) ($result['ok'] ?? false),
            'data' => $result,
        ]);
    }
}
