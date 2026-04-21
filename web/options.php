<?php
$page_title = 'Office Options Roadmap';
$page_description = 'See which communications, fulfillment, payment, and office options CreditSoft already supports, which ones are actively being built, and which ones are still planned.';
$page_hero = true;
$hero_title = 'Office options should be part of the operating model, not just a pile of upsells.';
$hero_subtitle = 'This page marks what CreditSoft already supports, what is actively being built, and what is still on the communications and fulfillment roadmap. We would rather be plain about the status than act like every switch is already turned on.';
require __DIR__ . '/header.php';
?>
<style>
    .options-wrap { max-width: 1140px; margin: 0 auto; padding: 44px 20px 0; display:grid; gap:28px; }
    .options-hero { display:grid; grid-template-columns: 1.04fr .96fr; gap:24px; align-items:start; }
    .options-panel { background:white; border:1px solid var(--border); border-radius:24px; padding:30px 28px; box-shadow:0 16px 40px rgba(15,23,42,.05); }
    .options-panel h2 { font-size:30px; line-height:1.08; margin-bottom:10px; }
    .options-panel p { color:var(--gray); margin-bottom:16px; }
    .options-panel.visual { padding:18px; overflow:hidden; background:linear-gradient(180deg,#f8fbff 0%,#eff6ff 100%); }
    .options-panel.visual img { width:100%; height:auto; display:block; border-radius:18px; }
    .options-kicker { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; margin-bottom:14px; background:#e0f2fe; color:#075985; }
    .options-list { list-style:none; margin:0; padding:0; display:grid; gap:12px; }
    .options-list li { padding:14px 16px; border:1px solid var(--border); border-radius:16px; background:#f8fafc; color:var(--dark); }
    .options-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; }
    .options-column { background:white; border:1px solid var(--border); border-radius:22px; padding:24px; box-shadow:0 10px 35px rgba(15,23,42,.05); display:grid; gap:14px; }
    .options-column h3 { font-size:22px; margin-bottom:0; }
    .options-column p { color:var(--gray); margin:0; }
    .options-column ul { list-style:none; margin:0; padding:0; display:grid; gap:10px; }
    .options-column li { padding:14px 14px 14px 16px; border-radius:16px; border:1px solid var(--border); color:#334155; }
    .options-column li strong { display:block; color:var(--dark); margin-bottom:4px; }
    .options-column.now li { background:#f0fdf4; border-color:#bbf7d0; }
    .options-column.progress li { background:#eff6ff; border-color:#bfdbfe; }
    .options-column.next li { background:#fff7ed; border-color:#fed7aa; }
    .status-pill { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; background:#f8fafc; border:1px solid var(--border); color:#334155; }
    .status-pill.now { background:#dcfce7; border-color:#86efac; color:#166534; }
    .status-pill.progress { background:#dbeafe; border-color:#93c5fd; color:#1d4ed8; }
    .status-pill.next { background:#fff7ed; border-color:#fdba74; color:#c2410c; }
    .options-triple { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; }
    .options-card { background:white; border:1px solid var(--border); border-radius:20px; padding:24px; box-shadow:0 10px 30px rgba(15,23,42,.04); }
    .options-card h3 { font-size:20px; margin-bottom:8px; }
    .options-card p { color:var(--gray); margin:0; }
    .options-strip { background:#fff7ed; border:1px solid #fed7aa; border-radius:22px; padding:26px 24px; }
    .options-strip h2 { font-size:28px; margin-bottom:10px; }
    .options-strip p { color:#7c2d12; max-width:920px; }
    .options-band { background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 55%,#2563eb 100%); color:white; border-radius:24px; padding:28px; box-shadow:0 18px 40px rgba(15,23,42,.16); display:grid; gap:16px; grid-template-columns:minmax(0,1fr) auto; align-items:center; }
    .options-band h2 { font-size:30px; margin-bottom:8px; }
    .options-band p { color:rgba(255,255,255,.82); max-width:760px; }
    .options-band .btn { background:white; color:var(--dark); }
    .options-band .btn:hover { background:#eff6ff; }
    @media (max-width: 960px) {
        .options-hero,
        .options-grid,
        .options-triple,
        .options-band {
            grid-template-columns: 1fr;
        }
        .options-band { align-items:flex-start; }
    }
</style>

<div class="options-wrap">
    <div class="options-hero">
        <div class="options-panel">
            <span class="options-kicker">Options roadmap</span>
            <h2>Communications, fulfillment, and office extras should feel connected to the workflow.</h2>
            <p>Some software turns every office convenience into a separate little toll booth. CreditSoft should handle these lanes more like part of the operating system: billing, communications, updates, and fulfillment should connect back to the client record and the office workflow instead of floating around as random add-ons.</p>
            <ul class="options-list">
                <li>Payment methods and gateway notes should live where billing actually happens.</li>
                <li>Voice and message lanes should tie back to the office record, not just a detached provider login.</li>
                <li>Print, mail, and fax should eventually feel like workflow actions, not a second software stack.</li>
                <li>Budget controls and permissions should keep offices from getting surprised by usage creep.</li>
            </ul>
        </div>
        <div class="options-panel visual">
            <img src="/assets/images/graphics/options-communications-roadmap.svg" alt="CreditSoft office options and communications roadmap illustration">
        </div>
    </div>

    <div class="options-grid">
        <section class="options-column now">
            <span class="status-pill now">Available now</span>
            <h3>What already exists</h3>
            <p>The parts of the options lane that are already real in CreditSoft today.</p>
            <ul>
                <li><strong>Billing and revenue workspace</strong>Recurring billing profiles, payment history, gateway notes, and manual payment entries already live inside the office app.</li>
                <li><strong>Manual and offline payment tracking</strong>Zelle, Cash App, Apple Pay, Google Pay, and office-managed payment notes can already be represented in the billing workflow.</li>
                <li><strong>Gateway-aware setup notes</strong>Authorize.net, PaymentCloud, NMI, USAePay, GOAT Payments, Valor PayTech, and related office stacks can already be documented in the billing lane.</li>
            </ul>
        </section>

        <section class="options-column progress">
            <span class="status-pill progress">In progress</span>
            <h3>What is being tightened up</h3>
            <p>The parts that are actively being turned into cleaner product lanes instead of one-off notes.</p>
            <ul>
                <li><strong>Dedicated update and renewal lane</strong>License recovery, renewal instructions, QR payment flow, and update status are being consolidated into a cleaner update path.</li>
                <li><strong>Office billing setup polish</strong>Provider suggestions, workflow wording, and gateway setup guidance are still being refined around the real kinds of merchants this business can actually use.</li>
                <li><strong>Page-aware support reporting</strong>Bug and feature reporting is being tied more directly to the page where the office actually hit the issue.</li>
            </ul>
        </section>

        <section class="options-column next">
            <span class="status-pill next">Planned</span>
            <h3>What is on deck</h3>
            <p>These are planned options lanes, not fake-finished checkboxes.</p>
            <ul>
                <li><strong>FreeSWITCH API voice lane</strong>Planned calling, masking, call logging, and office routing will be built around your FreeSWITCH API rather than pretending every office wants the same Twilio-shaped lane.</li>
                <li><strong>SMS and office message routing</strong>Client updates and staff-triggered message workflows are planned, but they stay marked planned until the real transport and audit path are ready.</li>
                <li><strong>Print, mail, and fax workflow</strong>Letter delivery options belong on the roadmap too, but they should land as real office actions tied back to the client record.</li>
                <li><strong>Usage budgets and permissions</strong>Office-level controls for communications, fulfillment, and spend are planned so the team can control who can trigger paid actions.</li>
            </ul>
        </section>
    </div>

    <div class="options-triple">
        <div class="options-card">
            <h3>Why we are not copying the add-on treadmill</h3>
            <p>We are not trying to recreate a menu where every practical office function becomes its own permanent surcharge. The better pattern is to wire the important lanes into the office workflow first, then decide what deserves usage pricing later.</p>
        </div>
        <div class="options-card">
            <h3>Where FreeSWITCH fits</h3>
            <p>Your `FreeSWITCH API` gives us a cleaner future voice lane than pretending the office has to live inside the same canned telephony path everyone else resells. That should let us shape calling around the actual office workflow.</p>
        </div>
        <div class="options-card">
            <h3>What should stay visible to the office</h3>
            <p>Every option lane should show status, cost exposure, and client context clearly enough that a staff member knows what happened, why it happened, and where it is logged.</p>
        </div>
    </div>

    <div class="options-strip">
        <h2>We would rather mark these as roadmap than act like everything is already wired.</h2>
        <p>That is especially true for communications. Voice, SMS, print, mail, and fax can become expensive and messy fast if they are bolted on carelessly. CreditSoft should add those lanes when they are tied back to the office record, the support flow, the billing trail, and the permissions model in a way that actually makes sense.</p>
    </div>

    <section class="options-band">
        <div>
            <h2>Options should make the office easier to run, not harder to audit.</h2>
            <p>CreditSoft is moving these lanes toward a single office model: billing, updates, communications, and fulfillment should all point back to the client, the user action, and the office trail.</p>
        </div>
        <a href="/roadmap" class="btn btn-primary">See the full roadmap</a>
    </section>
</div>

<?php require __DIR__ . '/footer.php'; ?>
