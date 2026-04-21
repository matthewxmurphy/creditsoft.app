<?php
$page_title = 'Built-In Automation';
$page_description = 'CreditSoft includes native automation, browser companion workflows, API lanes, and office operations that do not force you into a separate Zapier bill just to keep work moving.';
$page_hero = true;
$hero_title = 'Automation without the Zapier tax.';
$hero_subtitle = 'Yes, you can connect extra tools if you want. The point is that the core office workflow should already be there before anyone asks you to buy a second automation product.';
require __DIR__ . '/header.php';
?>
<style>
    .auto-wrap { max-width: 1120px; margin: 0 auto; padding: 44px 20px 0; display:grid; gap:28px; }
    .auto-shell { display:grid; grid-template-columns:1.08fr .92fr; gap:24px; }
    .auto-card { background:white; border:1px solid var(--border); border-radius:22px; padding:30px 28px; box-shadow:0 16px 40px rgba(15,23,42,.05); }
    .auto-card h2 { font-size:30px; margin-bottom:10px; }
    .auto-card p { color:var(--gray); margin-bottom:16px; }
    .auto-card.visual { padding:18px; overflow:hidden; }
    .auto-card.visual img { width:100%; height:auto; display:block; border-radius:18px; }
    .auto-pill { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; margin-bottom:14px; background:#fef3c7; color:#92400e; }
    .auto-list { list-style:none; margin:0; padding:0; display:grid; gap:12px; }
    .auto-list li { padding:14px 16px; border:1px solid var(--border); border-radius:16px; background:#f8fafc; color:var(--dark); }
    .mock { background:#0f172a; color:white; border-radius:22px; padding:26px 24px; }
    .mock h3 { font-size:22px; margin-bottom:10px; }
    .mock .url { display:block; padding:12px 14px; border-radius:14px; background:rgba(255,255,255,.08); color:#fde68a; font-size:13px; word-break:break-all; margin-bottom:16px; }
    .mock p { color:rgba(255,255,255,.82); margin:0 0 14px; }
    .compare { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; }
    .compare-card { background:white; border:1px solid var(--border); border-radius:20px; padding:24px; }
    .compare-card h3 { font-size:22px; margin-bottom:10px; }
    .compare-card ul { margin:0; padding-left:18px; color:var(--gray); display:grid; gap:10px; }
    .flow-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:18px; }
    .flow-card { background:white; border:1px solid var(--border); border-radius:20px; padding:24px; }
    .flow-card h3 { font-size:19px; margin-bottom:8px; }
    .flow-card p { color:var(--gray); }
    .cta { background:linear-gradient(135deg,#eff6ff,#ffffff); border:1px solid #bfdbfe; border-radius:22px; padding:28px; display:flex; justify-content:space-between; gap:18px; align-items:center; }
    .cta h2 { font-size:28px; margin-bottom:8px; }
    .cta p { color:var(--gray); max-width:720px; }
    @media(max-width: 900px) {
        .auto-shell, .compare, .flow-grid, .cta { grid-template-columns:1fr; }
        .cta { align-items:flex-start; }
    }
</style>

<div class="auto-wrap">
    <div class="auto-shell">
        <div class="auto-card">
            <span class="auto-pill">Built-in automation</span>
            <h2>The product should already know how a credit repair office works.</h2>
            <p>That is the whole point. Intake, portal actions, browser companion workflows, client update triggers, report handling, and office notifications should not require another subscription before they feel usable.</p>
            <ul class="auto-list">
                <li>Trigger workflows from the product itself, not from a maze of third-party zaps.</li>
                <li>Use the browser companion on supported licenses without pretending the product ends at a webhook.</li>
                <li>Keep client, report, and office states connected so the team is not chasing stale information.</li>
                <li>Still connect outside systems if you want to, but only because it helps, not because the basics are missing.</li>
            </ul>
        </div>
        <div class="auto-card visual">
            <img src="/built-in-automation.svg" alt="CreditSoft built-in automation workflow illustration">
        </div>
    </div>

    <div class="mock">
        <h3>Yes, we can see the wild automation pitch.</h3>
        <span class="url">app.creditrepaircloud.com/webapi?...and then a giant tracking string hanging off the end of it</span>
        <p>That kind of page is exactly what makes the point. Other credit repair apps turn automation into another sell, another account, and another setup lane.</p>
        <p>CreditSoft’s argument is simpler: why should a credit repair office pay extra for Zapier just to make the core product behave like a real operating system?</p>
        </div>

    <div class="compare">
        <div class="compare-card">
            <h3>The extra-bill version</h3>
            <ul>
                <li>Need automation? Buy another service.</li>
                <li>Need intake handoff? Build a Zap.</li>
                <li>Need status sync? Add another external lane.</li>
                <li>Need browser workflows? Hope the integration story catches up.</li>
            </ul>
        </div>
        <div class="compare-card">
            <h3>The CreditSoft version</h3>
            <ul>
                <li>Use the product’s own API and workflow lanes where it makes sense.</li>
                <li>Keep client, office, and report actions closer to the actual source of truth.</li>
                <li>Let the browser companion talk to the licensed office directly.</li>
                <li>Only bring in outside connectors when there is a real reason, not as a tax on basic operations.</li>
            </ul>
        </div>
    </div>

    <div class="flow-grid">
        <div class="flow-card">
            <h3>Lead enters</h3>
            <p>Branded website or portal intake routes where it belongs instead of disappearing into a generic automation bucket.</p>
        </div>
        <div class="flow-card">
            <h3>Office works</h3>
            <p>The local intranet remains the working lane for the real case data and actions.</p>
        </div>
        <div class="flow-card">
            <h3>Browser companion helps</h3>
            <p>On supported licenses, browser automation can split workload across machines without losing server-side control.</p>
        </div>
        <div class="flow-card">
            <h3>Clients get updates</h3>
            <p>Portal and client-facing flows can react from the same product family instead of needing a second orchestration bill.</p>
        </div>
    </div>

    <div class="cta">
        <div>
            <h2>Yes, you can still use Zapier. The better question is why you would need to for the basics.</h2>
            <p>That is the pitch. CreditSoft should come with more of the useful automation already inside the product lane.</p>
        </div>
        <a href="/pricing" class="btn btn-primary">See plans with automation</a>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
