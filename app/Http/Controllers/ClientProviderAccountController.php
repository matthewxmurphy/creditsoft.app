<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientProviderAccount;
use App\Services\AuditTrail;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ClientProviderAccountController extends Controller
{
    public function store(Request $request, Client $client, AuditTrail $auditTrail): RedirectResponse
    {
        $validated = $request->validate([
            'provider_key' => ['required', 'string', 'max:80'],
            'provider_label' => ['nullable', 'string', 'max:120'],
            'login_email' => ['nullable', 'email', 'max:255'],
            'login_username' => ['nullable', 'string', 'max:255'],
            'login_password' => ['nullable', 'string', 'max:255'],
            'security_answer' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $providerKey = Str::slug((string) $validated['provider_key'], '_');
        $catalog = $this->catalog()->keyBy('key');
        $catalogEntry = $catalog->get($providerKey);
        $providerLabel = trim((string) ($validated['provider_label'] ?? '')) ?: ($catalogEntry['label'] ?? Str::headline(str_replace('_', ' ', $providerKey)));

        $provider = $client->providerAccounts()->firstOrNew([
            'provider_key' => $providerKey,
        ]);

        if ($providerKey === 'identityiq' && blank($validated['security_answer'] ?? null) && ! $provider->hasStoredSecurityAnswer()) {
            return back()
                ->withErrors([
                    'security_answer' => 'IdentityIQ needs the saved security answer before companion sign-in can work. We do not need the question text, just the answer they actually use.',
                ])
                ->withInput();
        }

        $isNew = ! $provider->exists;
        $previous = Arr::only($provider->toArray(), [
            'provider_label',
            'login_email',
            'login_username',
            'status',
            'notes',
        ]);
        $previous['had_security_answer'] = $provider->hasStoredSecurityAnswer();
        $previousLoginIdentifier = trim((string) ($provider->login_email ?: $provider->login_username));
        $nextLoginIdentifier = trim((string) (($validated['login_email'] ?? '') ?: ($validated['login_username'] ?? '')));
        $loginChanged = $nextLoginIdentifier !== '' && Str::lower($nextLoginIdentifier) !== Str::lower($previousLoginIdentifier);
        $passwordSaved = filled($validated['login_password'] ?? null);
        $securityAnswerSaved = filled($validated['security_answer'] ?? null);
        $credentialChanged = $loginChanged || $passwordSaved || $securityAnswerSaved;
        $status = (string) $validated['status'];
        $metadata = $provider->metadata ?? [];

        if ($credentialChanged) {
            $metadata = $this->appendProviderCredentialHistory($metadata, 'credentials_saved', [
                'source' => 'manual',
                'actor_user_id' => $request->user()?->getKey(),
                'login_changed' => $loginChanged,
                'login_saved' => $nextLoginIdentifier !== '',
                'password_saved' => $passwordSaved,
                'security_answer_saved' => $securityAnswerSaved,
            ]);
            data_set($metadata, 'companion.credentials.invalid', null);
            data_set($metadata, 'smartcredit.invalid_credentials', null);

            if (in_array((string) $provider->status, ['needs_credentials', 'blocked', 'disconnected'], true) && $status === (string) $provider->status) {
                $status = 'import_only';
            }
        }

        $provider->fill([
            'provider_label' => $providerLabel,
            'login_email' => blank($validated['login_email'] ?? null) ? null : Str::lower((string) $validated['login_email']),
            'login_username' => blank($validated['login_username'] ?? null) ? null : (string) $validated['login_username'],
            'status' => $status,
            'notes' => blank($validated['notes'] ?? null) ? null : (string) $validated['notes'],
            'metadata' => $metadata,
        ]);

        if (filled($validated['login_password'] ?? null)) {
            $provider->login_password = (string) $validated['login_password'];
        }

        if (filled($validated['security_answer'] ?? null)) {
            $provider->security_answer = (string) $validated['security_answer'];
        }

        $provider->save();

        $syncedClientEmail = false;

        if (in_array($provider->provider_key, ['smartcredit', 'credit_karma'], true) && filled($provider->login_email)) {
            $normalizedProviderEmail = Str::lower((string) $provider->login_email);
            $previousLoginEmail = Str::lower((string) ($previous['login_email'] ?? ''));
            $currentClientEmail = Str::lower((string) ($client->email ?? ''));

            $shouldSyncClientEmail = blank($client->email)
                || ($previousLoginEmail !== '' && $currentClientEmail === $previousLoginEmail);

            if ($shouldSyncClientEmail && $currentClientEmail !== $normalizedProviderEmail) {
                $client->forceFill([
                    'email' => $normalizedProviderEmail,
                ])->save();

                $syncedClientEmail = true;
            }
        }

        $auditTrail->record(
            $request->user(),
            $isNew ? 'client.provider.created' : 'client.provider.updated',
            ($isNew ? 'Added' : 'Updated')." {$provider->provider_label} for {$client->display_name}.",
            $provider,
            [
                'client_id' => $client->getKey(),
                'provider_key' => $provider->provider_key,
                'before' => $previous,
                'after' => Arr::only($provider->fresh()->toArray(), [
                    'provider_label',
                    'login_email',
                    'login_username',
                    'status',
                    'notes',
                ]),
                'password_saved' => $passwordSaved,
                'security_answer_saved' => $securityAnswerSaved,
                'has_security_answer' => $provider->fresh()->hasStoredSecurityAnswer(),
                'synced_client_email' => $syncedClientEmail,
            ],
        );

        return redirect()->route('clients.show', $client);
    }

    public function credentials(Request $request, Client $client, ClientProviderAccount $providerAccount, AuditTrail $auditTrail): JsonResponse
    {
        abort_unless((int) $providerAccount->client_id === (int) $client->getKey(), 404);

        $user = $request->user();
        abort_unless($user && $user->canEditUsers(), 403);

        try {
            $password = $providerAccount->login_password;
            $securityAnswer = $providerAccount->security_answer;
        } catch (DecryptException) {
            return response()->json([
                'message' => 'Saved provider credentials could not be decrypted with this office APP_KEY.',
            ], 422);
        }

        $auditTrail->record(
            $user,
            'client.provider.credentials_revealed',
            "Revealed {$providerAccount->provider_label} credentials for {$client->display_name}.",
            $providerAccount,
            [
                'client_id' => $client->getKey(),
                'provider_key' => $providerAccount->provider_key,
                'provider_label' => $providerAccount->provider_label,
                'password_revealed' => filled($password),
                'security_answer_revealed' => filled($securityAnswer),
            ],
        );

        return response()->json([
            'data' => [
                'login_password' => $password,
                'security_answer' => $securityAnswer,
                'has_stored_password' => filled($password),
                'has_stored_security_answer' => filled($securityAnswer),
            ],
        ]);
    }

    public function destroy(Request $request, Client $client, ClientProviderAccount $providerAccount, AuditTrail $auditTrail): RedirectResponse
    {
        abort_unless((int) $providerAccount->client_id === (int) $client->getKey(), 404);

        $label = $providerAccount->provider_label;
        $providerKey = $providerAccount->provider_key;
        $providerAccount->delete();

        $auditTrail->record(
            $request->user(),
            'client.provider.deleted',
            "Removed {$label} from {$client->display_name}.",
            $client,
            [
                'provider_key' => $providerKey,
                'provider_label' => $label,
            ],
        );

        return redirect()->route('clients.show', $client);
    }

    protected function catalog(): Collection
    {
        return collect(config('creditsoft.client_providers.catalog', []));
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    protected function appendProviderCredentialHistory(array $metadata, string $event, array $details = []): array
    {
        $occurredAt = now()->toIso8601String();
        $safeDetails = Arr::except($details, ['password', 'login_password', 'security_answer']);
        $entry = [
            'event' => $event,
            'occurred_at' => $occurredAt,
            ...array_filter($safeDetails, fn ($value) => $value !== null && $value !== ''),
        ];
        $history = data_get($metadata, 'credentials.history', []);

        if (! is_array($history)) {
            $history = [];
        }

        $history[] = $entry;

        data_set($metadata, 'credentials.last_event', $entry);
        data_set($metadata, 'credentials.history', array_slice($history, -25));
        data_set($metadata, 'credentials.last_updated_at', $occurredAt);
        data_set($metadata, 'credentials.last_source', $entry['source'] ?? null);

        if (! empty($entry['login_saved']) || ! empty($entry['login_changed'])) {
            data_set($metadata, 'credentials.login_updated_at', $occurredAt);
        }

        if (! empty($entry['password_saved'])) {
            data_set($metadata, 'credentials.password_updated_at', $occurredAt);
        }

        if (! empty($entry['security_answer_saved'])) {
            data_set($metadata, 'credentials.security_answer_updated_at', $occurredAt);
        }

        return $metadata;
    }
}
