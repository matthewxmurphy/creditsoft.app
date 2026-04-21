<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\CreditReportComparisonService;
use App\Services\ClientHealthSignalService;
use App\Services\Metro2Scanner;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ComparisonController extends Controller
{
    public function show(
        Client $client,
        CreditReportComparisonService $comparisonService,
        ClientHealthSignalService $clientHealth,
        Metro2Scanner $scanner,
    ): Response|RedirectResponse
    {
        $cycle = $client->reportingCycles()
            ->with('bureauSnapshots.tradelines')
            ->when(request('cycle'), fn ($query, $cycleId) => $query->whereKey($cycleId))
            ->latest('started_at')
            ->first();

        if (! $cycle) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'No imported reporting cycle is ready for Compare yet.',
            ]);

            return redirect()->route('clients.show', $client);
        }

        return Inertia::render('clients/Compare', [
            'client' => $client,
            'clientHealth' => $clientHealth->signal($client),
            'cycle' => $cycle,
            'cycles' => $client->reportingCycles()->latest('started_at')->get(['id', 'cycle_label']),
            'summary' => $comparisonService->reviewSummary($cycle),
            'rows' => $comparisonService->comparisonRows($cycle),
            'suggestions' => $scanner->suggestionsForCycle($cycle),
        ]);
    }
}
