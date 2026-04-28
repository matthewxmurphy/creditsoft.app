<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SmartCreditCaptureParser
{
    /**
     * @return array<string, mixed>|null
     */
    public function parse(string $html, ?string $pageTitle = null, ?string $pageUrl = null): ?array
    {
        if (trim($html) === '' || ! $this->looksLikeSmartCredit($html, $pageTitle, $pageUrl)) {
            return null;
        }

        $resolvedTitle = $pageTitle ?: $this->extractTitle($html);
        $profile = $this->detectProfile($html, $resolvedTitle);
        $officeContext = $this->extractOfficeContext($html);

        $parsed = match ($profile) {
            'score_tracker' => $this->parseScoreTracker($html, $resolvedTitle),
            'smart_credit_report' => $this->parseSmartCreditReport($html, $resolvedTitle),
            'three_bureau_report' => $this->parseThreeBureauReport($html, $resolvedTitle),
            default => [
                'provider' => 'smartcredit',
                'profile' => 'generic',
                'label' => $resolvedTitle ?: 'SmartCredit capture',
            ],
        };

        if ($officeContext !== []) {
            $parsed['office_context'] = $officeContext;
        }

        return $parsed;
    }

    protected function looksLikeSmartCredit(string $html, ?string $pageTitle, ?string $pageUrl): bool
    {
        $haystacks = [
            Str::lower($html),
            Str::lower((string) $pageTitle),
            Str::lower((string) $pageUrl),
        ];

        foreach ($haystacks as $haystack) {
            if ($haystack !== '' && str_contains($haystack, 'smartcredit')) {
                return true;
            }
        }

        return false;
    }

    protected function detectProfile(string $html, ?string $pageTitle): string
    {
        $title = Str::lower((string) $pageTitle);
        $normalizedHtml = Str::lower($html);

        if (str_contains($title, '3-bureau credit report') || str_contains($normalizedHtml, 'account history') && str_contains($normalizedHtml, 'all 3 match')) {
            return 'three_bureau_report';
        }

        if (str_contains($title, 'score tracker') || str_contains($normalizedHtml, 'credit-score-info') && str_contains($normalizedHtml, 'auto-score-info')) {
            return 'score_tracker';
        }

        if (str_contains($title, 'smart credit report') || str_contains($normalizedHtml, 'recordtype=trade') && str_contains($normalizedHtml, 'report-scroller')) {
            return 'smart_credit_report';
        }

        return 'generic';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractOfficeContext(string $html): array
    {
        $context = array_filter([
            'contact_email' => $this->matchString($html, "/\\['customerEmail'\\]\\s*=\\s*'([^']+)'/i"),
            'contact_first_name' => $this->matchString($html, "/\\['customerFirstName'\\]\\s*=\\s*'([^']+)'/i"),
            'contact_last_name' => $this->matchString($html, "/\\['customerLastName'\\]\\s*=\\s*'([^']+)'/i"),
            'plan' => $this->matchString($html, "/\\['plan'\\]\\s*=\\s*'([^']+)'/i"),
            'smartcredit_user_id' => $this->matchString($html, "/\\['user_id'\\]\\s*=\\s*'([^']+)'/i"),
            'publisher_id' => $this->matchString($html, "/\\['publisher_id'\\]\\s*=\\s*'([^']+)'/i"),
            'anonymous_id' => $this->matchString($html, "/\\['anonymous_id'\\]\\s*=\\s*'([^']+)'/i"),
        ]);

        $xpath = $this->xpath($html);
        $brandName = $this->cleanLabel($this->firstXPathText($xpath, "//*[contains(@class,'app-title-container')]"));
        $brandAlt = $this->cleanLabel($this->firstXPathText($xpath, "//*[contains(@class,'app-logo-container')]//img/@alt"));

        if ($brandName) {
            $context['office_brand'] = preg_replace('/\\s+/', ' ', str_replace('Credit Repair Specialists', '', $brandName));
            $context['office_brand_full'] = $brandName;
        } elseif ($brandAlt) {
            $context['office_brand_full'] = $brandAlt;
        }

        $contactName = trim(collect([
            $context['contact_first_name'] ?? null,
            $context['contact_last_name'] ?? null,
        ])->filter()->implode(' '));

        if ($contactName !== '') {
            $context['contact_name'] = Str::title($contactName);
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseScoreTracker(string $html, ?string $pageTitle): array
    {
        $xpath = $this->xpath($html);
        $history = $this->extractScoreTrackerHistory($xpath);
        $scoreCards = $this->extractScoreTrackerCards($xpath);

        $scores = array_filter([
            'credit' => data_get(collect($scoreCards)->firstWhere('key', 'credit'), 'numeric') ?? $this->numeric($this->firstXPathText($xpath, "//*[@id='credit-score-info']//h4[1]")) ?? data_get($history, '0.credit'),
            'auto' => data_get(collect($scoreCards)->firstWhere('key', 'auto'), 'numeric') ?? $this->numeric($this->firstXPathText($xpath, "//*[@id='auto-score-info']//h4[1]")) ?? data_get($history, '0.auto'),
            'insurance' => data_get(collect($scoreCards)->firstWhere('key', 'insurance'), 'numeric') ?? $this->numeric($this->firstXPathText($xpath, "//*[@id='insurance-score-info']//h4[1]")) ?? data_get($history, '0.insurance'),
        ], fn ($value) => $value !== null);

        $grades = collect($scoreCards)
            ->mapWithKeys(fn (array $card) => [$card['key'] => $card['grade_display'] ?? null])
            ->filter()
            ->all();

        return array_filter([
            'provider' => 'smartcredit',
            'profile' => 'score_tracker',
            'label' => $pageTitle ?: 'SmartCredit Score Tracker',
            'as_of_date' => $this->extractScoreTrackerAsOfDate($xpath, $html),
            'scores' => $scores,
            'grades' => $grades,
            'score_cards' => $scoreCards,
            'score_history' => $history,
            'score_history_count' => count($history),
            'score_history_chart' => $this->buildScoreHistoryChart($history),
            'graph_kind' => count($history) > 1 ? 'score_history' : 'gauge_cards',
        ], fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseSmartCreditReport(string $html, ?string $pageTitle): array
    {
        $xpath = $this->xpath($html);
        $asOf = $this->extractAsOfDate($html);
        $accounts = $this->extractSmartCreditReportAccounts($xpath);

        return array_filter([
            'provider' => 'smartcredit',
            'profile' => 'smart_credit_report',
            'label' => $pageTitle ?: 'Smart Credit Report',
            'as_of_date' => $asOf,
            'account_count' => count($accounts),
            'negative_account_count' => count(array_filter($accounts, fn ($account) => (bool) ($account['negative'] ?? false))),
            'accounts_preview' => array_slice($accounts, 0, 12),
        ], fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseThreeBureauReport(string $html, ?string $pageTitle): array
    {
        $xpath = $this->xpath($html);
        $bureauScores = $this->extractThreeBureauScores($xpath);
        $summary = $this->extractThreeBureauSummary($xpath);

        if ($summary === []) {
            $summary = $this->extractThreeBureauGlobalSummary($xpath);
        }

        $accountPreview = $this->extractThreeBureauAccountPreview($xpath);
        $accountMatrix = $this->extractThreeBureauAccountMatrix($xpath);

        return array_filter([
            'provider' => 'smartcredit',
            'profile' => 'three_bureau_report',
            'label' => $pageTitle ?: '3-Bureau Credit Report & Scores',
            'as_of_date' => $this->extractThreeBureauAsOfDate($xpath),
            'available_report_dates' => $this->extractThreeBureauReportDates($xpath),
            'bureau_scores' => $bureauScores,
            'summary' => $summary,
            'account_preview' => array_slice($accountPreview, 0, 12),
            'account_preview_count' => count($accountPreview),
            'account_matrix' => $accountMatrix,
            'account_matrix_count' => count($accountMatrix),
            'graph_kind' => 'comparison_summary',
        ], fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @return list<array{name:string,status:?string,type:?string,balance:?string,negative:bool}>
     */
    protected function extractSmartCreditReportAccounts(DOMXPath $xpath): array
    {
        $accounts = [];
        $nodes = $xpath->query("//*[@id='report-scroller']//li[@data]");

        if (! $nodes) {
            return [];
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $data = (string) $node->getAttribute('data');

            if (! str_contains($data, "type:['account'")) {
                continue;
            }

            preg_match("/title:'([^']+)'/", $data, $titleMatch);
            preg_match("/status:'([^']+)'/", $data, $statusMatch);
            preg_match("/displayType:'([^']+)'/", $data, $typeMatch);
            preg_match('/negative:(true|false)/', $data, $negativeMatch);

            $small = $xpath->query('.//small', $node)?->item(0);
            $smallText = $small ? $this->nodeText($small) : '';
            preg_match('/\$\s?[0-9,]+/', $smallText, $balanceMatch);

            $title = trim((string) ($titleMatch[1] ?? ''));

            if ($title === '') {
                continue;
            }

            $accounts[] = [
                'name' => $title,
                'status' => $this->cleanLabel($statusMatch[1] ?? null),
                'type' => $this->cleanLabel($typeMatch[1] ?? null),
                'balance' => $balanceMatch[0] ?? null,
                'negative' => ($negativeMatch[1] ?? 'false') === 'true',
            ];
        }

        return $accounts;
    }

    /**
     * @return array<string, array{value:string,match:?string}>
     */
    protected function extractThreeBureauSummary(DOMXPath $xpath): array
    {
        $summary = [];
        $nodes = $xpath->query("//h2[contains(@class,'headline')][.//span[contains(normalize-space(), 'Summary')]]/following-sibling::div[contains(@class,'attribute-collection')][1]//div[contains(@class,'attribute-row')]");

        if (! $nodes) {
            return [];
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $label = $this->firstChildTextByClass($xpath, $node, 'text-gray-900');
            $value = $this->firstXPathText($xpath, ".//div[contains(@class,'display-attribute')]//p[1]", $node);
            $match = $this->extractMatchLabel($xpath, $node);

            if ($label === null || $value === null) {
                continue;
            }

            $summary[$this->slugKey($label)] = [
                'value' => $this->cleanLabel($value) ?? $value,
                'match' => $match,
            ];
        }

        return $summary;
    }

    /**
     * @return array<string, array{value:string,match:?string}>
     */
    protected function extractThreeBureauGlobalSummary(DOMXPath $xpath): array
    {
        $summary = [];
        $nodes = $xpath->query("//div[contains(@class,'attribute-row')]");

        if (! $nodes) {
            return [];
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $label = $this->firstChildTextByClass($xpath, $node, 'text-gray-900');
            $value = $this->firstXPathText($xpath, ".//div[contains(@class,'display-attribute')]//p[1]", $node);
            $match = $this->extractMatchLabel($xpath, $node);

            if ($label === null || $value === null) {
                continue;
            }

            $summary[$this->slugKey($label)] = [
                'value' => $this->cleanLabel($value) ?? $value,
                'match' => $match,
            ];
        }

        return $summary;
    }

    /**
     * @return array<string, array{key:string,label:string,display:string,score:?int,available:bool}>
     */
    protected function extractThreeBureauScores(DOMXPath $xpath): array
    {
        $scores = [];
        $nodes = $xpath->query("//*[contains(@class,'bureau-score')]");

        if (! $nodes) {
            return [];
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $label = $this->cleanLabel($this->firstXPathText($xpath, ".//h6[1]", $node));
            $display = $this->cleanLabel($this->firstXPathText($xpath, ".//h1[1]", $node));

            if ($label === null || $display === null) {
                continue;
            }

            $key = $this->normalizeBureauKey($label);

            if ($key === null) {
                continue;
            }

            $scores[$key] = [
                'key' => $key,
                'label' => $label,
                'display' => $display,
                'score' => $this->numeric($display),
                'available' => $this->numeric($display) !== null,
            ];
        }

        return $scores;
    }

    /**
     * @return list<array{name:string,status:?string,utilization:?string}>
     */
    protected function extractThreeBureauAccountPreview(DOMXPath $xpath): array
    {
        $names = $xpath->query("//*[@data-test-account-name]");

        if (! $names) {
            return [];
        }

        $accounts = [];

        foreach ($names as $nameNode) {
            if (! $nameNode instanceof DOMElement) {
                continue;
            }

            $name = $this->nodeText($nameNode);

            if ($name === '') {
                continue;
            }

            $container = $nameNode;

            while ($container && $container instanceof DOMElement && ! str_contains($container->getAttribute('class'), 'account-container')) {
                $container = $container->parentNode instanceof DOMElement ? $container->parentNode : null;
            }

            $status = $container ? $this->firstXPathText($xpath, ".//*[@data-test-account-status][1]", $container) : null;
            $utilizationText = $container ? $this->firstXPathText($xpath, ".//*[@data-test-account-utilization][1]", $container) : null;
            preg_match('/\d+%/', (string) $utilizationText, $utilizationMatch);

            $accounts[] = [
                'name' => $this->cleanLabel($name) ?? $name,
                'status' => $this->cleanLabel($status),
                'utilization' => $utilizationMatch[0] ?? null,
            ];
        }

        return $accounts;
    }

    /**
     * @return list<array{
     *     name:string,
     *     status:?string,
     *     category:?string,
     *     utilization:?string,
     *     negative:bool,
     *     coverage_label:?string,
     *     coverage:array{experian:string,transunion:string,equifax:string},
     *     evidence:list<array{key:string,label:string,value:string,match:?string}>
     * }>
     */
    protected function extractThreeBureauAccountMatrix(DOMXPath $xpath): array
    {
        $nodes = $xpath->query("//div[contains(@class,'account-container')][.//*[@data-test-account-name]]");

        if (! $nodes) {
            return [];
        }

        $accounts = [];

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $name = $this->cleanLabel($this->firstXPathText($xpath, ".//*[@data-test-account-name][1]", $node));

            if ($name === null) {
                continue;
            }

            $status = $this->cleanLabel($this->firstXPathText($xpath, ".//*[@data-test-account-status][1]", $node));
            $utilizationText = $this->cleanLabel($this->firstXPathText($xpath, ".//*[@data-test-account-utilization][1]", $node));
            preg_match('/\d+%/', (string) $utilizationText, $utilizationMatch);

            $evidence = [];
            $matchLabels = [];
            $fieldNodes = $xpath->query(".//div[contains(@class,'attribute-row')]", $node);

            if ($fieldNodes) {
                foreach ($fieldNodes as $fieldNode) {
                    if (! $fieldNode instanceof DOMElement) {
                        continue;
                    }

                    $label = $this->cleanLabel($this->firstXPathText($xpath, ".//p[contains(@class,'text-gray-900')][1]", $fieldNode));
                    $value = $this->cleanLabel($this->firstXPathText($xpath, ".//div[contains(@class,'display-attribute')]//p[1]", $fieldNode));
                    $match = $this->extractMatchLabel($xpath, $fieldNode);

                    if ($label === null || $value === null) {
                        continue;
                    }

                    if ($match !== null) {
                        $matchLabels[] = $match;
                    }

                    $evidence[] = [
                        'key' => $this->slugKey($label),
                        'label' => $label,
                        'value' => $value,
                        'match' => $match,
                    ];
                }
            }

            $coverage = $this->deriveThreeBureauCoverage($matchLabels);

            $accounts[] = [
                'name' => $name,
                'status' => $status,
                'category' => $this->cleanLabel($this->firstXPathText($xpath, "preceding::div[contains(@class,'accounttype-heading')][1]//h3[1]", $node)),
                'utilization' => $utilizationMatch[0] ?? null,
                'negative' => str_contains($node->getAttribute('class'), 'negative-account') || str_contains(Str::lower((string) $status), 'collection') || str_contains(Str::lower((string) $status), 'derogatory'),
                'coverage_label' => $this->pickCoverageLabel($matchLabels),
                'coverage' => $coverage,
                'evidence' => array_slice($evidence, 0, 4),
            ];
        }

        return $accounts;
    }

    /**
     * @return list<array{date:string,recorded_on:?string,credit:?int,auto:?int,insurance:?int}>
     */
    protected function extractScoreTrackerHistory(DOMXPath $xpath): array
    {
        $rows = [];
        $nodes = $xpath->query("//*[contains(@class,'score-tracker-table')]//tr[position() > 1]");

        if (! $nodes) {
            return [];
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $cells = $xpath->query('./td', $node);

            if (! $cells || $cells->length < 2) {
                continue;
            }

            $date = $this->cleanLabel($this->nodeText($cells->item(0)));

            if ($date === null) {
                continue;
            }

            $rows[] = [
                'date' => $date,
                'recorded_on' => $this->normalizeDateLabel($date),
                'credit' => $this->numeric($this->nodeText($cells->item(1))),
                'auto' => $cells->length > 2 ? $this->numeric($this->nodeText($cells->item(2))) : null,
                'insurance' => $cells->length > 3 ? $this->numeric($this->nodeText($cells->item(3))) : null,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{
     *     key:string,
     *     label:string,
     *     value_display:string,
     *     numeric:?int,
     *     grade:?string,
     *     grade_display:?string,
     *     detail_url:?string,
     *     scale:'score'|'grade'
     * }>
     */
    protected function extractScoreTrackerCards(DOMXPath $xpath): array
    {
        $cards = [];
        $nodes = $xpath->query("//*[contains(@class,'score-content')]//div[contains(@class,'col-sm-3')][.//h5]");

        if (! $nodes) {
            return [];
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $label = $this->cleanLabel($this->firstXPathText($xpath, ".//h5[1]", $node));

            if ($label === null) {
                continue;
            }

            $key = $this->normalizeScoreTrackerKey($label);

            if ($key === null) {
                continue;
            }

            $valueDisplay = $this->cleanLabel($this->firstXPathText($xpath, ".//*[contains(@class,'score-details-container')]//h4[1]", $node));
            $gradeDisplay = $this->cleanLabel($this->firstXPathText($xpath, ".//*[contains(@class,'score-details-container')]//p[1]", $node));
            $detailUrl = $this->cleanLabel($this->firstXPathText($xpath, ".//*[contains(@class,'details-link')]//a[1]/@href", $node));
            $grade = $this->extractScoreTrackerGrade($valueDisplay, $gradeDisplay, $key);

            $cards[] = [
                'key' => $key,
                'label' => $label,
                'value_display' => $valueDisplay ?? ($grade ?? 'N/A'),
                'numeric' => $key === 'hiring_risk' ? null : $this->numeric($valueDisplay),
                'grade' => $grade,
                'grade_display' => $gradeDisplay,
                'detail_url' => $detailUrl,
                'scale' => $key === 'hiring_risk' ? 'grade' : 'score',
            ];
        }

        return $cards;
    }

    protected function extractThreeBureauAsOfDate(DOMXPath $xpath): ?string
    {
        return $this->cleanLabel($this->firstXPathText($xpath, "//select[@id='report-switcher']/option[@selected][1]"))
            ?? $this->cleanLabel($this->firstXPathText($xpath, "//select[@id='report-switcher']/option[1]"));
    }

    /**
     * @return list<string>
     */
    protected function extractThreeBureauReportDates(DOMXPath $xpath): array
    {
        $dates = [];
        $nodes = $xpath->query("//select[@id='report-switcher']/option");

        if (! $nodes) {
            return [];
        }

        foreach ($nodes as $node) {
            $date = $this->cleanLabel($this->nodeText($node));

            if ($date !== null) {
                $dates[] = $date;
            }
        }

        return array_values(array_unique($dates));
    }

    protected function matchString(string $html, string $pattern): ?string
    {
        if (! preg_match($pattern, $html, $match)) {
            return null;
        }

        return $this->cleanLabel(html_entity_decode((string) ($match[1] ?? ''), ENT_QUOTES | ENT_HTML5));
    }

    /**
     * @param  list<array{date:string,recorded_on:?string,credit:?int,auto:?int,insurance:?int}>  $history
     * @return array{labels:list<string>,series:list<array{key:string,label:string,color:string,values:list<?int>}>>|null
     */
    protected function buildScoreHistoryChart(array $history): ?array
    {
        if (count($history) < 2) {
            return null;
        }

        $chronological = array_reverse($history);
        $labels = array_map(fn (array $row) => $row['date'], $chronological);
        $seriesDefinitions = [
            'credit' => ['label' => 'Credit Score', 'color' => '#5f8b95'],
            'auto' => ['label' => 'Auto Score', 'color' => '#ba4d51'],
            'insurance' => ['label' => 'Insurance', 'color' => '#af8a53'],
        ];

        $series = [];

        foreach ($seriesDefinitions as $key => $definition) {
            $values = array_map(fn (array $row) => $row[$key] ?? null, $chronological);

            if (count(array_filter($values, fn ($value) => $value !== null)) === 0) {
                continue;
            }

            $series[] = [
                'key' => $key,
                'label' => $definition['label'],
                'color' => $definition['color'],
                'values' => $values,
            ];
        }

        if ($series === []) {
            return null;
        }

        return [
            'labels' => $labels,
            'series' => $series,
        ];
    }

    protected function extractAsOfDate(string $html): ?string
    {
        if (preg_match('/As of ([A-Za-z]{3} \d{1,2}, \d{4})/i', $html, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }

    protected function extractScoreTrackerAsOfDate(DOMXPath $xpath, string $html): ?string
    {
        $heading = $this->firstXPathText($xpath, "//*[contains(@class,'heading-sublink')][contains(., 'Score as of:')][1]");

        if ($heading !== null && preg_match('/Score as of:\s*([A-Za-z]{3} \d{1,2}, \d{4})/i', $heading, $matches) === 1) {
            return trim($matches[1]);
        }

        return $this->extractAsOfDate($html);
    }

    protected function extractTitle(string $html): ?string
    {
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches) !== 1) {
            return null;
        }

        return trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5));
    }

    protected function normalizeScoreTrackerKey(string $label): ?string
    {
        $normalized = Str::lower($label);

        return match (true) {
            str_contains($normalized, 'credit score') => 'credit',
            str_contains($normalized, 'auto score') => 'auto',
            str_contains($normalized, 'insurance score') => 'insurance',
            str_contains($normalized, 'hiring risk') => 'hiring_risk',
            default => null,
        };
    }

    protected function extractScoreTrackerGrade(?string $valueDisplay, ?string $gradeDisplay, string $key): ?string
    {
        if (is_string($gradeDisplay) && preg_match('/Grade:\s*([A-F])/i', $gradeDisplay, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        if ($key === 'hiring_risk' && is_string($valueDisplay) && preg_match('/^[A-F]$/i', $valueDisplay) === 1) {
            return strtoupper($valueDisplay);
        }

        return null;
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

    protected function firstXPathText(DOMXPath $xpath, string $query, ?DOMNode $context = null): ?string
    {
        $node = $xpath->query($query, $context)?->item(0);

        if (! $node) {
            return null;
        }

        $text = $this->nodeText($node);

        return $text !== '' ? $text : null;
    }

    protected function firstChildTextByClass(DOMXPath $xpath, DOMNode $context, string $classFragment): ?string
    {
        return $this->firstXPathText($xpath, ".//*[contains(@class,'{$classFragment}')][1]", $context);
    }

    protected function extractMatchLabel(DOMXPath $xpath, DOMNode $context): ?string
    {
        $textNodes = $xpath->query(".//div[contains(@class,'bubbles')]//*[self::span or self::div]", $context);

        if (! $textNodes) {
            return null;
        }

        foreach ($textNodes as $textNode) {
            $text = $this->cleanLabel($this->nodeText($textNode));

            if ($text !== null && (str_contains($text, 'Match') || str_contains($text, 'Only'))) {
                return $text;
            }
        }

        return null;
    }

    protected function numeric(?string $value): ?int
    {
        if (! is_string($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits !== '' ? (int) $digits : null;
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

    /**
     * @param  list<string>  $matchLabels
     * @return array{experian:string,transunion:string,equifax:string}
     */
    protected function deriveThreeBureauCoverage(array $matchLabels): array
    {
        $coverage = [
            'experian' => 'unknown',
            'transunion' => 'unknown',
            'equifax' => 'unknown',
        ];

        $labels = array_values(array_unique(array_filter(array_map(fn ($label) => $this->cleanLabel($label), $matchLabels))));

        foreach ($labels as $label) {
            if ($label === null) {
                continue;
            }

            $lower = Str::lower($label);

            if (str_contains($lower, 'experian only')) {
                $coverage['experian'] = 'only';
                $coverage['transunion'] = $coverage['transunion'] === 'unknown' ? 'missing' : $coverage['transunion'];
                $coverage['equifax'] = $coverage['equifax'] === 'unknown' ? 'missing' : $coverage['equifax'];
            }

            if (str_contains($lower, 'transunion only')) {
                $coverage['transunion'] = 'only';
                $coverage['experian'] = $coverage['experian'] === 'unknown' ? 'missing' : $coverage['experian'];
                $coverage['equifax'] = $coverage['equifax'] === 'unknown' ? 'missing' : $coverage['equifax'];
            }

            if (str_contains($lower, 'equifax only')) {
                $coverage['equifax'] = 'only';
                $coverage['experian'] = $coverage['experian'] === 'unknown' ? 'missing' : $coverage['experian'];
                $coverage['transunion'] = $coverage['transunion'] === 'unknown' ? 'missing' : $coverage['transunion'];
            }
        }

        if (in_array('All 3 Match', $labels, true)) {
            foreach ($coverage as $bureau => $state) {
                if ($state === 'unknown') {
                    $coverage[$bureau] = 'match';
                }
            }
        }

        if (in_array('2 Match', $labels, true)) {
            foreach ($coverage as $bureau => $state) {
                if ($state === 'unknown') {
                    $coverage[$bureau] = 'pair';
                }
            }
        }

        if (in_array('None Match', $labels, true)) {
            foreach ($coverage as $bureau => $state) {
                if ($state === 'unknown') {
                    $coverage[$bureau] = 'diff';
                }
            }
        }

        return $coverage;
    }

    /**
     * @param  list<string>  $matchLabels
     */
    protected function pickCoverageLabel(array $matchLabels): ?string
    {
        $labels = array_values(array_unique(array_filter(array_map(fn ($label) => $this->cleanLabel($label), $matchLabels))));

        foreach (['Experian Only', 'Transunion Only', 'Equifax Only', 'All 3 Match', '2 Match', 'None Match'] as $preferred) {
            if (in_array($preferred, $labels, true)) {
                return $preferred;
            }
        }

        return $labels[0] ?? null;
    }

    protected function normalizeBureauKey(string $label): ?string
    {
        $label = Str::lower($label);

        return match (true) {
            str_contains($label, 'experian') => 'experian',
            str_contains($label, 'transunion') => 'transunion',
            str_contains($label, 'equifax') => 'equifax',
            default => null,
        };
    }

    protected function slugKey(string $label): string
    {
        return Str::of($label)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();
    }
}
