<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\UserApiKey;
use App\Services\AuditTrail;
use App\Services\ConnectivityLaneService;
use App\Services\CreditsoftApiAccess;
use App\Services\LicenseStateService;
use App\Services\TailscaleStatusService;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(
        Request $request,
        CreditsoftApiAccess $apiAccess,
        TailscaleStatusService $tailscaleStatus,
        ConnectivityLaneService $laneService,
        LicenseStateService $licenseState,
    ): Response
    {
        $detectedTailscale = $tailscaleStatus->current();
        $apiUrls = $laneService->apiUrls($request, $apiAccess, $detectedTailscale);

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'clientPairing' => [
                'generated_personal_token' => $request->session()->get('generated_personal_api_token'),
                'personal_keys' => $request->user()
                    ? $apiAccess->activeKeysFor($request->user())
                        ->map(fn (UserApiKey $key) => [
                            'id' => $key->getKey(),
                            'name' => $key->name,
                            'masked_token' => $key->masked_token,
                            'last_used_at' => optional($key->last_used_at)?->toIso8601String(),
                            'created_at' => optional($key->created_at)?->toIso8601String(),
                        ])
                        ->values()
                        ->all()
                    : [],
                ...$apiUrls,
                'tailscale_running' => (bool) ($detectedTailscale['running'] ?? false),
                'tailscale_dns_name' => $detectedTailscale['dns_name'] ?? null,
                'browser_companion_enabled' => $licenseState->allows('browser_companion'),
                'browser_companion_download_url' => $licenseState->allows('browser_companion')
                    ? route('browser-companion.download')
                    : null,
                'connectivity_url' => route('connectivity.edit'),
                'api_docs_url' => route('api-docs.edit'),
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    public function storeApiKey(
        Request $request,
        CreditsoftApiAccess $apiAccess,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        abort_unless($user, 403);

        $name = trim((string) ($validated['name'] ?? ''));
        if ($name === '') {
            $name = 'Intranet client';
        }

        $plainToken = $apiAccess->issueUserToken($user, $name, ['partner_api', 'browser_companion', 'intranet_client', 'crm_automation']);
        $request->session()->flash('generated_personal_api_token', $plainToken);

        $auditTrail->record(
            $user,
            'settings.profile.api_key.created',
            "Generated personal API key {$name}.",
            null,
            [
                'key_name' => $name,
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Personal API key generated. Copy it now; the full value is only shown once.',
        ]);

        return to_route('profile.edit');
    }

    public function destroyApiKey(
        Request $request,
        UserApiKey $userApiKey,
        CreditsoftApiAccess $apiAccess,
        AuditTrail $auditTrail,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user, 403);

        if ($userApiKey->user_id !== $user->getKey() && ! $user->hasRole('owner_admin')) {
            abort(403);
        }

        $apiAccess->revokeUserKey($userApiKey);

        $auditTrail->record(
            $user,
            'settings.profile.api_key.revoked',
            "Revoked personal API key {$userApiKey->name}.",
            null,
            [
                'key_id' => $userApiKey->getKey(),
                'key_name' => $userApiKey->name,
            ],
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Personal API key revoked.',
        ]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
