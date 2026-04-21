<?php

namespace App\Services;

use Illuminate\Support\Str;

class SimplePdfWriter
{
    /**
     * @param  list<string>  $metaLines
     */
    public function renderLetter(string $title, array $metaLines, string $body): string
    {
        $lines = array_values(array_filter([
            $this->normalizeLine(Str::upper($title)),
            '',
            ...array_map(fn (string $line) => $this->normalizeLine($line), $metaLines),
            '',
            ...$this->normalizeBody($body),
        ], fn (mixed $line) => is_string($line)));

        $pages = array_chunk($lines, 44);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pageReferences = [];
        $objectNumber = 4;

        foreach ($pages as $pageLines) {
            $pageObject = $objectNumber++;
            $contentObject = $objectNumber++;
            $pageReferences[] = "{$pageObject} 0 R";

            $stream = $this->buildContentStream($pageLines);

            $objects[$pageObject] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentObject} 0 R >>";
            $objects[$contentObject] = "<< /Length ".strlen($stream)." >>\nstream\n{$stream}endstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageReferences).'] /Count '.count($pageReferences).' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }

        $startXref = strlen($pdf);
        $pdf .= 'xref'."\n";
        $pdf .= '0 '.(max(array_keys($objects)) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (range(1, max(array_keys($objects))) as $id) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }

        $pdf .= "trailer\n";
        $pdf .= '<< /Size '.(max(array_keys($objects)) + 1).' /Root 1 0 R >>'."\n";
        $pdf .= "startxref\n{$startXref}\n%%EOF";

        return $pdf;
    }

    /**
     * @param  list<string>  $pageLines
     */
    protected function buildContentStream(array $pageLines): string
    {
        $stream = "BT\n/F1 11 Tf\n14 TL\n54 756 Td\n";

        foreach ($pageLines as $line) {
            if ($line === '') {
                $stream .= "T*\n";

                continue;
            }

            $stream .= '('.$this->escapePdfText($line).") Tj\nT*\n";
        }

        return $stream."ET\n";
    }

    /**
     * @return list<string>
     */
    protected function normalizeBody(string $body): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $body);
        $paragraphs = preg_split("/\n{2,}/", $text) ?: [];
        $lines = [];

        foreach ($paragraphs as $paragraph) {
            $normalized = trim(preg_replace('/\s+/u', ' ', $this->normalizeLine($paragraph)) ?? '');

            if ($normalized === '') {
                continue;
            }

            foreach (explode("\n", wordwrap($normalized, 92, "\n", true)) as $line) {
                $lines[] = $line;
            }

            $lines[] = '';
        }

        return $lines === [] ? ['No draft body was available.'] : $lines;
    }

    protected function normalizeLine(string $value): string
    {
        $line = str_replace('§', 'Sec.', $value);
        $line = preg_replace('/\s+/u', ' ', $line) ?? $line;

        return trim(Str::ascii($line));
    }

    protected function escapePdfText(string $value): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $value,
        );
    }
}
