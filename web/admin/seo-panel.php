<?php
declare(strict_types=1);
?>
<section class="overview-grid<?= admin_panel_visible('seo', $panel) ?>">
    <article class="card section-card">
        <div class="section-head">
            <div class="section-head-copy">
                <span class="eyebrow">Crawler health</span>
                <h2>Sitemap, robots, and preview defaults</h2>
                <p>Keep the discovery layer honest. This lane shows every public URL in the sitemap and lets you tune the metadata without touching the page templates directly.</p>
            </div>
        </div>
        <div class="social-summary-grid">
            <article class="social-summary-card">
                <strong><?= count($seoRows) ?></strong>
                <span>URLs in the live sitemap</span>
            </article>
            <article class="social-summary-card">
                <strong><?= $siteTracking['google_measurement_id'] !== '' ? 'Live' : 'Missing' ?></strong>
                <span>Google tag status<?= $siteTracking['google_measurement_id'] !== '' ? ' · ' . admin_escape((string) $siteTracking['google_measurement_id']) : '' ?></span>
            </article>
            <article class="social-summary-card">
                <strong><?= $siteTracking['meta_pixel_id'] !== '' ? 'Live' : 'Missing' ?></strong>
                <span>Meta Pixel status<?= $siteTracking['meta_pixel_id'] !== '' ? ' · ' . admin_escape((string) $siteTracking['meta_pixel_id']) : '' ?></span>
            </article>
            <article class="social-summary-card">
                <strong><?= admin_escape(basename((string) ($siteSeo['default_og_image'] ?? '/assets/images/og-image.png'))) ?></strong>
                <span>Default social image</span>
            </article>
        </div>
        <div class="social-link-list">
            <a class="social-link-chip is-dark" href="<?= public_url('/sitemap.xml') ?>" target="_blank" rel="noopener">Open sitemap.xml</a>
            <a class="social-link-chip" href="<?= public_url('/robots.txt') ?>" target="_blank" rel="noopener">Open robots.txt</a>
            <a class="social-link-chip" href="https://developers.facebook.com/tools/debug/?q=<?= rawurlencode('https://www.creditsoft.app/') ?>" target="_blank" rel="noopener">Debug homepage share</a>
        </div>
        <div class="note-box">We are keeping the default OG image generic unless you explicitly assign a page-specific one. That keeps sharing predictable instead of letting random gallery art become the preview.</div>
    </article>
    <article class="card section-card">
        <div class="section-head">
            <div class="section-head-copy">
                <span class="eyebrow">Analytics lane</span>
                <h2>Google Analytics connection</h2>
                <p>The tracking tag is live, but page-level GA data still needs a property connection before this admin can score titles and descriptions against traffic.</p>
            </div>
        </div>
        <div class="social-meta-list">
            <div class="social-meta-row">
                <div>
                    <strong>Measurement tag</strong>
                    <span>Browser tracking already loaded by the shared header.</span>
                </div>
                <div class="social-meta-value"><?= $siteTracking['google_measurement_id'] !== '' ? admin_escape((string) $siteTracking['google_measurement_id']) : 'Not saved' ?></div>
            </div>
            <div class="social-meta-row">
                <div>
                    <strong>Property data</strong>
                    <span>Once GA property access is connected, this panel can compare page traffic against titles, descriptions, and social previews.</span>
                </div>
                <div class="social-meta-value">Connection pending</div>
            </div>
            <div class="social-meta-row">
                <div>
                    <strong>Meta rescrape</strong>
                    <span>Use the Sharing Debugger on a page after you change its title, description, or OG image.</span>
                </div>
                <div class="social-meta-value"><a href="https://developers.facebook.com/tools/debug/" target="_blank" rel="noopener">Open debugger</a></div>
            </div>
        </div>
    </article>
</section>

<section class="card section-card<?= admin_panel_visible('seo', $panel) ?>">
    <div class="section-head">
        <div class="section-head-copy">
            <span class="eyebrow">Global defaults</span>
            <h2>Default social preview</h2>
            <p>Use one safe fallback image across the site, then override only the pages that deserve their own card.</p>
        </div>
    </div>
    <form method="post" class="admin-form-grid" data-autosave="true">
        <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
        <input type="hidden" name="action" value="save_seo">
        <input type="hidden" name="panel" value="seo">
        <div class="admin-form-grid is-two">
            <div class="admin-field">
                <label>Default OG image path</label>
                <input class="admin-input" type="text" name="default_og_image" value="<?= admin_escape((string) ($siteSeo['default_og_image'] ?? '/assets/images/og-image.png')) ?>">
            </div>
            <div class="admin-field">
                <label>Current fallback preview</label>
                <div class="seo-image-preview">
                    <img src="<?= admin_escape((string) ($siteSeo['default_og_image'] ?? '/assets/images/og-image.png')) ?>" alt="Default OG preview">
                </div>
            </div>
        </div>
        <div class="helper-copy">Sitemap routes update automatically from the public page files. The default OG image stays generic until you override a page below.</div>
        <div class="autosave-note"><span>Leave the field and the default preview path saves automatically.</span><span class="autosave-status" aria-live="polite"></span></div>
    </form>
</section>

<section class="card section-card<?= admin_panel_visible('seo', $panel) ?>">
    <div class="section-head">
        <div class="section-head-copy">
            <span class="eyebrow">Per-page SEO</span>
            <h2>Sitemap pages and preview tags</h2>
            <p>Each row shows the current source metadata, the live effective values, and a dedicated upload lane for a page-specific OG image.</p>
        </div>
    </div>
    <div class="social-collapse-stack">
        <?php foreach ($seoRows as $row): ?>
            <details class="social-collapse">
                <summary>
                    <span class="social-collapse-label">
                        <strong><?= admin_escape((string) $row['label']) ?></strong>
                        <span><?= admin_escape((string) $row['url']) ?></span>
                    </span>
                    <span class="social-collapse-pill"><?= $row['has_override'] ? 'Override live' : 'Source default' ?></span>
                </summary>
                <div class="social-collapse-body">
                    <div class="seo-page-grid">
                        <div class="seo-page-card">
                            <span class="eyebrow">Current source</span>
                            <strong><?= admin_escape((string) $row['source_title']) ?></strong>
                            <p><?= admin_escape((string) $row['source_description']) ?></p>
                            <div class="helper-copy">OG image: <?= admin_escape((string) $row['source_og_image']) ?></div>
                            <div class="helper-copy">Last modified: <?= admin_escape(date('M j, Y g:i a', strtotime((string) $row['lastmod']))) ?></div>
                        </div>
                        <div class="seo-page-card">
                            <span class="eyebrow">Live effective</span>
                            <strong><?= admin_escape((string) $row['title']) ?></strong>
                            <p><?= admin_escape((string) $row['description']) ?></p>
                            <div class="helper-copy">OG image: <?= admin_escape((string) $row['og_image']) ?></div>
                            <div class="social-link-list">
                                <a class="social-link-chip" href="<?= admin_escape((string) $row['url']) ?>" target="_blank" rel="noopener">Open page</a>
                                <a class="social-link-chip" href="https://developers.facebook.com/tools/debug/?q=<?= rawurlencode((string) $row['url']) ?>" target="_blank" rel="noopener">Open debugger</a>
                            </div>
                        </div>
                    </div>

                    <form method="post" class="admin-form-grid" data-autosave="true">
                        <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
                        <input type="hidden" name="action" value="save_seo">
                        <input type="hidden" name="panel" value="seo">
                        <div class="admin-form-grid is-two">
                            <div class="admin-field">
                                <label>Browser and share title</label>
                                <input class="admin-input" type="text" name="pages[<?= admin_escape((string) $row['slug_key']) ?>][title]" value="<?= admin_escape((string) $row['title']) ?>">
                            </div>
                            <div class="admin-field">
                                <label>OG image path</label>
                                <input class="admin-input" type="text" name="pages[<?= admin_escape((string) $row['slug_key']) ?>][og_image]" value="<?= admin_escape((string) $row['og_image']) ?>">
                            </div>
                        </div>
                        <div class="admin-field">
                            <label>Meta description</label>
                            <textarea class="admin-textarea" name="pages[<?= admin_escape((string) $row['slug_key']) ?>][description]"><?= admin_escape((string) $row['description']) ?></textarea>
                        </div>
                        <div class="autosave-note"><span>Leave a field and CreditSoft saves the SEO override for this page.</span><span class="autosave-status" aria-live="polite"></span></div>
                    </form>

                    <form method="post" enctype="multipart/form-data" class="seo-upload-form">
                        <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
                        <input type="hidden" name="action" value="upload_seo_image">
                        <input type="hidden" name="panel" value="seo">
                        <input type="hidden" name="page_slug" value="<?= admin_escape((string) $row['slug']) ?>">
                        <label class="seo-upload-drop">
                            <span><strong>Drop a PNG/JPG/WebP here</strong> or click to choose a replacement preview image for <?= admin_escape((string) $row['label']) ?>.</span>
                            <input type="file" name="seo_image_file" accept=".png,.jpg,.jpeg,.webp" required>
                        </label>
                        <div class="form-actions">
                            <button type="submit" class="hero-btn">Upload and assign image</button>
                        </div>
                    </form>
                </div>
            </details>
        <?php endforeach; ?>
    </div>
</section>
