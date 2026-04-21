<?php
declare(strict_types=1);

require_once __DIR__ . '/admin/bootstrap.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function creditsoft_login_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);

    return (bool) $stmt->fetchColumn();
}

function creditsoft_login_portal_account(string $email): ?array
{
    $pdo = cs_site_admin_db();

    if (! $pdo instanceof PDO || ! creditsoft_login_table_exists($pdo, 'customer_portal_accounts')) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM customer_portal_accounts WHERE LOWER(email) = LOWER(?) LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

$submittedEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
$submittedPassword = (string) ($_POST['password'] ?? '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($submittedEmail !== '' && filter_var($submittedEmail, FILTER_VALIDATE_EMAIL)) {
        if (cs_site_admin_find_user($submittedEmail)) {
            header('Location: ' . cs_site_admin_url('/login', ['email' => $submittedEmail]));
            exit;
        }

        $portalAccount = creditsoft_login_portal_account($submittedEmail);
        if (is_array($portalAccount) && trim((string) ($portalAccount['password_hash'] ?? '')) !== '') {
            if ($submittedPassword === '') {
                $error = 'Enter your CreditSoft portal password.';
            } elseif (! password_verify($submittedPassword, (string) $portalAccount['password_hash'])) {
                $error = 'That portal password did not match.';
            } else {
                $_SESSION['creditsoft_portal_email'] = $submittedEmail;

                $pdo = cs_site_admin_db();
                if ($pdo instanceof PDO) {
                    $update = $pdo->prepare('UPDATE customer_portal_accounts SET last_login_at = NOW(), updated_at = NOW() WHERE id = ?');
                    $update->execute([(int) $portalAccount['id']]);
                }

                header('Location: /client-portal?email=' . rawurlencode($submittedEmail));
                exit;
            }
        } else {
            header('Location: /client-portal?email=' . rawurlencode($submittedEmail));
            exit;
        }
    } else {
        $error = 'Enter a valid email address.';
    }
}

$page_title = 'Login';
$page_description = 'CreditSoft login.';
$page_hero = false;
require __DIR__ . '/header.php';
?>
<style>
    .login-shell {
        max-width: 560px;
        margin: 0 auto;
        padding: 72px 20px 0;
    }
    .login-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 28px;
        padding: 32px;
        box-shadow: 0 18px 42px rgba(15,23,42,.06);
        display: grid;
        gap: 18px;
    }
    .login-card h1 {
        margin: 0;
        font-size: 38px;
        line-height: 1;
    }
    .login-card p {
        margin: 0;
        color: var(--gray);
    }
    .login-form {
        display: grid;
        gap: 14px;
    }
    .login-form label {
        display: grid;
        gap: 8px;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--gray);
    }
    .login-form input {
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 15px 16px;
        font-size: 16px;
    }
    .login-form input:focus {
        outline: none;
        border-color: rgba(37,99,235,.4);
        box-shadow: 0 0 0 4px rgba(37,99,235,.10);
    }
    .login-form .btn {
        width: 100%;
    }
    .login-error {
        border-radius: 16px;
        padding: 12px 14px;
        border: 1px solid #fecdd3;
        background: #fff1f2;
        color: #9f1239;
        font-weight: 600;
    }
    .login-links {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        font-size: 14px;
    }
    .login-links a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 700;
    }
    .login-links a:hover {
        text-decoration: underline;
    }
</style>

<div class="login-shell">
    <section class="login-card">
        <h1>Login</h1>
        <p>Enter your email to continue. If you finished paid onboarding, use the portal password you created.</p>

        <?php if ($error !== ''): ?>
            <div class="login-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form class="login-form" method="post" action="/login" novalidate>
            <label for="email">
                Email
                <input
                    id="email"
                    name="email"
                    type="email"
                    placeholder="you@company.com"
                    value="<?= htmlspecialchars($submittedEmail, ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="username"
                    required
                >
            </label>
            <label for="password">
                Portal password
                <input
                    id="password"
                    name="password"
                    type="password"
                    placeholder="Only required after setup"
                    autocomplete="current-password"
                >
            </label>
            <button class="btn btn-primary" type="submit">Continue</button>
        </form>

        <div class="login-links">
            <a href="<?= htmlspecialchars(cs_site_admin_url('/login'), ENT_QUOTES, 'UTF-8') ?>">Admin login</a>
            <a href="/client-portal">Client portal</a>
        </div>
    </section>
</div>

<?php require __DIR__ . '/footer.php'; ?>
