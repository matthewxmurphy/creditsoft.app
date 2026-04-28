<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CreditKarmaCaptureParser
{
    /**
     * @return array<string, mixed>|null
     */
    public function parse(string $html, ?string $pageTitle = null, ?string $pageUrl = null): ?array
    {
        if (trim($html) === '' || ! $this->looksLikeCreditKarma($html, $pageTitle, $pageUrl)) {
            return null;
        }

        $resolvedTitle = $pageTitle ?: $this->extractTitle($html);
        $resolvedUrl = $pageUrl ?: $this->extractCanonicalUrl($html);
        $bureau = $this->detectBureau($resolvedUrl, $resolvedTitle, $html);
        $providedBy = $this->extractProvidedBy($html);
        $score = $this->extractScore($html);
        $subjectName = $this->extractSubjectName($html);
        $reportDates = $this->extractReportDates($html, $bureau);
        $history = $this->buildScoreHistory($reportDates, $score);
        $personalInformation = $this->extractPersonalInformation($html);
        $sectionNotices = $this->extractSectionNotices($html);

        return array_filter([
            'provider' => 'credit_karma',
            'profile' => 'credit_report',
            'label' => $resolvedTitle ?: 'Your Credit Health | Credit Karma',
            'bureau' => $bureau,
            'provided_by' => $providedBy,
            'subject_name' => $subjectName,
            'as_of_date' => $reportDates[0]['date'] ?? null,
            'scores' => $score !== null ? ['credit' => $score] : null,
            'score_history' => $history,
            'score_history_count' => count($history),
            'score_history_chart' => $this->buildScoreHistoryChart($history),
            'available_report_dates' => array_map(fn (array $point) => $point['date'], $reportDates),
            'personal_information' => $personalInformation,
            'section_notices' => $sectionNotices,
            'bureau_coverage' => [
                'reported_bureaus' => ['equifax', 'transunion'],
                'missing_bureaus' => ['experian'],
                'coverage' => 'two_bureau_only',
            ],
            'provider_warnings' => [
                'credit_karma_does_not_include_experian',
            ],
            'graph_kind' => count($history) > 1 ? 'score_history' : 'bureau_score',
        ], fn ($value) => $value !== null && $value !== [] && $value !== '');
    }

    protected function looksLikeCreditKarma(string $html, ?string $pageTitle, ?string $pageUrl): bool
    {
        $haystacks = [
            Str::lower($html),
            Str::lower((string) $pageTitle),
            Str::lower((string) $pageUrl),
        ];

        foreach ($haystacks as $haystack) {
            if ($haystack !== '' && str_contains($haystack, 'credit karma')) {
                return true;
            }
        }

        return false;
    }

    protected function detectBureau(?string $pageUrl, ?string $pageTitle, string $html): ?string
    {
        $url = Str::lower((string) $pageUrl);
        $title = Str::lower((string) $pageTitle);
        $providedBy = Str::lower((string) $this->extractProvidedBy($html));

        return match (true) {
            str_contains($url, '/equifax/'),
            str_contains($title, 'equifax'),
            str_contains($providedBy, 'equifax') => 'equifax',
            str_contains($url, '/transunion/'),
            str_contains($title, 'transunion'),
            str_contains($providedBy, 'transunion') => 'transunion',
            default => null,
        };
    }

    protected function extractProvidedBy(string $html): ?string
    {
        if (preg_match('/Provided by ([A-Za-z]+(?:Union)?)/i', $html, $matches) !== 1) {
            return null;
        }

        return $this->cleanLabel($matches[1]);
    }

    protected function extractScore(string $html): ?int
    {
        if (preg_match('/<div class="[^"]*\bscore\b[^"]*">\s*(\d{3})\s*<\/div>/i', $html, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    protected function extractSubjectName(string $html): ?string
    {
        if (preg_match('/<h1[^>]*>\s*([^<]+?)\s*<\/h1>/i', $html, $matches) !== 1) {
            return null;
        }

        return $this->cleanLabel(Str::title(Str::lower($matches[1])));
    }

    /**
     * @return list<array{date:string,recorded_on:?string}>
     */
    protected function extractReportDates(string $html, ?string $bureau): array
    {
        $bureauKey = $bureau ? Str::lower($bureau).'ReportHistory' : null;
        $labels = $bureauKey ? $this->extractQuotedArrayForHistoryKey($html, $bureauKey, 'reportDateLabels') : [];
        $isoDates = $bureauKey ? $this->extractQuotedArrayForHistoryKey($html, $bureauKey, 'reportDates') : [];

        $points = [];

        foreach ($labels as $index => $label) {
            $points[] = [
                'date' => $label,
                'recorded_on' => isset($isoDates[$index]) ? $this->normalizeIsoDate($isoDates[$index]) : $this->normalizeDateLabel($label),
            ];
        }

        if ($points === []) {
            return [];
        }

        return array_values(array_filter($points, fn (array $point) => filled($point['date'])));
    }

    /**
     * @return list<string>
     */
    protected function extractQuotedArrayForHistoryKey(string $html, string $historyKey, string $field): array
    {
        $quotedKey = preg_quote($historyKey, '/');
        $quotedField = preg_quote($field, '/');

        if (preg_match('/"'.$quotedKey.'":\{.*?"'.$quotedField.'":\[(.*?)\]/s', $html, $matches) !== 1) {
            return [];
        }

        preg_match_all('/"([^"]+)"/', $matches[1], $valueMatches);

        return array_values(array_filter(array_map(
            fn (string $value) => $this->cleanLabel($value),
            $valueMatches[1] ?? [],
        )));
    }

    /**
     * @param  list<array{date:string,recorded_on:?string}>  $reportDates
     * @return list<array{date:string,recorded_on:?string,credit:?int,auto:?int,insurance:?int}>
     */
    protected function buildScoreHistory(array $reportDates, ?int $score): array
    {
        if ($score === null) {
            return [];
        }

        $latest = $reportDates[0] ?? null;

        return [[
            'date' => $latest['date'] ?? 'Latest',
            'recorded_on' => $latest['recorded_on'] ?? null,
            'credit' => $score,
            'auto' => null,
            'insurance' => null,
        ]];
    }

    /**
     * @return array<string, list<string>>
     */
    protected function extractPersonalInformation(string $html): array
    {
        $xpath = $this->xpath($html);

        return array_filter([
            'names_reported' => $this->extractSectionList($xpath, 'NAMES REPORTED'),
            'employment_info' => $this->extractSectionList($xpath, 'EMPLOYMENT INFO'),
            'addresses_reported' => $this->extractSectionList($xpath, 'ADDRESSES REPORTED'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function extractSectionNotices(string $html): array
    {
        $xpath = $this->xpath($html);
        $sections = [
            'collections' => 'Collections',
            'public_records' => 'Public Records',
        ];

        $notices = [];

        foreach ($sections as $key => $heading) {
            $notice = $this->extractSectionNotice($xpath, $heading);

            if ($notice !== null) {
                $notices[$key] = $notice;
            }
        }

        return $notices;
    }

    protected function extractSectionNotice(DOMXPath $xpath, string $heading): ?string
    {
        $headingNode = $this->findHeadingNode($xpath, $heading);

        if (! $headingNode) {
            return null;
        }

        $section = $headingNode;

        while ($section instanceof DOMElement && $section->tagName !== 'section') {
            $section = $section->parentNode instanceof DOMElement ? $section->parentNode : null;
        }

        if (! $section) {
            return null;
        }

        $text = $this->cleanLabel($this->nodeText($section));

        if ($text === null) {
            return null;
        }

        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        if (Str::length($text) > 260) {
            $text = trim(Str::limit($text, 260, '...'));
        }

        return $text;
    }

    /**
     * @return list<string>
     */
    protected function extractSectionList(DOMXPath $xpath, string $heading): array
    {
        $headingNode = $this->findHeadingNode($xpath, $heading);

        if (! $headingNode) {
            return [];
        }

        $section = $headingNode;

        while ($section instanceof DOMElement && $section->tagName !== 'section') {
            $section = $section->parentNode instanceof DOMElement ? $section->parentNode : null;
        }

        if (! $section) {
            return [];
        }

        $items = $xpath->query('.//li', $section);

        if (! $items) {
            return [];
        }

        $values = [];

        foreach ($items as $item) {
            if (! $item instanceof DOMElement) {
                continue;
            }

            $text = $this->cleanLabel($this->nodeText($item));

            if ($text !== null) {
                $values[] = $text;
            }
        }

        return array_values(array_unique($values));
    }

    protected function findHeadingNode(DOMXPath $xpath, string $heading): ?DOMElement
    {
        $nodes = $xpath->query('//h2 | //h3');

        if (! $nodes) {
            return null;
        }

        $needle = Str::upper($heading);

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $text = Str::upper($this->nodeText($node));

            if ($text !== '' && str_contains($text, $needle)) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @param  list<array{date:string,recorded_on:?string,credit:?int,auto:?int,insurance:?int}>  $history
     * @return array{labels:list<string>,series:list<array{key:string,label:string,color:string,values:list<?int>}>>|null
     */
    protected function buildScoreHistoryChart(array $history): ?array
    {
        if ($history === []) {
            return null;
        }

        $chronological = array_reverse($history);
        $values = array_map(fn (array $row) => $row['credit'] ?? null, $chronological);

        if (count(array_filter($values, fn ($value) => $value !== null)) === 0) {
            return null;
        }

        return [
            'labels' => array_map(fn (array $row) => $row['date'], $chronological),
            'series' => [[
                'key' => 'credit',
                'label' => 'Credit Score',
                'color' => '#6b7280',
                'values' => $values,
            ]],
        ];
    }

    protected function extractTitle(string $html): ?string
    {
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches) !== 1) {
            return null;
        }

        return trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));
    }

    protected function extractCanonicalUrl(string $html): ?string
    {
        if (preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $matches) !== 1) {
            return null;
        }

        return $this->cleanLabel($matches[1]);
    }

    protected function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument();

        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    protected function nodeText(?DOMNode $node): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $node?->textContent) ?? '');
    }

    protected function cleanLabel(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = trim(preg_replace('/\s+/', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5)) ?? '');

        return $clean !== '' ? $clean : null;
    }

    protected function normalizeDateLabel(string $label): ?string
    {
        try {
            return Carbon::parse($label)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function normalizeIsoDate(string $value): ?string
    {
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
