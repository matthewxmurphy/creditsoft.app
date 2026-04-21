<?php
$page_title = 'Security';
$page_description = 'CreditSoft security practices. Your data stays on YOUR server. We never host client information.';
$page_hero = true;
$hero_title = '🔒 Security & Privacy';
$hero_subtitle = "Your data stays on YOUR server. That's the CreditSoft difference.";
require __DIR__ . '/header.php';
?>
<style>
    .hero h1 { font-size: 42px; font-weight: 800; margin-bottom: 12px; }
    .hero p { font-size: 18px; opacity: 0.9; }
    .container-narrow { max-width: 800px; margin: 0 auto; padding: 60px 20px; }
    .security-section { background: white; padding: 40px; border-radius: 16px; margin-bottom: 24px; }
    .security-section h2 { font-size: 24px; margin-bottom: 16px; display: flex; align-items: center; gap: 12px; }
    .security-section h2 .icon { font-size: 28px; }
    .security-section p { color: var(--gray); margin-bottom: 16px; }
    .security-section ul { color: var(--gray); padding-left: 24px; }
    .security-section li { margin-bottom: 8px; }
    .highlight-box { background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05)); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 12px; padding: 24px; margin-bottom: 24px; }
    .highlight-box h3 { color: var(--success); margin-bottom: 8px; }
    .highlight-box p { margin-bottom: 0; }
    .compliance-badges { display: flex; gap: 16px; flex-wrap: wrap; justify-content: center; margin: 40px 0; }
    .badge { background: white; padding: 12px 24px; border-radius: 8px; font-weight: 600; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
</style>

<div class="container-narrow">
    <div class="highlight-box">
        <h3>🏆 The CreditSoft Advantage</h3>
        <p>Unlike competitors (CDM, CRC), we don't host your client data on our servers. Your sensitive information never leaves your infrastructure. You control the security.</p>
    </div>

    <div class="security-section">
        <h2><span class="icon">🛡️</span> Your Server, Your Data</h2>
        <p>CreditSoft runs on infrastructure you control. We provide the software; you provide the hosting. This means:</p>
        <ul>
            <li>Your clients' PII never touches our systems</li>
            <li>You choose your own security measures</li>
            <li>No third-party data breaches can affect your clients</li>
            <li>Full compliance with data residency requirements</li>
        </ul>
    </div>

    <div class="security-section">
        <h2><span class="icon">🔐</span> Encryption</h2>
        <p>We support industry-standard encryption for all data transmissions:</p>
        <ul>
            <li><strong>TLS 1.3</strong> for all web traffic</li>
            <li><strong>AES-256</strong> database encryption (when enabled on your server)</li>
            <li>Secure API connections with token-based authentication</li>
        </ul>
    </div>

    <div class="security-section">
        <h2><span class="icon">✅</span> Access Controls</h2>
        <p>Built-in security features include:</p>
        <ul>
            <li>Role-based access control (admin, user, viewer)</li>
            <li>Two-factor authentication support</li>
            <li>Session management and timeout controls</li>
            <li>Audit logging for all account activity</li>
        </ul>
    </div>

    <div class="security-section">
        <h2><span class="icon">📋</span> Compliance</h2>
        <p>CreditSoft helps you maintain compliance with:</p>
        <ul>
            <li><strong>FCRA</strong> - Fair Credit Reporting Act</li>
            <li><strong>FDCPA</strong> - Fair Debt Collection Practices Act</li>
            <li><strong>CRO Act</strong> - Credit Repair Organization Act</li>
            <li><strong>State-specific</strong> credit repair laws (all 50 states)</li>
            <li><strong>GDPR</strong> - General Data Protection Regulation</li>
        </ul>
    </div>

    <div class="security-section">
        <h2><span class="icon">☁️</span> Cloud vs. Self-Hosted</h2>
        <p>Choose your deployment model:</p>
        <ul>
            <li><strong>Self-hosted:</strong> Run on your own server (recommended)</li>
            <li><strong>Net30Hosting:</strong> Our recommended hosting partner</li>
        </ul>
        <p class="mt-2">We never mandatory cloud-host your data. Your clients' information stays where YOU decide.</p>
    </div>

    <div class="compliance-badges">
        <div class="badge">🔒 FCRA Compliant</div>
        <div class="badge">🛡️ FDCPA Ready</div>
        <div class="badge">📋 GDPR Support</div>
        <div class="badge">🏛️ 50-State CRO</div>
    </div>

    <div class="security-section">
        <h2>Report a Security Issue</h2>
        <p>If you discover a security vulnerability, please contact us immediately at <strong>security@creditsoft.app</strong>. We appreciate responsible disclosure.</p>
    </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
