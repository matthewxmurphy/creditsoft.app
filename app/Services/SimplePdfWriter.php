<?php

namespace App\Services;

use Illuminate\Support\Str;

class SimplePdfWriter
{
    /**
     * @param  list<string>  $metaLines
     */
    public function renderLetter(string $title, array $metaLines, string $body, array $options = []): string
    {
        $style = $this->pdfStyle($options);
        $body = $style === 'typed_typos'
            ? $this->applyTypoProfile($body, (string) ($options['typo_rate'] ?? 'light'))
            : $body;
        $wrapWidth = str_starts_with($style, 'handwritten_') ? 76 : 92;
        $lines = array_values(array_filter([
            $this->normalizeLine(Str::upper($title)),
            '',
            ...array_map(fn (string $line) => $this->normalizeLine($line), $metaLines),
            '',
            ...$this->normalizeBody($body, $wrapWidth),
        ], fn (mixed $line) => is_string($line)));

        $pages = array_chunk($lines, str_starts_with($style, 'handwritten_') ? 38 : 44);
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Italic >>',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>',
        ];
        $pageReferences = [];
        $objectNumber = 6;

        foreach ($pages as $pageIndex => $pageLines) {
            $pageObject = $objectNumber++;
            $contentObject = $objectNumber++;
            $pageReferences[] = "{$pageObject} 0 R";

            $stream = $this->buildContentStream($pageLines, [
                ...$options,
                'style' => $style,
                'page_index' => $pageIndex,
            ]);

            $objects[$pageObject] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R /F2 4 0 R /F3 5 0 R >> >> /Contents {$contentObject} 0 R >>";
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
    protected function buildContentStream(array $pageLines, array $options): string
    {
        $style = (string) ($options['style'] ?? 'typed');

        if (str_starts_with($style, 'handwritten_')) {
            return $this->buildHandwrittenContentStream($pageLines, $options);
        }

        return $this->buildTypedContentStream($pageLines, $style);
    }

    /**
     * @param  list<string>  $pageLines
     */
    protected function buildTypedContentStream(array $pageLines, string $style): string
    {
        $font = $style === 'typed_typos' ? 'F3' : 'F1';
        $fontSize = $style === 'typed_typos' ? '10.5' : '11';
        $stream = "BT\n/{$font} {$fontSize} Tf\n14 TL\n54 756 Td\n";

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
     * @param  list<string>  $pageLines
     */
    protected function buildHandwrittenContentStream(array $pageLines, array $options): string
    {
        $stream = '';
        $style = (string) ($options['style'] ?? 'handwritten_right');
        $seed = crc32(json_encode($pageLines).$style.':'.($options['page_index'] ?? 0));
        $baseY = 756.0;

        foreach ($pageLines as $index => $line) {
            if ($line === '') {
                $baseY -= 18.0;

                continue;
            }

            $x = 54.0 + $this->jitter($seed, $index * 17 + 1, -2.2, 2.4);
            $y = $baseY + $this->jitter($seed, $index * 17 + 2, -1.1, 1.2);
            $fontSize = 12.1 + $this->jitter($seed, $index * 17 + 3, -0.55, 0.65);
            $stream .= $this->renderHandwrittenLine($line, $x, $y, $fontSize, $style, $seed + $index * 101);
            $baseY -= 16.8 + $this->jitter($seed, $index * 17 + 4, -0.6, 0.9);
        }

        return $stream;
    }

    protected function renderHandwrittenLine(string $line, float $x, float $y, float $fontSize, string $style, int $seed): string
    {
        $stream = '';
        $cursor = $x;
        $leftHanded = $style === 'handwritten_left';
        $skew = $leftHanded ? -0.055 : 0.075;

        foreach (mb_str_split($line) as $index => $char) {
            if ($char === ' ') {
                $cursor += $fontSize * 0.36;

                continue;
            }

            $size = $fontSize + $this->jitter($seed, $index * 13 + 1, -0.45, 0.35);
            $charX = $cursor + $this->jitter($seed, $index * 13 + 2, -0.28, 0.34);
            $charY = $y + $this->jitter($seed, $index * 13 + 3, -0.42, 0.42);
            $charSkew = $skew + $this->jitter($seed, $index * 13 + 4, -0.018, 0.018);
            $stream .= sprintf(
                "BT\n/F2 %.2F Tf\n1 0 %.4F 1 %.2F %.2F Tm\n(%s) Tj\nET\n",
                $size,
                $charSkew,
                $charX,
                $charY,
                $this->escapePdfText($char),
            );
            $cursor += $this->characterAdvance($char, $fontSize);
        }

        return $stream;
    }

    /**
     * @return list<string>
     */
    protected function normalizeBody(string $body, int $wrapWidth = 92): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $body);
        $paragraphs = preg_split("/\n{2,}/", $text) ?: [];
        $lines = [];

        foreach ($paragraphs as $paragraph) {
            $normalized = trim(preg_replace('/\s+/u', ' ', $this->normalizeLine($paragraph)) ?? '');

            if ($normalized === '') {
                continue;
            }

            foreach (explode("\n", wordwrap($normalized, $wrapWidth, "\n", true)) as $line) {
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

    protected function pdfStyle(array $options): string
    {
        $style = (string) ($options['style'] ?? 'typed');

        return in_array($style, ['typed', 'typed_typos', 'handwritten_right', 'handwritten_left'], true)
            ? $style
            : 'typed';
    }

    protected function applyTypoProfile(string $body, string $typoRate): string
    {
        $rates = [
            'none' => 0.0,
            'light' => 0.045,
            'medium' => 0.085,
        ];
        $rate = $rates[$typoRate] ?? $rates['light'];

        if ($rate <= 0) {
            return $body;
        }

        $misspellings = [
            'the' => 'hte',
            'and' => 'adn',
            'because' => 'becuase',
            'receive' => 'recieve',
            'received' => 'recieved',
            'address' => 'adress',
            'account' => 'acount',
            'accounts' => 'acounts',
            'information' => 'infromation',
            'immediately' => 'imediately',
            'please' => 'plesae',
            'request' => 'reqeust',
            'verified' => 'verfied',
            'inaccurate' => 'inacurate',
            'investigation' => 'investagation',
        ];
        $seed = crc32($body.':'.$typoRate);
        $index = 0;

        return preg_replace_callback('/\b[A-Za-z]{3,}\b/', function (array $match) use ($misspellings, $rate, $seed, &$index): string {
            $word = $match[0];
            $lower = strtolower($word);

            if (! isset($misspellings[$lower])) {
                $index++;

                return $word;
            }

            $shouldReplace = $this->jitter($seed, $index, 0, 1) <= $rate;
            $index++;

            if (! $shouldReplace) {
                return $word;
            }

            $replacement = $misspellings[$lower];

            return ctype_upper($word[0])
                ? ucfirst($replacement)
                : $replacement;
        }, $body) ?? $body;
    }

    protected function characterAdvance(string $char, float $fontSize): float
    {
        if (preg_match('/[ilI1.,]/', $char) === 1) {
            return $fontSize * 0.27;
        }

        if (preg_match('/[mwMW]/', $char) === 1) {
            return $fontSize * 0.78;
        }

        return $fontSize * 0.52;
    }

    protected function jitter(int $seed, int $index, float $min, float $max): float
    {
        $hash = crc32($seed.':'.$index);
        $unit = ($hash % 10000) / 9999;

        return $min + (($max - $min) * $unit);
    }
}
