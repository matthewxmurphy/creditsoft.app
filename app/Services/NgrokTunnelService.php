<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class NgrokTunnelService
{
    /**
     * @return array{
     *     running: bool,
     *     public_url: ?string,
     *     web_url: ?string
     * }
     */
    public function current(): array
    {
        $result = Process::timeout(2)->run(['sh', '-lc', 'curl -s --max-time 1 http://127.0.0.1:4040/api/tunnels']);

        if (! $result->successful()) {
            return [
                'running' => false,
                'public_url' => null,
                'web_url' => null,
            ];
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($result->output(), true);

        if (! is_array($payload) || ! is_array($payload['tunnels'] ?? null)) {
            return [
                'running' => false,
                'public_url' => null,
                'web_url' => null,
            ];
        }

        $publicUrl = null;

        foreach ($payload['tunnels'] as $tunnel) {
            if (is_array($tunnel) && is_string($tunnel['public_url'] ?? null) && str_starts_with($tunnel['public_url'], 'https://')) {
                $publicUrl = rtrim($tunnel['public_url'], '/');
                break;
            }
        }

        if (! $publicUrl) {
            foreach ($payload['tunnels'] as $tunnel) {
                if (is_array($tunnel) && is_string($tunnel['public_url'] ?? null) && str_starts_with($tunnel['public_url'], 'http://')) {
                    $publicUrl = rtrim($tunnel['public_url'], '/');
                    break;
                }
            }
        }

        return [
            'running' => $publicUrl !== null,
            'public_url' => $publicUrl,
            'web_url' => 'http://127.0.0.1:4040',
        ];
    }

    /**
     * @return array{
     *     running: bool,
     *     public_url: ?string,
     *     web_url: ?string
     * }
     */
    public function ensureRunning(int|string|null $port, string $configPath): array
    {
        $current = $this->current();

        if ($current['running']) {
            return $current;
        }

        $resolvedPort = is_numeric($port) ? (int) $port : 80;
        $configuredDomain = trim((string) config('creditsoft.tunnels.ngrok.domain'));
        $endpointUrl = $configuredDomain !== ''
            ? (str_starts_with($configuredDomain, 'http://') || str_starts_with($configuredDomain, 'https://')
                ? $configuredDomain
                : 'https://'.$configuredDomain)
            : null;
        $poolingEnabled = (bool) config('creditsoft.tunnels.ngrok.pooling_enabled', true);

        Process::timeout(3)->run([
            '/bin/bash',
            '-lc',
            sprintf(
                "nohup ngrok http 127.0.0.1:%d --config %s --log stdout%s%s >/tmp/creditsoft-ngrok.log 2>&1 < /dev/null & disown",
                $resolvedPort,
                escapeshellarg($configPath),
                $endpointUrl ? ' --url '.escapeshellarg($endpointUrl) : '',
                $poolingEnabled ? ' --pooling-enabled' : '',
            ),
        ]);

        for ($attempt = 0; $attempt < 8; $attempt++) {
            usleep(350000);

            $current = $this->current();

            if ($current['running']) {
                return $current;
            }
        }

        return $current;
    }
}
