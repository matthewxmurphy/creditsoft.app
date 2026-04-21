<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (cs_site_admin_is_authenticated()) {
    cs_site_admin_redirect(cs_site_admin_url('/'));
}

$error = '';
$success = cs_site_admin_flash('success');
$redirect = trim((string) ($_GET['redirect'] ?? '/'));
$redirect = $redirect !== '' && str_starts_with($redirect, '/') ? $redirect : '/';
$prefillEmail = cs_site_admin_prefill_email();
$logoUrl = cs_site_public_url('/assets/images/CreditSoft.png');
$rememberMe = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect = trim((string) ($_POST['redirect'] ?? '/'));
    $redirect = $redirect !== '' && str_starts_with($redirect, '/') ? $redirect : '/';
    $rememberMe = ($_POST['remember'] ?? '') === '1';

    if (! cs_site_admin_verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'The sign-in request expired. Please try again.';
    } elseif (! cs_site_admin_verify_turnstile((string) ($_POST['cf-turnstile-response'] ?? ''))) {
        $error = 'Please complete the security check before signing in.';
    } else {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $prefillEmail = $email;

        if ($email === '' || $password === '') {
            $error = 'Enter both your email and password.';
        } else {
            $user = cs_site_admin_attempt_login($email, $password);

            if (! $user) {
                $error = 'Those sign-in details did not match.';
            } else {
                if (cs_site_admin_user_has_two_factor($user)) {
                    $user['_remember'] = $rememberMe;
                    cs_site_admin_begin_pending_login($user, $redirect);
                    cs_site_admin_redirect(cs_site_admin_url('/two-factor-challenge'));
                }

                cs_site_admin_login($user, $rememberMe);
                cs_site_admin_flash('success', 'Signed in to CreditSoft.');
                cs_site_admin_redirect(cs_site_admin_url($redirect));
            }
        }
    }
}

$turnstileSiteKey = cs_site_admin_request_is_trusted()
    ? ''
    : cs_site_admin_turnstile_site_key();
$csrf = cs_site_admin_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CreditSoft Sign In</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php if ($turnstileSiteKey !== ''): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
    <style>
        :root {
            --ink: #111827;
            --muted: #6b7280;
            --line: rgba(15,23,42,.1);
            --card: rgba(255,255,255,.92);
            --ink-solid: #09090b;
            --accent: #f4c542;
            --shadow: 0 28px 56px rgba(15,23,42,.14);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(37,99,235,.18), transparent 32%),
                radial-gradient(circle at bottom right, rgba(22,163,74,.16), transparent 24%),
                linear-gradient(135deg, #f8f5eb 0%, #faf8f1 44%, #f4f1e8 100%);
            display: grid;
            place-items: center;
            padding: 28px;
        }
        .login-shell {
            width: min(480px, 100%);
        }
        .login-card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 18px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(18px);
        }
        .logo { display: flex; align-items: center; justify-content: center; width: 100%; }
        .logo img { height: 82px; width: auto; display: block; filter: drop-shadow(0 16px 24px rgba(0,0,0,.12)); }
        .login-card { padding: 30px; display: grid; gap: 16px; align-content: start; }
        .login-top { display: grid; gap: 6px; text-align: center; }
        .login-top h2 { margin: 0; font-size: 28px; line-height: 1; }
        .login-top p { margin: 0; color: var(--muted); line-height: 1.55; }
        .alert { border-radius: 10px; padding: 13px 15px; font-size: 14px; line-height: 1.5; }
        .alert-error { background: #fff1f2; border: 1px solid #fecdd3; color: #9f1239; }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .field { display: grid; gap: 7px; }
        .field input {
            width: 100%;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: white;
            padding: 14px 15px;
            font-size: 15px;
            color: var(--ink);
        }
        .field input:focus {
            outline: none;
            border-color: rgba(37,99,235,.45);
            box-shadow: 0 0 0 4px rgba(37,99,235,.10);
        }
        .turnstile-wrap { display: flex; justify-content: center; padding: 4px 0 2px; }
        .remember-row {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--muted);
            font-size: 14px;
        }
        .remember-row input {
            width: 16px;
            height: 16px;
            margin: 0;
        }
        .submit {
            width: 100%;
            border: 0;
            border-radius: 10px;
            padding: 14px 18px;
            background: linear-gradient(135deg, var(--ink-solid), #292524);
            color: #fff;
            font-weight: 800;
            font-size: 15px;
            cursor: pointer;
            box-shadow: 0 16px 28px rgba(15,23,42,.18);
        }
        .submit:hover { background: linear-gradient(135deg, #18181b, #1c1917); }
        .login-meta {
            border-top: 1px solid var(--line);
            padding-top: 16px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }
        .login-meta a { color: var(--ink-solid); text-decoration: none; font-weight: 700; }
        .login-meta a:hover { text-decoration: underline; }
        .login-trust {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #92400e;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .login-trust::before {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--accent);
            box-shadow: 0 0 0 4px rgba(244,197,66,.18);
        }
        @media (max-width: 720px) {
            body { padding: 16px; }
            .login-card { border-radius: 16px; padding: 24px; }
        }
    </style>
</head>
<body>
    <div class="login-shell">
        <section class="login-card">
            <a class="logo" href="<?= htmlspecialchars(cs_site_public_url('/'), ENT_QUOTES, 'UTF-8') ?>" aria-label="CreditSoft home">
                <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="CreditSoft">
            </a>
            <div class="login-top">
                <h2>Sign in</h2>
                <p>Use your CreditSoft email and password.</p>
            </div>
            <?php if (cs_site_admin_request_is_trusted()): ?>
                <div class="login-trust">Trusted office network</div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form method="post" action="<?= htmlspecialchars(cs_site_admin_url('/login'), ENT_QUOTES, 'UTF-8') ?>" novalidate>
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">
                <div class="field">
                    <input id="email" name="email" type="email" aria-label="Email" placeholder="Email" value="<?= htmlspecialchars($prefillEmail, ENT_QUOTES, 'UTF-8') ?>" autocomplete="username" required>
                </div>
                <div class="field">
                    <input id="password" name="password" type="password" aria-label="Password" placeholder="Password" autocomplete="current-password" required>
                </div>
                <label class="remember-row" for="remember">
                    <input id="remember" name="remember" type="checkbox" value="1" <?= $rememberMe ? 'checked' : '' ?>>
                    <span>Remember this browser for 90 days</span>
                </label>
                <?php if ($turnstileSiteKey !== ''): ?>
                    <div class="turnstile-wrap">
                        <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($turnstileSiteKey, ENT_QUOTES, 'UTF-8') ?>"></div>
                    </div>
                <?php endif; ?>
                <button class="submit" type="submit">Sign in</button>
            </form>
            <div class="login-meta">
                <a href="<?= htmlspecialchars(cs_site_public_url('/client-portal'), ENT_QUOTES, 'UTF-8') ?>">Client portal</a>
            </div>
        </section>
    </div>
</body>
</html>
