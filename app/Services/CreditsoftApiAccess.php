<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserApiKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CreditsoftApiAccess
{
    public function isEnabled(): bool
    {
        return $this->asBool(config('creditsoft.api.enabled'), true);
    }

    public function currentToken(): ?string
    {
        $token = trim((string) config('creditsoft.api.token'));

        return $token !== '' ? $token : null;
    }

    public function hasToken(): bool
    {
        return filled($this->currentToken());
    }

    public function issueToken(): string
    {
        return 'csoft_'.Str::random(48);
    }

    public function issueUserToken(User $user, string $name = 'Personal API key', array $abilities = ['partner_api']): string
    {
        $token = 'csoft_u_'.Str::random(48);

        UserApiKey::query()->create([
            'user_id' => $user->getKey(),
            'name' => $name,
            'token_prefix' => substr($token, 0, 12),
            'token_suffix' => substr($token, -4),
            'token_hash' => hash('sha256', $token),
            'abilities' => $abilities,
        ]);

        return $token;
    }

    /**
     * @return Collection<int, UserApiKey>
     */
    public function activeKeysFor(User $user): Collection
    {
        return $user->apiKeys()
            ->active()
            ->get();
    }

    public function revokeUserKey(UserApiKey $key): void
    {
        $key->forceFill([
            'revoked_at' => now(),
        ])->save();
    }

    public function maskedToken(): ?string
    {
        $token = $this->currentToken();

        if (! $token) {
            return null;
        }

        return strlen($token) > 12
            ? substr($token, 0, 10).'...'.substr($token, -4)
            : '********';
    }

    public function matches(?string $incomingToken): bool
    {
        return $this->resolveToken($incomingToken) !== null;
    }

    /**
     * @return array{type:'user',user:User,key:UserApiKey}|array{type:'legacy'}|null
     */
    public function resolveToken(?string $incomingToken): ?array
    {
        if (! is_string($incomingToken) || trim($incomingToken) === '') {
            return null;
        }

        $token = trim($incomingToken);
        $hash = hash('sha256', $token);

        $userKey = UserApiKey::query()
            ->with('user')
            ->active()
            ->where('token_hash', $hash)
            ->first();

        if ($userKey && $userKey->user) {
            return [
                'type' => 'user',
                'user' => $userKey->user,
                'key' => $userKey,
            ];
        }

        $expected = $this->currentToken();

        if ($expected && hash_equals($expected, $token)) {
            return [
                'type' => 'legacy',
            ];
        }

        return null;
    }

    public function touchKeyUsage(UserApiKey $key): void
    {
        $key->forceFill([
            'last_used_at' => now(),
        ])->save();
    }

    public function ngrokEnabled(): bool
    {
        return $this->asBool(config('creditsoft.tunnels.ngrok.enabled'), false);
    }

    public function ngrokApiOnly(): bool
    {
        return $this->asBool(config('creditsoft.tunnels.ngrok.api_only'), true);
    }

    public function rawConfiguredPublicApiBaseUrl(): ?string
    {
        $value = trim((string) config('creditsoft.api.public_base_url'));

        return $value !== '' ? $value : null;
    }

    public function configuredPublicApiBaseUrl(): ?string
    {
        return $this->normalizePublicApiBaseUrl(config('creditsoft.api.public_base_url'));
    }

    /**
     * @return array{
     *     state: 'none'|'verified'|'unreachable'|'unchecked',
     *     normalized_base_url: ?string,
     *     callback_url: ?string,
     *     http_status: ?int,
     *     message: string
     * }
     */
    public function configuredPublicApiBaseStatus(bool $allowLiveCheck = true): array
    {
        $configuredBaseUrl = $this->configuredPublicApiBaseUrl();

        if (! $configuredBaseUrl) {
            return $this->emptyPublicApiBaseStatus();
        }

        $cacheKey = $this->publicApiBaseStatusCacheKey($configuredBaseUrl);

        if (! $allowLiveCheck) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                return $cached;
            }

            return [
                'state' => 'unchecked',
                'normalized_base_url' => $configuredBaseUrl,
                'callback_url' => rtrim($configuredBaseUrl, '/').'/meta/callback',
                'http_status' => null,
                'message' => 'A website bridge domain is saved. CreditSoft skipped the live bridge check on page load so the settings screen cannot deadlock through ngrok.',
            ];
        }

        return Cache::remember(
            $cacheKey,
            now()->addSeconds(60),
            fn (): array => $this->inspectNormalizedPublicApiBaseUrl($configuredBaseUrl),
        );
    }

    /**
     * @return array{
     *     state: 'none'|'verified'|'unreachable'|'unchecked',
     *     normalized_base_url: ?string,
     *     callback_url: ?string,
     *     http_status: ?int,
     *     message: string
     * }
     */
    public function inspectPublicApiBaseUrl(mixed $value): array
    {
        $normalizedBaseUrl = $this->normalizePublicApiBaseUrl($value);

        if (! $normalizedBaseUrl) {
            return $this->emptyPublicApiBaseStatus();
        }

        return $this->inspectNormalizedPublicApiBaseUrl($normalizedBaseUrl);
    }

    public function forgetPublicApiBaseStatusCache(mixed $value): void
    {
        $normalizedBaseUrl = $this->normalizePublicApiBaseUrl($value);

        if (! $normalizedBaseUrl) {
            return;
        }

        Cache::forget($this->publicApiBaseStatusCacheKey($normalizedBaseUrl));
    }

    public function configuredPublicMetaCallbackUrl(): ?string
    {
        $configuredBaseUrl = $this->configuredPublicApiBaseUrl();

        return $configuredBaseUrl ? rtrim($configuredBaseUrl, '/').'/meta/callback' : null;
    }

    public function verifiedConfiguredPublicApiBaseUrl(): ?string
    {
        $status = $this->configuredPublicApiBaseStatus();

        return $status['state'] === 'verified' ? $status['normalized_base_url'] : null;
    }

    public function publicApiBaseSource(): string
    {
        if ($this->configuredPublicApiBaseUrl()) {
            return 'api_domain';
        }

        return $this->ngrokPublicBaseUrl() ? 'ngrok' : 'local';
    }

    public function publicApiBaseUrl(): ?string
    {
        $configuredBaseUrl = $this->configuredPublicApiBaseUrl();

        if ($configuredBaseUrl) {
            return $configuredBaseUrl;
        }

        $ngrokBaseUrl = $this->ngrokPublicBaseUrl();

        return $ngrokBaseUrl ? rtrim($ngrokBaseUrl, '/').'/api/v1' : null;
    }

    public function publicMetaCallbackUrl(): ?string
    {
        $publicApiBaseUrl = $this->publicApiBaseUrl();

        return $publicApiBaseUrl ? rtrim($publicApiBaseUrl, '/').'/meta/callback' : null;
    }

    public function publicMetaOauthCallbackUrl(): ?string
    {
        $configuredBaseUrl = $this->configuredPublicApiBaseUrl();

        if ($configuredBaseUrl) {
            return $this->publicRootPhpUrl($configuredBaseUrl, 'oauth.php');
        }

        return $this->publicMetaCallbackUrl();
    }

    public function publicMetaRootEndpointUrl(string $filename): ?string
    {
        $configuredBaseUrl = $this->configuredPublicApiBaseUrl();

        if (! $configuredBaseUrl) {
            return null;
        }

        return $this->publicRootPhpUrl($configuredBaseUrl, $filename);
    }

    public function ngrokPublicBaseUrl(): ?string
    {
        if (! $this->ngrokEnabled()) {
            return null;
        }

        $domain = trim((string) config('creditsoft.tunnels.ngrok.domain'));

        if ($domain === '') {
            return $this->activeNgrokBaseUrl();
        }

        return str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')
            ? rtrim($domain, '/')
            : 'https://'.trim($domain, '/');
    }

    public function activeNgrokBaseUrl(): ?string
    {
        try {
            $response = Http::connectTimeout(1)
                ->timeout(1)
                ->acceptJson()
                ->get('http://127.0.0.1:4040/api/tunnels');
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $tunnels = $response->json('tunnels');

        if (! is_array($tunnels)) {
            return null;
        }

        $httpsTunnel = collect($tunnels)
            ->first(fn (mixed $tunnel): bool => is_array($tunnel) && str_starts_with((string) ($tunnel['public_url'] ?? ''), 'https://'));

        if (is_array($httpsTunnel) && filled($httpsTunnel['public_url'] ?? null)) {
            return rtrim((string) $httpsTunnel['public_url'], '/');
        }

        $httpTunnel = collect($tunnels)
            ->first(fn (mixed $tunnel): bool => is_array($tunnel) && str_starts_with((string) ($tunnel['public_url'] ?? ''), 'http://'));

        if (is_array($httpTunnel) && filled($httpTunnel['public_url'] ?? null)) {
            return rtrim((string) $httpTunnel['public_url'], '/');
        }

        return null;
    }

    protected function asBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
        }

        return $default;
    }

    protected function normalizePublicApiBaseUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (! str_starts_with($trimmed, 'http://') && ! str_starts_with($trimmed, 'https://')) {
            $trimmed = 'https://'.$trimmed;
        }

        $parts = parse_url($trimmed);

        if (! is_array($parts) || ! filled($parts['scheme'] ?? null) || ! filled($parts['host'] ?? null)) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = '/'.ltrim((string) ($parts['path'] ?? ''), '/');
        $path = $path === '/' ? '/api/v1' : rtrim($path, '/');

        if ($path === '/api') {
            $path = '/api/v1';
        }

        if (! str_ends_with($path, '/api/v1')) {
            $path .= '/api/v1';
        }

        return "{$scheme}://{$host}{$port}{$path}";
    }

    protected function publicRootPhpUrl(string $publicApiBaseUrl, string $filename): ?string
    {
        $parts = parse_url($publicApiBaseUrl);

        if (! is_array($parts) || ! filled($parts['scheme'] ?? null) || ! filled($parts['host'] ?? null)) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return "{$scheme}://{$host}{$port}/".ltrim($filename, '/');
    }

    /**
     * @return array{
     *     state: 'none'|'verified'|'unreachable'|'unchecked',
     *     normalized_base_url: ?string,
     *     callback_url: ?string,
     *     http_status: ?int,
     *     message: string
     * }
     */
    protected function emptyPublicApiBaseStatus(): array
    {
        return [
            'state' => 'none',
            'normalized_base_url' => null,
            'callback_url' => null,
            'http_status' => null,
            'message' => 'No website bridge domain is saved yet.',
        ];
    }

    /**
     * @return array{
     *     state: 'none'|'verified'|'unreachable',
     *     normalized_base_url: ?string,
     *     callback_url: ?string,
     *     http_status: ?int,
     *     message: string
     * }
     */
    protected function inspectNormalizedPublicApiBaseUrl(string $normalizedBaseUrl): array
    {
        $callbackUrl = rtrim($normalizedBaseUrl, '/').'/meta/callback';

        $response = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::connectTimeout(6)
                    ->timeout(10)
                    ->withoutRedirecting()
                    ->get($callbackUrl);

                break;
            } catch (\Throwable) {
                if ($attempt < 3) {
                    usleep(350000);
                }
            }
        }

        if ($response === null) {
            return [
                'state' => 'unreachable',
                'normalized_base_url' => $normalizedBaseUrl,
                'callback_url' => $callbackUrl,
                'http_status' => null,
                'message' => 'CreditSoft could not reach the saved website bridge yet. Keep ngrok, Tailscale, or a reverse proxy forwarding /api/v1 until this domain responds.',
            ];
        }

        $status = $response->status();

        if ($status >= 200 && $status < 400) {
            return [
                'state' => 'verified',
                'normalized_base_url' => $normalizedBaseUrl,
                'callback_url' => $callbackUrl,
                'http_status' => $status,
                'message' => 'CreditSoft reached the Meta callback path on this host. The saved website bridge can carry the stable callback lane.',
            ];
        }

        return [
            'state' => 'unreachable',
            'normalized_base_url' => $normalizedBaseUrl,
            'callback_url' => $callbackUrl,
            'http_status' => $status,
            'message' => "CreditSoft checked the saved website bridge and got HTTP {$status}. Keep ngrok, Tailscale, or a reverse proxy forwarding /api/v1 until this domain responds.",
        ];
    }

    protected function publicApiBaseStatusCacheKey(string $normalizedBaseUrl): string
    {
        return 'creditsoft.public_api_base_status:'.sha1($normalizedBaseUrl);
    }
}
