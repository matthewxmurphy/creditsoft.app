<?php
$page_title = 'Social / Meta Manager';
$page_description = 'Run Meta marketing, Graph v25.0 Page connection, publishing, and lead follow-up from the same CreditSoft stack.';
$page_hero = true;
$hero_class = '';
$hero_title = 'Run Meta marketing, publishing, and lead follow-up from one stack.';
$hero_subtitle = 'CreditSoft gives your office a Social / Meta manager with a current Meta Graph v25.0 connector, calendar planning, publishing controls, branded website handoff, and better lead follow-up without stitching together disconnected tools.';

$social_gallery = [
    [
        'image' => '/assets/images/social-proof-web/social-meta-overview.webp',
        'alt' => 'CreditSoft Social and Meta overview screen',
        'caption' => 'Social / Meta overview screen from April 14, 2026.',
        'eyebrow' => 'Social / Meta',
        'title' => 'Meta, Instagram, and WhatsApp in one office lane',
        'copy' => 'The office can see Graph v25.0 connection status, callback readiness, page context, and the next move without bouncing between tools.',
    ],
    [
        'image' => '/assets/images/social-proof-web/master-calendar-month.webp',
        'alt' => 'CreditSoft master calendar month view',
        'caption' => 'Master calendar month view from April 14, 2026.',
        'eyebrow' => 'Master calendar',
        'title' => 'A real calendar workspace, not a pile of notes',
        'copy' => 'Month view keeps content blocks, booked consults, and follow-up timing visible in one place.',
    ],
    [
        'image' => '/assets/images/social-proof-web/master-calendar-planner.webp',
        'alt' => 'CreditSoft calendar planner and scheduling lane',
        'caption' => 'Master calendar planner and scheduling lane from April 14, 2026.',
        'eyebrow' => 'Planner',
        'title' => 'AI planning and scheduling lanes beside the calendar',
        'copy' => 'Selected-day detail, planning prompts, and scheduling lanes sit right beside the main calendar instead of hiding in another tool.',
    ],
    [
        'image' => '/assets/images/social-proof-web/social-calendar-month.webp',
        'alt' => 'CreditSoft social calendar month view',
        'caption' => 'Social content calendar month view from April 14, 2026.',
        'eyebrow' => 'Content calendar',
        'title' => 'The social lane has its own month view',
        'copy' => 'Content planning shows what is publishing, what is booked, and where the office still has room to push more activity.',
    ],
    [
        'image' => '/assets/images/social-proof-web/social-calendar-planner.webp',
        'alt' => 'CreditSoft social calendar planner and meeting lane',
        'caption' => 'Social planner and meeting lane from April 14, 2026.',
        'eyebrow' => 'Calendar detail',
        'title' => 'Weekly content plan and scheduling handoff',
        'copy' => 'AI planner cards, selected-day detail, and the meeting lane stay in the same workspace instead of splitting social from operations.',
    ],
    [
        'image' => '/assets/images/social-proof-web/meta-whatsapp-settings.webp',
        'alt' => 'CreditSoft Meta and WhatsApp settings screen',
        'caption' => 'Meta business and WhatsApp settings from April 14, 2026.',
        'eyebrow' => 'Meta + WhatsApp',
        'title' => 'Connection and follow-up setup in one screen',
        'copy' => 'Meta app identity, callback rules, and WhatsApp handoff stay aligned instead of being buried across multiple vendors.',
    ],
    [
        'image' => '/assets/images/social-proof-web/publishing-lane.webp',
        'alt' => 'CreditSoft publishing lane screen',
        'caption' => 'Publishing lane controls from April 14, 2026.',
        'eyebrow' => 'Publishing',
        'title' => 'Posting controls that feel operational',
        'copy' => 'Approval, cadence, Facebook Page posting, Instagram rules, and CTA defaults stay easy to scan before anything goes live.',
    ],
    [
        'image' => '/assets/images/social-proof-web/ads-lane.webp',
        'alt' => 'CreditSoft ads and lead capture lane screen',
        'caption' => 'Ads and lead capture lane from April 14, 2026.',
        'eyebrow' => 'Lead ads',
        'title' => 'Lead capture setup without the guesswork',
        'copy' => 'Default ad account, objective, budget, and destination stay tied to the same office process that actually works the lead.',
    ],
];

$featured_shot = $social_gallery[0];
$gallery_shots = array_slice($social_gallery, 1);
$creator_score_rules = [
    [
        'points' => '+6',
        'label' => 'Public comment',
        'copy' => 'Score visible page comments the office can moderate, respond to, and verify later.',
    ],
    [
        'points' => '+8',
        'label' => 'Public share',
        'copy' => 'Reward public shares because private shares are not visible enough to audit fairly.',
    ],
    [
        'points' => '+3',
        'label' => 'Published post',
        'copy' => 'Give credit for keeping the page active so weekly challenges reward cadence too.',
    ],
    [
        'points' => '+1 / 5 likes',
        'label' => 'Liked comment bonus',
        'copy' => 'Add bonus weight when a comment keeps attracting reactions after it is posted.',
    ],
];
$creator_goals = [
    'Track the page\'s weekly creator challenge so the office can see which target still needs help.',
    'Recommend the next post, reply window, or follow-up move when the page is behind on comments, shares, or publishing cadence.',
    'Keep contest activity tied back to leads, consults, and office follow-up instead of treating engagement like vanity metrics.',
];
$creator_rankings = [
    [
        'placement' => '1',
        'title' => 'First place winner',
        'copy' => 'Highest verified score after required challenge goals are met.',
    ],
    [
        'placement' => '2',
        'title' => 'Second place',
        'copy' => 'Strong score with goal completion, but behind the top finisher.',
    ],
    [
        'placement' => '3',
        'title' => 'Third place',
        'copy' => 'Still leaderboard worthy when comments, shares, and cadence stay consistent.',
    ],
    [
        'placement' => '4-10',
        'title' => 'Placement tier',
        'copy' => 'Optional top-ten bracket for offices that want deeper contest visibility.',
    ],
];
require __DIR__ . '/header.php';
?>
<style>
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
    .social-wrap { max-width: 1140px; margin: 0 auto; padding: 44px 20px 0; display:grid; gap:22px; }
    .social-shell { display:grid; grid-template-columns:1.02fr .98fr; gap:24px; align-items:start; }
    .social-panel,
    .social-proof-copy,
    .social-hero-shot,
    .social-gallery-section,
    .social-stage,
    .social-band,
    .social-engine,
    .social-context-panel {
        background:white;
        border:1px solid var(--border);
        border-radius:24px;
        box-shadow:0 16px 40px rgba(15,23,42,.05);
    }
    .social-panel,
    .social-proof-copy,
    .social-gallery-section,
    .social-stage,
    .social-engine,
    .social-context-panel {
        padding:28px 26px;
    }
    .social-kicker {
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:8px 12px;
        border-radius:999px;
        font-size:12px;
        font-weight:800;
        letter-spacing:.08em;
        text-transform:uppercase;
        margin-bottom:14px;
        background:#dbeafe;
        color:#1d4ed8;
    }
    .social-kicker i {
        font-size:14px;
    }
    .social-panel h2,
    .social-context-panel h2,
    .social-engine h2,
    .social-band h2 {
        font-size:30px;
        line-height:1.08;
        margin:0 0 10px;
    }
    .social-panel p,
    .social-context-panel p,
    .social-engine p,
    .social-band p {
        color:var(--gray);
        margin:0;
    }
    .social-list {
        list-style:none;
        margin:18px 0 0;
        padding:0;
        display:grid;
        gap:12px;
    }
    .social-list li {
        padding:14px 16px;
        border:1px solid var(--border);
        border-radius:16px;
        background:#f8fafc;
        color:#334155;
    }
    .social-list strong {
        display:block;
        color:var(--dark);
        margin-bottom:4px;
    }
    .social-board {
        background:linear-gradient(135deg,#0f172a 0%, #1e3a5f 55%, #2563eb 100%);
        color:white;
        border-radius:24px;
        padding:28px;
        box-shadow:0 18px 42px rgba(15,23,42,.16);
        display:grid;
        gap:18px;
    }
    .social-board p { color:rgba(255,255,255,.82); }
    .social-board__eyebrow {
        display:inline-flex;
        width:max-content;
        align-items:center;
        gap:8px;
        padding:8px 12px;
        border-radius:999px;
        font-size:12px;
        font-weight:800;
        letter-spacing:.08em;
        text-transform:uppercase;
        color:#dbeafe;
        background:rgba(255,255,255,.1);
        border:1px solid rgba(255,255,255,.14);
    }
    .social-board__eyebrow i {
        font-size:14px;
    }
    .social-stage-grid {
        display:grid;
        gap:14px;
    }
    .social-stage {
        background:rgba(255,255,255,.08);
        border:1px solid rgba(191,219,254,.18);
        box-shadow:none;
    }
    .social-stage span {
        display:inline-flex;
        margin-bottom:8px;
        font-size:12px;
        font-weight:800;
        letter-spacing:.08em;
        text-transform:uppercase;
        color:#93c5fd;
    }
    .social-stage strong {
        display:block;
        font-size:18px;
        margin-bottom:6px;
    }
    .social-stage p {
        color:rgba(255,255,255,.8);
    }
    .social-columns {
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:18px;
    }
    .social-band {
        background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 55%,#2563eb 100%);
        color:white;
        padding:28px;
        display:grid;
        grid-template-columns:minmax(0,1fr) auto;
        gap:18px;
        align-items:center;
        box-shadow:0 18px 40px rgba(15,23,42,.16);
    }
    .social-band p {
        color:rgba(255,255,255,.82);
        max-width:760px;
    }
    .social-band .btn {
        background:white;
        color:var(--dark);
    }
    .social-band .btn:hover {
        background:#eff6ff;
        text-decoration:none;
    }
    .social-proof-band {
        display:grid;
        grid-template-columns:minmax(0, .92fr) minmax(0, 1.08fr);
        gap:20px;
        align-items:stretch;
    }
    .social-proof-copy {
        display:grid;
        gap:14px;
        box-shadow:0 16px 40px rgba(15,23,42,.05);
    }
    .social-proof-copy h2,
    .social-gallery-section h2 {
        margin:0;
        font-size:32px;
        line-height:1.08;
        color:var(--dark);
    }
    .social-proof-copy p,
    .social-gallery-section p {
        margin:0;
        color:var(--gray);
    }
    .social-proof-disclaimer {
        background:#eff6ff;
        border:1px solid #bfdbfe;
        border-radius:18px;
        padding:18px 20px;
        display:grid;
        gap:6px;
    }
    .social-proof-disclaimer strong {
        font-size:12px;
        font-weight:800;
        letter-spacing:.08em;
        text-transform:uppercase;
        color:#1d4ed8;
    }
    .social-hero-shot {
        padding:0;
        overflow:hidden;
        display:grid;
        box-shadow:0 18px 46px rgba(15,23,42,.08);
    }
    .social-hero-shot__body {
        padding:22px 24px 24px;
        display:grid;
        gap:8px;
    }
    .social-hero-shot__body strong {
        font-size:24px;
        line-height:1.1;
        color:var(--dark);
    }
    .social-hero-shot__body p {
        margin:0;
        color:var(--gray);
        line-height:1.7;
    }
    .social-gallery-section {
        display:grid;
        gap:18px;
    }
    .social-gallery-section__header {
        display:grid;
        gap:8px;
        max-width:820px;
    }
    .social-gallery-section__header span {
        display:inline-flex;
        width:max-content;
        padding:8px 12px;
        border-radius:999px;
        background:#dbeafe;
        color:#1d4ed8;
        font-size:12px;
        font-weight:800;
        letter-spacing:.08em;
        text-transform:uppercase;
    }
    .social-shot-grid {
        display:grid;
        grid-template-columns:repeat(3, minmax(0, 1fr));
        gap:14px;
    }
    .shot-card {
        background:white;
        border:1px solid var(--border);
        border-radius:22px;
        overflow:hidden;
        box-shadow:0 16px 40px rgba(15,23,42,.05);
        display:grid;
    }
    .shot-card__body {
        padding:18px 18px 20px;
        display:grid;
        gap:8px;
    }
    .shot-card__eyebrow {
        display:inline-flex;
        width:max-content;
        font-size:11px;
        font-weight:800;
        letter-spacing:.12em;
        text-transform:uppercase;
        color:#1d4ed8;
    }
    .shot-card__body strong {
        font-size:20px;
        line-height:1.14;
        color:var(--dark);
    }
    .shot-card__body p {
        margin:0;
        color:var(--gray);
        line-height:1.7;
    }
    .shot-link {
        position:relative;
        display:block;
        background:#f8fafc;
    }
    .shot-link img {
        display:block;
        width:100%;
        height:auto;
        transition:transform .22s ease;
    }
    .shot-link::after {
        content:'Open screenshot';
        position:absolute;
        right:14px;
        bottom:14px;
        padding:8px 10px;
        border-radius:999px;
        background:rgba(15,23,42,.82);
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
    .shot-link:hover img,
    .shot-link:focus-visible img {
        transform:scale(1.02);
    }
    .shot-link:hover::after,
    .shot-link:focus-visible::after {
        opacity:1;
        transform:translateY(0);
    }
    .social-actions {
        display:flex;
        flex-wrap:wrap;
        gap:12px;
        margin-top:18px;
    }
    .social-engine {
        display:grid;
        gap:18px;
        background:
            radial-gradient(circle at top right, rgba(37,99,235,.08), transparent 34%),
            linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }
    .social-engine__header {
        display:grid;
        gap:10px;
        max-width:780px;
    }
    .social-engine__grid,
    .social-context-grid {
        display:grid;
        grid-template-columns:repeat(3, minmax(0, 1fr));
        gap:16px;
    }
    .social-engine__card {
        background:white;
        border:1px solid var(--border);
        border-radius:20px;
        padding:22px 20px;
        display:grid;
        gap:12px;
        box-shadow:0 14px 32px rgba(15,23,42,.05);
    }
    .social-engine__card span,
    .social-context-panel__eyebrow {
        display:inline-flex;
        width:max-content;
        font-size:11px;
        font-weight:800;
        letter-spacing:.12em;
        text-transform:uppercase;
        color:#1d4ed8;
    }
    .social-engine__card h3 {
        margin:0;
        font-size:21px;
        line-height:1.12;
        color:var(--dark);
    }
    .social-engine__rules,
    .social-engine__goals,
    .social-engine__ranks,
    .social-context-panel ul {
        list-style:none;
        margin:0;
        padding:0;
        display:grid;
        gap:10px;
    }
    .social-engine__rules li,
    .social-engine__goals li,
    .social-engine__ranks li,
    .social-context-panel li {
        border-top:1px solid #e2e8f0;
        padding-top:10px;
    }
    .social-engine__rules li:first-child,
    .social-engine__goals li:first-child,
    .social-engine__ranks li:first-child,
    .social-context-panel li:first-child {
        border-top:0;
        padding-top:0;
    }
    .social-engine__rule-row,
    .social-engine__rank-row {
        display:grid;
        grid-template-columns:auto 1fr;
        gap:12px;
        align-items:start;
    }
    .social-engine__points {
        min-width:72px;
        font-size:14px;
        font-weight:800;
        color:#0f172a;
    }
    .social-engine__rule-copy strong,
    .social-engine__rank-copy strong,
    .social-context-panel li strong {
        display:block;
        color:var(--dark);
        margin-bottom:4px;
    }
    .social-engine__rule-copy p,
    .social-engine__rank-copy p,
    .social-context-panel li p {
        margin:0;
        font-size:14px;
        line-height:1.6;
        color:var(--gray);
    }
    .social-engine__placement {
        min-width:54px;
        min-height:54px;
        padding:8px 10px;
        border-radius:16px;
        background:#0f172a;
        color:white;
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:14px;
        font-weight:800;
        letter-spacing:.02em;
        text-align:center;
    }
    .social-engine__note {
        padding:16px 18px;
        border-radius:18px;
        background:#eff6ff;
        border:1px solid #bfdbfe;
        color:#1e3a5f;
        font-size:14px;
        line-height:1.7;
    }
    .social-context {
        display:grid;
        gap:16px;
    }
    .social-context__header {
        display:grid;
        gap:10px;
        max-width:760px;
    }
    .social-context-panel {
        display:grid;
        gap:12px;
    }
    .social-context-panel h2 {
        font-size:26px;
        margin:0;
    }
    @media (max-width: 960px) {
        .social-shell,
        .social-proof-band,
        .social-band,
        .social-engine__grid,
        .social-context-grid {
            grid-template-columns:1fr;
        }
        .social-shot-grid {
            grid-template-columns:repeat(2, minmax(0, 1fr));
        }
        .social-band {
            align-items:flex-start;
        }
    }
    @media (max-width: 720px) {
        .social-shot-grid {
            grid-template-columns:1fr;
        }
        .social-panel,
        .social-proof-copy,
        .social-gallery-section,
        .social-stage,
        .social-engine,
        .social-context-panel,
        .social-band {
            padding:24px 20px;
        }
        .lightbox {
            padding:16px;
        }
        .lightbox__close {
            top:10px;
            right:10px;
        }
    }
</style>

<div class="social-wrap">
    <div class="social-shell">
        <section class="social-panel">
            <span class="social-kicker"><i class="fa-brands fa-meta" aria-hidden="true"></i> Social / Meta manager</span>
            <h2>Sell the growth lane with the same confidence as the casework lane.</h2>
            <p>CreditSoft ties Meta identity, a current Graph v25.0 connector, content planning, publishing, website handoff, and office follow-up into one local-first system. That makes the story easier to trust, easier to explain, and easier to sell.</p>
            <ul class="social-list">
                <li><strong>Meta Graph v25.0 connector</strong>Keep Page list, Page engagement, OAuth callbacks, and lead handoff aligned with the current Meta connector flow.</li>
                <li><strong>Meta lead routing</strong>Show how Facebook and Instagram leads hand off into branded site flows and office follow-up instead of turning into CSV cleanup.</li>
                <li><strong>Calendar and publishing control</strong>Put planning, approval, posting, and campaign defaults inside the same system that owns the rest of the office workflow.</li>
                <li><strong>Website and WhatsApp handoff</strong>Keep landing pages, intake flow, and faster follow-up tied back to the same branded stack.</li>
                <li><strong>Marketing-safe boundaries</strong>Keep the growth lane close to the business without exposing private casework on the public side.</li>
            </ul>
            <div class="social-actions">
                <a href="/features" class="btn btn-outline">Back to features</a>
                <a href="/pricing" class="btn btn-primary">See pricing</a>
            </div>
        </section>

        <section class="social-board">
            <span class="social-board__eyebrow"><i class="fa-brands fa-meta" aria-hidden="true"></i> From social to signed client</span>
            <h2>From ad click to office follow-up, the handoff stays visible.</h2>
            <p>Offices do not just need ads. They need the path after the click to feel branded, trackable, and connected to the same people who actually answer, qualify, and close the lead.</p>
            <div class="social-stage-grid">
                <div class="social-stage">
                    <span>Meta side</span>
                    <strong>Graph v25.0 Pages, publishing, lead forms, and brand identity</strong>
                    <p>Keep the Facebook, Instagram, and Threads side of the brand visible as part of the same product story.</p>
                </div>
                <div class="social-stage">
                    <span>Website side</span>
                    <strong>Branded intake and offer pages</strong>
                    <p>Send traffic into the CreditSoft-managed website lane instead of forcing the office to duct-tape a separate marketing stack.</p>
                </div>
                <div class="social-stage">
                    <span>Office side</span>
                    <strong>Calendar, WhatsApp, and real follow-up</strong>
                    <p>Qualified leads and response steps route back to the same office workflow where the company actually works the customer.</p>
                </div>
            </div>
        </section>
    </div>

    <section class="social-proof-band">
        <div class="social-proof-copy">
            <span class="social-kicker"><i class="fa-solid fa-images" aria-hidden="true"></i> Actual screens</span>
            <h2>Real product screens that make the lane easier to sell.</h2>
            <p>These screenshots show the Social / Meta workspace inside CreditSoft: Graph v25.0 Meta connection, content calendar, publishing controls, lead capture, and WhatsApp follow-up in one operating lane.</p>
            <ul class="social-list">
                <li><strong>This is product proof</strong> Buyers can see the Social / Meta lane as a working part of CreditSoft, not a mockup or roadmap promise.</li>
                <li><strong>The connector is current</strong> The public story now matches the working Meta connector lane using Graph v25.0 for Page connection and lead handoff.</li>
                <li><strong>Calendar, publishing, and follow-up stay connected</strong> Content planning, booked consults, and response timing all live in one workspace.</li>
                <li><strong>Meta, websites, and the office line up</strong> The same stack can shape the click, the landing page, and the handoff back to the office.</li>
            </ul>
            <div class="social-proof-disclaimer">
                <strong>Screenshot note</strong>
                <p>These screenshots were captured on April 14, 2026. The lane is still evolving, but the product shown here is already real, working, and ready to sell.</p>
            </div>
        </div>

        <article class="social-hero-shot">
            <a class="shot-link" href="<?= htmlspecialchars((string) $featured_shot['image'], ENT_QUOTES, 'UTF-8') ?>" data-lightbox-caption="<?= htmlspecialchars((string) $featured_shot['caption'], ENT_QUOTES, 'UTF-8') ?>" aria-label="Open the Social / Meta overview screenshot full size">
                <img src="<?= htmlspecialchars((string) $featured_shot['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) $featured_shot['alt'], ENT_QUOTES, 'UTF-8') ?>" loading="eager">
            </a>
            <div class="social-hero-shot__body">
                <span class="shot-card__eyebrow"><?= htmlspecialchars((string) $featured_shot['eyebrow'], ENT_QUOTES, 'UTF-8') ?></span>
                <strong><?= htmlspecialchars((string) $featured_shot['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                <p><?= htmlspecialchars((string) $featured_shot['copy'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </article>
    </section>

    <section class="social-gallery-section">
        <div class="social-gallery-section__header">
            <span>Gallery</span>
            <h2>Open the screens and see how the lane actually works.</h2>
            <p>Click any screenshot to inspect the calendar, publishing, ads, and follow-up details full size. The point is to make the Social / Meta lane feel tangible, not abstract.</p>
        </div>

        <div class="social-shot-grid">
            <?php foreach ($gallery_shots as $shot): ?>
                <article class="shot-card">
                    <a class="shot-link" href="<?= htmlspecialchars((string) $shot['image'], ENT_QUOTES, 'UTF-8') ?>" data-lightbox-caption="<?= htmlspecialchars((string) $shot['caption'], ENT_QUOTES, 'UTF-8') ?>" aria-label="Open <?= htmlspecialchars((string) $shot['title'], ENT_QUOTES, 'UTF-8') ?> full size">
                        <img src="<?= htmlspecialchars((string) $shot['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) $shot['alt'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                    </a>
                    <div class="shot-card__body">
                        <span class="shot-card__eyebrow"><?= htmlspecialchars((string) $shot['eyebrow'], ENT_QUOTES, 'UTF-8') ?></span>
                        <strong><?= htmlspecialchars((string) $shot['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <p><?= htmlspecialchars((string) $shot['copy'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="social-engine">
        <div class="social-engine__header">
            <span class="social-kicker"><i class="fa-solid fa-trophy" aria-hidden="true"></i> Creator challenge engine</span>
            <h2>Run weekly creator pushes with a scoring model the office can actually defend.</h2>
            <p>CreditSoft can track visible engagement signals, coach the office toward the page&rsquo;s weekly creator goals, and pick clear winners without pretending it can see private activity that Facebook does not expose.</p>
        </div>

        <div class="social-engine__grid">
            <article class="social-engine__card">
                <span>Scoring model</span>
                <h3>Use your own office algorithm for contest points.</h3>
                <ul class="social-engine__rules">
                    <?php foreach ($creator_score_rules as $rule): ?>
                        <li>
                            <div class="social-engine__rule-row">
                                <div class="social-engine__points"><?= htmlspecialchars((string) $rule['points'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="social-engine__rule-copy">
                                    <strong><?= htmlspecialchars((string) $rule['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <p><?= htmlspecialchars((string) $rule['copy'], ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>

            <article class="social-engine__card">
                <span>Weekly challenge mode</span>
                <h3>Help the page reach the current creator goals, not just post blindly.</h3>
                <ul class="social-engine__goals">
                    <?php foreach ($creator_goals as $goal): ?>
                        <li><?= htmlspecialchars((string) $goal, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </article>

            <article class="social-engine__card">
                <span>Winner logic</span>
                <h3>Pick first, second, third, and the rest of the leaderboard cleanly.</h3>
                <ul class="social-engine__ranks">
                    <?php foreach ($creator_rankings as $ranking): ?>
                        <li>
                            <div class="social-engine__rank-row">
                                <div class="social-engine__placement"><?= htmlspecialchars((string) $ranking['placement'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="social-engine__rank-copy">
                                    <strong><?= htmlspecialchars((string) $ranking['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <p><?= htmlspecialchars((string) $ranking['copy'], ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>
        </div>

        <div class="social-engine__note">
            <strong>Policy-safe by design.</strong> This model stays on signals the office can legitimately verify: page comments, public shares, visible comment likes, publishing cadence, and challenge completion. It avoids private-share guessing, hidden friend-graph claims, or any promise that depends on data Meta does not expose.
        </div>
    </section>

    <section class="social-context">
        <div class="social-context__header">
            <span class="social-kicker">Why it changes the story</span>
            <h2>The social lane starts to look like a measurable growth workspace, not a side feature.</h2>
            <p>Once the page can show publishing, lead routing, weekly challenges, and winner selection in one place, CreditSoft reads like a broader operating stack instead of a tool that stops at back-office casework.</p>
        </div>

        <div class="social-context-grid">
            <article class="social-context-panel">
                <span class="social-context-panel__eyebrow">What this helps you sell</span>
                <h2>A bigger product story than “credit repair software.”</h2>
                <ul>
                    <li>
                        <strong>Meta is not an afterthought</strong>
                        <p>It belongs in the product map right beside the portal, websites, and office controls.</p>
                    </li>
                    <li>
                        <strong>Growth stays operational</strong>
                        <p>The same system capturing the lead can stay close to the same system that follows up and converts it.</p>
                    </li>
                    <li>
                        <strong>The stack looks more complete</strong>
                        <p>Marketing, website, calendar, and casework start to read like one real operating platform.</p>
                    </li>
                </ul>
            </article>

            <article class="social-context-panel">
                <span class="social-context-panel__eyebrow">What keeps it credible</span>
                <h2>Growth stays close to the office without exposing private work.</h2>
                <ul>
                    <li>
                        <strong>Private work stays private</strong>
                        <p>Case notes, disputes, review decisions, and back-office actions stay off the public side.</p>
                    </li>
                    <li>
                        <strong>Marketing still points inward</strong>
                        <p>Website and Meta flows should hand off to the office instead of becoming another dead-end inbox.</p>
                    </li>
                    <li>
                        <strong>Branded packaging gets stronger</strong>
                        <p>Managed websites and Meta-ready growth tools become easier to sell together as one package.</p>
                    </li>
                </ul>
            </article>

            <article class="social-context-panel">
                <span class="social-context-panel__eyebrow">What gets measured</span>
                <h2>Comments, shares, follow-up, and winner tiers all become reportable.</h2>
                <ul>
                    <li>
                        <strong>Every point has a reason</strong>
                        <p>The office can explain why comments, public shares, and liked replies are weighted the way they are.</p>
                    </li>
                    <li>
                        <strong>Challenge gaps stay visible</strong>
                        <p>If the page is behind on publishing cadence or reply volume, the team sees it before the week ends.</p>
                    </li>
                    <li>
                        <strong>Placements stay configurable</strong>
                        <p>One winner, top three, or a top-ten bracket can all be defined by the office without changing the product story.</p>
                    </li>
                </ul>
            </article>
        </div>
    </section>

    <section class="social-band">
        <div>
            <h2>Social / Meta belongs in the product story, not in fine print.</h2>
            <p>CreditSoft gives offices a visible Social / Meta workspace with a current Graph v25.0 connector, calendar planning, publishing controls, ads, website handoff, and WhatsApp follow-up. It should read like a first-class product lane because that is exactly what it is.</p>
        </div>
        <a href="/websites" class="btn btn-primary">See managed websites</a>
    </section>
</div>

<div class="lightbox" id="socialLightbox" aria-hidden="true">
    <div class="lightbox__dialog" role="dialog" aria-modal="true" aria-label="Social screenshot preview">
        <button type="button" class="lightbox__close" id="socialLightboxClose" aria-label="Close screenshot preview">&times;</button>
        <div class="lightbox__image-wrap">
            <img class="lightbox__image" id="socialLightboxImage" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="">
        </div>
        <div class="lightbox__caption" id="socialLightboxCaption"></div>
    </div>
</div>

<script>
(() => {
    const placeholderImage = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
    const lightbox = document.getElementById('socialLightbox');
    const image = document.getElementById('socialLightboxImage');
    const caption = document.getElementById('socialLightboxCaption');
    const closeButton = document.getElementById('socialLightboxClose');
    const links = document.querySelectorAll('.shot-link');

    if (!lightbox || !image || !caption || !closeButton || !links.length) {
        return;
    }

    const closeLightbox = () => {
        lightbox.classList.remove('is-open');
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('lightbox-open');
        image.src = placeholderImage;
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
