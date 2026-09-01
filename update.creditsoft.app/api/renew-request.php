<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('X-Robots-Tag: noindex, nofollow');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

http_response_code(410);
echo json_encode([
    'success' => false,
    'error' => 'CreditSoft renewal lives at https://www.creditsoft.app/renewal/. This update host is for installer and update files only.',
], JSON_UNESCAPED_SLASHES);
