<?php

namespace App\Http\Middleware;

use App\Services\LicenseStateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceCreditsoftLicenseAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $licenseState = app(LicenseStateService::class);
        $user = $request->user();

        if (! $user || ! $licenseState->isLocked()) {
            return $next($request);
        }

        if (
            $request->is('install')
            || $request->is('install/*')
            || $request->is('settings/license')
            || $request->is('logout')
            || $request->routeIs('install.*')
            || $request->routeIs('settings.license')
            || $request->routeIs('settings.license.update')
            || $request->routeIs('logout')
        ) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'License grace ended. Renew the office license to restore workspace access.',
            ], 423);
        }

        $request->session()->flash('toast', [
            'type' => 'error',
            'message' => 'License grace ended. Renew the office license to restore workspace access.',
        ]);

        return redirect()->route('settings.license');
    }
}
