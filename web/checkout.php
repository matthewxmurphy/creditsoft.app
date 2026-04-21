<?php
declare(strict_types=1);

require_once __DIR__ . '/pricing-config.php';
require_once __DIR__ . '/cash-app-config.php';

$pricing = creditsoft_site_pricing_load();
$plans = is_array($pricing['plans'] ?? null) ? $pricing['plans'] : creditsoft_site_pricing_defaults()['plans'];
$availableAddons = creditsoft_site_checkout_addons();

$requestedPlan = (string) ($_GET['plan'] ?? 'enterprise');
$publicPlanKey = creditsoft_site_public_plan_key($requestedPlan);
$billing = strtolower(trim((string) ($_GET['billing'] ?? 'monthly')));

if (! in_array($billing, ['monthly', 'yearly', 'lifetime'], true)) {
    $billing = 'monthly';
}

$isClusterOnly = $publicPlanKey === 'cluster';

if ($isClusterOnly && $billing === 'lifetime') {
    $billing = 'monthly';
}

$addonParam = strtolower(trim((string) ($_GET['addon'] ?? $_GET['addons'] ?? '')));
$addonKeys = array_values(array_unique(array_filter(array_map(
    static fn (string $value): string => str_replace(['-', ' '], '_', trim($value)),
    explode(',', $addonParam),
))));

if ($isClusterOnly) {
    $addonKeys = ['cluster'];
}

$selectedAddons = [];
foreach ($addonKeys as $addonKey) {
    if (isset($availableAddons[$addonKey])) {
        $selectedAddons[$addonKey] = $availableAddons[$addonKey];
    }
}

$basePlanKey = $isClusterOnly ? null : ($plans[$publicPlanKey] ?? null ? $publicPlanKey : 'enterprise');
$selectedPlan = $basePlanKey !== null
    ? ($plans[$basePlanKey] ?? $plans['enterprise'])
    : [
        'name' => $availableAddons['cluster']['name'] ?? 'Cluster license',
        'features' => $availableAddons['cluster']['features'] ?? [],
        'monthly' => $availableAddons['cluster']['monthly'] ?? 19.95,
        'list_monthly' => $availableAddons['cluster']['list_monthly'] ?? 29.95,
        'yearly' => $availableAddons['cluster']['yearly'] ?? 191.52,
        'list_yearly' => $availableAddons['cluster']['list_yearly'] ?? 359.40,
    ];

$priceKey = $billing === 'yearly' ? 'yearly' : 'monthly';
$listPriceKey = $billing === 'yearly' ? 'list_yearly' : 'list_monthly';
$isLifetime = $billing === 'lifetime';

$baseSalePrice = $isLifetime ? null : round((float) ($selectedPlan[$priceKey] ?? 0), 2);
$baseListPrice = $isLifetime ? null : round((float) ($selectedPlan[$listPriceKey] ?? 0), 2);
$addonSaleTotal = 0.0;
$addonListTotal = 0.0;

foreach ($selectedAddons as $addonKey => $addon) {
    if ($isClusterOnly && $addonKey === 'cluster') {
        continue;
    }

    $addonSaleTotal += round((float) ($addon[$priceKey] ?? 0), 2);
    $addonListTotal += round((float) ($addon[$listPriceKey] ?? 0), 2);
}

$displayPrice = $baseSalePrice === null ? null : round($baseSalePrice + $addonSaleTotal, 2);
$displayListPrice = $baseListPrice === null ? null : round($baseListPrice + $addonListTotal, 2);
$zelleDiscountPercent = 10;
$displayPriceCents = $displayPrice !== null ? (int) round((float) $displayPrice * 100) : null;
$zelleAmountCents = $displayPriceCents !== null ? intdiv($displayPriceCents * (100 - $zelleDiscountPercent), 100) : null;
$zelleDiscountCents = $displayPriceCents !== null && $zelleAmountCents !== null ? $displayPriceCents - $zelleAmountCents : null;
$zelleDiscountAmount = $zelleDiscountCents !== null ? $zelleDiscountCents / 100 : null;
$zelleAmount = $zelleAmountCents !== null ? $zelleAmountCents / 100 : null;
$intervalLabel = $billing === 'yearly' ? 'year' : 'month';
$checkoutPlanSlug = $isClusterOnly ? 'cluster' : creditsoft_site_checkout_plan_slug((string) $basePlanKey);
$selectedAddonNames = array_values(array_map(
    static fn (array $addon): string => (string) ($addon['short_name'] ?? $addon['name'] ?? 'Add-on'),
    $selectedAddons,
));
$planDisplayName = (string) ($selectedPlan['name'] ?? 'CreditSoft');

if (! $isClusterOnly && $selectedAddonNames !== []) {
    $planDisplayName .= ' + ' . implode(' + ', $selectedAddonNames);
}

$saleLabel = match (true) {
    $isLifetime => 'Lifetime interest pricing is handled manually so we can scope the office correctly.',
    $isClusterOnly => 'Node pricing requires an active Enterprise or Enterprise Pro license. If no main license exists, CreditSoft will ask for the main license balance before activating this node.',
    $selectedAddons !== [] => 'This total includes the selected add-on before the Zelle discount is applied.',
    $billing === 'yearly' => 'Yearly pricing keeps the early-adopter sale and stacks the annual discount.',
    default => 'Monthly pricing already reflects the early-adopter sale.',
};

$features = array_values(array_filter(array_map('strval', (array) ($selectedPlan['features'] ?? []))));

if (! $isClusterOnly) {
    foreach ($selectedAddons as $addon) {
        $features[] = (string) ($addon['name'] ?? 'Add-on');
    }
}

$zellePayee = 'Matthew Murphy';
$zelleHandle = 'z@creditsoft.app';
$supportEmail = 'hello@creditsoft.app';
$contactVcardPath = '/assets/creditsoft-contact.vcf';
$zelleLogo = is_file(__DIR__ . '/assets/images/payments/zelle.svg') ? '/assets/images/payments/zelle.svg' : '';
$cashAppLogo = is_file(__DIR__ . '/assets/images/payments/cash-app.svg') ? '/assets/images/payments/cash-app.svg' : '';
$cashAppLabel = 'Cash App';
$cashAppApiConfigured = creditsoft_site_cash_app_configured();
$processingNote = 'Payments can take up to 8 hours to process.';
$memo = 'Your CreditSoft account email';
$qrPayload = implode("\n", array_filter([
    'CreditSoft checkout',
    'Plan: ' . $planDisplayName,
    'Payee: ' . $zellePayee,
    'Zelle: ' . $zelleHandle,
    $zelleAmount !== null ? 'Amount: $' . number_format((float) $zelleAmount, 2) : null,
    'Memo: ' . $memo,
    $processingNote,
]));

$qrDataUri = null;
try {
    require_once dirname(__DIR__) . '/vendor/autoload.php';

    $renderer = new \BaconQrCode\Renderer\ImageRenderer(
        new \BaconQrCode\Renderer\RendererStyle\RendererStyle(320),
        new \BaconQrCode\Renderer\Image\SvgImageBackEnd(),
    );
    $qrSvg = (new \BaconQrCode\Writer($renderer))->writeString($qrPayload);
    $qrDataUri = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);
} catch (\Throwable $exception) {
    $qrDataUri = null;
}

$liveQrImage = __DIR__ . '/assets/images/payments/zelle.png';
$qrImage = is_file($liveQrImage)
    ? '/assets/images/payments/zelle.png'
    : ($qrDataUri ?: 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' . rawurlencode($qrPayload));

$enterpriseProHref = '/checkout?plan=enterprise-pro&billing=' . rawurlencode($billing);
if ($selectedAddons !== []) {
    $enterpriseProHref .= '&addon=' . rawurlencode(implode(',', array_keys($selectedAddons)));
}

$page_title = 'Checkout';
$page_description = 'Complete your CreditSoft order, pay by Zelle or create a Cash App Pay API request, and use your account email as the memo so the payment can match cleanly.';
require __DIR__ . '/header.php';
?>
<style>
    .checkout-shell { max-width: 1180px; margin: 0 auto; padding: 30px 20px 64px; }
    .checkout-hero { max-width: 880px; margin-bottom: 22px; }
    .checkout-hero .kicker { display:flex; align-items:center; gap:10px; color:var(--primary); font-size:12px; font-weight:900; letter-spacing:.16em; text-transform:uppercase; margin-bottom:12px; flex-wrap:wrap; }
    .checkout-hero .kicker img { height:22px; width:auto; display:block; }
    .checkout-hero .kicker img.cashapp-logo { height:24px; }
    .checkout-hero h1 { font-size: clamp(2rem, 4.6vw, 3.55rem); line-height: 1; letter-spacing:-.055em; margin-bottom: 12px; max-width:820px; }
    .checkout-hero p { color: var(--gray); max-width: 760px; font-size:17px; line-height:1.65; }
    .checkout-grid { display:grid; grid-template-columns: minmax(320px, .92fr) minmax(380px, 1.08fr); gap:22px; align-items:start; }
    .checkout-card { background: rgba(255,255,255,0.96); border:1px solid rgba(120,113,108,.16); border-radius:26px; padding:24px; box-shadow:0 22px 50px rgba(15,23,42,.075); }
    .checkout-card h2 { font-size: 28px; margin-bottom: 8px; letter-spacing:-.035em; }
    .checkout-card--payment { display:grid; gap:18px; }
    .payment-heading { display:grid; gap:10px; padding-bottom:16px; border-bottom:1px solid rgba(120,113,108,.14); }
    .payment-heading span { display:block; color:var(--primary); font-size:12px; font-weight:900; letter-spacing:.15em; text-transform:uppercase; margin-bottom:6px; }
    .payment-heading h2 { margin:0; font-size:clamp(1.55rem, 2.8vw, 2.2rem); line-height:1.05; }
    .payment-heading p { margin:0; max-width:620px; color:var(--gray); font-size:14px; line-height:1.55; }
    .price-list { display:block; font-size:15px; color:var(--gray); text-decoration:line-through; margin-bottom:4px; }
    .price-amount { font-size: 42px; font-weight: 800; color: var(--primary); margin-bottom: 6px; }
    .price-amount span { font-size:16px; font-weight:500; color:var(--gray); }
    .zelle-price { margin: 18px 0; border:1px solid rgba(22,163,74,.20); background:#f0fdf4; color:#14532d; border-radius:20px; padding:16px; }
    .zelle-price strong { display:block; font-size:12px; letter-spacing:.14em; text-transform:uppercase; margin-bottom:6px; }
    .zelle-price .zelle-total { font-size:32px; font-weight:800; line-height:1; }
    .zelle-price small { display:block; margin-top:6px; color:#166534; }
    .summary-note { color: var(--gray); margin-bottom: 18px; }
    .summary-list { list-style:none; margin:18px 0 0; padding:0; display:grid; gap:9px; }
    .summary-list li { display:flex; align-items:flex-start; gap:10px; padding:2px 0; color:#111827; font-weight:700; line-height:1.45; }
    .summary-list li::before { content:'✓'; color:var(--success); font-weight:900; flex:0 0 auto; }
    .addon-summary { margin-top:18px; border-top:1px solid rgba(120,113,108,.14); padding-top:16px; display:grid; gap:10px; }
    .addon-summary h3 { margin:0; font-size:16px; letter-spacing:-.02em; }
    .addon-line { display:flex; justify-content:space-between; gap:14px; color:#334155; font-weight:800; }
    .addon-line small { display:block; color:var(--gray); font-weight:500; margin-top:4px; }
    .upgrade-panel { margin-top:18px; border:1px solid #bfdbfe; background:linear-gradient(135deg, #eff6ff, #ffffff); border-radius:22px; padding:18px; display:grid; gap:12px; }
    .upgrade-panel strong { color:#1d4ed8; font-size:12px; letter-spacing:.14em; text-transform:uppercase; }
    .upgrade-panel h3 { margin:0; font-size:21px; letter-spacing:-.03em; }
    .upgrade-panel p { margin:0; color:var(--gray); line-height:1.55; }
    .upgrade-actions { display:flex; flex-wrap:wrap; gap:10px; }
    .upgrade-actions a { display:inline-flex; align-items:center; justify-content:center; min-height:42px; border-radius:999px; padding:0 15px; font-weight:800; text-decoration:none; }
    .upgrade-actions a:first-child { background:#1d4ed8; color:#fff; }
    .upgrade-actions a:last-child { background:#fff; color:#1d4ed8; border:1px solid #bfdbfe; }
    .pay-method-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .pay-method { position:relative; display:flex; align-items:center; gap:12px; border:1px solid rgba(120,113,108,.16); border-radius:18px; background:#fff; padding:14px 15px; cursor:pointer; min-height:62px; }
    .pay-method.is-zelle { border-color:rgba(109,30,212,.20); background:#faf5ff; }
    .pay-method.is-cashapp { border-color:rgba(16,185,129,.24); background:#ecfdf5; }
    .pay-method input { position:absolute; opacity:0; pointer-events:none; }
    .pay-method.is-zelle:has(input:checked) { border-color:#6d1ed4; box-shadow:0 0 0 3px rgba(109,30,212,.12); }
    .pay-method.is-cashapp:has(input:checked) { border-color:#059669; box-shadow:0 0 0 3px rgba(5,150,105,.14); }
    .pay-method img { height:26px; width:auto; display:block; max-width:118px; }
    .pay-method img.cashapp-logo { height:28px; }
    .pay-method strong { display:block; color:#111827; font-size:14px; }
    .pay-method small { display:block; margin-top:2px; color:var(--gray); font-size:12px; line-height:1.3; }
    .cashapp-api-status { border:1px solid rgba(16,185,129,.22); background:#ecfdf5; color:#065f46; border-radius:18px; padding:14px 16px; font-size:14px; line-height:1.5; }
    .cashapp-api-status.is-blocked { border-color:#fed7aa; background:#fff7ed; color:#9a3412; }
    .qr-wrap { display:grid; gap:18px; justify-items:center; }
    .qr-stage { width:min(360px, 100%); border:1px solid rgba(120,113,108,.16); border-radius:24px; background:white; padding:16px; text-align:center; }
    .qr-stage img { width:100%; max-width:330px; height:auto; display:block; margin:0 auto; border-radius:18px; background:white; }
    .detail-grid { width:100%; display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:10px; }
    .detail-line { border:1px solid rgba(120,113,108,.14); border-radius:16px; background:white; padding:13px 14px; min-width:0; }
    .detail-line.is-wide,
    .detail-line.is-total,
    .callout.processing { grid-column:1 / -1; }
    .detail-line.is-total { border-color:rgba(22,163,74,.22); background:#f0fdf4; color:#14532d; }
    .detail-line strong { display:block; font-size:11px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; color:var(--gray); margin-bottom:5px; }
    .detail-line.is-total strong { color:#166534; }
    .detail-line small { display:block; margin-top:6px; color:var(--gray); line-height:1.5; }
    .form-grid { display:grid; gap:14px; margin-top:2px; padding-top:18px; border-top:1px solid rgba(120,113,108,.14); }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .form-group { display:grid; gap:8px; }
    .form-group label { font-size:13px; font-weight:800; letter-spacing:0; text-transform:none; color:#334155; }
    .form-group input, .form-group textarea { width:100%; border:1px solid rgba(120,113,108,.18); border-radius:18px; padding:14px 16px; font-size:16px; background:white; }
    .form-group textarea { min-height: 96px; resize: vertical; }
    .callout { border-radius:20px; padding:16px 18px; font-size:14px; line-height:1.6; }
    .callout.processing { background:#fff7ed; border:1px solid #fdba74; color:#9a3412; }
    .callout.contact { background:#eff6ff; border:1px solid #bfdbfe; color:#1d4ed8; display:grid; gap:10px; }
    .callout.contact strong { color:#0f172a; font-size:15px; }
    .callout.contact span { color:#1e40af; }
    .contact-download { display:inline-flex; align-items:center; justify-content:center; width:fit-content; border-radius:999px; background:#111827; color:white; padding:10px 14px; font-size:13px; font-weight:900; text-decoration:none; }
    .contact-download:hover { background:#0f172a; color:white; text-decoration:none; transform:translateY(-1px); }
    .payment-help-link { display:block; border:1px solid #fed7aa; background:#fff7ed; color:#9a3412; border-radius:18px; padding:14px 16px; font-size:14px; line-height:1.5; text-decoration:none; }
    .payment-help-link strong { color:#7c2d12; }
    .payment-help-link:hover { text-decoration:none; background:#ffedd5; }
    .btn-submit { width:100%; border:0; border-radius:999px; background:var(--primary); color:white; padding:16px 20px; font-size:16px; font-weight:700; cursor:pointer; }
    .btn-submit:hover { background:var(--primary-dark); }
    .status-msg { display:none; border-radius:18px; padding:14px 16px; font-size:14px; }
    .status-msg.error { background:#fff1f2; border:1px solid #fda4af; color:#9f1239; }
    .status-msg.success { background:#f0fdf4; border:1px solid #86efac; color:#166534; }
    @media (max-width: 980px) {
        .checkout-grid,
        .form-row { grid-template-columns:1fr; }
    }
    @media (max-width: 700px) {
        .checkout-shell { padding:26px 22px 52px; }
        .checkout-hero { margin-bottom:18px; }
        .checkout-hero h1 { font-size:clamp(2rem, 9vw, 2.65rem); }
        .checkout-hero p { font-size:16px; }
        .checkout-card { padding:22px; border-radius:24px; }
        .pay-method-row { grid-template-columns:1fr; }
        .detail-grid { grid-template-columns:1fr; }
        .detail-line.is-wide,
        .detail-line.is-total,
        .callout.processing { grid-column:auto; }
        .qr-stage img { max-width:280px; }
    }
</style>

<div class="checkout-shell">
    <div class="checkout-hero">
        <div class="kicker">
            <?php if ($zelleLogo !== ''): ?><img src="<?= htmlspecialchars($zelleLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Zelle"><?php endif; ?>
            <?php if ($cashAppLogo !== ''): ?><img class="cashapp-logo" src="<?= htmlspecialchars($cashAppLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Cash App"><?php endif; ?>
            Checkout
        </div>
        <h1>Pay with Zelle or Cash App. Put your email in the memo.</h1>
        <p>Scan the Zelle QR, or create a real Cash App Pay request from this checkout. Use the same email from this form as the payment memo/note so CreditSoft can match the payment faster. <?= htmlspecialchars($processingNote, ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <div class="checkout-grid">
        <section class="checkout-card">
            <h2><?= htmlspecialchars($planDisplayName, ENT_QUOTES, 'UTF-8') ?></h2>
            <?php if ($displayPrice !== null): ?>
                <span class="price-list">$<?= number_format((float) $displayListPrice, 2) ?>/<?= $intervalLabel ?></span>
                <div class="price-amount">$<?= number_format((float) $displayPrice, 2) ?><span>/<?= $intervalLabel ?></span></div>
            <?php else: ?>
                <div class="price-amount" style="font-size:30px;">Lifetime interest</div>
            <?php endif; ?>
            <p class="summary-note"><?= htmlspecialchars($saleLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <?php if ($zelleAmount !== null): ?>
                <div class="zelle-price">
                    <strong>Zelle / Cash App total after <?= (int) $zelleDiscountPercent ?>% discount</strong>
                    <div class="zelle-total">$<?= number_format((float) $zelleAmount, 2) ?><span style="font-size:16px; font-weight:600;"> / <?= $intervalLabel ?></span></div>
                    <small>You save $<?= number_format((float) $zelleDiscountAmount, 2) ?> when this payment is sent by Zelle or Cash App.</small>
                </div>
            <?php endif; ?>

            <ul class="summary-list">
                <?php foreach ($features as $feature): ?>
                    <li><?= htmlspecialchars($feature, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>

            <?php if (! $isClusterOnly && $selectedAddons !== []): ?>
                <div class="addon-summary">
                    <h3>Selected add-ons</h3>
                    <?php foreach ($selectedAddons as $addon): ?>
                        <div class="addon-line">
                            <div>
                                <?= htmlspecialchars((string) ($addon['name'] ?? 'Add-on'), ENT_QUOTES, 'UTF-8') ?>
                                <small><?= htmlspecialchars((string) ($addon['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                            <span>$<?= number_format((float) ($addon[$priceKey] ?? 0), 2) ?>/<?= $intervalLabel ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (! $isClusterOnly && $basePlanKey === 'enterprise'): ?>
                <div class="upgrade-panel">
                    <strong>Want the browser companion?</strong>
                    <h3>Enterprise Pro adds provider capture and API automation.</h3>
                    <p>Watch the browser companion video before you choose. If the office wants SmartCredit, IdentityIQ, and provider capture work moving faster, Enterprise Pro is the cleaner fit.</p>
                    <div class="upgrade-actions">
                        <a href="/videos?watch=smartcredit-companion">Watch companion video</a>
                        <a href="<?= htmlspecialchars($enterpriseProHref, ENT_QUOTES, 'UTF-8') ?>">Upgrade to Enterprise Pro</a>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="checkout-card checkout-card--payment">
            <div class="payment-heading">
                <div>
                    <span>Payment details</span>
                    <h2><?= $zelleAmount !== null ? 'Send $' . number_format((float) $zelleAmount, 2) : 'Send your payment' ?></h2>
                </div>
                <p>Choose Zelle to send from your bank, or choose Cash App to create a real Cash App Pay API request. If Cash App credentials are not configured, checkout shows the setup issue and keeps the customer on Zelle.</p>
            </div>

            <div class="pay-method-row" aria-label="Payment method">
                <label class="pay-method is-zelle">
                    <input type="radio" name="payment_method" value="zelle" checked>
                    <?php if ($zelleLogo !== ''): ?><img src="<?= htmlspecialchars($zelleLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Zelle"><?php endif; ?>
                    <span><strong>Pay with Zelle</strong><small>Fastest if your email is in the memo.</small></span>
                </label>
                <label class="pay-method is-cashapp">
                    <input type="radio" name="payment_method" value="cashapp">
                    <?php if ($cashAppLogo !== ''): ?><img class="cashapp-logo" src="<?= htmlspecialchars($cashAppLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Cash App"><?php endif; ?>
                    <span><strong>Pay with <?= htmlspecialchars($cashAppLabel, ENT_QUOTES, 'UTF-8') ?></strong><small><?= $cashAppApiConfigured ? 'Creates an API payment request.' : 'API setup required before use.' ?></small></span>
                </label>
            </div>
            <div id="cashAppApiStatus" class="cashapp-api-status <?= $cashAppApiConfigured ? '' : 'is-blocked' ?>" style="display:none;">
                <?= $cashAppApiConfigured
                    ? 'Cash App Pay is API-backed here. Fill out the form and CreditSoft will create the Cash App request, then show the returned QR/mobile/desktop links.'
                    : 'Cash App Pay API credentials are not configured on this checkout yet. Use Zelle until Client ID and Scope ID are saved on the server.' ?>
            </div>

            <div class="qr-wrap">
                <div class="qr-stage">
                    <img src="<?= htmlspecialchars($qrImage, ENT_QUOTES, 'UTF-8') ?>" alt="CreditSoft checkout payment QR code">
                </div>

                <div class="detail-grid">
                    <div class="detail-line">
                        <strong>Payee</strong>
                        <div><?= htmlspecialchars($zellePayee, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="detail-line">
                        <strong>Zelle destination</strong>
                        <div><?= htmlspecialchars($zelleHandle, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="detail-line is-wide">
                        <strong>Memo</strong>
                        <div id="memoDisplay"><?= htmlspecialchars($memo, ENT_QUOTES, 'UTF-8') ?></div>
                        <small>Use the exact email you enter below. If the memo is wrong, matching falls back to payer name, amount, and this checkout notice.</small>
                    </div>
                    <?php if ($displayPrice !== null): ?>
                        <div class="detail-line">
                            <strong>Checkout total</strong>
                            <div>$<?= number_format((float) $displayPrice, 2) ?></div>
                        </div>
                        <div class="detail-line">
                            <strong>Zelle discount</strong>
                            <div><?= (int) $zelleDiscountPercent ?>% off (-$<?= number_format((float) $zelleDiscountAmount, 2) ?>)</div>
                        </div>
                    <div class="detail-line is-total">
                            <strong>Zelle / Cash App total to send</strong>
                            <div>$<?= number_format((float) $zelleAmount, 2) ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="callout processing"><?= htmlspecialchars($processingNote, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>

            <form id="checkoutForm" class="form-grid">
                <div id="checkoutError" class="status-msg error"></div>
                <div id="checkoutSuccess" class="status-msg success"></div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="customerEmail">Your Email</label>
                        <input type="email" id="customerEmail" name="customer_email" placeholder="you@company.com" required>
                        <small style="color:var(--gray);">Use this same email as the Zelle memo or Cash App note.</small>
                    </div>
                    <div class="form-group">
                        <label for="customerPhone">Your Phone</label>
                        <input type="tel" id="customerPhone" name="customer_phone" placeholder="(555) 555-5555" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="paymentSource">Payment Came From</label>
                        <input type="text" id="paymentSource" name="payment_source" placeholder="Zelle/Cash App email, phone, $cashtag, or sender name" required>
                    </div>
                    <div class="form-group">
                        <label for="officeName">Company Name</label>
                        <input type="text" id="officeName" name="office_name" placeholder="Company name on the license">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="paymentAmountSent">Amount You Sent</label>
                        <input type="number" id="paymentAmountSent" name="payment_amount_sent" step="0.01" min="0.01" value="<?= $zelleAmount !== null ? htmlspecialchars(number_format((float) $zelleAmount, 2, '.', ''), ENT_QUOTES, 'UTF-8') : '' ?>" placeholder="89.95" required>
                        <small style="color:var(--gray);">Use the actual amount you sent, even if it was a test or partial payment.</small>
                    </div>
                    <div class="form-group">
                        <label for="paymentTransactionId">Transaction Number</label>
                        <input type="text" id="paymentTransactionId" name="payment_transaction_id" placeholder="Optional confirmation/reference number">
                        <small style="color:var(--gray);">If your bank shows one, add it here so CreditSoft can match faster.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="checkoutNotes">Anything We Should Match Against?</label>
                    <textarea id="checkoutNotes" name="notes" placeholder="Examples: paid from a spouse's phone, different business name on Zelle, or anything else helpful."></textarea>
                </div>

                <div class="callout contact">
                    <strong>Add CreditSoft before you pay.</strong>
                    <span>Download the contact card or manually add <?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?> to your contacts so the receipt, license, and setup email are less likely to land in spam.</span>
                    <a class="contact-download" href="<?= htmlspecialchars($contactVcardPath, ENT_QUOTES, 'UTF-8') ?>" download>Download CreditSoft contact</a>
                    <span>For Zelle, submit this notice right after you send the payment. For Cash App, this form creates the API request first. Best match: email in the Zelle memo or Cash App note.</span>
                </div>
                <a class="payment-help-link" href="/payment-help">
                    <strong>Paid but still do not have a license?</strong>
                    Open the payment help FAQ and ticket form. Tell us who the payment came from, whether a memo was used, and attach a screenshot if you have one.
                </a>

                <button type="submit" class="btn-submit">Continue with payment</button>
            </form>
        </section>
    </div>
</div>

<script>
const checkoutForm = document.getElementById('checkoutForm');
const checkoutError = document.getElementById('checkoutError');
const checkoutSuccess = document.getElementById('checkoutSuccess');
const customerEmailInput = document.getElementById('customerEmail');
const memoDisplay = document.getElementById('memoDisplay');
const cashAppApiConfigured = <?= json_encode($cashAppApiConfigured) ?>;
const cashAppApiStatus = document.getElementById('cashAppApiStatus');
const qrImageElement = document.querySelector('.qr-stage img');
const defaultQrImageSrc = qrImageElement?.getAttribute('src') || '';
const defaultQrImageAlt = qrImageElement?.getAttribute('alt') || 'CreditSoft checkout payment QR code';

customerEmailInput?.addEventListener('input', () => {
    const email = customerEmailInput.value.trim();
    memoDisplay.textContent = email || <?= json_encode($memo) ?>;
});

function showCheckoutMessage(target, message) {
    target.textContent = message;
    target.style.display = 'block';
}

function showCheckoutHtml(target, html) {
    target.innerHTML = html;
    target.style.display = 'block';
}

function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));
}

function clearCheckoutMessages() {
    checkoutError.textContent = '';
    checkoutSuccess.textContent = '';
    checkoutSuccess.innerHTML = '';
    checkoutError.style.display = 'none';
    checkoutSuccess.style.display = 'none';
}

function selectedPaymentMethod() {
    return document.querySelector('input[name="payment_method"]:checked')?.value || 'zelle';
}

function refreshPaymentUi() {
    const isCashApp = selectedPaymentMethod() === 'cashapp';

    if (cashAppApiStatus) {
        cashAppApiStatus.style.display = isCashApp ? 'block' : 'none';
    }

    if (!isCashApp && qrImageElement) {
        qrImageElement.setAttribute('src', defaultQrImageSrc);
        qrImageElement.setAttribute('alt', defaultQrImageAlt);
    }
}

document.querySelectorAll('input[name="payment_method"]').forEach((input) => {
    input.addEventListener('change', refreshPaymentUi);
});

refreshPaymentUi();

checkoutForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearCheckoutMessages();

    const paymentMethod = selectedPaymentMethod();
    const payload = {
        plan: <?= json_encode($checkoutPlanSlug) ?>,
        public_plan_key: <?= json_encode($publicPlanKey) ?>,
        billing: <?= json_encode($billing) ?>,
        addons: <?= json_encode(array_keys($selectedAddons)) ?>,
        plan_name: <?= json_encode($planDisplayName) ?>,
        amount: <?= json_encode($zelleAmount) ?>,
        base_amount: <?= json_encode($displayPrice) ?>,
        plan_amount: <?= json_encode($baseSalePrice) ?>,
        addon_amount: <?= json_encode($addonSaleTotal) ?>,
        list_amount: <?= json_encode($displayListPrice) ?>,
        zelle_amount: <?= json_encode($zelleAmount) ?>,
        zelle_discount_percent: <?= json_encode($zelleDiscountPercent) ?>,
        customer_email: document.getElementById('customerEmail').value.trim(),
        customer_phone: document.getElementById('customerPhone').value.trim(),
        payment_source: document.getElementById('paymentSource').value.trim(),
        payment_amount_sent: document.getElementById('paymentAmountSent').value.trim(),
        payment_transaction_id: document.getElementById('paymentTransactionId').value.trim(),
        office_name: document.getElementById('officeName').value.trim(),
        notes: document.getElementById('checkoutNotes').value.trim(),
        payment_memo_email: document.getElementById('customerEmail').value.trim(),
        payment_method: paymentMethod,
    };

    if (!payload.customer_email || !payload.customer_phone || !payload.payment_source) {
        showCheckoutMessage(checkoutError, 'Please fill in your email, phone, and the email, phone, or name the payment came from.');
        return;
    }

    const amountSent = Number(payload.payment_amount_sent);
    if (!Number.isFinite(amountSent) || amountSent <= 0) {
        showCheckoutMessage(checkoutError, 'Enter the actual amount you sent so CreditSoft can match the payment correctly.');
        return;
    }

    const submitButton = checkoutForm.querySelector('.btn-submit');
    submitButton.disabled = true;
    submitButton.textContent = paymentMethod === 'cashapp' ? 'Creating Cash App request...' : 'Saving your payment notice...';

    try {
        let cashAppResponse = null;

        if (paymentMethod === 'cashapp') {
            if (!cashAppApiConfigured) {
                throw new Error('Cash App Pay API is not configured on this checkout yet. Use Zelle until Client ID and Scope ID are saved on the server.');
            }

            const cashResponse = await fetch('/api/cash-app-request.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    plan: payload.plan,
                    billing: payload.billing,
                    amount: payload.payment_amount_sent,
                    currency: 'USD',
                    customer_email: payload.customer_email,
                })
            });

            cashAppResponse = await cashResponse.json();

            if (!cashResponse.ok || !cashAppResponse.success) {
                throw new Error(cashAppResponse.error || 'Unable to create Cash App Pay request.');
            }

            payload.cash_app_request_id = cashAppResponse.cash_app_request_id || '';
            payload.cash_app_reference_id = cashAppResponse.reference_id || '';
            payload.cash_app_status = cashAppResponse.status || '';
            payload.cash_app_mobile_url = cashAppResponse.mobile_url || '';
            payload.cash_app_desktop_url = cashAppResponse.desktop_url || '';

            const cashQr = cashAppResponse.qr_code_image_url || cashAppResponse.qr_code_svg_url || '';
            if (cashQr && qrImageElement) {
                qrImageElement.setAttribute('src', cashQr);
                qrImageElement.setAttribute('alt', 'Cash App Pay request QR code');
            }
        }

        const response = await fetch('/api/checkout-request.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Unable to save the checkout request.');
        }

        if (paymentMethod === 'cashapp' && cashAppResponse) {
            const links = [
                cashAppResponse.mobile_url ? `<a href="${escapeHtml(cashAppResponse.mobile_url)}" target="_blank" rel="noopener">Open on mobile</a>` : '',
                cashAppResponse.desktop_url ? `<a href="${escapeHtml(cashAppResponse.desktop_url)}" target="_blank" rel="noopener">Open on desktop</a>` : '',
            ].filter(Boolean).join(' · ');
            showCheckoutHtml(
                checkoutSuccess,
                `Cash App Pay request created and saved. Request ${escapeHtml(cashAppResponse.cash_app_request_id || cashAppResponse.reference_id || '')}. ${links ? '<br>' + links : 'Use the QR shown above to continue.'}`
            );
        } else {
            showCheckoutMessage(checkoutSuccess, 'Payment notice saved. Watch your email and phone. If the Zelle memo used this email, CreditSoft can match it automatically; otherwise it will fall back to payer-name review.');
        }

        if (paymentMethod !== 'cashapp') {
            checkoutForm.reset();
            memoDisplay.textContent = <?= json_encode($memo) ?>;
            refreshPaymentUi();
        }
    } catch (error) {
        showCheckoutMessage(checkoutError, error.message || 'Something went wrong. Please try again.');
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = 'Continue with payment';
    }
});
</script>

<?php require __DIR__ . '/footer.php'; ?>
