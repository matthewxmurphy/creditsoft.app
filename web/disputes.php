<?php
declare(strict_types=1);

$page_title = 'Disputes';
$page_description = 'CreditSoft dispute workflows with Metro2 review, letters, briefs, and client-safe progress tracking.';
$page_hero = true;
$hero_title = 'Disputes should feel like a workflow, not a template pile.';
$hero_subtitle = 'CreditSoft pairs report review, Metro2 signals, letters, and client progress so the office stops bouncing between disconnected dispute tools.';

require __DIR__ . '/header.php';
?>
<style>
    .detail-wrap { max-width: 1120px; margin: 0 auto; padding: 44px 20px 0; display: grid; gap: 24px; }
    .detail-grid { display:grid; grid-template-columns:1.05fr .95fr; gap:24px; }
    .detail-card { background:white; border:1px solid var(--border); border-radius:22px; padding:30px 28px; box-shadow:0 16px 40px rgba(15,23,42,.05); }
    .detail-card h2 { font-size:30px; margin-bottom:10px; }
    .detail-card p { color:var(--gray); margin-bottom:16px; }
    .detail-kicker { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; background:#dcfce7; color:#166534; font-size:12px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; margin-bottom:14px; }
    .detail-list { list-style:none; margin:0; padding:0; display:grid; gap:12px; }
    .detail-list li { padding:14px 16px; border:1px solid var(--border); border-radius:16px; background:#f8fafc; color:var(--dark); }
    .detail-strip { background:linear-gradient(135deg,#eff6ff,#ffffff); border:1px solid #bfdbfe; border-radius:22px; padding:26px 24px; }
    .detail-strip h3 { font-size:22px; margin-bottom:10px; }
    .detail-strip p { color:var(--gray); }
    .detail-links { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; }
    .detail-links a { display:block; background:white; border:1px solid var(--border); border-radius:18px; padding:22px; text-decoration:none; color:inherit; }
    .detail-links a:hover { text-decoration:none; border-color:#93c5fd; box-shadow:0 14px 28px rgba(37,99,235,.08); }
    .detail-links strong { display:block; font-size:18px; margin-bottom:6px; }
    .detail-links span { color:var(--gray); }
    @media(max-width: 900px) { .detail-grid, .detail-links { grid-template-columns:1fr; } }
</style>

<div class="detail-wrap">
    <div class="detail-grid">
        <div class="detail-card">
            <span class="detail-kicker">Dispute workflow</span>
            <h2>Move from report review to action without losing the context.</h2>
            <p>The point is not just generating another letter. The point is reviewing what is reporting, surfacing the right issue signals, and turning that into a cleaner next step for the office and the client.</p>
            <ul class="detail-list">
                <li>Report review and Metro2 signals stay tied to the same lane.</li>
                <li>Letters and briefs can be chosen from what the file actually supports.</li>
                <li>Client updates stop depending on somebody remembering what changed last cycle.</li>
            </ul>
        </div>
        <div class="detail-strip">
            <h3>Why this matters</h3>
            <p>Most software calls it automation, then shows the team every possible template and hopes somebody sorts it out. CreditSoft is trying to narrow the lane first so the operator confirms the next move instead of digging through a giant list.</p>
        </div>
    </div>

    <div class="detail-links">
        <a href="/metro2">
            <strong>Metro2 review</strong>
            <span>See the signal-detection side that helps the office spot mismatches, stale reporting, and stronger factual review candidates.</span>
        </a>
        <a href="/clients/2/letters">
            <strong>Letter workflow</strong>
            <span>Inside the office, letters can already be filtered and ranked by the file context instead of staying a giant undifferentiated library.</span>
        </a>
        <a href="/features">
            <strong>Feature overview</strong>
            <span>See where disputes sit beside portals, billing, websites, and the rest of the office stack.</span>
        </a>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
