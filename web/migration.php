<?php
$page_title = 'Migration';
$page_description = 'CreditSoft helps credit repair offices migrate from another platform in stages, preserving history where possible, keeping branding intact, and avoiding overnight chaos.';
$page_hero = true;
$hero_title = 'Migrate to CreditSoft without turning Monday into a fire drill.';
$hero_subtitle = 'We do not pretend migrations are instant. We help offices move in stages, protect the client record, rebuild the workflow cleanly, and keep the brand recognizable while the new lane comes online.';
require __DIR__ . '/header.php';
?>
<style>
    .migration-wrap { max-width: 1120px; margin: 0 auto; padding: 44px 20px 0; display:grid; gap:28px; }
    .migration-shell { display:grid; grid-template-columns:1.08fr .92fr; gap:24px; align-items:start; }
    .migration-card { background:white; border:1px solid var(--border); border-radius:22px; padding:30px 28px; box-shadow:0 16px 40px rgba(15,23,42,.05); }
    .migration-card h2 { font-size:30px; margin-bottom:10px; }
    .migration-card p { color:var(--gray); margin-bottom:16px; }
    .migration-tag { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; margin-bottom:14px; background:#e0f2fe; color:#075985; }
    .migration-list { list-style:none; margin:0; padding:0; display:grid; gap:12px; }
    .migration-list li { padding:14px 16px; border:1px solid var(--border); border-radius:16px; background:#f8fafc; color:var(--dark); }
    .migration-visual { background:linear-gradient(180deg,#ffffff,#f8fafc); border:1px solid var(--border); border-radius:22px; padding:18px; box-shadow:0 16px 40px rgba(15,23,42,.05); }
    .migration-visual img { width:100%; height:auto; display:block; border-radius:18px; }
    .migration-notes { display:grid; gap:18px; }
    .migration-note { background:#0f172a; color:white; border-radius:22px; padding:26px 24px; }
    .migration-note h3 { font-size:20px; margin-bottom:8px; }
    .migration-note p { color:rgba(255,255,255,.82); margin:0 0 12px; }
    .migration-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; }
    .migration-step { background:white; border:1px solid var(--border); border-radius:20px; padding:24px; }
    .migration-step .num { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:var(--primary); color:white; font-weight:800; margin-bottom:14px; }
    .migration-step h3 { font-size:20px; margin-bottom:8px; }
    .migration-step p { color:var(--gray); }
    .migration-triple { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; }
    .migration-triple .card { background:white; border:1px solid var(--border); border-radius:20px; padding:24px; }
    .migration-triple h3 { font-size:19px; margin-bottom:8px; }
    .migration-triple p { color:var(--gray); }
    .truth-strip { background:#fff7ed; border:1px solid #fed7aa; border-radius:22px; padding:26px 24px; }
    .truth-strip h2 { font-size:28px; margin-bottom:10px; }
    .truth-strip p { color:#7c2d12; max-width:920px; }
    .cta { background:linear-gradient(135deg,#eff6ff,#ffffff); border:1px solid #bfdbfe; border-radius:22px; padding:28px; display:flex; justify-content:space-between; gap:18px; align-items:center; }
    .cta h2 { font-size:28px; margin-bottom:8px; }
    .cta p { color:var(--gray); max-width:720px; }
    @media(max-width: 900px) {
        .migration-shell, .migration-grid, .migration-triple, .cta { grid-template-columns:1fr; }
        .cta { align-items:flex-start; }
    }
</style>

<div class="migration-wrap">
    <div class="migration-shell">
        <div class="migration-card">
            <span class="migration-tag">Migration lane</span>
            <h2>The safest move is usually not the fastest one.</h2>
            <p>Switching platforms is a project, not a button. The goal is to move the office without scrambling client communication, losing historical context, or forcing your team to relearn every workflow in the same afternoon.</p>
            <ul class="migration-list">
                <li>Move clients in phases so the office can keep working while the new lane is coming online.</li>
                <li>Start with the client export first so active dossiers, ownership, and progress survive the move before deeper history gets mapped.</li>
                <li>Preserve history where it can be brought over, and be honest about what needs to be reconstructed.</li>
                <li>Keep branding and client-facing touchpoints familiar so the transition still feels like your office.</li>
                <li>Rebuild workflows cleanly when the old stack was only working because people were babysitting it.</li>
            </ul>
        </div>
        <div class="migration-visual">
            <img src="/assets/images/graphics/migration-office-transition.svg" alt="CreditSoft office migration illustration">
        </div>
    </div>

    <div class="migration-notes">
        <div class="migration-note">
            <h3>What we are trying to avoid</h3>
            <p>We do not want your team logging into a new system on Friday night and discovering that everything important still lives in the old one.</p>
            <p>The better path is staged: confirm the data, line up the new office structure, and move with enough margin that the staff can breathe.</p>
        </div>
        <div class="migration-note">
            <h3>What stays recognizable</h3>
            <p>Your brand should still feel like your brand. That means client-facing presentation, office language, and the look of the transition should stay steady while the underlying workflow changes.</p>
        </div>
    </div>

    <div class="migration-triple">
        <div class="card">
            <h3>Clients</h3>
            <p>Bring over the active client list first. The current first-pass lane is aimed at DisputeFox-style client exports so the office can stop retyping basic dossiers by hand.</p>
        </div>
        <div class="card">
            <h3>History</h3>
            <p>Move notes, timelines, templates, and other useful context where possible, then document the gaps instead of pretending nothing was lost.</p>
        </div>
        <div class="card">
            <h3>Branding</h3>
            <p>Keep the public-facing side stable so clients see a transition, not a disappearance and reappearance under a different skin.</p>
        </div>
    </div>

    <div class="migration-grid">
        <div class="migration-step">
            <div class="num">1</div>
            <h3>Map the old stack</h3>
            <p>Figure out what data exists, where it lives, which workflows matter, and which parts of the old process were only surviving because someone was doing extra work.</p>
        </div>
        <div class="migration-step">
            <div class="num">2</div>
            <h3>Stage the move</h3>
            <p>Move the office in pieces so client work can continue while the new environment is being prepared and checked.</p>
        </div>
        <div class="migration-step">
            <div class="num">3</div>
            <h3>Rebuild with intent</h3>
            <p>Use the migration as a chance to clean up processes, not just copy the old chaos into a new box with a fresh logo.</p>
        </div>
    </div>

    <div class="truth-strip">
        <h2>Some things can be carried over. Some things should be rebuilt.</h2>
        <p>That is the honest version. A real migration is part transfer, part cleanup, and part decision-making. CreditSoft is meant to help the office land safely, keep the core identity intact, and come out with a workflow that makes more sense than the one it replaced.</p>
    </div>

    <div class="cta">
        <div>
            <h2>If your office is ready to move, we will help make the move look planned.</h2>
            <p>Not magical. Not overnight. Just staged, sane, and built around the reality that the business still has to run while the software changes.</p>
        </div>
        <a href="/pricing" class="btn btn-primary">See pricing</a>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
