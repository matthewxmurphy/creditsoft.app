<?php
$page_title = 'Features Roadmap';
$page_description = 'See what CreditSoft already includes, what is actively being hardened, and what is still on the public roadmap.';
$page_hero = true;
$hero_title = 'What CreditSoft already has, what is improving next, and what is still on deck.';
$hero_subtitle = 'A public features page should not pretend every rough edge is already finished. This one marks what is ready, what is being hardened, and what is still roadmap.';
require __DIR__ . '/header.php';
?>
<style>
    .roadmap-wrap {
        max-width: 1160px;
        margin: 0 auto;
        padding: 44px 20px 0;
        display: grid;
        gap: 28px;
    }
    .roadmap-hero {
        display: grid;
        grid-template-columns: 1.05fr .95fr;
        gap: 24px;
        align-items: stretch;
    }
    .roadmap-panel {
        background: white;
        border: 1px solid var(--border);
        border-radius: 24px;
        padding: 30px 28px;
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.05);
    }
    .roadmap-panel h2 {
        font-size: 30px;
        line-height: 1.08;
        margin-bottom: 10px;
    }
    .roadmap-panel p {
        color: var(--gray);
        margin-bottom: 16px;
    }
    .roadmap-kicker {
        display: inline-flex;
        align-items: center;
        justify-self: start;
        gap: 7px;
        width: fit-content;
        max-width: 100%;
        padding: 7px 11px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        margin-bottom: 14px;
        background: #e7f0ff;
        border: 1px solid #bfdbfe;
        color: #1d4ed8;
        line-height: 1;
    }
    .roadmap-kicker::before {
        content: '';
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: currentColor;
        opacity: 0.72;
        flex: 0 0 auto;
    }
    .roadmap-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 12px;
    }
    .roadmap-list li {
        padding: 14px 16px;
        border: 1px solid var(--border);
        border-radius: 16px;
        background: #f8fafc;
        color: var(--dark);
    }
    .roadmap-list strong {
        display: block;
        margin-bottom: 4px;
        font-size: 15px;
    }
    .roadmap-note {
        margin-top: 16px;
        padding: 16px 18px;
        border-radius: 18px;
        background: #fff7ed;
        border: 1px solid #fed7aa;
        color: #7c2d12;
    }
    .roadmap-visual {
        padding: 18px;
        overflow: hidden;
        background:
            radial-gradient(circle at top right, rgba(37, 99, 235, 0.10), transparent 32%),
            linear-gradient(180deg, #f8fbff 0%, #eef4fb 100%);
    }
    .roadmap-visual img {
        width: 100%;
        height: auto;
        display: block;
        border-radius: 18px;
    }
    .legend-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .status-pill {
        display: inline-flex;
        align-items: center;
        justify-self: start;
        align-self: start;
        gap: 7px;
        width: fit-content;
        max-width: 100%;
        padding: 7px 11px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        background: #f8fafc;
        border: 1px solid var(--border);
        color: #334155;
        line-height: 1;
        box-shadow: 0 1px 0 rgba(255, 255, 255, 0.75) inset;
    }
    .status-pill::before {
        content: '';
        width: 7px;
        height: 7px;
        border-radius: 999px;
        background: currentColor;
        opacity: 0.75;
        flex: 0 0 auto;
    }
    .status-pill.now {
        background: #ecfdf3;
        border-color: #bbf7d0;
        color: #166534;
    }
    .status-pill.progress {
        background: #eef4ff;
        border-color: #bfdbfe;
        color: #1d4ed8;
    }
    .status-pill.next {
        background: #fff8ef;
        border-color: #fed7aa;
        color: #c2410c;
    }
    .roadmap-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
    }
    .support-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
    }
    .support-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 22px;
        padding: 24px;
        box-shadow: 0 10px 35px rgba(15, 23, 42, 0.05);
        display: grid;
        gap: 14px;
    }
    .support-card h3 {
        font-size: 22px;
        margin-bottom: 0;
    }
    .support-card p {
        color: var(--gray);
        margin-bottom: 0;
    }
    .support-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 10px;
    }
    .support-list li {
        padding: 14px 14px 14px 16px;
        border-radius: 16px;
        background: #f8fafc;
        border: 1px solid var(--border);
        color: #334155;
    }
    .support-list strong {
        display: block;
        color: var(--dark);
        margin-bottom: 4px;
    }
    .support-card.now .support-list li {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }
    .support-card.progress .support-list li {
        background: #eff6ff;
        border-color: #bfdbfe;
    }
    .support-card.next .support-list li {
        background: #fff7ed;
        border-color: #fed7aa;
    }
    .roadmap-column {
        background: white;
        border: 1px solid var(--border);
        border-radius: 22px;
        padding: 24px;
        box-shadow: 0 10px 35px rgba(15, 23, 42, 0.05);
        display: grid;
        gap: 14px;
    }
    .roadmap-column h3 {
        font-size: 22px;
        margin-bottom: 0;
    }
    .roadmap-column p {
        color: var(--gray);
    }
    .roadmap-column ul {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 10px;
    }
    .roadmap-column li {
        padding: 14px 14px 14px 16px;
        border-radius: 16px;
        background: #f8fafc;
        border: 1px solid var(--border);
        color: #334155;
    }
    .roadmap-column li strong {
        display: block;
        color: var(--dark);
        margin-bottom: 4px;
    }
    .roadmap-column.now li {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }
    .roadmap-column.progress li {
        background: #eff6ff;
        border-color: #bfdbfe;
    }
    .roadmap-column.next li {
        background: #fff7ed;
        border-color: #fed7aa;
    }
    .roadmap-band {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 55%, #2563eb 100%);
        color: white;
        border-radius: 24px;
        padding: 28px;
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.16);
        display: grid;
        gap: 16px;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
    }
    .roadmap-band h2 {
        font-size: 30px;
        margin-bottom: 8px;
    }
    .roadmap-band p {
        color: rgba(255, 255, 255, 0.82);
        max-width: 760px;
    }
    .roadmap-band .btn {
        background: white;
        color: var(--dark);
    }
    .roadmap-band .btn:hover {
        background: #eff6ff;
    }
    .roadmap-links {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }
    .roadmap-links a {
        display: block;
        background: white;
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 22px;
        text-decoration: none;
        color: inherit;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
    }
    .roadmap-links a:hover {
        text-decoration: none;
        border-color: #93c5fd;
        box-shadow: 0 16px 34px rgba(37, 99, 235, 0.08);
    }
    .roadmap-links strong {
        display: block;
        font-size: 18px;
        margin-bottom: 6px;
    }
    .roadmap-links span {
        color: var(--gray);
    }
    @media (max-width: 960px) {
        .roadmap-hero,
        .support-grid,
        .roadmap-grid,
        .roadmap-band,
        .roadmap-links {
            grid-template-columns: 1fr;
        }
        .roadmap-band {
            align-items: flex-start;
        }
    }
</style>

<div class="roadmap-wrap">
    <div class="roadmap-hero">
        <div class="roadmap-panel">
            <span class="roadmap-kicker">Public roadmap</span>
            <h2>We would rather mark the real status than quietly overpromise.</h2>
            <p>CreditSoft already has real client workspaces, report-review lanes, letters, portal layers, websites, billing, and office operations. The point of this page is to separate those shipped lanes from the pieces that are still tightening up.</p>
            <div class="legend-row">
                <span class="status-pill now">Available now</span>
                <span class="status-pill progress">In progress</span>
                <span class="status-pill next">Roadmap</span>
            </div>
            <div class="roadmap-note">
                <strong>Why this matters:</strong> offices need to know what they can use today, what is improving next, and what is still just a plan. That is better than a feature list that acts finished when it is not.
            </div>
        </div>
        <div class="roadmap-panel roadmap-visual">
            <img src="/assets/images/graphics/roadmap-path.svg" alt="CreditSoft roadmap illustration">
        </div>
    </div>

    <div class="support-grid">
        <section class="support-card now">
            <span class="status-pill now">Supported now</span>
            <h3>Provider lanes you can use today</h3>
            <p>The monitoring sources that already have real workflow support in the office today.</p>
            <ul class="support-list">
                <li><strong>SmartCredit</strong>Saved logins, browser companion queueing, archive imports, score history, and review lanes are already in the product.</li>
                <li><strong>Credit Karma</strong>Saved logins, companion navigation, and current import/review coverage are already part of the stack.</li>
                <li><strong>IdentityIQ sign-in and navigation</strong>Provider records, email/password plus saved security-answer handling, and companion routing into the dashboard, report, monitoring, and alerts pages are already wired.</li>
            </ul>
        </section>

        <section class="support-card progress">
            <span class="status-pill progress">In progress</span>
            <h3>Provider coverage being deepened now</h3>
            <p>The next layer is the deeper capture and review coverage, not pretending the entire provider lane is already finished.</p>
            <ul class="support-list">
                <li><strong>IdentityIQ deeper capture coverage</strong>Login and navigation are already there, while the deeper report parsing, capture mapping, and review coverage are still being tightened up.</li>
            </ul>
        </section>

        <section class="support-card next">
            <span class="status-pill next">Planned</span>
            <h3>Provider lanes still on deck</h3>
            <p>These are planned, but they stay marked planned until the import and review flows are genuinely ready.</p>
            <ul class="support-list">
                <li><strong>MyScoreIQ</strong>Queued as a future provider lane after IdentityIQ is stable.</li>
                <li><strong>Direct bureau and custom monitoring sources</strong>Possible later, but not something we are claiming as finished now.</li>
            </ul>
        </section>
    </div>

    <div class="roadmap-grid">
        <section class="roadmap-column now">
            <span class="status-pill now">Available now</span>
            <h3>What ships today</h3>
            <p>The pieces that already exist in the product and are usable now.</p>
            <ul>
                <li><strong>Local-first client workspaces</strong>Client dossiers, notes, letters, briefs, tasks, and office workflow already live inside the main app.</li>
                <li><strong>Report comparison and Metro2 review</strong>Imported reports, comparison lanes, and violation review are part of the product today.</li>
                <li><strong>IdentityIQ companion login lane</strong>Saved provider records, security-answer handling, and page-aware companion routing are already in the office.</li>
                <li><strong>DisputeFox roster migration</strong>The office can start from a DisputeFox-style client export instead of retyping the whole current roster by hand.</li>
                <li><strong>Branded websites and client portal</strong>Public pages, lead capture, and client-facing portal layers are already part of the CreditSoft stack.</li>
                <li><strong>Billing and revenue workspace</strong>Billing profiles, payment history, gateway notes, and revenue reporting are already in the office layer.</li>
            </ul>
        </section>

        <section class="roadmap-column progress">
            <span class="status-pill progress">In progress</span>
            <h3>What is being built</h3>
            <p>Items that are actively improving but should still be treated as evolving.</p>
            <ul>
                <li><strong>Browser companion hardening</strong>License-aware behavior, better bootstrapping, and safer companion workflows are still being tightened up.</li>
                <li><strong>IdentityIQ capture coverage</strong>The login lane is already in place, and the deeper report parsing and review mapping keeps getting tightened up from there.</li>
                <li><strong>Import mapper review</strong>Next import lanes should let AI suggest header matches, then let a human confirm them instead of forcing one-off manual remaps every time.</li>
                <li><strong>Communications and options lane</strong>Billing setup, update flow, and future communications lanes are being split into a cleaner office-options roadmap instead of scattered feature hints.</li>
                <li><strong>Update and renewal lane</strong>Version notices, license recovery, and renewal flows are moving into a cleaner dedicated update path.</li>
                <li><strong>Page-aware feedback and support</strong>Bug reporting is being tied more directly to the exact page where the issue happened.</li>
                <li><strong>Migration and intake polish</strong>Import, office transition, billing/gateway setup, and deeper DisputeFox migration lanes are being made cleaner and more explicit.</li>
            </ul>
        </section>

        <section class="roadmap-column next">
            <span class="status-pill next">Roadmap</span>
            <h3>What is next</h3>
            <p>Planned ideas that should make the product feel more complete and more office-aware.</p>
            <ul>
                <li><strong>Per-machine companion tokens</strong>Split browser companion work across multiple systems without duplicate processing.</li>
                <li><strong>Deeper report history migration</strong>Bring older score history, progress context, and imported result timelines over more cleanly.</li>
                <li><strong>Smarter office automation</strong>More status-driven triggers, report-received notifications, and team-aware update workflows.</li>
                <li><strong>Expanded support and training lanes</strong>More guided setup, migration help, and public education pages without the usual guru fluff.</li>
            </ul>
        </section>
    </div>

    <section class="roadmap-band">
        <div>
            <h2>Built for a real office, not just a polished demo.</h2>
            <p>CreditSoft should keep getting clearer, safer, and easier to operate. This roadmap is meant to show that direction without overclaiming it is all done already.</p>
        </div>
        <a href="/pricing" class="btn btn-primary">See pricing</a>
    </section>

    <div class="roadmap-links">
        <a href="/migration">
            <strong>Migration</strong>
            <span>See how we think about moving a real office over without pretending migrations are instant.</span>
        </a>
        <a href="/options">
            <strong>Options roadmap</strong>
            <span>See how billing, communications, FreeSWITCH-backed calling, and future fulfillment lanes are being staged honestly.</span>
        </a>
        <a href="/outsourcing">
            <strong>Outsourcing</strong>
            <span>See why better automation and tighter workflows should reduce the amount you need to hand off.</span>
        </a>
        <a href="/built-in-automation">
            <strong>Built-in automation</strong>
            <span>See why the office should not need a second automation bill just to keep routine work moving.</span>
        </a>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
