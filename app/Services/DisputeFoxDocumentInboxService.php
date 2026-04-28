<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DisputeFoxDocumentInboxService
{
    /**
     * @return array{
     *     inbox_files:int,
     *     documents_checked:int,
     *     attached:int,
     *     missing:int,
     *     deleted_sources:int,
     *     pruned_tiny_previews:int
     * }
     */
    public function reconcile(?Client $client = null, bool $deleteSource = true, bool $pruneTinyPreviews = true): array
    {
        $files = $this->indexInboxFiles();
        $stats = [
            'inbox_files' => count($files),
            'documents_checked' => 0,
            'attached' => 0,
            'missing' => 0,
            'deleted_sources' => 0,
            'pruned_tiny_previews' => 0,
        ];

        if ($files === []) {
            return $stats;
        }

        $documents = ClientDocument::query()
            ->with('client')
            ->when($client, fn ($query) => $query->where('client_id', $client->getKey()))
            ->latest('updated_at')
            ->get()
            ->filter(fn (ClientDocument $document): bool => $this->isDisputeFoxDocument($document));

        $usedPaths = [];

        foreach ($documents as $document) {
            if (! $this->documentNeedsFile($document)) {
                if ($deleteSource) {
                    $match = $this->matchDocument($document, $files, $usedPaths);

                    if ($match) {
                        $stats['deleted_sources'] += $this->deleteMatchedSources($files, $match, $usedPaths);
                    }
                }

                continue;
            }

            $stats['documents_checked']++;
            $match = $this->matchDocument($document, $files, $usedPaths);

            if (! $match) {
                $stats['missing']++;

                continue;
            }

            $attached = $this->attachMatchedFile($document, $match);
            $stats['attached']++;
            $usedPaths[$match['path']] = true;

            if ($deleteSource) {
                $stats['deleted_sources'] += $this->deleteMatchedSources($files, $match, $usedPaths);
            }

            if ($attached->wasChanged()) {
                $attached->refresh();
            }
        }

        if ($deleteSource && $pruneTinyPreviews) {
            $stats['pruned_tiny_previews'] = $this->pruneTinyPreviewFiles($files, $usedPaths);
        }

        return $stats;
    }

    /**
     * @return array{attached:bool, deleted_sources:int}
     */
    public function attachToDocument(ClientDocument $document, bool $deleteSource = true): array
    {
        $files = $this->indexInboxFiles();

        if ($files === [] || ! $this->documentNeedsFile($document)) {
            return ['attached' => false, 'deleted_sources' => 0];
        }

        $usedPaths = [];
        $match = $this->matchDocument($document->loadMissing('client'), $files, $usedPaths);

        if (! $match) {
            return ['attached' => false, 'deleted_sources' => 0];
        }

        $this->attachMatchedFile($document, $match);

        return [
            'attached' => true,
            'deleted_sources' => $deleteSource ? $this->deleteMatchedSources($files, $match, $usedPaths) : 0,
        ];
    }

    /**
     * @return list<array{
     *     path:string,
     *     name:string,
     *     key:string,
     *     stem_key:string,
     *     extension:string,
     *     size:int,
     *     mtime:int,
     *     tiny_preview:bool
     * }>
     */
    protected function indexInboxFiles(): array
    {
        $files = [];

        foreach ($this->inboxPaths() as $path) {
            if (! File::isDirectory($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                $realPath = $file->getRealPath();

                if (! $realPath || ! $file->isFile()) {
                    continue;
                }

                $extension = Str::lower($file->getExtension());

                if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'tif', 'tiff'], true)) {
                    continue;
                }

                $name = $file->getFilename();
                $size = (int) $file->getSize();

                $files[] = [
                    'path' => $realPath,
                    'name' => $name,
                    'key' => $this->normalizeFileKey($name),
                    'stem_key' => $this->normalizeFileKey(pathinfo($name, PATHINFO_FILENAME)),
                    'extension' => $extension,
                    'size' => $size,
                    'mtime' => (int) $file->getMTime(),
                    'tiny_preview' => $size > 0 && $size < 8192 && in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true),
                ];
            }
        }

        usort($files, fn (array $left, array $right): int => [$right['mtime'], $right['size']] <=> [$left['mtime'], $left['size']]);

        return $files;
    }

    /**
     * @return list<string>
     */
    protected function inboxPaths(): array
    {
        $paths = config('creditsoft.disputefox_document_inbox_paths', []);

        if (is_string($paths)) {
            $paths = explode(',', $paths);
        }

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $path): string => rtrim((string) $path, DIRECTORY_SEPARATOR),
            is_array($paths) ? $paths : [],
        ))));
    }

    protected function isDisputeFoxDocument(ClientDocument $document): bool
    {
        $metadata = $document->metadata ?? [];
        $source = Str::lower((string) data_get($metadata, 'source'));

        return $source === 'browser_companion'
            || data_get($metadata, 'imports.disputefox.document') !== null
            || Str::contains(Str::lower((string) $document->notes), ['disputefox', 'pulse', 'browser companion']);
    }

    protected function documentNeedsFile(ClientDocument $document): bool
    {
        $path = (string) $document->file_path;

        return $path === '' || ! File::exists($path) || (int) ($document->file_size ?? 0) < 1;
    }

    /**
     * @param  list<array<string, mixed>>  $files
     * @param  array<string, bool>  $usedPaths
     * @return array<string, mixed>|null
     */
    protected function matchDocument(ClientDocument $document, array $files, array $usedPaths): ?array
    {
        $keys = $this->candidateKeysForDocument($document);

        foreach ($keys as $key) {
            $match = $this->bestCandidate($files, $usedPaths, fn (array $file): bool => $file['key'] === $key);

            if ($match) {
                return $match;
            }

            $match = $this->bestCandidate($files, $usedPaths, fn (array $file): bool => $file['stem_key'] === $key);

            if ($match) {
                return $match;
            }
        }

        $size = (int) ($document->file_size ?? 0);

        if ($size > 0) {
            return $this->bestCandidate(
                $files,
                $usedPaths,
                fn (array $file): bool => (int) $file['size'] === $size && $this->extensionFitsDocument($file, $document),
            );
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $files
     * @param  array<string, bool>  $usedPaths
     */
    protected function bestCandidate(array $files, array $usedPaths, callable $predicate): ?array
    {
        foreach ($files as $file) {
            if (isset($usedPaths[$file['path']]) || $file['tiny_preview'] || ! File::exists((string) $file['path'])) {
                continue;
            }

            if ($predicate($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function candidateKeysForDocument(ClientDocument $document): array
    {
        $metadata = $document->metadata ?? [];
        $import = data_get($metadata, 'imports.disputefox.document')
            ?: collect((array) data_get($metadata, 'imports', []))
                ->map(fn ($value) => is_array($value) ? data_get($value, 'document') : null)
                ->first(fn ($value) => is_array($value));
        $values = [
            $document->file_name,
            data_get($import, 'raw.file_name'),
            data_get($import, 'raw.client_document_url'),
            data_get($import, 'source_path'),
            data_get($import, 'source_document_uid'),
            data_get($import, 'download_url'),
            data_get($import, 'preview_url'),
        ];

        $keys = [];

        foreach ($values as $value) {
            foreach ($this->fileNamesFromValue((string) $value) as $name) {
                $key = $this->normalizeFileKey($name);

                if ($key !== '') {
                    $keys[] = $key;
                    $keys[] = $this->normalizeFileKey(pathinfo($name, PATHINFO_FILENAME));
                }
            }
        }

        return array_values(array_unique(array_filter($keys)));
    }

    /**
     * @return list<string>
     */
    protected function fileNamesFromValue(string $value): array
    {
        $value = trim($value);

        if ($value === '') {
            return [];
        }

        $names = [$value];
        $parts = parse_url($value);

        if (is_array($parts)) {
            if (isset($parts['query'])) {
                parse_str((string) $parts['query'], $query);

                foreach (['file', 'filename', 'name', 'document', 'path'] as $key) {
                    if (filled($query[$key] ?? null)) {
                        $names[] = (string) $query[$key];
                    }
                }
            }

            if (isset($parts['path'])) {
                $names[] = basename(rawurldecode((string) $parts['path']));
            }
        }

        return array_values(array_unique(array_filter(array_map(
            fn (string $name): string => basename(rawurldecode(Str::before($name, '?'))),
            $names,
        ))));
    }

    protected function attachMatchedFile(ClientDocument $document, array $match): ClientDocument
    {
        $client = $document->client;
        $directory = rtrim((string) config('creditsoft.document_path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$client->getKey();
        File::ensureDirectoryExists($directory);

        $fileName = $this->friendlyFileName($document, (string) $match['name']);
        $targetPath = $this->uniquePath($directory, $fileName);
        File::copy((string) $match['path'], $targetPath);

        $metadata = $document->metadata ?? [];
        data_set($metadata, 'imports.disputefox.document.has_file', true);
        data_set($metadata, 'imports.disputefox.document.local_inbox_attached_at', now()->toIso8601String());
        data_set($metadata, 'imports.disputefox.document.local_inbox_source_name', (string) $match['name']);

        $document->forceFill([
            'file_name' => basename($targetPath),
            'file_path' => $targetPath,
            'mime_type' => $this->mimeTypeForPath($targetPath, $document),
            'file_size' => (int) (File::size($targetPath) ?: 0),
            'portal_visible' => true,
            'metadata' => $metadata,
            'uploaded_at' => $document->uploaded_at ?: now(),
        ])->save();

        return $document;
    }

    protected function friendlyFileName(ClientDocument $document, string $sourceName): string
    {
        $client = $document->client;
        $extension = Str::lower(pathinfo($sourceName, PATHINFO_EXTENSION) ?: pathinfo((string) $document->file_name, PATHINFO_EXTENSION) ?: 'bin');
        $clientName = Str::slug($client->display_name ?: 'Client', '-');
        $title = Str::slug($document->title ?: pathinfo($sourceName, PATHINFO_FILENAME) ?: 'Document', '-');
        $date = ($document->uploaded_at instanceof Carbon ? $document->uploaded_at : now())
            ->timezone(config('app.timezone'))
            ->format('Y-m-d');

        return sprintf('client-%s-%s_%s_%s.%s', $client->getKey(), $clientName, $title, $date, $extension);
    }

    protected function uniquePath(string $directory, string $fileName): string
    {
        $path = $directory.DIRECTORY_SEPARATOR.$fileName;

        if (! File::exists($path)) {
            return $path;
        }

        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $stem = pathinfo($fileName, PATHINFO_FILENAME);

        for ($index = 2; $index < 1000; $index++) {
            $candidate = $directory.DIRECTORY_SEPARATOR.$stem.'-'.$index.($extension ? '.'.$extension : '');

            if (! File::exists($candidate)) {
                return $candidate;
            }
        }

        return $directory.DIRECTORY_SEPARATOR.$stem.'-'.Str::random(6).($extension ? '.'.$extension : '');
    }

    /**
     * @param  list<array<string, mixed>>  $files
     * @param  array<string, bool>  $usedPaths
     */
    protected function deleteMatchedSources(array $files, array $match, array &$usedPaths): int
    {
        $deleted = 0;

        foreach ($files as $file) {
            if ($file['key'] !== $match['key'] || (int) $file['size'] !== (int) $match['size']) {
                continue;
            }

            if (isset($usedPaths[$file['path']]) && $file['path'] !== $match['path']) {
                continue;
            }

            if (File::exists((string) $file['path'])) {
                File::delete((string) $file['path']);
                $deleted++;
            }

            $usedPaths[$file['path']] = true;
        }

        return $deleted;
    }

    /**
     * @param  list<array<string, mixed>>  $files
     * @param  array<string, bool>  $usedPaths
     */
    protected function pruneTinyPreviewFiles(array $files, array $usedPaths): int
    {
        $deleted = 0;

        foreach ($files as $file) {
            if (! $file['tiny_preview'] || isset($usedPaths[$file['path']])) {
                continue;
            }

            if (File::exists((string) $file['path'])) {
                File::delete((string) $file['path']);
                $deleted++;
            }
        }

        return $deleted;
    }

    protected function extensionFitsDocument(array $file, ClientDocument $document): bool
    {
        $mime = Str::lower((string) $document->mime_type);
        $extension = (string) $file['extension'];

        if (Str::contains($mime, 'pdf')) {
            return $extension === 'pdf';
        }

        if (Str::startsWith($mime, 'image/')) {
            return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'tif', 'tiff'], true);
        }

        return true;
    }

    protected function mimeTypeForPath(string $path, ClientDocument $document): ?string
    {
        $mime = File::mimeType($path);

        return is_string($mime) && $mime !== '' ? $mime : $document->mime_type;
    }

    protected function normalizeFileKey(string $value): string
    {
        $value = rawurldecode(trim($value));
        $value = preg_replace('/\s+\(\d+\)(?=(\.[^.]+)?$)/', '', $value) ?? $value;
        $value = Str::lower(Str::ascii($value));

        return trim(preg_replace('/[^a-z0-9.]+/', '-', $value) ?? '', '-');
    }
}
