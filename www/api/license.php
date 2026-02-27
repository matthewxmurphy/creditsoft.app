<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config_path = dirname(__FILE__, 2) . '/credit_config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

$db = null;
if (defined('DB_HOST')) {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Exception $e) {
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
}

function generateLicenseKey($length = 32) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $key = '';
    for ($i = 0; $i < $length; $i++) {
        $key .= $chars[rand(0, strlen($chars) - 1)];
    }
    return chunk_split($key, 4, '-');
}

function logLicenseAction($pdo, $license_id, $action, $details = null) {
    if (!$pdo) return;
    try {
        $stmt = $pdo->prepare("INSERT INTO license_logs (license_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
        $stmt->execute([$license_id, $action, $_SERVER['REMOTE_ADDR'] ?? null, $details]);
    } catch (Exception $e) {
        error_log("License log error: " . $e->getMessage());
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'validate') {
    $key = $_GET['key'] ?? '';
    $key = str_replace('-', '', strtoupper($key));
    
    if (strlen($key) < 20) {
        echo json_encode(['valid' => false, 'error' => 'Invalid license key format']);
        exit;
    }
    
    if (!$pdo) {
        echo json_encode(['valid' => true, 'message' => 'License valid (no DB)', 'plan' => 'professional']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM licenses WHERE REPLACE(REPLACE(license_key, '-', ''), ' ', '') = ? AND status = 'active'");
        $stmt->execute([$key]);
        $license = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$license) {
            echo json_encode(['valid' => false, 'error' => 'License not found or inactive']);
            exit;
        }
        
        $now = new DateTime();
        $expires = new DateTime($license['expires_at']);
        $grace_end = (clone $expires)->modify('+4 days');
        
        $stmt = $pdo->prepare("UPDATE licenses SET last_validated = NOW() WHERE id = ?");
        $stmt->execute([$license['id']]);
        
        logLicenseAction($pdo, $license['id'], 'validate', 'License validated');
        
        if ($now > $grace_end) {
            echo json_encode([
                'valid' => false, 
                'error' => 'License expired',
                'expired_at' => $license['expires_at'],
                'grace_expired' => true
            ]);
            exit;
        }
        
        $in_grace = $now > $expires && $now <= $grace_end;
        
        echo json_encode([
            'valid' => true,
            'plan' => $license['plan'],
            'expires_at' => $license['expires_at'],
            'in_grace_period' => $in_grace,
            'grace_days_remaining' => $in_grace ? $grace_end->diff($now)->days : 0
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['valid' => false, 'error' => 'Validation error']);
    }
    exit;
}

if ($method === 'POST' && $action === 'create') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $plan = $input['plan'] ?? 'professional';
    $duration = $input['duration'] ?? 'monthly'; // monthly, yearly, lifetime
    
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['error' => 'Valid email required']);
        exit;
    }
    
    $duration_days = match($duration) {
        'monthly' => 30,
        'yearly' => 365,
        'lifetime' => 3650,
        default => 30
    };
    
    $key = generateLicenseKey();
    $expires = date('Y-m-d H:i:s', strtotime("+{$duration_days} days"));
    
    if (!$pdo) {
        echo json_encode([
            'license_key' => trim($key),
            'expires_at' => $expires,
            'message' => 'License created (no DB)'
        ]);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO licenses (license_key, customer_email, plan, expires_at, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([trim($key), $email, $plan, $expires, $_SERVER['REMOTE_ADDR'] ?? null]);
        $id = $pdo->lastInsertId();
        
        logLicenseAction($pdo, $id, 'activate', 'License created');
        
        if ($duration !== 'lifetime') {
            $amount = match($plan) {
                'starter' => 49,
                'professional' => 99,
                'enterprise' => 199,
                default => 99
            };
            $amount = $duration === 'yearly' ? $amount * 0.8 * 12 : $amount;
            
            $stmt = $pdo->prepare("INSERT INTO license_subscriptions (license_id, billing_cycle, amount, next_billing) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id, $duration, $amount, $expires]);
        }
        
        echo json_encode([
            'license_key' => trim($key),
            'expires_at' => $expires,
            'plan' => $plan,
            'id' => $id
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to create license']);
    }
    exit;
}

if ($method === 'POST' && $action === 'check-grace-abuse') {
    if (!$pdo) {
        echo json_encode(['message' => 'No DB']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $license_id = $input['license_id'] ?? 0;
    
    try {
        $stmt = $pdo->query("
            SELECT 
                l.id, l.customer_email, l.expires_at,
                g.grace_start, g.grace_end, g.days_used, g.payment_status,
                g.created_at as grace_created
            FROM licenses l
            LEFT JOIN license_grace_logs g ON l.id = g.license_id
            WHERE l.id = " . intval($license_id) . "
            ORDER BY g.created_at DESC
            LIMIT 10
        ");
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $abuse_detected = false;
        $total_grace_days = 0;
        
        foreach ($logs as $log) {
            if ($log['grace_start'] && $log['days_used']) {
                $total_grace_days += $log['days_used'];
                if ($log['days_used'] >= 4 || $log['payment_status'] === 'failed') {
                    $abuse_detected = true;
                    logLicenseAction($pdo, $license_id, 'grace_used', 'Potential abuse detected: ' . $log['days_used'] . ' days');
                }
            }
        }
        
        echo json_encode([
            'license_id' => $license_id,
            'grace_logs' => $logs,
            'total_grace_days_used' => $total_grace_days,
            'abuse_detected' => $abuse_detected
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to check grace abuse']);
    }
    exit;
}

if ($method === 'POST' && $action === 'enable-auto-renew') {
    $input = json_decode(file_get_contents('php://input'), true);
    $license_id = $input['license_id'] ?? 0;
    $auto_renew = $input['auto_renew'] ?? true;
    
    if (!$pdo) {
        echo json_encode(['message' => 'No DB']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE license_subscriptions SET auto_renew = ? WHERE license_id = ?");
        $stmt->execute([$auto_renew ? 1 : 0, $license_id]);
        
        logLicenseAction($pdo, $license_id, 'renew', 'Auto-renewal ' . ($auto_renew ? 'enabled' : 'disabled'));
        
        echo json_encode(['success' => true, 'auto_renew' => $auto_renew]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to update']);
    }
    exit;
}

if ($method === 'GET' && $action === 'admin-list') {
    if (!$pdo) {
        echo json_encode(['error' => 'No DB']);
        exit;
    }
    
    try {
        $stmt = $pdo->query("
            SELECT l.*, s.billing_cycle, s.auto_renew, s.next_billing, s.failed_attempts
            FROM licenses l
            LEFT JOIN license_subscriptions s ON l.id = s.license_id
            ORDER BY l.created_at DESC
            LIMIT 50
        ");
        $licenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['licenses' => $licenses]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to fetch']);
    }
    exit;
}

echo json_encode(['error' => 'Invalid request']);
