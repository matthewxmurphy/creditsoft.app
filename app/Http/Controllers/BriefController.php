<?php

namespace App\Http\Controllers;

use App\Models\CaseBrief;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\OutboundSignal;
use App\Services\AuditTrail;
use App\Services\BriefAutoSeedService;
use App\Services\BriefPdfDocumentService;
use App\Services\CreditsoftAiRegistry;
use App\Services\CreditsoftAiService;
use App\Services\InstallationFeedbackPolicy;
use App\Services\SignalSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;
use Inertia\Inertia;
use Inertia\Response;

class BriefController extends Controller
{
    public function index(
        Request $request,
        Client $client,
        CreditsoftAiRegistry $registry,
        BriefAutoSeedService $autoSeeder,
        AuditTrail $auditTrail,
    ): Response
    {
        $seededBrief = $request->user()?->isReadOnlyDemo()
            ? null
            : $autoSeeder->ensureSeeded($client, $request->user());

        if ($seededBrief) {
            $auditTrail->record(
                $request->user(),
                'brief.auto_seeded',
                "Auto-generated {$seededBrief->period} brief {$seededBrief->title} for review.",
                $seededBrief,
                [
                    'source' => 'briefs.index',
                    'reporting_cycle_id' => $seededBrief->reporting_cycle_id,
                ],
            );

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Initial case brief generated and saved as PDF for review.',
            ]);
        }

        $briefs = $client->briefs()->with('reportingCycle')->latest()->get();
        $documentsByBrief = $client->documents()
            ->where('category', 'brief_pdf')
            ->latest('uploaded_at')
            ->get()
            ->filter(fn (ClientDocument $document) => filled(data_get($document->metadata, 'brief_id')))
            ->keyBy(fn (ClientDocument $document) => (int) data_get($document->metadata, 'brief_id'));

        return Inertia::render('clients/Briefs', [
            'client' => $client,
            'briefs' => $briefs->map(function (CaseBrief $brief) use ($client, $documentsByBrief): array {
                $document = $documentsByBrief->get($brief->getKey());

                return [
                    'id' => $brief->getKey(),
                    'title' => $brief->title,
                    'content' => $brief->content,
                    'period' => $brief->period,
                    'approved_at' => optional($brief->approved_at)?->toIso8601String(),
                    'generated_by_ai' => $brief->generated_by_ai,
                    'ai_metadata' => $brief->ai_metadata,
                    'reporting_cycle' => $brief->reportingCycle?->cycle_label,
                    'pdf_document' => $document ? [
                        'id' => $document->getKey(),
                        'title' => $document->title,
                        'file_name' => $document->file_name,
                        'uploaded_at' => optional($document->uploaded_at)?->toIso8601String(),
                        'download_url' => route('clients.documents.download', [$client, $document]),
                    ] : null,
                ];
            })->values(),
            'cycles' => $client->reportingCycles()->latest('started_at')->get(['id', 'cycle_label']),
            'aiTask' => collect($registry->catalog()['tasks'] ?? [])->firstWhere('key', 'summaries'),
        ]);
    }

    public function store(
        Request $request,
        Client $client,
        AuditTrail $auditTrail,
        SignalSanitizer $signalSanitizer,
        InstallationFeedbackPolicy $feedbackPolicy,
        BriefPdfDocumentService $pdfDocuments,
    ): RedirectResponse
    {
        $validated = $request->validate([
            'reporting_cycle_id' => ['nullable', 'integer', 'exists:reporting_cycles,id'],
            'period' => ['required', 'in:weekly,monthly'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'approve_now' => ['nullable', 'boolean'],
            'sync_eligible' => ['nullable', 'boolean'],
        ]);

        $brief = $client->briefs()->create([
            ...$validated,
            'user_id' => $request->user()?->getKey(),
            'sync_eligible' => $request->boolean('sync_eligible', true),
            'approved_at' => $request->boolean('approve_now') ? now() : null,
            'approved_by' => $request->boolean('approve_now') ? $request->user()?->getKey() : null,
        ]);

        if ($brief->sync_eligible && $brief->approved_at && $feedbackPolicy->portalSyncEnabled()) {
            $payload = [
                'client_cuid' => $client->cuid,
                'event_type' => 'shareable_case_brief.created',
                'brief_title' => $brief->title,
                'brief_excerpt' => str($brief->content)->limit(180)->toString(),
                'stage' => $client->status,
                'recorded_at' => now()->toIso8601String(),
                'summary' => $brief->title,
            ];

            OutboundSignal::create([
                'client_id' => $client->getKey(),
                'event_type' => 'shareable_case_brief.created',
                'payload' => $payload,
                'sanitized_payload' => $signalSanitizer->sanitize($payload),
                'status' => 'pending',
                'queued_at' => now(),
            ]);
        }

        $auditTrail->record(
            $request->user(),
            'brief.created',
            "Created {$brief->period} case brief {$brief->title}.",
            $brief,
        );

        $pdfDocuments->ensurePdf($brief, $request->user());

        return redirect()->route('clients.briefs.index', $client);
    }

    public function generateAiDraft(
        Request $request,
        Client $client,
        CreditsoftAiService $aiService,
        AuditTrail $auditTrail,
        BriefPdfDocumentService $pdfDocuments,
    ): RedirectResponse {
        $validated = $request->validate([
            'reporting_cycle_id' => ['nullable', 'integer', 'exists:reporting_cycles,id'],
            'period' => ['required', 'in:weekly,monthly'],
            'operator_focus' => ['nullable', 'string'],
        ]);

        $cycle = filled($validated['reporting_cycle_id'] ?? null)
            ? $client->reportingCycles()->findOrFail($validated['reporting_cycle_id'])
            : $client->reportingCycles()->latest('started_at')->first();

        try {
            $draft = $aiService->generateCaseBrief(
                client: $client,
                cycle: $cycle,
                period: (string) $validated['period'],
                operatorFocus: $validated['operator_focus'] ?? null,
            );
        } catch (Throwable $throwable) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $throwable->getMessage(),
            ]);

            return redirect()->route('clients.briefs.index', $client);
        }

        $brief = $client->briefs()->create([
            'reporting_cycle_id' => $cycle?->getKey(),
            'user_id' => $request->user()?->getKey(),
            'period' => $validated['period'],
            'title' => $draft['title'],
            'content' => $draft['content'],
            'generated_by_ai' => true,
            'ai_metadata' => $draft['meta'],
        ]);

        $auditTrail->record(
            $request->user(),
            'brief.ai_drafted',
            "AI drafted {$brief->period} brief {$brief->title}.",
            $brief,
            $draft['meta'],
        );

        $pdfDocuments->ensurePdf($brief, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'AI brief created and saved as a review PDF.',
        ]);

        return redirect()->route('clients.briefs.index', $client);
    }
}
