<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/admin/bootstrap.php';
require_once dirname(__DIR__) . '/admin/zelle-payments.php';

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

$result = cs_site_zelle_create_payment_support_ticket($_POST, $_FILES['payment_screenshot'] ?? null);

if (empty($result['success'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => (string) ($result['message'] ?? 'Could not open the payment support ticket.')]);
    exit;
}

echo json_encode([
    'success' => true,
    'ticket_number' => (string) ($result['ticket_number'] ?? ''),
]);
