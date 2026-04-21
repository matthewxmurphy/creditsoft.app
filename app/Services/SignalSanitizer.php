<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SignalSanitizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sanitize(array $payload, array|string|null $allowlist = null): array
    {
        $keys = is_array($allowlist)
            ? $allowlist
            : config(is_string($allowlist) ? $allowlist : 'creditsoft.signal_allowlist', []);

        $allowed = Arr::only($payload, $keys);

        return collect($allowed)
            ->map(fn ($value) => $this->sanitizeValue($value))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    public function isSafe(string $text): bool
    {
        foreach (config('creditsoft.signal_denylist_patterns', []) as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return false;
            }
        }

        return ! Str::contains(strtolower($text), [
            'ssn',
            'social security',
            'date of birth',
            'dob',
            'account number',
            'private note',
        ]);
    }

    protected function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $sanitized = collect($value)
                ->map(fn ($nested) => $this->sanitizeValue($nested))
                ->filter(fn ($nested) => $nested !== null && $nested !== '')
                ->all();

            return Arr::isAssoc($value)
                ? $sanitized
                : array_values($sanitized);
        }

        if (! is_string($value)) {
            return $value;
        }

        if (! $this->isSafe($value)) {
            return null;
        }

        return trim($value);
    }
}
