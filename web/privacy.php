<?php
$page_title = 'Privacy Policy';
$page_description = 'CreditSoft Privacy Policy - How we protect your data.';
$page_hero = true;
$hero_class = 'hero--legal';
$hero_title = 'Privacy Policy';
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
        <h2>Information We Collect</h2>
        <p>We collect information you provide: name, email, company. Client data you input stays on YOUR server.</p>

        <h2>How We Use Information</h2>
        <p>To provide and improve our services, communicate with you, and comply with legal obligations.</p>

        <h2>Data Storage</h2>
        <p><strong>Important:</strong> Unlike competitors, CreditSoft runs on YOUR server. We never host your client data.</p>

        <h2>Your Rights</h2>
        <ul>
            <li>Access your data</li>
            <li>Request deletion</li>
            <li>Export your data</li>
        </ul>

        <h2>Contact</h2>
        <p>Questions? Email hello@creditsoft.app</p>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
