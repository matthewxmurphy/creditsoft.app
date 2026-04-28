<?php

namespace App\Services;

use App\Models\LetterDraft;
use Illuminate\Support\Str;

class LetterDraftPresentationService
{
    public function title(LetterDraft $letter): string
    {
        $title = trim((string) $letter->title);

        if ($letter->letter_type === 'dispute' && $this->looksLikeLegacyPacketTitle($title)) {
            return 'Credit Report Dispute Letter';
        }

        return $title !== '' ? $title : Str::headline($letter->letter_type).' Letter';
    }

    public function content(LetterDraft $letter): string
    {
        $body = $this->replaceClientPlaceholders($letter);

        if (! $this->looksLikeLegacyPacket($letter, $body)) {
            return trim($body);
        }

        $lines = preg_split('/\R/u', $body) ?: [];
        $normalized = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                if (($normalized[count($normalized) - 1] ?? null) !== '') {
                    $normalized[] = '';
                }

                continue;
            }

            if (preg_match('/^DISPUTE IDENTIFICATION:/i', $trimmed)) {
                continue;
            }

            if (preg_match('/^Dear Disputes Department,?$/i', $trimmed)) {
                $normalized[] = 'To Whom It May Concern,';
                $normalized[] = '';

                continue;
            }

            if (Str::startsWith($trimmed, 'This constitutes a formal dispute under')) {
                $normalized[] = 'I am writing to dispute inaccurate, incomplete, or unverifiable information appearing on my credit report.';
                $normalized[] = 'The items below contain inconsistencies or missing information that I am asking you to investigate and correct.';
                $normalized[] = '';

                continue;
            }

            if (preg_match('/^DISPUTED ACCOUNTS AND NATURE OF INCONSISTENCIES:?$/i', $trimmed)) {
                $normalized[] = 'The items I am disputing include:';
                $normalized[] = '';

                continue;
            }

            if (preg_match('/^INVESTIGATION REQUEST:?$/i', $trimmed)) {
                $normalized[] = 'Please investigate each of these items and correct or delete any information that cannot be verified as complete and accurate.';
                $normalized[] = '';

                continue;
            }

            if (Str::startsWith($trimmed, 'Pursuant to FCRA')) {
                $normalized[] = 'Please review each disputed item against your records and update or delete any information that is incomplete, inaccurate, or cannot be verified.';
                $normalized[] = '';

                continue;
            }

            if (preg_match('/^\[Client Services on behalf of Client \d+\]$/i', $trimmed)) {
                continue;
            }

            if (preg_match('/^(File Reference|Dispute Type|Total Accounts Under Dispute):/i', $trimmed)) {
                continue;
            }

            if ($trimmed === 'Sincerely,') {
                break;
            }

            $normalized[] = $trimmed;
        }

        while (($normalized[count($normalized) - 1] ?? null) === '') {
            array_pop($normalized);
        }

        $normalized[] = '';
        $normalized[] = 'Sincerely,';
        $normalized[] = $letter->client->display_name;

        return implode("\n", $normalized);
    }

    protected function replaceClientPlaceholders(LetterDraft $letter): string
    {
        return str_replace(
            ['[Client Name]', '[CLIENT NAME]', '{{client_name}}', '{{ client_name }}'],
            $letter->client->display_name,
            (string) ($letter->content ?? ''),
        );
    }

    protected function looksLikeLegacyPacket(LetterDraft $letter, string $body): bool
    {
        if ($letter->letter_type !== 'dispute') {
            return false;
        }

        return Str::contains($body, [
            'DISPUTE IDENTIFICATION:',
            'Dear Disputes Department,',
            '[Client Services on behalf of Client',
            'File Reference:',
            'Dispute Type:',
            'Total Accounts Under Dispute:',
            'three-bureau comparison review',
        ]);
    }

    protected function looksLikeLegacyPacketTitle(string $title): bool
    {
        return Str::contains(Str::lower($title), [
            'round 1',
            'three-bureau cross-reference',
            'dispute packet',
            'metro 2 dispute',
        ]);
    }
}
