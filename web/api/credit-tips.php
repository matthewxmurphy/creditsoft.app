<?php

declare(strict_types=1);

$dataPath = dirname(__DIR__) . '/data/credit-tips.json';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: public, max-age=900, s-maxage=3600');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!is_file($dataPath)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Credit tips source is not configured.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$payload = (string) file_get_contents($dataPath);
$decoded = json_decode($payload, true);

if (!is_array($decoded)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Credit tips source is invalid JSON.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
