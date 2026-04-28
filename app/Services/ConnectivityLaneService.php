<?php

namespace App\Services;

use Illuminate\Http\Request;

class ConnectivityLaneService
{
    /**
     * @param  array<string, mixed>  $tailscaleStatus
     * @return array{
     *     local_base_url: string,
     *     public_base_url: ?string,
     *     tailnet_base_url: ?string,
     *     preferred_base_url: string
     * }
     */
    public function apiUrls(Request $request, CreditsoftApiAccess $apiAccess, array $tailscaleStatus): array
    {
        $localBaseUrl = url('/api/v1');
        $publicBaseUrl = $apiAccess->publicApiBaseUrl();
        $tailnetBaseUrl = $this->tailnetBaseUrl($request, $tailscaleStatus);

        return [
            'local_base_url' => $localBaseUrl,
            'public_base_url' => $publicBaseUrl,
            'tailnet_base_url' => $tailnetBaseUrl,
            'preferred_base_url' => $publicBaseUrl ?? $tailnetBaseUrl ?? $localBaseUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $tailscaleStatus
     * @return array{
     *     local_base_url: string,
     *     public_base_url: ?string,
     *     tailnet_base_url: ?string,
     *     preferred_base_url: string
     * }
     */
    public function portalUrls(Request $request, CreditsoftApiAccess $apiAccess, array $tailscaleStatus): array
    {
        $localBaseUrl = url('/');
        $publicBaseUrl = $apiAccess->ngrokPublicBaseUrl();
        $tailnetBaseUrl = $this->tailnetOrigin($request, $tailscaleStatus);

        return [
            'local_base_url' => $localBaseUrl,
            'public_base_url' => $publicBaseUrl,
            'tailnet_base_url' => $tailnetBaseUrl,
            'preferred_base_url' => $publicBaseUrl ?? $tailnetBaseUrl ?? $localBaseUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $tailscaleStatus
     */
    protected function tailnetBaseUrl(Request $request, array $tailscaleStatus): ?string
    {
        $origin = $this->tailnetOrigin($request, $tailscaleStatus);

        if (! $origin) {
            return null;
        }

        return rtrim($origin, '/').'/api/v1';
    }

    protected function shouldIncludePort(string $scheme, int|string|null $port): bool
    {
        $normalizedPort = is_numeric($port) ? (int) $port : null;

        if ($normalizedPort === null) {
            return false;
        }

        return ! (($scheme === 'http' && $normalizedPort === 80) || ($scheme === 'https' && $normalizedPort === 443));
    }

    /**
     * @param  array<string, mixed>  $tailscaleStatus
     */
    protected function tailnetOrigin(Request $request, array $tailscaleStatus): ?string
    {
        $host = $this->firstString(
            $tailscaleStatus['dns_name'] ?? null,
            $tailscaleStatus['ipv4'] ?? null,
        );

        if (! $host) {
            return null;
        }

        $scheme = $request->getScheme();
        $port = $request->getPort();
        $portSegment = $this->shouldIncludePort($scheme, $port) ? ':'.$port : '';

        return "{$scheme}://{$host}{$portSegment}";
    }

    protected function firstString(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);

            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }
}
