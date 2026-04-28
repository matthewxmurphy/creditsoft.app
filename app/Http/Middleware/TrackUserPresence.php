<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserPresence
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $now = now();
        $threshold = now()->subMinutes(5);
        $updates = [];

        if (! $user->last_login_at) {
            $updates['last_login_at'] = $now;
        }

        if (! $user->last_seen_at || $user->last_seen_at->lessThanOrEqualTo($threshold)) {
            $updates['last_seen_at'] = $now;
        }

        if ($updates !== []) {
            $user->forceFill($updates)->saveQuietly();
        }

        return $next($request);
    }
}
