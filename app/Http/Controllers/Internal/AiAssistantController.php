<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\AuditTrail;
use App\Services\CreditsoftAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AiAssistantController extends Controller
{
    public function store(
        Request $request,
        CreditsoftAiService $aiService,
        AuditTrail $auditTrail,
    ): JsonResponse {
        $validated = $request->validate([
            'lane' => ['required', 'in:opencode_zen,openrouter_creditsoft,ollama_cloud'],
            'message' => ['required', 'string', 'max:6000'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'history' => ['nullable', 'array', 'max:6'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
        ]);

        $client = isset($validated['client_id']) ? Client::query()->find($validated['client_id']) : null;

        try {
            $result = $aiService->chatAssistant(
                provider: $validated['lane'],
                message: $validated['message'],
                client: $client,
                history: $validated['history'] ?? [],
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $auditTrail->record(
            $request->user(),
            'ai.assistant.chat',
            'AI assistant reply generated via '.($result['meta']['provider'] ?? $validated['lane']),
            $client,
            [
                'lane' => $validated['lane'],
                'model' => $result['meta']['model'] ?? null,
                'message_preview' => mb_strimwidth((string) $validated['message'], 0, 180, '...'),
                'reply_preview' => mb_strimwidth((string) $result['reply'], 0, 220, '...'),
            ],
        );

        return response()->json([
            'reply' => $result['reply'],
            'meta' => [
                'provider' => $result['meta']['provider'] ?? $validated['lane'],
                'model' => $result['meta']['model'] ?? null,
                'lane' => $validated['lane'],
            ],
        ]);
    }
}
