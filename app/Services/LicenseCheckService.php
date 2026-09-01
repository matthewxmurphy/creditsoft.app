<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class LicenseCheckService
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function check(string $licenseKey, array $context = []): array
    {
        $normalizedKey = Str::upper(trim($licenseKey));
        $mode = (string) config('creditsoft.installer.license_mode', 'auto');
        $graceDays = max((int) config('creditsoft.installer.license_grace_days', 7), 0);
        $remoteUrls = $this->remoteUrls();

        if (in_array($mode, ['auto', 'remote'], true) && count($remoteUrls) > 0) {
            foreach ($remoteUrls as $remoteUrl) {
                $payload = $this->remotePayload($remoteUrl, $normalizedKey, $context);

                if (is_array($payload)) {
                    return $this->remoteResult($normalizedKey, $payload, $graceDays);
                }
            }

            $fallbackMessage = $mode === 'remote'
                ? 'Remote license validation was unavailable, so the installer fell back to a format check.'
                : 'Live entitlement checks were unavailable, so the installer fell back to a format check.';

            return $this->softResult(
                $normalizedKey,
                $fallbackMessage,
                $graceDays,
                requestedMode: $mode,
                remoteUnreachable: true,
            );
        }

        return $this->softResult(
            $normalizedKey,
            graceDays: $graceDays,
            requestedMode: $mode,
            remoteUnreachable: false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function softResult(
        string $normalizedKey,
        ?string $prefixMessage = null,
        int $graceDays = 7,
        string $requestedMode = 'soft',
        bool $remoteUnreachable = false,
    ): array {
        $valid = (bool) preg_match('/^[A-Z0-9]{4,8}(?:-[A-Z0-9]{4,8}){2,5}$/', $normalizedKey);
        $message = $valid
            ? 'License format looks valid. Configure a remote endpoint later if you want live entitlement checks.'
            : 'License format did not match the expected office-key pattern.';

        return [
            'valid' => $valid,
            'status' => $valid ? 'valid' : 'invalid',
            'mode' => 'soft',
            'requested_mode' => $requestedMode,
            'message' => $prefixMessage ? "{$prefixMessage} {$message}" : $message,
            'checked_at' => now()->toIso8601String(),
            'last_verified_at' => null,
            'remote_unreachable' => $remoteUnreachable,
            'verification_fail_started_at' => $remoteUnreachable ? now()->toIso8601String() : null,
            'masked_key' => $this->mask($normalizedKey),
            'plan' => null,
            'plan_key' => null,
            'features' => [],
            'expires_at' => null,
            'grace_days' => $graceDays,
            'grace_ends_at' => null,
            'access_state' => $valid ? 'active' : 'invalid',
            'can_access_workspace' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function remotePayload(string $remoteUrl, string $normalizedKey, array $context): ?array
    {
        $requestPayload = [
            'license_key' => $normalizedKey,
            'tailscale_hostname' => $context['tailscale_hostname'] ?? null,
            'company_name' => $context['company_name'] ?? null,
            'admin_email' => $context['admin_email'] ?? null,
            'business_city' => $context['business_city'] ?? null,
            'business_state' => $context['business_state'] ?? null,
        ];

        try {
            foreach (['post', 'get'] as $method) {
                $response = Http::timeout(5)
                    ->acceptJson()
                    ->{$method}($remoteUrl, $requestPayload);

                if (is_array($response->json())) {
                    return $response->json();
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function remoteResult(string $normalizedKey, array $payload, int $graceDays): array
    {
        $status = Str::lower((string) data_get($payload, 'status', ''));
        $explicitValid = data_get($payload, 'valid');
        $valid = is_bool($explicitValid)
            ? $explicitValid
            : in_array($status, ['valid', 'active', 'licensed'], true);

        $expiresAt = $this->parseDate(
            (string) (data_get($payload, 'expires_at') ?: data_get($payload, 'expired_at') ?: ''),
        );
        $remoteGraceDays = (int) data_get($payload, 'grace_days', $graceDays);
        $resolvedGraceDays = max($remoteGraceDays, 0);
        $graceEndsAt = $this->parseDate((string) data_get($payload, 'grace_ends_at', ''))
            ?? ($expiresAt?->copy()->addDays($resolvedGraceDays));
        $accessState = $this->accessState($status, $valid, $graceEndsAt);

        return [
            'valid' => $valid,
            'status' => $status !== '' ? $status : ($valid ? 'valid' : 'invalid'),
            'mode' => 'remote',
            'requested_mode' => 'remote',
            'message' => (string) data_get(
                $payload,
                'message',
                $this->defaultRemoteMessage($accessState, $valid),
            ),
            'checked_at' => now()->toIso8601String(),
            'last_verified_at' => now()->toIso8601String(),
            'remote_unreachable' => false,
            'verification_fail_started_at' => null,
            'masked_key' => $this->mask($normalizedKey),
            'plan' => data_get($payload, 'plan')
                ?: data_get($payload, 'edition')
                ?: data_get($payload, 'tier'),
            'plan_key' => data_get($payload, 'plan_key')
                ?: data_get($payload, 'plan')
                ?: data_get($payload, 'sku'),
            'features' => $this->featurePayload($payload),
            'expires_at' => $expiresAt?->toIso8601String(),
            'grace_days' => $resolvedGraceDays,
            'grace_ends_at' => $graceEndsAt?->toIso8601String(),
            'access_state' => $accessState,
            'can_access_workspace' => $accessState !== 'locked',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function remoteUrls(): array
    {
        return collect([
            config('creditsoft.installer.license_check_api_url'),
            config('creditsoft.installer.license_check_portal_url'),
            config('creditsoft.installer.license_check_url'),
        ])
            ->filter(fn (mixed $url): bool => is_string($url) && trim($url) !== '')
            ->map(fn (string $url): string => trim($url))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function featurePayload(array $payload): mixed
    {
        foreach ([
            'features',
            'feature_flags',
            'entitlements',
            'products.creditsoft.features',
            'applications.creditsoft.features',
        ] as $path) {
            $value = data_get($payload, $path);

            if (is_array($value)) {
                return $value;
            }
        }

        return [];
    }

    private function accessState(string $status, bool $valid, ?CarbonInterface $graceEndsAt): string
    {
        if (in_array($status, ['locked', 'suspended'], true)) {
            return 'locked';
        }

        if (in_array($status, ['expired', 'grace'], true)) {
            if ($graceEndsAt instanceof CarbonInterface && now()->lt($graceEndsAt)) {
                return 'grace';
            }

            return 'locked';
        }

        if ($valid) {
            return 'active';
        }

        if ($status === 'invalid') {
            return 'invalid';
        }

        return 'pending';
    }

    private function defaultRemoteMessage(string $accessState, bool $valid): string
    {
        return match ($accessState) {
            'grace' => 'License expired, but the office is still inside the grace window.',
            'locked' => 'License grace ended. Renew the office license to restore workspace access.',
            'invalid' => 'License was not accepted.',
            default => $valid ? 'License validated.' : 'License check is pending.',
        };
    }

    private function parseDate(string $value): ?CarbonInterface
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        try {
            return Carbon::parse($trimmed);
        } catch (Throwable) {
            return null;
        }
    }

    private function mask(string $licenseKey): string
    {
        $segments = explode('-', $licenseKey);

        if (count($segments) <= 2) {
            return Str::mask($licenseKey, '*', 4);
        }

        return implode('-', [
            $segments[0],
            ...array_fill(0, max(count($segments) - 2, 0), '****'),
            $segments[count($segments) - 1],
        ]);
    }
}
