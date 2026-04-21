<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class TailscaleStatusService
{
    /**
     * @return array{
     *     installed: bool,
     *     running: bool,
     *     version: ?string,
     *     hostname: ?string,
     *     dns_name: ?string,
     *     ipv4: ?string,
     *     ipv6: ?string,
     *     tailnet: ?string,
     *     tailnet_name: ?string,
     *     reason: ?string
     * }
     */
    public function current(): array
    {
        $localStatus = $this->localStatus();

        if ($localStatus['running']) {
            return $localStatus;
        }

        return $this->apiStatus($localStatus) ?? $localStatus;
    }

    /**
     * @return array{
     *     installed: bool,
     *     running: bool,
     *     version: ?string,
     *     hostname: ?string,
     *     dns_name: ?string,
     *     ipv4: ?string,
     *     ipv6: ?string,
     *     tailnet: ?string,
     *     tailnet_name: ?string,
     *     reason: ?string
     * }
     */
    protected function localStatus(): array
    {
        if (! $this->binaryExists()) {
            return $this->emptyStatus('Tailscale is not installed on this machine.');
        }

        $result = Process::timeout(3)->run(['sh', '-lc', 'tailscale status --json']);

        if (! $result->successful()) {
            return [
                ...$this->emptyStatus('Tailscale is installed, but the local status could not be read.'),
                'installed' => true,
            ];
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($result->output(), true);

        if (! is_array($payload)) {
            return [
                ...$this->emptyStatus('Tailscale returned unreadable status data.'),
                'installed' => true,
            ];
        }

        /** @var array<string, mixed> $self */
        $self = is_array(data_get($payload, 'Self')) ? data_get($payload, 'Self') : [];
        $ips = array_values(array_filter(
            is_array(data_get($self, 'TailscaleIPs')) ? data_get($self, 'TailscaleIPs') : [],
            fn (mixed $ip): bool => is_string($ip) && trim($ip) !== '',
        ));

        return [
            'installed' => true,
            'running' => strcasecmp((string) data_get($payload, 'BackendState'), 'running') === 0,
            'version' => $this->nullableString(data_get($payload, 'Version')),
            'hostname' => $this->nullableString(data_get($self, 'HostName')),
            'dns_name' => $this->trimDot(data_get($self, 'DNSName')),
            'ipv4' => $this->firstMatchingIp($ips, true),
            'ipv6' => $this->firstMatchingIp($ips, false),
            'tailnet' => $this->nullableString(data_get($payload, 'CurrentTailnet.MagicDNSSuffix')),
            'tailnet_name' => $this->nullableString(data_get($payload, 'CurrentTailnet.Name')),
            'reason' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $fallbackStatus
     * @return array{
     *     installed: bool,
     *     running: bool,
     *     version: ?string,
     *     hostname: ?string,
     *     dns_name: ?string,
     *     ipv4: ?string,
     *     ipv6: ?string,
     *     tailnet: ?string,
     *     tailnet_name: ?string,
     *     reason: ?string
     * }|null
     */
    protected function apiStatus(array $fallbackStatus): ?array
    {
        $apiKey = trim((string) config('creditsoft.tunnels.tailscale.api_key'));

        if ($apiKey === '') {
            return null;
        }

        $response = $this->devicesResponse();

        if (! $response?->successful()) {
            $status = $response?->status();

            return [
                ...$fallbackStatus,
                'reason' => $status
                    ? "Tailscale admin API returned HTTP {$status}."
                    : 'Tailscale admin API could not be reached.',
            ];
        }

        $devices = data_get($response->json(), 'devices');

        if (! is_array($devices)) {
            return [
                ...$fallbackStatus,
                'reason' => 'Tailscale admin API returned unreadable device data.',
            ];
        }

        $device = $this->findConfiguredDevice($devices);

        if (! $device) {
            $hostname = trim((string) config('creditsoft.tunnels.tailscale.hostname'));

            return [
                ...$fallbackStatus,
                'reason' => $hostname !== ''
                    ? "Tailscale admin API is available, but {$hostname} was not found in the tailnet."
                    : 'Tailscale admin API is available, but no CreditSoft hostname is configured.',
            ];
        }

        $addresses = $this->deviceAddresses($device);
        $dnsName = $this->trimDot(data_get($device, 'name')) ?? $this->trimDot(data_get($device, 'dnsName'));
        $hostname = $this->nullableString(data_get($device, 'hostname')) ?? $this->hostnameFromDns($dnsName);
        $tailnet = $this->configuredTailnet() ?? $this->tailnetFromDns($dnsName);

        return [
            'installed' => true,
            'running' => (bool) data_get($device, 'online', false),
            'version' => $this->nullableString(data_get($device, 'clientVersion')),
            'hostname' => $hostname,
            'dns_name' => $dnsName,
            'ipv4' => $this->firstMatchingIp($addresses, true),
            'ipv6' => $this->firstMatchingIp($addresses, false),
            'tailnet' => $tailnet,
            'tailnet_name' => $tailnet,
            'reason' => (bool) data_get($device, 'online', false)
                ? null
                : 'Tailscale admin API found this device, but it is currently offline.',
        ];
    }

    protected function devicesResponse(): ?Response
    {
        $tailnets = array_values(array_unique(array_filter([
            $this->configuredTailnet(),
            '-',
        ])));

        foreach ($tailnets as $tailnet) {
            try {
                $response = Http::withToken((string) config('creditsoft.tunnels.tailscale.api_key'))
                    ->acceptJson()
                    ->timeout(4)
                    ->get("https://api.tailscale.com/api/v2/tailnet/{$tailnet}/devices", [
                        'fields' => 'all',
                    ]);
            } catch (\Throwable) {
                $response = null;
            }

            if ($response?->successful() || $tailnet === '-') {
                return $response;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $devices
     * @return array<string, mixed>|null
     */
    protected function findConfiguredDevice(array $devices): ?array
    {
        $configuredHostname = $this->normalizeHostname(config('creditsoft.tunnels.tailscale.hostname'));

        foreach ($devices as $device) {
            if (! is_array($device)) {
                continue;
            }

            if ($configuredHostname === '') {
                continue;
            }

            $candidates = [
                data_get($device, 'hostname'),
                data_get($device, 'name'),
                data_get($device, 'dnsName'),
            ];

            foreach ($candidates as $candidate) {
                if ($this->normalizeHostname($candidate) === $configuredHostname) {
                    return $device;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $device
     * @return array<int, string>
     */
    protected function deviceAddresses(array $device): array
    {
        $addresses = data_get($device, 'addresses');

        if (! is_array($addresses)) {
            $addresses = data_get($device, 'tailscaleIPs');
        }

        return array_values(array_filter(
            is_array($addresses) ? $addresses : [],
            fn (mixed $ip): bool => is_string($ip) && trim($ip) !== '',
        ));
    }

    protected function binaryExists(): bool
    {
        return Process::timeout(1)->run(['sh', '-lc', 'command -v tailscale >/dev/null 2>&1'])->successful();
    }

    /**
     * @return array{
     *     installed: bool,
     *     running: bool,
     *     version: ?string,
     *     hostname: ?string,
     *     dns_name: ?string,
     *     ipv4: ?string,
     *     ipv6: ?string,
     *     tailnet: ?string,
     *     tailnet_name: ?string,
     *     reason: ?string
     * }
     */
    protected function emptyStatus(?string $reason = null): array
    {
        return [
            'installed' => false,
            'running' => false,
            'version' => null,
            'hostname' => null,
            'dns_name' => null,
            'ipv4' => null,
            'ipv6' => null,
            'tailnet' => null,
            'tailnet_name' => null,
            'reason' => $reason,
        ];
    }

    /**
     * @param  array<int, string>  $ips
     */
    protected function firstMatchingIp(array $ips, bool $preferIpv4): ?string
    {
        foreach ($ips as $ip) {
            $isIpv4 = str_contains($ip, '.');

            if ($preferIpv4 === $isIpv4) {
                return $ip;
            }
        }

        return null;
    }

    protected function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    protected function trimDot(mixed $value): ?string
    {
        $trimmed = $this->nullableString($value);

        return $trimmed ? rtrim($trimmed, '.') : null;
    }

    protected function configuredTailnet(): ?string
    {
        return $this->nullableString(config('creditsoft.tunnels.tailscale.tailnet'));
    }

    protected function hostnameFromDns(?string $dnsName): ?string
    {
        if (! $dnsName) {
            return null;
        }

        return explode('.', $dnsName)[0] ?: null;
    }

    protected function tailnetFromDns(?string $dnsName): ?string
    {
        if (! $dnsName || ! str_contains($dnsName, '.')) {
            return null;
        }

        return substr($dnsName, strpos($dnsName, '.') + 1) ?: null;
    }

    protected function normalizeHostname(mixed $value): string
    {
        $trimmed = $this->trimDot($value);

        if (! $trimmed) {
            return '';
        }

        return strtolower(explode('.', $trimmed)[0] ?: $trimmed);
    }
}
