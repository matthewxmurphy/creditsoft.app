<?php
require_once __DIR__ . '/admin/bootstrap.php';

$page_title = 'Website Bridge + Partner API';
$page_description = 'CreditSoft connects branded websites, Meta callbacks, client portals, and lead forms to the local intranet through a stable public bridge without exposing private casework.';
$page_hero = true;
$hero_title = 'Website API Bridge';
$hero_subtitle = 'Give Meta, WordPress, client portals, and lead forms one stable HTTPS route into approved CreditSoft API endpoints while casework stays local.';
require __DIR__ . '/header.php';
?>
<style>
    .bridge-wrap { max-width: 1120px; margin: 0 auto; padding: 44px 20px 0; display: grid; gap: 28px; }
    .bridge-shell { display: grid; grid-template-columns: 1.02fr .98fr; gap: 24px; align-items: stretch; }
    .bridge-shell > *,
    .bridge-split > *,
    .bridge-panel,
    .bridge-proof,
    .bridge-note,
    .bridge-cta { min-width: 0; }
    .bridge-panel,
    .bridge-card,
    .bridge-step,
    .bridge-split > div {
        background: white;
        border: 1px solid var(--border);
        border-radius: 24px;
        box-shadow: 0 16px 40px rgba(15,23,42,.05);
    }
    .bridge-card { padding: 30px 28px; display: grid; gap: 16px; align-content: start; }
    .bridge-eyebrow {
        display: inline-flex;
        width: fit-content;
        align-items: center;
        gap: 8px;
        border-radius: 999px;
        background: #dbeafe;
        color: #1d4ed8;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .1em;
        text-transform: uppercase;
    }
    .bridge-card h2,
    .bridge-panel h2,
    .bridge-proof h2,
    .bridge-cta h2 { font-size: 30px; line-height: 1.08; margin: 0; }
    .bridge-card p,
    .bridge-panel p,
    .bridge-step p,
    .bridge-proof p,
    .bridge-split p,
    .bridge-cta p { color: var(--gray); margin: 0; }
    .bridge-list { list-style: none; padding: 0; margin: 2px 0 0; display: grid; gap: 12px; }
    .bridge-list li { display: flex; gap: 10px; align-items: flex-start; color: var(--dark); }
    .bridge-list li span { min-width: 0; }
    .bridge-list code { color: inherit; font: inherit; font-weight: 800; overflow-wrap: anywhere; word-break: break-word; }
    .bridge-list li::before {
        content: '';
        width: 8px;
        height: 8px;
        margin-top: 9px;
        border-radius: 999px;
        background: var(--success);
        flex: 0 0 auto;
    }
    .bridge-board { background: linear-gradient(135deg,#0f172a 0%, #132544 55%, #1d4ed8 100%); color: white; border-radius: 24px; padding: 24px; box-shadow: 0 18px 42px rgba(15,23,42,.16); display: grid; gap: 18px; }
    .bridge-board__eyebrow { display: inline-flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #bfdbfe; }
    .bridge-board h3 { font-size: 28px; line-height: 1.08; margin: 0; color: white; }
    .bridge-board p { margin: 0; color: rgba(255,255,255,.82); }
    .bridge-flow { display: grid; gap: 12px; }
    .bridge-flow__item { background: rgba(255,255,255,.08); border: 1px solid rgba(191,219,254,.18); border-radius: 18px; padding: 16px; display: grid; gap: 4px; }
    .bridge-flow__item span { color: #93c5fd; font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .bridge-flow__item strong { font-size: 17px; color: white; }
    .bridge-flow__item p { color: rgba(255,255,255,.74); font-size: 14px; }
    .bridge-endpoint { background: rgba(255,255,255,.07); border: 1px solid rgba(191,219,254,.14); border-radius: 18px; padding: 16px; color: #dbeafe; font-size: 14px; line-height: 1.8; overflow-x: auto; }
    .bridge-endpoint code { color: inherit; }
    .bridge-split { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
    .bridge-split > div { padding: 24px; display: grid; gap: 12px; align-content: start; }
    .bridge-split h3 { font-size: 20px; margin: 0; display: flex; align-items: center; gap: 10px; }
    .bridge-icon {
        width: 44px;
        height: 44px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 20px;
        flex: 0 0 auto;
    }
    .bridge-panel { padding: 32px; display: grid; gap: 18px; align-content: start; }
    .bridge-steps { display: grid; gap: 14px; }
    .bridge-step { border-radius: 20px; padding: 20px; display: grid; grid-template-columns: auto 1fr; gap: 16px; align-items: start; }
    .bridge-num {
        width: 36px;
        height: 36px;
        border-radius: 999px;
        background: var(--primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
    }
    .bridge-step h3 { font-size: 17px; margin: 0 0 4px; }
    .bridge-proof { padding: 32px; background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%); color: white; border: none; border-radius: 24px; box-shadow: 0 18px 42px rgba(15,23,42,.16); }
    .bridge-proof h2 { color: white; }
    .bridge-proof p { color: rgba(255,255,255,.84); }
    .bridge-proof-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-top: 18px; }
    .bridge-proof-grid div { border: 1px solid rgba(255,255,255,.16); border-radius: 16px; padding: 18px; background: rgba(255,255,255,.08); }
    .bridge-proof-grid strong { display: block; margin-bottom: 6px; }
    .bridge-note {
        border: 1px solid #fed7aa;
        background: #fff7ed;
        color: #7c2d12;
        border-radius: 18px;
        padding: 22px 24px;
    }
    .bridge-note strong { display: block; color: #431407; margin-bottom: 6px; }
    .bridge-cta { background: linear-gradient(135deg,#eff6ff,#ffffff); border: 1px solid #bfdbfe; border-radius: 24px; padding: 28px; display: grid; grid-template-columns: minmax(0,1fr) auto; gap: 18px; align-items: center; }
    @media (max-width: 860px) {
        .hero { padding-top: 220px; padding-bottom: 78px; }
        .hero h1 { font-size: 38px; line-height: 1.08; }
        .hero p { font-size: 17px; }
        .bridge-wrap { padding-top: 36px; gap: 22px; }
        .bridge-shell,
        .bridge-split,
        .bridge-proof-grid,
        .bridge-cta { grid-template-columns: 1fr; }
        .bridge-card,
        .bridge-panel,
        .bridge-proof { padding: 26px; }
        .bridge-card h2,
        .bridge-panel h2,
        .bridge-proof h2,
        .bridge-cta h2 { font-size: 26px; }
        .bridge-board h3 { font-size: 25px; }
    }
</style>

<main class="bridge-wrap">
    <div class="bridge-shell">
        <section class="bridge-card">
            <span class="bridge-eyebrow">Public website, private office</span>
            <h2>A stable public API lane for the pieces that cannot call localhost.</h2>
            <p>Meta, WordPress, lead forms, and client portals need a real HTTPS domain. The website bridge gives those systems one controlled route into approved CreditSoft API endpoints while the working client file stays inside the intranet.</p>
            <ul class="bridge-list">
                <li><span>Register the Meta OAuth callback like <code>https://yourdomain.com/oauth.php</code>.</span></li>
                <li><span>Forward only approved requests to the current office target.</span></li>
                <li><span>Keep raw tunnel credentials, staff passwords, and private casework out of the public website folder.</span></li>
            </ul>
        </section>

        <aside class="bridge-board">
            <span class="bridge-board__eyebrow">Bridge path</span>
            <h3>The domain stays stable even when the office tunnel changes.</h3>
            <p>Meta and portal users see the website. CreditSoft controls the private hop behind it.</p>

            <div class="bridge-flow">
                <div class="bridge-flow__item">
                    <span>Public</span>
                    <strong>Website endpoint</strong>
                    <p>The customer domain owns the callback, portal, and intake route.</p>
                </div>
                <div class="bridge-flow__item">
                    <span>Relay</span>
                    <strong>Website API bridge</strong>
                    <p>The bridge verifies the request and forwards it to the configured office API target.</p>
                </div>
                <div class="bridge-flow__item">
                    <span>Private</span>
                    <strong>CreditSoft intranet</strong>
                    <p>The actual casework, staff access, and AI tools remain on the approved office lane.</p>
                </div>
            </div>

            <pre class="bridge-endpoint"><code>Meta callback:
https://yourdomain.com/oauth.php</code></pre>
        </aside>
    </div>

    <section class="bridge-split">
        <div>
            <h3><span class="bridge-icon"><i class="fa-solid fa-globe"></i></span> Outside: website domain</h3>
            <p>This is what Meta, portal users, WordPress, and lead forms can safely reach.</p>
            <ul class="bridge-list">
                <li><span>Stable Meta callback like <code>https://yourdomain.com/oauth.php</code></span></li>
                <li><span>WordPress plugin or plain PHP drop-in for non-WordPress sites.</span></li>
                <li><span>Public HTTPS endpoint that can survive ngrok URL changes.</span></li>
            </ul>
        </div>

        <div>
            <h3><span class="bridge-icon"><i class="fa-solid fa-house-lock"></i></span> Inside: CreditSoft intranet</h3>
            <p>This is where casework, staff access, API keys, and private customer records stay.</p>
            <ul class="bridge-list">
                <li><span>Local-first office install remains private to the company machine or tailnet.</span></li>
                <li><span>Bridge forwards through the current office target, such as ngrok, Tailscale, or reverse proxy.</span></li>
                <li><span>No Tailscale admin key or ngrok host credential belongs in the public website folder.</span></li>
            </ul>
        </div>
    </section>

    <section class="bridge-panel">
        <span class="bridge-eyebrow">How it works</span>
        <h2>The callback URL becomes boring on purpose.</h2>
        <div class="bridge-steps">
            <article class="bridge-step">
                <div class="bridge-num">1</div>
                <div>
                    <h3>Install the bridge on the public site</h3>
                    <p>Use the WordPress add-on or the hand-coded PHP drop-in so the customer domain owns <strong>/api/v1</strong>.</p>
                </div>
            </article>
            <article class="bridge-step">
                <div class="bridge-num">2</div>
                <div>
                    <h3>Point the bridge at the office API target</h3>
                    <p>The target can be ngrok for testing, Tailscale when the website server can see the tailnet, or a future reverse proxy.</p>
                </div>
            </article>
            <article class="bridge-step">
                <div class="bridge-num">3</div>
                <div>
                    <h3>Give Meta the public callback, not localhost</h3>
                    <p>Meta only sees the stable website URL. CreditSoft handles the private hop behind it.</p>
                </div>
            </article>
        </div>
    </section>

    <section class="bridge-proof">
        <h2>This is also the lane for portals, forms, and companion tools.</h2>
        <p>The same bridge pattern supports client portal reads, lead intake, website forms, browser companion handoff, and future WordPress installs without turning the intranet into a public SaaS box.</p>
        <div class="bridge-proof-grid">
            <div><strong>Client portal</strong><p>Show portal-safe status, documents, and updates through the branded public site.</p></div>
            <div><strong>Lead intake</strong><p>Send public website leads into CreditSoft without copy-paste or disconnected inboxes.</p></div>
            <div><strong>Meta callbacks</strong><p>Keep OAuth and webhook URLs stable even when the temporary office tunnel changes.</p></div>
        </div>
    </section>

    <section class="bridge-note">
        <strong>Security boundary</strong>
        The public website gets a bridge token and a forward target. It should not store raw ngrok account keys, Tailscale admin keys, staff passwords, or client casework data.
    </section>

    <section class="bridge-cta">
        <div>
            <h2>The bridge belongs beside the client portal, not buried in setup notes.</h2>
            <p>Use it as the public API layer for Meta callbacks, website intake, WordPress installs, and portal handoff while CreditSoft keeps the private office system separate.</p>
        </div>
        <a href="/client-portal" class="btn btn-primary">View client portal</a>
    </section>
</main>

<?php require __DIR__ . '/footer.php'; ?>
