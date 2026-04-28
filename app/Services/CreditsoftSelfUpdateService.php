<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class CreditsoftSelfUpdateService
{
    public function __construct(
        protected CreditsoftUpdateFeed $updateFeed,
        protected EnvironmentEditor $environmentEditor,
        protected InstallerState $installerState,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function check(): array
    {
        return $this->updateFeed->refresh();
    }

    public function latestPackagePath(): string
    {
        return $this->resolvePackagePath($this->updateFeed->current());
    }

    /**
     * @return array<string, mixed>
     */
    public function applyLatest(): array
    {
        $status = $this->updateFeed->refresh();

        if (! ($status['update_available'] ?? false)) {
            throw new RuntimeException('This office is already on the newest available build.');
        }

        $packagePath = $this->resolvePackagePath($status);

        if (! is_file($packagePath)) {
            throw new RuntimeException('CreditSoft could not find the staged office update package.');
        }

        $stageDirectory = storage_path('app/private/updates/staging/'.now()->format('Ymd-His'));
        $extractDirectory = $stageDirectory.DIRECTORY_SEPARATOR.'extracted';

        File::ensureDirectoryExists($extractDirectory);

        $zip = new ZipArchive();

        if ($zip->open($packagePath) !== true) {
            throw new RuntimeException('CreditSoft could not open the office update archive.');
        }

        $zip->extractTo($extractDirectory);
        $zip->close();

        $packageRoot = $this->detectPackageRoot($extractDirectory);
        $manifest = $this->readManifest($packageRoot);
        $version = trim((string) ($manifest['version'] ?? $status['latest_version'] ?? ''));
        $build = trim((string) ($manifest['build'] ?? $status['latest_build'] ?? now()->format('Y.m.d.His')));

        if ($version === '') {
            throw new RuntimeException('The update package is missing a valid version.');
        }

        $currentVersion = trim((string) config('creditsoft.updates.current_version', ''));

        if ($this->isSameOrOlderDateRelease($version, $currentVersion)) {
            throw new RuntimeException(sprintf(
                'CreditSoft refused to apply package %s because this office is already on %s or newer.',
                $version,
                $currentVersion,
            ));
        }

        $this->syncPackage($packageRoot);
        $this->environmentEditor->setMany([
            'CREDITSOFT_APP_VERSION' => $version,
            'CREDITSOFT_APP_BUILD' => $build,
        ]);
        $this->installerState->merge([
            'updates' => [
                'current_version' => $version,
                'current_build' => $build,
                'last_applied_at' => now()->toIso8601String(),
            ],
        ]);
        $this->runPostApplyCommands();
        $this->updateFeed->refresh();

        return [
            'version' => $version,
            'build' => $build,
            'package_path' => $packagePath,
            'stage_directory' => $stageDirectory,
        ];
    }

    /**
     * @param  array<string, mixed>  $status
     */
    protected function resolvePackagePath(array $status): string
    {
        $packagePath = trim((string) ($status['package_path'] ?? ''));

        if ($packagePath !== '' && is_file($packagePath)) {
            return $packagePath;
        }

        $latestVersion = trim((string) ($status['latest_version'] ?? ''));

        if ($latestVersion !== '') {
            $localPath = base_path(sprintf('update.creditsoft.app/downloads/creditsoft-office-v%s.zip', $latestVersion));

            if (is_file($localPath)) {
                return $localPath;
            }
        }

        $downloadUrl = trim((string) ($status['download_url'] ?? ''));

        if ($downloadUrl === '') {
            throw new RuntimeException('CreditSoft does not have a download URL for the current update package.');
        }

        return $this->downloadRemotePackage($downloadUrl, $latestVersion !== '' ? $latestVersion : 'latest');
    }

    protected function downloadRemotePackage(string $downloadUrl, string $version): string
    {
        $downloadDirectory = storage_path('app/private/updates/downloads');
        $localPath = $downloadDirectory.DIRECTORY_SEPARATOR.sprintf('creditsoft-office-v%s.zip', $version);

        if (is_file($localPath)) {
            return $localPath;
        }

        File::ensureDirectoryExists($downloadDirectory);

        try {
            $response = Http::timeout(120)->withOptions(['sink' => $localPath])->get($downloadUrl);
        } catch (Throwable $exception) {
            throw new RuntimeException('CreditSoft could not download the update package: '.$exception->getMessage(), previous: $exception);
        }

        if (! $response->successful() || ! is_file($localPath)) {
            throw new RuntimeException('CreditSoft could not download the update package from the remote update lane.');
        }

        return $localPath;
    }

    protected function detectPackageRoot(string $extractDirectory): string
    {
        $entries = collect(File::directories($extractDirectory))
            ->filter(fn (string $path) => is_dir($path))
            ->values();

        if ($entries->count() === 1) {
            return $entries->first();
        }

        return $extractDirectory;
    }

    /**
     * @return array<string, mixed>
     */
    protected function readManifest(string $packageRoot): array
    {
        $manifestPath = $packageRoot.DIRECTORY_SEPARATOR.'manifest.json';

        if (! is_file($manifestPath)) {
            return [];
        }

        $decoded = json_decode((string) File::get($manifestPath), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function syncPackage(string $packageRoot): void
    {
        if ($this->executableExists('rsync')) {
            $command = [
                'rsync',
                '-a',
                '--exclude=.env',
                '--exclude=bootstrap/cache/',
                '--exclude=database/database.sqlite',
                '--exclude=database/testing.sqlite',
                '--exclude=public/hot',
                '--exclude=storage/',
                $packageRoot.DIRECTORY_SEPARATOR,
                base_path().DIRECTORY_SEPARATOR,
            ];

            $this->runProcess($command, base_path(), 'CreditSoft could not overlay the new office package.');

            return;
        }

        $this->copyPackageWithoutRsync($packageRoot);
    }

    protected function executableExists(string $binary): bool
    {
        $process = new Process(['sh', '-lc', 'command -v '.escapeshellarg($binary)]);
        $process->setTimeout(10);
        $process->run();

        return $process->isSuccessful() && trim($process->getOutput()) !== '';
    }

    protected function copyPackageWithoutRsync(string $packageRoot): void
    {
        $sourceRoot = rtrim($packageRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $targetRoot = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $relativePath = str_replace('\\', '/', substr($item->getPathname(), strlen($sourceRoot)));

            if ($this->shouldSkipUpdatePath($relativePath)) {
                continue;
            }

            $targetPath = $targetRoot.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

            if ($item->isDir()) {
                File::ensureDirectoryExists($targetPath);

                continue;
            }

            File::ensureDirectoryExists(dirname($targetPath));

            if (! @copy($item->getPathname(), $targetPath)) {
                throw new RuntimeException('CreditSoft could not overlay the new office package. Failed to copy '.$relativePath.'.');
            }
        }
    }

    protected function shouldSkipUpdatePath(string $relativePath): bool
    {
        $path = trim($relativePath, '/');

        return $path === '.env'
            || $path === 'database/database.sqlite'
            || $path === 'database/testing.sqlite'
            || $path === 'public/hot'
            || str_starts_with($path.'/', 'bootstrap/cache/')
            || str_starts_with($path.'/', 'storage/');
    }

    protected function isSameOrOlderDateRelease(string $candidateVersion, string $currentVersion): bool
    {
        if (! $this->isDateReleaseVersion($candidateVersion) || ! $this->isDateReleaseVersion($currentVersion)) {
            return false;
        }

        return $this->compareDateReleaseVersions($candidateVersion, $currentVersion) <= 0;
    }

    protected function isDateReleaseVersion(string $version): bool
    {
        return preg_match('/^20\d{2}\.\d{1,2}\.\d{1,2}\.\d+$/', $version) === 1;
    }

    protected function compareDateReleaseVersions(string $left, string $right): int
    {
        $leftParts = array_map('intval', explode('.', $left));
        $rightParts = array_map('intval', explode('.', $right));
        $length = max(count($leftParts), count($rightParts));

        for ($index = 0; $index < $length; $index++) {
            $leftPart = $leftParts[$index] ?? 0;
            $rightPart = $rightParts[$index] ?? 0;

            if ($leftPart === $rightPart) {
                continue;
            }

            return $leftPart <=> $rightPart;
        }

        return 0;
    }

    protected function runPostApplyCommands(): void
    {
        $phpBinary = PHP_BINARY ?: 'php';

        $this->runProcess([$phpBinary, 'artisan', 'optimize:clear'], base_path(), 'CreditSoft could not clear cached files after the update.');
        $this->runProcess([$phpBinary, 'artisan', 'migrate', '--force'], base_path(), 'CreditSoft could not finish the database migration step.');
        $this->runProcess([$phpBinary, 'artisan', 'optimize'], base_path(), 'CreditSoft could not rebuild the optimized caches after the update.');
    }

    /**
     * @param  array<int, string>  $command
     */
    protected function runProcess(array $command, string $workingDirectory, string $failureMessage): void
    {
        $process = new Process($command, $workingDirectory);
        $process->setTimeout(900);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($failureMessage.' '.$process->getErrorOutput().' '.$process->getOutput()));
        }
    }
}
