<?php

namespace App\Support;

use Illuminate\Support\Str;

class ClientName
{
    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public static function normalizeFields(array $fields): array
    {
        foreach (['first_name', 'middle_name', 'last_name', 'name_suffix'] as $field) {
            if (array_key_exists($field, $fields)) {
                $fields[$field] = $field === 'name_suffix'
                    ? self::normalizeSuffix($fields[$field])
                    : self::normalizePart($fields[$field]);
            }
        }

        if (array_key_exists('first_name', $fields) && blank($fields['middle_name'] ?? null)) {
            $parts = preg_split('/\s+/', (string) ($fields['first_name'] ?? '')) ?: [];

            if (count($parts) > 1) {
                $fields['first_name'] = self::normalizePart(array_shift($parts));
                $fields['middle_name'] = self::normalizePart(implode(' ', $parts));
            }
        }

        if (array_key_exists('last_name', $fields) && blank($fields['name_suffix'] ?? null)) {
            $parts = preg_split('/\s+/', (string) ($fields['last_name'] ?? '')) ?: [];
            $lastPart = $parts !== [] ? (string) end($parts) : '';
            $normalizedSuffix = self::suffixKey($lastPart);

            if (count($parts) > 1 && self::suffixMap($normalizedSuffix) !== null) {
                $fields['name_suffix'] = self::normalizeSuffix(array_pop($parts));
                $fields['last_name'] = self::normalizePart(implode(' ', $parts));
            }
        }

        return $fields;
    }

    public static function normalizePart(mixed $value): ?string
    {
        $name = Str::squish((string) $value);

        if ($name === '') {
            return null;
        }

        return preg_replace_callback(
            "/[A-Za-z]+(?:['-][A-Za-z]+)*/",
            fn (array $matches): string => self::normalizeToken($matches[0]),
            $name,
        ) ?? $name;
    }

    public static function normalizeSuffix(mixed $value): ?string
    {
        $suffix = Str::squish((string) $value);

        if ($suffix === '') {
            return null;
        }

        return self::suffixMap(self::suffixKey($suffix)) ?? self::normalizePart($suffix);
    }

    protected static function normalizeToken(string $token): string
    {
        $letters = preg_replace('/[^A-Za-z]+/', '', $token) ?? '';

        if ($letters === '') {
            return $token;
        }

        $isAllLower = strtolower($letters) === $letters;
        $isAllUpper = strtoupper($letters) === $letters;
        $isSimpleTitle = preg_match('/^[A-Z][a-z]+(?:[\'-][a-z]+)*$/', $token) === 1;

        if (! $isAllLower && ! $isAllUpper && ! $isSimpleTitle) {
            return $token;
        }

        $title = Str::of($token)->lower()->title()->value();

        return preg_replace_callback(
            '/\bMc([a-z])/',
            fn (array $matches): string => 'Mc'.strtoupper($matches[1]),
            $title,
        ) ?? $title;
    }

    protected static function suffixKey(string $value): string
    {
        return Str::of($value)->lower()->replaceMatches('/[^a-z0-9]/', '')->value();
    }

    protected static function suffixMap(string $key): ?string
    {
        return [
            'jr' => 'Jr.',
            'sr' => 'Sr.',
            'ii' => 'II',
            'iii' => 'III',
            'iv' => 'IV',
            'v' => 'V',
            'vi' => 'VI',
            'vii' => 'VII',
            'viii' => 'VIII',
            'ix' => 'IX',
            'x' => 'X',
        ][$key] ?? null;
    }
}
