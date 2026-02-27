<?= $this->extend('layout/storefront') ?>

<?= $this->section('content') ?>

<style>
.activate-form{max-width:500px;margin:0 auto;background:white;padding:40px;border-radius:16px}
.activate-form input{width:100%;padding:16px;border:2px solid var(--border);border-radius:10px;font-size:16px;margin-bottom:16px}
.activate-form .btn{width:100%}
.result{padding:20px;border-radius:12px;margin-top:20px}
.result.success{background:#d1fae5;color:#065f46}
.result.error{background:#fee2e2;color:#991b1b}
.result.warning{background:#fef3c7;color:#92400e}
</style>

<div class="container" style="padding: 40px 20px;">
    <div class="activate-form">
        <h2 style="text-align:center;margin-bottom:24px;">Activate CreditSoft</h2>
        <p style="color:var(--gray);margin-bottom:24px;text-align:center;">Enter your license key to activate your account.</p>
        
        <input type="text" id="licenseKey" placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" style="text-transform:uppercase;font-family:monospace;">
        <button class="btn btn-primary" onclick="activateLicense()">Activate License</button>
        
        <div id="result"></div>
        
        <p style="margin-top:24px;color:var(--gray);font-size:14px;text-align:center;">
            Your license key was sent to your email when you purchased.
        </p>
    </div>
</div>

<script>
async function activateLicense() {
    const key = document.getElementById('licenseKey').value.replace(/[-\s]/g, '').toUpperCase();
    const result = document.getElementById('result');
    
    if (key.length < 20) {
        result.innerHTML = '<div class="result error">Please enter a valid license key</div>';
        return;
    }
    
    result.innerHTML = '<div class="result">Activating...</div>';
    
    try {
        const res = await fetch('/api/license.php?action=validate&key=' + key);
        const data = await res.json();
        
        if (data.valid) {
            localStorage.setItem('license_key', key);
            localStorage.setItem('license_plan', data.plan);
            localStorage.setItem('license_expires', data.expires_at);
            
            result.innerHTML = '<div class="result success">' +
                '<strong>✓ License Activated!</strong><br>' +
                'Plan: ' + data.plan + '<br>' +
                'Expires: ' + new Date(data.expires_at).toLocaleDateString() +
                (data.in_grace_period ? '<br><strong>⚠️ In grace period - ' + data.grace_days_remaining + ' days left</strong>' : '') +
                '</div>';
            
            setTimeout(() => {
                window.location.href = '/client-portal';
            }, 2000);
        } else {
            result.innerHTML = '<div class="result error"><strong>✗ Activation Failed</strong><br>' + (data.error || 'Invalid or expired license') + '</div>';
        }
    } catch (e) {
        result.innerHTML = '<div class="result error">Connection error. Please try again.</div>';
    }
}

document.getElementById('licenseKey').addEventListener('input', function(e) {
    let val = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    let formatted = '';
    for (let i = 0; i < val.length && i < 20; i++) {
        if (i > 0 && i % 4 === 0) formatted += '-';
        formatted += val[i];
    }
    e.target.value = formatted;
});
</script>

<?= $this->endSection() ?>
