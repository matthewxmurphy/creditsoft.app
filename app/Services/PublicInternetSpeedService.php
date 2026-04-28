<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class PublicInternetSpeedService
{
    protected const PROVIDERS = [
        'fast' => [
            'label' => 'Fast.com',
            'url' => 'https://fast.com/',
        ],
        'speedtest' => [
            'label' => 'Speedtest.net',
            'url' => 'https://www.speedtest.net/',
        ],
    ];

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $stored = $this->read();
        $providers = [];

        foreach (self::PROVIDERS as $key => $provider) {
            $entry = (array) Arr::get($stored, "providers.$key", []);
            $download = $this->nullableFloat(Arr::get($entry, 'download_mbps'));
            $upload = $this->nullableFloat(Arr::get($entry, 'upload_mbps'));
            $latency = $this->nullableFloat(Arr::get($entry, 'latency_ms'));
            $measuredAt = $this->parseMeasuredAt((string) Arr::get($entry, 'measured_at', ''));

            $providers[$key] = [
                'key' => $key,
                'label' => $provider['label'],
                'url' => $provider['url'],
                'download_mbps' => $download,
                'upload_mbps' => $upload,
                'latency_ms' => $latency,
                'download_label' => $this->speedLabel($download),
                'upload_label' => $this->speedLabel($upload),
                'latency_label' => $this->latencyLabel($latency),
                'measured_at' => $measuredAt?->toIso8601String(),
                'measured_at_label' => $measuredAt ? $measuredAt->diffForHumans() : 'No result saved',
            ];
        }

        return [
            'providers' => $providers,
            'average' => $this->average($providers),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function save(string $provider, array $input): array
    {
        if (! array_key_exists($provider, self::PROVIDERS)) {
            return $this->summary();
        }

        $stored = $this->read();
        $stored['providers'] = (array) Arr::get($stored, 'providers', []);
        $stored['providers'][$provider] = [
            'download_mbps' => $this->nullableFloat(Arr::get($input, 'download_mbps')),
            'upload_mbps' => $this->nullableFloat(Arr::get($input, 'upload_mbps')),
            'latency_ms' => $this->nullableFloat(Arr::get($input, 'latency_ms')),
            'measured_at' => now()->toIso8601String(),
            'source' => (string) Arr::get($input, 'source', 'manual'),
        ];

        File::ensureDirectoryExists(dirname($this->storagePath()));
        File::put($this->storagePath(), json_encode($stored, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        return $this->summary();
    }

    /**
     * @return array<string, mixed>
     */
    protected function read(): array
    {
        $path = $this->storagePath();

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function storagePath(): string
    {
        return (string) config('creditsoft.diagnostics.public_speed_path', storage_path('app/private/public-internet-speed.json'));
    }

    protected function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return round(max((float) $value, 0), 2);
    }

    protected function parseMeasuredAt(string $value): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $providers
     * @return array<string, mixed>
     */
    protected function average(array $providers): array
    {
        $download = $this->averageValue($providers, 'download_mbps');
        $upload = $this->averageValue($providers, 'upload_mbps');
        $latency = $this->averageValue($providers, 'latency_ms');

        return [
            'provider_count' => collect($providers)->filter(fn (array $provider): bool => $provider['download_mbps'] !== null)->count(),
            'download_mbps' => $download,
            'upload_mbps' => $upload,
            'latency_ms' => $latency,
            'download_label' => $this->speedLabel($download),
            'upload_label' => $this->speedLabel($upload),
            'latency_label' => $this->latencyLabel($latency),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $providers
     */
    protected function averageValue(array $providers, string $key): ?float
    {
        $values = collect($providers)
            ->pluck($key)
            ->filter(fn ($value): bool => is_numeric($value))
            ->values();

        if ($values->isEmpty()) {
            return null;
        }

        return round((float) $values->average(), 1);
    }

    protected function speedLabel(?float $value): string
    {
        return $value === null ? 'Not saved' : number_format($value, 1).' Mbps';
    }

    protected function latencyLabel(?float $value): string
    {
        return $value === null ? 'Not saved' : number_format($value, 0).' ms';
    }
}
