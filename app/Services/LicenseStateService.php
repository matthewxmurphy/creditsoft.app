<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class LicenseStateService
{
    public function __construct(
        protected InstallerState $installerState,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $state = $this->installerState->read();
        $license = array_replace([
            'valid' => false,
            'status' => 'pending',
            'mode' => config('creditsoft.installer.license_mode', 'auto'),
            'requested_mode' => config('creditsoft.installer.license_mode', 'auto'),
            'message' => 'License has not been checked yet.',
            'checked_at' => null,
            'last_verified_at' => null,
            'remote_unreachable' => false,
            'verification_fail_started_at' => null,
            'masked_key' => null,
            'plan' => null,
            'plan_key' => null,
            'features' => [],
            'expires_at' => null,
            'expired_at' => null,
            'grace_days' => (int) config('creditsoft.installer.license_grace_days', 7),
            'grace_ends_at' => null,
            'access_state' => null,
            'can_access_workspace' => true,
        ], (array) data_get($state, 'license', []));

        $expiresAt = $this->parseDate((string) ($license['expires_at'] ?? $license['expired_at'] ?? ''));
        $graceDays = max((int) ($license['grace_days'] ?? config('creditsoft.installer.license_grace_days', 7)), 0);
        $graceEndsAt = $this->parseDate((string) ($license['grace_ends_at'] ?? ''))
            ?? ($expiresAt ? $expiresAt->copy()->addDays($graceDays) : null);
        $now = now();
        $planKey = $this->normalizePlanKey((string) ($license['plan_key'] ?? $license['plan'] ?? ''));
        $planLabel = $this->planLabel($planKey, (string) ($license['plan'] ?? ''));
        $features = $this->resolveFeatures($license['features'] ?? [], $planKey);
        $requestedMode = (string) ($license['requested_mode'] ?? $license['mode'] ?? config('creditsoft.installer.license_mode', 'auto'));
        $remoteUnreachable = (bool) ($license['remote_unreachable'] ?? false);
        $lastVerifiedAt = $this->parseDate((string) ($license['last_verified_at'] ?? ''));
        $verificationFailStartedAt = $this->parseDate((string) ($license['verification_fail_started_at'] ?? ''));
        $verificationWindowDays = max((int) config('creditsoft.installer.verification_window_days', 7), 1);
        $verificationAnchor = $verificationFailStartedAt ?? $lastVerifiedAt;
        $verificationExpired = $remoteUnreachable
            && in_array($requestedMode, ['auto', 'remote'], true)
            && $verificationAnchor instanceof CarbonInterface
            && $verificationAnchor->copy()->addDays($verificationWindowDays)->lte($now);
        $verificationDeadline = $verificationAnchor?->copy()->addDays($verificationWindowDays);

        $status = (string) ($license['status'] ?? 'pending');
        $accessState = (string) ($license['access_state'] ?? '');

        if ($accessState === '') {
            if (in_array($status, ['locked', 'suspended'], true)) {
                $accessState = 'locked';
            } elseif (in_array($status, ['expired', 'grace'], true) && $graceEndsAt instanceof CarbonInterface) {
                $accessState = $now->lt($graceEndsAt) ? 'grace' : 'locked';
            } elseif (in_array($status, ['expired', 'grace'], true)) {
                $accessState = 'locked';
            } elseif (($license['valid'] ?? false) === true) {
                $accessState = 'active';
            } elseif ($status === 'invalid') {
                $accessState = 'invalid';
            } else {
                $accessState = 'pending';
            }
        }

        if ($verificationExpired) {
            $accessState = 'locked';
        }

        $secondsRemaining = $graceEndsAt instanceof CarbonInterface && $accessState === 'grace'
            ? max(0, $now->diffInSeconds($graceEndsAt, false))
            : 0;

        $message = (string) ($license['message'] ?? 'License has not been checked yet.');

        if ($verificationExpired) {
            $deadlineLabel = $verificationDeadline?->toFormattedDateString();
            $message = $deadlineLabel
                ? "CreditSoft could not verify this license for more than {$verificationWindowDays} days. Reconnect and renew from the license page. Verification window ended {$deadlineLabel}."
                : "CreditSoft could not verify this license for more than {$verificationWindowDays} days. Reconnect and renew from the license page.";
        }

        return [
            ...$license,
            'message' => $message,
            'plan' => $license['plan'] ?: $planLabel,
            'plan_key' => $planKey,
            'plan_label' => $planLabel,
            'features' => $features,
            'requested_mode' => $requestedMode,
            'remote_unreachable' => $remoteUnreachable,
            'last_verified_at' => $lastVerifiedAt?->toIso8601String(),
            'last_verified_label' => $lastVerifiedAt?->toFormattedDateString(),
            'verification_fail_started_at' => $verificationFailStartedAt?->toIso8601String(),
            'verification_fail_started_label' => $verificationFailStartedAt?->toFormattedDateString(),
            'verification_window_days' => $verificationWindowDays,
            'verification_window_ends_at' => $verificationDeadline?->toIso8601String(),
            'verification_window_ends_label' => $verificationDeadline?->toFormattedDateString(),
            'verification_expired' => $verificationExpired,
            'expires_at' => $expiresAt?->toIso8601String(),
            'expires_label' => $expiresAt?->toFormattedDateString(),
            'grace_days' => $graceDays,
            'grace_ends_at' => $graceEndsAt?->toIso8601String(),
            'grace_ends_label' => $graceEndsAt?->toFormattedDateString(),
            'access_state' => $accessState,
            'can_access_workspace' => $accessState !== 'locked',
            'warning_active' => in_array($accessState, ['grace', 'locked'], true),
            'seconds_remaining' => $secondsRemaining,
            'countdown_label' => $this->countdownLabel($accessState, $graceEndsAt),
            'rail_message' => $this->railMessage($accessState, $graceEndsAt),
        ];
    }

    public function isLocked(): bool
    {
        return (string) $this->current()['access_state'] === 'locked';
    }

    public function allows(string $feature): bool
    {
        $state = $this->current();

        if (($state['access_state'] ?? null) === 'locked') {
            return false;
        }

        return (bool) data_get($state, "features.{$feature}", false);
    }

    public function featureUnavailableMessage(string $feature): string
    {
        $state = $this->current();

        if (($state['access_state'] ?? null) === 'locked') {
            return 'License grace ended. Renew the office license to restore workspace access.';
        }

        $label = (string) data_get(
            config('creditsoft.licensing.features', []),
            "{$feature}.label",
            Str::of($feature)->replace('_', ' ')->title()->value(),
        );
        $recommendedPlan = $this->recommendedPlanLabel($feature);

        return $recommendedPlan !== null
            ? "{$label} is not included in this license. Upgrade to {$recommendedPlan} to use it."
            : "{$label} is not included in this license.";
    }

    protected function countdownLabel(string $accessState, ?CarbonInterface $graceEndsAt): ?string
    {
        if (! in_array($accessState, ['grace', 'locked'], true) || ! $graceEndsAt) {
            return null;
        }

        if ($accessState === 'locked') {
            return 'grace ended';
        }

        $now = now();
        $days = max(0, $now->diffInDays($graceEndsAt, false));
        $hours = max(0, $now->diffInHours($graceEndsAt, false) % 24);

        if ($days > 0) {
            return $days.'d '.$hours.'h left';
        }

        $minutes = max(0, $now->diffInMinutes($graceEndsAt, false) % 60);

        return $hours.'h '.$minutes.'m left';
    }

    protected function railMessage(string $accessState, ?CarbonInterface $graceEndsAt): ?string
    {
        return match ($accessState) {
            'grace' => 'License expired. Renew before lockout.',
            'locked' => 'License needs renewal to unlock.',
            default => null,
        };
    }

    protected function parseDate(string $value): ?CarbonInterface
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        try {
            return Carbon::parse($trimmed);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function normalizePlanKey(string $rawPlan): ?string
    {
        $normalized = Str::of($rawPlan)
            ->lower()
            ->trim()
            ->replace(['—', '-', ' '], '_')
            ->value();

        if ($normalized === '') {
            return null;
        }

        $aliases = collect(config('creditsoft.licensing.plan_aliases', []))
            ->mapWithKeys(fn (string $value, string $key) => [
                Str::of($key)->lower()->trim()->replace(['—', '-', ' '], '_')->value() => $value,
            ])
            ->all();

        return $aliases[$normalized] ?? $normalized;
    }

    protected function planLabel(?string $planKey, string $rawPlan): ?string
    {
        if ($planKey) {
            $configuredLabel = data_get(config('creditsoft.licensing.plans', []), "{$planKey}.label");

            if (is_string($configuredLabel) && trim($configuredLabel) !== '') {
                return $configuredLabel;
            }
        }

        $trimmed = trim($rawPlan);

        if ($trimmed === '') {
            return null;
        }

        return Str::of($trimmed)->replace(['_', '-'], ' ')->title()->value();
    }

    /**
     * @return array<string, bool>
     */
    protected function resolveFeatures(mixed $rawFeatures, ?string $planKey): array
    {
        $defaults = collect(config('creditsoft.licensing.default_features', []))
            ->map(fn (mixed $value): bool => (bool) $value)
            ->all();
        $planFeatures = collect(data_get(config('creditsoft.licensing.plans', []), "{$planKey}.features", []))
            ->map(fn (mixed $value): bool => (bool) $value)
            ->all();
        $features = array_replace($defaults, $planFeatures);
        $explicitFeatures = $this->normalizeExplicitFeatures($rawFeatures);

        if ($explicitFeatures !== null) {
            $features = array_replace($features, $explicitFeatures);
        }

        return collect($features)
            ->mapWithKeys(fn (mixed $value, string $key) => [$key => (bool) $value])
            ->all();
    }

    /**
     * @return array<string, bool>|null
     */
    protected function normalizeExplicitFeatures(mixed $rawFeatures): ?array
    {
        $knownFeatures = array_keys((array) config('creditsoft.licensing.features', []));

        if (! is_array($rawFeatures) || $rawFeatures === []) {
            return null;
        }

        if (array_is_list($rawFeatures)) {
            $enabled = collect($rawFeatures)
                ->filter(fn (mixed $value): bool => is_string($value) && $this->normalizeFeatureKey($value) !== null)
                ->map(fn (string $value): string => (string) $this->normalizeFeatureKey($value))
                ->unique()
                ->values()
                ->all();

            if ($enabled === []) {
                return null;
            }

            $features = collect($knownFeatures)
                ->mapWithKeys(fn (string $feature) => [$feature => false])
                ->all();

            foreach ($enabled as $feature) {
                $features[$feature] = true;
            }

            return $features;
        }

        $features = [];

        foreach ($rawFeatures as $key => $value) {
            $normalizedKey = $this->normalizeFeatureKey(is_string($key) ? $key : (is_string($value) ? $value : null));

            if ($normalizedKey === null) {
                continue;
            }

            if (! is_string($key) && is_string($value)) {
                $features[$normalizedKey] = true;

                continue;
            }

            if (is_array($value)) {
                $value = $value['enabled']
                    ?? $value['allowed']
                    ?? $value['active']
                    ?? $value['included']
                    ?? $value['value']
                    ?? null;
            }

            $features[$normalizedKey] = $this->asBool($value);
        }

        return $features === [] ? null : $features;
    }

    protected function normalizeFeatureKey(?string $rawFeature): ?string
    {
        $normalized = Str::of((string) $rawFeature)
            ->lower()
            ->trim()
            ->replace([' ', '-'], '_')
            ->value();

        if ($normalized === '') {
            return null;
        }

        return array_key_exists($normalized, config('creditsoft.licensing.features', [])) ? $normalized : null;
    }

    protected function recommendedPlanLabel(string $feature): ?string
    {
        foreach ((array) config('creditsoft.licensing.plans', []) as $plan) {
            if ((bool) data_get($plan, "features.{$feature}", false)) {
                $label = data_get($plan, 'label');

                return is_string($label) && trim($label) !== '' ? $label : null;
            }
        }

        return null;
    }

    protected function asBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        return $value !== null;
    }
}
