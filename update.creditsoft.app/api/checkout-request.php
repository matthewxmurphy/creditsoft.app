<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true);

if (!is_array($input)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid request body']);
    exit;
}

$customerEmail = filter_var((string) ($input['customer_email'] ?? ''), FILTER_SANITIZE_EMAIL);
$customerPhone = trim((string) ($input['customer_phone'] ?? ''));
$paymentSource = trim((string) ($input['payment_source'] ?? ''));
$officeName = trim((string) ($input['office_name'] ?? ''));

if (!$customerEmail || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'A valid customer email is required.']);
    exit;
}

if ($customerPhone === '' || $officeName === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Phone and office name are required.']);
    exit;
}

$storageDir = dirname(__DIR__).'/data';
$storagePath = $storageDir.'/checkout_requests.jsonl';

if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to prepare checkout storage.']);
    exit;
}

$checkoutNumber = 'CSCO-'.gmdate('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
$clientPortalUrl = 'https://www.creditsoft.app/client-portal.php';
$paymentMemo = $checkoutNumber.' or '.$customerEmail;

$record = [
    'submitted_at' => gmdate('c'),
    'checkout_number' => $checkoutNumber,
    'checkout_reference' => $checkoutNumber,
    'plan' => trim((string) ($input['plan'] ?? '')),
    'billing' => trim((string) ($input['billing'] ?? 'monthly')),
    'plan_name' => trim((string) ($input['plan_name'] ?? 'CreditSoft')),
    'amount' => $input['amount'] ?? null,
    'payment_method' => trim((string) ($input['payment_method'] ?? '')),
    'customer_email' => $customerEmail,
    'customer_phone' => $customerPhone,
    'payment_source' => $paymentSource,
    'payment_memo' => $paymentMemo,
    'payment_memo_email' => $customerEmail,
    'office_name' => $officeName,
    'notes' => trim((string) ($input['notes'] ?? '')),
    'client_portal_url' => $clientPortalUrl,
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
];

$written = file_put_contents($storagePath, json_encode($record, JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND | LOCK_EX);

if ($written === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to save the checkout request.']);
    exit;
}

echo json_encode([
    'success' => true,
    'checkout_number' => $checkoutNumber,
    'checkout_reference' => $checkoutNumber,
    'customer_email' => $customerEmail,
    'payment_memo' => $paymentMemo,
    'client_portal_url' => $clientPortalUrl,
    'zelle' => [
        'payee' => 'Matthew Murphy',
        'destination' => 'hello@creditsoft.app',
        'memo' => $paymentMemo,
    ],
    'cash_app' => [
        'cashtag' => '$creditsoft',
        'url' => 'https://cash.app/$creditsoft',
        'note' => $paymentMemo,
    ],
], JSON_UNESCAPED_SLASHES);
