<?php

namespace App\Services;

use App\Models\ManagedLetterTemplate;
use App\Models\MigrationOperatorCapture;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MigrationOperatorLetterTemplateImporter
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function import(array $payload, User $user, ?MigrationOperatorCapture $capture = null): ManagedLetterTemplate
    {
        $html = (string) ($payload['html'] ?? $capture?->content_html ?? '');
        $metadata = $this->normalizedMetadata($payload, $capture);

        $label = $this->resolveLabel($payload, $capture, $metadata, $html);
        $contentTemplate = $this->resolveContentTemplate($payload, $metadata, $html);
        $letterType = $this->resolveLetterType($payload, $label, $contentTemplate);
        $legalBasis = $this->resolveLegalBasis($payload);
        $operatorNotes = trim((string) ($payload['operator_notes'] ?? $payload['operator_note'] ?? $capture?->operator_note ?? ''));
        $sourceSystem = Str::lower((string) ($payload['source_system'] ?? $capture?->source_system ?? 'legacy'));
        $pageUrl = (string) ($payload['page_url'] ?? $capture?->page_url ?? '');
        $publicSourceSystem = 'imported';
        $key = $this->uniqueKey($label);

        $template = ManagedLetterTemplate::query()->create([
            'key' => $key,
            'version' => (string) ($payload['version'] ?? now()->format('Y.m.d.His')),
            'label' => $label,
            'letter_type' => $letterType,
            'legal_basis' => $legalBasis,
            'ai_focus' => $this->buildAiFocus($payload, $letterType),
            'operator_notes' => $operatorNotes !== '' ? $operatorNotes : $this->defaultOperatorNotes(),
            'content_template' => $contentTemplate,
            'source_system' => $publicSourceSystem,
            'source_page_url' => null,
            'metadata' => array_filter([
                'imported_via' => 'migration_operator',
                'capture_id' => $capture?->getKey(),
                'page_title' => $payload['page_title'] ?? $capture?->page_title,
                'migration_source_system' => $sourceSystem !== '' ? $sourceSystem : null,
                'migration_source_page_url' => $pageUrl !== '' ? $pageUrl : null,
                'source_host' => data_get($metadata, 'source_host'),
                'source_category' => data_get($metadata, 'selected_category'),
                'source_title_field' => data_get($metadata, 'selected_title'),
                'source_description' => data_get($metadata, 'selected_description.value'),
                'source_description_field' => data_get($metadata, 'selected_description'),
                'source_body_field' => data_get($metadata, 'selected_body_field'),
                'field_candidates' => data_get($metadata, 'field_candidates'),
                'headings' => data_get($metadata, 'headings'),
            ], fn ($value) => ! is_null($value) && $value !== [] && $value !== ''),
            'created_by' => $user->getKey(),
            'updated_by' => $user->getKey(),
        ]);

        if ($capture) {
            $capture->forceFill([
                'status' => 'imported',
                'processed_at' => now(),
                'metadata' => array_replace_recursive($capture->metadata ?? [], [
                    'imported_letter_template_id' => $template->getKey(),
                    'imported_letter_template_key' => $template->key,
                ]),
            ])->save();
        }

        return $template;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizedMetadata(array $payload, ?MigrationOperatorCapture $capture): array
    {
        $metadata = $payload['metadata'] ?? $capture?->metadata ?? [];

        return is_array($metadata) ? $metadata : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    protected function resolveLabel(array $payload, ?MigrationOperatorCapture $capture, array $metadata, string $html): string
    {
        $candidates = [
            trim((string) ($payload['label'] ?? '')),
            trim((string) data_get($metadata, 'selected_title.value', '')),
            trim((string) data_get($metadata, 'title_candidates.0.value', '')),
            trim((string) ($capture?->page_title ?? '')),
            trim($this->extractFirstMatch($html, [
                '//input[contains(translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "letter") and (contains(translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "name") or contains(translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "title"))]/@value',
                '//input[contains(translate(@id, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "letter") and (contains(translate(@id, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "name") or contains(translate(@id, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "title"))]/@value',
                '//h1/text()',
                '//h2/text()',
            ])),
        ];

        $label = collect($candidates)
            ->map(fn ($value) => preg_replace('/\s+/', ' ', (string) $value))
            ->filter(fn ($value) => filled($value))
            ->reject(fn ($value) => Str::contains(Str::lower((string) $value), ['disputefox', 'letter library |', 'add-letter-library']))
            ->first();

        return $label ?: 'Imported letter template';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    protected function resolveContentTemplate(array $payload, array $metadata, string $html): string
    {
        $explicit = trim((string) ($payload['content_template'] ?? ''));

        if ($explicit !== '') {
            return $explicit;
        }

        $candidatePools = [
            data_get($metadata, 'body_candidates', []),
            data_get($metadata, 'textareas', []),
            data_get($metadata, 'content_editable', []),
        ];

        foreach ($candidatePools as $pool) {
            if (! is_array($pool)) {
                continue;
            }

            foreach ($pool as $candidate) {
                $value = trim((string) data_get($candidate, 'value', ''));

                if ($this->looksLikeBody($value)) {
                    return $this->normalizeTemplateContent($value);
                }
            }
        }

        $body = $this->extractFirstMatch($html, [
            '//*[contains(@class, "ck-content")]',
            '//*[@contenteditable="true"]',
            '//textarea[contains(translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "body")]',
            '//textarea[contains(translate(@id, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "body")]',
            '//textarea[contains(translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "letter")]',
            '//textarea[contains(translate(@id, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "letter")]',
        ]);

        if ($this->looksLikeBody($body)) {
            return $this->normalizeTemplateContent($body);
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    protected function resolveLegalBasis(array $payload): array
    {
        $legalBasis = $payload['legal_basis'] ?? [];

        if (is_string($legalBasis)) {
            $legalBasis = array_map('trim', explode(',', $legalBasis));
        }

        if (! is_array($legalBasis)) {
            $legalBasis = [];
        }

        $resolved = collect($legalBasis)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        return $resolved !== [] ? $resolved : ['FCRA § 611', 'Accuracy and completeness'];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveLetterType(array $payload, string $label, string $content): string
    {
        $explicit = trim((string) ($payload['letter_type'] ?? ''));

        if (in_array($explicit, ['dispute', 'follow_up', 'escalation'], true)) {
            return $explicit;
        }

        $haystack = Str::lower($label.' '.$content);

        return match (true) {
            Str::contains($haystack, ['follow up', 'reinvestigation', 'second round']) => 'follow_up',
            Str::contains($haystack, ['escalat', 'regulator', 'cfpb', 'attorney general']) => 'escalation',
            default => 'dispute',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function buildAiFocus(array $payload, string $letterType): string
    {
        $explicit = trim((string) ($payload['ai_focus'] ?? ''));

        if ($explicit !== '') {
            return $explicit;
        }

        return match ($letterType) {
            'follow_up' => 'Use the imported legacy structure as the anchor, but tighten the follow-up around what was ignored, partially corrected, or left unsupported.',
            'escalation' => 'Use the imported legacy structure as the anchor, then frame the chronology, unresolved evidence, and escalation ask more clearly.',
            default => 'Use the imported legacy structure as the anchor, then sharpen the facts, keep the client voice clean, and make the dispute easier to review.',
        };
    }

    protected function defaultOperatorNotes(): string
    {
        return 'Imported through the internal migration operator. Review the structure, merge fields, and legal framing before using it in production.';
    }

    protected function uniqueKey(string $label): string
    {
        $base = Str::slug(trim($label), '_');
        $base = Str::limit($base !== '' ? $base : 'imported_letter_template', 54, '');
        $key = 'imported_'.$base;
        $suffix = 1;

        while (ManagedLetterTemplate::query()->where('key', $key)->exists()) {
            $suffix++;
            $key = 'imported_'.Str::limit($base, max(1, 54 - strlen((string) $suffix) - 1), '').'_'.$suffix;
        }

        return $key;
    }

    /**
     * @param  list<string>  $expressions
     */
    protected function extractFirstMatch(string $html, array $expressions): string
    {
        if (trim($html) === '') {
            return '';
        }

        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new DOMXPath($dom);

        foreach ($expressions as $expression) {
            $nodes = $xpath->query($expression);

            if (! $nodes || $nodes->length === 0) {
                continue;
            }

            foreach ($nodes as $node) {
                $value = trim((string) $node->textContent);

                if ($node->nodeType === XML_ATTRIBUTE_NODE) {
                    $value = trim((string) $node->nodeValue);
                }

                if ($this->looksLikeBody($value) || strlen($value) >= 4) {
                    return $value;
                }
            }
        }

        return '';
    }

    protected function looksLikeBody(string $value): bool
    {
        $trimmed = trim($value);

        return strlen($trimmed) >= 80 && Str::contains($trimmed, ['\n', "\n", 'Dear ', 'Sincerely', 'To whom', 'credit']);
    }

    protected function normalizeTemplateContent(string $value): string
    {
        $normalized = preg_replace("/\r\n?/", "\n", $value) ?? $value;
        $normalized = preg_replace("/\n{3,}/", "\n\n", $normalized) ?? $normalized;

        return trim((string) $normalized);
    }
}
