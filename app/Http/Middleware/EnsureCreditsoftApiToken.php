<?php

namespace App\Http\Middleware;

use App\Services\CreditsoftApiAccess;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCreditsoftApiToken
{
    public function __construct(
        protected CreditsoftApiAccess $apiAccess,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->apiAccess->isEnabled()) {
            return $this->forbidden('The CreditSoft partner API is disabled.');
        }

        $token = $request->bearerToken() ?: $request->header('X-CreditSoft-Token');
        $resolution = $this->apiAccess->resolveToken($token);

        if (! $resolution) {
            return $this->unauthorized('A valid CreditSoft API token is required.');
        }

        if (($resolution['type'] ?? null) === 'user') {
            $this->apiAccess->touchKeyUsage($resolution['key']);
            $request->attributes->set('creditsoft_api_key', $resolution['key']);
            $request->attributes->set('creditsoft_api_token_type', 'user');
            $request->setUserResolver(fn () => $resolution['user']);
        } else {
            $request->attributes->set('creditsoft_api_token_type', 'legacy');
        }

        return $next($request);
    }

    protected function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 401);
    }

    protected function forbidden(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 403);
    }
}
