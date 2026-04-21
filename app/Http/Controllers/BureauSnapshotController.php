<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Tradeline;
use App\Services\AuditTrail;
use App\Services\ReportFeedbackSignalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use League\Csv\Reader;

class BureauSnapshotController extends Controller
{
    public function store(
        Request $request,
        Client $client,
        AuditTrail $auditTrail,
        ReportFeedbackSignalService $reportFeedbackSignals,
    ): RedirectResponse
    {
        $validated = $request->validate([
            'reporting_cycle_id' => ['required', 'integer', 'exists:reporting_cycles,id'],
            'bureau' => ['required', 'in:experian,transunion,equifax'],
            'source' => ['nullable', 'string', 'max:50'],
            'report_file' => ['nullable', 'file', 'mimes:csv,txt'],
            'creditor_name' => ['nullable', 'string', 'max:255'],
            'account_type' => ['nullable', 'string', 'max:255'],
            'balance' => ['nullable', 'numeric'],
            'credit_limit' => ['nullable', 'numeric'],
            'account_status' => ['nullable', 'string', 'max:255'],
            'payment_status' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ]);

        $cycle = $client->reportingCycles()->findOrFail($validated['reporting_cycle_id']);

        $tradelines = $request->hasFile('report_file')
            ? $this->parseCsv($request->file('report_file')->getRealPath())
            : [[
                'creditor_name' => $validated['creditor_name'] ?? 'Manual tradeline',
                'account_type' => $validated['account_type'] ?? 'revolving',
                'balance' => $validated['balance'] ?? null,
                'credit_limit' => $validated['credit_limit'] ?? null,
                'account_status' => $validated['account_status'] ?? 'review_needed',
                'payment_status' => $validated['payment_status'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'is_revolving' => Str::contains(Str::lower((string) ($validated['account_type'] ?? '')), ['revolving', 'card']),
            ]];

        $snapshot = $cycle->bureauSnapshots()->create([
            'bureau' => $validated['bureau'],
            'source' => $validated['source'] ?? ($request->hasFile('report_file') ? 'csv' : 'manual'),
            'imported_by' => $request->user()?->getKey(),
            'imported_at' => now(),
            'file_name' => $request->file('report_file')?->getClientOriginalName(),
            'snapshot_hash' => sha1(json_encode($tradelines)),
            'raw_summary' => [
                'count' => count($tradelines),
                'origin' => $request->hasFile('report_file') ? 'csv' : 'manual',
            ],
        ]);

        foreach ($tradelines as $tradeline) {
            $snapshot->tradelines()->create([
                ...$tradeline,
                'normalized_key' => Tradeline::buildNormalizedKey($tradeline),
                'provenance' => $request->hasFile('report_file') ? 'csv' : 'manual',
                'is_open' => ! Str::contains(Str::lower((string) ($tradeline['account_status'] ?? '')), ['closed', 'charged off']),
                'positive_classification' => ! Str::contains(Str::lower((string) ($tradeline['remarks'] ?? '')), ['collection', 'late', 'charge']),
                'data' => $tradeline,
            ]);
        }

        $auditTrail->record(
            $request->user(),
            'snapshot.imported',
            "Imported {$validated['bureau']} snapshot for {$cycle->cycle_label}.",
            $snapshot,
            ['tradeline_count' => count($tradelines)],
        );

        $reportFeedbackSignals->queueSnapshotImported($snapshot);

        return redirect()->route('clients.compare', $client);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function parseCsv(string $path): array
    {
        $reader = Reader::createFromPath($path)->setHeaderOffset(0);

        return collect($reader->getRecords())
            ->map(function (array $row): array {
                $creditor = $row['creditor_name']
                    ?? $row['creditor']
                    ?? $row['furnisher']
                    ?? 'Imported tradeline';

                $limit = $row['credit_limit'] ?? $row['limit'] ?? null;
                $balance = $row['balance'] ?? null;

                return [
                    'creditor_name' => $creditor,
                    'account_name' => $row['account_name'] ?? $creditor,
                    'account_type' => $row['account_type'] ?? $row['type'] ?? null,
                    'bureau_account_reference' => $row['account_reference'] ?? $row['account_number_last4'] ?? null,
                    'balance' => $balance !== null ? (float) $balance : null,
                    'credit_limit' => $limit !== null ? (float) $limit : null,
                    'utilization_percent' => isset($row['utilization_percent']) ? (float) $row['utilization_percent'] : null,
                    'account_status' => $row['account_status'] ?? $row['status'] ?? null,
                    'payment_status' => $row['payment_status'] ?? null,
                    'remarks' => $row['remarks'] ?? null,
                    'is_revolving' => Str::contains(Str::lower((string) ($row['account_type'] ?? '')), ['revolving', 'card']),
                ];
            })
            ->values()
            ->all();
    }
}
