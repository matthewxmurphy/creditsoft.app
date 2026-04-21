<?php
declare(strict_types=1);

$page_title = 'Compliance';
$page_description = 'CreditSoft compliance lanes for 50-state CRO rules, FCRA and FDCPA workflow support, and office-safe operating practices.';
$page_hero = true;
$hero_title = 'Compliance should be built into the workflow.';
$hero_subtitle = 'CreditSoft keeps the legal and operational lane closer to the work so teams stop guessing which rule set applies or where the office drift starts.';

require __DIR__ . '/header.php';
?>
<style>
    .detail-wrap { max-width: 1120px; margin: 0 auto; padding: 44px 20px 0; display: grid; gap: 24px; }
    .detail-grid { display:grid; grid-template-columns:1.1fr .9fr; gap:24px; }
    .detail-card { background:white; border:1px solid var(--border); border-radius:22px; padding:30px 28px; box-shadow:0 16px 40px rgba(15,23,42,.05); }
    .detail-card h2 { font-size:30px; margin-bottom:10px; }
    .detail-card p { color:var(--gray); margin-bottom:16px; }
    .detail-kicker { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; background:#eef6ff; color:#1d4ed8; font-size:12px; font-weight:800; letter-spacing:.1em; text-transform:uppercase; margin-bottom:14px; }
    .detail-list { list-style:none; margin:0; padding:0; display:grid; gap:12px; }
    .detail-list li { padding:14px 16px; border:1px solid var(--border); border-radius:16px; background:#f8fafc; color:var(--dark); }
    .detail-strip { background:#0f172a; color:white; border-radius:22px; padding:26px 24px; }
    .detail-strip h3 { font-size:22px; margin-bottom:10px; }
    .detail-strip p, .detail-strip li { color:rgba(255,255,255,.82); }
    .detail-strip ul { padding-left:20px; display:grid; gap:10px; }
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
            <span class="detail-kicker">Office-safe workflow</span>
            <h2>Stop treating compliance like a PDF nobody reads after onboarding.</h2>
            <p>CreditSoft keeps the compliance lane close to the casework: state CRO expectations, FCRA and FDCPA-aware workflow language, and the operational truth that a real office needs repeatable steps more than slogans.</p>
            <ul class="detail-list">
                <li>50-state CRO rules are part of the operating lane, not an afterthought.</li>
                <li>Letters and workflow prompts are shaped around factual review instead of generic template churn.</li>
                <li>Public-site pages can teach the right expectations before a lead ever becomes a client.</li>
            </ul>
        </div>
        <div class="detail-strip">
            <h3>What this means in practice</h3>
            <ul>
                <li>Less guessing about bond, registration, or disclosure expectations.</li>
                <li>Cleaner separation between educational content, factual dispute review, and legal advice.</li>
                <li>Fewer disconnected tools making the office harder to audit.</li>
            </ul>
        </div>
    </div>

    <div class="detail-links">
        <a href="/cro-rules">
            <strong>50-state CRO rules</strong>
            <span>Open the state rule lane and review the compliance requirements that most offices end up hunting for manually.</span>
        </a>
        <a href="/security">
            <strong>Security and privacy</strong>
            <span>See how the local-first setup supports privacy and why your client data does not need to live on somebody else’s SaaS stack.</span>
        </a>
        <a href="/features">
            <strong>Product overview</strong>
            <span>See where the compliance lane fits alongside Metro2 review, letters, portals, and branded websites.</span>
        </a>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
