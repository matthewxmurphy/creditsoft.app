<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

class MigrationOperatorBundle
{
    public function baseSourcePath(): string
    {
        return base_path('browser-extension/creditsoft-dom-capture');
    }

    public function overlaySourcePath(): string
    {
        return base_path('browser-extension/creditsoft-me-operator');
    }

    public function manifest(): array
    {
        $manifestPath = $this->overlaySourcePath().DIRECTORY_SEPARATOR.'manifest.json';

        if (! is_file($manifestPath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($manifestPath), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function version(): string
    {
        $version = trim((string) data_get($this->manifest(), 'version'));

        return $version !== '' ? $version : '0.1.0';
    }

    public function downloadName(): string
    {
        return sprintf('creditsoft-ops-v%s.zip', $this->version());
    }

    public function build(): string
    {
        $baseSourcePath = $this->baseSourcePath();
        $overlaySourcePath = $this->overlaySourcePath();

        if (! is_dir($baseSourcePath) || ! is_dir($overlaySourcePath)) {
            throw new RuntimeException('CreditSoft OPS source folders were not found.');
        }

        $bundleDirectory = storage_path('app/private/migration-operator');
        File::ensureDirectoryExists($bundleDirectory);
        $archivePath = $bundleDirectory.DIRECTORY_SEPARATOR.$this->downloadName();

        if (! is_file($archivePath) || filemtime($archivePath) < $this->latestSourceTimestamp()) {
            $this->writeArchive($archivePath);
        }

        return $archivePath;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'name' => (string) data_get($this->manifest(), 'name', 'CreditSoft OPS'),
            'version' => $this->version(),
            'download_name' => $this->downloadName(),
            'description' => 'Internal-only migration capture tool that stages outside-platform pages into CreditSoft.',
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

    protected function writeArchive(string $archivePath): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to build the ME operator archive.');
        }

        $zip = new ZipArchive();

        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('CreditSoft could not create the OPS operator archive.');
        }

        foreach ($this->relativeFileMap() as $relativePath => $absolutePath) {
            $zip->addFile($absolutePath, $relativePath);
        }

        $zip->close();
    }

    /**
     * @return array<string, string>
     */
    protected function relativeFileMap(): array
    {
        $map = [];

        foreach ($this->baseFiles() as $file) {
            $map[$file['relative']] = $file['absolute'];
        }

        foreach ($this->overlayFiles() as $file) {
            $map[$file['relative']] = $file['absolute'];
        }

        return $map;
    }

    /**
     * @return array<int, array{relative:string, absolute:string}>
     */
    protected function baseFiles(): array
    {
        return $this->filesFrom($this->baseSourcePath());
    }

    /**
     * @return array<int, array{relative:string, absolute:string}>
     */
    protected function overlayFiles(): array
    {
        return $this->filesFrom($this->overlaySourcePath());
    }

    /**
     * @return array<int, string>
     */
    protected function sourceFiles(): array
    {
        return collect([...$this->baseFiles(), ...$this->overlayFiles()])
            ->pluck('absolute')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{relative:string, absolute:string}>
     */
    protected function filesFrom(string $path): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $files = [];

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $absolute = $file->getPathname();
            $relative = ltrim(str_replace($path, '', $absolute), DIRECTORY_SEPARATOR);
            $files[] = [
                'relative' => $relative,
                'absolute' => $absolute,
            ];
        }

        return $files;
    }
}
