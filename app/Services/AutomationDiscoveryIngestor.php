<?php

namespace App\Services;

use App\Models\AutomationDiscovery;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AutomationDiscoveryIngestor
{
    public function __construct(
        protected AuditTrail $auditTrail,
    ) {
    }

    /**
     * @return array{discoveries: array<int, AutomationDiscovery>, created: int, updated: int}
     */
    public function ingest(array $payload, ?User $user = null): array
    {
        $automation = Arr::get($payload, 'automation', []);

        if (! is_array($automation)) {
            return ['discoveries' => [], 'created' => 0, 'updated' => 0];
        }

        $sourceSystem = $this->sourceKey(
            Arr::get($payload, 'source_system')
                ?: Arr::get($automation, 'source_system')
                ?: 'external'
        );
        $sourceProduct = $this->shortText(
            Arr::get($automation, 'source_product')
                ?: Arr::get($automation, 'product')
                ?: 'Automation',
            120
        );
        $pageKind = $this->sourceKey(Arr::get($automation, 'page_kind') ?: 'automation-workflow');
        $pageTitle = $this->shortText(Arr::get($payload, 'page_title'), 255);
        $pageUrl = $this->shortText(Arr::get($payload, 'page_url'), 2048);
        $workerId = $this->shortText(Arr::get($payload, 'worker_id'), 120);
        $workflows = $this->workflowPayloads($automation);
        $discoveries = [];
        $createdCount = 0;
        $updatedCount = 0;

        foreach ($workflows as $workflow) {
            $normalized = $this->normalizeWorkflow(
                workflow: $workflow,
                automation: $automation,
                sourceSystem: $sourceSystem,
                sourceProduct: $sourceProduct,
                pageKind: $pageKind,
                pageTitle: $pageTitle,
                pageUrl: $pageUrl,
                workerId: $workerId,
            );

            if ($normalized['name'] === '' && $normalized['source_identifier'] === '') {
                continue;
            }

            $discovery = AutomationDiscovery::query()->firstOrNew([
                'source_signature' => $normalized['source_signature'],
            ]);
            $created = ! $discovery->exists;
            $now = now();

            $discovery->fill([
                'last_seen_by_user_id' => $user?->getKey(),
                'source_system' => $normalized['source_system'],
                'source_product' => $normalized['source_product'],
                'page_kind' => $normalized['page_kind'],
                'source_identifier' => $normalized['source_identifier'] ?: null,
                'name' => $normalized['name'] ?: null,
                'status' => $normalized['status'] ?: null,
                'category' => $normalized['category'] ?: null,
                'workflow_type' => $normalized['workflow_type'] ?: null,
                'start_condition' => $normalized['start_condition'] ?: null,
                'condition_count' => count($normalized['payload']['condition_catalog']),
                'action_count' => count($normalized['payload']['action_catalog']),
                'step_count' => count($normalized['payload']['steps']),
                'seen_count' => $created ? 1 : ((int) $discovery->seen_count + 1),
                'first_seen_at' => $created ? $now : ($discovery->first_seen_at ?: $now),
                'last_seen_at' => $now,
                'payload' => $normalized['payload'],
            ]);
            $discovery->save();

            $discoveries[] = $discovery;
            $created ? $createdCount++ : $updatedCount++;

            $this->auditTrail->record(
                $user,
                $created ? 'automation.discovery.created' : 'automation.discovery.updated',
                sprintf(
                    '%s automation discovery %s from %s.',
                    $created ? 'Created' : 'Updated',
                    $discovery->name ?: $discovery->source_identifier ?: $discovery->source_signature,
                    $discovery->source_product ?: $discovery->source_system,
                ),
                $discovery,
                [
                    'source' => 'browser_companion',
                    'source_system' => $discovery->source_system,
                    'source_product' => $discovery->source_product,
                    'source_identifier' => $discovery->source_identifier,
                    'page_kind' => $discovery->page_kind,
                    'page_url' => $pageUrl,
                    'worker_id' => $workerId,
                    'created' => $created,
                ],
            );
        }

        return [
            'discoveries' => $discoveries,
            'created' => $createdCount,
            'updated' => $updatedCount,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function workflowPayloads(array $automation): array
    {
        $workflows = Arr::get($automation, 'workflows');

        if (is_array($workflows) && $workflows !== []) {
            return array_values(array_filter($workflows, 'is_array'));
        }

        $workflow = Arr::get($automation, 'workflow');

        if (is_array($workflow) && $workflow !== []) {
            return [$workflow];
        }

        return [$automation];
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeWorkflow(
        array $workflow,
        array $automation,
        string $sourceSystem,
        string $sourceProduct,
        string $pageKind,
        string $pageTitle,
        string $pageUrl,
        string $workerId,
    ): array {
        $conditions = $this->catalog(
            Arr::get($workflow, 'condition_catalog')
                ?: Arr::get($automation, 'condition_catalog')
                ?: [],
            140
        );
        $actions = $this->catalog(
            Arr::get($workflow, 'action_catalog')
                ?: Arr::get($automation, 'action_catalog')
                ?: Arr::get($workflow, 'actions')
                ?: [],
            80
        );
        $steps = $this->steps(Arr::get($workflow, 'steps') ?: [], 100);
        $name = $this->shortText(
            Arr::get($workflow, 'name')
                ?: Arr::get($workflow, 'workflow_name')
                ?: Arr::get($automation, 'workflow_name'),
            255
        );
        $sourceIdentifier = $this->shortText(
            Arr::get($workflow, 'source_identifier')
                ?: Arr::get($workflow, 'workflow_id')
                ?: Arr::get($workflow, 'autofox_id')
                ?: Arr::get($automation, 'workflow_id')
                ?: Arr::get($automation, 'autofox_id'),
            255
        );
        $status = $this->shortText(Arr::get($workflow, 'status') ?: Arr::get($automation, 'status'), 80);
        $category = $this->shortText(Arr::get($workflow, 'category') ?: Arr::get($automation, 'category'), 120);
        $workflowType = $this->shortText(Arr::get($workflow, 'workflow_type') ?: Arr::get($automation, 'workflow_type'), 120);
        $startCondition = $this->shortText(Arr::get($workflow, 'start_condition') ?: Arr::get($automation, 'start_condition'), 120);
        $payload = [
            'name' => $name,
            'source_identifier' => $sourceIdentifier,
            'page_title' => $pageTitle,
            'page_url' => $pageUrl,
            'worker_id' => $workerId,
            'status' => $status,
            'category' => $category,
            'workflow_type' => $workflowType,
            'start_condition' => $startCondition,
            'created_label' => $this->shortText(Arr::get($workflow, 'created_label'), 120),
            'condition_catalog' => $conditions,
            'action_catalog' => $actions,
            'steps' => $steps,
            'detected_at' => $this->shortText(Arr::get($automation, 'detected_at'), 80),
            'source_raw' => [
                'page_kind' => $pageKind,
                'source_product' => $sourceProduct,
                'label_sample' => $this->strings(Arr::get($workflow, 'label_sample') ?: Arr::get($automation, 'label_sample') ?: [], 40, 140),
            ],
        ];
        $signature = hash('sha256', json_encode([
            $sourceSystem,
            $sourceProduct,
            $sourceIdentifier,
            Str::of($name)->lower()->value(),
            $workflowType,
            $startCondition,
            collect($conditions)->pluck('key')->filter()->values()->all(),
            collect($actions)->pluck('key')->filter()->values()->all(),
            collect($steps)->pluck('title')->filter()->values()->all(),
        ], JSON_UNESCAPED_SLASHES));

        return [
            'source_system' => $sourceSystem,
            'source_product' => $sourceProduct,
            'page_kind' => $pageKind,
            'source_identifier' => $sourceIdentifier,
            'source_signature' => $signature,
            'name' => $name,
            'status' => $status,
            'category' => $category,
            'workflow_type' => $workflowType,
            'start_condition' => $startCondition,
            'payload' => $payload,
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, source: string}>
     */
    protected function catalog(mixed $items, int $limit): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(function ($item): ?array {
                if (is_string($item)) {
                    $label = $this->shortText($item, 140);

                    return $label !== '' ? [
                        'key' => $this->sourceKey($label),
                        'label' => $label,
                        'source' => 'text',
                    ] : null;
                }

                if (! is_array($item)) {
                    return null;
                }

                $label = $this->shortText(Arr::get($item, 'label') ?: Arr::get($item, 'name') ?: Arr::get($item, 'title'), 140);
                $key = $this->sourceKey(Arr::get($item, 'key') ?: Arr::get($item, 'value') ?: $label);

                if ($label === '' && $key === '') {
                    return null;
                }

                return [
                    'key' => $key,
                    'label' => $label ?: $key,
                    'source' => $this->shortText(Arr::get($item, 'source'), 80) ?: 'page',
                ];
            })
            ->filter()
            ->unique('key')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function steps(mixed $items, int $limit): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(function ($item): ?array {
                if (is_string($item)) {
                    $title = $this->shortText($item, 160);

                    return $title !== '' ? [
                        'title' => $title,
                        'timing' => '',
                        'status' => '',
                        'actions' => [],
                    ] : null;
                }

                if (! is_array($item)) {
                    return null;
                }

                $title = $this->shortText(Arr::get($item, 'title') ?: Arr::get($item, 'name'), 160);

                if ($title === '') {
                    return null;
                }

                return [
                    'title' => $title,
                    'timing' => $this->shortText(Arr::get($item, 'timing'), 120),
                    'status' => $this->shortText(Arr::get($item, 'status'), 80),
                    'actions' => $this->strings(Arr::get($item, 'actions') ?: [], 30, 120),
                ];
            })
            ->filter()
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function strings(mixed $items, int $limit, int $textLimit): array
    {
        if (! is_array($items)) {
            return [];
        }

        return Collection::make($items)
            ->map(fn ($value) => is_array($value)
                ? $this->shortText(Arr::get($value, 'label') ?: Arr::get($value, 'title') ?: Arr::get($value, 'name'), $textLimit)
                : $this->shortText($value, $textLimit))
            ->filter()
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }

    protected function sourceKey(mixed $value): string
    {
        return Str::of((string) $value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->limit(120, '')
            ->value();
    }

    protected function shortText(mixed $value, int $limit): string
    {
        $text = trim((string) $value);
        $text = preg_replace('/\s+/', ' ', $text) ?: '';

        return Str::limit($text, $limit, '');
    }
}
