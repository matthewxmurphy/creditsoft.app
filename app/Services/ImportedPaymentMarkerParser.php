<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class ImportedPaymentMarkerParser
{
    /**
     * @param  array<string, mixed>  $metadata
     * @return Collection<int, array{status:string,label:string,amount:?float,occurred_at:?CarbonInterface,raw:string,path:string}>
     */
    public function markersFromMetadata(array $metadata): Collection
    {
        $markers = [];

        foreach (Arr::dot($metadata) as $path => $value) {
            if ($this->shouldSkipPath((string) $path)) {
                continue;
            }

            if (! is_scalar($value)) {
                continue;
            }

            $raw = trim((string) $value);

            if ($raw === '' || ! preg_match('/(?:activepay|failedpay|failpay)/i', $raw)) {
                continue;
            }

            preg_match_all(
                '/(?:(?<date>\d{1,2}\/\d{1,2}\/\d{2,4})\s+)?(?:\$\s*)?(?<amount>[0-9]{1,6}(?:,[0-9]{3})*(?:\.[0-9]{2})?)?\s*(?<label>ActivePay|FailedPay|FailPay)/i',
                $raw,
                $matches,
                PREG_SET_ORDER,
            );

            foreach ($matches as $match) {
                $label = Str::of((string) ($match['label'] ?? ''))->lower()->value();

                if ($label === '') {
                    continue;
                }

                $date = trim((string) ($match['date'] ?? ''));
                $amount = trim((string) ($match['amount'] ?? ''));

                $markers[] = [
                    'status' => str_contains($label, 'fail') ? 'failed' : 'paid',
                    'label' => str_contains($label, 'fail') ? 'FailedPay' : 'ActivePay',
                    'amount' => $amount !== '' ? (float) str_replace(',', '', $amount) : null,
                    'occurred_at' => $this->parseImportedPaymentDate($date),
                    'raw' => $raw,
                    'path' => (string) $path,
                ];
            }
        }

        return collect($markers)
            ->unique(fn (array $marker): string => implode('|', [
                $marker['path'],
                $marker['label'],
                $marker['amount'] ?? '',
                optional($marker['occurred_at'])->toDateString(),
            ]))
            ->values();
    }

    protected function shouldSkipPath(string $path): bool
    {
        return Str::startsWith($path, [
            'billing_signal',
            'client_health',
            'health_signal',
        ]);
    }

    protected function parseImportedPaymentDate(string $date): ?CarbonInterface
    {
        if ($date === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
