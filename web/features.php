<?php
$page_title = 'Features';
$page_description = 'See CreditSoft CRM, client systems, SMTP/email delivery, Meta management, multi-office nodes, redundant office backups, and the managed PostgreSQL/PHP stack behind the product.';
$page_hero = false;
require __DIR__ . '/header.php';
?>
<style>
    .nav-spacer { display:none; }
    .features-top {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #2563eb 100%);
        color: white;
        padding: 198px 20px 92px;
        position: relative;
        overflow: hidden;
    }
    .features-top::before {
        content: '';
        position: absolute;
        inset: -20% auto auto -10%;
        width: 620px;
        height: 620px;
        background: radial-gradient(circle, rgba(255,255,255,0.14) 0%, rgba(255,255,255,0) 68%);
        pointer-events: none;
    }
    .features-top__shell,
    .features-wrap { max-width: 1180px; margin: 0 auto; }
    .features-top__shell {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: minmax(0, 0.94fr) minmax(0, 1.06fr);
        gap: 30px;
        align-items: center;
    }
    .features-top__copy { display:grid; gap:18px; }
    .features-kicker {
        display:inline-flex;
        align-items:center;
        gap:8px;
        width:max-content;
        padding:8px 14px;
        border-radius:999px;
        background:rgba(255,255,255,0.12);
        border:1px solid rgba(255,255,255,0.18);
        color:#dbeafe;
        font-size:12px;
        font-weight:800;
        letter-spacing:.08em;
        text-transform:uppercase;
    }
    .features-top h1 {
        font-size: clamp(2.5rem, 4.8vw, 4.45rem);
        line-height: 0.97;
        margin:0;
    }
    .features-top h1 span { color:#93c5fd; }
    .features-top p {
        font-size: 18px;
        line-height: 1.7;
        color: rgba(255,255,255,0.86);
        max-width: 600px;
        margin:0;
    }
    .features-top__points {
        list-style:none;
        margin:0;
        padding:0;
        display:grid;
        gap:10px;
        max-width: 620px;
    }
    .features-top__points li {
        padding:14px 16px;
        border-radius:16px;
        background:rgba(15,23,42,0.34);
        border:1px solid rgba(191,219,254,0.16);
        color:#e2e8f0;
    }
    .features-top__actions {
        display:flex;
        flex-wrap:wrap;
        gap:12px;
        margin-top: 6px;
    }
    .features-top__actions .btn-outline {
        border:1.5px solid rgba(255,255,255,0.28);
        background: rgba(255,255,255,0.06);
        color:white;
    }
    .features-top__visual { position:relative; }
    .hero-shot {
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.14);
        border-radius: 28px;
        padding: 16px;
        box-shadow: 0 24px 60px rgba(15,23,42,0.36);
        backdrop-filter: blur(10px);
    }
    .hero-shot img {
        width:100%;
        display:block;
        border-radius:20px;
    }
    .shot-link {
        display:block;
        position:relative;
        text-decoration:none;
        color:inherit;
        cursor: zoom-in;
    }
    .shot-link:hover { text-decoration:none; }
    .shot-link::after {
        content:'Open full size';
        position:absolute;
        right:14px;
        bottom:14px;
        padding:8px 10px;
        border-radius:999px;
        background:rgba(15,23,42,0.86);
        color:white;
        font-size:11px;
        font-weight:800;
        letter-spacing:.08em;
        text-transform:uppercase;
        box-shadow:0 10px 20px rgba(15,23,42,.25);
        opacity:0;
        transform:translateY(6px);
        transition:opacity .18s ease, transform .18s ease;
        pointer-events:none;
    }
    .shot-link:hover::after,
    .shot-link:focus-visible::after {
        opacity:1;
        transform:translateY(0);
    }
    body.lightbox-open { overflow:hidden; }
    .lightbox {
        position: fixed;
        inset: 0;
        background: rgba(2, 6, 23, 0.82);
        backdrop-filter: blur(10px);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 28px;
        z-index: 1000;
    }
    .lightbox.is-open { display:flex; }
    .lightbox__dialog {
        position: relative;
        width: min(1180px, 100%);
        max-height: calc(100vh - 56px);
        display: grid;
        gap: 14px;
        justify-items: center;
    }
    .lightbox__close {
        position: absolute;
        top: -6px;
        right: -6px;
        width: 42px;
        height: 42px;
        border: 0;
        border-radius: 999px;
        background: rgba(15,23,42,0.92);
        color: white;
        font-size: 22px;
        line-height: 1;
        cursor: pointer;
        box-shadow: 0 16px 28px rgba(15,23,42,.32);
    }
    .lightbox__image-wrap {
        width: 100%;
        overflow: auto;
        border-radius: 24px;
        background: rgba(255,255,255,0.06);
        border: 1px solid rgba(255,255,255,0.14);
        box-shadow: 0 24px 48px rgba(15,23,42,.28);
    }
    .lightbox__image {
        display: block;
        width: 100%;
        height: auto;
    }
    .lightbox__caption {
        width: min(940px, 100%);
        padding: 12px 16px;
        border-radius: 16px;
        background: rgba(15,23,42,0.72);
        color: #e2e8f0;
        text-align: center;
        font-size: 14px;
        line-height: 1.6;
    }
    .hero-shot__meta {
        display:flex;
        justify-content:space-between;
        gap:12px;
        align-items:center;
        padding: 14px 6px 0;
        color:#dbeafe;
        font-size:13px;
        letter-spacing:.08em;
        text-transform:uppercase;
    }
    .features-wrap { padding: 54px 20px 0; display:grid; gap:28px; }
    .proof-band {
        display:grid;
        gap:22px;
        grid-template-columns: minmax(0,1.15fr) minmax(0,0.85fr);
        align-items:start;
    }
    .proof-copy,
    .proof-grid,
    .features-grid,
    .features-links,
    .settings-strip,
    .features-band {
        width:100%;
    }
    .proof-copy {
        background:white;
        border:1px solid var(--border);
        border-radius:24px;
        padding:30px 28px;
        box-shadow:0 16px 40px rgba(15,23,42,.05);
        display:grid;
        gap:16px;
    }
    .proof-disclaimer {
        background:#fff7ed;
        border:1px solid #fed7aa;
        border-radius:20px;
        padding:18px 20px;
        display:grid;
        gap:6px;
    }
    .proof-disclaimer strong {
        font-size:12px;
        font-weight:800;
        letter-spacing:.12em;
        text-transform:uppercase;
        color:#9a3412;
    }
    .proof-disclaimer p {
        color:#7c2d12;
        font-size:14px;
        line-height:1.65;
    }
    .proof-copy h2,
    .features-strip h2,
    .features-band h2 { font-size:32px; line-height:1.08; margin:0; }
    .proof-copy p,
    .features-strip p,
    .features-band p { color:var(--gray); margin:0; }
    .proof-list {
        list-style:none;
        margin:0;
        padding:0;
        display:grid;
        gap:12px;
    }
    .proof-list li {
        padding:15px 16px;
        border-radius:16px;
        background:#f8fafc;
        border:1px solid var(--border);
    }
    .proof-grid {
        display:grid;
        gap:16px;
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
    .shot-card {
        background:white;
        border:1px solid var(--border);
        border-radius:24px;
        overflow:hidden;
        box-shadow:0 14px 34px rgba(15,23,42,.05);
    }
    .shot-card img { width:100%; display:block; }
    .shot-card__body { padding:18px 18px 20px; display:grid; gap:6px; }
    .shot-card__eyebrow {
        font-size:11px;
        font-weight:800;
        letter-spacing:.12em;
        text-transform:uppercase;
        color:#64748b;
    }
    .shot-card strong { font-size:18px; line-height:1.2; }
    .shot-card p { margin:0; color:var(--gray); font-size:14px; }
    .section-title {
        display:grid;
        gap:8px;
        margin-bottom: 6px;
    }
    .section-title--lead {
        max-width: 920px;
        margin-bottom: 12px;
        justify-items: start;
    }
    .section-title span {
        font-size:12px;
        font-weight:800;
        letter-spacing:.1em;
        text-transform:uppercase;
        color:#64748b;
    }
    .section-title h2 {
        font-size:32px;
        line-height:1.08;
        margin:0;
    }
    .section-title p { color:var(--gray); margin:0; max-width:760px; }
    .section-title--lead h2 {
        max-width: 860px;
        font-size: clamp(2rem, 3vw, 3.1rem);
        line-height: 1.12;
    }
    .section-title--lead p {
        max-width: 700px;
        font-size: 18px;
        line-height: 1.65;
    }
    .features-grid {
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:18px;
    }
    .overview-section {
        display:grid;
        gap:20px;
        padding: 8px 0 0;
    }
    .overview-map {
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:16px;
    }
    .overview-card {
        position:relative;
        min-height:188px;
        display:grid;
        align-content:space-between;
        gap:18px;
        padding:22px;
        border:1px solid var(--border);
        border-radius:22px;
        background:white;
        color:inherit;
        text-decoration:none;
        box-shadow:0 12px 30px rgba(15,23,42,.04);
        transition:border-color .18s ease, transform .18s ease, box-shadow .18s ease;
    }
    .overview-card:hover {
        text-decoration:none;
        border-color:#93c5fd;
        transform:translateY(-2px);
        box-shadow:0 18px 38px rgba(37,99,235,.08);
    }
    .overview-card__top {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:16px;
    }
    .overview-card__icon {
        width:44px;
        height:44px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        border-radius:16px;
        background:#eff6ff;
        color:#2563eb;
        font-size:19px;
    }
    .overview-card__arrow {
        color:#94a3b8;
        font-size:16px;
    }
    .overview-card strong {
        display:block;
        font-size:20px;
        line-height:1.1;
        color:var(--dark);
    }
    .overview-card span {
        display:block;
        margin-top:8px;
        color:var(--gray);
        line-height:1.55;
    }
    .feature-lane {
        background:white;
        border:1px solid var(--border);
        border-radius:22px;
        padding:24px;
        box-shadow:0 10px 35px rgba(15,23,42,.05);
        display:grid;
        gap:14px;
    }
    .feature-lane h3 { font-size:22px; margin:0; }
    .feature-lane p { color:var(--gray); margin:0; }
    .feature-lane ul {
        list-style:none;
        margin:0;
        padding:0;
        display:grid;
        gap:10px;
    }
    .feature-lane li {
        padding:14px 14px 14px 16px;
        border-radius:16px;
        border:1px solid var(--border);
        background:#f8fafc;
        color:#334155;
    }
    .feature-lane li strong { display:block; color:var(--dark); margin-bottom:4px; }
    .email-provider-grid {
        display:grid;
        grid-template-columns:repeat(5,minmax(0,1fr));
        gap:12px;
    }
    .email-provider {
        min-height:136px;
        border:1px solid var(--border);
        border-radius:16px;
        background:#f8fafc;
        padding:14px;
        display:flex;
        flex-direction:column;
        justify-content:center;
        align-items:center;
        gap:12px;
        text-align:center;
    }
    .email-provider img {
        width:auto;
        max-width:170px;
        max-height:90px;
        object-fit:contain;
    }
    .email-provider span {
        font-size:12px;
        font-weight:800;
        color:var(--dark);
    }
    .settings-strip {
        display:grid;
        grid-template-columns: repeat(3, minmax(0,1fr));
        gap:18px;
    }
    .control-section {
        background:white;
        border:1px solid var(--border);
        border-radius:28px;
        padding:28px;
        box-shadow:0 16px 42px rgba(15,23,42,.055);
        display:grid;
        gap:24px;
    }
    .control-section__top {
        display:grid;
        grid-template-columns:minmax(0,.88fr) minmax(0,1.12fr);
        gap:24px;
        align-items:start;
    }
    .control-section .section-title {
        margin:0;
        text-align:left;
        justify-items:start;
    }
    .control-section .section-title h2 {
        font-size:clamp(2rem, 3.1vw, 3.2rem);
        max-width:640px;
    }
    .control-section .section-title p {
        max-width:620px;
        font-size:17px;
        line-height:1.65;
    }
    .control-facts {
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:12px;
    }
    .control-fact {
        border:1px solid var(--border);
        border-radius:18px;
        background:#f8fafc;
        padding:16px;
        display:grid;
        gap:6px;
    }
    .control-fact strong {
        color:var(--dark);
        font-size:16px;
    }
    .control-fact span {
        color:var(--gray);
        font-size:14px;
        line-height:1.55;
    }
    .features-links {
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:18px;
    }
    .features-links a {
        display:block;
        background:white;
        border:1px solid var(--border);
        border-radius:20px;
        padding:22px;
        text-decoration:none;
        color:inherit;
        box-shadow:0 12px 30px rgba(15,23,42,.04);
    }
    .features-links a:hover { text-decoration:none; border-color:#93c5fd; box-shadow:0 16px 34px rgba(37,99,235,.08); }
    .features-links strong { display:block; font-size:18px; margin-bottom:6px; }
    .features-links span { color:var(--gray); }
    .features-strip {
        background:#fff7ed;
        border:1px solid #fed7aa;
        border-radius:24px;
        padding:28px 26px;
        display:grid;
        gap:10px;
    }
    .features-band {
        background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 55%,#2563eb 100%);
        color:white;
        border-radius:24px;
        padding:30px 28px;
        box-shadow:0 18px 40px rgba(15,23,42,.16);
        display:grid;
        gap:18px;
        grid-template-columns:minmax(0,1fr) auto;
        align-items:center;
    }
    .features-band p { color:rgba(255,255,255,.82); max-width:760px; }
    .features-band .btn { background:white; color:var(--dark); }
    .features-band .btn:hover { background:#eff6ff; text-decoration:none; }
    @media (max-width: 1080px) {
        .features-top__shell,
        .proof-band,
        .overview-map,
        .features-grid,
        .control-section__top,
        .control-facts,
        .email-provider-grid,
        .settings-strip,
        .features-links,
        .features-band { grid-template-columns:1fr; }
    }
    @media (max-width: 768px) {
        .features-top { padding-top: 184px; }
        .features-top h1 { font-size: clamp(2.08rem, 11vw, 3rem); line-height: 1.02; }
        .features-top p { font-size: 16px; line-height: 1.62; }
        .features-top__points li { padding: 12px 14px; }
        .hero-shot { border-radius: 22px; padding: 10px; }
        .hero-shot__meta { align-items: flex-start; flex-direction: column; gap: 4px; font-size: 10px; }
        .features-wrap { padding-inline: 14px; }
        .proof-copy,
        .feature-lane,
        .control-section,
        .features-strip,
        .features-band { border-radius: 20px; padding: 22px 18px; }
        .proof-grid { grid-template-columns:1fr; }
        .section-title h2,
        .proof-copy h2,
        .features-strip h2,
        .features-band h2 { font-size:28px; }
        .lightbox {
            padding: 18px;
        }
        .lightbox__close {
            top: -10px;
            right: -2px;
        }
    }
</style>

<section class="features-top">
    <div class="features-top__shell">
        <div class="features-top__copy">
            <span class="features-kicker">Real product proof</span>
            <h1>See the product before you buy it.<br><span>Actual screens. Actual workflow.</span></h1>
            <p>CreditSoft is a local-first credit repair operating system with client workspaces, review queues, billing, portal, website, social tools, and office-node controls already built in. This page shows the product as it exists today.</p>
            <ul class="features-top__points">
                <li>Work the file, review issues, route tasks, and see next actions from one app instead of juggling disconnected tabs.</li>
                <li>Run intake, client portal, billing, Meta growth, multi-office nodes, and control tools without stitching together five separate systems.</li>
                <li>Start with the software, then add public-site, backup, and automation pieces only where your company actually needs them.</li>
            </ul>
            <div class="features-top__actions">
                <a href="/pricing" class="btn btn-primary">See pricing</a>
                <a href="/subscribe" class="btn btn-outline">Start intake</a>
            </div>
        </div>
        <div class="features-top__visual">
            <div class="hero-shot">
                <a class="shot-link" href="/assets/images/product-proof/dashboard-in-browser.png" data-lightbox-caption="CreditSoft control panel in the browser on April 13, 2026." aria-label="Open the CreditSoft control panel screenshot full size">
                    <img src="/assets/images/product-proof/dashboard-in-browser.png" alt="CreditSoft control panel in the browser" loading="eager">
                    <div class="hero-shot__meta">
                        <span>Office control panel</span>
                        <span>Local-first credit operations</span>
                    </div>
                </a>
            </div>
        </div>
    </div>
</section>

<div class="features-wrap">
    <section class="proof-band">
        <div class="proof-copy">
            <div class="section-title">
                <span>Product map</span>
                <h2>The product gets easier to trust when you can actually see it.</h2>
                <p>These screenshots are the fastest way to understand what CreditSoft really is: not just a dispute letter tool, but the operating system around the work.</p>
            </div>
            <ul class="proof-list">
                <li><strong>Dashboard and CFO</strong> keep revenue, workload, and impact numbers in view instead of burying the business side in spreadsheets.</li>
                <li><strong>Client roster and inbox</strong> keep ownership, review pressure, and next actions visible so your team can actually move files forward.</li>
                <li><strong>Tasks, API, AI, nodes, and connectivity</strong> let your company control integrations, automation, remote offices, and redundancy without giving up the local-first model.</li>
            </ul>
            <div class="proof-disclaimer">
                <strong>Screenshot note</strong>
                <p>The CRM view was refreshed from the working app on April 19, 2026. Other proof shots are updated as the product lanes settle.</p>
            </div>
        </div>
        <div class="proof-grid">
            <article class="shot-card">
                <a class="shot-link" href="/assets/images/product-proof/clients-roster-expanded-20260419.png" data-lightbox-caption="Current CRM roster with billing health, provider status, and client actions." aria-label="Open the CreditSoft client roster screenshot full size">
                    <img src="/assets/images/product-proof/clients-roster-expanded-20260419.png" alt="CreditSoft client roster screen" loading="lazy">
                </a>
                <div class="shot-card__body">
                    <span class="shot-card__eyebrow">Clients</span>
                    <strong>Client roster with billing health built in</strong>
                    <p>See ownership, provider status, payment signals, status, and cycle context without leaving the main workspace.</p>
                </div>
            </article>
            <article class="shot-card">
                <a class="shot-link" href="/assets/images/product-proof/inbox-queue.png" data-lightbox-caption="Inbox and review queue view from April 13, 2026." aria-label="Open the CreditSoft inbox screenshot full size">
                    <img src="/assets/images/product-proof/inbox-queue.png" alt="CreditSoft inbox and review queue" loading="lazy">
                </a>
                <div class="shot-card__body">
                    <span class="shot-card__eyebrow">Inbox</span>
                    <strong>Review queue instead of scattered follow-up</strong>
                    <p>Due-soon work, high-severity review, and next actions stay visible so your team can move the file forward.</p>
                </div>
            </article>
            <article class="shot-card">
                <a class="shot-link" href="/assets/images/product-proof/tasks-board.png" data-lightbox-caption="Task board view from April 13, 2026." aria-label="Open the CreditSoft task board screenshot full size">
                    <img src="/assets/images/product-proof/tasks-board.png" alt="CreditSoft task board" loading="lazy">
                </a>
                <div class="shot-card__body">
                    <span class="shot-card__eyebrow">Tasks</span>
                    <strong>Operational work tied to the client</strong>
                    <p>Create and advance work without pretending task management lives outside the product.</p>
                </div>
            </article>
            <article class="shot-card">
                <a class="shot-link" href="/assets/images/product-proof/cfo-dashboard.png" data-lightbox-caption="CFO dashboard view from April 13, 2026." aria-label="Open the CreditSoft CFO screenshot full size">
                    <img src="/assets/images/product-proof/cfo-dashboard.png" alt="CreditSoft CFO dashboard" loading="lazy">
                </a>
                <div class="shot-card__body">
                    <span class="shot-card__eyebrow">CFO</span>
                    <strong>Revenue and throughput in the same system</strong>
                    <p>MRR, client lifespan, churn signals, and workload belong inside the product, not in a separate spreadsheet habit.</p>
                </div>
            </article>
        </div>
    </section>

    <section class="overview-section">
        <div class="section-title section-title--lead">
            <span>Overview map</span>
            <h2>The stack at a glance.</h2>
            <p>Use this page to understand the product shape quickly. Each lane now has its own page when you want the expanded details.</p>
        </div>
        <div class="overview-map" aria-label="CreditSoft feature overview pages">
            <a class="overview-card" href="/crm">
                <span class="overview-card__top"><span class="overview-card__icon"><i class="fa-solid fa-address-book" aria-hidden="true"></i></span><span class="overview-card__arrow"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span></span>
                <span><strong>Local-first CRM</strong><span>Leads, clients, billing signals, provider access, tasks, and follow-up.</span></span>
            </a>
            <a class="overview-card" href="/client-system">
                <span class="overview-card__top"><span class="overview-card__icon"><i class="fa-solid fa-users-gear" aria-hidden="true"></i></span><span class="overview-card__arrow"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span></span>
                <span><strong>Client system</strong><span>Client records, casework, report review, files, letters, and follow-up.</span></span>
            </a>
            <a class="overview-card" href="/client-portal">
                <span class="overview-card__top"><span class="overview-card__icon"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></span><span class="overview-card__arrow"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span></span>
                <span><strong>Client portal</strong><span>Customer uploads, branded access, and status tied back to the office.</span></span>
            </a>
            <a class="overview-card" href="/email-delivery">
                <span class="overview-card__top"><span class="overview-card__icon"><i class="fa-solid fa-envelope-circle-check" aria-hidden="true"></i></span><span class="overview-card__arrow"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span></span>
                <span><strong>Email providers</strong><span>Microsoft 365, Google Workspace, SES, SendGrid, Mailgun, and more.</span></span>
            </a>
            <a class="overview-card" href="/office-nodes">
                <span class="overview-card__top"><span class="overview-card__icon"><i class="fa-solid fa-network-wired" aria-hidden="true"></i></span><span class="overview-card__arrow"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span></span>
                <span><strong>Multi-office nodes</strong><span>Local office servers, manager visibility, and approved private routes.</span></span>
            </a>
            <a class="overview-card" href="/office-backup">
                <span class="overview-card__top"><span class="overview-card__icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span><span class="overview-card__arrow"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span></span>
                <span><strong>Backup redundancy</strong><span>Peer backup, queued sync, and clearer recovery behavior.</span></span>
            </a>
            <a class="overview-card" href="/tech-stack">
                <span class="overview-card__top"><span class="overview-card__icon"><i class="fa-solid fa-server" aria-hidden="true"></i></span><span class="overview-card__arrow"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span></span>
                <span><strong>Tech stack</strong><span>PostgreSQL, PHP 8.5, OPcache, queues, router, and managed setup.</span></span>
            </a>
        </div>
    </section>

    <section class="control-section" id="stack-control">
        <div class="control-section__top">
            <div class="section-title">
                <span>Settings and control</span>
                <h2>Own the setup without babysitting the stack.</h2>
                <p>Provider keys, partner API access, public callbacks, local connectivity, AI providers, and node behavior belong inside the same system as the work. If you give us access to the system, CreditSoft handles the setup.</p>
            </div>
            <div class="control-facts">
                <div class="control-fact">
                    <strong>API and domain bridge</strong>
                    <span>Stable public domains can relay portal traffic, Meta callbacks, and website integrations without exposing the local intranet.</span>
                </div>
                <div class="control-fact">
                    <strong>Connectivity modes</strong>
                    <span>Local-first access can be paired with ngrok, Tailscale, and office-node rules when the installation needs outside access.</span>
                </div>
                <div class="control-fact">
                    <strong>SMTP delivery</strong>
                    <span>Microsoft 365, Google Workspace, Amazon SES, SendGrid, Mailgun, Zoho Mail, Postmark, Brevo, SMTP.com, and custom SMTP can be configured from Settings without remote logo loads.</span>
                </div>
                <div class="control-fact">
                    <strong>PostgreSQL direction</strong>
                    <span>The intranet is being prepared around PostgreSQL-backed operation for stronger data, reporting, and multi-node support.</span>
                </div>
                <div class="control-fact">
                    <strong>Managed installation</strong>
                    <span>CreditSoft can configure PHP 8.5, OPcache, database, callbacks, and backups when the customer provides access.</span>
                </div>
            </div>
        </div>
        <div class="settings-strip">
            <article class="shot-card">
                <a class="shot-link" href="/assets/images/product-proof/api-settings.png" data-lightbox-caption="API settings view from April 13, 2026." aria-label="Open the CreditSoft API settings screenshot full size">
                    <img src="/assets/images/product-proof/api-settings.png" alt="CreditSoft API settings" loading="lazy">
                </a>
                <div class="shot-card__body">
                    <span class="shot-card__eyebrow">API</span>
                    <strong>Partner API, domain, and callback controls</strong>
                    <p>Token-protected integration details for the website bridge, portal, installer, Meta callbacks, and companion capture.</p>
                </div>
            </article>
            <article class="shot-card">
                <a class="shot-link" href="/assets/images/product-proof/ai-settings.png" data-lightbox-caption="AI settings view from April 13, 2026." aria-label="Open the CreditSoft AI settings screenshot full size">
                    <img src="/assets/images/product-proof/ai-settings.png" alt="CreditSoft AI provider settings" loading="lazy">
                </a>
                <div class="shot-card__body">
                    <span class="shot-card__eyebrow">AI</span>
                    <strong>Provider choices with validation</strong>
                    <p>Choose which drafting, social planning, summarization, and review provider your company should trust without losing visibility.</p>
                </div>
            </article>
            <article class="shot-card">
                <a class="shot-link" href="/assets/images/product-proof/connectivity-settings.png" data-lightbox-caption="Connectivity settings view from April 13, 2026." aria-label="Open the CreditSoft connectivity settings screenshot full size">
                    <img src="/assets/images/product-proof/connectivity-settings.png" alt="CreditSoft connectivity settings" loading="lazy">
                </a>
                <div class="shot-card__body">
                    <span class="shot-card__eyebrow">Connectivity</span>
                    <strong>Local-first node and tunnel rules</strong>
                    <p>Keep ngrok, Tailscale, partner access, and privacy-sensitive feedback inside clear company-scoped boundaries.</p>
                </div>
            </article>
        </div>
    </section>

    <div class="features-links">
        <a href="/crm">
            <strong>Local-first CRM</strong>
            <span>See how leads, clients, billing signals, provider login status, notes, and follow-up stay in one office workspace.</span>
        </a>
        <a href="/client-system">
            <strong>Client system</strong>
            <span>See how client files, portal uploads, letters, reports, and follow-up stay tied together.</span>
        </a>
        <a href="/email-delivery">
            <strong>SMTP / email delivery</strong>
            <span>See supported outbound providers and how CRM follow-up gets flagged from provider-login failures.</span>
        </a>
        <a href="/office-nodes">
            <strong>Multi-office nodes</strong>
            <span>See how local office installs, manager visibility, and faster node routing fit the product direction.</span>
        </a>
        <a href="/office-backup">
            <strong>Office backup redundancy</strong>
            <span>See how one office node can help protect another instead of leaving recovery on one machine.</span>
        </a>
        <a href="/tech-stack">
            <strong>Tech stack and setup</strong>
            <span>See the PostgreSQL, PHP 8.5, OPcache, and managed setup direction behind CreditSoft.</span>
        </a>
        <a href="/roadmap">
            <strong>Roadmap</strong>
            <span>See what is available now, what is in progress, and what is still on deck.</span>
        </a>
        <a href="/options">
            <strong>Options roadmap</strong>
            <span>See communications, fulfillment, and extra company support as their own product track.</span>
        </a>
        <a href="/migration">
            <strong>Migration</strong>
            <span>See how CreditSoft thinks about moving a real company without making migration sound magical.</span>
        </a>
        <a href="/outsourcing">
            <strong>Outsourcing</strong>
            <span>See why tighter automation should reduce outsourcing pressure instead of increasing dependency.</span>
        </a>
        <a href="/client-portal">
            <strong>Client portal</strong>
            <span>See the client-facing side of the product and how it fits into the company workflow.</span>
        </a>
        <a href="/api-bridge">
            <strong>Website API bridge</strong>
            <span>See how public domains carry Meta callbacks, portals, and website integrations safely.</span>
        </a>
        <a href="/social-media">
            <strong>Social / Meta manager</strong>
            <span>See Meta Graph v25.0, content calendar, publishing controls, creator goals, and lead handoff.</span>
        </a>
        <a href="/websites">
            <strong>Branded websites</strong>
            <span>See how managed websites tie back into intake and portal packaging.</span>
        </a>
    </div>

    <div class="features-strip">
        <h2>See the rest of the stack.</h2>
        <p>Pricing shows what the software costs, migration shows how your company moves over, and the client portal shows what your customers will actually use. Multi-office and backup setup is handled with you when system access is provided.</p>
    </div>

    <section class="features-band">
        <div>
            <h2>If the workflow looks right, the next step is choosing the setup that fits your company.</h2>
            <p>Start with the core software. Add intake, managed websites, or extra support only where they actually help your company.</p>
        </div>
        <a href="/pricing" class="btn btn-primary">See pricing</a>
    </section>
</div>

<div class="lightbox" id="featureLightbox" aria-hidden="true">
    <div class="lightbox__dialog" role="dialog" aria-modal="true" aria-label="Feature screenshot preview">
        <button type="button" class="lightbox__close" id="featureLightboxClose" aria-label="Close screenshot preview">&times;</button>
        <div class="lightbox__image-wrap">
            <img class="lightbox__image" id="featureLightboxImage" src="" alt="">
        </div>
        <div class="lightbox__caption" id="featureLightboxCaption"></div>
    </div>
</div>

<script>
(() => {
    const lightbox = document.getElementById('featureLightbox');
    const image = document.getElementById('featureLightboxImage');
    const caption = document.getElementById('featureLightboxCaption');
    const closeButton = document.getElementById('featureLightboxClose');
    const links = document.querySelectorAll('.shot-link');

    if (!lightbox || !image || !caption || !closeButton || !links.length) {
        return;
    }

    const closeLightbox = () => {
        lightbox.classList.remove('is-open');
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('lightbox-open');
        image.src = '';
        image.alt = '';
        caption.textContent = '';
    };

    links.forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const previewImage = link.querySelector('img');
            image.src = link.href;
            image.alt = previewImage?.alt || '';
            caption.textContent = link.dataset.lightboxCaption || previewImage?.alt || '';
            lightbox.classList.add('is-open');
            lightbox.setAttribute('aria-hidden', 'false');
            document.body.classList.add('lightbox-open');
        });
    });

    closeButton.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', (event) => {
        if (event.target === lightbox) {
            closeLightbox();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && lightbox.classList.contains('is-open')) {
            closeLightbox();
        }
    });
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
