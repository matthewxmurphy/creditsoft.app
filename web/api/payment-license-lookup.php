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

$input = json_decode((string) file_get_contents('php://input'), true);

if (! is_array($input)) {
    $input = $_POST;
}

$result = cs_site_zelle_public_payment_lookup($input);

if (empty($result['success'])) {
    http_response_code(422);
}

echo json_encode($result, JSON_UNESCAPED_SLASHES);
