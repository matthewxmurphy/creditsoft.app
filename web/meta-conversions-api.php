<?php
declare(strict_types=1);

require_once __DIR__ . '/site-tracking-config.php';

function creditsoft_meta_capi_log(string $message, array $context = []): void
{
    $path = dirname(__DIR__) . '/web-meta/meta-capi.log';
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        return;
    }

    $line = '[' . gmdate('c') . '] ' . $message;

    if ($context !== []) {
        $encoded = json_encode($context, JSON_UNESCAPED_SLASHES);
        if (is_string($encoded)) {
            $line .= ' ' . $encoded;
        }
    }

    @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function creditsoft_meta_capi_current_url(): string
{
    $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $forwarded = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));

    if ($forwarded === 'https' || $forwarded === 'http') {
        $scheme = $forwarded;
    }

    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $uri = trim((string) ($_SERVER['REQUEST_URI'] ?? '/'));

    if ($host === '') {
        return '';
    }

    return $scheme . '://' . $host . ($uri !== '' ? $uri : '/');
}

function creditsoft_meta_capi_normalize_email(?string $value): string
{
    return strtolower(trim((string) $value));
}

function creditsoft_meta_capi_normalize_phone(?string $value): string
{
    return preg_replace('/\D+/', '', (string) $value) ?: '';
}

function creditsoft_meta_capi_hash(?string $value): ?string
{
    $value = trim((string) $value);

    return $value !== '' ? hash('sha256', $value) : null;
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function creditsoft_meta_capi_user_data(array $payload): array
{
    $userData = [];
    $emailHash = creditsoft_meta_capi_hash(creditsoft_meta_capi_normalize_email((string) ($payload['email'] ?? '')));
    $phoneHash = creditsoft_meta_capi_hash(creditsoft_meta_capi_normalize_phone((string) ($payload['phone'] ?? '')));
    $externalIdHash = creditsoft_meta_capi_hash((string) ($payload['external_id'] ?? ''));

    if ($emailHash) {
        $userData['em'] = [$emailHash];
    }

    if ($phoneHash) {
        $userData['ph'] = [$phoneHash];
    }

    if ($externalIdHash) {
        $userData['external_id'] = [$externalIdHash];
    }

    $clientIp = trim((string) ($payload['client_ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? '')));
    $clientUserAgent = trim((string) ($payload['client_user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')));
    $fbp = trim((string) ($payload['fbp'] ?? ($_COOKIE['_fbp'] ?? '')));
    $fbc = trim((string) ($payload['fbc'] ?? ($_COOKIE['_fbc'] ?? '')));

    if ($clientIp !== '') {
        $userData['client_ip_address'] = $clientIp;
    }

    if ($clientUserAgent !== '') {
        $userData['client_user_agent'] = $clientUserAgent;
    }

    if ($fbp !== '') {
        $userData['fbp'] = $fbp;
    }

    if ($fbc !== '') {
        $userData['fbc'] = $fbc;
    }

    return $userData;
}

/**
 * @param array<string, mixed> $payload
 * @param array<string, mixed> $options
 * @return array{success: bool, status?: int, response?: array<string, mixed>|string|null, error?: string}
 */
function creditsoft_meta_capi_send_event(string $eventName, array $payload, array $options = []): array
{
    $tracking = creditsoft_site_tracking_load();

    if (! creditsoft_site_tracking_capi_ready($tracking)) {
        return ['success' => false, 'error' => 'Meta CAPI is not configured yet.'];
    }

    $pixelId = trim((string) ($tracking['meta_pixel_id'] ?? ''));
    $token = trim((string) ($tracking['meta_capi_token'] ?? ''));
    $eventSourceUrl = trim((string) ($options['event_source_url'] ?? creditsoft_meta_capi_current_url()));
    $leadFormName = trim((string) ($tracking['lead_form_name'] ?? ''));
    $customData = array_filter([
        'content_name' => trim((string) ($options['content_name'] ?? $leadFormName)),
        'content_category' => trim((string) ($options['content_category'] ?? 'creditsoft_website')),
        'currency' => trim((string) ($options['currency'] ?? '')),
        'value' => isset($options['value']) && $options['value'] !== '' ? (float) $options['value'] : null,
        'status' => trim((string) ($options['status'] ?? '')),
    ], static fn ($value) => $value !== null && $value !== '');

    $event = array_filter([
        'event_name' => $eventName,
        'event_time' => (int) ($options['event_time'] ?? time()),
        'action_source' => trim((string) ($options['action_source'] ?? 'website')) ?: 'website',
        'event_source_url' => $eventSourceUrl !== '' ? $eventSourceUrl : null,
        'event_id' => trim((string) ($options['event_id'] ?? '')) ?: null,
        'user_data' => creditsoft_meta_capi_user_data($payload),
        'custom_data' => $customData !== [] ? $customData : null,
    ], static fn ($value) => $value !== null && $value !== []);

    $body = [
        'data' => [$event],
    ];

    $testEventCode = trim((string) ($tracking['meta_capi_test_event_code'] ?? ''));
    if ($testEventCode !== '') {
        $body['test_event_code'] = $testEventCode;
    }

    $endpoint = sprintf(
        'https://graph.facebook.com/v25.0/%s/events?access_token=%s',
        rawurlencode($pixelId),
        rawurlencode($token),
    );

    $json = json_encode($body, JSON_UNESCAPED_SLASHES);
    if (! is_string($json)) {
        return ['success' => false, 'error' => 'Could not encode the Meta event payload.'];
    }

    if (! function_exists('curl_init')) {
        return ['success' => false, 'error' => 'cURL is not available for Meta CAPI.'];
    }

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
    ]);

    $responseBody = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $decoded = is_string($responseBody) ? json_decode($responseBody, true) : null;

    if ($curlError !== '') {
        creditsoft_meta_capi_log('curl_error', ['event_name' => $eventName, 'error' => $curlError]);

        return ['success' => false, 'error' => $curlError];
    }

    if ($status < 200 || $status >= 300) {
        creditsoft_meta_capi_log('http_error', ['event_name' => $eventName, 'status' => $status, 'response' => $decoded ?: $responseBody]);

        return ['success' => false, 'status' => $status, 'response' => $decoded ?: $responseBody, 'error' => 'Meta CAPI returned an error response.'];
    }

    creditsoft_meta_capi_log('sent', ['event_name' => $eventName, 'status' => $status, 'event_id' => $event['event_id'] ?? null]);

    return ['success' => true, 'status' => $status, 'response' => is_array($decoded) ? $decoded : $responseBody];
}
