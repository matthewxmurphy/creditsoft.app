<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\AuditTrail;
use App\Services\ClientHealthSignalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NoteController extends Controller
{
    public function index(Client $client, ClientHealthSignalService $clientHealth): Response
    {
        return Inertia::render('clients/Notes', [
            'client' => $client,
            'clientHealth' => $clientHealth->signal($client),
            'notes' => $client->notes()->with('reportingCycle')->latest()->get(),
            'cycles' => $client->reportingCycles()->latest('started_at')->get(['id', 'cycle_label']),
        ]);
    }

    public function store(Request $request, Client $client, AuditTrail $auditTrail): RedirectResponse
    {
        $validated = $request->validate([
            'reporting_cycle_id' => ['nullable', 'integer', 'exists:reporting_cycles,id'],
            'visibility' => ['required', 'in:private_note,working_note,shareable_case_brief'],
            'note' => ['required', 'string'],
        ]);

        $note = $client->notes()->create([
            ...$validated,
            'user_id' => $request->user()?->getKey(),
            'sync_eligible' => $validated['visibility'] === 'shareable_case_brief',
        ]);

        $auditTrail->record(
            $request->user(),
            'note.created',
            "Added {$validated['visibility']} note.",
            $note,
        );

        return redirect()->route('clients.notes.index', $client);
    }
}
