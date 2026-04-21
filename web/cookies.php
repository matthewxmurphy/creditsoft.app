<?php
$page_title = 'Cookie Policy';
$page_description = 'CreditSoft Cookie Policy - GDPR, CCPA, Google Consent Mode';
$page_hero = true;
$hero_class = 'hero--legal';
$hero_title = 'Cookie Policy';
$hero_subtitle = '';
require __DIR__ . '/header.php';
?>
<style>
    .container-narrow { max-width: 800px; margin: 0 auto; padding: 40px 20px; }
    .legal-content h2 { font-size: 22px; margin: 32px 0 12px; }
    .legal-content p, .legal-content li { color: var(--gray); margin-bottom: 12px; }
    .legal-content ul { padding-left: 24px; }
    .last-updated { color: var(--gray); font-size: 14px; margin-bottom: 32px; }
</style>

<div class="container-narrow">
    <p class="last-updated">Last Updated: February 2026</p>
    <div class="legal-content">
        <h2>What Are Cookies</h2>
        <p>Small text files stored on your device to enhance browsing experience.</p>

        <h2>Types We Use</h2>
        <ul>
            <li><strong>Essential:</strong> Required for site functionality</li>
            <li><strong>Analytics:</strong> Help us understand site usage</li>
            <li><strong>Marketing:</strong> Used for relevant ads (with consent)</li>
        </ul>

        <h2>Managing Cookies</h2>
        <p>You can control or delete cookies via browser settings. Blocking essential cookies may affect functionality.</p>

        <h2>Compliance</h2>
        <p>We comply with: GDPR, CCPA/CPRA, DMA, LGPD, POPIA, and Google Consent Mode v2.</p>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
