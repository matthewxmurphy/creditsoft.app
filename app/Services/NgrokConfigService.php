<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class NgrokConfigService
{
    /**
     * @return array{
     *     installed: bool,
     *     config_path: string,
     *     config_exists: bool,
     *     host_authtoken_saved: bool,
     *     host_api_key_saved: bool,
     *     validated: bool,
     *     masked_authtoken: ?string,
     *     masked_api_key: ?string,
     *     running: bool,
     *     active_public_url: ?string,
     *     message: string
     * }
     */
    public function current(): array
    {
        $path = $this->configPath();
        $installed = $this->binaryExists();
        $exists = File::exists($path);
        $hasAuthtoken = $exists && $this->fileContainsAuthtoken($path);
        $hasApiKey = $exists && $this->fileContainsApiKey($path);
        $validated = $exists && $installed ? $this->validateConfig($path) : false;
        $maskedAuthtoken = $this->maskedAuthtoken();
        $maskedApiKey = $this->maskedApiKey();
        $activePublicUrl = $this->activePublicUrl();

        return [
            'installed' => $installed,
            'config_path' => $path,
            'config_exists' => $exists,
            'host_authtoken_saved' => $hasAuthtoken,
            'host_api_key_saved' => $hasApiKey,
            'validated' => $validated,
            'masked_authtoken' => $maskedAuthtoken,
            'masked_api_key' => $maskedApiKey,
            'running' => $activePublicUrl !== null,
            'active_public_url' => $activePublicUrl,
            'message' => $this->statusMessage($installed, $exists, $hasAuthtoken, $hasApiKey, $validated, $activePublicUrl),
        ];
    }

    /**
     * @return array{
     *     installed: bool,
     *     config_path: string,
     *     config_exists: bool,
     *     host_authtoken_saved: bool,
     *     host_api_key_saved: bool,
     *     validated: bool,
     *     masked_authtoken: ?string,
     *     masked_api_key: ?string,
     *     running: bool,
     *     active_public_url: ?string,
     *     message: string,
     *     synced: bool
     * }
     */
    public function syncCredentials(?string $authtoken, ?string $apiKey): array
    {
        $resolvedToken = trim((string) $authtoken);
        $resolvedApiKey = trim((string) $apiKey);

        if ($resolvedToken === '' && $resolvedApiKey === '') {
            return [
                ...$this->current(),
                'synced' => false,
                'message' => 'No ngrok authtoken or API key is saved yet, so the host config file was left alone.',
            ];
        }

        $path = $this->configPath();
        $installed = $this->binaryExists();

        File::ensureDirectoryExists(dirname($path));

        if ($installed && $resolvedToken !== '') {
            Process::timeout(5)->run(['ngrok', 'config', 'add-authtoken', $resolvedToken, '--config', $path]);
        }

        if ($installed && $resolvedApiKey !== '') {
            Process::timeout(5)->run(['ngrok', 'config', 'add-api-key', $resolvedApiKey, '--config', $path]);
        }

        // Normalize the final file ourselves so the host config exactly matches
        // the saved CreditSoft credentials even if ngrok CLI only partially rewrites it.
        File::put($path, $this->configContents($resolvedToken, $resolvedApiKey));

        $status = $this->current();

        return [
            ...$status,
            'synced' => $status['config_exists']
                && ($resolvedToken === '' || $status['host_authtoken_saved'])
                && ($resolvedApiKey === '' || $status['host_api_key_saved']),
            'message' => $status['config_exists']
                && ($resolvedToken === '' || $status['host_authtoken_saved'])
                && ($resolvedApiKey === '' || $status['host_api_key_saved'])
                ? "Saved the ngrok credentials to {$path}."
                : 'The ngrok credentials were saved in CreditSoft, but the host ngrok config still needs attention.',
        ];
    }

    public function maskedAuthtoken(): ?string
    {
        return $this->mask(config('creditsoft.tunnels.ngrok.authtoken'));
    }

    public function maskedApiKey(): ?string
    {
        return $this->mask(config('creditsoft.tunnels.ngrok.api_key'));
    }

    protected function configPath(): string
    {
        $configuredPath = trim((string) config('creditsoft.tunnels.ngrok.config_path'));

        if ($configuredPath !== '') {
            return $configuredPath;
        }

        $home = rtrim((string) env('HOME', base_path()), DIRECTORY_SEPARATOR);

        return match (PHP_OS_FAMILY) {
            'Windows' => rtrim((string) env('APPDATA', $home), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'ngrok'.DIRECTORY_SEPARATOR.'ngrok.yml',
            'Darwin' => $home.'/Library/Application Support/ngrok/ngrok.yml',
            default => $home.'/.config/ngrok/ngrok.yml',
        };
    }

    protected function binaryExists(): bool
    {
        return Process::timeout(1)->run(['sh', '-lc', 'command -v ngrok >/dev/null 2>&1'])->successful();
    }

    protected function validateConfig(string $path): bool
    {
        return Process::timeout(3)->run(['ngrok', 'config', 'check', '--config', $path])->successful();
    }

    protected function fileContainsAuthtoken(string $path): bool
    {
        if (! File::exists($path)) {
            return false;
        }

        return preg_match('/^\s*authtoken:\s*.+$/m', (string) File::get($path)) === 1;
    }

    protected function fileContainsApiKey(string $path): bool
    {
        if (! File::exists($path)) {
            return false;
        }

        return preg_match('/^\s*api_key:\s*.+$/m', (string) File::get($path)) === 1;
    }

    protected function configContents(string $authtoken, string $apiKey): string
    {
        $lines = ['version: "3"', 'agent:'];

        if ($authtoken !== '') {
            $lines[] = "    authtoken: '".$this->escapeYamlValue($authtoken)."'";
        }

        if ($apiKey !== '') {
            $lines[] = "    api_key: '".$this->escapeYamlValue($apiKey)."'";
        }

        return implode(PHP_EOL, $lines);
    }

    protected function activePublicUrl(): ?string
    {
        $result = Process::timeout(2)->run(['sh', '-lc', 'curl -s --max-time 1 http://127.0.0.1:4040/api/tunnels']);

        if (! $result->successful()) {
            return null;
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($result->output(), true);

        if (! is_array($payload) || ! is_array($payload['tunnels'] ?? null)) {
            return null;
        }

        foreach ($payload['tunnels'] as $tunnel) {
            if (is_array($tunnel) && is_string($tunnel['public_url'] ?? null) && str_starts_with($tunnel['public_url'], 'https://')) {
                return rtrim($tunnel['public_url'], '/');
            }
        }

        foreach ($payload['tunnels'] as $tunnel) {
            if (is_array($tunnel) && is_string($tunnel['public_url'] ?? null) && str_starts_with($tunnel['public_url'], 'http://')) {
                return rtrim($tunnel['public_url'], '/');
            }
        }

        return null;
    }

    protected function statusMessage(bool $installed, bool $exists, bool $hasAuthtoken, bool $hasApiKey, bool $validated, ?string $activePublicUrl): string
    {
        if (! $exists) {
            return 'No ngrok host config file has been written yet.';
        }

        if (! $hasAuthtoken && ! $hasApiKey) {
            return 'The ngrok host config exists, but it does not contain an authtoken or API key yet.';
        }

        if (! $hasAuthtoken && $hasApiKey) {
            return 'The ngrok API key is saved. Add an authtoken too if you want this machine to run a public tunnel.';
        }

        if (! $installed) {
            return 'The ngrok credentials are saved to the host config file, but ngrok is not installed on this machine yet.';
        }

        if (! $validated) {
            return 'The ngrok credentials are saved, but the local ngrok config file check still failed.';
        }

        if ($activePublicUrl) {
            return "The ngrok authtoken is saved, the local config file validates, and ngrok is live at {$activePublicUrl}.";
        }

        if ($hasApiKey) {
            return 'The ngrok authtoken and API key are saved, and the local config file validates. Start ngrok or add a reserved domain to expose a public base URL.';
        }

        return 'The ngrok authtoken is saved and the local config file validates. Start ngrok or add a reserved domain to expose a public base URL.';
    }

    protected function mask(mixed $value): ?string
    {
        $resolved = trim((string) $value);

        if ($resolved === '') {
            return null;
        }

        return strlen($resolved) > 8
            ? str_repeat('*', max(strlen($resolved) - 4, 8)).substr($resolved, -4)
            : str_repeat('*', strlen($resolved));
    }

    protected function escapeYamlValue(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
