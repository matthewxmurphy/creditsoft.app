<?php
$page_title = '30+ Metro2 Error Codes Covered';
$page_description = 'AI-driven Metro2 violation detection for credit repair teams, covering account mismatches, inaccurate late payments, invalid collections, duplicate accounts, balances, statuses, and bureau reporting patterns.';
$page_hero = true;
$hero_class = 'hero--left';
$hero_title = '30+ Metro2 Error Codes Covered';
$hero_subtitle = 'AI-driven detection for Metro2 violations — identifying account mismatches, inaccurate late payments, invalid collections, and bureau-level reporting conflicts with precision.';
require __DIR__ . '/header.php';
?>
<style>
    .hero h1 { font-size: 42px; font-weight: 800; margin-bottom: 12px; }
    .container { max-width: 1100px; margin: 0 auto; padding: 40px 20px; }
    .intro { background: white; padding: 32px; border-radius: 16px; margin-bottom: 24px; }
    .intro h2 { font-size: 24px; margin-bottom: 16px; }
    .intro p { color: var(--gray); }
    .search-box { width: 100%; padding: 14px 20px; border: 2px solid var(--border); border-radius: 12px; font-size: 16px; margin-bottom: 24px; }
    .search-box:focus { outline: none; border-color: var(--primary); }
    .codes-grid { display: grid; grid-template-columns: repeat(auto-fill,minmax(320px,1fr)); gap: 16px; }
    .code-card { background: white; padding: 20px; border-radius: 12px; border-left: 4px solid var(--primary); }
    .code-card h3 { font-size: 16px; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
    .code-card .code { background: var(--light); padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; }
    .code-card p { font-size: 14px; color: var(--gray); }
    .code-card.high { border-left-color: var(--success); }
    .stats { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 32px; }
    .stat { background: white; padding: 20px; border-radius: 12px; text-align: center; }
    .stat-num { font-size: 32px; font-weight: 700; color: var(--primary); }
    .stat-label { font-size: 13px; color: var(--gray); }
    @media(max-width:768px) { .stats { grid-template-columns: repeat(2,1fr); } }
</style>

<div class="container">
    <div class="stats">
        <div class="stat"><div class="stat-num">30+</div><div class="stat-label">Codes Covered</div></div>
        <div class="stat"><div class="stat-num">3</div><div class="stat-label">Bureaus</div></div>
        <div class="stat"><div class="stat-num">FCRA</div><div class="stat-label">Claim-aware Review</div></div>
        <div class="stat"><div class="stat-num">AI</div><div class="stat-label">Driven Detection</div></div>
    </div>

    <div class="intro">
        <h2>AI-driven Metro2 review, built for factual disputes</h2>
        <p>Metro2 is the data reporting format used by all major credit bureaus (Equifax, Experian, TransUnion). CreditSoft uses AI-driven review to surface violation patterns, account mismatches, inaccurate late payments, invalid collections, duplicate accounts, and balance or status conflicts so your team can review the facts before drafting.</p>
    </div>

    <div class="intro">
        <h2>Turn report problems into better intake</h2>
        <p>If you want a consumer-facing intake before the consultation, use our <a href="/lawsuit-test">FCRA / FDCPA issue check</a> to screen for account mismatches, invalid collection claims, inaccurate late-payment history, and reinsertion-style reporting behavior before the file reaches your review queue.</p>
    </div>

    <input type="text" class="search-box" placeholder="Search covered Metro2 signals..." id="searchBox" onkeyup="filterCodes()">

    <div class="codes-grid" id="codesGrid">
        <div class="code-card"><h3><span class="code">A</span> Account Number</h3><p>AI-driven review flags account-number mismatches that do not line up with the consumer's records or bureau trail.</p></div>
        <div class="code-card high"><h3><span class="code">B</span> Balance</h3><p>Balance conflicts are surfaced when reported amounts fall outside expected tolerances or supporting records.</p></div>
        <div class="code-card"><h3><span class="code">C</span> Collection</h3><p>Invalid collection signals are reviewed against payment, settlement, validation, and ownership history.</p></div>
        <div class="code-card"><h3><span class="code">D</span> Date Opened</h3><p>Date-opened conflicts are identified when the reported timeline is inaccurate, missing, or inconsistent.</p></div>
        <div class="code-card high"><h3><span class="code">F</span> Future Date</h3><p>Future-dated reporting is treated as a high-priority Metro2 warning signal for factual review.</p></div>
        <div class="code-card"><h3><span class="code">I</span> Inaccurate Identity</h3><p>Identity mismatches flag SSN, name, address, or ownership details that do not match the consumer.</p></div>
        <div class="code-card high"><h3><span class="code">K</span> Keyer</h3><p>Manual-entry signals help identify wrongly keyed account, payment, date, or identity information.</p></div>
        <div class="code-card"><h3><span class="code">L</span> Late Payment</h3><p>Inaccurate late-payment history is flagged when the timeline does not match statements or other records.</p></div>
        <div class="code-card"><h3><span class="code">M</span> Multiple Employer</h3><p>Employment mismatches are surfaced when bureau records conflict with the consumer's actual profile.</p></div>
        <div class="code-card high"><h3><span class="code">P</span> Payment History</h3><p>Payment-history conflicts are identified across statements, bureau data, and supporting records.</p></div>
        <div class="code-card"><h3><span class="code">R</span> Reaging</h3><p>Reaging signals flag accounts where dates or delinquency history may have been moved without support.</p></div>
        <div class="code-card high"><h3><span class="code">S</span> Status</h3><p>Status conflicts surface when an account's reported state does not match the broader bureau or statement trail.</p></div>
        <div class="code-card"><h3><span class="code">T</span> Term/Expiration</h3><p>Term and expiration issues are covered when loan terms, dates, or payment windows are incorrectly reported.</p></div>
        <div class="code-card"><h3><span class="code">U</span> Unverified</h3><p>Unverified reporting is flagged when the information cannot be supported by the available record trail.</p></div>
        <div class="code-card high"><h3><span class="code">X</span> Duplicate</h3><p>Duplicate-account detection identifies repeated tradelines and overlapping collection records with precision.</p></div>
        <div class="code-card"><h3><span class="code">Z</span> Zero Balance</h3><p>Zero-balance conflicts are covered when a paid or closed account still reports an active balance.</p></div>
        <div class="code-card"><h3><span class="code">01</span> No Consumer Statement</h3><p>Statement filed but not showing.</p></div>
        <div class="code-card"><h3><span class="code">02</span> Fraud/Identity Theft</h3><p>Account opened fraudulently.</p></div>
        <div class="code-card high"><h3><span class="code">05</span> Bankruptcy</h3><p>Bankruptcy discharged but still showing.</p></div>
        <div class="code-card"><h3><span class="code">11</span> Credit Limit</h3><p>Credit limit higher than valid amount.</p></div>
        <div class="code-card"><h3><span class="code">13</span> Monthly Payment</h3><p>Payment doesn't match statement.</p></div>
        <div class="code-card"><h3><span class="code">17</span> New Address</h3><p>Address different from bureau records.</p></div>
        <div class="code-card"><h3><span class="code">19</span> Prior Delinquency</h3><p>Delinquency previously reported in error.</p></div>
        <div class="code-card"><h3><span class="code">21</span> Court Judgment</h3><p>Judgment vacated or paid but still showing.</p></div>
        <div class="code-card high"><h3><span class="code">63</span> Account Type</h3><p>Revolving reported as installment (wrong type).</p></div>
        <div class="code-card"><h3><span class="code">71</span> ECOA Code</h3><p>Wrong consumer designation on account.</p></div>
        <div class="code-card"><h3><span class="code">77</span> Passenger Vehicle</h3><p>Wrong vehicle type on auto loan.</p></div>
        <div class="code-card"><h3><span class="code">93</span> Mortgage Info</h3><p>Mortgage terms incorrectly reported.</p></div>
        <div class="code-card"><h3><span class="code">95</span> Loan Detail</h3><p>Original loan amount incorrect.</p></div>
        <div class="code-card"><h3><span class="code">97</span> Satisfied Balance</h3><p>Account shows balance after being paid.</p></div>
    </div>
</div>

<script>
function filterCodes() {
    const search = document.getElementById('searchBox').value.toLowerCase();
    document.querySelectorAll('.code-card').forEach(card => {
        card.style.display = card.textContent.toLowerCase().includes(search) ? 'block' : 'none';
    });
}
</script>

<?php require __DIR__ . '/footer.php'; ?>
