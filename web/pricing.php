<?php
declare(strict_types=1);

require_once __DIR__ . '/pricing-config.php';
require_once __DIR__ . '/site-content-config.php';

$pricing = creditsoft_site_pricing_load();
$siteContent = creditsoft_site_content_load();
$content = $siteContent['pricing'] ?? [];
$plans = $pricing['plans'] ?? [];
$checkoutAddons = creditsoft_site_checkout_addons();
$clusterAddon = $checkoutAddons['cluster'] ?? null;
$zelleLogo = is_file(__DIR__ . '/assets/images/payments/zelle.svg') ? '/assets/images/payments/zelle.svg' : '';
$cashAppLogo = is_file(__DIR__ . '/assets/images/payments/cash-app.svg') ? '/assets/images/payments/cash-app.svg' : '';

foreach ([
    'Plans, sale pricing, and the real software lane.' => 'Simple pricing for the CreditSoft office system.',
    'Pick the software lane first, then add the website or intake layer only if your office needs it.' => 'Simple pricing for the CreditSoft office system.',
] as $legacy => $replacement) {
    if (($content['title'] ?? '') === $legacy) {
        $content['title'] = $replacement;
    }
}

foreach ([
    'The public pricing page should stay straightforward: real monthly pricing, visible annual savings, and plan differences that actually mean something to an office.' => 'Start with the core software plan. Add the browser companion, extra office installs, legal-intake tools, or a managed website only when the office needs them.',
    'Real monthly pricing, visible annual savings, and a clear split between the core office software and the optional public-site package.' => 'Start with the core software plan. Add the browser companion, extra office installs, legal-intake tools, or a managed website only when the office needs them.',
] as $legacy => $replacement) {
    if (($content['subtitle'] ?? '') === $legacy) {
        $content['subtitle'] = $replacement;
    }
}

if (($content['subtitle'] ?? '') === 'Start with the core software plan. Add the browser companion, extra office installs, legal-intake tools, or a managed website only when the office needs them.') {
    $content['subtitle'] = 'Start with Enterprise. Upgrade to Enterprise Pro for the browser companion, add cluster installs only when you need more office nodes, and keep lawsuit intake included in every plan.';
}

if (in_array(($content['note'] ?? ''), [
    'Every plan below reads from the same stored pricing config the website admin controls.',
    'The software plans below read from the same pricing config the website admin controls.',
], true)) {
    $content['note'] = 'Prices update from the same admin-controlled pricing file used by checkout.';
}

if (($content['support_card_one_title'] ?? '') === 'Packaged software lanes') {
    $content['support_card_one_title'] = 'Core office software';
}

if (($content['support_card_three_text'] ?? '') === 'If you run more than one office, each location gets its own local install and its own license lane instead of pretending one box is the whole company.') {
    $content['support_card_three_text'] = 'If you run more than one office, each location gets its own local install and license instead of pretending one machine is the whole company.';
}

if (($content['support_card_three_title'] ?? '') === 'Cluster licensing') {
    $content['support_card_three_title'] = 'Legal intake included';
}

$page_title = 'Pricing';
$page_description = 'CreditSoft pricing for Enterprise, Enterprise Pro, browser companion access, cluster installs, managed websites, and Zelle checkout.';
$page_hero = false;

require __DIR__ . '/header.php';
?>
<style>
    .pricing-shell {
        display: grid;
        gap: 32px;
    }
    .pricing-top {
        display: grid;
        gap: 18px;
    }
    .pricing-hero,
    .pricing-website-card,
    .support-card,
    .plan-card,
    .addon-card,
    .faq-item {
        background: rgba(255,255,255,.96);
        border: 1px solid var(--border);
        border-radius: 28px;
        box-shadow: 0 22px 48px rgba(15,23,42,.08);
    }
    .pricing-hero {
        padding: 34px 32px;
        display: grid;
        gap: 18px;
        background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.98));
    }
    .pricing-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        width: fit-content;
        padding: 8px 12px;
        border-radius: 999px;
        background: #dbeafe;
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .pricing-hero h1 {
        margin: 0;
        font-size: clamp(2.35rem, 4vw, 3.9rem);
        line-height: .98;
        letter-spacing: -.05em;
    }
    .pricing-hero p {
        margin: 0;
        color: var(--gray);
        font-size: 17px;
        line-height: 1.75;
        max-width: 760px;
    }
    .pricing-plan-stage {
        display: grid;
        gap: 22px;
        padding: 28px;
        border-radius: 32px;
        background: linear-gradient(180deg, rgba(255,255,255,.82), rgba(239,246,255,.54));
        border: 1px solid rgba(191,219,254,.74);
        box-shadow: 0 24px 54px rgba(15,23,42,.07);
    }
    .pricing-hero-points {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .pricing-hero-points li {
        padding: 14px 16px;
        border-radius: 18px;
        background: #f8fafc;
        border: 1px solid var(--border);
        color: #334155;
        font-size: 14px;
        line-height: 1.65;
    }
    .pricing-website-card {
        padding: 30px 28px;
        display: grid;
        gap: 18px;
        background: linear-gradient(135deg, rgba(239,246,255,.95), rgba(255,255,255,.98));
        border-color: #bfdbfe;
    }
    .pricing-website-card strong {
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: #1d4ed8;
    }
    .pricing-website-card h2 {
        margin: 0;
        font-size: 30px;
        line-height: 1.02;
        letter-spacing: -.04em;
    }
    .pricing-website-price {
        display: grid;
        gap: 6px;
    }
    .pricing-website-price .list {
        color: var(--gray);
        text-decoration: line-through;
        font-size: 15px;
        font-weight: 700;
    }
    .pricing-website-price .sale {
        font-size: 42px;
        line-height: .95;
        font-weight: 800;
        color: var(--primary);
        letter-spacing: -.05em;
    }
    .pricing-website-price .sale span {
        font-size: 16px;
        font-weight: 600;
        color: var(--gray);
    }
    .pricing-website-card p {
        margin: 0;
        color: var(--gray);
        line-height: 1.7;
    }
    .pricing-website-card ul {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 10px;
    }
    .pricing-website-card li {
        padding: 12px 14px;
        border-radius: 16px;
        background: rgba(255,255,255,.84);
        border: 1px solid rgba(191,219,254,.82);
        color: #334155;
        font-size: 14px;
    }
    .pricing-support-grid {
        display: grid;
        gap: 18px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .support-card {
        padding: 22px 22px 24px;
        display: grid;
        gap: 8px;
    }
    .support-card strong {
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .1em;
        text-transform: uppercase;
        color: #64748b;
    }
    .support-card h3 {
        margin: 0;
        font-size: 20px;
        line-height: 1.1;
    }
    .support-card p {
        margin: 0;
        color: var(--gray);
        line-height: 1.7;
    }
    .billing-shell {
        display: grid;
        gap: 10px;
        justify-items: center;
    }
    .billing-toggle {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px;
        border-radius: 999px;
        border: 1px solid rgba(226,232,240,.9);
        background: rgba(255,255,255,.94);
        box-shadow: 0 16px 34px rgba(15,23,42,.06);
        flex-wrap: wrap;
    }
    .billing-btn {
        appearance: none;
        border: 0;
        background: transparent;
        color: #475569;
        border-radius: 999px;
        padding: 12px 18px;
        font-size: 14px;
        font-weight: 800;
        letter-spacing: .04em;
        cursor: pointer;
        transition: background .18s ease, color .18s ease, transform .18s ease;
    }
    .billing-btn:hover {
        background: #eff6ff;
        color: #1d4ed8;
    }
    .billing-btn.is-active {
        background: var(--primary);
        color: white;
        box-shadow: 0 14px 28px rgba(37,99,235,.22);
    }
    .pricing-note {
        text-align: center;
        color: var(--gray);
        font-size: 14px;
        margin: 0;
    }
    .payment-method-strip {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        flex-wrap: wrap;
        color: #334155;
        font-size: 13px;
        font-weight: 800;
    }
    .payment-method-strip img {
        height: 24px;
        width: auto;
        display: block;
    }
    .payment-method-strip img.cashapp-logo {
        height: 24px;
        max-width: 112px;
    }
    .plan-payment-note {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        padding-top: 4px;
        color: #475569;
        font-size: 13px;
        font-weight: 800;
    }
    .plan-payment-note img {
        height: 22px;
        width: auto;
        display: block;
    }
    .pricing-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 24px;
        max-width: 1040px;
        margin: 0 auto;
        width: 100%;
    }
    .plan-card {
        padding: 30px;
        display: grid;
        gap: 18px;
    }
    .plan-card.featured {
        border-color: #93c5fd;
        background: linear-gradient(180deg, rgba(239,246,255,.95), #ffffff);
    }
    .plan-top {
        display: grid;
        gap: 8px;
    }
    .plan-top .kicker {
        display: inline-flex;
        align-items: center;
        padding: 8px 12px;
        border-radius: 999px;
        background: #dbeafe;
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
        width: fit-content;
    }
    .plan-top .kicker.alt {
        background: #dcfce7;
        color: #166534;
    }
    .plan-top h2 {
        font-size: 30px;
        letter-spacing: -.04em;
        margin: 0;
    }
    .plan-top p {
        color: var(--gray);
        margin: 0;
    }
    .plan-mode-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        width: fit-content;
        padding: 8px 12px;
        border-radius: 999px;
        background: rgba(37,99,235,.08);
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .1em;
        text-transform: uppercase;
    }
    .price-rail {
        display: grid;
        gap: 14px;
    }
    .price-band {
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 16px 18px;
        background: #f8fafc;
    }
    .price-band strong {
        display: block;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: .12em;
        color: var(--gray);
        margin-bottom: 4px;
    }
    .price-line {
        display: flex;
        align-items: end;
        gap: 10px;
        flex-wrap: wrap;
    }
    .price-line .list {
        color: var(--gray);
        text-decoration: line-through;
    }
    .price-line .sale {
        font-size: 34px;
        line-height: .95;
        font-weight: 800;
        color: var(--primary);
        letter-spacing: -.04em;
    }
    .price-line .sale span {
        font-size: 15px;
        color: var(--gray);
        font-weight: 600;
    }
    .price-equivalent {
        color: var(--gray);
        font-size: 13px;
        margin-top: 8px;
    }
    .price-desc {
        color: var(--gray);
        font-size: 14px;
        margin-top: 8px;
    }
    .price-contact-box {
        border: 1px solid #bfdbfe;
        border-radius: 18px;
        padding: 18px;
        background: linear-gradient(135deg, rgba(239,246,255,.96), rgba(255,255,255,.94));
        display: grid;
        gap: 8px;
    }
    .price-contact-box strong {
        display: block;
        font-size: 12px;
        letter-spacing: .14em;
        text-transform: uppercase;
        color: #1d4ed8;
    }
    .price-contact-box .contact-title {
        font-size: 28px;
        line-height: 1;
        font-weight: 800;
        letter-spacing: -.04em;
        color: #0f172a;
    }
    .price-contact-box p {
        margin: 0;
        color: var(--gray);
        font-size: 14px;
    }
    .feature-list {
        list-style: none;
        display: grid;
        gap: 10px;
        margin: 0;
        padding: 0;
    }
    .feature-list li {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        border-bottom: 1px solid var(--border);
        padding-bottom: 10px;
    }
    .feature-list li:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }
    .feature-list li::before {
        content: '✓';
        color: var(--success);
        font-weight: 800;
    }
    .plan-actions {
        display: grid;
        gap: 10px;
    }
    .btn-row-link {
        display: inline-grid;
        align-items: center;
        justify-content: center;
        gap: 2px;
        min-height: 58px;
        padding: 10px 18px;
        border-radius: 14px;
        font-weight: 700;
        text-decoration: none;
        text-align: center;
        line-height: 1.15;
    }
    .btn-row-link small {
        display: block;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .12em;
        text-transform: uppercase;
        opacity: .78;
    }
    .btn-row-link.primary {
        background: var(--primary);
        color: white;
    }
    .btn-row-link.primary:hover {
        background: var(--primary-dark);
        text-decoration: none;
    }
    .btn-row-link.secondary {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
    }
    .btn-row-link.secondary:hover {
        background: #dbeafe;
        text-decoration: none;
    }
    .addons,
    .faq {
        display: grid;
        gap: 18px;
    }
    .section-heading {
        display: grid;
        gap: 8px;
        max-width: 780px;
    }
    .section-heading strong {
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
        color: #64748b;
    }
    .section-heading h2 {
        margin: 0;
        font-size: 34px;
        line-height: 1.04;
        letter-spacing: -.04em;
    }
    .section-heading p {
        margin: 0;
        color: var(--gray);
        line-height: 1.75;
    }
    .addon-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 24px;
    }
    .addon-card {
        padding: 28px 28px 30px;
        display: grid;
        gap: 14px;
    }
    .addon-card .addon-kicker {
        display: inline-flex;
        width: fit-content;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--primary);
        padding: 8px 12px;
        border-radius: 999px;
        background: #dbeafe;
    }
    .addon-card h3 {
        font-size: 28px;
        line-height: 1.04;
        letter-spacing: -.04em;
        margin: 0;
    }
    .addon-price-stack {
        display: grid;
        gap: 4px;
    }
    .addon-card .addon-price-list {
        display: block;
        font-size: 15px;
        color: var(--gray);
        text-decoration: line-through;
        font-weight: 700;
    }
    .addon-card .addon-price {
        font-size: 40px;
        font-weight: 800;
        color: var(--primary);
        line-height: .96;
        letter-spacing: -.04em;
    }
    .addon-card .addon-price-note {
        color: var(--gray);
        font-size: 13px;
        margin: 0;
    }
    .addon-card p {
        color: var(--gray);
        margin: 0;
        line-height: 1.75;
    }
    .addon-card ul {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 10px;
    }
    .addon-card li {
        padding: 10px 0;
        border-bottom: 1px solid var(--border);
        color: #334155;
    }
    .addon-card li:last-child {
        border-bottom: none;
    }
    .addon-card li::before {
        content: '•';
        color: var(--success);
        font-weight: 700;
        margin-right: 10px;
    }
    .website-bottom {
        display: grid;
        grid-template-columns: minmax(0, 1.05fr) minmax(300px, .95fr);
        gap: 26px;
        align-items: center;
        padding: 34px;
        border-radius: 34px;
        background: linear-gradient(135deg, #07111f, #0f2f64);
        color: white;
        box-shadow: 0 28px 70px rgba(15,23,42,.22);
        overflow: hidden;
        position: relative;
    }
    .website-bottom::after {
        content: '';
        position: absolute;
        inset: auto -80px -140px auto;
        width: 320px;
        height: 320px;
        border-radius: 999px;
        background: rgba(96,165,250,.28);
        filter: blur(6px);
    }
    .website-bottom-copy,
    .website-bottom-details {
        position: relative;
        z-index: 1;
    }
    .website-bottom-copy {
        display: grid;
        gap: 14px;
    }
    .website-bottom-copy strong {
        width: fit-content;
        padding: 8px 12px;
        border-radius: 999px;
        background: rgba(255,255,255,.12);
        color: #bfdbfe;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
    }
    .website-bottom-copy h2 {
        margin: 0;
        max-width: 760px;
        font-size: clamp(2rem, 4vw, 4rem);
        line-height: .98;
        letter-spacing: -.05em;
    }
    .website-bottom-copy p {
        margin: 0;
        max-width: 650px;
        color: rgba(255,255,255,.78);
        line-height: 1.75;
    }
    .website-bottom-price {
        display: grid;
        gap: 4px;
    }
    .website-bottom-price .list {
        color: rgba(255,255,255,.56);
        text-decoration: line-through;
        font-weight: 800;
    }
    .website-bottom-price .sale {
        font-size: 52px;
        line-height: .95;
        font-weight: 900;
        letter-spacing: -.06em;
        color: white;
    }
    .website-bottom-price .sale span {
        font-size: 18px;
        color: rgba(255,255,255,.66);
    }
    .website-bottom-details {
        display: grid;
        gap: 12px;
    }
    .website-bottom-details ul {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 10px;
    }
    .website-bottom-details li {
        padding: 12px 14px;
        border-radius: 16px;
        background: rgba(255,255,255,.1);
        border: 1px solid rgba(255,255,255,.14);
        color: rgba(255,255,255,.82);
    }
    .website-bottom-details .btn-row-link {
        background: white;
        color: #0f172a;
    }
    .faq {
        max-width: 860px;
    }
    .faq-item {
        padding: 22px 24px;
    }
    .faq-item h4 {
        margin: 0 0 8px;
        font-size: 18px;
    }
    .faq-item p {
        margin: 0;
        color: var(--gray);
        line-height: 1.7;
    }
    @media (max-width: 1080px) {
        .pricing-top,
        .pricing-support-grid,
        .addon-grid,
        .website-bottom {
            grid-template-columns: 1fr;
        }
        .pricing-hero-points {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 720px) {
        .pricing-hero,
        .pricing-website-card,
        .plan-card,
        .addon-card,
        .faq-item {
            padding-left: 20px;
            padding-right: 20px;
        }
        .pricing-hero h1,
        .section-heading h2 {
            font-size: 30px;
        }
        .pricing-website-price .sale,
        .addon-card .addon-price {
            font-size: 34px;
        }
    }
</style>

<div class="page-content">
    <div class="pricing-shell">
        <section class="pricing-top">
            <article class="pricing-hero">
                <span class="pricing-eyebrow"><?= htmlspecialchars((string) ($content['eyebrow'] ?? 'Pricing'), ENT_QUOTES, 'UTF-8') ?></span>
                <h1><?= htmlspecialchars((string) ($content['title'] ?? 'Simple pricing for the CreditSoft office system.'), ENT_QUOTES, 'UTF-8') ?></h1>
                <p><?= htmlspecialchars((string) ($content['subtitle'] ?? 'Start with Enterprise. Upgrade to Enterprise Pro for the browser companion, add cluster installs only when you need more office nodes, and keep lawsuit intake included in every plan.'), ENT_QUOTES, 'UTF-8') ?></p>
                <ul class="pricing-hero-points">
                    <li>Software pricing covers the local-first intranet, client workspaces, Metro 2 review, letters, briefs, and office operations.</li>
                    <li>FCRA / FDCPA lawsuit intake is built into every plan, not sold as a separate add-on.</li>
                    <li>The browser companion is the clean upgrade reason for Enterprise Pro.</li>
                    <li>Annual pricing keeps the early-adopter sale and stacks another discount instead of hiding the math.</li>
                    <li>Have more than one office? Add a cluster license at checkout for each additional office install.</li>
                </ul>
            </article>
        </section>

        <section class="pricing-plan-stage" aria-label="Software pricing">
            <section class="billing-shell" aria-label="Billing view">
                <div class="billing-toggle" role="tablist" aria-label="Billing cadence">
                    <button class="billing-btn is-active" type="button" data-billing-view="monthly" aria-pressed="true">Monthly</button>
                    <button class="billing-btn" type="button" data-billing-view="yearly" aria-pressed="false">Annually</button>
                    <button class="billing-btn" type="button" data-billing-view="lifetime" aria-pressed="false">Lifetime</button>
                </div>
                <p class="pricing-note"><?= htmlspecialchars((string) ($content['note'] ?? ($pricing['note'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
            </section>

            <section class="pricing-grid" id="pricingGrid" aria-label="Plans"></section>
        </section>

        <section class="pricing-support-grid" aria-label="What the software plans cover">
            <article class="support-card">
                <strong>Software</strong>
                <h3><?= htmlspecialchars((string) ($content['support_card_one_title'] ?? 'Core office software'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p><?= htmlspecialchars((string) ($content['support_card_one_text'] ?? 'Local-first CRM and intranet workflows for Metro2 review, letters, briefs, audit trails, client portals, and office operations.'), ENT_QUOTES, 'UTF-8') ?></p>
            </article>
            <article class="support-card">
                <strong>Automation</strong>
                <h3><?= htmlspecialchars((string) ($content['support_card_two_title'] ?? 'Browser companion'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p><?= htmlspecialchars((string) ($content['support_card_two_text'] ?? 'Office-paired browser automation for supported provider imports, direct API capture routing, and less manual intake work.'), ENT_QUOTES, 'UTF-8') ?></p>
            </article>
            <article class="support-card">
                <strong>Included</strong>
                <h3><?= htmlspecialchars((string) ($content['support_card_three_title'] ?? 'Legal intake included'), ENT_QUOTES, 'UTF-8') ?></h3>
                <p><?= htmlspecialchars((string) ($content['support_card_three_text'] ?? 'FCRA / FDCPA lawsuit intake belongs in the core system so every office can screen stronger issue signals without buying a separate add-on.'), ENT_QUOTES, 'UTF-8') ?></p>
            </article>
        </section>

        <section class="addons" aria-label="Add-ons">
            <div class="section-heading">
                <strong>Multi-office add-on</strong>
                <h2>Add another office node when the company actually needs one.</h2>
                <p>Legal intake is included in the software plans. The extra checkout add-on is for another local install: a second office, a branch, a front desk machine, or a separate node that should stay tied to the same CreditSoft setup.</p>
            </div>

            <div class="addon-grid">
                <article class="addon-card">
                    <span class="addon-kicker">Multi-office</span>
                    <h3><?= htmlspecialchars((string) ($clusterAddon['name'] ?? 'Cluster license'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <div class="addon-price-stack">
                        <span class="addon-price-list">$<?= number_format((float) ($clusterAddon['list_monthly'] ?? 29.95), 2) ?>/mo</span>
                        <div class="addon-price">$<?= number_format((float) ($clusterAddon['monthly'] ?? 19.95), 2) ?>/mo</div>
                        <p class="addon-price-note">Per additional office install. Requires an active Enterprise or Enterprise Pro license first.</p>
                    </div>
                    <p><?= htmlspecialchars((string) ($clusterAddon['description'] ?? 'Additional office install for a second office, branch, front desk machine, or dedicated local node.'), ENT_QUOTES, 'UTF-8') ?></p>
                    <ul>
                        <?php foreach ((array) ($clusterAddon['features'] ?? []) as $feature): ?>
                            <li><?= htmlspecialchars((string) $feature, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="plan-actions">
                        <a href="/checkout?plan=enterprise&billing=monthly&addon=cluster" class="btn-row-link primary">Buy Enterprise with node</a>
                        <a href="/checkout?plan=cluster&billing=monthly" class="btn-row-link secondary">Add node to existing license</a>
                    </div>
                </article>
            </div>
        </section>

        <section class="website-bottom" aria-label="Managed website package">
            <div class="website-bottom-copy">
                <strong>Managed websites</strong>
                <h2>Need the public website too? Start here after the software plan.</h2>
                <p>A branded CreditSoft-connected website is a separate build because it includes public pages, intake, portal entry points, launch copy, and deployment work. It belongs at the bottom of pricing, not mixed into the software plan chart.</p>
                <div class="website-bottom-price">
                    <span class="list">$695+</span>
                    <div class="sale">$495<span>+</span></div>
                </div>
            </div>
            <div class="website-bottom-details">
                <ul>
                    <li>Branded public website and landing pages</li>
                    <li>Lead forms routed into CreditSoft CRM and intake</li>
                    <li>Portal and status calls-to-action wired into the site</li>
                    <li>SEO basics, share image setup, and launch handoff</li>
                </ul>
                <a href="/websites" class="btn-row-link">View managed websites</a>
            </div>
        </section>

        <section class="faq" aria-label="Pricing FAQ">
            <div class="section-heading">
                <strong>FAQ</strong>
                <h2>Simple answers before someone reaches out.</h2>
            </div>
            <article class="faq-item">
                <h4>Is the website offer included in the software subscription?</h4>
                <p>No. Managed Websites is a separate branded build that starts at $495+ and ties your public site back into CreditSoft CRM, portal, and intake workflows.</p>
            </article>
            <article class="faq-item">
                <h4>Can I change plans later?</h4>
                <p>Yes. Start on the software plan your office needs today and move up when you want the browser companion or deeper automation.</p>
            </article>
            <article class="faq-item">
                <h4>What if I have more than one office?</h4>
                <p>Use a cluster license for each additional office install. CreditSoft is local-first, so each office can keep its own machine and still be tied together through private networking instead of sharing one overloaded box.</p>
            </article>
            <article class="faq-item">
                <h4>What systems do you support?</h4>
                <p>Supported office setups are current macOS, Ubuntu LTS, and Windows 11+ on modern hardware. If an older machine is still worth saving, we can often help repurpose it onto Ubuntu with a new SSD, maxed memory, and a sane network setup. <a href="/requirements">See full requirements</a>.</p>
            </article>
        </section>
    </div>
</div>

<script>
    (() => {
        const plans = <?= json_encode($plans, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        const zelleLogo = <?= json_encode($zelleLogo, JSON_UNESCAPED_SLASHES) ?>;
        const cashAppLogo = <?= json_encode($cashAppLogo, JSON_UNESCAPED_SLASHES) ?>;
        const grid = document.getElementById('pricingGrid');
        const buttons = Array.from(document.querySelectorAll('[data-billing-view]'));

        let activeView = 'monthly';

        const money = (amount) => '$' + Number(amount || 0).toFixed(2);
        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const modeLabel = (view) => ({
            monthly: 'Monthly billing',
            yearly: 'Annual billing',
            lifetime: 'Lifetime interest'
        }[view] || 'Billing');

        const actionLabel = (view) => ({
            monthly: 'Checkout monthly',
            yearly: 'Checkout annually',
            lifetime: 'Ask about lifetime'
        }[view] || 'Checkout');

        const actionSubLabel = (view) => ({
            monthly: 'Zelle / Cash App',
            yearly: 'Zelle / Cash App',
            lifetime: 'Custom quote'
        }[view] || 'Continue');

        const actionClass = (view) => view === 'monthly' ? 'primary' : 'secondary';
        const paymentLogosMarkup = () => `
            <div class="plan-payment-note" aria-label="Payment methods">
                <span>Pay with</span>
                ${zelleLogo ? `<img src="${escapeHtml(zelleLogo)}" alt="Zelle">` : '<span>Zelle</span>'}
                <span>or</span>
                ${cashAppLogo ? `<img src="${escapeHtml(cashAppLogo)}" alt="Cash App">` : '<span>Cash App</span>'}
            </div>
        `;

        function renderPlan(planKey, plan, view) {
            const featured = plan.featured ? ' featured' : '';
            const kicker = view === 'lifetime'
                ? (plan.sale_badge_lifetime || 'Lifetime')
                : (view === 'yearly'
                    ? (plan.sale_badge_yearly || 'Annual')
                    : (plan.sale_badge_monthly || 'Monthly'));
            const description = plan.description?.[view]
                || (view === 'lifetime'
                    ? 'Talk to us about one-time ownership pricing for this office.'
                    : 'CreditSoft office plan.');
            const checkoutPlan = planKey === 'enterprise_pro' ? 'enterprise-pro' : planKey;
            const checkoutHref = '/checkout?plan=' + encodeURIComponent(checkoutPlan) + '&billing=' + encodeURIComponent(view);
            const features = Array.isArray(plan.features) ? plan.features : [];

            let priceMarkup = '';

            if (view === 'lifetime') {
                priceMarkup = `
                    <div class="price-contact-box">
                        <strong>Lifetime interest</strong>
                        <div class="contact-title">Email for pricing</div>
                        <p>Tell us what one-time ownership would actually be worth for this office.</p>
                    </div>
                `;
            } else {
                const listPrice = view === 'yearly' ? plan.list_yearly : plan.list_monthly;
                const salePrice = view === 'yearly' ? plan.yearly : plan.monthly;
                const suffix = view === 'yearly' ? '/yr' : '/mo';
                const equivalent = view === 'yearly'
                    ? `<div class="price-equivalent">Equivalent to ${money((Number(plan.yearly || 0) / 12))}/mo while the annual discount is live.</div>`
                    : '';

                priceMarkup = `
                    <div class="price-band">
                        <strong>${escapeHtml(modeLabel(view))}</strong>
                        <div class="price-line">
                            <span class="list">${escapeHtml(money(listPrice))}${escapeHtml(suffix)}</span>
                            <span class="sale">${escapeHtml(money(salePrice))}<span>${escapeHtml(suffix)}</span></span>
                        </div>
                        ${equivalent}
                        <div class="price-desc">${escapeHtml(description)}</div>
                    </div>
                `;
            }

            return `
                <article class="plan-card${featured}">
                    <div class="plan-top">
                        <span class="kicker${plan.featured ? ' alt' : ''}">${escapeHtml(kicker)}</span>
                        <h2>${escapeHtml(plan.name || 'Plan')}</h2>
                        <span class="plan-mode-pill">${escapeHtml(modeLabel(view))}</span>
                        <p>${escapeHtml(description)}</p>
                    </div>
                    <div class="price-rail">
                        ${priceMarkup}
                    </div>
                    <ul class="feature-list">
                        ${features.map((feature) => `<li>${escapeHtml(feature)}</li>`).join('')}
                    </ul>
                    <div class="plan-actions">
                        ${view === 'lifetime' ? '' : paymentLogosMarkup()}
                        <a class="btn-row-link ${actionClass(view)}" href="${checkoutHref}">
                            <span>${escapeHtml(actionLabel(view))}</span>
                            <small>${escapeHtml(actionSubLabel(view))}</small>
                        </a>
                    </div>
                </article>
            `;
        }

        function render(view) {
            activeView = view;
            buttons.forEach((button) => {
                const selected = button.dataset.billingView === view;
                button.classList.toggle('is-active', selected);
                button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });

            grid.innerHTML = Object.entries(plans)
                .map(([planKey, plan]) => renderPlan(planKey, plan, view))
                .join('');
        }

        buttons.forEach((button) => {
            button.addEventListener('click', () => render(button.dataset.billingView || 'monthly'));
        });

        render(activeView);
    })();
</script>

<?php require __DIR__ . '/footer.php'; ?>
