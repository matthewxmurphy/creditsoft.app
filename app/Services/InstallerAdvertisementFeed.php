<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class InstallerAdvertisementFeed
{
    /**
     * @return array<string, mixed>
     */
    public function feed(): array
    {
        $localFeed = $this->normalizeFeed($this->localFeed(), 'local');
        $remoteUrl = config('creditsoft.installer.ad_feed_url');

        if (! filled($remoteUrl)) {
            return $localFeed;
        }

        try {
            $response = Http::timeout(3)->acceptJson()->get($remoteUrl);

            if ($response->successful() && is_array($response->json())) {
                return $this->mergeFeeds(
                    $localFeed,
                    $this->normalizeFeed($this->extractFeed($response->json()), 'remote', (string) $remoteUrl),
                    'remote',
                    (string) $remoteUrl,
                );
            }
        } catch (Throwable) {
            return $this->normalizeFeed($localFeed, 'local-fallback', (string) $remoteUrl);
        }

        return $this->normalizeFeed($localFeed, 'local-fallback', (string) $remoteUrl);
    }

    /**
     * @return array<string, mixed>
     */
    private function localFeed(): array
    {
        $path = (string) config('creditsoft.installer.ad_feed_path', base_path('creditsoft/install_ads.json'));

        if (! File::exists($path)) {
            return [
                'title' => 'Reference feed unavailable',
                'subtitle' => 'Add a local or remote JSON feed to populate this panel.',
                'updated_at' => null,
                'items' => [],
            ];
        }

        $decoded = json_decode(File::get($path), true);

        if (! is_array($decoded)) {
            return [
                'title' => 'Reference feed unavailable',
                'subtitle' => 'The installer feed could not be parsed as JSON.',
                'updated_at' => null,
                'items' => [],
            ];
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $feed
     * @return array<string, mixed>
     */
    private function normalizeFeed(array $feed, string $source, ?string $feedUrl = null): array
    {
        $normalized = array_replace_recursive([
            'title' => 'Reference feed unavailable',
            'subtitle' => 'Add a local or remote JSON feed to populate this panel.',
            'updated_at' => null,
            'source' => $source,
            'feed_url' => $feedUrl,
            'items' => [],
        ], $feed, [
            'source' => $source,
            'feed_url' => $feedUrl,
        ]);

        $normalized['items'] = $this->normalizeItems((array) ($normalized['items'] ?? []));

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function extractFeed(array $payload): array
    {
        if (isset($payload['data']) && is_array($payload['data'])) {
            return [
                'title' => (string) ($payload['title'] ?? 'Installer partner rail'),
                'subtitle' => (string) ($payload['subtitle'] ?? 'JSON-backed partner tiles for the installer rail.'),
                'updated_at' => $payload['updated_at'] ?? null,
                'items' => $payload['data'],
            ];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $local
     * @param  array<string, mixed>  $remote
     * @return array<string, mixed>
     */
    private function mergeFeeds(array $local, array $remote, string $source, ?string $feedUrl = null): array
    {
        $items = collect((array) ($local['items'] ?? []))
            ->keyBy('id')
            ->merge(collect((array) ($remote['items'] ?? []))->keyBy('id'))
            ->values()
            ->all();

        return $this->normalizeFeed(array_replace_recursive($local, $remote, [
            'items' => $items,
        ]), $source, $feedUrl);
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(array $items): array
    {
        return collect($items)
            ->map(fn (mixed $item): array => $this->normalizeItem(is_array($item) ? $item : []))
            ->filter(fn (array $item): bool => (bool) ($item['active'] ?? true) && $item['id'] !== '')
            ->sortBy('sort_order')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalizeItem(array $item): array
    {
        return [
            'id' => (string) ($item['id'] ?? Str::slug((string) ($item['title'] ?? 'campaign'))),
            'eyebrow' => (string) ($item['eyebrow'] ?? $item['category'] ?? 'Partner'),
            'title' => (string) ($item['title'] ?? ''),
            'summary' => (string) ($item['summary'] ?? ''),
            'copy' => (string) ($item['copy'] ?? $item['description'] ?? ''),
            'image_url' => $item['image_url'] ?? $item['imageUrl'] ?? null,
            'logo_url' => $item['logo_url'] ?? $item['logoUrl'] ?? $item['image_url'] ?? $item['imageUrl'] ?? null,
            'cta_label' => $item['cta_label'] ?? $item['ctaLabel'] ?? 'Open',
            'cta_url' => $item['cta_url'] ?? $item['linkUrl'] ?? $item['url'] ?? null,
            'duration_ms' => max(5000, (int) ($item['duration_ms'] ?? $item['durationMs'] ?? 20000)),
            'disclaimer' => (string) ($item['disclaimer'] ?? ''),
            'active' => (bool) ($item['active'] ?? true),
            'sort_order' => (int) ($item['sort_order'] ?? $item['sortOrder'] ?? 100),
        ];
    }
}
