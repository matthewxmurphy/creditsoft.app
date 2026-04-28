<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class TailscaleCredentialService
{
    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $apiKey = trim((string) config('creditsoft.tunnels.tailscale.api_key'));
        $warningDays = max(1, (int) config('creditsoft.tunnels.tailscale.api_key_warning_days', 3));
        $expiresAt = $this->parseDate((string) config('creditsoft.tunnels.tailscale.api_key_expires_at'));
        $reminderStartsAt = $expiresAt?->subDays($warningDays)->startOfDay();
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $daysUntilExpiry = $expiresAt ? (int) $today->diffInDays($expiresAt, false) : null;
        $reminderActive = $expiresAt && $reminderStartsAt
            ? $today->greaterThanOrEqualTo($reminderStartsAt)
            : false;

        return [
            'has_api_key' => $apiKey !== '',
            'masked_api_key' => $this->mask($apiKey),
            'expires_at' => $expiresAt?->toDateString(),
            'expires_label' => $expiresAt?->isoFormat('MMMM D, YYYY'),
            'warning_days' => $warningDays,
            'reminder_starts_at' => $reminderStartsAt?->toDateString(),
            'reminder_starts_label' => $reminderStartsAt?->isoFormat('MMMM D, YYYY'),
            'reminder_active' => $reminderActive,
            'days_until_expiry' => $daysUntilExpiry,
            'status_summary' => $this->summary(
                hasApiKey: $apiKey !== '',
                expiresAt: $expiresAt,
                reminderStartsAt: $reminderStartsAt,
                reminderActive: $reminderActive,
                daysUntilExpiry: $daysUntilExpiry,
            ),
            'rotation_url' => 'https://login.tailscale.com/admin/settings/keys',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activeReminder(): ?array
    {
        $state = $this->current();

        if (
            ! ($state['has_api_key'] ?? false)
            || ! ($state['reminder_active'] ?? false)
            || ! filled((string) ($state['expires_at'] ?? null))
        ) {
            return null;
        }

        $daysUntilExpiry = $state['days_until_expiry'];
        $expiresLabel = $state['expires_label'] ?? $state['expires_at'];
        $reminderStartsLabel = $state['reminder_starts_label'] ?? $state['reminder_starts_at'];
        $title = is_int($daysUntilExpiry) && $daysUntilExpiry < 0
            ? 'Tailscale admin API key has expired'
            : "Update Tailscale admin API key before {$expiresLabel}";

        $details = is_int($daysUntilExpiry) && $daysUntilExpiry < 0
            ? "The saved Tailscale admin API key expired on {$expiresLabel}. Update it in Connectivity so staff offboarding and tailnet automation keep working."
            : "The saved Tailscale admin API key expires on {$expiresLabel}. This reminder started {$reminderStartsLabel}. Update it now so staff offboarding and tailnet automation keep working.";

        return [
            'id' => 'ops-tailscale-api-key-renewal',
            'title' => $title,
            'details' => $details,
            'status' => 'open',
            'priority' => 'high',
            'due_at' => $state['expires_at'],
            'client' => null,
            'system_item' => true,
            'source' => 'system',
            'action_href' => route('connectivity.edit'),
            'action_label' => 'Open connectivity',
        ];
    }

    protected function parseDate(string $value): ?CarbonImmutable
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($trimmed, config('app.timezone'))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function mask(string $value): ?string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (strlen($trimmed) <= 4) {
            return str_repeat('*', strlen($trimmed));
        }

        return str_repeat('*', max(strlen($trimmed) - 4, 8)).substr($trimmed, -4);
    }

    protected function summary(
        bool $hasApiKey,
        ?CarbonImmutable $expiresAt,
        ?CarbonImmutable $reminderStartsAt,
        bool $reminderActive,
        ?int $daysUntilExpiry,
    ): string {
        if (! $hasApiKey) {
            if ($expiresAt) {
                $expiresLabel = $expiresAt->isoFormat('MMMM D, YYYY');
                $reminderLabel = $reminderStartsAt?->isoFormat('MMMM D, YYYY');

                if ($reminderLabel) {
                    return "No Tailscale admin API key is saved yet. The renewal date on file is {$expiresLabel}, and the daily reminder window begins {$reminderLabel}.";
                }

                return "No Tailscale admin API key is saved yet. The renewal date on file is {$expiresLabel}.";
            }

            return 'No Tailscale admin API key is saved yet.';
        }

        if (! $expiresAt) {
            return 'Tailscale admin API key is saved. Add the expiration date so CreditSoft can warn the office before it expires.';
        }

        $expiresLabel = $expiresAt->isoFormat('MMMM D, YYYY');

        if (is_int($daysUntilExpiry) && $daysUntilExpiry < 0) {
            return "This Tailscale admin API key expired on {$expiresLabel}. Replace it now.";
        }

        if ($reminderActive) {
            return "This Tailscale admin API key expires on {$expiresLabel}. The daily reminder is active now.";
        }

        $reminderLabel = $reminderStartsAt?->isoFormat('MMMM D, YYYY');

        if ($reminderLabel) {
            return "This Tailscale admin API key expires on {$expiresLabel}. The daily reminder starts {$reminderLabel}.";
        }

        return "This Tailscale admin API key expires on {$expiresLabel}.";
    }
}
