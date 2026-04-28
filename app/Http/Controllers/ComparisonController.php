<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\CreditReportComparisonService;
use App\Services\Metro2Scanner;
use Inertia\Inertia;
use Inertia\Response;

class ComparisonController extends Controller
{
    public function show(Client $client, CreditReportComparisonService $comparisonService, Metro2Scanner $scanner): Response
    {
        $cycle = $client->reportingCycles()
            ->with('bureauSnapshots.tradelines')
            ->when(request('cycle'), fn ($query, $cycleId) => $query->whereKey($cycleId))
            ->latest('started_at')
            ->firstOrFail();

        return Inertia::render('clients/Compare', [
            'client' => $client,
            'cycle' => $cycle,
            'cycles' => $client->reportingCycles()->latest('started_at')->get(['id', 'cycle_label']),
            'summary' => $comparisonService->reviewSummary($cycle),
            'rows' => $comparisonService->comparisonRows($cycle),
            'suggestions' => $scanner->suggestionsForCycle($cycle),
        ]);
    }
}
