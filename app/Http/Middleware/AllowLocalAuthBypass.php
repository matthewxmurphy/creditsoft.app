<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AllowLocalAuthBypass
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->attempt($request);

        return $next($request);
    }

    public function attempt(Request $request): void
    {
        if (
            ! (bool) config('creditsoft.access.local_auth_bypass.enabled', false)
            || $request->user()
            || $request->is('api/*')
            || $request->routeIs('logout')
            || ! $this->requestUsesAllowedHost($request)
        ) {
            return;
        }

        $email = trim((string) config('creditsoft.access.local_auth_bypass.email', ''));
        $user = $email !== ''
            ? User::query()->where('email', $email)->first()
            : null;

        $user ??= User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['owner_admin', 'admin', 'demo_admin']))
            ->orderBy('id')
            ->first();

        if ($user) {
            Auth::guard('web')->login($user);
            $request->setUserResolver(fn () => $user);
        }
    }

    protected function requestUsesAllowedHost(Request $request): bool
    {
        $forwardedHost = trim((string) $request->header('x-forwarded-host'));

        if ($forwardedHost !== '') {
            return $this->hostIsAllowed($forwardedHost);
        }

        $host = trim((string) ($request->header('host') ?: $request->getHost()));

        return $host !== '' && $this->hostIsAllowed($host);
    }

    protected function hostIsAllowed(string $host): bool
    {
        $normalizedHost = $this->normalizeHost($host);

        if ($normalizedHost === '') {
            return false;
        }

        $allowedHosts = collect(config('creditsoft.access.local_auth_bypass.allowed_hosts', []))
            ->map(fn (string $allowedHost) => $this->normalizeHost($allowedHost))
            ->filter()
            ->values()
            ->all();

        return in_array($normalizedHost, $allowedHosts, true);
    }

    protected function normalizeHost(string $host): string
    {
        $host = strtolower(trim(explode(',', $host, 2)[0] ?? ''));

        if ($host === '') {
            return '';
        }

        if (str_starts_with($host, '[')) {
            $end = strpos($host, ']');

            return $end === false ? trim($host, '[]') : substr($host, 1, $end - 1);
        }

        if (substr_count($host, ':') === 1) {
            return explode(':', $host, 2)[0];
        }

        return $host;
    }
}
