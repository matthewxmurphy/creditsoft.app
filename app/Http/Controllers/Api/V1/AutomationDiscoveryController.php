<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AutomationDiscovery;
use App\Services\AutomationDiscoveryIngestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationDiscoveryController extends Controller
{
    public function store(Request $request, AutomationDiscoveryIngestor $ingestor): JsonResponse
    {
        $validated = $request->validate([
            'source_system' => ['nullable', 'string', 'max:120'],
            'page_title' => ['nullable', 'string', 'max:255'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'worker_id' => ['nullable', 'string', 'max:120'],
            'automation' => ['required', 'array'],
            'automation.source_system' => ['nullable', 'string', 'max:120'],
            'automation.source_product' => ['nullable', 'string', 'max:120'],
            'automation.page_kind' => ['nullable', 'string', 'max:80'],
            'automation.workflow' => ['nullable', 'array'],
            'automation.workflows' => ['nullable', 'array'],
            'automation.condition_catalog' => ['nullable', 'array'],
            'automation.action_catalog' => ['nullable', 'array'],
            'automation.detected_at' => ['nullable', 'string', 'max:80'],
            'companion' => ['nullable', 'array'],
        ]);

        $result = $ingestor->ingest($validated, $request->user());
        $discoveries = collect($result['discoveries']);

        if ($discoveries->isEmpty()) {
            return response()->json([
                'message' => 'CreditSoft could not detect a reusable automation workflow on this page.',
            ], 422);
        }

        return response()->json([
            'data' => [
                'count' => $discoveries->count(),
                'created' => $result['created'],
                'updated' => $result['updated'],
                'discoveries' => $discoveries
                    ->map(fn (AutomationDiscovery $discovery) => [
                        'id' => $discovery->getKey(),
                        'source_system' => $discovery->source_system,
                        'source_product' => $discovery->source_product,
                        'source_identifier' => $discovery->source_identifier,
                        'name' => $discovery->name,
                        'status' => $discovery->status,
                        'category' => $discovery->category,
                        'workflow_type' => $discovery->workflow_type,
                        'start_condition' => $discovery->start_condition,
                        'condition_count' => $discovery->condition_count,
                        'action_count' => $discovery->action_count,
                        'step_count' => $discovery->step_count,
                        'seen_count' => $discovery->seen_count,
                        'last_seen_at' => optional($discovery->last_seen_at)?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
            ],
        ], $result['created'] > 0 ? 201 : 200);
    }
}
