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
     * @var array<int, string>
     */
    protected const CANONICAL_REMOTE_FEEDS = [
        'https://updates.creditsoft.app/api/update-feed.php',
        'https://updates.creditsoft.app/api/update-feed',
    ];

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
        $feedRequiresUpdate = (bool) ($feed['update_required'] ?? false);

        if ($currentBuild === '') {
            $currentBuild = $currentVersion;
        }

        if ($latestBuild === '') {
            $latestBuild = $latestVersion;
        }

        $currentAheadOfFeed = $latestVersion !== '' && $this->isVersionLessThan($latestVersion, $currentVersion);
        $versionBehind = $this->isVersionLessThan($currentVersion, $latestVersion);
        $belowMinimum = $this->isVersionLessThan($currentVersion, $minimumVersion);
        $requiresUpdate = $feedRequiresUpdate && ! $currentAheadOfFeed;
        $buildChanged = ! $currentAheadOfFeed && $currentBuild !== '' && $latestBuild !== '' && $currentBuild !== $latestBuild;
        $updateAvailable = $versionBehind || $belowMinimum || ($requiresUpdate && ($buildChanged || $latestVersion !== '' && $latestVersion !== $currentVersion));
        $displayLatestVersion = $currentAheadOfFeed ? $currentVersion : $latestVersion;
        $displayLatestBuild = $currentAheadOfFeed ? $currentBuild : $latestBuild;

        $payload = array_replace_recursive([
            'source' => 'none',
            'feed_url' => null,
            'checked_at' => now()->toIso8601String(),
            'current_version' => $currentVersion,
            'current_build' => $currentBuild !== '' ? $currentBuild : null,
            'channel' => $channel,
            'latest_version' => $displayLatestVersion !== '' ? $displayLatestVersion : null,
            'latest_build' => $displayLatestBuild !== '' ? $displayLatestBuild : null,
            'published_latest_version' => $latestVersion !== '' ? $latestVersion : null,
            'published_latest_build' => $latestBuild !== '' ? $latestBuild : null,
            'local_build_ahead' => $currentAheadOfFeed,
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
            'latest_version' => $displayLatestVersion !== '' ? $displayLatestVersion : null,
            'latest_build' => $displayLatestBuild !== '' ? $displayLatestBuild : null,
            'published_latest_version' => $latestVersion !== '' ? $latestVersion : null,
            'published_latest_build' => $latestBuild !== '' ? $latestBuild : null,
            'local_build_ahead' => $currentAheadOfFeed,
            'channel' => $channel,
            'update_available' => $updateAvailable,
            'update_required' => $requiresUpdate || $belowMinimum,
            'checked_at' => now()->toIso8601String(),
        ]);

        if ($currentAheadOfFeed && ! $updateAvailable) {
            $payload['download_url'] = null;
            $payload['package_path'] = null;
        }

        if (
            ! $updateAvailable
            && filled($latestVersion)
            && ! in_array((string) ($payload['source'] ?? 'none'), ['none', 'unavailable'], true)
        ) {
            $payload['headline'] = 'CreditSoft is up to date';
            $payload['summary'] = $currentAheadOfFeed
                ? sprintf(
                    'This office is on %s, which is newer than the published update feed (%s). No package should be applied right now.',
                    $currentVersion,
                    $latestVersion,
                )
                : sprintf(
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
            $feeds = [];

            foreach ($urls as $url) {
                try {
                    $response = Http::timeout(3)
                        ->acceptJson()
                        ->withOptions($this->ipv4CurlOptions())
                        ->get($url);

                    if ($response->successful() && is_array($response->json())) {
                        $feeds[] = $this->normalizeFeed($response->json(), 'remote', $url);
                    }
                } catch (Throwable) {
                    continue;
                }
            }

            if ($localFeed !== null) {
                $feeds[] = $localFeed;
            }

            if ($feeds !== []) {
                return $this->newestFeed($feeds);
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
        return collect([
            trim((string) config('creditsoft.updates.feed_url', '')),
            trim((string) config('creditsoft.updates.fallback_feed_url', '')),
            ...self::CANONICAL_REMOTE_FEEDS,
        ])
            ->filter(fn (string $url): bool => $url !== '' && ! str_starts_with($url, 'file://'))
            ->unique()
            ->values()
            ->all();
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
        foreach ($this->localFeedPaths() as $path) {
            if (! File::exists($path)) {
                continue;
            }

            $decoded = json_decode((string) File::get($path), true);

            if (! is_array($decoded)) {
                continue;
            }

            if (! filled((string) ($decoded['latest_version'] ?? '')) && filled((string) ($decoded['version'] ?? ''))) {
                $decoded['latest_version'] = trim((string) $decoded['version']);
                $decoded['latest_build'] = trim((string) ($decoded['build'] ?? $decoded['version']));
            }

            return $this->normalizeFeed($decoded, 'local', 'file://'.$path);
        }

        return null;
    }

    protected function localFeedPath(): string
    {
        return implode('|', $this->localFeedPaths());
    }

    /**
     * @return array<int, string>
     */
    protected function localFeedPaths(): array
    {
        return [
            base_path('update.creditsoft.app/data/update-feed.json'),
            base_path('manifest.json'),
        ];
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
        $current = $this->comparableVersion($current);
        $target = $this->comparableVersion($target);

        if ($current === null || $target === null) {
            return false;
        }

        return version_compare($current, $target, '<');
    }

    /**
     * @param  array<int, array<string, mixed>>  $feeds
     * @return array<string, mixed>
     */
    protected function newestFeed(array $feeds): array
    {
        return collect($feeds)
            ->sort(function (array $left, array $right): int {
                $leftVersion = (string) ($left['latest_version'] ?? '');
                $rightVersion = (string) ($right['latest_version'] ?? '');

                if ($this->isVersionLessThan($leftVersion, $rightVersion)) {
                    return 1;
                }

                if ($this->isVersionLessThan($rightVersion, $leftVersion)) {
                    return -1;
                }

                return strcmp(
                    (string) ($right['published_at'] ?? ''),
                    (string) ($left['published_at'] ?? ''),
                );
            })
            ->values()
            ->first() ?? $feeds[0];
    }

    protected function comparableVersion(string $value): ?string
    {
        $version = $this->normalizeVersion($value);

        if ($version === null) {
            return null;
        }

        if (preg_match('/^20\d{2}\.\d{1,2}\.\d{1,2}\.\d+$/', $version) === 1) {
            return '2.'.$version;
        }

        return '1.'.$version;
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
