<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
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
                return $this->normalizeFeed(
                    array_replace_recursive($localFeed, $response->json()),
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
        return array_replace_recursive([
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
    }
}
