<?php
declare(strict_types=1);

$page_title = 'Reporting';
$page_description = 'CreditSoft reporting lanes for report comparison, cycle tracking, client updates, and office visibility.';
$page_hero = true;
$hero_title = 'Reporting should show movement, not just dump another PDF.';
$hero_subtitle = 'CreditSoft treats reporting like a comparison lane: what changed, what stayed, what improved, and what the office should do next.';

require __DIR__ . '/header.php';
?>
<style>
    .detail-wrap { max-width: 1120px; margin: 0 auto; padding: 44px 20px 0; display: grid; gap: 24px; }
    .detail-shell { display:grid; grid-template-columns:1.1fr .9fr; gap:24px; }
    .detail-card { background:white; border:1px solid var(--border); border-radius:22px; padding:30px 28px; box-shadow:0 16px 40px rgba(15,23,42,.05); }
    .detail-card h2 { font-size:30px; margin-bottom:10px; }
    .detail-card p { color:var(--gray); margin-bottom:16px; }
    .detail-kicker { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; background:#ede9fe; color:#5b21b6; font-size:12px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; margin-bottom:14px; }
    .detail-list { list-style:none; margin:0; padding:0; display:grid; gap:12px; }
    .detail-list li { padding:14px 16px; border:1px solid var(--border); border-radius:16px; background:#f8fafc; color:var(--dark); }
    .detail-note { background:#0f172a; color:white; border-radius:22px; padding:26px 24px; }
    .detail-note h3 { font-size:22px; margin-bottom:10px; }
    .detail-note p { color:rgba(255,255,255,.82); }
    .detail-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; }
    .detail-box { background:white; border:1px solid var(--border); border-radius:18px; padding:22px; }
    .detail-box strong { display:block; font-size:18px; margin-bottom:6px; }
    .detail-box span { color:var(--gray); }
    @media(max-width: 900px) { .detail-shell, .detail-grid { grid-template-columns:1fr; } }
</style>

<div class="detail-wrap">
    <div class="detail-shell">
        <div class="detail-card">
            <span class="detail-kicker">Reporting lane</span>
            <h2>Show what changed, not just that another cycle happened.</h2>
            <p>CreditSoft’s reporting side is strongest when it compares the file over time: score shifts, negative-item movement, account changes, and the practical question of whether the office is actually making progress.</p>
            <ul class="detail-list">
                <li>Compare the newest report against the last cycle instead of treating each import like a fresh start.</li>
                <li>Surface score movement, removed negatives, and debt movement for real office brag-rights reporting.</li>
                <li>Give the client-facing portal enough progress detail without exposing the whole office view.</li>
            </ul>
        </div>
        <div class="detail-note">
            <h3>What clients actually care about</h3>
            <p>Did anything get removed? Did the score change? What is still open? What is next? That is a better reporting lane than making them interpret a raw bureau file on their own.</p>
        </div>
    </div>

    <div class="detail-grid">
        <div class="detail-box">
            <strong>Cycle comparison</strong>
            <span>Track what changed between imports and stop guessing which movement happened in which cycle.</span>
        </div>
        <div class="detail-box">
            <strong>Portal updates</strong>
            <span>Expose meaningful progress through the client portal without dumping internal office details into the public lane.</span>
        </div>
        <div class="detail-box">
            <strong>Office metrics</strong>
            <span>Feed debt removed, negative items removed, score lift, and client lifespan into the dashboard and public brag-rights API.</span>
        </div>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
