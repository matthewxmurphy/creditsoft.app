<?php
$page_title = "Legal Information - CreditSoft";
$page_description = "Legal information and compliance details for CreditSoft.";
require __DIR__ . '/header.php';
?>
<style>
    .legal-hero { background: var(--dark); color: white; padding: 120px 20px 60px; text-align: center; }
    .legal-hero h1 { font-size: 42px; margin-bottom: 12px; }
    .legal-content { max-width: 800px; margin: 0 auto; padding: 60px 20px; }
    .legal-content h2 { font-size: 24px; margin: 32px 0 16px; color: var(--dark); }
    .legal-content p, .legal-content li { color: var(--gray); line-height: 1.7; margin-bottom: 12px; }
    .legal-content ul { padding-left: 24px; margin-bottom: 24px; }
    .compliance-badge {
        display: inline-block;
        background: var(--light);
        border: 1px solid var(--border);
        padding: 12px 20px;
        border-radius: 8px;
        margin: 8px;
    }
    .compliance-badge strong { color: var(--dark); }
</style>

<section class="legal-hero">
    <h1>Legal & Compliance</h1>
    <p>Our commitment to legal compliance and data protection</p>
</section>

<div class="legal-content">
    <h2>Compliance Standards</h2>
    <p>CreditSoft is designed to help credit repair professionals maintain compliance with federal and state regulations:</p>
    <div>
        <div class="compliance-badge"><strong>FCRA</strong> - Fair Credit Reporting Act</div>
        <div class="compliance-badge"><strong>FDCPA</strong> - Fair Debt Collection Practices Act</div>
        <div class="compliance-badge"><strong>CRO Act</strong> - Credit Repair Organization Act</div>
        <div class="compliance-badge"><strong>Metro2</strong> - EBRD Data Reporting Standard</div>
    </div>
    
    <h2>State Licensing Support</h2>
    <p>CreditSoft includes built-in tools for compliance with state-specific credit repair laws, including:</p>
    <ul>
        <li>California - CRO Registration requirements</li>
        <li>Georgia - License bond requirements</li>
        <li>New York - Fee restrictions and disclosure requirements</li>
        <li>Texas - Credit Services Organization registration</li>
        <li>Florida - Credit repair business registration</li>
        <li>All 50 states - CRO rule database</li>
    </ul>
    
    <h2>Metro2 Compliance</h2>
    <p>Our platform is built around Metro2 data standards, ensuring:</p>
    <ul>
        <li>Accurate credit report data interpretation</li>
        <li>Proper error code identification (30+ codes)</li>
        <li>Dispute documentation that meets CRAs' requirements</li>
        <li>Audit trail for all credit repair activities</li>
    </ul>
    
    <h2>Data Protection</h2>
    <p><strong>Your Data, Your Server:</strong> Unlike cloud-based competitors, CreditSoft runs on infrastructure you control. Your client data never touches our servers.</p>
    <ul>
        <li>Local data storage options</li>
        <li>Encrypted database connections</li>
        <li>Secure API integrations</li>
        <li>GDPR-compliant data handling</li>
    </ul>
    
    <h2>PII on Customer Websites</h2>
    <p><strong>What This Means:</strong> Some credit repair software providers host their customers' client data on the software company's servers. This creates security and privacy risks:</p>
    <ul>
        <li>Your clients' personal information (SSN, addresses, financial data) lives on someone else's infrastructure</li>
        <li>You have limited control over data security practices</li>
        <li>Data breaches at the software company could expose your client data</li>
        <li>Regulatory liability may extend to you</li>
    </ul>
    <p><strong>CreditSoft Solution:</strong> Your data stays on your server. We provide the software; you control the data.</p>
    
    <h2>Important Disclaimers</h2>
    <p>CreditSoft provides software tools for credit repair workflow management. We do not:</p>
    <ul>
        <li>Guarantee specific credit score improvements</li>
        <li>Provide legal advice</li>
        <li>Represent that all disputes will be successful</li>
        <li>Promise particular results from using our software</li>
    </ul>
    <p>Users should consult with qualified attorneys regarding specific legal questions.</p>
    
    <h2>Business Information</h2>
    <p>
        CreditSoft<br>
        Email: hello@creditsoft.app<br>
        Website: www.creditsoft.app
    </p>
    
    <h2>Contact Legal</h2>
    <p>For legal inquiries, please contact us at hello@creditsoft.app</p>
</div>

<?php require __DIR__ . '/footer.php'; ?>
