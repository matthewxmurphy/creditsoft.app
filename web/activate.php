<?php
$page_title = 'Activate License';
$page_description = 'Activate your CreditSoft license key';
$page_hero = true;
$hero_title = 'Activate CreditSoft';
$hero_subtitle = 'Enter your license key to get started';
?>
<?php include 'header.php'; ?>

<div class="container section">
    <div class="card" style="max-width: 500px; margin: 0 auto;">
        <p class="text-gray text-center mb-3">Enter your license key to activate your account.</p>
        
        <input type="text" id="licenseKey" placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" class="mb-2" style="text-transform: uppercase; font-family: monospace;">
        <button class="btn btn-primary" onclick="activateLicense()" style="width: 100%;">Activate License</button>
        
        <div id="result"></div>
        
        <p class="text-gray text-sm text-center mt-3">
            Your license key was sent to your email when you purchased.
        </p>
    </div>
</div>

<script>
async function activateLicense() {
    const key = document.getElementById('licenseKey').value.replace(/[-\s]/g, '').toUpperCase();
    const result = document.getElementById('result');
    
    if (key.length < 20) {
        result.innerHTML = '<div class="alert alert-danger mt-3">Please enter a valid license key</div>';
        return;
    }
    
    result.innerHTML = '<div class="alert alert-warning mt-3">Activating...</div>';
    
    try {
        const res = await fetch('/api/license.php?action=validate&key=' + key);
        const data = await res.json();
        
        if (data.valid) {
            localStorage.setItem('license_key', key);
            localStorage.setItem('license_plan', data.plan);
            localStorage.setItem('license_expires', data.expires_at);
            
            result.innerHTML = '<div class="alert alert-success mt-3">' +
                '<strong>✓ License Activated!</strong><br>' +
                'Plan: ' + data.plan + '<br>' +
                'Expires: ' + new Date(data.expires_at).toLocaleDateString() +
                (data.in_grace_period ? '<br><strong>⚠️ In grace period - ' + data.grace_days_remaining + ' days left</strong>' : '') +
                '</div>';
            
            setTimeout(() => {
                window.location.href = '/client-portal';
            }, 2000);
        } else {
            result.innerHTML = '<div class="alert alert-danger mt-3"><strong>✗ Activation Failed</strong><br>' + (data.error || 'Invalid or expired license') + '</div>';
        }
    } catch (e) {
        result.innerHTML = '<div class="alert alert-danger mt-3">Connection error. Please try again.</div>';
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

<?php include 'footer.php'; ?>
