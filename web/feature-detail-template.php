<?php
require_once __DIR__ . '/feature-detail-data.php';

$feature_slug = $feature_slug ?? '';
$feature_page = creditsoft_feature_detail_page((string) $feature_slug);

if (! $feature_page) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    return;
}

$page_title = $feature_page['title'];
$page_description = $feature_page['description'];
$page_hero = true;
$hero_title = $feature_page['hero_title'];
$hero_subtitle = $feature_page['hero_subtitle'];
require __DIR__ . '/header.php';
?>
<style>
    .detail-wrap { max-width:1120px; margin:0 auto; padding:44px 20px 0; display:grid; gap:26px; }
    .detail-shell { display:grid; grid-template-columns:minmax(0,1.02fr) minmax(320px,.98fr); gap:24px; align-items:start; }
    .detail-panel,
    .detail-visual,
    .detail-section,
    .detail-provider-section,
    .detail-cta {
        background:white;
        border:1px solid var(--border);
        border-radius:24px;
        box-shadow:0 16px 40px rgba(15,23,42,.05);
    }
    .detail-panel { padding:30px 28px; display:grid; gap:18px; align-content:start; }
    .detail-eyebrow {
        display:inline-flex;
        width:max-content;
        padding:8px 12px;
        border-radius:999px;
        background:#dbeafe;
        color:#1d4ed8;
        font-size:12px;
        font-weight:800;
        letter-spacing:.1em;
        text-transform:uppercase;
    }
    .detail-panel h2,
    .detail-section h2,
    .detail-provider-section h2,
    .detail-cta h2 {
        font-size:clamp(1.9rem,3vw,3rem);
        line-height:1.05;
        margin:0;
    }
    .detail-panel p,
    .detail-section p,
    .detail-cta p {
        margin:0;
        color:var(--gray);
        line-height:1.7;
    }
    .detail-summary { list-style:none; padding:0; margin:0; display:grid; gap:12px; }
    .detail-summary li {
        padding:15px 16px;
        border-radius:16px;
        background:#f8fafc;
        border:1px solid var(--border);
        color:#334155;
    }
    .detail-visual { overflow:hidden; }
    .detail-visual img { width:100%; display:block; }
    .shot-link {
        display:block;
        position:relative;
        color:inherit;
        text-decoration:none;
        cursor:zoom-in;
    }
    .shot-link:hover { text-decoration:none; }
    .shot-link::after {
        content:'Open full size';
        position:absolute;
        right:14px;
        bottom:14px;
        padding:8px 10px;
        border-radius:999px;
        background:rgba(15,23,42,.86);
        color:white;
        font-size:11px;
        font-weight:800;
        letter-spacing:.08em;
        text-transform:uppercase;
        box-shadow:0 10px 20px rgba(15,23,42,.25);
        opacity:0;
        transform:translateY(6px);
        transition:opacity .18s ease, transform .18s ease;
        pointer-events:none;
    }
    .shot-link:hover::after,
    .shot-link:focus-visible::after {
        opacity:1;
        transform:translateY(0);
    }
    .detail-visual__caption {
        padding:16px 18px 18px;
        display:grid;
        gap:4px;
        background:linear-gradient(180deg,#ffffff,#f8fafc);
    }
    .detail-visual__caption strong { color:var(--dark); }
    .detail-visual__caption span { color:var(--gray); font-size:14px; }
    .detail-gallery {
        padding:26px 24px;
        display:grid;
        gap:18px;
        background:white;
        border:1px solid var(--border);
        border-radius:24px;
        box-shadow:0 16px 40px rgba(15,23,42,.05);
    }
    .detail-gallery__head {
        display:flex;
        justify-content:space-between;
        align-items:end;
        gap:18px;
    }
    .detail-gallery__head h2 {
        margin:0;
        font-size:clamp(1.8rem,3vw,2.7rem);
        line-height:1.08;
    }
    .detail-gallery__head p {
        max-width:470px;
        margin:0;
        color:var(--gray);
        line-height:1.65;
    }
    .detail-shot-grid {
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:14px;
    }
    .detail-shot {
        margin:0;
        overflow:hidden;
        border:1px solid var(--border);
        border-radius:18px;
        background:#f8fafc;
    }
    .detail-shot img {
        width:100%;
        aspect-ratio:16/10;
        display:block;
        object-fit:cover;
        object-position:top left;
        border-bottom:1px solid var(--border);
    }
    .detail-shot figcaption {
        padding:14px 15px 16px;
        display:grid;
        gap:5px;
    }
    .detail-shot strong { color:var(--dark); }
    .detail-shot span {
        color:var(--gray);
        font-size:14px;
        line-height:1.5;
    }
    body.lightbox-open { overflow:hidden; }
    .lightbox {
        position:fixed;
        inset:0;
        z-index:1000;
        display:none;
        align-items:center;
        justify-content:center;
        padding:28px;
        background:rgba(2,6,23,.82);
        backdrop-filter:blur(10px);
    }
    .lightbox.is-open { display:flex; }
    .lightbox__dialog {
        position:relative;
        width:min(1180px,100%);
        max-height:calc(100vh - 56px);
        display:grid;
        gap:14px;
        justify-items:center;
    }
    .lightbox__close {
        position:absolute;
        top:-6px;
        right:-6px;
        width:42px;
        height:42px;
        border:0;
        border-radius:999px;
        background:rgba(15,23,42,.92);
        color:white;
        font-size:22px;
        line-height:1;
        cursor:pointer;
        box-shadow:0 16px 28px rgba(15,23,42,.32);
    }
    .lightbox__image-wrap {
        width:100%;
        overflow:auto;
        border-radius:24px;
        background:rgba(255,255,255,.06);
        border:1px solid rgba(255,255,255,.14);
        box-shadow:0 24px 48px rgba(15,23,42,.28);
    }
    .lightbox__image {
        display:block;
        width:100%;
        height:auto;
    }
    .lightbox__caption {
        width:min(940px,100%);
        padding:12px 16px;
        border-radius:16px;
        background:rgba(15,23,42,.72);
        color:#e2e8f0;
        text-align:center;
        font-size:14px;
        line-height:1.6;
    }
    .detail-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px; }
    .detail-section { padding:26px 24px; display:grid; gap:18px; }
    .detail-section h2 { font-size:28px; }
    .detail-items { list-style:none; padding:0; margin:0; display:grid; gap:12px; }
    .detail-items li {
        padding:16px;
        border-radius:18px;
        border:1px solid var(--border);
        background:#f8fafc;
    }
    .detail-items strong { display:block; margin-bottom:5px; color:var(--dark); }
    .detail-items span { color:var(--gray); line-height:1.6; }
    .detail-provider-section { padding:26px 24px; display:grid; gap:18px; }
    .detail-provider-grid {
        display:grid;
        grid-template-columns:repeat(5,minmax(0,1fr));
        gap:12px;
    }
    .detail-provider {
        min-height:150px;
        border:1px solid var(--border);
        border-radius:16px;
        background:#f8fafc;
        padding:16px;
        display:grid;
        align-content:center;
        justify-items:center;
        gap:10px;
        text-align:center;
    }
    .detail-provider img {
        width:auto;
        max-width:170px;
        max-height:90px;
        object-fit:contain;
    }
    .detail-provider span {
        font-size:12px;
        font-weight:800;
        color:var(--dark);
    }
    .detail-cta {
        padding:28px;
        display:grid;
        grid-template-columns:minmax(0,1fr) auto;
        gap:18px;
        align-items:center;
        background:linear-gradient(135deg,#eff6ff,#ffffff);
        border-color:#bfdbfe;
    }
    .detail-actions { display:flex; flex-wrap:wrap; gap:12px; justify-content:flex-end; }
    @media(max-width:900px) {
        .detail-shell,
        .detail-shot-grid,
        .detail-grid,
        .detail-provider-grid,
        .detail-cta { grid-template-columns:1fr; }
        .detail-gallery__head { display:grid; }
        .detail-actions { justify-content:flex-start; }
    }
    @media(max-width:640px) {
        .detail-wrap { padding-inline:14px; }
        .lightbox { padding:14px; }
        .lightbox__close { top:4px; right:4px; }
        .detail-panel,
        .detail-gallery,
        .detail-section,
        .detail-provider-section,
        .detail-cta { padding:22px 18px; border-radius:20px; }
    }
</style>

<main class="detail-wrap">
    <div class="detail-shell">
        <section class="detail-panel">
            <span class="detail-eyebrow"><?= htmlspecialchars((string) $feature_page['eyebrow'], ENT_QUOTES, 'UTF-8') ?></span>
            <h2><?= htmlspecialchars((string) $feature_page['title'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= htmlspecialchars((string) $feature_page['lead'], ENT_QUOTES, 'UTF-8') ?></p>
            <ul class="detail-summary">
                <?php foreach ((array) $feature_page['summary'] as $summary): ?>
                    <li><?= htmlspecialchars((string) $summary, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </section>

        <figure class="detail-visual">
            <a class="shot-link" href="<?= htmlspecialchars((string) $feature_page['image'], ENT_QUOTES, 'UTF-8') ?>" data-lightbox-caption="<?= htmlspecialchars((string) ($feature_page['image_caption'] ?? $feature_page['image_alt']), ENT_QUOTES, 'UTF-8') ?>" aria-label="Open <?= htmlspecialchars((string) $feature_page['title'], ENT_QUOTES, 'UTF-8') ?> screenshot full size">
                <img src="<?= htmlspecialchars((string) $feature_page['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) $feature_page['image_alt'], ENT_QUOTES, 'UTF-8') ?>" loading="eager">
            </a>
            <figcaption class="detail-visual__caption">
                <strong><?= htmlspecialchars((string) $feature_page['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                <span><?= htmlspecialchars((string) ($feature_page['image_caption'] ?? 'Current product view from the CreditSoft workspace.'), ENT_QUOTES, 'UTF-8') ?></span>
            </figcaption>
        </figure>
    </div>

    <?php if (! empty($feature_page['gallery'])): ?>
        <section class="detail-gallery" aria-labelledby="detail-gallery-title">
            <div class="detail-gallery__head">
                <div>
                    <span class="detail-eyebrow">CRM workspace</span>
                    <h2 id="detail-gallery-title">See the CRM lane in motion.</h2>
                </div>
                <p>Clients, leads, billing health, provider access, and relationship actions stay in one local-first workspace.</p>
            </div>
            <div class="detail-shot-grid">
                <?php foreach ((array) $feature_page['gallery'] as $shot): ?>
                    <figure class="detail-shot">
                        <a class="shot-link" href="<?= htmlspecialchars((string) $shot['image'], ENT_QUOTES, 'UTF-8') ?>" data-lightbox-caption="<?= htmlspecialchars((string) $shot['copy'], ENT_QUOTES, 'UTF-8') ?>" aria-label="Open <?= htmlspecialchars((string) $shot['label'], ENT_QUOTES, 'UTF-8') ?> screenshot full size">
                            <img src="<?= htmlspecialchars((string) $shot['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) $shot['alt'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                            <figcaption>
                                <strong><?= htmlspecialchars((string) $shot['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <span><?= htmlspecialchars((string) $shot['copy'], ENT_QUOTES, 'UTF-8') ?></span>
                            </figcaption>
                        </a>
                    </figure>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (! empty($feature_page['providers'])): ?>
        <section class="detail-provider-section">
            <div>
                <span class="detail-eyebrow">Supported providers</span>
                <h2>Email lanes your office can configure.</h2>
            </div>
            <div class="detail-provider-grid" aria-label="Supported email providers">
                <?php foreach (creditsoft_email_provider_cards() as $provider): ?>
                    <div class="detail-provider">
                        <img src="<?= htmlspecialchars((string) $provider['logo'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) $provider['label'], ENT_QUOTES, 'UTF-8') ?>">
                        <span><?= htmlspecialchars((string) $provider['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="detail-grid">
        <?php foreach ((array) $feature_page['sections'] as $section): ?>
            <section class="detail-section">
                <h2><?= htmlspecialchars((string) $section['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                <ul class="detail-items">
                    <?php foreach ((array) $section['items'] as $item): ?>
                        <li>
                            <strong><?= htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars((string) $item['copy'], ENT_QUOTES, 'UTF-8') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>

    <section class="detail-cta">
        <div>
            <h2>Keep the overview clean.</h2>
            <p><?= htmlspecialchars((string) $feature_page['cta_copy'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="detail-actions">
            <a href="/features" class="btn btn-outline" style="color:var(--primary);border-color:var(--primary)">Back to overview</a>
            <a href="/pricing" class="btn btn-primary">See pricing</a>
        </div>
    </section>
</main>

<div class="lightbox" id="featureLightbox" aria-hidden="true">
    <div class="lightbox__dialog" role="dialog" aria-modal="true" aria-label="Feature screenshot preview">
        <button type="button" class="lightbox__close" id="featureLightboxClose" aria-label="Close screenshot preview">&times;</button>
        <div class="lightbox__image-wrap">
            <img class="lightbox__image" id="featureLightboxImage" src="" alt="">
        </div>
        <div class="lightbox__caption" id="featureLightboxCaption"></div>
    </div>
</div>

<script>
(() => {
    const lightbox = document.getElementById('featureLightbox');
    const image = document.getElementById('featureLightboxImage');
    const caption = document.getElementById('featureLightboxCaption');
    const closeButton = document.getElementById('featureLightboxClose');
    const links = document.querySelectorAll('.shot-link');

    if (!lightbox || !image || !caption || !closeButton || !links.length) {
        return;
    }

    const closeLightbox = () => {
        lightbox.classList.remove('is-open');
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('lightbox-open');
        image.src = '';
        image.alt = '';
        caption.textContent = '';
    };

    links.forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const previewImage = link.querySelector('img');
            image.src = link.href;
            image.alt = previewImage?.alt || '';
            caption.textContent = link.dataset.lightboxCaption || previewImage?.alt || '';
            lightbox.classList.add('is-open');
            lightbox.setAttribute('aria-hidden', 'false');
            document.body.classList.add('lightbox-open');
        });
    });

    closeButton.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', (event) => {
        if (event.target === lightbox) {
            closeLightbox();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && lightbox.classList.contains('is-open')) {
            closeLightbox();
        }
    });
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
