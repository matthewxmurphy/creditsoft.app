<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserApiKey;
use App\Services\CreditsoftApiAccess;
use App\Services\CreditsoftCrmAutomationBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmAutomationWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        CreditsoftCrmAutomationBridgeService $bridge,
        CreditsoftApiAccess $apiAccess,
    ): JsonResponse {
        if (! (bool) config('creditsoft.integrations.crm.automation.enabled', true)) {
            return response()->json([
                'message' => 'CreditSoft CRM automation is disabled.',
            ], 404);
        }

        $actor = $this->authorizedActor($request, $apiAccess);

        if ($actor === false) {
            return response()->json([
                'message' => 'A valid CreditSoft CRM webhook secret or API key is required.',
            ], 401);
        }

        $payload = $request->json()->all();

        if (! is_array($payload) || $payload === []) {
            $payload = $request->all();
        }

        if (! is_array($payload) || $payload === []) {
            return response()->json([
                'message' => 'Webhook payload is empty.',
            ], 422);
        }

        $event = $bridge->handleTwentyWebhook($payload, $actor);

        return response()->json([
            'message' => 'CRM automation event processed.',
            'event' => [
                'id' => $event->getKey(),
                'provider' => $event->provider,
                'event_type' => $event->event_type,
                'object_type' => $event->object_type,
                'object_id' => $event->object_id,
                'client_id' => $event->client_id,
                'status' => $event->status,
                'priority' => $event->priority,
                'decision' => $event->decision,
            ],
        ], $event->wasRecentlyCreated ? 202 : 200);
    }

    protected function authorizedActor(Request $request, CreditsoftApiAccess $apiAccess): User|false|null
    {
        $provided = trim((string) (
            $request->header('X-CreditSoft-CRM-Webhook-Secret')
            ?: $request->header('X-CreditSoft-Webhook-Secret')
            ?: $request->header('X-Twenty-Webhook-Secret')
            ?: $request->bearerToken()
        ));
        $expected = trim((string) config('creditsoft.integrations.crm.automation.webhook_secret', ''));

        if ($expected !== '' && $provided !== '' && hash_equals($expected, $provided)) {
            return null;
        }

        $resolution = $apiAccess->resolveToken($request->bearerToken() ?: $request->header('X-CreditSoft-Token'));

        if (! $resolution) {
            return false;
        }

        if (($resolution['type'] ?? null) === 'legacy') {
            return null;
        }

        /** @var UserApiKey|null $key */
        $key = $resolution['key'] ?? null;

        if (! $key || ! $this->keyAllowsCrmAutomation($key)) {
            return false;
        }

        $apiAccess->touchKeyUsage($key);

        return $resolution['user'] ?? null;
    }

    protected function keyAllowsCrmAutomation(UserApiKey $key): bool
    {
        $abilities = collect($key->abilities ?? [])
            ->filter(fn (mixed $ability): bool => is_string($ability) && trim($ability) !== '')
            ->map(fn (string $ability): string => trim($ability));

        return $abilities->contains('crm_automation')
            || $abilities->contains('partner_api')
            || $abilities->contains('*');
    }
}
