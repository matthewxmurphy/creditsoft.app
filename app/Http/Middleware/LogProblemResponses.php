<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogProblemResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            Log::error('HTTP exception before response.', [
                'method' => $request->method(),
                'path' => $request->path(),
                'route' => $request->route()?->getName(),
                'user_id' => $request->user()?->getKey(),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if ($response->getStatusCode() >= 400) {
            Log::warning('HTTP problem response.', [
                'status' => $response->getStatusCode(),
                'method' => $request->method(),
                'path' => $request->path(),
                'route' => $request->route()?->getName(),
                'user_id' => $request->user()?->getKey(),
                'expects_json' => $request->expectsJson(),
                'inertia' => $request->headers->get('X-Inertia'),
                'inertia_version' => $request->headers->get('X-Inertia-Version'),
                'response_inertia_location' => $response->headers->get('X-Inertia-Location'),
                'referer_path' => $this->refererPath($request),
            ]);
        }

        return $response;
    }

    private function refererPath(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        if (! is_string($referer) || $referer === '') {
            return null;
        }

        $path = parse_url($referer, PHP_URL_PATH);

        return is_string($path) ? $path : null;
    }
}
