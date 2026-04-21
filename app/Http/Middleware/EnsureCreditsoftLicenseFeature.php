<?php

namespace App\Http\Middleware;

use App\Services\LicenseStateService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCreditsoftLicenseFeature
{
    public function __construct(
        protected LicenseStateService $licenseState,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if ($this->licenseState->allows($feature)) {
            return $next($request);
        }

        $message = $this->licenseState->featureUnavailableMessage($feature);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $message,
                'feature' => $feature,
                'plan' => data_get($this->licenseState->current(), 'plan_key'),
            ], 403);
        }

        if ($request->routeIs('install.*') || $request->is('install') || $request->is('install/*')) {
            return $this->redirectWithToast($request, route('install.show'), $message);
        }

        $fallback = $request->user() ? route('settings.license') : route('install.show');
        $target = url()->previous();

        if ($target === '' || $target === $request->fullUrl()) {
            $target = $fallback;
        }

        return $this->redirectWithToast($request, $target, $message);
    }

    protected function redirectWithToast(Request $request, string $target, string $message): RedirectResponse
    {
        $request->session()->flash('toast', [
            'type' => 'warning',
            'message' => $message,
        ]);

        return redirect()->to($target);
    }
}
