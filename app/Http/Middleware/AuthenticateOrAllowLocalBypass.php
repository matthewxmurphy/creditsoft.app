<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateOrAllowLocalBypass
{
    public function __construct(
        protected AllowLocalAuthBypass $localAuthBypass,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->localAuthBypass->attempt($request);

        if ($request->user()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return redirect()->guest(route('login'));
    }
}
