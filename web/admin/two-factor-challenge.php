<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (cs_site_admin_is_authenticated()) {
    cs_site_admin_redirect(cs_site_admin_url('/'));
}

$pending = cs_site_admin_pending_login();
$user = cs_site_admin_pending_login_user();

if (! $pending || ! $user) {
    cs_site_admin_clear_pending_login();
    cs_site_admin_flash('error', 'Your security check expired. Please sign in again.');
    cs_site_admin_redirect(cs_site_admin_url('/login'));
}

$error = '';
$logoUrl = cs_site_public_url('/assets/images/CreditSoft.png');
$csrf = cs_site_admin_csrf_token();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (! cs_site_admin_verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'The security check expired. Please sign in again.';
    } else {
        $code = trim((string) ($_POST['code'] ?? ''));

        if ($code === '') {
            $error = 'Enter the 6-digit code or a recovery code.';
        } else {
            $verified = cs_site_admin_verify_two_factor_code(
                (string) ($user['two_factor_secret'] ?? ''),
                $code
            );

            if (! $verified) {
                $verified = cs_site_admin_verify_recovery_code($user, $code);
            }

            if (! $verified) {
                $error = 'That code did not match. Try the current authenticator code or a recovery code.';
            } else {
                cs_site_admin_login($user, (bool) ($pending['remember'] ?? false));
                cs_site_admin_flash('success', 'Security check complete.');
                cs_site_admin_redirect(cs_site_admin_url((string) ($pending['redirect'] ?? '/')));
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CreditSoft Admin Security Check</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #111827;
            --muted: #6b7280;
            --line: rgba(15,23,42,.12);
            --card: rgba(255,255,255,.94);
            --shadow: 0 28px 56px rgba(15,23,42,.12);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(37,99,235,.14), transparent 32%),
                radial-gradient(circle at bottom right, rgba(244,197,66,.18), transparent 24%),
                linear-gradient(135deg, #f8f5eb 0%, #faf8f1 44%, #f4f1e8 100%);
            display: grid;
            place-items: center;
            padding: 24px;
        }
        .shell { width: min(460px, 100%); }
        .card {
            display: grid;
            gap: 18px;
            padding: 34px;
            border-radius: 32px;
            border: 1px solid var(--line);
            background: var(--card);
            box-shadow: var(--shadow);
            backdrop-filter: blur(18px);
        }
        .logo img { height: 72px; width: auto; display: block; }
        h1 { margin: 0; font-size: 30px; line-height: 1; }
        p { margin: 0; color: var(--muted); line-height: 1.6; }
        .field { display: grid; gap: 8px; }
        label { font-size: 12px; letter-spacing: .14em; text-transform: uppercase; font-weight: 800; color: var(--muted); }
        input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: white;
            padding: 15px 16px;
            font-size: 18px;
            letter-spacing: .18em;
            text-transform: uppercase;
        }
        input:focus {
            outline: none;
            border-color: rgba(37,99,235,.45);
            box-shadow: 0 0 0 4px rgba(37,99,235,.10);
        }
        .error {
            border-radius: 16px;
            padding: 12px 14px;
            border: 1px solid #fecdd3;
            background: #fff1f2;
            color: #9f1239;
            font-weight: 600;
        }
        .submit {
            width: 100%;
            border: 0;
            border-radius: 18px;
            padding: 16px 18px;
            background: linear-gradient(135deg, #09090b, #292524);
            color: #fff;
            font-weight: 800;
            font-size: 15px;
            cursor: pointer;
            box-shadow: 0 16px 28px rgba(15,23,42,.18);
        }
        .meta {
            border-top: 1px solid var(--line);
            padding-top: 14px;
            display: grid;
            gap: 6px;
            font-size: 13px;
            color: var(--muted);
        }
        .meta strong { color: var(--ink); }
    </style>
</head>
<body>
    <div class="shell">
        <section class="card">
            <a class="logo" href="<?= htmlspecialchars(cs_site_public_url('/'), ENT_QUOTES, 'UTF-8') ?>" aria-label="CreditSoft home">
                <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="CreditSoft">
            </a>
            <div>
                <h1>Security check</h1>
                <p>Enter the current code from Apple Passwords, Google Authenticator, Microsoft Authenticator, or use one of your recovery codes.</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" action="<?= htmlspecialchars(cs_site_admin_url('/two-factor-challenge'), ENT_QUOTES, 'UTF-8') ?>" novalidate>
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <div class="field">
                    <label for="code">Verification code</label>
                    <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" placeholder="123456 or ABCD-EFGH" autofocus required>
                </div>
                <button class="submit" type="submit">Continue</button>
            </form>

            <div class="meta">
                <div><strong><?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div>This second step stays on even when the office network is trusted. Tailscale lowers friction around bot checks, not identity.</div>
            </div>
        </section>
    </div>
</body>
</html>
