<?php

namespace App\Http\Middleware;

use App\Models\UserApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCreditsoftApiAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $tokenType = $request->attributes->get('creditsoft_api_token_type');

        if ($tokenType !== 'user') {
            return $this->forbidden('This endpoint requires a personal CreditSoft API key.');
        }

        /** @var UserApiKey|null $key */
        $key = $request->attributes->get('creditsoft_api_key');

        if (! $key) {
            return $this->forbidden('A personal CreditSoft API key is required.');
        }

        $abilities = collect($key->abilities ?? [])
            ->filter(fn ($entry) => is_string($entry) && trim($entry) !== '')
            ->values();

        if (! $abilities->contains($ability)) {
            return $this->forbidden("This API key is missing the {$ability} ability.");
        }

        return $next($request);
    }

    protected function forbidden(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 403);
    }
}
