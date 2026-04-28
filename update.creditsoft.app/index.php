<?php
require __DIR__.'/common.php';

$feed = update_creditsoft_load_feed();
$officeVersion = trim((string) ($feed['latest_version'] ?? '2026.4.27.1'));
$officeDownloadUrl = trim((string) ($feed['download_url'] ?? ''));

if ($officeDownloadUrl === '') {
    $officeDownloadUrl = update_creditsoft_site_url('downloads/creditsoft-office-v'.$officeVersion.'.zip');
}

$companion = is_array($feed['browser_companion'] ?? null) ? $feed['browser_companion'] : [];
$companionVersion = trim((string) ($companion['latest_version'] ?? $feed['browser_companion_version'] ?? $officeVersion));
$companionDownloadUrl = trim((string) ($companion['download_url'] ?? $feed['browser_companion_url'] ?? ''));

if ($companionDownloadUrl === '') {
    $companionDownloadUrl = update_creditsoft_site_url('downloads/creditsoft-browser-companion-v'.$companionVersion.'.zip');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Update CreditSoft</title>
    <meta name="description" content="CreditSoft renewal, checkout, and browser companion update lane.">
    <link rel="stylesheet" href="<?= htmlspecialchars(update_creditsoft_site_url('assets/styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <div class="shell">
        <div class="topbar">
            <a class="brand" href="<?= htmlspecialchars(update_creditsoft_site_url(), ENT_QUOTES, 'UTF-8') ?>">
                <p class="eyebrow">Update lane</p>
                <h1 class="title">Renew, update, or finish checkout.</h1>
            </a>
            <a class="nav-pill" href="https://creditsoft.app/pricing">Back to pricing</a>
        </div>

        <div class="grid">
            <section class="panel">
                <h2>Office checkout</h2>
                <p>Use this lane when someone chooses a plan from the public pricing page. It shows the Zelle QR code, tells them payments can take up to 8 hours to process, and collects the email or phone the payment is coming from so we can match it cleanly.</p>
                <div class="hero-links">
                    <a class="hero-link" href="<?= htmlspecialchars(update_creditsoft_site_url('checkout.php?plan=professional'), ENT_QUOTES, 'UTF-8') ?>">
                        <strong>Enterprise checkout</strong>
                        <span>Monthly or yearly office checkout with payment-match details.</span>
                    </a>
                    <a class="hero-link" href="<?= htmlspecialchars(update_creditsoft_site_url('checkout.php?plan=enterprise'), ENT_QUOTES, 'UTF-8') ?>">
                        <strong>Enterprise Pro checkout</strong>
                        <span>Includes the browser companion / API lane messaging.</span>
                    </a>
                </div>
            </section>

            <section class="panel">
                <h2>Renew an expired office</h2>
                <p>Use the renewal lane when the intranet license expires or the browser companion needs to be re-enabled. It gives the Zelle QR code, license memo details, and a simple payment-notice form tied to the office.</p>
                <div class="hero-links">
                    <a class="hero-link" href="<?= htmlspecialchars(update_creditsoft_site_url('renew.php'), ENT_QUOTES, 'UTF-8') ?>">
                        <strong>Open renewal page</strong>
                        <span>QR payment, office details, and a clean renewal notice flow.</span>
                    </a>
                    <a class="hero-link" href="https://api.creditsoft.app/">
                        <strong>API docs</strong>
                        <span>License/API documentation for the office and browser companion lanes.</span>
                    </a>
                </div>
            </section>
        </div>

        <div class="grid" style="margin-top: 18px;">
            <section class="panel">
                <h2>Download the current office package</h2>
                <p>The update lane now has a real staged package artifact so the intranet feed can point at something concrete instead of the site root.</p>
                <div class="hero-links">
                    <a class="hero-link" href="<?= htmlspecialchars($officeDownloadUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <strong>Download CreditSoft Office <?= htmlspecialchars($officeVersion, ENT_QUOTES, 'UTF-8') ?></strong>
                        <span>Current date-versioned office package with the restored client workspace, DisputeFox import lanes, the client process checklist, grouped expandable left rail, HR and Payroll lanes, PostgreSQL cutover fixes, the real /cto workspace, /violations review queue, live Zelle QR renewal flow, Docker node runtime, local router, and optional CRM launch bridge.</span>
                    </a>
                </div>
            </section>

            <section class="panel">
                <h2>Customer browser companion</h2>
                <p>The customer companion package now lives in the same update lane so downloads and recovery paths stay together.</p>
                <div class="hero-links">
                    <a class="hero-link" href="<?= htmlspecialchars($companionDownloadUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <strong>Download browser companion <?= htmlspecialchars($companionVersion, ENT_QUOTES, 'UTF-8') ?></strong>
                        <span>Current date-versioned side-panel companion build with DisputeFox import buttons, client and lead profile processing, invoice detail capture, provider report pulls, licensed client sync, and AutoFox automation discovery for OPS review.</span>
                    </a>
                </div>
            </section>
        </div>

        <p class="footer-note">This package is staged for <code>updates.creditsoft.app</code> and can also be previewed directly from the current website while DNS catches up.</p>
    </div>
</body>
</html>
