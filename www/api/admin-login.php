<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config_path = dirname(__FILE__, 2) . '/credit_config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Email and password required']);
    exit;
}

$valid_admins = [
    'hello@creditsoft.app' => 'CreditSoft2026!',
    'matthew@creditsoft.app' => 'CreditSoft2026!',
];

if (isset($valid_admins[$email]) && $valid_admins[$email] === $password) {
    $token = bin2hex(random_bytes(32));
    
    echo json_encode([
        'success' => true,
        'token' => $token,
        'admin' => [
            'email' => $email,
            'name' => 'Admin'
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
}
