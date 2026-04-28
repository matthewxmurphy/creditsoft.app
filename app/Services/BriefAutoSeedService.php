<?php

namespace App\Services;

use App\Models\CaseBrief;
use App\Models\Client;
use App\Models\ReportingCycle;
use App\Models\User;
use Illuminate\Support\Str;
use Throwable;

class BriefAutoSeedService
{
    public function __construct(
        protected CreditsoftAiService $aiService,
        protected CreditReportComparisonService $comparisonService,
        protected BriefPdfDocumentService $pdfDocuments,
    ) {
    }

    public function ensureSeeded(Client $client, ?User $user = null): ?CaseBrief
    {
        if ($client->briefs()->exists()) {
            return null;
        }

        $cycle = $client->reportingCycles()->latest('started_at')->first();

        if (! $cycle) {
            return null;
        }

        $draft = null;
        $generatedByAi = false;

        try {
            $draft = $this->aiService->generateCaseBrief(
                client: $client,
                cycle: $cycle,
                period: 'monthly',
                operatorFocus: 'Draft the first shareable operator review packet. Keep it concise, factual, and ready for review before release.',
            );
            $generatedByAi = true;
        } catch (Throwable) {
            $draft = $this->buildFallbackDraft($client, $cycle);
        }

        $brief = $client->briefs()->create([
            'reporting_cycle_id' => $cycle->getKey(),
            'user_id' => $user?->getKey(),
            'period' => 'monthly',
            'title' => $draft['title'],
            'content' => $draft['content'],
            'generated_by_ai' => $generatedByAi,
            'ai_metadata' => $generatedByAi ? ($draft['meta'] ?? []) : null,
            'sync_eligible' => true,
        ]);

        $this->pdfDocuments->ensurePdf($brief, $user);

        return $brief->fresh(['reportingCycle']);
    }

    /**
     * @return array{title:string,content:string,meta:array<string,mixed>}
     */
    protected function buildFallbackDraft(Client $client, ReportingCycle $cycle): array
    {
        $summary = $this->comparisonService->reviewSummary($cycle);

        $body = implode("\n", [
            'Current cycle summary:',
            '- Accounts reviewed in import: '.((string) ($summary['accounts_reviewed'] ?? 0)),
            '- Priority disputes currently open: '.((string) ($summary['priority_disputes'] ?? 0)),
            '- Utilization targets identified: '.((string) ($summary['utilization_targets'] ?? 0)),
            '',
            'Recommended next steps:',
            '- Review the imported Metro 2 mismatches.',
            '- Confirm which tradelines should move into dispute.',
            '- Finalize the outward brief only after operator review.',
        ]);

        return [
            'title' => trim($client->display_name.' monthly review packet'),
            'content' => $body,
            'meta' => [
                'provider' => 'local_fallback',
                'model' => 'deterministic_template',
                'cycle_label' => $cycle->cycle_label,
            ],
        ];
    }
}
