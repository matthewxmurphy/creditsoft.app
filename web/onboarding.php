<?php
declare(strict_types=1);

$config_path = dirname(__DIR__) . '/credit_config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

require_once __DIR__ . '/lead-intake.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function creditsoft_onboarding_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function creditsoft_onboarding_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);

    return (bool) $stmt->fetchColumn();
}

function creditsoft_onboarding_ensure_accounts(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `customer_portal_accounts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `name` VARCHAR(255) DEFAULT NULL,
            `company` VARCHAR(255) DEFAULT NULL,
            `phone` VARCHAR(80) DEFAULT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `license_id` INT DEFAULT NULL,
            `license_key` VARCHAR(128) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `last_login_at` DATETIME DEFAULT NULL,
            INDEX `idx_license_id` (`license_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function creditsoft_onboarding_license(PDO $pdo, int $licenseId): array
{
    if ($licenseId <= 0 || ! creditsoft_onboarding_table_exists($pdo, 'licenses')) {
        return [];
    }

    $stmt = $pdo->prepare('SELECT id, license_key, plan, expires_at FROM licenses WHERE id = ? LIMIT 1');
    $stmt->execute([$licenseId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : [];
}

function creditsoft_onboarding_date(?string $value): string
{
    if (! $value) {
        return 'Active';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('M j, Y', $timestamp) : 'Active';
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$hash = $token !== '' ? hash('sha256', $token) : '';
$pdo = creditsoft_lead_db();

if ($hash === '' || ! $pdo) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$stmt = $pdo->prepare(
    "SELECT t.*, t.email AS token_email, l.name, l.email AS lead_email, l.phone, l.company, l.source, l.notes
     FROM customer_onboarding_tokens t
     LEFT JOIN leads l ON l.id = t.lead_id
     WHERE t.token_hash = ?
       AND t.expires_at >= NOW()
     LIMIT 1"
);
$stmt->execute([$hash]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (! is_array($row)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$email = strtolower(trim((string) ($row['lead_email'] ?: $row['token_email'])));
$name = trim((string) ($row['name'] ?? ''));
$company = trim((string) ($row['company'] ?? ''));
$phone = trim((string) ($row['phone'] ?? ''));
$licenseId = (int) ($row['license_id'] ?? 0);
$license = creditsoft_onboarding_license($pdo, $licenseId);
$licenseKey = trim((string) ($license['license_key'] ?? ''));
$licensePlan = trim(str_replace(['_', '-'], ' ', (string) ($license['plan'] ?? 'CreditSoft')));
$licensePlan = $licensePlan !== '' ? ucwords($licensePlan) : 'CreditSoft';
$expiresAt = creditsoft_onboarding_date((string) ($license['expires_at'] ?? ''));
$error = '';
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'This setup link is missing a valid customer email.';
    } elseif (strlen($password) < 10) {
        $error = 'Use at least 10 characters for the portal password.';
    } elseif (! hash_equals($password, $confirmPassword)) {
        $error = 'The password confirmation did not match.';
    } else {
        creditsoft_onboarding_ensure_accounts($pdo);
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $account = $pdo->prepare(
            "INSERT INTO customer_portal_accounts (email, name, company, phone, password_hash, license_id, license_key, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                company = VALUES(company),
                phone = VALUES(phone),
                password_hash = VALUES(password_hash),
                license_id = VALUES(license_id),
                license_key = VALUES(license_key),
                updated_at = NOW()"
        );
        $account->execute([
            $email,
            $name !== '' ? $name : null,
            $company !== '' ? $company : null,
            $phone !== '' ? $phone : null,
            $passwordHash,
            $licenseId > 0 ? $licenseId : null,
            $licenseKey !== '' ? $licenseKey : null,
        ]);

        $update = $pdo->prepare('UPDATE customer_onboarding_tokens SET used_at = NOW() WHERE id = ?');
        $update->execute([(int) $row['id']]);

        $_SESSION['creditsoft_portal_email'] = $email;
        $saved = true;
    }
}

$page_title = 'Finish Setup';
$page_description = 'Set your CreditSoft portal password and finish paid onboarding.';
$page_hero = false;
require __DIR__ . '/header.php';
?>
<style>
    .setup-shell { max-width: 1120px; margin: 0 auto; padding: 72px 22px 78px; }
    .setup-grid { display:grid; grid-template-columns:minmax(0, 1.05fr) minmax(320px, .95fr); gap:24px; align-items:start; }
    .setup-card { background:#fff; border:1px solid rgba(120,113,108,.16); border-radius:30px; padding:30px; box-shadow:0 22px 50px rgba(15,23,42,.08); }
    .setup-kicker { color:var(--primary); font-size:12px; font-weight:900; letter-spacing:.16em; text-transform:uppercase; margin-bottom:12px; }
    .setup-card h1 { margin:0 0 12px; font-size:clamp(2.2rem, 5vw, 4rem); line-height:.98; letter-spacing:-.06em; }
    .setup-card h2 { margin:0 0 10px; font-size:28px; letter-spacing:-.04em; }
    .setup-card p { margin:0; color:var(--gray); line-height:1.65; }
    .setup-form { display:grid; gap:14px; margin-top:24px; }
    .setup-form label { display:grid; gap:8px; font-size:13px; font-weight:800; color:#334155; }
    .setup-form input { width:100%; border:1px solid rgba(120,113,108,.20); border-radius:18px; padding:15px 16px; font-size:16px; background:white; }
    .setup-form input:focus { outline:none; border-color:rgba(37,99,235,.45); box-shadow:0 0 0 4px rgba(37,99,235,.10); }
    .setup-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:18px; }
    .setup-btn { display:inline-flex; align-items:center; justify-content:center; min-height:48px; border:0; border-radius:999px; padding:0 18px; background:#111827; color:#fff; font-weight:900; text-decoration:none; cursor:pointer; }
    .setup-btn:hover { color:#fff; text-decoration:none; background:#0f172a; transform:translateY(-1px); }
    .setup-btn.secondary { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
    .setup-btn.secondary:hover { color:#1d4ed8; background:#dbeafe; }
    .setup-error { border:1px solid #fecdd3; background:#fff1f2; color:#9f1239; border-radius:18px; padding:14px 16px; font-weight:700; }
    .setup-success { border:1px solid #86efac; background:#f0fdf4; color:#166534; border-radius:18px; padding:14px 16px; font-weight:800; }
    .receipt-list { display:grid; gap:10px; margin-top:18px; }
    .receipt-row { display:flex; justify-content:space-between; gap:18px; padding:13px 0; border-bottom:1px solid #e5e7eb; color:#111827; font-weight:800; }
    .receipt-row span { color:#64748b; font-size:12px; letter-spacing:.12em; text-transform:uppercase; font-weight:900; }
    .contact-note { margin-top:18px; padding:16px 18px; border-radius:20px; background:#eff6ff; border:1px solid #bfdbfe; color:#1d4ed8; line-height:1.6; }
    .contact-note strong { color:#0f172a; }
    @media (max-width: 860px) { .setup-grid { grid-template-columns:1fr; } .setup-shell { padding-top:50px; } }
</style>

<main class="setup-shell">
    <div class="setup-grid">
        <section class="setup-card">
            <div class="setup-kicker">Paid onboarding</div>
            <?php if ($saved): ?>
                <h1>Password saved.</h1>
                <p>Your CreditSoft portal password is set for <?= creditsoft_onboarding_escape($email) ?>. Open the portal to review billing history and setup status.</p>
                <div class="setup-success" style="margin-top:22px;">Setup is ready. The old subscribe qualification flow is no longer used for paid license onboarding.</div>
                <div class="setup-actions">
                    <a class="setup-btn" href="/client-portal?email=<?= rawurlencode($email) ?>">Open client portal</a>
                    <a class="setup-btn secondary" href="/assets/creditsoft-contact.vcf" download>Download CreditSoft contact</a>
                </div>
            <?php else: ?>
                <h1>Finish your CreditSoft setup.</h1>
                <p>Set the portal password for <?= creditsoft_onboarding_escape($email) ?>. This is the account you will use after a Zelle or Cash App payment activates the license.</p>
                <?php if ($error !== ''): ?>
                    <div class="setup-error" style="margin-top:22px;"><?= creditsoft_onboarding_escape($error) ?></div>
                <?php endif; ?>
                <form class="setup-form" method="post" action="/onboarding" novalidate>
                    <input type="hidden" name="token" value="<?= creditsoft_onboarding_escape($token) ?>">
                    <label for="password">
                        Portal password
                        <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required>
                    </label>
                    <label for="confirm_password">
                        Confirm password
                        <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="10" required>
                    </label>
                    <button class="setup-btn" type="submit">Save password and continue</button>
                </form>
            <?php endif; ?>
        </section>

        <aside class="setup-card">
            <h2>Receipt details</h2>
            <p>This setup link is tied to the license generated from the matched payment email.</p>
            <div class="receipt-list">
                <div class="receipt-row"><span>Email</span><strong><?= creditsoft_onboarding_escape($email) ?></strong></div>
                <div class="receipt-row"><span>Plan</span><strong><?= creditsoft_onboarding_escape($licensePlan) ?></strong></div>
                <div class="receipt-row"><span>License</span><strong><?= creditsoft_onboarding_escape($licenseKey !== '' ? $licenseKey : 'Active') ?></strong></div>
                <div class="receipt-row"><span>Expires</span><strong><?= creditsoft_onboarding_escape($expiresAt) ?></strong></div>
            </div>
            <div class="contact-note">
                <strong>Keep CreditSoft out of spam.</strong><br>
                Add hello@creditsoft.app as a contact. The contact card includes the support address and Zelle payment address.
            </div>
            <div class="setup-actions">
                <a class="setup-btn secondary" href="/assets/creditsoft-contact.vcf" download>Download contact card</a>
            </div>
        </aside>
    </div>
</main>

<?php require __DIR__ . '/footer.php'; ?>
