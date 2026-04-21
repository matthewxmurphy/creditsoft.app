<?php

namespace App\Console\Commands;

use App\Services\CreditsoftOfficeUpdatePackage;
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
        {--dry-run : Show the next version without writing files}';

    protected $description = 'Publish the next date-based CreditSoft release version and optionally build the office update package.';

    public function handle(CreditsoftOfficeUpdatePackage $packageBuilder): int
    {
        $feed = $this->readFeed();
        $now = Carbon::now();
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
        $downloadUrl = sprintf('https://updates.creditsoft.app/downloads/creditsoft-office-v%s.zip', $version);

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

        $this->writeFeedMirrors($feed);

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
