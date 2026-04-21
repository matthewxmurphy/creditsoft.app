<?php
http_response_code(404);

require_once __DIR__ . '/admin/bootstrap.php';

$page_title = 'Page Not Found';
$page_description = 'The page you asked for is not available.';
$page_robots = 'noindex, follow';
$page_canonical_url = 'https://www.creditsoft.app/404';

include __DIR__ . '/header.php';
?>

<style>
    .not-found-shell {
        max-width: 1100px;
        margin: 0 auto;
        padding: 44px 20px 68px;
        display: grid;
        gap: 24px;
    }
    .not-found-card {
        background: rgba(255,255,255,0.94);
        border: 1px solid rgba(15,23,42,0.08);
        border-radius: 28px;
        box-shadow: 0 24px 56px rgba(15,23,42,0.08);
        padding: 34px;
    }
    .not-found-kicker {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px;
        border-radius: 999px;
        background: rgba(37,99,235,0.08);
        color: var(--primary);
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
        margin-bottom: 18px;
    }
    .not-found-card h1 {
        font-size: clamp(2.2rem, 5vw, 4.2rem);
        line-height: .95;
        margin-bottom: 14px;
    }
    .not-found-card p {
        color: var(--gray);
        max-width: 760px;
        margin: 0 0 18px;
    }
    .not-found-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 12px;
    }
    .not-found-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
    }
    .not-found-box {
        border: 1px solid rgba(15,23,42,0.08);
        border-radius: 22px;
        background: rgba(255,255,255,0.88);
        padding: 22px;
    }
    .not-found-box h2 {
        font-size: 21px;
        margin-bottom: 10px;
    }
    .not-found-box p {
        color: var(--gray);
        margin: 0 0 14px;
    }
    .not-found-box ul {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        gap: 10px;
    }
    .not-found-box li::before {
        content: '•';
        color: var(--success);
        font-weight: 800;
        margin-right: 10px;
    }
    @media (max-width: 860px) {
        .not-found-grid {
            grid-template-columns: 1fr;
        }
        .not-found-card,
        .not-found-box {
            padding: 22px;
        }
    }
</style>

<div class="not-found-shell">
    <section class="not-found-card">
        <div class="not-found-kicker">404 page</div>
        <h1>That page fell out of the workflow.</h1>
        <p>The link may be old, the page may have moved, or the address may have been typed a little off. The rest of the site is still here, so let’s route you back into a live lane instead of leaving you on a dead end.</p>
        <div class="not-found-actions">
            <a href="/" class="btn btn-primary">Go home</a>
            <a href="/features" class="btn">Open features</a>
            <a href="/pricing" class="btn">Open pricing</a>
            <a href="/client-portal" class="btn">Open portal</a>
        </div>
    </section>

    <section class="not-found-grid">
        <article class="not-found-box">
            <h2>Product pages</h2>
            <p>If you were looking for software details, the product lane is still intact.</p>
            <ul>
                <li><a href="/features">Features</a></li>
                <li><a href="/roadmap">Roadmap</a></li>
                <li><a href="/migration">Migration</a></li>
            </ul>
        </article>
        <article class="not-found-box">
            <h2>Sales pages</h2>
            <p>If you were trying to buy, compare, or qualify an office, these are the right next stops.</p>
            <ul>
                <li><a href="/pricing">Pricing</a></li>
                <li><a href="/subscribe">Start intake</a></li>
                <li><a href="/requirements">Requirements</a></li>
            </ul>
        </article>
        <article class="not-found-box">
            <h2>Existing customers</h2>
            <p>If you were trying to reach the customer side, jump straight into the live entry points here.</p>
            <ul>
                <li><a href="/client-portal">Client portal</a></li>
                <li><a href="<?= htmlspecialchars(cs_site_admin_url('/login'), ENT_QUOTES, 'UTF-8') ?>">Site admin</a></li>
                <li><a href="mailto:hello@creditsoft.app">Contact support</a></li>
            </ul>
        </article>
    </section>
</div>

<?php include __DIR__ . '/footer.php'; ?>
