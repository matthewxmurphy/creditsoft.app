<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class MailingAddress
{
    /**
     * @return array<string, mixed>
     */
    public static function normalizeFields(array $fields): array
    {
        foreach (['address_line_1', 'address_line_2', 'city', 'state', 'postal_code'] as $field) {
            if (array_key_exists($field, $fields) && is_string($fields[$field])) {
                $fields[$field] = Str::squish($fields[$field]) ?: null;
            }
        }

        $parsed = self::parseEmbeddedLine($fields['address_line_1'] ?? null);

        if ($parsed === null) {
            return $fields;
        }

        $fields['address_line_1'] = $parsed['address_line_1'];

        foreach (['city', 'state', 'postal_code'] as $field) {
            if (blank($fields[$field] ?? null)) {
                $fields[$field] = $parsed[$field];
            }
        }

        return $fields;
    }

    /**
     * @return array{address_line_1:string,city:string,state:string,postal_code:string}|null
     */
    public static function parseEmbeddedLine(mixed $value): ?array
    {
        $raw = Str::squish((string) $value);

        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(.+),\s*([^,]+),\s*([A-Za-z]{2}|[A-Za-z][A-Za-z .\'-]*?)\s+(\d{5}(?:-\d{4})?)$/', $raw, $matches) !== 1) {
            return null;
        }

        $street = Str::squish($matches[1] ?? '');
        $city = Str::squish($matches[2] ?? '');
        $state = Str::squish($matches[3] ?? '');
        $postalCode = Str::squish($matches[4] ?? '');

        if ($street === '' || $city === '' || $state === '' || $postalCode === '') {
            return null;
        }

        return [
            'address_line_1' => $street,
            'city' => $city,
            'state' => $state,
            'postal_code' => $postalCode,
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function cityStateZipLine(array $fields): string
    {
        $fields = self::normalizeFields($fields);
        $cityState = trim(implode(', ', array_filter([
            trim((string) ($fields['city'] ?? '')),
            trim((string) ($fields['state'] ?? '')),
        ])));

        return trim(implode(' ', array_filter([
            $cityState,
            trim((string) ($fields['postal_code'] ?? '')),
        ])));
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function mailingLabel(mixed $name, array $fields): ?string
    {
        $fields = self::normalizeFields($fields);

        $lines = collect([
            $name,
            $fields['address_line_1'] ?? null,
            $fields['address_line_2'] ?? null,
            self::cityStateZipLine($fields),
        ])
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values();

        return $lines->isNotEmpty() ? $lines->implode("\n") : null;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function fingerprint(array $fields): ?string
    {
        $fields = self::normalizeFields(Arr::only($fields, [
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postal_code',
        ]));

        $normalized = collect($fields)
            ->map(fn ($value) => Str::of((string) $value)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value())
            ->filter()
            ->implode('|');

        return $normalized !== '' ? hash('sha256', $normalized) : null;
    }
}
