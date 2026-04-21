<?php

namespace App\Services;

class CtoAdvisorActionService
{
    public function __construct(
        protected EnvironmentEditor $environmentEditor,
        protected InstallerState $installerState,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function applyLocal(string $action, array $payload = []): array
    {
        return match ($action) {
            'memory_saver_profile' => $this->applyMemorySaverProfile($payload),
            'prefer_healthy_node' => $this->applyRouterPreference($payload),
            'ram_action_note' => $this->recordRamAction($payload),
            default => [
                'ok' => false,
                'message' => 'Unknown CTO advisor action.',
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function applyMemorySaverProfile(array $payload): array
    {
        $targetLabel = $this->string($payload['target_label'] ?? null) ?: 'this office node';
        $settings = [
            'PHP_OPCACHE_MEMORY_CONSUMPTION' => '96',
            'PHP_OPCACHE_MAX_ACCELERATED_FILES' => '12000',
            'OFFICE_PG_SHARED_BUFFERS' => '256MB',
            'OFFICE_PG_EFFECTIVE_CACHE_SIZE' => '1GB',
            'OFFICE_PG_WORK_MEM' => '4MB',
            'OFFICE_PG_MAINTENANCE_WORK_MEM' => '64MB',
            'CRM_DISABLE_CRON_JOBS_REGISTRATION' => 'true',
        ];

        $this->environmentEditor->setMany($settings);
        $this->installerState->merge([
            'cto_actions' => [
                'memory_saver_profile' => [
                    'target_label' => $targetLabel,
                    'settings' => $settings,
                    'applied_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        return [
            'ok' => true,
            'action' => 'memory_saver_profile',
            'title' => 'Memory-saver profile staged',
            'message' => 'Wrote conservative PHP OPcache and PostgreSQL memory limits for '.$targetLabel.'. Restart the Docker office services for the full effect.',
            'requires_restart' => true,
            'target_label' => $targetLabel,
            'settings' => $settings,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function applyRouterPreference(array $payload): array
    {
        $preferredLabel = $this->string($payload['preferred_label'] ?? null) ?: 'healthiest node';
        $preferredBaseUrl = $this->string($payload['preferred_base_url'] ?? null);
        $preferredApiBaseUrl = $this->apiBaseUrl($this->string($payload['preferred_api_base_url'] ?? null) ?: $preferredBaseUrl);

        if ($preferredApiBaseUrl === '') {
            return [
                'ok' => false,
                'action' => 'prefer_healthy_node',
                'message' => 'No reachable node URL was available for the router preference.',
            ];
        }

        $this->environmentEditor->setMany([
            'CREDITSOFT_ROUTER_SELECTION_STRATEGY' => 'resource-aware',
            'CREDITSOFT_ROUTER_PREFERRED_LABEL' => $preferredLabel,
            'CREDITSOFT_ROUTER_PREFERRED_BASE_URL' => $preferredApiBaseUrl,
        ]);
        $this->installerState->merge([
            'router' => [
                'selection_strategy' => 'resource-aware',
                'preferred_node_label' => $preferredLabel,
                'preferred_base_url' => $preferredBaseUrl,
                'preferred_api_base_url' => $preferredApiBaseUrl,
                'updated_at' => now()->toIso8601String(),
                'reason' => 'cto_advisor_action',
            ],
        ]);

        return [
            'ok' => true,
            'action' => 'prefer_healthy_node',
            'title' => 'Router preference saved',
            'message' => 'New client/router probes will prefer '.$preferredLabel.' unless that node is offline or unauthenticated.',
            'requires_restart' => false,
            'preferred_label' => $preferredLabel,
            'preferred_api_base_url' => $preferredApiBaseUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function recordRamAction(array $payload): array
    {
        $targetLabel = $this->string($payload['target_label'] ?? null) ?: 'M4';

        $this->installerState->merge([
            'cto_actions' => [
                'ram_upgrade' => [
                    'target_label' => $targetLabel,
                    'status' => 'open',
                    'recommended_at' => now()->toIso8601String(),
                    'reason' => 'High memory and swap pressure reported by CTO advisor.',
                ],
            ],
        ]);

        return [
            'ok' => true,
            'action' => 'ram_action_note',
            'title' => 'RAM action recorded',
            'message' => $targetLabel.' is flagged for a RAM check or app cleanup review. This is a hardware/admin task, so CreditSoft records it instead of pretending to install memory.',
            'requires_restart' => false,
            'target_label' => $targetLabel,
        ];
    }

    protected function apiBaseUrl(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $parts = parse_url($value);

        if (! is_array($parts) || blank($parts['scheme'] ?? null) || blank($parts['host'] ?? null)) {
            return '';
        }

        $scheme = strtolower((string) $parts['scheme']);
        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = rtrim((string) ($parts['path'] ?? ''), '/');

        if ($path === '' || $path === '/') {
            $path = '/api/v1';
        } elseif ($path === '/api') {
            $path = '/api/v1';
        } elseif (! str_ends_with($path, '/api/v1')) {
            $path .= '/api/v1';
        }

        return "{$scheme}://{$host}{$port}{$path}";
    }

    protected function string(mixed $value): string
    {
        return trim((string) $value);
    }
}
