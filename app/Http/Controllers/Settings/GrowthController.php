<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\AuditTrail;
use App\Services\OfficeGrowthSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GrowthController extends Controller
{
    public function edit(OfficeGrowthSettingsService $settings): Response
    {
        return Inertia::render('settings/Growth', [
            'settings' => $settings->load(),
        ]);
    }

    public function update(Request $request, OfficeGrowthSettingsService $settings, AuditTrail $auditTrail): RedirectResponse
    {
        $saved = $settings->save($request->all());

        $auditTrail->record(
            $request->user(),
            'settings.growth.updated',
            'Updated growth, signup, affiliate, and CRM settings.',
            null,
            [
                'credit_reason_count' => count($saved['credit_settings']['reasons'] ?? []),
                'crm_fields_count' => count($saved['crm_fields'] ?? []),
                'affiliate_count' => count($saved['affiliates'] ?? []),
                'booking_links_count' => count($saved['appointments']['links'] ?? []),
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Growth and signup settings saved.',
        ]);

        return redirect()->route('growth.edit');
    }

    public function importActivity(Request $request, OfficeGrowthSettingsService $settings, AuditTrail $auditTrail): RedirectResponse
    {
        $validated = $request->validate([
            'activity_csv' => ['required', 'file', 'max:8192'],
        ]);

        $rows = $settings->importActivityCsv($validated['activity_csv']);

        $auditTrail->record(
            $request->user(),
            'settings.growth.activity_imported',
            'Imported team activity history into the growth settings lane.',
            null,
            [
                'row_count' => count($rows),
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Activity history imported.',
        ]);

        return redirect()->route('growth.edit');
    }
}
