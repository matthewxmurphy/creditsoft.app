<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiDocsHostService
{
    public function shouldServeDocsAtRoot(Request $request): bool
    {
        $host = $this->normalizeHost($request);

        if ($host === null) {
            return false;
        }

        $configuredHosts = collect(config('creditsoft.api.docs_hosts', []))
            ->filter(fn (mixed $value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => Str::lower(trim($value)))
            ->values();

        if ($configuredHosts->contains($host)) {
            return true;
        }

        return collect(config('creditsoft.api.docs_host_prefixes', []))
            ->filter(fn (mixed $value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => Str::lower(trim($value)))
            ->contains(fn (string $prefix) => str_starts_with($host, $prefix));
    }

    public function docsMeta(Request $request): array
    {
        $host = $this->normalizeHost($request) ?? 'api';
        $brand = $this->brandLabel($host);
        $origin = $this->origin($request);

        return [
            'host' => $host,
            'origin' => $origin,
            'title' => "{$brand} API",
            'subtitle' => 'Reference, auth details, and live endpoint explorer.',
            'description' => "Interactive API docs for {$brand}. Explore the live spec, authenticate with a bearer token, and test the current host without leaving the page.",
            'spec_url' => "{$origin}/openapi.yaml",
            'api_base_url' => "{$origin}/api/v1",
        ];
    }

    public function publicSpecUrl(Request $request): string
    {
        return $this->docsMeta($request)['spec_url'];
    }

    protected function brandLabel(string $host): string
    {
        $labels = config('creditsoft.api.docs_host_labels', []);
        $configured = $labels[$host] ?? null;

        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        $segments = explode('.', $host);

        if (count($segments) >= 3 && $segments[0] === 'api') {
            return Str::headline($segments[1]);
        }

        if (count($segments) >= 2) {
            return Str::headline($segments[count($segments) - 2]);
        }

        return 'API';
    }

    protected function origin(Request $request): string
    {
        $scheme = $request->header('x-forwarded-proto') ?: $request->getScheme();
        $host = $request->header('x-forwarded-host') ?: $request->getHttpHost();

        return rtrim("{$scheme}://{$host}{$request->getBaseUrl()}", '/');
    }

    protected function normalizeHost(Request $request): ?string
    {
        $host = $request->header('x-forwarded-host') ?: $request->getHttpHost();

        if (! is_string($host) || trim($host) === '') {
            return null;
        }

        return Str::lower(trim(Str::before($host, ':')));
    }
}
