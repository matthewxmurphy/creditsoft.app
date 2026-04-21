<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CreditSoft-License, X-CreditSoft-Idempotency-Key');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

foreach ([
    dirname(__DIR__) . '/../credit_config.php',
    dirname(__DIR__, 2) . '/credit_config.php',
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/../credit_config.php',
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/credit_config.php',
    dirname(__DIR__, 2) . '/web-meta/credit_config.php',
] as $configPath) {
    if ($configPath && file_exists($configPath)) {
        require_once $configPath;
        break;
    }
}

function creditsoft_billing_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function creditsoft_billing_db(): ?PDO
{
    if (
        ! defined('DB_HOST')
        || ! defined('DB_NAME')
        || ! defined('DB_USER')
        || ! defined('DB_PASS')
    ) {
        return null;
    }

    try {
        return new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Throwable) {
        return null;
    }
}

function creditsoft_billing_license_code(array $input): string
{
    $value = $input['license_code']
        ?? $input['licenseCode']
        ?? ($_SERVER['HTTP_X_CREDITSOFT_LICENSE'] ?? '');

    return strtoupper(trim((string) $value));
}

function creditsoft_billing_string(array $input, string $key, string $fallback = ''): string
{
    $value = $input[$key] ?? $fallback;

    return is_scalar($value) ? trim((string) $value) : $fallback;
}

function creditsoft_billing_object(array $input, string $key): array
{
    $value = $input[$key] ?? [];

    return is_array($value) ? $value : [];
}

function creditsoft_billing_sanitize(array $payload): array
{
    $blocked = '/password|passcode|ssn|social security|secret|token|security answer|security question/i';
    $clean = [];

    foreach ($payload as $key => $value) {
        if (preg_match($blocked, (string) $key)) {
            continue;
        }

        if (is_array($value)) {
            $clean[$key] = creditsoft_billing_sanitize($value);
        } elseif (is_scalar($value) || $value === null) {
            $text = is_string($value) ? trim($value) : $value;
            $clean[$key] = is_string($text) ? mb_substr($text, 0, 1000) : $text;
        }
    }

    return $clean;
}

function creditsoft_billing_normalized_license(string $licenseCode): string
{
    return preg_replace('/[^A-Z0-9]/', '', strtoupper($licenseCode)) ?: '';
}

function creditsoft_billing_find_license(PDO $pdo, string $licenseCode): ?array
{
    try {
        $stmt = $pdo->prepare(
            "SELECT id, license_key, customer_email, customer_name, plan, status
             FROM licenses
             WHERE REPLACE(REPLACE(UPPER(license_key), '-', ''), ' ', '') = ?
             LIMIT 1"
        );
        $stmt->execute([creditsoft_billing_normalized_license($licenseCode)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    } catch (Throwable) {
        return null;
    }
}

function creditsoft_billing_ensure_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS license_billing_intelligence (
            id INT AUTO_INCREMENT PRIMARY KEY,
            license_id INT DEFAULT NULL,
            license_key VARCHAR(128) NOT NULL,
            idempotency_key VARCHAR(128) NOT NULL,
            source_system VARCHAR(128) NOT NULL,
            capture_type VARCHAR(128) NOT NULL,
            customer_name VARCHAR(255) DEFAULT NULL,
            client_cuid VARCHAR(128) DEFAULT NULL,
            amount DECIMAL(10,2) DEFAULT NULL,
            status VARCHAR(128) DEFAULT NULL,
            gateway_name VARCHAR(128) DEFAULT NULL,
            reference VARCHAR(255) DEFAULT NULL,
            paid_at DATETIME DEFAULT NULL,
            payload_json LONGTEXT NOT NULL,
            ip_address VARCHAR(64) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_billing_intelligence_idempotency (idempotency_key),
            INDEX idx_billing_intelligence_license (license_key),
            INDEX idx_billing_intelligence_reference (reference)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function creditsoft_billing_fallback_store(array $row): int
{
    $path = dirname(__DIR__) . '/data/license-billing-intelligence.json';
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        return 0;
    }

    $existing = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
    $rows = is_array($existing) ? array_values(array_filter($existing, 'is_array')) : [];
    $rows = array_values(array_filter(
        $rows,
        fn (array $item): bool => (string) ($item['idempotency_key'] ?? '') !== (string) $row['idempotency_key']
    ));
    array_unshift($rows, $row);

    file_put_contents($path, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);

    return count($rows);
}

$raw = file_get_contents('php://input') ?: '';
$input = json_decode($raw, true);

if (! is_array($input)) {
    creditsoft_billing_json(400, ['ok' => false, 'error' => 'A JSON payload is required.']);
}

$licenseCode = creditsoft_billing_license_code($input);
$idempotencyKey = creditsoft_billing_string($input, 'idempotency_key')
    ?: creditsoft_billing_string($input, 'idempotencyKey')
    ?: trim((string) ($_SERVER['HTTP_X_CREDITSOFT_IDEMPOTENCY_KEY'] ?? ''));

if ($licenseCode === '' || ! preg_match('/^[A-Z0-9]{3,12}(?:-[A-Z0-9]{3,12}){1,8}$/', $licenseCode)) {
    creditsoft_billing_json(400, ['ok' => false, 'error' => 'A valid license_code is required.']);
}

if ($idempotencyKey === '') {
    creditsoft_billing_json(400, ['ok' => false, 'error' => 'idempotency_key is required.']);
}

$client = creditsoft_billing_object($input, 'client');
$billing = creditsoft_billing_object($input, 'billing');
$payload = creditsoft_billing_sanitize($input);
$paidAt = trim((string) ($billing['paid_at'] ?? $billing['paidAt'] ?? ''));

if ($paidAt !== '') {
    try {
        $paidAt = (new DateTime($paidAt))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        $paidAt = '';
    }
}

$row = [
    'license_key' => $licenseCode,
    'idempotency_key' => $idempotencyKey,
    'source_system' => creditsoft_billing_string($input, 'source_system', 'unknown'),
    'capture_type' => creditsoft_billing_string($input, 'capture_type', 'legacy_billing'),
    'customer_name' => trim((string) ($client['display_name'] ?? $client['displayName'] ?? '')) ?: null,
    'client_cuid' => trim((string) ($client['cuid'] ?? '')) ?: null,
    'amount' => isset($billing['amount']) && is_numeric($billing['amount']) ? (float) $billing['amount'] : null,
    'status' => trim((string) ($billing['status'] ?? '')) ?: null,
    'gateway_name' => trim((string) ($billing['gateway_name'] ?? $billing['gatewayName'] ?? '')) ?: null,
    'reference' => trim((string) ($billing['reference'] ?? '')) ?: null,
    'paid_at' => $paidAt ?: null,
    'payload' => $payload,
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    'created_at' => gmdate('c'),
];

$pdo = creditsoft_billing_db();
$license = $pdo ? creditsoft_billing_find_license($pdo, $licenseCode) : null;
$row['license_id'] = is_array($license) ? (int) ($license['id'] ?? 0) : null;
$row['license_verified'] = is_array($license);

if ($pdo instanceof PDO) {
    try {
        creditsoft_billing_ensure_table($pdo);
        $stmt = $pdo->prepare(
            "INSERT INTO license_billing_intelligence
                (license_id, license_key, idempotency_key, source_system, capture_type, customer_name, client_cuid, amount, status, gateway_name, reference, paid_at, payload_json, ip_address)
             VALUES
                (:license_id, :license_key, :idempotency_key, :source_system, :capture_type, :customer_name, :client_cuid, :amount, :status, :gateway_name, :reference, :paid_at, :payload_json, :ip_address)
             ON DUPLICATE KEY UPDATE
                license_id = VALUES(license_id),
                source_system = VALUES(source_system),
                capture_type = VALUES(capture_type),
                customer_name = VALUES(customer_name),
                client_cuid = VALUES(client_cuid),
                amount = VALUES(amount),
                status = VALUES(status),
                gateway_name = VALUES(gateway_name),
                reference = VALUES(reference),
                paid_at = VALUES(paid_at),
                payload_json = VALUES(payload_json),
                ip_address = VALUES(ip_address),
                updated_at = NOW()"
        );
        $stmt->execute([
            ':license_id' => $row['license_id'] ?: null,
            ':license_key' => $row['license_key'],
            ':idempotency_key' => $row['idempotency_key'],
            ':source_system' => $row['source_system'],
            ':capture_type' => $row['capture_type'],
            ':customer_name' => $row['customer_name'],
            ':client_cuid' => $row['client_cuid'],
            ':amount' => $row['amount'],
            ':status' => $row['status'],
            ':gateway_name' => $row['gateway_name'],
            ':reference' => $row['reference'],
            ':paid_at' => $row['paid_at'],
            ':payload_json' => json_encode($row['payload'], JSON_UNESCAPED_SLASHES),
            ':ip_address' => $row['ip_address'],
        ]);

        creditsoft_billing_json(200, [
            'ok' => true,
            'source' => 'database',
            'license_verified' => $row['license_verified'],
            'idempotency_key' => $idempotencyKey,
        ]);
    } catch (Throwable $exception) {
        $row['database_error'] = $exception->getMessage();
    }
}

$count = creditsoft_billing_fallback_store($row);

creditsoft_billing_json(200, [
    'ok' => true,
    'source' => 'fallback',
    'stored_count' => $count,
    'license_verified' => $row['license_verified'],
    'idempotency_key' => $idempotencyKey,
]);
