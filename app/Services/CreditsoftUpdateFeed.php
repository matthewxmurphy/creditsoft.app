<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class CreditsoftUpdateFeed
{
    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $currentVersion = trim((string) config('creditsoft.updates.current_version', '1.0.0'));
        $currentBuild = trim((string) config('creditsoft.updates.current_build', ''));
        $channel = trim((string) config('creditsoft.updates.channel', 'stable'));
        $feed = $this->fetchFeed();
        $latestVersion = trim((string) ($feed['latest_version'] ?? ''));
        $latestBuild = trim((string) ($feed['latest_build'] ?? ''));
        $minimumVersion = trim((string) ($feed['minimum_version'] ?? ''));
        $requiresUpdate = (bool) ($feed['update_required'] ?? false);

        $versionBehind = $this->isVersionLessThan($currentVersion, $latestVersion);
        $belowMinimum = $this->isVersionLessThan($currentVersion, $minimumVersion);
        $buildChanged = $currentBuild !== '' && $latestBuild !== '' && $currentBuild !== $latestBuild;
        $updateAvailable = $versionBehind || $belowMinimum || ($requiresUpdate && ($buildChanged || $latestVersion !== '' && $latestVersion !== $currentVersion));

        $payload = array_replace_recursive([
            'source' => 'none',
            'feed_url' => null,
            'checked_at' => now()->toIso8601String(),
            'current_version' => $currentVersion,
            'current_build' => $currentBuild !== '' ? $currentBuild : null,
            'channel' => $channel,
            'latest_version' => $latestVersion !== '' ? $latestVersion : null,
            'latest_build' => $latestBuild !== '' ? $latestBuild : null,
            'minimum_version' => $minimumVersion !== '' ? $minimumVersion : null,
            'headline' => 'CreditSoft update status',
            'summary' => null,
            'notes' => [],
            'download_url' => null,
            'package_path' => null,
            'renewal_url' => null,
            'support_url' => 'https://creditsoft.app/',
            'published_at' => null,
            'update_required' => $requiresUpdate,
            'update_available' => $updateAvailable,
        ], $feed, [
            'current_version' => $currentVersion,
            'current_build' => $currentBuild !== '' ? $currentBuild : null,
            'channel' => $channel,
            'update_available' => $updateAvailable,
            'update_required' => $requiresUpdate || $belowMinimum,
            'checked_at' => now()->toIso8601String(),
        ]);

        if (
            ! $updateAvailable
            && filled($latestVersion)
            && ! in_array((string) ($payload['source'] ?? 'none'), ['none', 'unavailable'], true)
        ) {
            $payload['headline'] = 'CreditSoft is up to date';
            $payload['summary'] = sprintf(
                'This office is already on version %s and does not need a newer package right now.',
                $currentVersion
            );
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function refresh(): array
    {
        $this->forget();

        return $this->current();
    }

    public function forget(): void
    {
        Cache::forget($this->cacheKey($this->remoteUrls()));
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchFeed(): array
    {
        $urls = $this->remoteUrls();
        $localFeed = $this->localFeed();

        if ($urls === [] && $localFeed === null) {
            return [
                'source' => 'none',
                'headline' => 'No update feed configured',
                'summary' => 'Add the update feed URL so this office can see when a new build is ready.',
                'notes' => [],
            ];
        }

        $cacheKey = $this->cacheKey($urls);
        $cacheMinutes = max(1, (int) config('creditsoft.updates.cache_minutes', 15));

        /** @var array<string, mixed> $feed */
        $feed = Cache::remember($cacheKey, now()->addMinutes($cacheMinutes), function () use ($urls, $localFeed): array {
            foreach ($urls as $url) {
                try {
                    $response = Http::timeout(3)
                        ->acceptJson()
                        ->withOptions($this->ipv4CurlOptions())
                        ->get($url);

                    if ($response->successful() && is_array($response->json())) {
                        return $this->normalizeFeed($response->json(), 'remote', $url);
                    }
                } catch (Throwable) {
                    continue;
                }
            }

            if ($localFeed !== null) {
                return $localFeed;
            }

            return [
                'source' => 'unavailable',
                'headline' => 'Update feed unavailable',
                'summary' => 'The office could not reach the remote update feed right now.',
                'notes' => [],
                'feed_url' => $urls[0] ?? null,
            ];
        });

        return $feed;
    }

    /**
     * @param  array<string, mixed>  $feed
     * @return array<string, mixed>
     */
    protected function normalizeFeed(array $feed, string $source, string $url): array
    {
        $latestVersion = trim((string) ($feed['latest_version'] ?? ''));
        $downloadUrl = trim((string) ($feed['download_url'] ?? ''));
        $inferredDownloadUrl = $downloadUrl;

        if ($latestVersion !== '' && ($downloadUrl === '' || ! str_ends_with(strtolower(parse_url($downloadUrl, PHP_URL_PATH) ?: ''), '.zip'))) {
            $inferredDownloadUrl = sprintf('https://updates.creditsoft.app/downloads/creditsoft-office-v%s.zip', $latestVersion);
        }

        $payload = array_replace_recursive([
            'product' => 'CreditSoft Intranet',
            'channel' => 'stable',
            'latest_version' => null,
            'latest_build' => null,
            'minimum_version' => null,
            'published_at' => null,
            'headline' => 'CreditSoft update available',
            'summary' => null,
            'notes' => [],
            'download_url' => $inferredDownloadUrl !== '' ? $inferredDownloadUrl : null,
            'package_path' => $this->resolvePackagePath($feed, $latestVersion, $inferredDownloadUrl),
            'renewal_url' => null,
            'support_url' => 'https://creditsoft.app/',
            'update_required' => false,
            'source' => $source,
            'feed_url' => $url,
        ], $feed, [
            'source' => $source,
            'feed_url' => $url,
        ]);

        if (filled((string) ($payload['package_path'] ?? ''))) {
            $payload['download_url'] = route('internal.updates.download', [], false);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function ipv4CurlOptions(): array
    {
        if (! defined('CURLOPT_IPRESOLVE') || ! defined('CURL_IPRESOLVE_V4')) {
            return [];
        }

        return [
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function remoteUrls(): array
    {
        return array_values(array_filter([
            trim((string) config('creditsoft.updates.feed_url', '')),
            trim((string) config('creditsoft.updates.fallback_feed_url', '')),
        ]));
    }

    protected function cacheKey(array $urls): string
    {
        $signature = implode('|', [
            ...$urls,
            $this->localFeedPath(),
        ]);

        return 'creditsoft:update-feed:'.sha1($signature);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function localFeed(): ?array
    {
        $path = $this->localFeedPath();

        if (! File::exists($path)) {
            return null;
        }

        $decoded = json_decode((string) File::get($path), true);

        if (! is_array($decoded)) {
            return null;
        }

        return $this->normalizeFeed($decoded, 'local', 'file://'.$path);
    }

    protected function localFeedPath(): string
    {
        return base_path('update.creditsoft.app/data/update-feed.json');
    }

    /**
     * @param  array<string, mixed>  $feed
     */
    protected function resolvePackagePath(array $feed, string $latestVersion, string $downloadUrl): ?string
    {
        $explicitPath = trim((string) ($feed['package_path'] ?? ''));

        if ($explicitPath !== '' && File::exists($explicitPath)) {
            return $explicitPath;
        }

        $downloadPath = trim((string) parse_url($downloadUrl, PHP_URL_PATH));

        if ($downloadPath !== '') {
            $localDownloadPath = base_path('update.creditsoft.app'.DIRECTORY_SEPARATOR.ltrim($downloadPath, '/'));

            if (File::exists($localDownloadPath)) {
                return $localDownloadPath;
            }
        }

        if ($latestVersion !== '') {
            $localVersionedPackage = base_path(sprintf('update.creditsoft.app/downloads/creditsoft-office-v%s.zip', $latestVersion));

            if (File::exists($localVersionedPackage)) {
                return $localVersionedPackage;
            }
        }

        return null;
    }

    protected function isVersionLessThan(string $current, string $target): bool
    {
        $current = $this->normalizeVersion($current);
        $target = $this->normalizeVersion($target);

        if ($current === null || $target === null) {
            return false;
        }

        return version_compare($current, $target, '<');
    }

    protected function normalizeVersion(string $value): ?string
    {
        $trimmed = trim(Str::of($value)->lower()->replace('version', '')->value());

        if ($trimmed === '') {
            return null;
        }

        $trimmed = ltrim($trimmed, 'v');

        return preg_match('/^[0-9]+(?:\.[0-9A-Za-z\-]+)*$/', $trimmed) === 1 ? $trimmed : null;
    }
}
