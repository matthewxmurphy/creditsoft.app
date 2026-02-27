<?= $this->extend('layout/storefront') ?>

<?= $this->section('content') ?>

<style>
.two-layer{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px}
.layer-card{background:white;padding:32px;border-radius:16px}
.layer-card h3{font-size:20px;margin-bottom:16px;display:flex;align-items:center;gap:12px}
.layer-card p{color:var(--gray);margin-bottom:16px}
.layer-card ul{padding-left:20px;color:var(--gray)}
.layer-card li{margin-bottom:8px}
.vpn-section{background:white;padding:32px;border-radius:16px;margin-bottom:24px}
.vpn-section h3{font-size:20px;margin-bottom:16px}
.vpn-section ul{padding-left:20px;color:var(--gray)}
.vpn-section li{margin-bottom:8px}
.how-it-works{background:white;padding:32px;border-radius:16px}
.step{display:flex;gap:16px;margin-bottom:24px}
.step-num{width:40px;height:40px;background:var(--primary);color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0}
.step h4{font-size:18px;margin-bottom:4px}
.license-warning{background:#fee2e2;border:2px solid #ef4444;padding:20px;border-radius:12px;margin-bottom:24px;text-align:center}
.license-warning h3{color:#991b1b;margin-bottom:8px}
.license-warning a{color:#2563eb;font-weight:600}
@media(max-width:768px){.two-layer{grid-template-columns:1fr}}
</style>

<div class="container">
    <div id="licenseCheck"></div>
    
    <div class="intro" style="background:white;padding:32px;border-radius:16px;margin-bottom:24px;">
        <h2>Two-Layer Client Portal</h2>
        <p>Give your clients visibility into their credit repair progress while keeping your data secure.</p>
    </div>
    
    <div class="two-layer">
        <div class="layer-card">
            <h3>🏠 Layer 1: Intranet</h3>
            <p>Your clients access CreditSoft through your local network. No data leaves your server.</p>
            <ul>
                <li>Encrypted local storage</li>
                <li>Your domain/IP only</li>
                <li>Full client management</li>
                <li>Real-time updates</li>
            </ul>
        </div>
        <div class="layer-card">
            <h3>🌐 Layer 2: Widget</h3>
            <p>Add a client portal widget to your existing website.</p>
            <ul>
                <li>White-label ready</li>
                <li>Client sees only their data</li>
                <li>Progress tracking</li>
                <li>Dispute status updates</li>
            </ul>
        </div>
    </div>
    
    <div class="vpn-section">
        <h3>🔐 Remote Access with VPN</h3>
        <p>Need to access your intranet from another location? Connect via your own VPN (Tailnet, WireGuard, OpenVPN, etc.).</p>
        <ul>
            <li>Your intranet accessible ONLY via VPN</li>
            <li>When remote, connect to VPN first</li>
            <li>Your data never touches public internet unencrypted</li>
        </ul>
    </div>
    
    <div class="how-it-works">
        <h2>How It Works</h2>
        <div class="step">
            <div class="step-num">1</div>
            <div><h4>Run CreditSoft on your computer</h4><p>Install locally. Your data stays on your machine.</p></div>
        </div>
        <div class="step">
            <div class="step-num">2</div>
            <div><h4>Add your clients</h4><p>Import their credit reports and start disputing errors.</p></div>
        </div>
        <div class="step">
            <div class="step-num">3</div>
            <div><h4>Share progress via portal</h4><p>Clients log in to see their dispute status and score changes.</p></div>
        </div>
    </div>
</div>

<script>
async function checkLicense() {
    const key = localStorage.getItem('license_key');
    const checkDiv = document.getElementById('licenseCheck');
    
    if (!key) {
        checkDiv.innerHTML = '<div class="license-warning"><h3>⚠️ License Not Activated</h3><p>Please <a href="/activate">activate your license</a> to access all features.</p></div>';
        return;
    }
    
    try {
        const res = await fetch('/api/license.php?action=validate&key=' + key);
        const data = await res.json();
        
        if (!data.valid) {
            checkDiv.innerHTML = '<div class="license-warning"><h3>⚠️ License Issue</h3><p>' + (data.error || 'Invalid license') + '<br>Please <a href="/activate">reactivate</a> your license.</p></div>';
        } else if (data.in_grace_period) {
            checkDiv.innerHTML = '<div class="license-warning" style="background:#fef3c7;border-color:#f59e0b;"><h3 style="color:#92400e;">⚠️ Grace Period - ' + data.grace_days_remaining + ' days left</h3><p style="color:#92400e;">Your license expired. Please renew to avoid interruption.</p></div>';
        }
    } catch (e) {
        console.log('License check failed');
    }
}

checkLicense();
</script>

<?= $this->endSection() ?>
