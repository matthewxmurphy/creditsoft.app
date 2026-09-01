<?php

namespace App\Services;

use Illuminate\Support\Collection;

class DisputeModeCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function modes(string $mailingMethod = 'certified'): array
    {
        $rate = $this->mailingRate($mailingMethod);

        return collect(config('dispute_modes.modes', []))
            ->map(function (array $mode, string $key) use ($rate): array {
                $steps = collect($mode['steps'] ?? [])->map(function (array $step) use ($rate): array {
                    $letterCount = max(0, (int) ($step['letter_count'] ?? 0));

                    return [
                        ...$step,
                        'letter_count' => $letterCount,
                        'estimated_cost_cents' => $letterCount * $rate,
                    ];
                })->values()->all();

                return [
                    'key' => $key,
                    'name' => (string) ($mode['name'] ?? $key),
                    'summary' => (string) ($mode['summary'] ?? ''),
                    'steps' => $steps,
                    'first_round_letter_count' => collect($steps)->where('round', 1)->sum('letter_count'),
                    'first_round_cost_cents' => collect($steps)->where('round', 1)->sum('estimated_cost_cents'),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(?string $key, string $mailingMethod = 'certified'): ?array
    {
        return $key ? ($this->modes($mailingMethod)[$key] ?? null) : null;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys((array) config('dispute_modes.modes', []));
    }

    public function version(): int
    {
        return max(1, (int) config('dispute_modes.version', 1));
    }

    public function mailingRate(string $method): int
    {
        return max(0, (int) config("dispute_modes.mailing_rates_cents.{$method}", 0));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function steps(string $key, string $mailingMethod): Collection
    {
        return collect($this->find($key, $mailingMethod)['steps'] ?? []);
    }
}
