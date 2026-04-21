<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once dirname(__DIR__) . '/cash-app-config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

function creditsoft_cash_app_env(string $key, string $default = ''): string
{
    return creditsoft_site_cash_app_config_value('CREDITSOFT_'.$key, $key, $default);
}

function creditsoft_cash_app_enabled(): bool
{
    return creditsoft_site_cash_app_enabled();
}

function creditsoft_cash_app_json_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function creditsoft_cash_app_clean_url(?string $value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    return filter_var($value, FILTER_VALIDATE_URL) ? substr($value, 0, 2048) : null;
}

function creditsoft_cash_app_post_json(string $url, array $headers, array $payload): array
{
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

    if ($body === false) {
        return ['ok' => false, 'status' => 500, 'body' => null, 'error' => 'Unable to encode Cash App request.'];
    }

    if (function_exists('curl_init')) {
        $curlHeaders = [];

        foreach ($headers as $name => $value) {
            $curlHeaders[] = $name.': '.$value;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 20,
        ]);

        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);

        if ($responseBody === false) {
            return ['ok' => false, 'status' => $status ?: 500, 'body' => null, 'error' => $error ?: 'Cash App API request failed.'];
        }

        $decoded = json_decode((string) $responseBody, true);

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => is_array($decoded) ? $decoded : null,
            'error' => null,
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", array_map(
                static fn (string $name, string $value): string => $name.': '.$value,
                array_keys($headers),
                $headers,
            )),
            'content' => $body,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);
    $responseBody = @file_get_contents($url, false, $context);
    $status = 0;

    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }

    if ($responseBody === false) {
        return ['ok' => false, 'status' => $status ?: 500, 'body' => null, 'error' => 'Cash App API request failed.'];
    }

    $decoded = json_decode((string) $responseBody, true);

    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'body' => is_array($decoded) ? $decoded : null,
        'error' => null,
    ];
}

function creditsoft_cash_app_error_from_payload(?array $payload, string $fallback): string
{
    $paths = [
        ['errors', 0, 'detail'],
        ['errors', 0, 'code'],
        ['error', 'message'],
        ['message'],
    ];

    foreach ($paths as $path) {
        $cursor = $payload;

        foreach ($path as $part) {
            if (! is_array($cursor) || ! array_key_exists($part, $cursor)) {
                $cursor = null;
                break;
            }

            $cursor = $cursor[$part];
        }

        $message = trim((string) $cursor);

        if ($message !== '') {
            return $message;
        }
    }

    return $fallback;
}

$input = json_decode((string) file_get_contents('php://input'), true);

if (! is_array($input)) {
    creditsoft_cash_app_json_response(422, ['success' => false, 'error' => 'Invalid request body']);
}

$enabled = creditsoft_cash_app_enabled();
$apiBaseUrl = rtrim(creditsoft_cash_app_env('CASH_APP_PAY_API_BASE_URL', 'https://sandbox.api.cash.app'), '/');
$clientId = creditsoft_cash_app_env('CASH_APP_PAY_CLIENT_ID');
$scopeId = creditsoft_cash_app_env('CASH_APP_PAY_SCOPE_ID');
$redirectUrl = creditsoft_cash_app_clean_url(creditsoft_cash_app_env('CASH_APP_PAY_REDIRECT_URL'));
$userAgent = creditsoft_cash_app_env('CASH_APP_PAY_USER_AGENT', 'CreditSoft Checkout');

if (! $enabled || $clientId === '' || $scopeId === '' || ! filter_var($apiBaseUrl, FILTER_VALIDATE_URL)) {
    creditsoft_cash_app_json_response(503, [
        'success' => false,
        'configured' => false,
        'error' => 'Cash App Pay API is not configured on this checkout yet. Use Zelle or contact hello@creditsoft.app.',
    ]);
}

$customerEmail = filter_var((string) ($input['customer_email'] ?? ''), FILTER_SANITIZE_EMAIL);
$amount = is_numeric($input['amount'] ?? null) ? round((float) $input['amount'], 2) : 0.0;
$currency = strtoupper(substr(trim((string) ($input['currency'] ?? 'USD')), 0, 3)) ?: 'USD';

if (! $customerEmail || ! filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    creditsoft_cash_app_json_response(422, ['success' => false, 'error' => 'A valid customer email is required before creating a Cash App Pay request.']);
}

if ($amount <= 0) {
    creditsoft_cash_app_json_response(422, ['success' => false, 'error' => 'Enter the actual Cash App amount before creating a payment request.']);
}

$referenceId = 'cs-web-'.bin2hex(random_bytes(12));
$idempotencyKey = bin2hex(random_bytes(16));
$requestPayload = [
    'idempotency_key' => $idempotencyKey,
    'request' => [
        'actions' => [[
            'amount' => (int) round($amount * 100),
            'currency' => $currency,
            'scope_id' => $scopeId,
            'type' => 'ONE_TIME_PAYMENT',
        ]],
        'channel' => 'ONLINE',
        'reference_id' => $referenceId,
        'metadata' => array_filter([
            'source' => 'creditsoft_public_checkout',
            'customer_email' => $customerEmail,
            'plan' => trim((string) ($input['plan'] ?? '')),
            'billing' => trim((string) ($input['billing'] ?? '')),
        ]),
        'customer_metadata' => array_filter([
            'reference_id' => $customerEmail,
        ]),
    ],
];

if ($redirectUrl !== null) {
    $requestPayload['request']['redirect_url'] = $redirectUrl;
}

$response = creditsoft_cash_app_post_json(
    $apiBaseUrl.'/customer-request/v1/requests',
    [
        'Accept' => 'application/json',
        'Authorization' => 'Client '.$clientId,
        'Content-Type' => 'application/json',
        'User-Agent' => $userAgent !== '' ? $userAgent : 'CreditSoft Checkout',
    ],
    $requestPayload,
);

if (! $response['ok']) {
    creditsoft_cash_app_json_response(502, [
        'success' => false,
        'configured' => true,
        'error' => creditsoft_cash_app_error_from_payload(
            is_array($response['body']) ? $response['body'] : null,
            'Cash App Pay request failed with HTTP '.(int) $response['status'].'.',
        ),
    ]);
}

$payload = is_array($response['body']) ? $response['body'] : [];
$cashRequest = is_array($payload['request'] ?? null) ? $payload['request'] : [];
$triggers = is_array($cashRequest['auth_flow_triggers'] ?? null) ? $cashRequest['auth_flow_triggers'] : [];
$cashAppRequestId = trim((string) ($cashRequest['id'] ?? ''));
$status = strtolower(trim((string) ($cashRequest['status'] ?? 'pending'))) ?: 'pending';

$record = [
    'created_at' => gmdate('c'),
    'cash_app_request_id' => $cashAppRequestId,
    'reference_id' => $referenceId,
    'status' => $status,
    'amount' => $amount,
    'currency' => $currency,
    'customer_email' => $customerEmail,
    'plan' => trim((string) ($input['plan'] ?? '')),
    'billing' => trim((string) ($input['billing'] ?? '')),
    'qr_code_image_url' => $triggers['qr_code_image_url'] ?? null,
    'qr_code_svg_url' => $triggers['qr_code_svg_url'] ?? null,
    'mobile_url' => $triggers['mobile_url'] ?? null,
    'desktop_url' => $triggers['desktop_url'] ?? null,
    'api_response' => $payload,
];
$storageDir = dirname(__DIR__).'/data';

if (! is_dir($storageDir) && ! mkdir($storageDir, 0775, true) && ! is_dir($storageDir)) {
    creditsoft_cash_app_json_response(500, ['success' => false, 'error' => 'Unable to prepare Cash App request storage.']);
}

file_put_contents($storageDir.'/cash_app_requests.jsonl', json_encode($record, JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND | LOCK_EX);

creditsoft_cash_app_json_response(200, [
    'success' => true,
    'configured' => true,
    'cash_app_request_id' => $cashAppRequestId,
    'reference_id' => $referenceId,
    'status' => $status,
    'qr_code_image_url' => $triggers['qr_code_image_url'] ?? null,
    'qr_code_svg_url' => $triggers['qr_code_svg_url'] ?? null,
    'mobile_url' => $triggers['mobile_url'] ?? null,
    'desktop_url' => $triggers['desktop_url'] ?? null,
    'message' => 'Cash App Pay request created.',
]);
