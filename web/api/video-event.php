<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);

if (! is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$slug = preg_replace('/[^a-z0-9-]/', '', strtolower(trim((string) ($payload['slug'] ?? ''))));
$action = trim((string) ($payload['action'] ?? 'view'));

if ($slug === '' || $action !== 'view') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Missing video details']);
    exit;
}

$storagePath = dirname(__DIR__, 2) . '/web-meta/video-stats.json';
$storageDir = dirname($storagePath);

if (! is_dir($storageDir) && ! mkdir($storageDir, 0775, true) && ! is_dir($storageDir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Storage unavailable']);
    exit;
}

$stats = [];

if (is_file($storagePath)) {
    $decoded = json_decode((string) file_get_contents($storagePath), true);
    if (is_array($decoded)) {
        $stats = $decoded;
    }
}

if (! isset($stats[$slug]) || ! is_array($stats[$slug])) {
    $stats[$slug] = [
        'views' => 0,
        'last_viewed_at' => null,
    ];
}

$stats[$slug]['views'] = (int) ($stats[$slug]['views'] ?? 0) + 1;
$stats[$slug]['last_viewed_at'] = gmdate('c');

$encoded = json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if (! is_string($encoded) || file_put_contents($storagePath, $encoded . PHP_EOL) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save view']);
    exit;
}

echo json_encode([
    'success' => true,
    'slug' => $slug,
    'views' => (int) $stats[$slug]['views'],
    'last_viewed_at' => (string) $stats[$slug]['last_viewed_at'],
]);
