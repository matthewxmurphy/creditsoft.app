<?php

namespace App\Services;

/**
 * HtmlParser Service - Parse raw HTML credit reports
 * 
 * Purpose: Extract credit report data from raw HTML
 * 
 * This service parses HTML from credit bureau reports and extracts
 * structured data including accounts, balances, payment history, etc.
 */
class HtmlParser
{
    /**
     * Parse HTML and extract credit report data
     * 
     * @param string $html Raw HTML content
     * @param string $bureau Bureau name (experian, transunion, equifax)
     * @return array Parsed data
     */
    public function parse($html, $bureau = 'experian')
    {
        $data = [
            'accounts'      => [],
            'score'         => null,
            'score_factors' => [],
            'summary'       => [],
        ];
        
        // Load HTML into DOMDocument
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        // Route to appropriate parser based on bureau
        switch (strtolower($bureau)) {
            case 'experian':
                $data = $this->parseExperian($dom);
                break;
            case 'transunion':
                $data = $this->parseTransUnion($dom);
                break;
            case 'equifax':
                $data = $this->parseEquifax($dom);
                break;
            default:
                $data = $this->parseGeneric($dom);
        }
        
        return $data;
    }

    /**
     * Parse Experian report
     * 
     * @param \DOMDocument $dom
     * @return array Parsed data
     */
    private function parseExperian($dom)
    {
        $data = [
            'accounts'      => [],
            'score'         => null,
            'score_factors' => [],
            'summary'       => [],
        ];
        
        // Extract score - look for common patterns
        $xpath = new \DOMXPath($dom);
        
        // Try various score patterns
        $scoreNodes = $xpath->query('//*[contains(translate(text(), "SCORE", "score"), "score")]');
        foreach ($scoreNodes as $node) {
            preg_match('/(\d{3})/', $node->textContent, $matches);
            if (!empty($matches[1]) && $matches[1] >= 300 && $matches[1] <= 850) {
                $data['score'] = (int)$matches[1];
                break;
            }
        }
        
        // Extract accounts from tables
        $tables = $xpath->query('//table');
        foreach ($tables as $table) {
            $accounts = $this->parseAccountTable($table);
            if (!empty($accounts)) {
                $data['accounts'] = array_merge($data['accounts'], $accounts);
            }
        }
        
        // Extract summary totals
        $data['summary'] = $this->extractSummary($dom);
        
        return $data;
    }

    /**
     * Parse TransUnion report
     * 
     * @param \DOMDocument $dom
     * @return array Parsed data
     */
    private function parseTransUnion($dom)
    {
        // Similar to Experian but with TransUnion-specific patterns
        return $this->parseExperian($dom);
    }

    /**
     * Parse Equifax report
     * 
     * @param \DOMDocument $dom
     * @return array Parsed data
     */
    private function parseEquifax($dom)
    {
        // Similar to Experian but with Equifax-specific patterns
        return $this->parseExperian($dom);
    }

    /**
     * Parse generic report format
     * 
     * @param \DOMDocument $dom
     * @return array Parsed data
     */
    private function parseGeneric($dom)
    {
        $data = [
            'accounts'      => [],
            'score'         => null,
            'score_factors' => [],
            'summary'       => [],
        ];
        
        $xpath = new \DOMXPath($dom);
        
        // Look for any 3-digit number that looks like a score
        $allText = $dom->textContent;
        preg_match_all('/\b(\d{3})\b/', $allText, $matches);
        
        foreach ($matches[1] as $potentialScore) {
            if ($potentialScore >= 500 && $potentialScore <= 850) {
                $data['score'] = (int)$potentialScore;
                break;
            }
        }
        
        // Extract accounts from any tables found
        $tables = $xpath->query('//table');
        foreach ($tables as $table) {
            $accounts = $this->parseAccountTable($table);
            $data['accounts'] = array_merge($data['accounts'], $accounts);
        }
        
        return $data;
    }

    /**
     * Parse account data from table
     * 
     * @param \DOMElement $table
     * @return array Array of accounts
     */
    private function parseAccountTable($table)
    {
        $accounts = [];
        
        $xpath = new \DOMXPath($table);
        $rows = $xpath->query('.//tr');
        
        foreach ($rows as $row) {
            $cells = $xpath->query('.//td', $row);
            
            if ($cells->length >= 3) {
                $account = [
                    'account_name'      => trim($cells->item(0)->textContent ?? ''),
                    'account_number'    => trim($cells->item(1)->textContent ?? ''),
                    'balance'           => $this->parseCurrency($cells->item(2)->textContent ?? ''),
                    'credit_limit'      => null,
                    'payment_status'    => trim($cells->item(3)->textContent ?? ''),
                    'date_opened'       => null,
                ];
                
                // Only add if it looks like a valid account
                if (!empty($account['account_name']) && strlen($account['account_name']) > 2) {
                    $accounts[] = $account;
                }
            }
        }
        
        return $accounts;
    }

    /**
     * Extract summary data
     * 
     * @param \DOMDocument $dom
     * @return array Summary data
     */
    private function extractSummary(\DOMDocument $dom)
    {
        $summary = [
            'total_accounts'    => 0,
            'open_accounts'     => 0,
            'closed_accounts'   => 0,
            'total_balance'     => 0,
            'total_credit_limit' => 0,
            'utilization_rate'  => 0,
        ];
        
        $text = $dom->textContent;
        
        // Look for account counts
        preg_match('/(\d+)\s*total\s*account/i', $text, $matches);
        if (!empty($matches[1])) {
            $summary['total_accounts'] = (int)$matches[1];
        }
        
        preg_match('/(\d+)\s*open\s*account/i', $text, $matches);
        if (!empty($matches[1])) {
            $summary['open_accounts'] = (int)$matches[1];
        }
        
        preg_match('/(\d+)\s*closed\s*account/i', $text, $matches);
        if (!empty($matches[1])) {
            $summary['closed_accounts'] = (int)$matches[1];
        }
        
        // Look for balance totals
        preg_match('/total\s*balance.*?\$?([\d,]+(?:\.\d{2})?)/i', $text, $matches);
        if (!empty($matches[1])) {
            $summary['total_balance'] = (float)str_replace(',', '', $matches[1]);
        }
        
        return $summary;
    }

    /**
     * Parse currency string to float
     * 
     * @param string $currency Currency string (e.g., "$1,234.56")
     * @return float
     */
    private function parseCurrency($currency)
    {
        // Remove currency symbols and commas
        $cleaned = preg_replace('/[^\d.-]/', '', $currency);
        return (float)$cleaned;
    }

    /**
     * Validate parsed data
     * 
     * @param array $data Data to validate
     * @return bool
     */
    public function validate($data)
    {
        // Must have at least some accounts or a score
        return !empty($data['accounts']) || !empty($data['score']);
    }
}
