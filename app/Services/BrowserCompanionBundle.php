<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

class BrowserCompanionBundle
{
    public function sourcePath(): string
    {
        return base_path('browser-extension/creditsoft-dom-capture');
    }

    public function manifest(): array
    {
        $manifestPath = $this->sourcePath().DIRECTORY_SEPARATOR.'manifest.json';

        if (! is_file($manifestPath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($manifestPath), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function version(): string
    {
        $version = trim((string) data_get($this->manifest(), 'version'));

        return $version !== '' ? $version : (string) config('creditsoft.updates.current_version', '2026.4.27.1');
    }

    public function downloadName(): string
    {
        return sprintf('creditsoft-browser-companion-v%s.zip', $this->version());
    }

    public function publicDownloadUrl(): string
    {
        $configuredUrl = trim((string) config('creditsoft.updates.browser_companion_download_url', ''));

        return $configuredUrl !== ''
            ? $configuredUrl
            : sprintf('https://updates.creditsoft.app/downloads/%s', $this->downloadName());
    }

    public function build(): string
    {
        $sourcePath = $this->sourcePath();

        if (! is_dir($sourcePath)) {
            throw new RuntimeException('CreditSoft browser companion source folder was not found.');
        }

        $bundleDirectory = storage_path('app/private/browser-companion');

        File::ensureDirectoryExists($bundleDirectory);

        $archivePath = $bundleDirectory.DIRECTORY_SEPARATOR.$this->downloadName();

        if (! is_file($archivePath) || filemtime($archivePath) < $this->latestSourceTimestamp()) {
            $this->writeArchive($sourcePath, $archivePath);
        }

        $this->publishToUpdateLane($archivePath);

        return $archivePath;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'name' => (string) data_get($this->manifest(), 'name', 'CreditSoft Companion Capture'),
            'version' => $this->version(),
            'download_name' => $this->downloadName(),
            'description' => 'Bundled Chromium companion plus Safari webarchive quick-start.',
        ];
    }

    protected function latestSourceTimestamp(): int
    {
        $latest = 0;

        foreach ($this->sourceFiles() as $file) {
            $mtime = filemtime($file) ?: 0;
            $latest = max($latest, $mtime);
        }

        return $latest;
    }

    protected function writeArchive(string $sourcePath, string $archivePath): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to build the browser companion bundle.');
        }

        $zip = new ZipArchive();

        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('CreditSoft could not create the browser companion archive.');
        }

        foreach ($this->sourceFiles() as $file) {
            $relativePath = ltrim(str_replace($sourcePath, '', $file), DIRECTORY_SEPARATOR);
            $zip->addFile($file, $relativePath);
        }

        $zip->close();
    }

    protected function publishToUpdateLane(string $archivePath): void
    {
        $downloadsDirectory = base_path('update.creditsoft.app/downloads');

        if (! is_dir(dirname($downloadsDirectory))) {
            return;
        }

        File::ensureDirectoryExists($downloadsDirectory);

        $publishedPath = $downloadsDirectory.DIRECTORY_SEPARATOR.$this->downloadName();

        if (! is_file($publishedPath) || filemtime($publishedPath) < filemtime($archivePath)) {
            File::copy($archivePath, $publishedPath);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function sourceFiles(): array
    {
        $sourcePath = $this->sourcePath();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $files = [];

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
