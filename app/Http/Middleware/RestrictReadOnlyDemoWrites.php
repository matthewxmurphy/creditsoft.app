<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RestrictReadOnlyDemoWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'isReadOnlyDemo') || ! $user->isReadOnlyDemo()) {
            return $next($request);
        }

        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Demo accounts are view-only.',
            ], 403);
        }

        Inertia::flash('toast', [
            'type' => 'warning',
            'message' => 'Demo accounts are view-only and cannot change records.',
        ]);

        return redirect()->back(status: 303);
    }
}
