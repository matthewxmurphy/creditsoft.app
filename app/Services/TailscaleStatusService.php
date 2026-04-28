<?php

namespace App\Services;

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
}
