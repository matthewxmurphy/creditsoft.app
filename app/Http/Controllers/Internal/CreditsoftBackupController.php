<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Services\AuditTrail;
use App\Services\CreditsoftDatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use RuntimeException;

class CreditsoftBackupController extends Controller
{
    public function run(
        Request $request,
        CreditsoftDatabaseBackupService $backupService,
        AuditTrail $auditTrail,
    ): RedirectResponse|JsonResponse {
        $user = $request->user();

        abort_unless(
            $user
            && method_exists($user, 'canAccessOpsPanel')
            && $user->canAccessOpsPanel()
            && (! method_exists($user, 'isReadOnlyDemo') || ! $user->isReadOnlyDemo()),
            403,
        );

        $target = (string) $request->input('target', 'local');

        try {
            $result = $backupService->run($target);

            $auditTrail->record(
                $user,
                'system.database_backup.ran',
                'Ran a database backup from the footer backup lane.',
                null,
                [
                    'target' => $result['target'],
                    'archive_path' => $result['archive_path'],
                    'remote_path' => $result['remote_path'],
                    'handoff_path' => $result['handoff_path'],
                    'cluster_deliveries' => $result['cluster_deliveries'] ?? [],
                ],
            );

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => implode(' ', $result['messages']),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'target' => $result['target'],
                    'messages' => $result['messages'],
                    'archive_path' => $result['archive_path'],
                    'filename' => basename((string) $result['archive_path']),
                    'download_url' => route('internal.backups.download', [
                        'filename' => basename((string) $result['archive_path']),
                    ]),
                ]);
            }
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }
        }

        return back();
    }

    public function download(Request $request, string $filename): Response
    {
        $user = $request->user();

        abort_unless(
            $user
            && method_exists($user, 'canAccessOpsPanel')
            && $user->canAccessOpsPanel()
            && (! method_exists($user, 'isReadOnlyDemo') || ! $user->isReadOnlyDemo()),
            403,
        );

        $path = storage_path('app/private/database-backups/local/'.$filename);

        abort_unless(is_file($path), 404);

        return response()->download($path, $filename, [
            'Content-Type' => File::mimeType($path) ?: 'application/zip',
        ]);
    }
}
