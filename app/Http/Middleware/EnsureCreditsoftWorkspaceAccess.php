<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCreditsoftWorkspaceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        abort_unless(method_exists($user, 'hasWorkspaceAccess') && $user->hasWorkspaceAccess(), 403);

        return $next($request);
    }
}
