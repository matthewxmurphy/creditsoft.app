<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$sessionAdmin = cs_site_admin_require_login();
$admin = cs_site_admin_find_user((string) ($sessionAdmin['email'] ?? '')) ?: $sessionAdmin;

$error = '';
$success = cs_site_admin_flash('success');
$generatedRecoveryCodes = $_SESSION['creditsoft_site_admin_recovery_codes'] ?? [];
unset($_SESSION['creditsoft_site_admin_recovery_codes']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (! cs_site_admin_verify_csrf($_POST['_csrf'] ?? null)) {
        $error = 'The 2FA form expired. Please try again.';
    } else {
        $action = trim((string) ($_POST['action'] ?? ''));

        if ($action === 'enable') {
            $setup = cs_site_admin_two_factor_setup($admin);
            $code = trim((string) ($_POST['code'] ?? ''));

            if ($code === '') {
                $error = 'Enter the 6-digit code from your authenticator app.';
            } elseif (! cs_site_admin_verify_two_factor_code((string) ($setup['secret'] ?? ''), $code)) {
                $error = 'That verification code did not match the QR setup.';
            } else {
                $recoveryCodes = is_array($setup['recovery_codes'] ?? null) ? $setup['recovery_codes'] : [];
                $saved = cs_site_admin_update_user((string) ($admin['email'] ?? ''), [
                    'two_factor_secret' => (string) ($setup['secret'] ?? ''),
                    'two_factor_confirmed_at' => gmdate('c'),
                    'two_factor_recovery_codes' => cs_site_admin_hash_recovery_codes($recoveryCodes),
                ]);

                if (! $saved) {
                    $error = 'The security settings could not be saved yet.';
                } else {
                    $_SESSION['creditsoft_site_admin_recovery_codes'] = $recoveryCodes;
                    cs_site_admin_clear_two_factor_setup();
                    cs_site_admin_flash('success', 'Two-factor authentication is now enabled for the website admin lane.');
                    cs_site_admin_redirect(cs_site_admin_url('/two-factor'));
                }
            }
        } elseif ($action === 'disable') {
            $saved = cs_site_admin_update_user((string) ($admin['email'] ?? ''), [
                'two_factor_secret' => '',
                'two_factor_confirmed_at' => null,
                'two_factor_recovery_codes' => [],
            ]);

            if (! $saved) {
                $error = 'Two-factor authentication could not be disabled yet.';
            } else {
                cs_site_admin_clear_two_factor_setup();
                cs_site_admin_flash('success', 'Two-factor authentication has been disabled for this admin account.');
                cs_site_admin_redirect(cs_site_admin_url('/two-factor'));
            }
        } elseif ($action === 'regenerate') {
            $recoveryCodes = cs_site_admin_generate_recovery_codes();
            $saved = cs_site_admin_update_user((string) ($admin['email'] ?? ''), [
                'two_factor_recovery_codes' => cs_site_admin_hash_recovery_codes($recoveryCodes),
            ]);

            if (! $saved) {
                $error = 'Recovery codes could not be regenerated yet.';
            } else {
                $_SESSION['creditsoft_site_admin_recovery_codes'] = $recoveryCodes;
                cs_site_admin_flash('success', 'New recovery codes are ready. Save them before you leave this page.');
                cs_site_admin_redirect(cs_site_admin_url('/two-factor'));
            }
        } elseif ($action === 'refresh') {
            cs_site_admin_two_factor_setup($admin, true);
            cs_site_admin_flash('success', 'A fresh QR code is ready.');
            cs_site_admin_redirect(cs_site_admin_url('/two-factor'));
        }
    }
}

$admin = cs_site_admin_find_user((string) ($sessionAdmin['email'] ?? '')) ?: $sessionAdmin;
$enabled = cs_site_admin_user_has_two_factor($admin);
$csrf = cs_site_admin_csrf_token();
$logoUrl = cs_site_public_url('/assets/images/CreditSoft.png');
$setup = $enabled ? [] : cs_site_admin_two_factor_setup($admin);
$qrSvg = $enabled ? '' : cs_site_admin_two_factor_qr_svg((string) ($admin['email'] ?? ''), (string) ($setup['secret'] ?? ''));
$manualKey = $enabled ? '' : (string) ($setup['secret'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CreditSoft Admin 2FA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #111827;
            --muted: #6b7280;
            --line: rgba(15,23,42,.1);
            --card: rgba(255,255,255,.95);
            --surface: rgba(248,245,235,.82);
            --shadow: 0 24px 54px rgba(15,23,42,.10);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(37,99,235,.10), transparent 28%),
                radial-gradient(circle at bottom right, rgba(244,197,66,.20), transparent 20%),
                linear-gradient(135deg, #f8f5eb 0%, #faf8f1 44%, #f4f1e8 100%);
            padding: 32px 20px;
        }
        .shell { max-width: 1120px; margin: 0 auto; display: grid; gap: 24px; }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 20px 24px;
            border-radius: 28px;
            border: 1px solid var(--line);
            background: var(--card);
            box-shadow: var(--shadow);
        }
        .brand { display: flex; align-items: center; gap: 16px; }
        .brand img { height: 54px; width: auto; display: block; }
        .eyebrow { font-size: 11px; letter-spacing: .22em; text-transform: uppercase; color: #9ca3af; font-weight: 800; }
        .title { font-size: 28px; font-weight: 800; line-height: 1.05; }
        .subtitle { color: var(--muted); line-height: 1.6; }
        .top-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .ghost-link, .solid-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border-radius: 999px;
            padding: 12px 18px;
            font-size: 14px;
            font-weight: 800;
        }
        .ghost-link { border: 1px solid var(--line); color: var(--ink); background: white; }
        .solid-link { border: 1px solid transparent; color: white; background: #09090b; }
        .grid { display: grid; gap: 24px; grid-template-columns: minmax(320px, 420px) minmax(0, 1fr); }
        .card {
            border-radius: 30px;
            border: 1px solid var(--line);
            background: var(--card);
            box-shadow: var(--shadow);
            padding: 26px;
            display: grid;
            gap: 18px;
        }
        .card h2, .card h3 { margin: 0; }
        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            border-radius: 999px;
            padding: 9px 14px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .status.is-live { background: #ecfdf5; color: #166534; border: 1px solid #bbf7d0; }
        .status.is-setup { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .message {
            border-radius: 18px;
            padding: 14px 16px;
            font-size: 14px;
            line-height: 1.6;
        }
        .message.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .message.error { background: #fff1f2; border: 1px solid #fecdd3; color: #9f1239; }
        .qr-frame {
            width: 100%;
            max-width: 300px;
            aspect-ratio: 1;
            padding: 22px;
            border-radius: 24px;
            border: 1px solid var(--line);
            background: white;
            display: grid;
            place-items: center;
        }
        .qr-frame svg { width: 100%; height: auto; display: block; }
        .field { display: grid; gap: 8px; }
        .field label {
            font-size: 12px;
            letter-spacing: .14em;
            text-transform: uppercase;
            font-weight: 800;
            color: var(--muted);
        }
        .field input {
            width: 100%;
            border-radius: 18px;
            border: 1px solid var(--line);
            background: white;
            padding: 15px 16px;
            font-size: 15px;
            color: var(--ink);
        }
        .manual-key {
            padding: 16px 18px;
            border-radius: 20px;
            border: 1px dashed rgba(15,23,42,.18);
            background: var(--surface);
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 15px;
            letter-spacing: .14em;
            word-break: break-all;
        }
        .button-row { display: flex; gap: 12px; flex-wrap: wrap; }
        .button-row button {
            border: 0;
            border-radius: 999px;
            padding: 13px 18px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
        }
        .button-row .primary { color: white; background: #09090b; }
        .button-row .secondary { color: var(--ink); background: #f5f5f4; border: 1px solid var(--line); }
        .button-row .danger { color: #991b1b; background: #fef2f2; border: 1px solid #fecaca; }
        .recovery-list {
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            padding: 18px;
            border-radius: 24px;
            background: var(--surface);
            border: 1px solid var(--line);
        }
        .recovery-item {
            padding: 12px 14px;
            border-radius: 16px;
            background: white;
            border: 1px solid var(--line);
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 14px;
            letter-spacing: .12em;
            text-align: center;
        }
        ul {
            margin: 0;
            padding-left: 18px;
            color: var(--muted);
            line-height: 1.7;
        }
        @media (max-width: 900px) {
            .grid { grid-template-columns: 1fr; }
            .topbar { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <div class="brand">
                <a href="<?= htmlspecialchars(cs_site_admin_url('/'), ENT_QUOTES, 'UTF-8') ?>" aria-label="CreditSoft site admin home">
                    <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="CreditSoft">
                </a>
                <div>
                    <div class="eyebrow">CreditSoft site admin</div>
                    <div class="title">Two-factor authentication</div>
                    <div class="subtitle">Lock the website admin lane with a QR-code setup that works with Apple Passwords, Google Authenticator, and Microsoft Authenticator.</div>
                </div>
            </div>
            <div class="top-actions">
                <a class="ghost-link" href="<?= htmlspecialchars(cs_site_admin_url('/'), ENT_QUOTES, 'UTF-8') ?>">Back to admin</a>
                <a class="solid-link" href="<?= htmlspecialchars(cs_site_admin_url('/logout'), ENT_QUOTES, 'UTF-8') ?>">Sign out</a>
            </div>
        </header>

        <?php if ($success): ?>
            <div class="message success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="message error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="grid">
            <section class="card">
                <div class="status <?= $enabled ? 'is-live' : 'is-setup' ?>">
                    <?= $enabled ? '2FA enabled' : 'Setup required' ?>
                </div>
                <?php if ($enabled): ?>
                    <h2>This admin account is protected</h2>
                    <p class="subtitle">You will be prompted for a code after your email and password. Trusted Tailscale traffic can skip the bot check, but not the second factor.</p>
                    <div class="button-row">
                        <form method="post" action="<?= htmlspecialchars(cs_site_admin_url('/two-factor'), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="regenerate">
                            <button class="secondary" type="submit">Regenerate recovery codes</button>
                        </form>
                        <form method="post" action="<?= htmlspecialchars(cs_site_admin_url('/two-factor'), ENT_QUOTES, 'UTF-8') ?>" onsubmit="return confirm('Disable two-factor authentication for this admin account?');">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="disable">
                            <button class="danger" type="submit">Disable 2FA</button>
                        </form>
                    </div>
                <?php else: ?>
                    <h2>Scan the QR code</h2>
                    <p class="subtitle">Use the authenticator or verification-code scanner on your iPhone, or scan it with Google Authenticator or Microsoft Authenticator.</p>
                    <div class="qr-frame"><?= $qrSvg ?></div>
                    <div class="field">
                        <label>Manual setup key</label>
                        <div class="manual-key"><?= htmlspecialchars($manualKey, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <form method="post" action="<?= htmlspecialchars(cs_site_admin_url('/two-factor'), ENT_QUOTES, 'UTF-8') ?>" novalidate>
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="enable">
                        <div class="field">
                            <label for="code">Verification code</label>
                            <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" required>
                        </div>
                        <div class="button-row">
                            <button class="primary" type="submit">Enable 2FA</button>
                        </div>
                    </form>
                    <form method="post" action="<?= htmlspecialchars(cs_site_admin_url('/two-factor'), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="refresh">
                        <div class="button-row">
                            <button class="secondary" type="submit">Refresh QR code</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>

            <section class="card">
                <h3>Recovery codes</h3>
                <p class="subtitle">Recovery codes are the break-glass path if the phone with your authenticator is unavailable. Save them in Apple Passwords or another password manager.</p>
                <?php if ($generatedRecoveryCodes !== []): ?>
                    <div class="recovery-list">
                        <?php foreach ($generatedRecoveryCodes as $code): ?>
                            <div class="recovery-item"><?= htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="message" style="background: var(--surface); border: 1px solid var(--line); color: var(--muted);">
                        Recovery codes are only shown right after setup or regeneration so they can be stored once and kept out of view later.
                    </div>
                <?php endif; ?>

                <h3>What this gives you</h3>
                <ul>
                    <li>A QR code any standard TOTP app can scan.</li>
                    <li>Compatibility with Apple Passwords verification codes on iPhone.</li>
                    <li>Compatibility with Google Authenticator and Microsoft Authenticator.</li>
                    <li>A second factor for the website admin lane without changing the intranet auth model.</li>
                </ul>
            </section>
        </div>
    </div>
</body>
</html>
