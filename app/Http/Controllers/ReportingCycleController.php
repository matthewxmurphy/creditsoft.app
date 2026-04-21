<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportingCycleController extends Controller
{
    public function store(Request $request, Client $client, AuditTrail $auditTrail): RedirectResponse
    {
        $validated = $request->validate([
            'cycle_label' => ['required', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:50'],
            'started_at' => ['required', 'date'],
        ]);

        $cycle = $client->reportingCycles()->create([
            ...$validated,
            'source' => $validated['source'] ?? 'manual',
        ]);

        $auditTrail->record(
            $request->user(),
            'cycle.created',
            "Created reporting cycle {$cycle->cycle_label}.",
            $cycle,
        );

        return redirect()->route('clients.show', $client);
    }
}
