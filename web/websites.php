<?php
$page_title = 'Client Site Gallery';
$page_description = 'Examples of branded client websites and managed public front ends tied into CreditSoft CRM, intake, and portal workflows.';
$page_hero = true;
$hero_title = 'Client Site Gallery';
$hero_subtitle = 'Live examples, active rebuild lanes, and the kind of branded public front ends we pair with CreditSoft.';
include 'header.php';
?>

<style>
    .gallery-page {
        display: grid;
        gap: 28px;
    }
    .gallery-intro,
    .gallery-card,
    .gallery-offer {
        background: white;
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 28px;
        box-shadow: 0 10px 35px rgba(15, 23, 42, 0.06);
    }
    .gallery-intro h2,
    .gallery-offer h2 {
        font-size: 30px;
        margin-bottom: 10px;
    }
    .gallery-intro p,
    .gallery-offer p,
    .gallery-card p {
        color: var(--gray);
    }
    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 24px;
    }
    .gallery-card {
        display: grid;
        gap: 18px;
    }
    .gallery-card__top {
        display: flex;
        justify-content: space-between;
        align-items: start;
        gap: 16px;
    }
    .gallery-card__kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 7px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        background: rgba(37, 99, 235, 0.1);
        color: var(--primary);
    }
    .gallery-card__domain {
        font-size: 15px;
        font-weight: 700;
        color: var(--dark);
    }
    .gallery-browser__body {
        position: relative;
        min-height: 470px;
        padding: 24px;
        border: 1px solid var(--border);
        border-radius: 20px;
        background:
            radial-gradient(circle at top right, rgba(37, 99, 235, 0.10), transparent 28%),
            linear-gradient(180deg, #f8fbff 0%, #eef4fb 100%);
        overflow: hidden;
    }
    .gallery-scene {
        position: relative;
        min-height: 420px;
    }
    .gallery-device {
        position: absolute;
        overflow: hidden;
        background: #0f172a;
        box-shadow: 0 18px 45px rgba(15, 23, 42, 0.22);
    }
    .gallery-device__screen {
        display: block;
        width: 100%;
        height: auto;
        object-fit: cover;
        object-position: top center;
        background: #fff;
    }
    .gallery-device--laptop {
        left: 0;
        top: 0;
        width: min(100%, 640px);
        border-radius: 18px 18px 12px 12px;
        padding: 14px 14px 0;
    }
    .gallery-device--laptop::after {
        content: '';
        display: block;
        width: calc(100% + 44px);
        height: 16px;
        margin-left: -22px;
        background: linear-gradient(180deg, #cbd5e1 0%, #94a3b8 100%);
        border-radius: 0 0 20px 20px;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.4);
    }
    .gallery-device--laptop .gallery-device__screen {
        aspect-ratio: 1440 / 980;
        border-radius: 8px 8px 0 0;
    }
    .gallery-device--tablet {
        right: 68px;
        top: 108px;
        width: 250px;
        padding: 16px 12px;
        border-radius: 24px;
        background: #111827;
    }
    .gallery-device--tablet .gallery-device__screen {
        aspect-ratio: 834 / 1194;
        border-radius: 14px;
    }
    .gallery-device--phone {
        right: 0;
        top: 72px;
        width: 160px;
        padding: 12px 8px 16px;
        border-radius: 28px;
        background: #020617;
    }
    .gallery-device--phone::before {
        content: '';
        position: absolute;
        top: 8px;
        left: 50%;
        transform: translateX(-50%);
        width: 72px;
        height: 18px;
        border-radius: 0 0 14px 14px;
        background: #020617;
        z-index: 2;
    }
    .gallery-device--phone .gallery-device__screen {
        aspect-ratio: 1170 / 1992;
        border-radius: 18px;
    }
    .gallery-caption {
        padding: 20px 22px 22px;
        display: grid;
        gap: 14px;
        background:
            radial-gradient(circle at top right, rgba(37, 99, 235, 0.08), transparent 35%),
            linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    }
    .gallery-caption__title {
        font-size: 28px;
        font-weight: 800;
        line-height: 1.05;
        color: var(--dark);
    }
    .gallery-caption__copy {
        color: var(--gray);
        max-width: 42ch;
    }
    .gallery-caption__chips {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .gallery-chip {
        padding: 7px 10px;
        border-radius: 999px;
        background: #dbeafe;
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 700;
    }
    .gallery-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        gap: 10px;
    }
    .gallery-list li {
        display: flex;
        gap: 10px;
        color: #334155;
    }
    .gallery-list li::before {
        content: '•';
        color: var(--success);
        font-weight: 700;
    }
    .gallery-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }
    .gallery-offer {
        background:
            radial-gradient(circle at top right, rgba(16, 185, 129, 0.08), transparent 30%),
            linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    }
    .gallery-offer__meta {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
        margin-top: 22px;
    }
    .gallery-offer__meta-item {
        padding: 18px;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: rgba(255,255,255,0.82);
    }
    .gallery-offer__meta-item strong {
        display: block;
        font-size: 14px;
        margin-bottom: 6px;
    }
    @media (max-width: 900px) {
        .gallery-grid,
        .gallery-offer__meta {
            grid-template-columns: 1fr;
        }
        .gallery-browser__body {
            min-height: auto;
        }
        .gallery-scene {
            min-height: 0;
            display: grid;
            gap: 18px;
        }
        .gallery-device {
            position: relative;
            top: auto;
            right: auto;
            left: auto;
            width: 100%;
        }
    }
</style>

<main class="page-content">
    <div class="gallery-page">
        <section class="gallery-intro">
            <div class="gallery-card__kicker">Managed Websites</div>
            <h2>Branded client sites tied into the CRM</h2>
            <p>These are the kinds of public front ends we pair with CreditSoft: lead intake, branded trust pages, portal call-to-actions, and a cleaner path back into the local CRM and intranet. Some are already live, and some are in active rebuild lanes.</p>
            <div class="gallery-actions" style="margin-top:18px;">
                <a href="/clients" class="btn btn-outline">Open Client Preview Directory</a>
            </div>
        </section>

        <section class="gallery-grid">
            <article class="gallery-card">
                <div class="gallery-card__top">
                    <div>
                        <div class="gallery-card__kicker">Package preview</div>
                        <h3>Credit Sense Credit Repair</h3>
                        <div class="gallery-card__domain">Reusable client stack preview</div>
                    </div>
                </div>
                <div class="gallery-browser">
                    <div class="gallery-browser__body">
                        <div class="gallery-scene">
                            <div class="gallery-device gallery-device--laptop">
                                <img class="gallery-device__screen" src="/assets/images/sites/credit-sense-home.png" alt="Credit Sense package preview desktop screenshot" loading="lazy" />
                            </div>
                            <div class="gallery-device gallery-device--tablet">
                                <img class="gallery-device__screen" src="/assets/images/sites/credit-sense-tablet.png" alt="Credit Sense package preview tablet screenshot" loading="lazy" />
                            </div>
                            <div class="gallery-device gallery-device--phone">
                                <img class="gallery-device__screen" src="/assets/images/sites/credit-sense-mobile.png" alt="Credit Sense package preview iPhone style screenshot" loading="lazy" />
                            </div>
                        </div>
                    </div>
                    <div class="gallery-caption">
                        <div>
                            <div class="gallery-caption__title">Credit coaching, repair, and portal access in one branded lane.</div>
                            <p class="gallery-caption__copy">A richer package preview showing the kind of public site we can reskin for client installs with education, consultation, portal entry, admin, social calendar, and AI-backed content tooling behind it.</p>
                        </div>
                        <div class="gallery-caption__chips">
                            <span class="gallery-chip">Portal</span>
                            <span class="gallery-chip">AI</span>
                            <span class="gallery-chip">Social Calendar</span>
                            <span class="gallery-chip">Lead Intake</span>
                        </div>
                    </div>
                </div>
                <ul class="gallery-list">
                    <li>Shows the richer site/backend stack we can reskin for client installs.</li>
                    <li>Supports branded content, client portal entry, and admin-side management tools.</li>
                    <li>Useful reference for offices wanting more than a brochure site.</li>
                </ul>
                <div class="gallery-actions">
                    <a href="mailto:hello@creditsoft.app?subject=Managed%20CreditSoft%20Website%20Quote" class="btn btn-primary">Request a Similar Build</a>
                    <a href="/pricing#managed-websites" class="btn btn-outline">See Website Packaging</a>
                </div>
            </article>

            <article class="gallery-card">
                <div class="gallery-card__top">
                    <div>
                        <div class="gallery-card__kicker">Live customer preview</div>
                        <h3>I Got Bad Credit</h3>
                        <div class="gallery-card__domain">creditsoft.app/clients/igotbadcredit</div>
                    </div>
                </div>
                <div class="gallery-browser">
                    <div class="gallery-browser__body">
                        <div class="gallery-scene">
                            <div class="gallery-device gallery-device--laptop">
                                <img class="gallery-device__screen" src="/assets/images/sites/igotbadcredit-home.png" alt="I Got Bad Credit website desktop screenshot" loading="lazy" />
                            </div>
                            <div class="gallery-device gallery-device--tablet">
                                <img class="gallery-device__screen" src="/assets/images/sites/igotbadcredit-tablet.png" alt="I Got Bad Credit website tablet screenshot" loading="lazy" />
                            </div>
                            <div class="gallery-device gallery-device--phone">
                                <img class="gallery-device__screen" src="/assets/images/sites/igotbadcredit-mobile.png" alt="I Got Bad Credit website iPhone style screenshot" loading="lazy" />
                            </div>
                        </div>
                    </div>
                    <div class="gallery-caption">
                        <div>
                            <div class="gallery-caption__title">A live rebuild preview published so the customer can review the new direction.</div>
                            <p class="gallery-caption__copy">This is a stronger Greenwood / I Got Bad Credit preview showing the cleaner homepage, portal, consultation, and education lanes before final cutover off the legacy site.</p>
                        </div>
                        <div class="gallery-caption__chips">
                            <span class="gallery-chip">Live Preview</span>
                            <span class="gallery-chip">Portal</span>
                            <span class="gallery-chip">Quiz</span>
                            <span class="gallery-chip">CRM Handoff</span>
                        </div>
                    </div>
                </div>
                <ul class="gallery-list">
                    <li>Shows the rebuilt client experience instead of only the before-state.</li>
                    <li>Lets the customer review the new homepage, portal, and education lanes on a live URL.</li>
                    <li>Built to tie back into portal routing, intake, and CRM handoff once approved.</li>
                </ul>
                <div class="gallery-actions">
                    <a href="/clients/igotbadcredit/" class="btn btn-primary">Open Customer Preview</a>
                    <a href="https://www.igotbadcredit.net" target="_blank" rel="noreferrer" class="btn btn-outline">Visit Current Site</a>
                </div>
            </article>
        </section>

        <section class="gallery-offer">
            <div class="gallery-card__kicker">Your site here</div>
            <h2>We can build the next one in your brand</h2>
            <p>The managed website lane is meant for offices that want the public side cleaned up too: stronger copy, better mobile forms, quiz or intake flows, portal direction, and a direct handoff into the CreditSoft CRM and intranet.</p>

            <div class="gallery-offer__meta">
                <div class="gallery-offer__meta-item">
                    <strong>Branded public front end</strong>
                    <span>Home, services, quizzes, contact lanes, and portal CTAs aligned to your office.</span>
                </div>
                <div class="gallery-offer__meta-item">
                    <strong>CRM and intranet handoff</strong>
                    <span>Lead capture, portal traffic, and intake tied back into the CreditSoft workflow instead of scattered tools.</span>
                </div>
                <div class="gallery-offer__meta-item">
                    <strong>Launch-ready packaging</strong>
                    <span>SEO/share setup, branded copy direction, and a cleaner path to paid rollout once the office is ready.</span>
                </div>
            </div>

            <div class="gallery-actions" style="margin-top:22px;">
                <a href="mailto:hello@creditsoft.app?subject=Managed%20CreditSoft%20Website%20Quote" class="btn btn-primary">Request Website Quote</a>
                <a href="/pricing" class="btn btn-outline">Back to Pricing</a>
            </div>
        </section>
    </div>
</main>

<?php include 'footer.php'; ?>
