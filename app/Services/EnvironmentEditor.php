<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class EnvironmentEditor
{
    /**
     * @var array<string, string>
     */
    protected array $configMap = [
        'CREDITSOFT_APP_VERSION' => 'creditsoft.updates.current_version',
        'CREDITSOFT_APP_BUILD' => 'creditsoft.updates.current_build',
        'CREDITSOFT_AI_DEFAULT_PROVIDER' => 'ai.default',
        'CREDITSOFT_REVIEW_LABEL_STYLE' => 'creditsoft.ui.review_label_style',
        'CREDITSOFT_TAILSCALE_REQUIRED' => 'creditsoft.tunnels.tailscale.required',
        'CREDITSOFT_TAILSCALE_HOSTNAME' => 'creditsoft.tunnels.tailscale.hostname',
        'CREDITSOFT_TAILSCALE_TAILNET' => 'creditsoft.tunnels.tailscale.tailnet',
        'CREDITSOFT_TAILSCALE_API_KEY' => 'creditsoft.tunnels.tailscale.api_key',
        'CREDITSOFT_TAILSCALE_API_KEY_EXPIRES_AT' => 'creditsoft.tunnels.tailscale.api_key_expires_at',
        'CREDITSOFT_TAILSCALE_API_KEY_WARNING_DAYS' => 'creditsoft.tunnels.tailscale.api_key_warning_days',
        'CREDITSOFT_NGROK_ENABLED' => 'creditsoft.tunnels.ngrok.enabled',
        'CREDITSOFT_NGROK_API_ONLY' => 'creditsoft.tunnels.ngrok.api_only',
        'CREDITSOFT_NGROK_AUTHTOKEN' => 'creditsoft.tunnels.ngrok.authtoken',
        'CREDITSOFT_NGROK_API_KEY' => 'creditsoft.tunnels.ngrok.api_key',
        'CREDITSOFT_NGROK_DOMAIN' => 'creditsoft.tunnels.ngrok.domain',
        'CREDITSOFT_API_ENABLED' => 'creditsoft.api.enabled',
        'CREDITSOFT_API_TOKEN' => 'creditsoft.api.token',
        'CREDITSOFT_API_PUBLIC_BASE_URL' => 'creditsoft.api.public_base_url',
        'WASABI_ACCESS_KEY_ID' => 'filesystems.disks.wasabi.key',
        'WASABI_SECRET_ACCESS_KEY' => 'filesystems.disks.wasabi.secret',
        'WASABI_DEFAULT_REGION' => 'filesystems.disks.wasabi.region',
        'WASABI_BUCKET' => 'filesystems.disks.wasabi.bucket',
        'WASABI_ENDPOINT' => 'filesystems.disks.wasabi.endpoint',
        'WASABI_USE_PATH_STYLE_ENDPOINT' => 'filesystems.disks.wasabi.use_path_style_endpoint',
        'OPENROUTER_API_KEY' => 'ai.providers.openrouter_creditsoft.key',
        'OLLAMA_CLOUD_API_KEY' => 'ai.providers.ollama_cloud.key',
        'OPENCODE_API_KEY' => 'creditsoft.ai.providers.opencode_zen.key',
    ];

    /**
     * @var array<int, string>
     */
    protected array $booleanKeys = [
        'CREDITSOFT_TAILSCALE_REQUIRED',
        'CREDITSOFT_NGROK_ENABLED',
        'CREDITSOFT_NGROK_API_ONLY',
        'CREDITSOFT_API_ENABLED',
        'WASABI_USE_PATH_STYLE_ENDPOINT',
    ];

    /**
     * @param  array<string, string|null>  $variables
     */
    public function setMany(array $variables): void
    {
        $path = app()->environmentFilePath();
        $contents = File::exists($path) ? File::get($path) : '';

        foreach ($variables as $key => $value) {
            $escaped = $this->escape($value ?? '');
            $pattern = "/^{$key}=.*$/m";

            if (preg_match($pattern, $contents) === 1) {
                $contents = preg_replace($pattern, "{$key}={$escaped}", $contents) ?? $contents;
            } else {
                $contents .= rtrim($contents) === '' ? '' : PHP_EOL;
                $contents .= "{$key}={$escaped}".PHP_EOL;
            }
        }

        File::put($path, $contents);
        $this->syncRuntimeVariables($variables);
        Artisan::call('config:clear');
    }

    public function syncRuntimeFromFile(): void
    {
        $this->syncRuntimeVariables($this->readManagedVariables());
    }

    /**
     * @return array<string, string>
     */
    public function readManagedVariables(): array
    {
        $path = app()->environmentFilePath();

        if (! File::exists($path)) {
            return [];
        }

        $values = [];

        foreach (preg_split('/\R/', File::get($path)) ?: [] as $line) {
            if (! is_string($line) || trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }

            if (! preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $matches)) {
                continue;
            }

            $key = $matches[1];

            if (! array_key_exists($key, $this->configMap)) {
                continue;
            }

            $values[$key] = $this->unescape($matches[2]);
        }

        return $values;
    }

    protected function escape(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/\s/', $value) === 1 || str_contains($value, '#')) {
            return '"'.addcslashes($value, "\"\\").'"';
        }

        return $value;
    }

    /**
     * @param  array<string, string|null>  $variables
     */
    protected function syncRuntimeVariables(array $variables): void
    {
        foreach ($variables as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $resolved = $value ?? '';

            putenv("{$key}={$resolved}");
            $_ENV[$key] = $resolved;
            $_SERVER[$key] = $resolved;

            if (array_key_exists($key, $this->configMap)) {
                config([
                    $this->configMap[$key] => in_array($key, $this->booleanKeys, true)
                        ? filter_var($resolved, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false
                        : $resolved,
                ]);
            }
        }
    }

    protected function unescape(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        if (
            strlen($trimmed) >= 2
            && str_starts_with($trimmed, '"')
            && str_ends_with($trimmed, '"')
        ) {
            return stripcslashes(substr($trimmed, 1, -1));
        }

        return $trimmed;
    }
}
