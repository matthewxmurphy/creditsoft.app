<?php
require_once __DIR__ . '/admin/bootstrap.php';
require_once __DIR__ . '/admin/zelle-payments.php';

function creditsoft_portal_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function creditsoft_portal_money($amount): string
{
    if ($amount === null || $amount === '' || ! is_numeric($amount)) {
        return '-';
    }

    return '$' . number_format((float) $amount, 2);
}

function creditsoft_portal_date(?string $value): string
{
    if (! $value) {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('M j, Y', $timestamp) : '-';
}

function creditsoft_portal_label(string $value): string
{
    $value = trim(str_replace(['_', '-'], ' ', $value));

    return $value === '' ? '-' : ucwords($value);
}

function creditsoft_portal_billing_history(string $email): array
{
    $email = strtolower(trim($email));

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [];
    }

    $pdo = cs_site_admin_db();

    if (! $pdo instanceof PDO) {
        return [];
    }

    try {
        if (! cs_site_zelle_table_exists($pdo, 'zelle_payment_messages')) {
            return [];
        }

        $stmt = $pdo->prepare(
            "SELECT received_at, created_at, amount, expected_amount, balance_due, transaction_id, status, payment_status, plan_key, billing, license_key, email_sent_at, balance_email_sent_at
             FROM zelle_payment_messages
             WHERE LOWER(sender_email) = LOWER(?)
             ORDER BY COALESCE(received_at, created_at) DESC
             LIMIT 12"
        );
        $stmt->execute([$email]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $exception) {
        return [];
    }
}

$portalEmail = strtolower(trim((string) ($_GET['email'] ?? '')));
$portalEmail = filter_var($portalEmail, FILTER_VALIDATE_EMAIL) ? $portalEmail : '';
$billingHistory = $portalEmail !== '' ? creditsoft_portal_billing_history($portalEmail) : [];

$page_title = 'Client Portal + Branded Website';
$page_description = 'Two-layer client portal: local intranet inside, branded website and client portal outside. Managed website builds start at $495+ and tie into CreditSoft CRM/intranet flows.';
$page_hero = true;
$hero_class = 'hero--left';
$hero_title = 'Client Portal';
$hero_subtitle = 'Two layers of security: the local intranet for casework, and a branded public site for leads, portal access, and client updates.';
require __DIR__ . '/header.php';
?>
<style>
    .hero h1 { font-size: 38px; font-weight: 700; margin-bottom: 12px; }
    .hero p { max-width: 700px; }
    .container { max-width: 900px; margin: 0 auto; padding: 40px 20px; }
    .intro { background: white; padding: 32px; border-radius: 16px; margin-bottom: 24px; }
    .intro h2 { font-size: 24px; margin-bottom: 12px; }
    .intro p { color: var(--gray); }
    .offer-callout { margin-top:18px; padding:18px 20px; border-radius:14px; background:linear-gradient(135deg,#eff6ff,#ffffff); border:1px solid #bfdbfe; }
    .offer-callout strong { display:block; font-size:16px; margin-bottom:6px; }
    .offer-callout p { margin:0 0 12px; color:var(--gray); }
    .offer-callout a { font-weight:600; }
    .access-split { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-top:20px; }
    .access-tile { border-radius:16px; border:1px solid var(--border); background:#f8fafc; padding:18px 20px; }
    .access-tile strong { display:block; font-size:16px; margin-bottom:6px; }
    .access-tile p { margin:0 0 12px; color:var(--gray); }
    .access-tile a { font-weight:700; }
    .billing-history { background:white; padding:28px; border-radius:16px; margin-bottom:24px; border:1px solid var(--border); }
    .billing-history h2 { font-size:24px; margin-bottom:8px; }
    .billing-history p { color:var(--gray); margin:0 0 18px; }
    .billing-table-wrap { overflow:auto; border:1px solid var(--border); border-radius:14px; }
    .billing-table { width:100%; border-collapse:collapse; min-width:760px; background:white; }
    .billing-table th, .billing-table td { padding:14px 12px; border-bottom:1px solid var(--border); text-align:left; vertical-align:top; }
    .billing-table tr:last-child td { border-bottom:0; }
    .billing-table th { font-size:11px; letter-spacing:.14em; text-transform:uppercase; color:var(--gray); background:var(--light); }
    .billing-table strong { display:block; font-size:15px; }
    .billing-table small { display:block; color:var(--gray); margin-top:4px; line-height:1.4; }
    .billing-status { display:inline-flex; width:fit-content; padding:6px 10px; border-radius:999px; background:#f8fafc; border:1px solid var(--border); font-size:11px; letter-spacing:.12em; text-transform:uppercase; font-weight:700; color:#475569; }
    .billing-status.is-processed { background:#dcfce7; border-color:#bbf7d0; color:#166534; }
    .billing-status.is-balance_due { background:#fff7ed; border-color:#fed7aa; color:#9a3412; }
    .billing-status.is-needs_review { background:#fef2f2; border-color:#fecaca; color:#991b1b; }
    .billing-empty { padding:20px; border-radius:14px; border:1px dashed var(--border); background:#f8fafc; color:var(--gray); }
    .two-layer { display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px; }
    .layer-card { background:white; padding:32px; border-radius:16px; }
    .layer-card h3 { font-size:20px; margin-bottom:16px; display:flex; align-items:center; gap:12px; }
    .layer-card .icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:24px; }
    .layer-card.local .icon { background:#dbeafe; color:var(--primary); }
    .layer-card.widget .icon { background:#d1fae5; color:var(--success); }
    .layer-card ul { list-style:none; padding:0; }
    .layer-card li { padding:8px 0; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; }
    .layer-card li:last-child { border:none; }
    .layer-card li::before { content:'✓'; color:var(--success); font-weight:bold; }
    .how-it-works, .comparison, .example { background:white; padding:32px; border-radius:16px; margin-bottom:24px; }
    .how-it-works h2, .comparison h2, .example h2 { font-size:24px; margin-bottom:20px; }
    .step { display:flex; gap:16px; padding:16px 0; border-bottom:1px solid var(--border); }
    .step:last-child { border:none; }
    .step-num { width:36px; height:36px; background:var(--primary); color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; flex-shrink:0; }
    .step h4 { font-size:16px; margin-bottom:4px; }
    .step p { font-size:14px; color:var(--gray); }
    .comp-table { width:100%; border-collapse:collapse; }
    .comp-table th,.comp-table td { padding:14px; text-align:left; border-bottom:1px solid var(--border); }
    .comp-table th { background:var(--light); font-weight:600; }
    .comp-table .our { background:#d1fae5; }
    .comp-table .competitor { background:#fee2e2; }
    .vpn-section { background:linear-gradient(135deg,#0f172a,#1e3a5f); color:white; padding:32px; border-radius:16px; margin-bottom:24px; }
    .vpn-section h3 { font-size:20px; margin-bottom:12px; }
    .vpn-section p { opacity:0.9; font-size:15px; }
    .vpn-section ul { margin-top:16px; padding-left:20px; }
    .vpn-section li { margin-bottom:8px; }
    .screenshot { background:var(--light); padding:20px; border-radius:12px; text-align:center; margin-bottom:16px; }
    .portal-mockup { background:white; padding:24px; border-radius:8px; max-width:400px; margin:0 auto; text-align:left; }
    .portal-header { display:flex; align-items:center; gap:12px; margin-bottom:20px; border-bottom:1px solid var(--border); padding-bottom:16px; }
    .portal-avatar { width:40px; height:40px; background:var(--primary); border-radius:8px; }
    .portal-stat { margin-bottom:16px; }
    .portal-score { font-size:32px; font-weight:700; color:var(--success); }
    .progress-bar { background:var(--light); height:8px; border-radius:4px; }
    .progress-fill { background:var(--success); width:65%; height:100%; border-radius:4px; }
    .portal-items { padding:12px; background:var(--light); border-radius:8px; font-size:13px; }
    @media(max-width:768px){ .two-layer, .access-split { grid-template-columns:1fr; } .billing-history { padding:22px; } }
</style>

<div class="container">
    <div class="intro">
        <h2>The CreditSoft Difference</h2>
        <p>Most credit repair software hosts your client data on their servers. CreditSoft keeps the working data local, then lets your office add a branded website and portal layer on top.</p>
        <div class="offer-callout">
            <strong>Managed Websites from $495+</strong>
            <p>We can build the branded public front end too, then tie it into your CreditSoft CRM/intranet for lead capture, consultation flows, and client portal traffic.</p>
            <a href="/pricing#managed-websites">See website packaging</a>
        </div>
        <div class="access-split">
            <div class="access-tile">
                <strong>Client portal</strong>
                <p>Clients use the branded portal to view updates, upload documents, and stay in the loop without touching the internal workspace.</p>
                <a href="/login">Open portal login</a>
            </div>
            <div class="access-tile">
                <strong>Company-side access</strong>
                <p>Internal casework, lead review, and website operations stay in the secured CreditSoft login flow, not inside the client-facing portal.</p>
                <a href="/login">Open company login</a>
            </div>
        </div>
    </div>

    <?php if ($portalEmail !== ''): ?>
        <div class="billing-history">
            <h2>Billing history</h2>
            <p>Showing Zelle payment activity and license matching for <?= creditsoft_portal_escape($portalEmail) ?>. Transaction IDs stay attached here so renewals and balance-due follow-ups can be traced cleanly.</p>
            <?php if ($billingHistory === []): ?>
                <div class="billing-empty">No billing rows are attached to this email yet. Once a Zelle message is matched, the payment status and transaction ID will appear here.</div>
            <?php else: ?>
                <div class="billing-table-wrap">
                    <table class="billing-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th>Plan</th>
                                <th>Transaction</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($billingHistory as $row): ?>
                            <?php
                                $rowStatus = (string) ($row['status'] ?? 'needs_review');
                                $rowTransaction = trim((string) ($row['transaction_id'] ?? ''));
                                $rowBalanceDue = isset($row['balance_due']) && is_numeric($row['balance_due']) ? (float) $row['balance_due'] : null;
                            ?>
                            <tr>
                                <td><strong><?= creditsoft_portal_escape(creditsoft_portal_date((string) ($row['received_at'] ?? $row['created_at'] ?? ''))) ?></strong><small><?= creditsoft_portal_escape((string) ($row['license_key'] ?? '')) ?></small></td>
                                <td><span class="billing-status is-<?= creditsoft_portal_escape($rowStatus) ?>"><?= creditsoft_portal_escape(creditsoft_portal_label($rowStatus)) ?></span><small><?= creditsoft_portal_escape(creditsoft_portal_label((string) ($row['payment_status'] ?? 'unknown'))) ?></small></td>
                                <td><strong><?= creditsoft_portal_escape(creditsoft_portal_money($row['amount'] ?? null)) ?></strong><small>Expected <?= creditsoft_portal_escape(creditsoft_portal_money($row['expected_amount'] ?? null)) ?></small><?php if ($rowBalanceDue !== null && $rowBalanceDue > 0): ?><small>Balance due <?= creditsoft_portal_escape(creditsoft_portal_money($rowBalanceDue)) ?></small><?php endif; ?></td>
                                <td><strong><?= creditsoft_portal_escape(creditsoft_portal_label((string) ($row['plan_key'] ?? 'CreditSoft'))) ?></strong><small><?= creditsoft_portal_escape(creditsoft_portal_label((string) ($row['billing'] ?? 'monthly'))) ?></small></td>
                                <td><strong><?= creditsoft_portal_escape($rowTransaction !== '' ? $rowTransaction : 'Pending') ?></strong><small><?= ! empty($row['email_sent_at']) || ! empty($row['balance_email_sent_at']) ? 'Email notice sent' : 'Email notice pending' ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="two-layer">
        <div class="layer-card local">
            <h3><span class="icon">💻</span> Layer 1: Intranet</h3>
            <p class="text-gray text-sm mb-3">Your private, local credit repair software</p>
            <ul>
                <li>Runs on YOUR computer/server</li>
                <li>Bank-level AES-256 encryption</li>
                <li>Only YOUR network can access it</li>
                <li>Full client management</li>
                <li>Dispute letter generation</li>
                <li>Metro2 error detection</li>
                <li>No monthly "cloud" fees</li>
            </ul>
        </div>
        <div class="layer-card widget">
            <h3><span class="icon">🌐</span> Layer 2: Branded Website + Portal</h3>
            <p class="text-gray text-sm mb-3">What leads and clients see on your public front end</p>
            <ul>
                <li>White-labeled with your branding and offer copy</li>
                <li>Lead forms routed into CreditSoft CRM/intake</li>
                <li>Clients see only their progress and status</li>
                <li>No sensitive casework data stored on the site</li>
                <li>Portal, consultation, and status CTA wiring</li>
                <li>Mobile responsive public front end</li>
                <li>Works on your domain or a managed launch package</li>
            </ul>
        </div>
    </div>

    <div class="vpn-section">
        <h3>🔐 Remote access with Tailscale</h3>
        <p>CreditSoft uses Tailscale for remote access. That keeps the intranet private while still letting your team reach the office system from another trusted device.</p>
        <ul>
            <li>Tailscale creates the private connection back to the office machine.</li>
            <li>The intranet is not meant to sit open on the public internet.</li>
            <li>Staff connect to Tailscale first, then open CreditSoft.</li>
            <li>Casework stays on the office system instead of moving into a public cloud app.</li>
            <li>That gives the team remote access without pretending the product is a generic browser-only SaaS.</li>
        </ul>
    </div>

    <div class="how-it-works">
        <h2>How It Works</h2>
        <div class="step"><div class="step-num">1</div><div><h4>Run CreditSoft on your computer</h4><p>Install locally. Your data stays on your machine.</p></div></div>
        <div class="step"><div class="step-num">2</div><div><h4>Add your clients</h4><p>Import credit reports, track disputes, manage everything locally.</p></div></div>
        <div class="step"><div class="step-num">3</div><div><h4>Launch the branded website or portal front end</h4><p>Use your own site or let us build the managed website package and tie it into CreditSoft.</p></div></div>
        <div class="step"><div class="step-num">4</div><div><h4>Client sees progress only</h4><p>Your client logs into your branded site or portal. They see updates, while the working case data stays in the local intranet.</p></div></div>
    </div>

    <div class="comparison">
        <h2>vs. Competitors</h2>
        <table class="comp-table">
            <tr><th>Feature</th><th class="our">CreditSoft</th><th class="competitor">CDM / CRC</th></tr>
            <tr><td>Client data location</td><td class="our">Your server</td><td class="competitor">Their cloud</td></tr>
            <tr><td>PII on your website</td><td class="our">None</td><td class="competitor">Yes</td></tr>
            <tr><td>White-label</td><td class="our">Full</td><td class="competitor">Limited</td></tr>
            <tr><td>Monthly fees</td><td class="our">Software only</td><td class="competitor">Plus cloud storage</td></tr>
            <tr><td>Data portability</td><td class="our">You own it</td><td class="competitor">Stuck on their platform</td></tr>
        </table>
    </div>

    <div class="example">
        <h2>What Your Clients See</h2>
        <div class="screenshot">
            <div class="portal-mockup">
                <div class="portal-header">
                    <div class="portal-avatar"></div>
                    <div><div class="font-medium">Your Company Name</div><div class="text-xs text-gray">Client Portal</div></div>
                </div>
                <div class="portal-stat"><div class="text-xs text-gray mb-1">Credit Score</div><div class="portal-score">720</div></div>
                <div class="portal-stat"><div class="text-xs text-gray mb-1">Items Disputed</div><div class="font-medium">12 items</div></div>
                <div class="portal-stat"><div class="text-xs text-gray mb-1">Progress</div><div class="progress-bar"><div class="progress-fill"></div></div><div class="text-xs text-gray mt-1">65% Complete</div></div>
                <div class="portal-items">✅ Late payment removed<br>✅ Collection deleted<br>⏳ Dispute in progress</div>
            </div>
        </div>
        <p>Your logo, your colors, your website. Client sees ONLY progress - no SSN, no addresses, no sensitive data.</p>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
