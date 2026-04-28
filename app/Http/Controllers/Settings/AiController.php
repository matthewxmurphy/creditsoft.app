<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\AiProviderHealthService;
use App\Services\CreditsoftAiRegistry;
use App\Services\EnvironmentEditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiController extends Controller
{
    public function edit(CreditsoftAiRegistry $registry): Response
    {
        $catalog = $registry->catalog();

        return Inertia::render('settings/Ai', [
            'catalog' => $catalog,
            'defaultProvider' => config('ai.default'),
        ]);
    }

    public function update(
        Request $request,
        EnvironmentEditor $editor,
        AiProviderHealthService $healthService,
    ): RedirectResponse
    {
        $validated = $request->validate([
            'default_provider' => ['required', 'in:opencode_zen,openrouter_creditsoft,ollama_cloud'],
            'opencode_api_key' => ['nullable', 'string', 'max:500'],
            'openrouter_api_key' => ['nullable', 'string', 'max:500'],
            'ollama_cloud_api_key' => ['nullable', 'string', 'max:500'],
            'redirect_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $variables = [
            'CREDITSOFT_AI_DEFAULT_PROVIDER' => $validated['default_provider'],
        ];

        if (filled($validated['opencode_api_key'] ?? null)) {
            $variables['OPENCODE_API_KEY'] = $validated['opencode_api_key'];
        }

        if (filled($validated['openrouter_api_key'] ?? null)) {
            $variables['OPENROUTER_API_KEY'] = $validated['openrouter_api_key'];
        }

        if (filled($validated['ollama_cloud_api_key'] ?? null)) {
            $variables['OLLAMA_CLOUD_API_KEY'] = $validated['ollama_cloud_api_key'];
        }

        $editor->setMany($variables);

        $managedVariables = $editor->readManagedVariables();

        foreach ([
            'opencode_zen' => $managedVariables['OPENCODE_API_KEY'] ?? null,
            'openrouter_creditsoft' => $managedVariables['OPENROUTER_API_KEY'] ?? null,
            'ollama_cloud' => $managedVariables['OLLAMA_CLOUD_API_KEY'] ?? null,
        ] as $provider => $key) {
            $healthService->status($provider, is_string($key) ? $key : null, refresh: true);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'AI provider settings updated and rechecked.',
        ]);

        return filled($validated['redirect_to'] ?? null)
            ? redirect()->to((string) $validated['redirect_to'])
            : back();
    }
}
