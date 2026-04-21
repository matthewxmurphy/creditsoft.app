<?php
declare(strict_types=1);

function creditsoft_bridge_config_path(): string
{
    foreach (creditsoft_bridge_config_paths() as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return creditsoft_bridge_config_paths()[0];
}

function creditsoft_bridge_config_paths(): array
{
    $root = dirname(__DIR__, 3);
    $publicRoot = dirname(__DIR__, 2);
    $configuredPath = trim((string) getenv('CREDITSOFT_BRIDGE_CONFIG'));

    return array_values(array_unique(array_filter([
        $configuredPath,
        $root . '/web-meta/api-bridge.json',
        $root . '/private/api-bridge.json',
        $root . '/private/web-meta/api-bridge.json',
        $root . '/shared/api-bridge.json',
        $publicRoot . '/web-meta/api-bridge.json',
    ])));
}

function creditsoft_bridge_load_config(): array
{
    $defaults = [
        'target_base_url' => '',
        'target_base_urls' => [],
        'bridge_token' => '',
        'timeout_seconds' => 12,
        'queue_failed_leads' => true,
    ];

    $path = creditsoft_bridge_config_path();
    if (! is_file($path)) {
        return $defaults;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (! is_array($decoded)) {
        return $defaults;
    }

    return [
        'target_base_url' => trim((string) ($decoded['target_base_url'] ?? '')),
        'target_base_urls' => array_values(array_filter(array_map(
            static fn (mixed $target): string => trim((string) $target),
            (array) ($decoded['target_base_urls'] ?? [])
        ))),
        'bridge_token' => trim((string) ($decoded['bridge_token'] ?? '')),
        'timeout_seconds' => max(2, min(30, (int) ($decoded['timeout_seconds'] ?? 12))),
        'queue_failed_leads' => (bool) ($decoded['queue_failed_leads'] ?? true),
    ];
}

function creditsoft_bridge_normalize_target(string $target): string
{
    $target = trim($target);

    if ($target === '') {
        return '';
    }

    if (! str_starts_with($target, 'http://') && ! str_starts_with($target, 'https://')) {
        $target = 'https://' . $target;
    }

    $target = rtrim($target, '/');

    if (! str_ends_with($target, '/api/v1')) {
        $target .= '/api/v1';
    }

    return $target;
}

function creditsoft_bridge_json(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function creditsoft_bridge_target_base_urls(array $config): array
{
    $targets = [];
    $primary = creditsoft_bridge_normalize_target((string) ($config['target_base_url'] ?? ''));

    if ($primary !== '') {
        $targets[] = $primary;
    }

    foreach ((array) ($config['target_base_urls'] ?? []) as $target) {
        $normalized = creditsoft_bridge_normalize_target((string) $target);

        if ($normalized !== '') {
            $targets[] = $normalized;
        }
    }

    return array_values(array_unique($targets));
}

function creditsoft_bridge_public_url_for_current_request(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

    return 'https://' . ($host !== '' ? $host : 'creditsoft.app') . $uri;
}

function creditsoft_bridge_request_headers(string $token, ?string $publicMetaCallbackUrl = null): array
{
    $headers = [
        'Accept: ' . ($_SERVER['HTTP_ACCEPT'] ?? 'application/json'),
        'X-CreditSoft-Bridge: creditsoft.app',
        'X-Forwarded-Host: ' . ($_SERVER['HTTP_HOST'] ?? 'creditsoft.app'),
        'X-Forwarded-Proto: https',
        'X-Forwarded-Uri: ' . ($_SERVER['REQUEST_URI'] ?? '/'),
    ];

    if ($publicMetaCallbackUrl !== null && trim($publicMetaCallbackUrl) !== '') {
        $headers[] = 'X-CreditSoft-Public-Meta-Callback: ' . trim($publicMetaCallbackUrl);
    }

    $contentType = trim((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if ($contentType !== '') {
        $headers[] = 'Content-Type: ' . $contentType;
    }

    $incomingAuth = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if ($incomingAuth !== '') {
        $headers[] = 'Authorization: ' . $incomingAuth;
    } elseif ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    return $headers;
}

function creditsoft_bridge_is_lead_create(string $method, string $path): bool
{
    return $method === 'POST' && trim($path, '/') === 'clients';
}

function creditsoft_bridge_outbox_dir(): string
{
    $root = dirname(__DIR__, 3);
    $private = $root . '/private/creditsoft-bridge-outbox';

    if (is_dir(dirname($private)) || @mkdir(dirname($private), 0700, true)) {
        return $private;
    }

    return dirname(creditsoft_bridge_config_path()) . '/creditsoft-bridge-outbox';
}

function creditsoft_bridge_queue_lead(string $method, string $path, string $query, string $contentType, string $body, string $reason): string
{
    $dir = creditsoft_bridge_outbox_dir();
    if (! is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    $id = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(6));
    $file = $dir . '/' . $id . '.json';
    $payload = [
        'id' => $id,
        'queued_at' => gmdate(DATE_ATOM),
        'method' => $method,
        'path' => trim($path, '/'),
        'query' => $query,
        'content_type' => $contentType,
        'body_base64' => base64_encode($body),
        'attempts' => 0,
        'last_attempt_at' => null,
        'last_error' => $reason,
    ];

    file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    @chmod($file, 0600);

    return $id;
}

function creditsoft_bridge_update_queue_file(string $file, array $payload): void
{
    file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    @chmod($file, 0600);
}

function creditsoft_bridge_flush_queued_leads(array $config, ?string $publicMetaCallbackUrl = null): array
{
    if (! (bool) ($config['queue_failed_leads'] ?? true)) {
        return ['attempted' => 0, 'delivered' => 0, 'remaining' => 0];
    }

    $dir = creditsoft_bridge_outbox_dir();
    $files = is_dir($dir) ? glob($dir . '/*.json') : [];
    $targets = creditsoft_bridge_target_base_urls($config);
    $attempted = 0;
    $delivered = 0;

    sort($files);

    foreach ($files as $file) {
        if (! is_file($file)) {
            continue;
        }

        $payload = json_decode((string) file_get_contents($file), true);
        if (! is_array($payload)) {
            @rename($file, $file . '.bad');
            continue;
        }

        $attempted++;
        $payload['attempts'] = (int) ($payload['attempts'] ?? 0) + 1;
        $payload['last_attempt_at'] = gmdate(DATE_ATOM);
        $method = strtoupper((string) ($payload['method'] ?? 'POST'));
        $path = trim((string) ($payload['path'] ?? 'clients'), '/');
        $query = (string) ($payload['query'] ?? '');
        $body = base64_decode((string) ($payload['body_base64'] ?? ''), true);
        $body = is_string($body) ? $body : '';
        $contentType = trim((string) ($payload['content_type'] ?? 'application/json'));
        $headers = creditsoft_bridge_request_headers((string) ($config['bridge_token'] ?? ''), $publicMetaCallbackUrl);

        if ($contentType !== '') {
            $headers = array_values(array_filter($headers, static fn (string $header): bool => stripos($header, 'Content-Type:') !== 0));
            $headers[] = 'Content-Type: ' . $contentType;
        }

        $result = creditsoft_bridge_attempt_targets($targets, $path, $query, $method, $headers, $body, (int) ($config['timeout_seconds'] ?? 12));
        $status = (int) ($result['status'] ?? 0);

        if (($result['reachable'] ?? false) && $status >= 200 && $status < 300) {
            @unlink($file);
            $delivered++;
            continue;
        }

        $payload['last_error'] = (string) ($result['error'] ?? ('HTTP ' . $status));
        creditsoft_bridge_update_queue_file($file, $payload);

        if (($result['reachable'] ?? false) && $status >= 400 && $status < 500) {
            @rename($file, $file . '.failed');
            continue;
        }

        break;
    }

    $remaining = is_dir($dir) ? count(glob($dir . '/*.json') ?: []) : 0;

    return [
        'attempted' => $attempted,
        'delivered' => $delivered,
        'remaining' => $remaining,
    ];
}

function creditsoft_bridge_attempt_targets(array $targets, string $path, string $query, string $method, array $headers, string $body, int $timeout): array
{
    $last = [
        'reachable' => false,
        'status' => 0,
        'headers' => '',
        'body' => '',
        'error' => 'No office API target is configured.',
    ];

    foreach ($targets as $targetBaseUrl) {
        $targetUrl = rtrim($targetBaseUrl, '/') . '/' . ltrim($path, '/');
        if ($query !== '') {
            $targetUrl .= '?' . $query;
        }

        $result = creditsoft_bridge_http_request($targetUrl, $method, $headers, $body, $timeout);
        $last = $result;

        if (($result['reachable'] ?? false) && (int) ($result['status'] ?? 0) < 500) {
            return $result;
        }
    }

    return $last;
}

function creditsoft_bridge_emit_proxy_response(array $result): never
{
    $status = (int) ($result['status'] ?? 502);
    http_response_code($status > 0 ? $status : 502);

    foreach (explode("\n", (string) ($result['headers'] ?? '')) as $headerLine) {
        $headerLine = trim($headerLine);
        if ($headerLine === '' || ! str_contains($headerLine, ':')) {
            continue;
        }

        [$name, $value] = array_map('trim', explode(':', $headerLine, 2));
        $lower = strtolower($name);
        if (in_array($lower, ['transfer-encoding', 'content-length', 'connection'], true)) {
            continue;
        }

        header($name . ': ' . $value, false);
    }

    echo (string) ($result['body'] ?? '');
    exit;
}

function creditsoft_bridge_http_request(string $targetUrl, string $method, array $headers, string $body, int $timeout): array
{
    if (function_exists('curl_init')) {
        $curl = curl_init($targetUrl);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_TIMEOUT => $timeout,
        ]);

        if (! in_array($method, ['GET', 'HEAD'], true)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($curl);
        if (! is_string($response)) {
            $error = curl_error($curl) ?: 'Unknown bridge error';

            return [
                'reachable' => false,
                'status' => 0,
                'headers' => '',
                'body' => '',
                'error' => $error,
            ];
        }

        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $rawHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        return [
            'reachable' => $status > 0,
            'status' => $status,
            'headers' => $rawHeaders,
            'body' => $responseBody,
            'error' => $status >= 500 ? 'Office API returned HTTP ' . $status : '',
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => in_array($method, ['GET', 'HEAD'], true) ? '' : $body,
            'ignore_errors' => true,
            'timeout' => $timeout,
        ],
    ]);

    $responseBody = @file_get_contents($targetUrl, false, $context);
    if ($responseBody === false) {
        return [
            'reachable' => false,
            'status' => 0,
            'headers' => '',
            'body' => '',
            'error' => 'CreditSoft website bridge could not reach the office API target.',
        ];
    }

    $status = 200;
    $rawHeaders = implode("\n", $http_response_header ?? []);
    foreach (($http_response_header ?? []) as $headerLine) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $headerLine, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }

    return [
        'reachable' => true,
        'status' => $status,
        'headers' => $rawHeaders,
        'body' => $responseBody,
        'error' => $status >= 500 ? 'Office API returned HTTP ' . $status : '',
    ];
}

function creditsoft_bridge_forward(string $path, ?string $publicMetaCallbackUrl = null): never
{
    $config = creditsoft_bridge_load_config();
    $targetBaseUrls = creditsoft_bridge_target_base_urls($config);

    if ($targetBaseUrls === []) {
        creditsoft_bridge_json(503, [
            'success' => false,
            'message' => 'CreditSoft website bridge is installed, but no office API target is configured yet.',
            'config_path' => creditsoft_bridge_config_path(),
        ]);
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');

    $body = file_get_contents('php://input') ?: '';
    $headers = creditsoft_bridge_request_headers((string) ($config['bridge_token'] ?? ''), $publicMetaCallbackUrl);
    $timeout = (int) ($config['timeout_seconds'] ?? 12);
    $contentType = trim((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

    if (creditsoft_bridge_is_lead_create($method, $path)) {
        creditsoft_bridge_flush_queued_leads($config, $publicMetaCallbackUrl);
    }

    $result = creditsoft_bridge_attempt_targets($targetBaseUrls, $path, $query, $method, $headers, $body, $timeout);
    $status = (int) ($result['status'] ?? 0);

    if (($result['reachable'] ?? false) && $status < 500) {
        creditsoft_bridge_emit_proxy_response($result);
    }

    if (creditsoft_bridge_is_lead_create($method, $path) && (bool) ($config['queue_failed_leads'] ?? true)) {
        $queueId = creditsoft_bridge_queue_lead(
            method: $method,
            path: $path,
            query: $query,
            contentType: $contentType !== '' ? $contentType : 'application/json',
            body: $body,
            reason: (string) ($result['error'] ?? 'Office API targets are unavailable.'),
        );

        creditsoft_bridge_json(503, [
            'success' => false,
            'queued' => true,
            'queue_id' => $queueId,
            'message' => 'CreditSoft office intake is temporarily in maintenance mode. Please try again later.',
        ]);
    }

    creditsoft_bridge_json(502, [
        'success' => false,
        'message' => 'CreditSoft website bridge could not reach the office API target.',
        'error' => (string) ($result['error'] ?? 'Unknown bridge error'),
    ]);
}
