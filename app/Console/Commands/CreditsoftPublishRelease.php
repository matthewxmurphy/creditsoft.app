<?php

namespace App\Console\Commands;

use App\Services\CreditsoftOfficeUpdatePackage;
use App\Services\CreditsoftUpdateFeed;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;

class CreditsoftPublishRelease extends Command
{
    protected $signature = 'creditsoft:release
        {--release= : Publish an explicit release version instead of auto-bumping}
        {--build= : Publish an explicit build identifier instead of using the release version}
        {--summary= : One-line update-feed summary}
        {--headline= : Update-feed headline}
        {--note=* : Release note to prepend to the update feed}
        {--package : Build the office update zip after writing the feed}
        {--required : Mark this feed as a required build change even when the version number is lower than a previously published bad stamp}
        {--dry-run : Show the next version without writing files}';

    protected $description = 'Publish the next date-based CreditSoft release version and optionally build the office update package.';

    public function handle(CreditsoftOfficeUpdatePackage $packageBuilder): int
    {
        $feed = $this->readFeed();
        $now = Carbon::now($this->releaseTimezone());
        $previousVersion = trim((string) ($feed['latest_version'] ?? ''));
        $version = trim((string) ($this->option('release') ?: $this->nextVersion($previousVersion, $now)));
        $build = trim((string) ($this->option('build') ?: $version));
        $dryRun = (bool) $this->option('dry-run');

        if (! $this->isDateVersion($version)) {
            $this->error('CreditSoft release versions must use YYYY.M.D.N, for example 2026.4.21.38.');

            return self::FAILURE;
        }

        $notes = $this->releaseNotes($version);
        $headline = trim((string) ($this->option('headline') ?: "CreditSoft {$version} is ready"));
        $summary = trim((string) ($this->option('summary') ?: ($feed['summary'] ?? 'CreditSoft office update is ready.')));
        $downloadUrl = sprintf('https://update.creditsoft.app/downloads/creditsoft-office-v%s.zip', $version);

        $this->table(
            ['Field', 'Value'],
            [
                ['Previous version', $previousVersion ?: 'none'],
                ['Next version', $version],
                ['Build', $build],
                ['Package', $this->option('package') ? 'yes' : 'no'],
                ['Dry run', $dryRun ? 'yes' : 'no'],
            ],
        );

        if ($dryRun) {
            foreach ($notes as $note) {
                $this->line('- '.$note);
            }

            return self::SUCCESS;
        }

        if (! $this->workspaceAllowsRelease()) {
            return self::FAILURE;
        }

        $feed['latest_version'] = $version;
        $feed['latest_build'] = $build;
        $feed['published_at'] = $now->toIso8601String();
        $feed['headline'] = $headline;
        $feed['summary'] = $summary;
        $feed['notes'] = array_values(array_unique([
            ...$notes,
            ...array_values(is_array($feed['notes'] ?? null) ? $feed['notes'] : []),
        ]));
        $feed['download_url'] = $downloadUrl;
        $feed['update_required'] = (bool) $this->option('required');
        $this->syncFeedPackageVersions($feed, $version);

        $this->writeFeedMirrors($feed);
        $this->writeLocalVersionMirrors($feed, $version, $build);
        app(CreditsoftUpdateFeed::class)->forget();

        if ($this->option('package')) {
            $package = $packageBuilder->build($version, $build);
            $this->info('Package built: '.$package['archive_path']);
        }

        $this->info("CreditSoft release {$version} published.");

        return self::SUCCESS;
    }

    protected function nextVersion(string $previousVersion, Carbon $now): string
    {
        $year = (int) $now->format('Y');
        $month = (int) $now->format('n');
        $day = (int) $now->format('j');

        if (preg_match('/^(\d{4})\.(\d{1,2})\.(\d{1,2})\.(\d+)$/', $previousVersion, $matches) === 1) {
            $sameDay = (int) $matches[1] === $year
                && (int) $matches[2] === $month
                && (int) $matches[3] === $day;
            $release = $sameDay ? ((int) $matches[4]) + 1 : 1;

            return "{$year}.{$month}.{$day}.{$release}";
        }

        if (preg_match('/^\d+\.\d+\.(\d+)$/', $previousVersion, $matches) === 1) {
            $release = ((int) $matches[1]) + 1;

            return "{$year}.{$month}.{$day}.{$release}";
        }

        return "{$year}.{$month}.{$day}.1";
    }

    protected function workspaceAllowsRelease(): bool
    {
        if (filter_var(env('CREDITSOFT_ALLOW_RELEASE_WITHOUT_GIT', false), FILTER_VALIDATE_BOOL)) {
            $this->warn('CREDITSOFT_ALLOW_RELEASE_WITHOUT_GIT is enabled; skipping Git workspace guard.');

            return true;
        }

        $gitPath = base_path('.git');

        if (! File::exists($gitPath)) {
            $this->error('Refusing to publish a release because .git is missing from the CreditSoft workspace.');
            $this->line('Run: bash scripts/creditsoft-workspace-doctor.sh');
            $this->line('This prevents update packages from being built out of a detached iCloud/Desktop copy.');

            return false;
        }

        $canonical = realpath(base_path()) ?: base_path();

        if (str_contains($canonical, '/Desktop/CreditSoft.icloud-drift-archive-')
            || str_contains($canonical, '/Desktop/CreditSoft-legacy-0x-quarantine-')) {
            $this->error('Refusing to publish a release from a CreditSoft archive/quarantine folder.');
            $this->line("Current workspace: {$canonical}");

            return false;
        }

        return true;
    }

    protected function releaseTimezone(): string
    {
        $timezone = trim((string) config('creditsoft.updates.release_timezone', ''));

        return $timezone !== '' ? $timezone : config('app.timezone', 'UTC');
    }

    protected function isDateVersion(string $version): bool
    {
        return preg_match('/^\d{4}\.\d{1,2}\.\d{1,2}\.\d+$/', $version) === 1;
    }

    /**
     * @return array<int, string>
     */
    protected function releaseNotes(string $version): array
    {
        $notes = array_values(array_filter(array_map(
            fn (string $note): string => trim($note),
            (array) $this->option('note'),
        )));

        if ($notes === []) {
            return ["CreditSoft {$version} updates the office build."];
        }

        return array_map(fn (string $note): string => $this->formatNote($version, $note), $notes);
    }

    protected function formatNote(string $version, string $note): string
    {
        $note = str_replace('{version}', $version, trim($note));
        $note = rtrim($note, ". \t\n\r\0\x0B").'.';

        if (str_starts_with($note, 'CreditSoft ') || str_starts_with($note, 'Browser companion ')) {
            return $note;
        }

        return "CreditSoft {$version} ".$note;
    }

    /**
     * @return array<string, mixed>
     */
    protected function readFeed(): array
    {
        $path = $this->feedPath();

        if (! File::exists($path)) {
            throw new RuntimeException("Update feed not found: {$path}");
        }

        $decoded = json_decode((string) File::get($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Update feed is not valid JSON: {$path}");
        }

        return $decoded;
    }

    protected function feedPath(): string
    {
        return base_path('update.creditsoft.app/data/update-feed.json');
    }

    /**
     * @param  array<string, mixed>  $feed
     */
    protected function writeFeedMirrors(array $feed): void
    {
        $payload = json_encode($feed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

        foreach ($this->feedMirrorPaths() as $path) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $feed
     */
    protected function syncFeedPackageVersions(array &$feed, string $version): void
    {
        $browserCompanionUrl = 'https://update.creditsoft.app/downloads/creditsoft-browser-companion.zip';
        $intranetClientUrl = sprintf('https://update.creditsoft.app/downloads/creditsoft-intranet-client-installer-v%s.zip', $version);
        $browserCompanionArchive = base_path('update.creditsoft.app/downloads/creditsoft-browser-companion.zip');
        $intranetClientArchive = base_path(sprintf('update.creditsoft.app/downloads/creditsoft-intranet-client-installer-v%s.zip', $version));

        $feed['browser_companion'] = is_array($feed['browser_companion'] ?? null) ? $feed['browser_companion'] : [];

        if (File::exists($browserCompanionArchive)) {
            $feed['browser_companion']['latest_version'] = $version;
            $feed['browser_companion']['download_url'] = $browserCompanionUrl;
            $feed['browser_companion_url'] = $browserCompanionUrl;
        } elseif (filled((string) ($feed['browser_companion']['download_url'] ?? ''))) {
            $feed['browser_companion_url'] = (string) $feed['browser_companion']['download_url'];
        }

        $feed['browser_companion']['renewal_url'] = 'https://www.creditsoft.app/renewal/';

        $feed['intranet_client'] = is_array($feed['intranet_client'] ?? null) ? $feed['intranet_client'] : [];

        if (File::exists($intranetClientArchive)) {
            $feed['intranet_client']['latest_version'] = $version;
            $feed['intranet_client']['download_url'] = $intranetClientUrl;
        }

        $feed['renewal_url'] = 'https://www.creditsoft.app/renewal/';
        $feed['minimum_version'] = $version;
    }

    /**
     * @param  array<string, mixed>  $feed
     */
    protected function writeLocalVersionMirrors(array $feed, string $version, string $build): void
    {
        $this->writeEnvVersion(base_path('.env'), $version, $build);
        $this->writeEnvVersion(base_path('.env.docker'), $version, $build);
        $this->writeReleaseMarker($version, $build);
        $this->writePackageReadme($version, $build);

        $this->updateJsonFile(base_path('manifest.json'), function (array $manifest) use ($feed, $version, $build): array {
            $manifest['version'] = $version;
            $manifest['build'] = $build;
            $manifest['published_at'] = (string) ($feed['published_at'] ?? $manifest['published_at'] ?? '');
            $manifest['notes'] = array_values(is_array($feed['notes'] ?? null) ? $feed['notes'] : ($manifest['notes'] ?? []));
            $manifest['download_url'] = (string) ($feed['download_url'] ?? sprintf('https://update.creditsoft.app/downloads/creditsoft-office-v%s.zip', $version));
            $manifest['browser_companion_url'] = (string) ($feed['browser_companion']['download_url'] ?? 'https://update.creditsoft.app/downloads/creditsoft-browser-companion.zip');

            $manifest['intranet_client'] = is_array($manifest['intranet_client'] ?? null) ? $manifest['intranet_client'] : [];
            $manifest['intranet_client']['latest_version'] = $version;
            $manifest['intranet_client']['download_url'] = (string) ($feed['intranet_client']['download_url'] ?? sprintf('https://update.creditsoft.app/downloads/creditsoft-intranet-client-installer-v%s.zip', $version));

            return $manifest;
        });

        foreach ([
            base_path('browser-extension/creditsoft-dom-capture/manifest.json'),
            base_path('browser-extension/creditsoft-me-operator/manifest.json'),
            base_path('intranet-client/package.json'),
        ] as $path) {
            $this->updateJsonFile($path, function (array $payload) use ($version): array {
                $payload['version'] = $version;

                return $payload;
            });
        }
    }

    protected function writeEnvVersion(string $path, string $version, string $build): void
    {
        if (! File::exists($path)) {
            return;
        }

        $contents = (string) File::get($path);
        $contents = preg_replace('/^CREDITSOFT_APP_VERSION=.*$/m', "CREDITSOFT_APP_VERSION={$version}", $contents) ?? $contents;
        $contents = preg_replace('/^CREDITSOFT_APP_BUILD=.*$/m', "CREDITSOFT_APP_BUILD={$build}", $contents) ?? $contents;

        File::put($path, $contents);
    }

    protected function writeReleaseMarker(string $version, string $build): void
    {
        $path = base_path('CREDITSOFT_RELEASE.toon');

        if (! File::exists($path)) {
            return;
        }

        $contents = (string) File::get($path);
        $contents = preg_replace('/^canonical_version:\s*.*$/m', "canonical_version: {$version}", $contents) ?? $contents;
        $contents = preg_replace('/^canonical_build:\s*.*$/m', "canonical_build: {$build}", $contents) ?? $contents;

        File::put($path, $contents);
    }

    protected function writePackageReadme(string $version, string $build): void
    {
        $path = base_path('README.txt');

        if (! File::exists($path)) {
            return;
        }

        $contents = (string) File::get($path);
        $contents = preg_replace('/^Version:\s*.*$/m', "Version: {$version}", $contents) ?? $contents;
        $contents = preg_replace('/^Build:\s*.*$/m', "Build: {$build}", $contents) ?? $contents;
        $contents = preg_replace(
            '/^Version\s+20\d{2}\.\d{1,2}\.\d{1,2}\.\d+\s+is the canonical date-version package/m',
            "Version {$version} is the canonical date-version package",
            $contents,
        ) ?? $contents;

        File::put($path, $contents);
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $mutator
     */
    protected function updateJsonFile(string $path, callable $mutator): void
    {
        if (! File::exists($path)) {
            return;
        }

        $decoded = json_decode((string) File::get($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("JSON file is not valid: {$path}");
        }

        File::put($path, json_encode($mutator($decoded), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    /**
     * @return array<int, string>
     */
    protected function feedMirrorPaths(): array
    {
        return [
            base_path('update.creditsoft.app/data/update-feed.json'),
            base_path('update.creditsoft.app/api/update-feed'),
            base_path('update.creditsoft.app/api/update-feed.php'),
            base_path('site-astro/public/api/update-feed'),
            base_path('site-astro/public/api/update-feed.php'),
            base_path('updates-astro/public/data/update-feed.json'),
            base_path('updates-astro/public/api/update-feed'),
            base_path('updates-astro/public/api/update-feed.php'),
        ];
    }
}
