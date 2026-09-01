<?php

namespace App\Support;

class PhoneNumber
{
    public static function normalize(mixed $value): ?string
    {
        $phone = trim((string) $value);

        if ($phone === '') {
            return null;
        }

        $extension = '';

        if (preg_match('/\s+(?:ext\.?|extension|x)\s*(\d{1,8})\s*$/i', $phone, $matches) === 1) {
            $extension = $matches[1];
            $phone = trim(substr($phone, 0, -strlen($matches[0])));
        }

        $digits = self::digits($phone);

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) !== 10) {
            return trim((string) $value);
        }

        $formatted = sprintf(
            '+1 (%s) %s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 4),
        );

        return $extension !== '' ? $formatted.' x'.$extension : $formatted;
    }

    public static function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }
}
