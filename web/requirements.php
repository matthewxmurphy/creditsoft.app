<?php
declare(strict_types=1);

$page_title = 'System Requirements';
$page_description = 'Supported operating systems, hardware expectations, and network guidance for running CreditSoft well.';
$page_hero = true;
$hero_title = 'System Requirements';
$hero_subtitle = 'Supported systems, realistic upgrade paths, and the network basics that keep CreditSoft running smoothly.';

require __DIR__ . '/header.php';
?>
<style>
    .requirements-wrap { max-width: 1080px; margin: 0 auto; padding: 44px 20px 0; display: grid; gap: 24px; }
    .requirements-intro { max-width: 860px; color: var(--gray); font-size: 17px; }
    .requirements-selling { background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%); border: 1px solid #bfdbfe; border-radius: 22px; padding: 28px 26px; box-shadow: 0 16px 40px rgba(37,99,235,.08); }
    .requirements-selling h2 { font-size: 30px; margin-bottom: 10px; }
    .requirements-selling p { color: var(--gray); max-width: 920px; margin-bottom: 16px; }
    .requirements-selling-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
    .requirements-selling-card { background: rgba(255,255,255,.9); border: 1px solid rgba(191,219,254,.9); border-radius: 18px; padding: 18px 16px; }
    .requirements-selling-card strong { display:block; font-size: 17px; margin-bottom: 8px; color: var(--dark); }
    .requirements-selling-card span { color: var(--gray); font-size: 14px; line-height: 1.6; }
    .requirements-shell { display:grid; grid-template-columns:1.15fr .85fr; gap:24px; align-items:start; }
    .requirements-card { background:white; border:1px solid var(--border); border-radius:22px; padding:28px 26px; box-shadow:0 16px 40px rgba(15,23,42,.05); }
    .requirements-card h2 { font-size:28px; margin-bottom:10px; }
    .requirements-lead { color:var(--gray); margin-bottom:18px; }
    .requirements-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
    .requirements-pill { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; margin-bottom:14px; }
    .requirements-pill.good { background:#dcfce7; color:#166534; }
    .requirements-pill.warn { background:#fee2e2; color:#991b1b; }
    .requirements-list { list-style:none; margin:0; padding:0; }
    .requirements-list li { padding:10px 0; border-bottom:1px solid var(--border); color:var(--dark); }
    .requirements-list li:last-child { border-bottom:none; }
    .requirements-list li::before { content:'•'; color:var(--primary); font-weight:700; margin-right:10px; }
    .requirements-side { display:grid; gap:18px; }
    .requirements-note { background:#0f172a; color:white; border-radius:22px; padding:26px 24px; box-shadow:0 20px 44px rgba(15,23,42,.16); }
    .requirements-note h3 { font-size:20px; margin-bottom:10px; }
    .requirements-note p { color:rgba(255,255,255,.82); margin:0 0 14px; }
    .requirements-note strong { color:white; }
    .requirements-note .mini { font-size:13px; color:rgba(255,255,255,.72); }
    .requirements-note a {
        color: #bfdbfe;
        font-weight: 700;
        text-decoration: none;
    }
    .requirements-note a:hover {
        color: #ffffff;
        text-decoration: underline;
    }
    @media(max-width: 900px) {
        .requirements-selling-grid { grid-template-columns:1fr; }
        .requirements-shell { grid-template-columns:1fr; }
        .requirements-grid { grid-template-columns:1fr; }
    }
</style>

<div class="requirements-wrap">
    <p class="requirements-intro">CreditSoft is built for supported modern systems only. We do not support end-of-life operating systems. If an older office machine is still physically solid, we can often help turn it into a usable Ubuntu workstation or minimal server build, but only after the right storage, memory, and network basics are in place.</p>

    <section class="requirements-selling">
        <h2>Your hardware is the ceiling, and that is also the selling point.</h2>
        <p>With CreditSoft, you are not waiting on somebody else’s shared cloud stack to decide how fast your office can move. If you want more headroom, you can upgrade your own hardware. If you want to start lean, you can do that too.</p>
        <div class="requirements-selling-grid">
            <article class="requirements-selling-card">
                <strong>Start small if you need to</strong>
                <span>CreditSoft can run on a Raspberry Pi 4 or 5. It is a real local-first lane, not a fake “self-hosted” sticker on top of a SaaS dependency.</span>
            </article>
            <article class="requirements-selling-card">
                <strong>More hardware means more breathing room</strong>
                <span>8 GB RAM is workable, 16 GB is better. SSD is the floor, NVMe is better. An i3-class machine can do the job, while newer Ryzen-class hardware gives the office much more headroom.</span>
            </article>
            <article class="requirements-selling-card">
                <strong>You can scale by upgrading your own box</strong>
                <span>Buy a faster computer, add memory, swap in a better SSD, or move the office onto stronger hardware when the workload grows. The performance ceiling stays in your hands.</span>
            </article>
        </div>
    </section>

    <div class="requirements-shell">
        <div class="requirements-card">
            <h2>Supported office setup</h2>
            <p class="requirements-lead">The local intranet runs best on supported operating systems, SSD storage, enough RAM, and a stable network. If the office is working remotely, upload speed matters just as much as download speed.</p>

            <div class="requirements-grid">
                <div>
                    <span class="requirements-pill good">Supported desktop OS</span>
                    <ul class="requirements-list">
                        <li>macOS on supported current hardware</li>
                        <li>Ubuntu LTS desktop or server on supported hardware</li>
                        <li>Windows 11 or newer only</li>
                    </ul>
                </div>
                <div>
                    <span class="requirements-pill warn">Not supported</span>
                    <ul class="requirements-list">
                        <li>Windows 10 after end-of-life</li>
                        <li>Any end-of-life macOS or Linux release</li>
                        <li>Neglected systems left on dead operating systems</li>
                    </ul>
                </div>
                <div>
                    <span class="requirements-pill good">Recommended hardware</span>
                    <ul class="requirements-list">
                        <li>Raspberry Pi 4 or 5 can work for light local setups</li>
                        <li>8 GB RAM minimum, 16 GB preferred</li>
                        <li>i3-class hardware can run it, newer Ryzen-class hardware gives more headroom</li>
                        <li>SSD required, NVMe preferred, and never spinning disk</li>
                        <li>RAM should be maxed where practical on older rebuilds</li>
                    </ul>
                </div>
                <div>
                    <span class="requirements-pill good">Network and browser</span>
                    <ul class="requirements-list">
                        <li>Stable LAN inside the office for the local intranet</li>
                        <li>Strong upload link if the team works remotely</li>
                        <li>Supported Chromium-based browser for the browser companion</li>
                        <li>Internet required for license, updates, and companion validation</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="requirements-side">
            <div class="requirements-note">
                <h3>Ubuntu refurb lane</h3>
                <p>If an older Windows office box still has decent bones, we can often repurpose it with Ubuntu desktop or Ubuntu server minimal.</p>
                <p><strong>Usually that means:</strong> replace the drive with an SSD, max out the supported memory, and make sure the office network is actually fast enough for how the team works.</p>
                <p><a href="mailto:hello@creditsoft.app?subject=System%20Requirements%20Check">Click here to contact support and send us the system details</a> if you want help figuring out whether a machine is worth saving and what needs to be upgraded first.</p>
                <p class="mini">That is a path to a supported setup. It is not continued support for Windows 10 or any other end-of-life operating system.</p>
            </div>

            <div class="requirements-note">
                <h3>No EOL OS support</h3>
                <p>We are firm on this. If the operating system is end-of-life, it is out of support for CreditSoft too.</p>
                <p><strong>Windows 11+</strong> can still be a good fit, and we are willing to help make it faster for free in exchange for an honest review.</p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
