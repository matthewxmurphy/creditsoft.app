<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

foreach ([
    dirname(__DIR__) . '/../credit_config.php',
    dirname(__DIR__, 2) . '/credit_config.php',
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/../credit_config.php',
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/credit_config.php',
    dirname(__DIR__, 2) . '/web-meta/credit_config.php',
] as $config_path) {
    if ($config_path && file_exists($config_path)) {
        require_once $config_path;
        break;
    }
}

require_once dirname(__DIR__) . '/meta-conversions-api.php';

function dbConnection(): ?PDO
{
    if (!defined('DB_HOST')) {
        return null;
    }

    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    return $pdo;
}

function normalizeSource(string $source): string
{
    $normalized = strtolower(trim($source));

    return match ($normalized) {
        'quiz', 'knowledge_quiz' => 'knowledge_quiz',
        'lawsuit_test', 'issue_check', 'fcra_fdcpa' => 'lawsuit_test',
        default => $normalized !== '' ? $normalized : 'website',
    };
}

function assessmentLabel(string $source): string
{
    return match ($source) {
        'knowledge_quiz' => 'Knowledge Quiz',
        'lawsuit_test' => 'FCRA / FDCPA Lead Check',
        default => ucwords(str_replace('_', ' ', $source)),
    };
}

function defaultMaxScore(string $source): int
{
    return match ($source) {
        'knowledge_quiz' => 10,
        'lawsuit_test' => 16,
        default => 0,
    };
}

function ensureAssessmentTables(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS assessment_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) DEFAULT NULL,
            email VARCHAR(255) NOT NULL,
            source VARCHAR(100) NOT NULL,
            assessment_label VARCHAR(150) DEFAULT NULL,
            score INT DEFAULT NULL,
            max_score INT DEFAULT NULL,
            result_band VARCHAR(255) DEFAULT NULL,
            discount_percent INT DEFAULT NULL,
            coupon_code VARCHAR(64) DEFAULT NULL,
            metadata_json LONGTEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_assessment_email (email),
            INDEX idx_assessment_source (source),
            INDEX idx_assessment_created (created_at),
            UNIQUE KEY uniq_coupon_code (coupon_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function upsertLead(PDO $pdo, string $name, string $email, string $source, ?int $score): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO leads (name, email, source, score, created_at, updated_at)
         VALUES (?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            name = IF(VALUES(name) <> '', VALUES(name), name),
            source = VALUES(source),
            score = VALUES(score),
            updated_at = NOW()"
    );
    $stmt->execute([$name, $email, $source, $score]);
}

function existingCoupon(PDO $pdo, string $email, string $source): ?array
{
    $stmt = $pdo->prepare(
        "SELECT coupon_code, discount_percent
         FROM assessment_results
         WHERE email = ? AND source = ? AND coupon_code IS NOT NULL
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([$email, $source]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function uniqueCouponCode(PDO $pdo, string $prefix = 'QUIZ'): string
{
    do {
        $code = sprintf('%s-%s', $prefix, strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM assessment_results WHERE coupon_code = ?");
        $stmt->execute([$code]);
        $exists = (int) $stmt->fetchColumn() > 0;
    } while ($exists);

    return $code;
}

function sourceStats(PDO $pdo, string $source): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM assessment_results WHERE source = ?");
    $stmt->execute([$source]);
    return (int) $stmt->fetchColumn();
}

function requireAdminSession(): void
{
    require_once dirname(__DIR__) . '/admin/bootstrap.php';

    if (! function_exists('cs_site_admin_is_authenticated') || ! cs_site_admin_is_authenticated()) {
        http_response_code(401);
        echo json_encode(['error' => 'Admin authentication required']);
        exit;
    }
}

function leadEmailDomainHasMx(string $email): bool
{
    $domain = substr((string) strrchr(strtolower(trim($email)), '@'), 1);

    if ($domain === '') {
        return false;
    }

    return function_exists('checkdnsrr') ? checkdnsrr($domain, 'MX') : true;
}

function turnstileSecret(): string
{
    foreach ([
        'CREDITSOFT_TURNSTILE_SECRET_KEY',
        'TURNSTILE_SECRET_KEY',
        'CLOUDFLARE_TURNSTILE_SECRET_KEY',
    ] as $key) {
        if (defined($key) && trim((string) constant($key)) !== '') {
            return trim((string) constant($key));
        }

        $value = getenv($key);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    return '';
}

function verifyTurnstileToken(string $token): bool
{
    $secret = turnstileSecret();

    if ($secret === '') {
        return true;
    }

    if (trim($token) === '') {
        return false;
    }

    $payload = http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 5,
        ],
    ]);

    $response = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    $decoded = is_string($response) ? json_decode($response, true) : null;

    return is_array($decoded) && (bool) ($decoded['success'] ?? false);
}

try {
    $pdo = dbConnection();
} catch (Exception $e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'admin-list') {
    requireAdminSession();

    if (!$pdo) {
        echo json_encode(['error' => 'Database unavailable']);
        exit;
    }

    ensureAssessmentTables($pdo);

    $stats = [
        'total_results' => 0,
        'knowledge_quiz_total' => 0,
        'lawsuit_test_total' => 0,
        'perfect_scores' => 0,
        'coupons_issued' => 0,
    ];

    $stats['total_results'] = (int) $pdo->query("SELECT COUNT(*) FROM assessment_results")->fetchColumn();
    $stats['knowledge_quiz_total'] = sourceStats($pdo, 'knowledge_quiz');
    $stats['lawsuit_test_total'] = sourceStats($pdo, 'lawsuit_test');
    $stats['perfect_scores'] = (int) $pdo->query("SELECT COUNT(*) FROM assessment_results WHERE source = 'knowledge_quiz' AND score = max_score")->fetchColumn();
    $stats['coupons_issued'] = (int) $pdo->query("SELECT COUNT(*) FROM assessment_results WHERE coupon_code IS NOT NULL")->fetchColumn();

    $resultsStmt = $pdo->query(
        "SELECT id, name, email, source, assessment_label, score, max_score, result_band, discount_percent, coupon_code, created_at
         FROM assessment_results
         ORDER BY id DESC
         LIMIT 250"
    );

    $results = [];
    while ($row = $resultsStmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'source' => $row['source'],
            'assessment_label' => $row['assessment_label'] ?: assessmentLabel($row['source']),
            'score' => $row['score'] === null ? null : (int) $row['score'],
            'max_score' => $row['max_score'] === null ? null : (int) $row['max_score'],
            'result_band' => $row['result_band'],
            'discount_percent' => $row['discount_percent'] === null ? null : (int) $row['discount_percent'],
            'coupon_code' => $row['coupon_code'],
            'created_at' => $row['created_at'],
        ];
    }

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'results' => $results,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$name = trim((string) ($input['name'] ?? ''));
$email = filter_var((string) ($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$source = normalizeSource((string) ($input['source'] ?? 'website'));
$score = isset($input['score']) ? (int) $input['score'] : null;
$maxScore = isset($input['max_score']) ? (int) $input['max_score'] : defaultMaxScore($source);
$resultBand = trim((string) ($input['result_band'] ?? ''));
$assessmentLabel = trim((string) ($input['assessment_label'] ?? assessmentLabel($source)));
$metadata = $input['metadata'] ?? null;

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Valid email required']);
    exit;
}

if (!leadEmailDomainHasMx($email)) {
    echo json_encode(['error' => 'Email domain does not publish mail server DNS yet']);
    exit;
}

$turnstileToken = trim((string) ($input['turnstile_token'] ?? $input['cf-turnstile-response'] ?? ''));

if (!verifyTurnstileToken($turnstileToken)) {
    echo json_encode(['error' => 'Browser check failed']);
    exit;
}

$coupon = null;
$discountPercent = null;
$isPerfectKnowledgeQuiz = $source === 'knowledge_quiz' && $maxScore > 0 && $score !== null && $score >= $maxScore;

if (!$pdo) {
    if ($isPerfectKnowledgeQuiz) {
        $coupon = [
            'code' => 'QUIZ-' . strtoupper(substr(md5($email), 0, 8)),
            'discount_percent' => 25,
        ];
    }

    echo json_encode([
        'success' => true,
        'message' => 'Lead captured (no DB)',
        'coupon' => $coupon,
    ]);
    exit;
}

try {
    ensureAssessmentTables($pdo);
    upsertLead($pdo, $name, $email, $source, $score);

    if ($isPerfectKnowledgeQuiz) {
        $existing = existingCoupon($pdo, $email, $source);
        if ($existing) {
            $coupon = [
                'code' => $existing['coupon_code'],
                'discount_percent' => (int) ($existing['discount_percent'] ?? 25),
            ];
        } else {
            $coupon = [
                'code' => uniqueCouponCode($pdo, 'QUIZ'),
                'discount_percent' => 25,
            ];
        }
        $discountPercent = $coupon['discount_percent'];
    }

    $stmt = $pdo->prepare(
        "INSERT INTO assessment_results
            (name, email, source, assessment_label, score, max_score, result_band, discount_percent, coupon_code, metadata_json, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([
        $name !== '' ? $name : null,
        $email,
        $source,
        $assessmentLabel,
        $score,
        $maxScore > 0 ? $maxScore : null,
        $resultBand !== '' ? $resultBand : null,
        $discountPercent,
        $coupon['code'] ?? null,
        $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES) : null,
    ]);

    $assessmentId = (int) $pdo->lastInsertId();
    creditsoft_meta_capi_send_event('Lead', [
        'email' => $email,
        'external_id' => 'assessment:' . ($assessmentId > 0 ? $assessmentId : ($source . ':' . $email)),
    ], [
        'event_id' => 'assessment-' . ($assessmentId > 0 ? $assessmentId : substr(sha1($source . ':' . $email . ':' . time()), 0, 16)),
        'content_name' => $assessmentLabel !== '' ? $assessmentLabel : assessmentLabel($source),
        'content_category' => $source,
        'status' => $resultBand,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Lead saved',
        'coupon' => $coupon,
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error']);
}
