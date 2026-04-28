<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\CreditsoftSystemDiagnosticsService;
use App\Services\OfficeBackupFilesystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CreditsoftDiagnosticsController extends Controller
{
    public function show(
        Request $request,
        CreditsoftSystemDiagnosticsService $diagnostics,
        OfficeBackupFilesystemSettingsService $filesystemSettings,
    ): JsonResponse {
        if (! $this->allowsClusterRequest($request, $filesystemSettings)) {
            return response()->json([
                'ok' => false,
                'message' => 'Cluster diagnostics access denied.',
            ], 403);
        }

        return response()->json([
            'ok' => true,
            'data' => $diagnostics->clusterSummary(),
        ]);
    }

    public function bandwidth(
        Request $request,
        OfficeBackupFilesystemSettingsService $filesystemSettings,
    ): JsonResponse|StreamedResponse {
        if (! $this->allowsClusterRequest($request, $filesystemSettings)) {
            return response()->json([
                'ok' => false,
                'message' => 'Cluster diagnostics access denied.',
            ], 403);
        }

        $bytes = max((int) $request->integer('bytes', (int) config('creditsoft.diagnostics.cluster_probe_bytes', 16777216)), 1024);
        $bytes = min($bytes, 64 * 1024 * 1024);
        $chunk = str_repeat('0', 64 * 1024);
        $chunkLength = strlen($chunk);

        return response()->stream(function () use ($bytes, $chunk, $chunkLength): void {
            $remaining = $bytes;

            while ($remaining > 0) {
                if ($remaining >= $chunkLength) {
                    echo $chunk;
                    $remaining -= $chunkLength;
                } else {
                    echo substr($chunk, 0, $remaining);
                    $remaining = 0;
                }
            }
        }, 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Content-Length' => (string) $bytes,
            'Content-Type' => 'application/octet-stream',
            'X-Creditsoft-Probe-Bytes' => (string) $bytes,
        ]);
    }

    protected function allowsClusterRequest(
        Request $request,
        OfficeBackupFilesystemSettingsService $filesystemSettings,
    ): bool {
        if ($request->user()) {
            return true;
        }

        $expectedSecret = (string) data_get($filesystemSettings->stored(), 'cluster.shared_secret', '');
        $providedSecret = (string) $request->header('X-Creditsoft-Cluster-Secret', '');

        return $expectedSecret !== '' && hash_equals($expectedSecret, $providedSecret);
    }
}
