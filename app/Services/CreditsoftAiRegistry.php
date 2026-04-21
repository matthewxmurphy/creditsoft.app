<?php

namespace App\Services;

use App\Creditsoft\Config\YamlConfigLoader;
use Illuminate\Support\Arr;
use RuntimeException;

class CreditsoftAiRegistry
{
    public function __construct(
        protected YamlConfigLoader $loader,
        protected AiProviderHealthService $healthService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function catalog(bool $withValidation = true): array
    {
        $config = $this->configuration();
        $providers = Arr::get($config, 'providers', []);
        $models = Arr::get($config, 'models', []);

        return [
            'default_provider' => config('ai.default', Arr::get($config, 'default_provider')),
            'providers' => collect($providers)
                ->map(fn (array $provider, string $name) => $this->providerSummary($name, $provider, $withValidation))
                ->values()
                ->all(),
            'tasks' => collect($models)
                ->map(fn (array $task, string $key) => $this->taskSummary($key, $task))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function task(string $key): array
    {
        $task = Arr::get($this->configuration(), "models.{$key}");

        if (! is_array($task) || $task === []) {
            throw new RuntimeException("Creditsoft AI task [{$key}] is not defined.");
        }

        return $task;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function providerChain(string $task): array
    {
        $taskConfig = $this->task($task);
        $chain = collect([
            [
                'provider' => $taskConfig['provider'],
                'model' => $taskConfig['model'],
            ],
            ...Arr::get($taskConfig, 'fallbacks', []),
        ])->filter(fn ($item) => filled($item['provider'] ?? null) && filled($item['model'] ?? null))
            ->values();

        return $chain
            ->mapWithKeys(function (array $item): array {
                $provider = (string) $item['provider'];

                return [$provider => [
                    'provider' => $provider,
                    'model' => (string) $item['model'],
                    'definition' => $this->providerDefinition($provider),
                ]];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function providerDefinition(string $provider): array
    {
        $yaml = Arr::get($this->configuration(), "providers.{$provider}", []);
        $config = config("ai.providers.{$provider}");

        if (! is_array($config)) {
            $config = config("creditsoft.ai.providers.{$provider}", []);
        }

        if (! is_array($config) || $config === []) {
            throw new RuntimeException("Creditsoft AI provider [{$provider}] is not configured.");
        }

        return array_replace_recursive($yaml, $config, [
            'name' => $provider,
            'driver' => $config['driver'] ?? Arr::get($yaml, 'transport', 'laravel_ai'),
        ]);
    }

    public function providerIsConfigured(string $provider): bool
    {
        try {
            $definition = $this->providerDefinition($provider);
        } catch (RuntimeException) {
            return false;
        }

        if (($definition['driver'] ?? null) === 'ollama') {
            return filled($definition['key'] ?? null);
        }

        return filled($definition['key'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    protected function configuration(): array
    {
        /** @var array<string, mixed> $config */
        $config = $this->loader->load()['ai_models.yaml'] ?? [];

        return $config;
    }

    /**
     * @param  array<string, mixed>  $provider
     * @return array<string, mixed>
     */
    protected function providerSummary(string $name, array $provider, bool $withValidation = true): array
    {
        $definition = [];
        $key = null;

        try {
            $definition = $this->providerDefinition($name);
            $key = $definition['key'] ?? null;
        } catch (RuntimeException) {
            // Surface YAML-level metadata even when env secrets are missing.
        }

        return [
            'name' => $name,
            'label' => $provider['label'] ?? $name,
            'scope' => $provider['scope'] ?? 'remote',
            'transport' => $provider['transport'] ?? ($definition['driver'] ?? 'laravel_ai'),
            'purpose' => $provider['purpose'] ?? null,
            'configured' => $this->providerIsConfigured($name),
            'masked_key' => $this->maskedKey($key),
            'validation' => $withValidation
                ? $this->healthService->status($name, is_string($key) ? $key : null)
                : [
                    'state' => 'deferred',
                    'message' => 'Live validation loads on the AI settings screen.',
                    'checked_at' => null,
                ],
            'driver' => $definition['driver'] ?? null,
            'chat_model' => $this->chatModel($definition),
        ];
    }

    protected function chatModel(array $definition): ?string
    {
        $model = Arr::get($definition, 'models.text.smartest')
            ?? Arr::get($definition, 'models.text.default');

        return is_string($model) && $model !== '' ? $model : null;
    }

    protected function maskedKey(mixed $key): ?string
    {
        if (! is_string($key) || trim($key) === '') {
            return null;
        }

        if (strlen($key) < 8) {
            return '********';
        }

        return '********'.substr($key, -4);
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array<string, mixed>
     */
    protected function taskSummary(string $key, array $task): array
    {
        $chain = $this->providerChain($key);
        $primary = reset($chain) ?: null;

        return [
            'key' => $key,
            'label' => $task['label'] ?? $key,
            'provider' => $task['provider'] ?? null,
            'model' => $task['model'] ?? null,
            'configured' => $primary ? $this->providerIsConfigured((string) $primary['provider']) : false,
            'fallbacks' => collect(Arr::get($task, 'fallbacks', []))
                ->map(fn (array $fallback) => [
                    'provider' => $fallback['provider'] ?? null,
                    'model' => $fallback['model'] ?? null,
                    'configured' => filled($fallback['provider'] ?? null)
                        ? $this->providerIsConfigured((string) $fallback['provider'])
                        : false,
                ])->values()->all(),
        ];
    }
}
