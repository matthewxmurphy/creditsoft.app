<?php
$page_title = 'Scale Your Credit Repair Business';
$page_description = 'See how CreditSoft helps growing credit repair offices tighten operations, reduce disconnected tools, and keep client workflows cleaner as volume increases.';
$page_hero = true;
$hero_title = 'Scale your credit repair business without scaling the chaos.';
$hero_subtitle = 'Growth should mean more repeatable operations, better client visibility, and stronger office control. It should not mean three more subscriptions and a bigger support mess.';
require __DIR__ . '/header.php';
?>
<style>
    .scale-wrap { max-width: 1120px; margin: 0 auto; padding: 44px 20px 0; display:grid; gap:28px; }
    .scale-shell { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
    .scale-card { background:white; border:1px solid var(--border); border-radius:22px; padding:30px 28px; box-shadow:0 16px 40px rgba(15,23,42,.05); }
    .scale-card h2 { font-size:30px; margin-bottom:10px; }
    .scale-card p { color:var(--gray); margin-bottom:16px; }
    .scale-card.visual { padding:18px; overflow:hidden; }
    .scale-card.visual img { width:100%; height:auto; display:block; border-radius:18px; }
    .scale-tag { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; margin-bottom:14px; background:#ede9fe; color:#5b21b6; }
    .scale-list { list-style:none; margin:0; padding:0; display:grid; gap:12px; }
    .scale-list li { padding:14px 16px; border:1px solid var(--border); border-radius:16px; background:#f8fafc; color:var(--dark); }
    .ops-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:18px; }
    .ops-card { background:white; border:1px solid var(--border); border-radius:20px; padding:24px; }
    .ops-card h3 { font-size:19px; margin-bottom:8px; }
    .ops-card p { color:var(--gray); }
    .strip { background:#0f172a; color:white; border-radius:22px; padding:30px 28px; display:grid; grid-template-columns:1.2fr .8fr; gap:22px; align-items:center; }
    .strip h2 { font-size:30px; margin-bottom:10px; }
    .strip p { color:rgba(255,255,255,.82); }
    .strip ul { margin:0; padding-left:20px; display:grid; gap:10px; color:rgba(255,255,255,.88); }
    .cta { background:linear-gradient(135deg,#eff6ff,#ffffff); border:1px solid #bfdbfe; border-radius:22px; padding:28px; display:flex; justify-content:space-between; gap:18px; align-items:center; }
    .cta h2 { font-size:28px; margin-bottom:8px; }
    .cta p { color:var(--gray); max-width:720px; }
    @media(max-width: 900px) {
        .scale-shell, .ops-grid, .strip, .cta { grid-template-columns:1fr; }
        .cta { align-items:flex-start; }
    }
</style>

<div class="scale-wrap">
    <div class="scale-shell">
        <div class="scale-card">
            <span class="scale-tag">Scale</span>
            <h2>When the office grows, the weak spots get exposed fast.</h2>
            <p>That usually shows up as delayed follow-up, disconnected client updates, too much manual intake, scattered monitoring workflows, and nobody being sure which tool owns what.</p>
            <ul class="scale-list">
                <li>More leads means intake has to be tighter, not just faster.</li>
                <li>More clients means the portal and update flow matter more.</li>
                <li>More staff means role clarity and cleaner ops start to matter a lot.</li>
                <li>More report volume means the browser companion and API lanes can actually save real office time.</li>
            </ul>
        </div>
        <div class="scale-card visual">
            <img src="/scale-credit-repair-business.svg" alt="CreditSoft growth and operations illustration">
        </div>
    </div>

    <div class="ops-grid">
        <div class="ops-card">
            <h3>Lead handling</h3>
            <p>Use branded site flows, consultation forms, and CRM entry points that do not feel detached from the office.</p>
        </div>
        <div class="ops-card">
            <h3>Case review</h3>
            <p>Keep the real report comparison, Metro2 review, and dispute workflow in one lane instead of splitting it across generic tools.</p>
        </div>
        <div class="ops-card">
            <h3>Client updates</h3>
            <p>Clients should be able to see progress without your team having to manually re-explain everything every time.</p>
        </div>
        <div class="ops-card">
            <h3>Automation</h3>
            <p>If scaling immediately forces Zapier, third-party glue, and fragile webhooks, the stack was not built with office reality in mind.</p>
        </div>
    </div>

    <div class="strip">
        <div>
            <h2>Scaling is where “already built in” starts to matter a lot more.</h2>
            <p>When the office is small, people can brute force around bad tooling. When the office grows, that becomes expensive. CreditSoft’s advantage is not that you can never connect to outside tools. It is that you do not have to connect to outside tools just to make the basics work.</p>
        </div>
        <ul>
            <li>Client portal lane</li>
            <li>Managed website option</li>
            <li>Browser companion on supported licenses</li>
            <li>API and automation lanes that belong to the product</li>
        </ul>
    </div>

    <div class="cta">
        <div>
            <h2>Build for the office you are becoming, not just the one you can barely hold together right now.</h2>
            <p>That is the whole point of this lane. Better systems before more growth turns into more admin pain.</p>
        </div>
        <a href="/built-in-automation" class="btn btn-primary">See automation lane</a>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
