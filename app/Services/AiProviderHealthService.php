<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AiProviderHealthService
{
    /**
     * @return array{state:string,message:string,checked_at:?string}
     */
    public function status(string $provider, ?string $key, bool $refresh = false): array
    {
        $resolvedKey = is_string($key) ? trim($key) : '';

        if ($resolvedKey === '') {
            return [
                'state' => 'missing',
                'message' => 'No API key saved yet.',
                'checked_at' => null,
            ];
        }

        $cacheKey = "creditsoft:ai-provider-health:{$provider}:".sha1($resolvedKey);

        if (! $refresh && Cache::has($cacheKey)) {
            /** @var array{state:string,message:string,checked_at:?string} $cached */
            $cached = Cache::get($cacheKey);

            return $cached;
        }

        $status = $this->validate($provider, $resolvedKey);

        Cache::put($cacheKey, $status, now()->addHours(12));

        return $status;
    }

    /**
     * @return array{state:string,message:string,checked_at:?string}
     */
    protected function validate(string $provider, string $key): array
    {
        return match ($provider) {
            'openrouter_creditsoft' => $this->validateOpenRouter($key),
            'ollama_cloud' => $this->validateOllamaCloud($key),
            'opencode_zen' => $this->validateOpenCodeZen($key),
            default => [
                'state' => 'unknown',
                'message' => 'Provider validation is not configured.',
                'checked_at' => now()->toIso8601String(),
            ],
        };
    }

    /**
     * @return array{state:string,message:string,checked_at:?string}
     */
    protected function validateOpenRouter(string $key): array
    {
        $response = Http::timeout(8)
            ->acceptJson()
            ->withToken($key)
            ->get('https://openrouter.ai/api/v1/key');

        if ($response->successful()) {
            return [
                'state' => 'valid',
                'message' => 'API key verified with OpenRouter.',
                'checked_at' => now()->toIso8601String(),
            ];
        }

        if (in_array($response->status(), [401, 403], true)) {
            return [
                'state' => 'invalid',
                'message' => 'OpenRouter rejected this API key.',
                'checked_at' => now()->toIso8601String(),
            ];
        }

        return [
            'state' => 'warning',
            'message' => 'Could not verify the OpenRouter key right now.',
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{state:string,message:string,checked_at:?string}
     */
    protected function validateOllamaCloud(string $key): array
    {
        $response = Http::timeout(8)
            ->acceptJson()
            ->withToken($key)
            ->get('https://ollama.com/api/tags');

        if ($response->successful()) {
            return [
                'state' => 'valid',
                'message' => 'API key verified with Ollama Cloud.',
                'checked_at' => now()->toIso8601String(),
            ];
        }

        if (in_array($response->status(), [401, 403], true)) {
            return [
                'state' => 'invalid',
                'message' => 'Ollama Cloud rejected this API key.',
                'checked_at' => now()->toIso8601String(),
            ];
        }

        return [
            'state' => 'warning',
            'message' => 'Could not verify the Ollama Cloud key right now.',
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{state:string,message:string,checked_at:?string}
     */
    protected function validateOpenCodeZen(string $key): array
    {
        $response = Http::timeout(8)
            ->acceptJson()
            ->withToken($key)
            ->get('https://opencode.ai/zen/v1/models');

        if ($response->successful()) {
            return [
                'state' => 'valid',
                'message' => 'API key verified with OpenCode Zen.',
                'checked_at' => now()->toIso8601String(),
            ];
        }

        if (in_array($response->status(), [401, 403], true)) {
            return [
                'state' => 'invalid',
                'message' => 'OpenCode Zen rejected this API key.',
                'checked_at' => now()->toIso8601String(),
            ];
        }

        return [
            'state' => 'warning',
            'message' => 'Could not verify the OpenCode Zen key right now.',
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
