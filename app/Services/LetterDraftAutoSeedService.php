<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\LetterDraft;
use App\Models\ReportingCycle;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Throwable;

class LetterDraftAutoSeedService
{
    public function __construct(
        protected CreditsoftAiService $aiService,
        protected CreditReportComparisonService $comparisonService,
        protected LetterTemplateCatalog $templates,
        protected LetterPdfDocumentService $pdfDocuments,
    ) {
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\LetterDraft>
     */
    public function ensureSeeded(Client $client, ?User $user = null): Collection
    {
        $cycle = $client->reportingCycles()->latest('started_at')->first();

        if (! $cycle) {
            return collect();
        }

        $existingLetters = $client->letters()->with('reportingCycle')->get();
        $bureauIssues = $this->buildBureauIssueMap($cycle);

        if ($existingLetters->isNotEmpty()) {
            if (! $this->shouldUpgradeToBureauTargetedDisputes($existingLetters, $cycle, $bureauIssues)) {
                return collect();
            }

            $this->removeReplaceableDisputeLetters($client, $existingLetters, $cycle);
        }

        if ($bureauIssues !== []) {
            return $this->createBureauTargetedLetters(
                client: $client,
                cycle: $cycle,
                user: $user,
                bureauIssues: $bureauIssues,
            );
        }

        $template = $this->templates->defaultForType('dispute');
        $legalBasisList = array_values(array_filter($template['legal_basis'] ?? ['FCRA Sec. 611', 'Metro 2 completeness']));
        $resolvedLegalBasis = implode(', ', $legalBasisList);
        $draft = null;
        $generatedByAi = false;

        try {
            $draft = $this->aiService->generateLetterDraft(
                client: $client,
                cycle: $cycle,
                letterType: 'dispute',
                legalBasis: $resolvedLegalBasis,
                template: $template,
                operatorFocus: 'Draft the first review-ready dispute letter in the client\'s first-person voice. Keep it concise, direct, and centered on the strongest imported inconsistencies first.',
            );
            $generatedByAi = true;
        } catch (Throwable) {
            $draft = $this->buildFallbackDraft($client, $cycle, $legalBasisList, $template);
        }

        $letter = $client->letters()->create([
            'reporting_cycle_id' => $cycle->getKey(),
            'user_id' => $user?->getKey(),
            'title' => $draft['title'],
            'letter_type' => 'dispute',
            'template_key' => $template['key'] ?? null,
            'template_version' => $template['version'] ?? null,
            'legal_basis' => $legalBasisList,
            'content' => $draft['content'],
            'generated_by_ai' => $generatedByAi,
            'ai_metadata' => $generatedByAi ? ($draft['meta'] ?? []) : null,
        ]);

        $this->pdfDocuments->ensurePdf($letter, $user);

        return collect([$letter->fresh(['reportingCycle'])]);
    }

    /**
     * @param  list<string>  $legalBasisList
     * @param  array<string, mixed>|null  $template
     * @return array{title:string,content:string,meta:array<string,mixed>}
     */
    protected function buildFallbackDraft(
        Client $client,
        ReportingCycle $cycle,
        array $legalBasisList,
        ?array $template = null,
    ): array {
        $review = collect($this->comparisonService->comparisonRows($cycle))
            ->filter(fn (array $row) => count($row['mismatches'] ?? []) > 0)
            ->sortByDesc(fn (array $row) => count($row['mismatches'] ?? []))
            ->take(6)
            ->values();

        $body = [
            'To Whom It May Concern,',
            '',
            'I am requesting a reinvestigation of the items below because the latest credit review shows incomplete, inconsistent, or otherwise unverifiable reporting.',
            '',
            'Accounts requiring review:',
            ...$this->formatFindings($review),
            '',
            'Please investigate these items and correct, update, or delete any information that cannot be verified as complete and accurate.',
            '',
            'Thank you for your prompt attention to this matter.',
            '',
            'Sincerely,',
            $client->display_name,
        ];

        return [
            'title' => trim($client->display_name.' dispute packet - '.$cycle->cycle_label),
            'content' => implode("\n", $body),
            'meta' => [
                'provider' => 'local_fallback',
                'model' => 'deterministic_template',
                'template_key' => $template['key'] ?? null,
                'template_version' => $template['version'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, list<array{account:string,summary:string,mismatches:list<string>}>>  $bureauIssues
     * @return \Illuminate\Support\Collection<int, \App\Models\LetterDraft>
     */
    protected function createBureauTargetedLetters(
        Client $client,
        ReportingCycle $cycle,
        ?User $user,
        array $bureauIssues,
    ): Collection {
        $template = $this->templates->defaultForType('dispute');
        $legalBasisList = array_values(array_filter($template['legal_basis'] ?? ['FCRA Sec. 611', 'Metro 2 completeness']));

        return collect(['experian', 'transunion', 'equifax'])
            ->filter(fn (string $bureau) => filled($bureauIssues[$bureau] ?? null))
            ->map(function (string $bureau) use ($client, $cycle, $user, $template, $legalBasisList, $bureauIssues): LetterDraft {
                $issues = $bureauIssues[$bureau];
                $bureauLabel = $this->bureauLabel($bureau);

                $letter = $client->letters()->create([
                    'reporting_cycle_id' => $cycle->getKey(),
                    'user_id' => $user?->getKey(),
                    'title' => "{$bureauLabel} Credit Report Dispute Letter",
                    'letter_type' => 'dispute',
                    'template_key' => $template['key'] ?? null,
                    'template_version' => $template['version'] ?? null,
                    'legal_basis' => $legalBasisList,
                    'content' => $this->buildBureauLetterContent($client, $bureau, $issues),
                    'generated_by_ai' => false,
                    'ai_metadata' => [
                        'provider' => 'deterministic_bureau_template',
                        'model' => 'factual_dispute_template',
                        'recipient_bureau' => $bureau,
                        'issue_count' => count($issues),
                        'issue_accounts' => collect($issues)->pluck('account')->values()->all(),
                    ],
                ]);

                $this->pdfDocuments->ensurePdf($letter, $user);

                return $letter->fresh(['reportingCycle']);
            })
            ->values();
    }

    /**
     * @return array<string, list<array{account:string,summary:string,mismatches:list<string>}>>
     */
    protected function buildBureauIssueMap(ReportingCycle $cycle): array
    {
        $issues = [
            'experian' => [],
            'transunion' => [],
            'equifax' => [],
        ];

        foreach ($this->comparisonService->comparisonRows($cycle) as $row) {
            $targets = $this->issueTargetsForRow($row);

            foreach ($targets as $bureau) {
                $summary = $this->summarizeRowForBureau($bureau, $row);

                if ($summary === null) {
                    continue;
                }

                $fingerprint = mb_strtolower(trim(($row['label'] ?? 'unknown').'|'.$summary));

                $issues[$bureau][$fingerprint] = [
                    'account' => (string) ($row['label'] ?? 'Unknown account'),
                    'summary' => $summary,
                    'mismatches' => array_values($row['mismatches'] ?? []),
                ];
            }
        }

        return collect($issues)
            ->map(fn (array $items) => array_values($items))
            ->filter(fn (array $items) => $items !== [])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    protected function issueTargetsForRow(array $row): array
    {
        $mismatches = collect($row['mismatches'] ?? [])->values();
        $presentBureaus = collect($row['bureaus'] ?? [])->filter(fn ($bureau) => is_array($bureau))->keys();
        $missingBureaus = collect(['experian', 'transunion', 'equifax'])
            ->reject(fn (string $bureau) => $presentBureaus->contains($bureau));
        $targets = collect();

        if ($mismatches->contains('missing_bureau_entry')) {
            $targets = $targets->merge($missingBureaus);
        }

        if ($mismatches->contains('single_bureau_reporting') && $presentBureaus->count() === 1) {
            $targets = $targets->merge($presentBureaus);
        }

        if ($mismatches->intersect([
            'status_conflict',
            'payment_status_conflict',
            'open_state_conflict',
            'balance_conflict',
            'date_conflict',
            'duplicate_account',
        ])->isNotEmpty()) {
            $targets = $targets->merge($presentBureaus);
        }

        return $targets
            ->filter(fn ($bureau) => is_string($bureau) && $bureau !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function summarizeRowForBureau(string $bureau, array $row): ?string
    {
        $presentBureaus = collect($row['bureaus'] ?? [])->filter(fn ($item) => is_array($item));
        /** @var array<string, mixed>|null $current */
        $current = $presentBureaus->get($bureau);
        $others = $presentBureaus->except($bureau)->filter(fn ($item) => is_array($item));
        $mismatches = collect($row['mismatches'] ?? [])->values();
        $clauses = [];

        if ($mismatches->contains('missing_bureau_entry') && $current === null && $others->isNotEmpty()) {
            $clauses[] = 'this account appears on '.$this->joinBureaus($others->keys()->all()).' but is missing from my '.$this->bureauLabel($bureau).' file';
        }

        if ($mismatches->contains('single_bureau_reporting') && $current !== null && $presentBureaus->count() === 1) {
            $clauses[] = 'this account appears only on my '.$this->bureauLabel($bureau).' file and not on the other bureaus';
        }

        if ($current !== null && $mismatches->contains('status_conflict')) {
            $clauses[] = $this->fieldConflictClause($bureau, $current, $others, 'account_status', 'status');
        }

        if ($current !== null && $mismatches->contains('payment_status_conflict')) {
            $clauses[] = $this->fieldConflictClause($bureau, $current, $others, 'payment_status', 'payment status');
        }

        if ($current !== null && $mismatches->contains('balance_conflict')) {
            $clauses[] = $this->fieldConflictClause($bureau, $current, $others, 'balance', 'balance');
        }

        if ($current !== null && $mismatches->contains('date_conflict')) {
            $clauses[] = $this->fieldConflictClause($bureau, $current, $others, 'date_last_payment', 'last payment date');
        }

        if ($current !== null && $mismatches->contains('duplicate_account')) {
            $clauses[] = 'this account appears more than once on my '.$this->bureauLabel($bureau).' file';
        }

        $clauses = array_values(array_filter($clauses));

        if ($clauses === []) {
            return null;
        }

        return Str::ucfirst(implode('; ', $clauses)).'.';
    }

    /**
     * @param  \Illuminate\Support\Collection<string, array<string, mixed>>  $others
     * @param  array<string, mixed>  $current
     */
    protected function fieldConflictClause(
        string $bureau,
        array $current,
        Collection $others,
        string $field,
        string $label,
    ): ?string {
        $currentValue = $this->formatLetterFieldValue($field, $current[$field] ?? null);
        $otherValues = $others
            ->mapWithKeys(fn (array $other, string $otherBureau) => [$otherBureau => $this->formatLetterFieldValue($field, $other[$field] ?? null)])
            ->filter(fn (?string $value) => $value !== null)
            ->all();

        if ($currentValue === null || $otherValues === []) {
            return null;
        }

        $uniqueOtherValues = array_values(array_unique(array_values($otherValues)));

        if (count($uniqueOtherValues) === 1 && $uniqueOtherValues[0] === $currentValue) {
            return null;
        }

        $otherSummary = collect($otherValues)
            ->map(fn (string $value, string $otherBureau) => $this->bureauLabel($otherBureau).' shows '.$value)
            ->values()
            ->implode(', ');

        return 'your report shows '.$label.' '.$currentValue.' while '.$otherSummary;
    }

    protected function formatLetterFieldValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return 'missing';
        }

        return match ($field) {
            'balance' => is_numeric($value) ? '$'.number_format((float) $value, 2) : (string) $value,
            'date_last_payment' => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
                ? \Illuminate\Support\Carbon::parse($value)->format('F j, Y')
                : (string) $value,
            default => (string) $value,
        };
    }

    protected function joinBureaus(array $bureaus): string
    {
        $labels = array_values(array_map(fn (string $bureau) => $this->bureauLabel($bureau), $bureaus));

        if (count($labels) <= 1) {
            return $labels[0] ?? 'the other bureaus';
        }

        $last = array_pop($labels);

        return implode(', ', $labels).' and '.$last;
    }

    /**
     * @param  list<array{account:string,summary:string,mismatches:list<string>}>  $issues
     */
    protected function buildBureauLetterContent(Client $client, string $bureau, array $issues): string
    {
        $bureauLabel = $this->bureauLabel($bureau);
        $body = [
            "To {$bureauLabel},",
            '',
            "I am writing to dispute inaccurate, incomplete, or unverifiable information appearing on my {$bureauLabel} credit report. Please conduct a reinvestigation under FCRA Section 611 of the items below.",
            '',
            'The information I am disputing includes:',
            '',
        ];

        foreach (collect($issues)->take(8)->values() as $index => $issue) {
            $body[] = ($index + 1).'. '.$issue['account'].': '.$issue['summary'];
        }

        $body = array_merge($body, [
            '',
            'Please review these items and delete or correct any information that cannot be verified as complete and accurate.',
            'Please send me an updated copy of my credit report after your investigation is complete.',
            '',
            'Sincerely,',
            $client->display_name,
        ]);

        return implode("\n", $body);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\LetterDraft>  $existingLetters
     * @param  array<string, list<array{account:string,summary:string,mismatches:list<string>}>>  $bureauIssues
     */
    protected function shouldUpgradeToBureauTargetedDisputes(Collection $existingLetters, ReportingCycle $cycle, array $bureauIssues): bool
    {
        if ($bureauIssues === []) {
            return false;
        }

        $cycleDisputes = $existingLetters
            ->where('reporting_cycle_id', $cycle->getKey())
            ->where('letter_type', 'dispute')
            ->values();

        if ($cycleDisputes->isEmpty()) {
            return true;
        }

        if ($cycleDisputes->contains(fn (LetterDraft $letter) => filled(data_get($letter->ai_metadata, 'recipient_bureau')))) {
            return false;
        }

        if ($cycleDisputes->count() !== 1) {
            return false;
        }

        $letter = $cycleDisputes->first();
        $title = Str::lower((string) $letter->title);
        $content = Str::lower((string) $letter->content);

        return $letter instanceof LetterDraft
            && $letter->status === 'draft'
            && ($letter->generated_by_ai
                || $title === 'credit report dispute letter'
                || Str::contains($title, ['dispute packet', 'three-bureau', 'round 1'])
                || Str::contains($content, ['file reference:', 'total accounts under dispute:', '[client services on behalf of client']));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\LetterDraft>  $existingLetters
     */
    protected function removeReplaceableDisputeLetters(Client $client, Collection $existingLetters, ReportingCycle $cycle): void
    {
        $replaceable = $existingLetters
            ->where('reporting_cycle_id', $cycle->getKey())
            ->where('letter_type', 'dispute')
            ->filter(fn (LetterDraft $letter) => ! filled(data_get($letter->ai_metadata, 'recipient_bureau')))
            ->values();

        if ($replaceable->isEmpty()) {
            return;
        }

        $letterIds = $replaceable->pluck('id')->values()->all();

        $client->documents()
            ->where('category', 'letter_pdf')
            ->get()
            ->filter(fn (ClientDocument $document) => in_array((int) data_get($document->metadata, 'letter_draft_id', 0), $letterIds, true))
            ->each(function (ClientDocument $document): void {
                if (filled($document->file_path) && File::exists($document->file_path)) {
                    File::delete($document->file_path);
                }

                $document->delete();
            });

        $replaceable->each->delete();
    }

    protected function bureauLabel(string $bureau): string
    {
        return match ($bureau) {
            'transunion' => 'TransUnion',
            'equifax' => 'Equifax',
            'experian' => 'Experian',
            default => Str::headline($bureau),
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $review
     * @return list<string>
     */
    protected function formatFindings(Collection $review): array
    {
        if ($review->isEmpty()) {
            return [
                '- Review the latest imported tradelines for missing bureau entries, single-bureau reporting, and status mismatches before sending.',
            ];
        }

        return $review->map(function (array $row): string {
            $flags = collect($row['mismatches'] ?? [])
                ->map(fn (string $flag) => Str::of($flag)->replace('_', ' ')->upper()->value())
                ->implode('; ');

            return '- '.$row['name'].': '.$flags;
        })->values()->all();
    }
}
