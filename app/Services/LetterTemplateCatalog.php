<?php

namespace App\Services;

use App\Creditsoft\Config\YamlConfigLoader;
use App\Models\Client;
use App\Models\BrowserCapture;
use App\Models\ManagedLetterTemplate;
use App\Models\Tradeline;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LetterTemplateCatalog
{
    public function __construct(protected YamlConfigLoader $loader)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $config = $this->loader->load();

        $coreTemplates = collect(($config['letter_templates.yaml']['templates'] ?? []))
            ->filter(fn (mixed $template) => is_array($template) && filled($template['key'] ?? null))
            ->map(fn (array $template) => [
                'key' => (string) $template['key'],
                'version' => (string) ($template['version'] ?? '1'),
                'label' => (string) ($template['label'] ?? $template['key']),
                'letter_type' => (string) ($template['letter_type'] ?? 'dispute'),
                'legal_basis' => array_values(array_filter($template['legal_basis'] ?? [])),
                'description' => $this->resolveCoreDescription($template),
                'ai_focus' => $template['ai_focus'] ?? null,
                'operator_notes' => $template['operator_notes'] ?? null,
                'content_template' => $template['content_template'] ?? $template['content'] ?? null,
                'source_label' => 'CreditSoft core',
                'imported' => false,
                'applicability_tags' => $this->resolveApplicabilityTags(
                    (string) ($template['label'] ?? $template['key']),
                    (string) ($template['operator_notes'] ?? ''),
                    (string) ($template['content_template'] ?? $template['content'] ?? ''),
                    (string) ($template['description'] ?? ''),
                ),
            ]);

        $managedTemplates = collect();

        if (Schema::hasTable('managed_letter_templates')) {
            $managedTemplates = ManagedLetterTemplate::query()
                ->where('is_active', true)
                ->latest('updated_at')
                ->get()
                ->map(fn (ManagedLetterTemplate $template) => [
                    'key' => (string) $template->key,
                    'version' => (string) ($template->version ?: '1'),
                    'label' => (string) $template->label,
                    'letter_type' => (string) ($template->letter_type ?: 'dispute'),
                    'legal_basis' => array_values(array_filter($template->legal_basis ?? [])),
                    'description' => $this->resolveManagedDescription($template),
                    'ai_focus' => $template->ai_focus,
                    'operator_notes' => $template->operator_notes,
                    'content_template' => $template->content_template,
                    'source_label' => 'Imported',
                    'imported' => true,
                    'applicability_tags' => $this->resolveApplicabilityTags(
                        (string) $template->label,
                        $this->resolveManagedCategoryLabel($template),
                        (string) $template->content_template,
                        (string) data_get($template->metadata, 'source_description', ''),
                    ),
                ]);
        }

        return $coreTemplates
            ->concat($managedTemplates)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function availableForClient(Client $client): array
    {
        return $this->reviewForClient($client)['available'];
    }

    /**
     * @return array{
     *     signals: array<string, bool>,
     *     recommended: list<array<string, mixed>>,
     *     available: list<array<string, mixed>>,
     *     still_available: list<array<string, mixed>>,
     *     hidden: list<array<string, mixed>>
     * }
     */
    public function reviewForClient(Client $client): array
    {
        $templates = collect($this->all());
        $signals = $this->clientSignals($client);

        if (! ($signals['has_report_context'] ?? false)) {
            return [
                'signals' => $signals,
                'recommended' => [],
                'available' => $templates->values()->all(),
                'still_available' => $templates->values()->all(),
                'hidden' => [],
            ];
        }

        $available = $templates
            ->filter(fn (array $template) => $this->templateMatchesSignals($template, $signals))
            ->values();

        $hidden = $templates
            ->reject(fn (array $template) => $this->templateMatchesSignals($template, $signals))
            ->map(fn (array $template) => [
                ...$template,
                'availability_reason' => $this->hiddenReasonForTemplate($template, $signals),
            ])
            ->values();

        $recommended = $available
            ->map(fn (array $template) => [
                ...$template,
                'recommendation_score' => $this->recommendationScoreForTemplate($template, $signals),
                'recommendation_reason' => $this->recommendationReasonForTemplate($template, $signals),
            ])
            ->filter(fn (array $template) => ($template['recommendation_score'] ?? 0) > 0)
            ->sortByDesc(fn (array $template) => (int) $template['recommendation_score'])
            ->values();

        $recommendedKeys = $recommended->pluck('key');

        return [
            'signals' => $signals,
            'recommended' => $recommended->take(8)->values()->all(),
            'available' => $available->values()->all(),
            'still_available' => $available
                ->reject(fn (array $template) => $recommendedKeys->contains($template['key']))
                ->values()
                ->all(),
            'hidden' => $hidden->values()->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forType(string $letterType): array
    {
        return collect($this->all())
            ->where('letter_type', $letterType)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(?string $key): ?array
    {
        if (blank($key)) {
            return null;
        }

        return collect($this->all())->firstWhere('key', $key);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function defaultForType(string $letterType): ?array
    {
        return collect($this->forType($letterType))->first();
    }

    public function exists(?string $key): bool
    {
        return $this->find($key) !== null;
    }

    /**
     * @return array<string, bool>
     */
    public function clientSignals(Client $client): array
    {
        $latestCycle = $client->relationLoaded('reportingCycles')
            ? $client->reportingCycles->sortByDesc('started_at')->first()
            : $client->reportingCycles()->with('bureauSnapshots.tradelines')->latest('started_at')->first();

        $latestCycle?->loadMissing('bureauSnapshots.tradelines');

        /** @var Collection<int, BrowserCapture> $captures */
        $captures = $client->relationLoaded('browserCaptures')
            ? $client->browserCaptures->sortByDesc('imported_at')->take(5)->values()
            : $client->browserCaptures()->latest('imported_at')->limit(5)->get();

        /** @var Collection<int, Tradeline> $tradelines */
        $tradelines = $latestCycle?->bureauSnapshots?->flatMap->tradelines ?? collect();

        $hasReportContext = $captures->isNotEmpty() || $tradelines->isNotEmpty();

        if (! $hasReportContext) {
            return [
                'has_report_context' => false,
                'has_bankruptcy' => false,
                'has_inquiries' => false,
                'has_late_payments' => false,
                'has_collection_chargeoffs' => false,
            ];
        }

        $captureBlob = Str::lower($captures->map(function (BrowserCapture $capture): string {
            $metadata = json_encode($capture->metadata ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return trim(implode(' ', array_filter([
                $capture->page_title,
                $capture->page_url,
                is_string($metadata) ? $metadata : '',
                Str::limit((string) $capture->extracted_text, 5000, ''),
            ])));
        })->implode(' '));

        $tradelineBlob = Str::lower($tradelines->map(function (Tradeline $tradeline): string {
            $data = json_encode($tradeline->data ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return trim(implode(' ', array_filter([
                $tradeline->creditor_name,
                $tradeline->account_status,
                $tradeline->payment_status,
                $tradeline->remarks,
                is_string($data) ? $data : '',
            ])));
        })->implode(' '));

        $smartCreditRows = $captures->flatMap(fn (BrowserCapture $capture) => data_get($capture->metadata, 'smartcredit.account_matrix', []));
        $smartCreditPreview = $captures->flatMap(fn (BrowserCapture $capture) => data_get($capture->metadata, 'smartcredit.account_preview', []));
        $smartCreditSummary = $captures
            ->map(fn (BrowserCapture $capture) => data_get($capture->metadata, 'smartcredit.summary', []))
            ->first(fn ($summary) => is_array($summary) && $summary !== []);

        $matrixBlob = Str::lower($smartCreditRows->map(fn ($row) => json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '')->implode(' '));
        $previewBlob = Str::lower($smartCreditPreview->map(fn ($row) => json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '')->implode(' '));
        $combinedBlob = trim($captureBlob.' '.$tradelineBlob.' '.$matrixBlob.' '.$previewBlob);

        $inquiryCount = collect([
            data_get($smartCreditSummary, 'inquiries.value'),
        ])->map(function ($value): ?int {
            if (is_int($value)) {
                return $value;
            }

            if (is_string($value) && preg_match('/\d+/', $value, $matches) === 1) {
                return (int) $matches[0];
            }

            return null;
        })->filter(fn ($value) => $value !== null)->max() ?? 0;

        $hasBankruptcyKeywords = preg_match('/\b(bankrupt(?:cy)?|chapter\s*7|chapter\s*13)\b/i', $combinedBlob) === 1;
        $hasNegativePublicRecordNotice = preg_match('/\b(no|zero|0)\s+public\s+records?\b|\bpublic\s+records?\s*[:\-]?\s*(none|0)\b/i', $combinedBlob) === 1;
        $hasPublicRecordKeywords = preg_match('/\bpublic\s+record(s)?\b/i', $combinedBlob) === 1 && ! $hasNegativePublicRecordNotice;

        $hasCollections = Str::contains($combinedBlob, ['collection', 'chargeoff', 'charge off', 'derogatory']);
        $hasLatePayments = Str::contains($combinedBlob, [' late ', '30 day late', '30 days late', '60 day late', '60 days late', '90 day late', '90 days late', '120 day late', '120 days late']);
        $hasInquiries = $inquiryCount > 0
            || (
                Str::contains($combinedBlob, ['hard inquiry', 'hard inquiries'])
                && preg_match('/\b0\b[^a-z0-9]{0,6}hard inquiries?\b|\bhard inquiries?\b[^0-9]{0,12}0\b/i', $combinedBlob) !== 1
            );

        return [
            'has_report_context' => true,
            'has_bankruptcy' => $hasBankruptcyKeywords || $hasPublicRecordKeywords,
            'has_inquiries' => $hasInquiries,
            'has_late_payments' => $hasLatePayments,
            'has_collection_chargeoffs' => $hasCollections,
        ];
    }

    /**
     * @param  array<string, mixed>  $template
     */
    protected function resolveCoreDescription(array $template): string
    {
        $description = trim((string) ($template['description'] ?? ''));

        if ($description !== '') {
            return $description;
        }

        return trim((string) ($template['operator_notes'] ?? $template['ai_focus'] ?? 'CreditSoft core template ready for drafting and review.'));
    }

    protected function resolveManagedDescription(ManagedLetterTemplate $template): string
    {
        $category = $this->resolveManagedCategoryLabel($template);
        $label = trim((string) $template->label);
        $content = trim((string) $template->content_template);
        $haystack = Str::lower(trim($category.' '.$label.' '.$content));
        $round = $this->resolveRoundLabel($category, $label);
        $audience = $this->resolveAudienceLabel($category, $haystack);
        $action = $this->resolveActionLabel($haystack, (string) ($template->letter_type ?: 'dispute'));

        $parts = array_filter([
            $round,
            $audience,
            $action,
        ]);

        if ($parts === []) {
            return 'Imported legacy template rewritten into a cleaner CreditSoft drafting lane.';
        }

        return ucfirst(implode(' ', $parts)).'.';
    }

    protected function resolveManagedCategoryLabel(ManagedLetterTemplate $template): string
    {
        $category = data_get($template->metadata, 'source_category');

        if (is_array($category)) {
            return trim((string) ($category['label'] ?? $category['value'] ?? ''));
        }

        return trim((string) $category);
    }

    /**
     * @return list<string>
     */
    protected function resolveApplicabilityTags(string $label, string $categoryOrNotes, string $content, string $description = ''): array
    {
        $haystack = Str::lower(trim(implode(' ', array_filter([
            $label,
            $categoryOrNotes,
            $description,
            $content,
        ]))));

        $tags = [];

        if (Str::contains($haystack, ['bankrupt', 'chapter 7', 'chapter 13', 'public record'])) {
            $tags[] = 'bankruptcy';
        }

        if (Str::contains($haystack, ['inquiry', 'hard inquiry'])) {
            $tags[] = 'inquiry';
        }

        if (Str::contains($haystack, ['late payment', 'late payments', '30 day late', '30 days late', '60 day late', '60 days late', '90 day late', '90 days late'])) {
            $tags[] = 'late_payment';
        }

        if (Str::contains($haystack, ['collection', 'chargeoff', 'charge off', 'derogatory'])) {
            $tags[] = 'collection_chargeoff';
        }

        if ($tags === []) {
            $tags[] = 'general';
        }

        return array_values(array_unique($tags));
    }

    protected function resolveRoundLabel(string $category, string $label): ?string
    {
        $combined = trim($category.' '.$label);

        if (preg_match('/round\s+(\d+)/i', $combined, $matches) === 1) {
            return 'Round '.$matches[1];
        }

        return null;
    }

    protected function resolveAudienceLabel(string $category, string $haystack): string
    {
        $categoryLower = Str::lower($category);

        return match (true) {
            Str::contains($categoryLower, 'freeze') || Str::contains($haystack, ['security freeze', 'freeze']) => 'freeze template',
            Str::contains($categoryLower, 'bureau') || Str::contains($haystack, ['credit bureau', 'transunion', 'equifax', 'experian']) => 'bureau template',
            Str::contains($categoryLower, 'creditor') || Str::contains($haystack, ['creditor', 'furnisher']) => 'creditor template',
            Str::contains($haystack, 'inquiry') => 'inquiry dispute template',
            default => 'legacy dispute template',
        };
    }

    protected function resolveActionLabel(string $haystack, string $letterType): string
    {
        return match (true) {
            Str::contains($haystack, ['freeze']) => 'for placing or reinforcing a security freeze',
            Str::contains($haystack, ['intent to sue', 'threatening intent to sue', 'lawsuit']) => 'for escalating pressure with intent-to-sue language',
            Str::contains($haystack, ['no response', 'failed to respond']) => 'for pushing after no response',
            Str::contains($haystack, ['proof', 'provide proof']) => 'for demanding proof and documentation',
            Str::contains($haystack, ['investigation procedure', 'investigation procedures', 'procedure of investigation']) => 'for demanding investigation procedures',
            Str::contains($haystack, ['verification']) => 'for challenging or following up on verification',
            Str::contains($haystack, ['inquiry']) => 'for contesting hard inquiries',
            $letterType === 'follow_up' => 'for a second-pass follow-up after weak or partial handling',
            $letterType === 'escalation' => 'for a higher-pressure escalation lane',
            default => 'for credit report dispute and cleanup work',
        };
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, bool>  $signals
     */
    protected function templateMatchesSignals(array $template, array $signals): bool
    {
        $tags = collect($template['applicability_tags'] ?? [])
            ->filter(fn ($tag) => is_string($tag) && $tag !== '')
            ->values();

        if ($tags->contains('bankruptcy') && ! ($signals['has_bankruptcy'] ?? false)) {
            return false;
        }

        if ($tags->contains('inquiry') && ! ($signals['has_inquiries'] ?? false)) {
            return false;
        }

        if ($tags->contains('late_payment') && ! ($signals['has_late_payments'] ?? false)) {
            return false;
        }

        if ($tags->contains('collection_chargeoff') && ! ($signals['has_collection_chargeoffs'] ?? false)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, bool>  $signals
     */
    protected function recommendationScoreForTemplate(array $template, array $signals): int
    {
        $score = 0;
        $tags = collect($template['applicability_tags'] ?? []);
        $labelHaystack = Str::lower(trim(($template['label'] ?? '').' '.($template['description'] ?? '').' '.($template['operator_notes'] ?? '')));

        if ($tags->contains('collection_chargeoff') && ($signals['has_collection_chargeoffs'] ?? false)) {
            $score += 40;
        }

        if ($tags->contains('late_payment') && ($signals['has_late_payments'] ?? false)) {
            $score += 32;
        }

        if ($tags->contains('general')) {
            $score += 14;
        }

        if (! ($template['imported'] ?? false)) {
            $score += 18;
        }

        $score += match ($template['letter_type'] ?? null) {
            'dispute' => 10,
            'follow_up' => 6,
            'escalation' => 4,
            default => 2,
        };

        if (Str::contains($labelHaystack, ['round 1', 'metro 2', 'reinvestigation', 'proof', 'verification'])) {
            $score += 8;
        }

        if (Str::contains($labelHaystack, ['intent to sue', 'lawsuit', 'freeze'])) {
            $score -= 6;
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, bool>  $signals
     */
    protected function recommendationReasonForTemplate(array $template, array $signals): string
    {
        $tags = collect($template['applicability_tags'] ?? []);

        if ($tags->contains('collection_chargeoff') && ($signals['has_collection_chargeoffs'] ?? false)) {
            return 'Matches collection or chargeoff activity in this file.';
        }

        if ($tags->contains('late_payment') && ($signals['has_late_payments'] ?? false)) {
            return 'Matches late-payment activity in this file.';
        }

        if (! ($template['imported'] ?? false)) {
            return 'Core CreditSoft template that fits the current dispute lane.';
        }

        return 'Still fits the imported report and current letter lane.';
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, bool>  $signals
     */
    protected function hiddenReasonForTemplate(array $template, array $signals): string
    {
        $tags = collect($template['applicability_tags'] ?? []);

        if ($tags->contains('bankruptcy') && ! ($signals['has_bankruptcy'] ?? false)) {
            return 'Hidden because this file has no bankruptcy or public-record signal.';
        }

        if ($tags->contains('inquiry') && ! ($signals['has_inquiries'] ?? false)) {
            return 'Hidden because this file has no inquiry signal.';
        }

        if ($tags->contains('late_payment') && ! ($signals['has_late_payments'] ?? false)) {
            return 'Hidden because this file has no late-payment signal.';
        }

        if ($tags->contains('collection_chargeoff') && ! ($signals['has_collection_chargeoffs'] ?? false)) {
            return 'Hidden because this file has no collection or chargeoff signal.';
        }

        return 'Hidden because it does not fit the current imported report context.';
    }
}
