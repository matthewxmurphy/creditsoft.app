<?php
$page_title = 'Start Repairing Credit';
$page_description = 'Learn the CreditSoft path for fixing your own credit, understanding report errors, and building confidence before you ever help someone else.';
$page_hero = true;
$hero_title = 'Start repairing credit with a system that makes sense.';
$hero_subtitle = 'Fix your own file first, learn the workflow, and build confidence before you decide whether this stays personal or grows into something bigger.';
require __DIR__ . '/header.php';
?>
<style>
    .guide-wrap { max-width: 1120px; margin: 0 auto; padding: 44px 20px 0; display: grid; gap: 28px; }
    .guide-intro { max-width: 860px; color: var(--gray); font-size: 18px; }
    .guide-rail { display:grid; grid-template-columns:1.15fr .85fr; gap:24px; align-items:start; }
    .guide-card { background:white; border:1px solid var(--border); border-radius:22px; padding:30px 28px; box-shadow:0 16px 40px rgba(15,23,42,.05); }
    .guide-card h2 { font-size:30px; margin-bottom:10px; }
    .guide-card p { color:var(--gray); margin-bottom:16px; }
    .guide-kicker { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; margin-bottom:14px; background:#dbeafe; color:#1d4ed8; }
    .guide-list { list-style:none; margin:0; padding:0; display:grid; gap:12px; }
    .guide-list li { padding:14px 16px; border:1px solid var(--border); border-radius:16px; background:#f8fafc; color:var(--dark); }
    .guide-side { display:grid; gap:18px; }
    .guide-visual { background:linear-gradient(180deg,#ffffff,#f8fafc); border:1px solid var(--border); border-radius:22px; padding:18px; box-shadow:0 16px 40px rgba(15,23,42,.05); }
    .guide-visual img { width:100%; height:auto; display:block; border-radius:18px; }
    .guide-note { background:#0f172a; color:white; border-radius:22px; padding:26px 24px; }
    .guide-note h3 { font-size:20px; margin-bottom:8px; }
    .guide-note p { color:rgba(255,255,255,.82); margin:0 0 12px; }
    .guide-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; }
    .guide-step { background:white; border:1px solid var(--border); border-radius:20px; padding:24px; }
    .guide-step .num { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:var(--primary); color:white; font-weight:800; margin-bottom:14px; }
    .guide-step h3 { font-size:20px; margin-bottom:8px; }
    .guide-step p { color:var(--gray); }
    .guide-cta { background:linear-gradient(135deg,#eff6ff,#ffffff); border:1px solid #bfdbfe; border-radius:22px; padding:28px; display:flex; justify-content:space-between; gap:18px; align-items:center; }
    .guide-cta h2 { font-size:28px; margin-bottom:8px; }
    .guide-cta p { color:var(--gray); max-width:720px; }
    .guide-links { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; }
    .guide-links a { display:block; background:white; border:1px solid var(--border); border-radius:18px; padding:22px; text-decoration:none; color:inherit; }
    .guide-links a:hover { text-decoration:none; border-color:#93c5fd; box-shadow:0 14px 28px rgba(37,99,235,.08); }
    .guide-links strong { display:block; margin-bottom:6px; font-size:18px; }
    .guide-links span { color:var(--gray); }
    @media(max-width: 900px) {
        .guide-rail, .guide-grid, .guide-links, .guide-cta { grid-template-columns:1fr; }
        .guide-cta { align-items:flex-start; }
    }
</style>

<div class="guide-wrap">
    <p class="guide-intro">A lot of people do not need another guru funnel. They need a clean way to understand what is wrong on a report, what can actually be challenged, and how to move without guessing. CreditSoft is strongest when it helps you learn the work while doing the work.</p>

    <div class="guide-rail">
        <div class="guide-card">
            <span class="guide-kicker">Start here</span>
            <h2>Fix your own file before you turn this into a business pitch.</h2>
            <p>That is the best way to understand the process. You learn what a real report looks like, which errors matter, what supporting detail is missing, and how long real progress actually takes.</p>
            <ul class="guide-list">
                <li>Import a report and review what is actually reporting.</li>
                <li>Spot Metro2 mismatches, stale negatives, weird dates, and unsupported lines.</li>
                <li>Generate the letters and briefs without bouncing between six tools.</li>
                <li>Track the response cycle and understand what changed, what did not, and why.</li>
            </ul>
        </div>
        <div class="guide-side">
            <div class="guide-visual">
                <img src="/start-repairing-credit.svg" alt="CreditSoft report and dispute workflow illustration">
            </div>
            <div class="guide-note">
                <h3>Why this page exists</h3>
                <p>Other platforms push people into a giant “start your business” funnel before they even understand their own report.</p>
                <p>CreditSoft can support that path later, but the better first move is learning the work on your own credit file or a practice case that is easy to understand.</p>
            </div>
            <div class="guide-note">
                <h3>What you do not need yet</h3>
                <p>No Zapier account. No agency stack. No maze of add-ons just to understand why a late payment, collection, or identity mismatch is still sitting there.</p>
            </div>
        </div>
    </div>

    <div class="guide-grid">
        <div class="guide-step">
            <div class="num">1</div>
            <h3>Read the report like data</h3>
            <p>Look at the actual tradeline fields, reporting dates, balances, and identity details instead of relying on vague score advice.</p>
        </div>
        <div class="guide-step">
            <div class="num">2</div>
            <h3>Challenge what is real</h3>
            <p>Dispute what is unsupported, contradictory, unverifiable, or clearly wrong. Not every bad mark is a strong argument. That matters.</p>
        </div>
        <div class="guide-step">
            <div class="num">3</div>
            <h3>Track the cycle and learn</h3>
            <p>When the new report comes back, compare it against the last one and see what changed so you actually build pattern recognition.</p>
        </div>
    </div>

    <div class="guide-cta">
        <div>
            <h2>When it stops feeling confusing, that is when the software starts paying for itself.</h2>
            <p>If you end up helping family, friends, or clients later, you are not starting from zero. You already understand the reporting lane and the office workflow.</p>
        </div>
        <a href="/pricing" class="btn btn-primary">See pricing</a>
    </div>

    <div class="guide-links">
        <a href="/run-a-credit-repair-business">
            <strong>Run a credit repair business</strong>
            <span>What CreditSoft looks like when you are operating for clients, not just yourself.</span>
        </a>
        <a href="/scale-your-credit-repair-business">
            <strong>Scale your credit repair business</strong>
            <span>How to tighten operations when the office is growing and disconnected tools start hurting.</span>
        </a>
        <a href="/built-in-automation">
            <strong>Built-in automation</strong>
            <span>Why you should not need a second automation bill just to keep the office moving.</span>
        </a>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
