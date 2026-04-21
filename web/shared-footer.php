<?php
require_once __DIR__ . '/site-tracking-config.php';
$footerTracking = creditsoft_site_tracking_load();
$footerSocialLinks = array_filter([
    [
        'label' => 'Facebook',
        'href' => creditsoft_site_tracking_public_facebook_url($footerTracking),
        'icon' => '<i class="fa-brands fa-facebook-f" aria-hidden="true"></i>',
    ],
    [
        'label' => 'Instagram',
        'href' => creditsoft_site_tracking_public_instagram_url($footerTracking),
        'icon' => '<i class="fa-brands fa-instagram" aria-hidden="true"></i>',
    ],
    [
        'label' => 'Threads',
        'href' => creditsoft_site_tracking_public_threads_url($footerTracking),
        'icon' => '<i class="fa-brands fa-threads" aria-hidden="true"></i>',
    ],
    [
        'label' => 'X',
        'href' => creditsoft_site_tracking_public_x_url($footerTracking),
        'icon' => '<i class="fa-brands fa-x-twitter" aria-hidden="true"></i>',
    ],
], static fn ($item) => ! empty($item['href']));
?>
<style>
    .footer {
        background:
            radial-gradient(circle at top left, rgba(37, 99, 235, 0.18), transparent 26%),
            linear-gradient(180deg, #0a0f1a 0%, #070b13 100%);
        color: #94a3b8;
        padding: 42px 0 22px;
        margin-top: 64px;
        border-top: 1px solid rgba(148, 163, 184, 0.14);
        font-size: 14px;
    }
    .footer-container {
        max-width: 1100px;
        margin: 0 auto;
        padding: 0 20px;
    }
    .footer-shell {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 0.58fr) minmax(0, 1.18fr) minmax(0, 0.82fr);
        gap: 24px;
        align-items: start;
    }
    .footer-shell > * {
        text-align: left;
    }
    .footer-brand {
        display: grid;
        gap: 12px;
        padding-right: 24px;
    }
    .footer-column {
        padding-left: 24px;
        border-left: 1px solid rgba(148, 163, 184, 0.16);
    }
    .footer-brand img {
        height: 60px;
        width: auto;
        display: block;
    }
    .footer-brand p {
        max-width: 340px;
        color: #94a3b8;
        margin: 0;
        line-height: 1.55;
    }
    .footer-socials {
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
        margin-top: 2px;
    }
    .footer-socials a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #e2e8f0;
        text-decoration: none;
        transition: transform .18s, color .18s, opacity .18s;
        opacity: 0.88;
    }
    .footer-socials a:hover {
        transform: translateY(-2px);
        color: white;
        opacity: 1;
        text-decoration: none;
    }
    .footer-socials svg,
    .footer-socials i {
        width: 21px;
        height: 21px;
        display: block;
        font-size: 21px;
        line-height: 1;
    }
    .footer-column h3 {
        font-size: 12px;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #e2e8f0;
        margin-bottom: 12px;
    }
    .footer-links {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 9px;
    }
    .footer-links a {
        color: #94a3b8;
        text-decoration: none;
    }
    .footer-links a:hover {
        color: #ffffff;
        text-decoration: none;
    }
    .footer-bottom {
        margin-top: 20px;
        padding-top: 18px;
        border-top: 1px solid rgba(148, 163, 184, 0.12);
        display: flex;
        justify-content: space-between;
        gap: 16px;
        color: #64748b;
        flex-wrap: wrap;
    }
    .footer-bottom a {
        color: #93c5fd;
        text-decoration: none;
    }
    .footer-disclaimer {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid rgba(148, 163, 184, 0.12);
        color: #7b8aa1;
        font-size: 12px;
        line-height: 1.6;
    }
    .footer-disclaimer strong {
        color: #cbd5e1;
    }
    @media(max-width: 960px) {
        .footer-shell {
            grid-template-columns: 1fr 1fr;
        }
        .footer-brand {
            padding-right: 0;
        }
    }
    @media(max-width: 640px) {
        .footer {
            padding-top: 44px;
        }
        .footer-shell {
            grid-template-columns: 1fr;
        }
        .footer-column {
            padding-left: 0;
            border-left: none;
            padding-top: 18px;
            border-top: 1px solid rgba(148, 163, 184, 0.12);
        }
        .footer-bottom {
            flex-direction: column;
        }
    }
</style>
<footer class="footer">
    <div class="footer-container footer-shell">
        <div class="footer-brand">
            <a href="/"><img src="/assets/images/CreditSoft.png" alt="CreditSoft"></a>
            <p>Metro2-first credit repair software with a local intranet, branded website packaging, browser-companion workflows, and client operations built around the actual report data.</p>
            <?php if ($footerSocialLinks !== []): ?>
                <div class="footer-socials" aria-label="CreditSoft social links">
                    <?php foreach ($footerSocialLinks as $socialLink): ?>
                        <a href="<?= htmlspecialchars((string) $socialLink['href'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" aria-label="<?= htmlspecialchars((string) $socialLink['label'], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars((string) $socialLink['label'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= $socialLink['icon'] ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="footer-column footer-column--product">
            <h3>Product</h3>
            <ul class="footer-links">
                <li><a href="/features">Features</a></li>
                <li><a href="/metro2">Metro2 review</a></li>
                <li><a href="/pricing">Pricing</a></li>
                <li><a href="/websites">Client site gallery</a></li>
                <li><a href="/client-portal">Client portal</a></li>
                <li><a href="/api-bridge">Website API bridge</a></li>
            </ul>
        </div>
        <div class="footer-column footer-column--resources">
            <h3>Resources</h3>
            <ul class="footer-links">
                <li><a href="/resources">Resources hub</a></li>
                <li><a href="/quiz">Quizzes</a></li>
                <li><a href="/videos">Videos</a></li>
                <li><a href="/requirements">Requirements</a></li>
                <li><a href="/migration">Migration</a></li>
                <li><a href="/roadmap">Roadmap</a></li>
                <li><a href="/options">Options roadmap</a></li>
                <li><a href="/outsourcing">Outsourcing</a></li>
                <li><a href="/compliance">Compliance</a></li>
                <li><a href="/disputes">Disputes</a></li>
                <li><a href="/reporting">Reporting</a></li>
                <li><a href="/start-repairing-credit">Start Repairing Credit</a></li>
                <li><a href="/run-a-credit-repair-business">Run a Credit Repair Business</a></li>
                <li><a href="/scale-your-credit-repair-business">Scale Your Credit Repair Business</a></li>
                <li><a href="/built-in-automation">Built-In Automation</a></li>
                <li><a href="/security">Security</a></li>
            </ul>
        </div>
        <div class="footer-column">
            <h3>Access &amp; Company</h3>
            <ul class="footer-links">
                <li><a href="/subscribe">Start intake</a></li>
                <li><a href="/login">Login</a></li>
                <li><a href="/payment-help">Payment help</a></li>
                <li><a href="/about">About</a></li>
                <li><a href="/terms">Terms of Service</a></li>
                <li><a href="/privacy">Privacy Policy</a></li>
                <li><a href="/cookies">Cookie Policy</a></li>
                <li><a href="mailto:hello@creditsoft.app">Contact Us</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-container footer-bottom">
        <span>© 2026 CreditSoft. All rights reserved. Built for local-first credit operations.</span>
        <span>Designed by <a href="https://www.matthewxmurphy.com">Matthew Murphy</a> · Hosted on <a href="https://www.net30hosting.com">Net30Hosting</a></span>
    </div>
    <div class="footer-container footer-disclaimer">
        <strong>Legal disclaimer:</strong> The information on this site, our quizzes, guides, and educational pages is for informational purposes only. It does not constitute legal advice and does not replace legal advice. Anyone seeking legal guidance should consult counsel familiar with their specific situation, especially because consumer credit laws, compliance duties, and licensing rules can vary by state.
    </div>
</footer>
