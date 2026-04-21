<?php
$page_title = 'Run a Credit Repair Business';
$page_description = 'See how CreditSoft supports a real credit repair office with local-first operations, client portals, branded websites, compliance workflows, and fewer disconnected tools.';
$page_hero = true;
$hero_title = 'Run a credit repair business without building your office out of workarounds.';
$hero_subtitle = 'CreditSoft is for shops that want a real operating lane: local-first casework, branded client-facing pages, tighter CRM flows, and less glue code holding the whole thing together.';
require __DIR__ . '/header.php';
?>
<style>
    .guide-wrap { max-width: 1120px; margin: 0 auto; padding: 44px 20px 0; display: grid; gap: 28px; }
    .hero-grid { display:grid; grid-template-columns:1.05fr .95fr; gap:24px; align-items:start; }
    .panel { background:white; border:1px solid var(--border); border-radius:22px; padding:30px 28px; box-shadow:0 16px 40px rgba(15,23,42,.05); }
    .panel h2 { font-size:30px; margin-bottom:10px; }
    .panel p { color:var(--gray); margin-bottom:16px; }
    .panel.visual { padding:18px; overflow:hidden; }
    .panel.visual img { width:100%; height:auto; display:block; border-radius:18px; }
    .pill { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; margin-bottom:14px; background:#dcfce7; color:#166534; }
    .office-list { list-style:none; margin:0; padding:0; display:grid; gap:12px; }
    .office-list li { padding:14px 16px; border:1px solid var(--border); border-radius:16px; background:#f8fafc; color:var(--dark); }
    .compare { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; }
    .compare-card { border-radius:20px; padding:24px; }
    .compare-card.bad { background:#fff1f2; border:1px solid #fecdd3; }
    .compare-card.good { background:#ecfeff; border:1px solid #a5f3fc; }
    .compare-card h3 { font-size:22px; margin-bottom:10px; }
    .compare-card ul { margin:0; padding-left:18px; color:var(--gray); display:grid; gap:10px; }
    .feature-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; }
    .feature-card { background:white; border:1px solid var(--border); border-radius:20px; padding:24px; }
    .feature-card h3 { font-size:20px; margin-bottom:8px; }
    .feature-card p { color:var(--gray); }
    .truth-strip { background:#fff7ed; border:1px solid #fed7aa; border-radius:22px; padding:26px 24px; }
    .truth-strip h2 { font-size:28px; margin-bottom:10px; }
    .truth-strip p { color:#7c2d12; max-width:900px; }
    .cta { background:linear-gradient(135deg,#eff6ff,#ffffff); border:1px solid #bfdbfe; border-radius:22px; padding:28px; display:flex; justify-content:space-between; gap:18px; align-items:center; }
    .cta h2 { font-size:28px; margin-bottom:8px; }
    .cta p { color:var(--gray); max-width:720px; }
    @media(max-width: 900px) {
        .hero-grid, .compare, .feature-grid, .cta { grid-template-columns:1fr; }
        .cta { align-items:flex-start; }
    }
</style>

<div class="guide-wrap">
    <div class="hero-grid">
        <div class="panel">
            <span class="pill">Office lane</span>
            <h2>Run the business from one operating system instead of five stitched-together subscriptions.</h2>
            <p>That means the casework stays local, the portal and public site stay branded, and the office does not have to keep paying extra just to make the basic workflow talk to itself.</p>
            <ul class="office-list">
                <li>Local-first intranet for the actual sensitive casework and report review.</li>
                <li>Client portal lane for progress, updates, and access without exposing the whole office.</li>
                <li>Branded website packaging tied into intake, consults, and CRM flows.</li>
                <li>Metro2-first workflow instead of generic “AI letters” pretending to be strategy.</li>
            </ul>
        </div>
        <div class="panel visual">
            <img src="/run-credit-repair-business.svg" alt="CreditSoft office operations illustration">
        </div>
    </div>

    <div class="compare">
        <div class="compare-card bad">
            <h3>What breaks other setups</h3>
            <ul>
                <li>Cloud-first casework with sensitive data living on someone else’s stack.</li>
                <li>Zapier and external integrations just to move a lead into a usable workflow.</li>
                <li>Portal, CRM, and letters all feeling like different products glued together.</li>
                <li>Paying extra for the basic automation an office owner assumed was included.</li>
            </ul>
        </div>
        <div class="compare-card good">
            <h3>What CreditSoft is trying to fix</h3>
            <ul>
                <li>Local control where the real casework happens.</li>
                <li>Public-facing site and portal layers that can still look branded and polished.</li>
                <li>Browser companion and API lanes when the license supports them.</li>
                <li>A more opinionated office workflow so you can run the shop instead of babysitting tools.</li>
            </ul>
        </div>
    </div>

    <div class="feature-grid">
        <div class="feature-card">
            <h3>Compliance and reporting</h3>
            <p>Use the 50-state legal lane, Metro2 review, letters, and briefs without pretending the work is just generic template churn.</p>
        </div>
        <div class="feature-card">
            <h3>Client-facing trust</h3>
            <p>Pair the intranet with a managed website and portal so your office looks established instead of patched together.</p>
        </div>
        <div class="feature-card">
            <h3>Operations that can grow</h3>
            <p>As the team grows, the point is to make the office more repeatable, not more dependent on extra add-ons and monthly glue.</p>
        </div>
    </div>

    <div class="truth-strip">
        <h2>We are not going to lie and pretend starting a real office costs almost nothing.</h2>
        <p>A real credit repair operation usually means software, compliant workflows, a proper business setup, time, hardware that is not falling apart, and eventually website, intake, and client-management costs. CreditSoft can help keep that stack tighter, but it is not honest to market a serious office like it starts for pocket change.</p>
    </div>

    <div class="cta">
        <div>
            <h2>If you are serious about running the business, the software should feel like office infrastructure.</h2>
            <p>Not a marketing funnel with a login screen attached. CreditSoft is strongest for teams that want that difference to matter.</p>
        </div>
        <a href="/pricing" class="btn btn-primary">Compare plans</a>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
