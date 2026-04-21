<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class InstallerBranding
{
    /**
     * @return array{logo_name: string, logo_url: string, uploaded_at: string}
     */
    public function store(UploadedFile $file): array
    {
        $directory = $this->directory();
        $extension = strtolower($file->getClientOriginalExtension() ?: 'png');
        $filename = "logo.{$extension}";

        File::ensureDirectoryExists($directory);

        foreach (File::glob($directory.DIRECTORY_SEPARATOR.'logo.*') ?: [] as $existingFile) {
            File::delete($existingFile);
        }

        $file->move($directory, $filename);

        return [
            'logo_name' => $file->getClientOriginalName(),
            'logo_url' => rtrim($this->urlPrefix(), '/').'/'.$filename,
            'uploaded_at' => now()->toIso8601String(),
        ];
    }

    public function directory(): string
    {
        return (string) config('creditsoft.installer.logo_path', public_path('installer/branding'));
    }

    public function urlPrefix(): string
    {
        return (string) config('creditsoft.installer.logo_url_prefix', '/installer/branding');
    }
}
