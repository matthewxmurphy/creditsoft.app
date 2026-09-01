<?php

namespace App\Http\Controllers;

use App\Services\BrowserCompanionBundle;
use App\Services\InstallerState;
use App\Services\LicenseCheckService;
use App\Services\LicenseStateService;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class BrowserCompanionController extends Controller
{
    public function download(
        Request $request,
        BrowserCompanionBundle $bundle,
        InstallerState $installerState,
        LicenseCheckService $licenseCheckService,
        LicenseStateService $licenseState,
    ): Response
    {
        $state = $installerState->read();
        $savedKey = Str::upper(trim((string) ($state['license_key'] ?? '')));

        if ($savedKey !== '') {
            $refreshedLicense = $licenseCheckService->check($savedKey, [
                'company_name' => $state['company_name'] ?? config('app.name', 'CreditSoft'),
                'admin_email' => $state['admin_email'] ?? $request->user()?->email,
                'tailscale_hostname' => $state['tailscale_hostname'] ?? config('creditsoft.tailscale_hostname'),
            ]);

            $installerState->merge([
                'license' => $refreshedLicense,
            ]);
        }

        if (! $licenseState->allows('browser_companion')) {
            return $this->redirectToLicenseHub($request, $licenseState->featureUnavailableMessage('browser_companion'));
        }

        $publicDownloadUrl = $bundle->publicDownloadUrl();

        if ($this->isPublicZipUrl($publicDownloadUrl)) {
            $download = $this->downloadPublicZip($publicDownloadUrl, $bundle->downloadName());

            if ($download instanceof Response) {
                return $download;
            }
        }

        return response()->download(
            $bundle->build(),
            $bundle->downloadName(),
            ['Content-Type' => 'application/zip'],
        );
    }

    protected function redirectToLicenseHub(Request $request, string $message): RedirectResponse
    {
        $request->session()->flash('toast', [
            'type' => 'warning',
            'message' => $message,
        ]);

        return redirect()->to($request->user() ? route('settings.license') : route('install.show'));
    }

    protected function isPublicZipUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return in_array($scheme, ['http', 'https'], true) && str_ends_with($path, '.zip');
    }

    protected function downloadPublicZip(string $url, string $fallbackName): ?Response
    {
        try {
            $response = Http::timeout(30)
                ->withOptions($this->ipv4CurlOptions())
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful() || $response->body() === '') {
            return null;
        }

        $filename = $this->downloadNameFromUrl($url) ?: $fallbackName;

        return response($response->body(), Response::HTTP_OK, [
            'Cache-Control' => 'no-store',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Type' => 'application/zip',
        ]);
    }

    protected function downloadNameFromUrl(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $basename = basename(rawurldecode($path));

        return str_ends_with(strtolower($basename), '.zip') ? $basename : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function ipv4CurlOptions(): array
    {
        if (! defined('CURLOPT_IPRESOLVE') || ! defined('CURL_IPRESOLVE_V4')) {
            return [];
        }

        return [
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ],
        ];
    }
}
