<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\AuditTrail;
use App\Services\EnvironmentEditor;
use App\Services\OfficeBackupFilesystemSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BackupFilesystemController extends Controller
{
    public function edit(OfficeBackupFilesystemSettingsService $settings): Response
    {
        return Inertia::render('settings/BackupFilesystem', [
            'settings' => $settings->load(),
        ]);
    }

    public function update(
        Request $request,
        OfficeBackupFilesystemSettingsService $settings,
        EnvironmentEditor $editor,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $saved = $settings->save($request->all(), $editor);

        $auditTrail->record(
            $request->user(),
            'settings.backup_filesystem.updated',
            'Updated backup destination and file-system connector settings.',
            null,
            [
                'archive_destination' => $saved['archive_destination'] ?? 'local',
                'external_handoff_lane' => $saved['external_handoff_lane'] ?? 'none',
                'wasabi_enabled' => (bool) data_get($saved, 'wasabi.enabled', false),
                'dropbox_enabled' => (bool) data_get($saved, 'dropbox.enabled', false),
                'google_drive_enabled' => (bool) data_get($saved, 'google_drive.enabled', false),
                'cluster_enabled' => (bool) data_get($saved, 'cluster.enabled', false),
                'cluster_peer_count' => count((array) data_get($saved, 'cluster.peers', [])),
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Backup and file-system settings saved.',
        ]);

        return redirect()->route('backup-filesystem.edit');
    }
}
