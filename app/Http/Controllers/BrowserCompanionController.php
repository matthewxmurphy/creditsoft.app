<?php

namespace App\Http\Controllers;

use App\Services\BrowserCompanionBundle;
use App\Services\InstallerState;
use App\Services\LicenseCheckService;
use App\Services\LicenseStateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        $bundle->build();

        return redirect()->away($bundle->publicDownloadUrl());
    }

    protected function redirectToLicenseHub(Request $request, string $message): RedirectResponse
    {
        $request->session()->flash('toast', [
            'type' => 'warning',
            'message' => $message,
        ]);

        return redirect()->to($request->user() ? route('settings.license') : route('install.show'));
    }
}
