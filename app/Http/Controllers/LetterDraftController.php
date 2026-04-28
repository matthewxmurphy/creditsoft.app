<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\LetterDraft;
use App\Services\AuditTrail;
use App\Services\CreditsoftAiRegistry;
use App\Services\CreditsoftAiService;
use App\Services\LetterDraftAutoSeedService;
use App\Services\LetterPdfDocumentService;
use App\Services\LetterDraftPresentationService;
use App\Services\LetterTemplateCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class LetterDraftController extends Controller
{
    public function index(
        Request $request,
        Client $client,
        CreditsoftAiRegistry $registry,
        LetterTemplateCatalog $templates,
        LetterDraftAutoSeedService $autoSeeder,
        LetterDraftPresentationService $presentation,
        LetterPdfDocumentService $pdfDocuments,
        AuditTrail $auditTrail,
    ): Response
    {
        $seededLetters = $request->user()?->isReadOnlyDemo()
            ? collect()
            : $autoSeeder->ensureSeeded($client, $request->user());

        if ($seededLetters->isNotEmpty()) {
            $seededLetters->each(function (LetterDraft $seededLetter) use ($request, $auditTrail): void {
                $auditTrail->record(
                    $request->user(),
                    'letter.auto_seeded',
                    "Auto-generated {$seededLetter->letter_type} letter {$seededLetter->title} for review.",
                    $seededLetter,
                    [
                        'source' => 'letters.index',
                        'reporting_cycle_id' => $seededLetter->reporting_cycle_id,
                        'recipient_bureau' => data_get($seededLetter->ai_metadata, 'recipient_bureau'),
                    ],
                );
            });

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => $seededLetters->count() > 1
                    ? 'Bureau-specific dispute letters were generated and saved as PDFs for review.'
                    : 'Initial dispute letter generated and saved as PDF for review.',
            ]);
        }

        $letters = $client->letters()->with('reportingCycle')->latest()->get();

        foreach ($letters as $letter) {
            if ($letter->status === 'exported' || $letter->client->documents()->where('category', 'letter_pdf')->exists()) {
                $pdfDocuments->ensurePdf($letter, $request->user());
            }
        }

        $documentsByLetter = $client->documents()
            ->where('category', 'letter_pdf')
            ->latest('uploaded_at')
            ->get()
            ->filter(fn (ClientDocument $document) => filled(data_get($document->metadata, 'letter_draft_id')))
            ->keyBy(fn (ClientDocument $document) => (int) data_get($document->metadata, 'letter_draft_id'));

        $templateReview = $templates->reviewForClient($client);

        return Inertia::render('clients/Letters', [
            'client' => $client,
            'letters' => $letters->map(function (LetterDraft $letter) use ($client, $documentsByLetter, $presentation): array {
                $document = $documentsByLetter->get($letter->getKey());

                return [
                    'id' => $letter->getKey(),
                    'title' => $presentation->title($letter),
                    'letter_type' => $letter->letter_type,
                    'template_key' => $letter->template_key,
                    'template_version' => $letter->template_version,
                    'status' => $letter->status,
                    'content' => $presentation->content($letter),
                    'generated_by_ai' => $letter->generated_by_ai,
                    'ai_metadata' => $letter->ai_metadata,
                    'recipient_bureau' => data_get($letter->ai_metadata, 'recipient_bureau'),
                    'reporting_cycle' => $letter->reportingCycle?->cycle_label,
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
            'aiTask' => collect($registry->catalog()['tasks'] ?? [])->firstWhere('key', 'drafting'),
            'templates' => $templateReview['available'],
            'templateReview' => $templateReview,
        ]);
    }

    public function store(Request $request, Client $client, AuditTrail $auditTrail, LetterTemplateCatalog $templates): RedirectResponse
    {
        $validated = $request->validate([
            'reporting_cycle_id' => ['nullable', 'integer', 'exists:reporting_cycles,id'],
            'title' => ['required', 'string', 'max:255'],
            'letter_type' => ['required', 'string', 'max:255'],
            'template_key' => ['nullable', 'string', 'max:255'],
            'legal_basis' => ['nullable', 'string'],
            'content' => ['required', 'string'],
        ]);

        $template = $templates->find($validated['template_key'] ?? null)
            ?? $templates->defaultForType((string) $validated['letter_type']);
        $resolvedLegalBasis = filled($validated['legal_basis'] ?? null)
            ? array_map('trim', explode(',', (string) $validated['legal_basis']))
            : array_values(array_filter($template['legal_basis'] ?? []));

        $letter = $client->letters()->create([
            ...$validated,
            'user_id' => $request->user()?->getKey(),
            'template_key' => $template['key'] ?? null,
            'template_version' => $template['version'] ?? null,
            'legal_basis' => $resolvedLegalBasis,
        ]);

        $auditTrail->record(
            $request->user(),
            'letter.created',
            "Drafted {$letter->letter_type} letter {$letter->title}.",
            $letter,
            [
                'template_key' => $letter->template_key,
                'template_version' => $letter->template_version,
            ],
        );

        return redirect()->route('clients.letters.index', $client);
    }

    public function generateAiDraft(
        Request $request,
        Client $client,
        CreditsoftAiService $aiService,
        AuditTrail $auditTrail,
        LetterTemplateCatalog $templates,
    ): RedirectResponse {
        $validated = $request->validate([
            'reporting_cycle_id' => ['nullable', 'integer', 'exists:reporting_cycles,id'],
            'letter_type' => ['required', 'string', 'max:255'],
            'template_key' => ['nullable', 'string', 'max:255'],
            'legal_basis' => ['nullable', 'string'],
            'operator_focus' => ['nullable', 'string'],
        ]);

        $cycle = filled($validated['reporting_cycle_id'] ?? null)
            ? $client->reportingCycles()->findOrFail($validated['reporting_cycle_id'])
            : $client->reportingCycles()->latest('started_at')->first();
        $template = $templates->find($validated['template_key'] ?? null)
            ?? $templates->defaultForType((string) $validated['letter_type']);
        $resolvedLegalBasis = filled($validated['legal_basis'] ?? null)
            ? (string) $validated['legal_basis']
            : implode(', ', array_values(array_filter($template['legal_basis'] ?? ['FCRA § 611', 'Metro 2 completeness'])));

        try {
            $draft = $aiService->generateLetterDraft(
                client: $client,
                cycle: $cycle,
                letterType: (string) $validated['letter_type'],
                legalBasis: $resolvedLegalBasis,
                template: $template,
                operatorFocus: $validated['operator_focus'] ?? null,
            );
        } catch (Throwable $throwable) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $throwable->getMessage(),
            ]);

            return redirect()->route('clients.letters.index', $client);
        }

        $letter = $client->letters()->create([
            'reporting_cycle_id' => $cycle?->getKey(),
            'user_id' => $request->user()?->getKey(),
            'title' => $draft['title'],
            'letter_type' => $validated['letter_type'],
            'template_key' => $template['key'] ?? null,
            'template_version' => $template['version'] ?? null,
            'legal_basis' => array_map('trim', explode(',', $resolvedLegalBasis)),
            'content' => $draft['content'],
            'generated_by_ai' => true,
            'ai_metadata' => $draft['meta'],
        ]);

        $auditTrail->record(
            $request->user(),
            'letter.ai_drafted',
            "AI drafted {$letter->letter_type} letter {$letter->title}.",
            $letter,
            $draft['meta'],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'AI draft created. Review before approval or export.',
        ]);

        return redirect()->route('clients.letters.index', $client);
    }

    public function update(
        Request $request,
        Client $client,
        LetterDraft $letterDraft,
        AuditTrail $auditTrail,
        LetterPdfDocumentService $pdfDocuments,
    ): RedirectResponse
    {
        abort_unless($letterDraft->client_id === $client->getKey(), 404);

        $validated = $request->validate([
            'status' => ['required', 'in:draft,approved,exported'],
            'pdf_profile' => ['nullable', 'array'],
            'pdf_profile.style' => ['nullable', 'in:typed,typed_typos,handwritten_right,handwritten_left'],
            'pdf_profile.typo_rate' => ['nullable', 'in:none,light,medium'],
        ]);
        $metadata = $letterDraft->ai_metadata ?? [];

        if (array_key_exists('pdf_profile', $validated)) {
            data_set($metadata, 'pdf_profile', [
                'style' => data_get($validated, 'pdf_profile.style', 'typed'),
                'typo_rate' => data_get($validated, 'pdf_profile.typo_rate', 'none'),
            ]);
        }

        $letterDraft->update([
            'status' => $validated['status'],
            'ai_metadata' => $metadata,
            'approved_at' => $validated['status'] === 'approved' ? now() : $letterDraft->approved_at,
            'approved_by' => $validated['status'] === 'approved' ? $request->user()?->getKey() : $letterDraft->approved_by,
            'exported_at' => $validated['status'] === 'exported' ? now() : $letterDraft->exported_at,
        ]);

        $documentId = null;

        if ($validated['status'] === 'exported') {
            $documentId = $pdfDocuments->ensurePdf($letterDraft->fresh(), $request->user())->getKey();
        }

        $auditTrail->record(
            $request->user(),
            'letter.updated',
            "Marked {$letterDraft->title} as {$validated['status']}.",
            $letterDraft,
            array_filter([
                'document_id' => $documentId,
            ]),
        );

        if ($validated['status'] === 'exported') {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'PDF saved to the client documents lane for review.',
            ]);
        }

        return redirect()->route('clients.letters.index', $client);
    }
}
