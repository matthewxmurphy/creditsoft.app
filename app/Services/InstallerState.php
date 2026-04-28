<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class InstallerState
{
    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        $path = $this->path();

        if (! File::exists($path)) {
            return $this->defaults();
        }

        $decoded = json_decode(File::get($path), true);

        if (! is_array($decoded)) {
            return $this->defaults();
        }

        return array_replace_recursive($this->defaults(), $decoded);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function merge(array $payload): array
    {
        return $this->store(array_replace_recursive($this->read(), $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function store(array $payload): array
    {
        $state = array_replace_recursive($this->defaults(), $payload);
        $path = $this->path();

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $state;
    }

    public function path(): string
    {
        return (string) config('creditsoft.installer.state_path', storage_path('app/private/install/state.json'));
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'installed_at' => null,
            'company_name' => null,
            'admin_email' => null,
            'tailscale_required' => (bool) config('creditsoft.tunnels.tailscale.required', true),
            'tailscale_hostname' => config('creditsoft.tailscale_hostname'),
            'tailscale_tailnet' => (string) config('creditsoft.tunnels.tailscale.tailnet', ''),
            'tailscale_api_key_expires_at' => (string) config('creditsoft.tunnels.tailscale.api_key_expires_at', ''),
            'backup_destination' => 'wasabi',
            'portal_sync_enabled' => true,
            'report_feedback_enabled' => false,
            'api_enabled' => (bool) config('creditsoft.api.enabled', true),
            'ai_default_provider' => (string) config('ai.default', 'openrouter_creditsoft'),
            'ngrok_enabled' => (bool) config('creditsoft.tunnels.ngrok.enabled', false),
            'ngrok_api_only' => (bool) config('creditsoft.tunnels.ngrok.api_only', true),
            'branding' => [
                'logo_name' => null,
                'logo_url' => null,
                'uploaded_at' => null,
            ],
            'license' => [
                'valid' => false,
                'status' => 'pending',
                'mode' => config('creditsoft.installer.license_mode', 'soft'),
                'requested_mode' => config('creditsoft.installer.license_mode', 'soft'),
                'message' => 'License has not been checked yet.',
                'checked_at' => null,
                'last_verified_at' => null,
                'remote_unreachable' => false,
                'verification_fail_started_at' => null,
                'masked_key' => null,
                'plan' => null,
                'plan_key' => null,
                'features' => [],
                'expires_at' => null,
                'grace_days' => (int) config('creditsoft.installer.license_grace_days', 7),
                'grace_ends_at' => null,
                'access_state' => 'pending',
                'can_access_workspace' => true,
            ],
            'feature_trials' => [],
            'updates' => [
                'current_version' => (string) config('creditsoft.updates.current_version', '2026.4.27.1'),
                'current_build' => (string) config('creditsoft.updates.current_build', config('creditsoft.updates.current_version', '2026.4.27.1')),
            ],
        ];
    }
}
