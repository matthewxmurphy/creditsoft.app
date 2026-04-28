<?php

namespace App\Http\Middleware;

use App\Services\CreditsoftApiAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictNgrokPortalExposure
{
    public function __construct(
        protected CreditsoftApiAccess $apiAccess,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->apiAccess->ngrokEnabled() || ! $this->apiAccess->ngrokApiOnly()) {
            return $next($request);
        }

        $publicBaseUrl = $this->apiAccess->ngrokPublicBaseUrl();
        $ngrokHost = is_string($publicBaseUrl) ? parse_url($publicBaseUrl, PHP_URL_HOST) : null;

        if (! is_string($ngrokHost) || trim($ngrokHost) === '') {
            return $next($request);
        }

        $requestHost = trim((string) ($request->header('x-forwarded-host') ?: $request->getHost()));
        $normalizedHost = strtolower(str_contains($requestHost, ':') ? explode(':', $requestHost, 2)[0] : $requestHost);

        if ($normalizedHost !== strtolower($ngrokHost)) {
            return $next($request);
        }

        abort(404);
    }
}
