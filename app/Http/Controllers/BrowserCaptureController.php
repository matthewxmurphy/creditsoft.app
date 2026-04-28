<?php

namespace App\Http\Controllers;

use App\Models\BrowserCapture;
use App\Models\Client;
use App\Services\AuditTrail;
use App\Services\BrowserCaptureCleanupService;
use App\Services\BrowserCaptureIntake;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BrowserCaptureController extends Controller
{
    public function store(
        Request $request,
        Client $client,
        BrowserCaptureIntake $intake,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $validated = $request->validate([
            'reporting_cycle_id' => ['required', 'integer', 'exists:reporting_cycles,id'],
            'source_type' => ['nullable', 'in:dom_capture,browser_capture,companion_capture,safari_webarchive'],
            'browser_name' => ['nullable', 'string', 'max:255'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'html' => ['nullable', 'string'],
            'capture_file' => ['nullable', 'file', 'max:10240', 'extensions:json,html,htm,txt,mhtml,webarchive'],
        ]);

        if (blank($validated['html'] ?? null) && ! $request->hasFile('capture_file')) {
            return back()->withErrors([
                'capture_file' => 'Paste DOM HTML or upload a capture file.',
            ]);
        }

        $cycle = $client->reportingCycles()->findOrFail($validated['reporting_cycle_id']);
        $capture = $intake->ingest(
            client: $client,
            cycle: $cycle,
            payload: [
                ...$validated,
                'capture_file' => $request->file('capture_file'),
            ],
            user: $request->user(),
        );

        $auditTrail->record(
            $request->user(),
            'browser_capture.imported',
            'Imported browser evidence for '.$cycle->cycle_label.'.',
            $capture,
            [
                'source_type' => $capture->source_type,
                'page_title' => $capture->page_title,
            ],
        );

        return redirect()->route('clients.show', $client);
    }

    public function destroy(
        Request $request,
        Client $client,
        BrowserCapture $browserCapture,
        BrowserCaptureCleanupService $cleanup,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        abort_unless($browserCapture->client_id === $client->getKey(), 404);

        $captureSummary = $browserCapture->page_title ?: 'Untitled capture';

        $cleanup->deleteCapture($browserCapture);

        $auditTrail->record(
            $request->user(),
            'browser_capture.deleted',
            "Deleted browser capture {$captureSummary}.",
            $client,
            [
                'capture_id' => $browserCapture->getKey(),
                'page_title' => $browserCapture->page_title,
                'page_url' => $browserCapture->page_url,
                'source_type' => $browserCapture->source_type,
            ],
        );

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Browser capture deleted.');
    }

    public function pruneDuplicates(
        Request $request,
        Client $client,
        BrowserCaptureCleanupService $cleanup,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $deleted = $cleanup->pruneDuplicates($client);

        if ($deleted->isEmpty()) {
            return redirect()
                ->route('clients.show', $client)
                ->with('success', 'No duplicate browser captures needed pruning.');
        }

        $auditTrail->record(
            $request->user(),
            'browser_capture.pruned_duplicates',
            "Pruned {$deleted->count()} duplicate browser captures for {$client->display_name}.",
            $client,
            [
                'capture_ids' => $deleted->pluck('id')->all(),
                'source_types' => $deleted->pluck('source_type')->unique()->values()->all(),
                'page_titles' => $deleted->pluck('page_title')->filter()->unique()->values()->all(),
            ],
        );

        return redirect()
            ->route('clients.show', $client)
            ->with('success', "Pruned {$deleted->count()} duplicate browser capture(s).");
    }
}
