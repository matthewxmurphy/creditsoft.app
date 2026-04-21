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

$officeName = trim((string) ($input['office_name'] ?? ''));
$payerEmail = filter_var((string) ($input['payer_email'] ?? ''), FILTER_SANITIZE_EMAIL);
$payerPhone = trim((string) ($input['payer_phone'] ?? ''));

if ($officeName === '' || !$payerEmail || !filter_var($payerEmail, FILTER_VALIDATE_EMAIL) || $payerPhone === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Office name, payer email, and payer phone are required.']);
    exit;
}

$storageDir = dirname(__DIR__).'/data';
$storagePath = $storageDir.'/renew_requests.jsonl';

if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to prepare renewal storage.']);
    exit;
}

$record = [
    'submitted_at' => gmdate('c'),
    'plan' => trim((string) ($input['plan'] ?? '')),
    'billing' => trim((string) ($input['billing'] ?? 'monthly')),
    'plan_name' => trim((string) ($input['plan_name'] ?? 'CreditSoft')),
    'base_amount' => $input['base_amount'] ?? null,
    'zelle_discount_percent' => $input['zelle_discount_percent'] ?? null,
    'zelle_discount_amount' => $input['zelle_discount_amount'] ?? null,
    'amount' => $input['amount'] ?? null,
    'office_name' => $officeName,
    'license_key' => trim((string) ($input['license_key'] ?? '')),
    'payer_email' => $payerEmail,
    'payer_phone' => $payerPhone,
    'notes' => trim((string) ($input['notes'] ?? '')),
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
];

$written = file_put_contents($storagePath, json_encode($record, JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND | LOCK_EX);

if ($written === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to save the renewal request.']);
    exit;
}

echo json_encode(['success' => true]);
