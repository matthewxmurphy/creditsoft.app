<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? '');
$email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);
$source = trim($input['source'] ?? 'website');
$score = intval($input['score'] ?? 0);

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Valid email required']);
    exit;
}

$config_path = dirname(__DIR__, 2) . '/credit_config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

if (defined('DB_HOST')) {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $stmt = $pdo->prepare("INSERT INTO leads (name, email, source, score, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$name, $email, $source, $score]);
        echo json_encode(['success' => true, 'message' => 'Lead saved']);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Database error']);
    }
} else {
    echo json_encode(['success' => true, 'message' => 'Lead captured (no DB)']);
}
