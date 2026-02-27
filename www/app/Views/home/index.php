<?= $this->extend('layout/storefront') ?>

<?= $this->section('content') ?>

<style>
.hero-content{max-width:800px;margin:0 auto;text-align:center;padding:80px 20px}
.hero h1{font-size:56px;font-weight:800;margin-bottom:16px;line-height:1.1}
.hero .subtitle{font-size:22px;opacity:0.9;margin-bottom:32px}
.cta-group{display:flex;gap:16px;justify-content:center;flex-wrap:wrap}
.btn-outline{background:transparent;border:2px solid white;color:white;padding:14px 28px;border-radius:10px;font-size:16px;font-weight:600;text-decoration:none;display:inline-block;transition:all 0.2s}
.btn-outline:hover{background:white;color:var(--primary)}
.features{padding:80px 20px}
.features h2{text-align:center;font-size:36px;margin-bottom:48px}
.feature-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:32px;max-width:1000px;margin:0 auto}
.feature-card{background:white;padding:32px;border-radius:16px;border:1px solid var(--border)}
.feature-card h3{font-size:20px;margin-bottom:12px}
.feature-card p{color:var(--gray)}
.waitlist{background:linear-gradient(135deg,#0f172a,#1e3a5f);color:white;padding:80px 20px;text-align:center}
.waitlist h2{font-size:36px;margin-bottom:16px}
.waitlist p{font-size:18px;opacity:0.9;margin-bottom:32px;max-width:600px;margin-left:auto;margin-right:auto}
.waitlist-form{max-width:400px;margin:0 auto;display:flex;gap:12px}
.waitlist-form input{flex:1;padding:16px;border-radius:10px;border:none;font-size:16px}
.toast{position:fixed;bottom:20px;right:20px;background:var(--success);color:white;padding:16px 24px;border-radius:10px;font-weight:600;transform:translateY(100px);transition:transform 0.3s;z-index:1000}
.toast.show{transform:translateY(0)}
</style>

<section class="hero">
    <div class="hero-content">
        <h1>Stop Relying on AI Letters</h1>
        <p class="subtitle">Real results come from Metro2 compliance and proper dispute workflows. CreditSoft understands credit repair.</p>
        <div class="cta-group">
            <a href="#waitlist" class="btn btn-primary" style="font-size:18px;padding:16px 32px;">Get Early Access</a>
            <a href="/quiz" class="btn btn-outline">Take the Quiz</a>
        </div>
    </div>
</section>

<section class="features" id="features">
    <h2>Why CreditSoft?</h2>
    <div class="feature-grid">
        <div class="feature-card">
            <h3>🎯 Metro2-First</h3>
            <p>We don't just generate letters. We understand the underlying data format used by all credit bureaus.</p>
        </div>
        <div class="feature-card">
            <h3>📋 50-State Rules</h3>
            <p>Built-in compliance for Credit Repair Organization (CRO) laws in every state. Never accidentally break the law.</p>
        </div>
        <div class="feature-card">
            <h3>🤖 Smart Disputes</h3>
            <p>Our AI generates variation in your dispute letters so creditors can't pattern-match and ignore them.</p>
        </div>
        <div class="feature-card">
            <h3>🔒 Client Portal</h3>
            <p>Give clients access to track their disputes and see progress. White-label ready for your brand.</p>
        </div>
        <div class="feature-card">
            <h3>📊 Reporting</h3>
            <p>Metro2 error detection. Know which items are truly disputable before you waste time.</p>
        </div>
        <div class="feature-card">
            <h3>💰 No Per-Seat Fees</h3>
            <p>Unlimited users. Unlimited clients. One flat price. Your staff, your way.</p>
        </div>
    </div>
</section>

<section class="waitlist" id="waitlist">
    <h2>Get Early Access</h2>
    <p>Be among the first to try CreditSoft. We're offering lifetime discounts to early adopters.</p>
    <div class="waitlist-form">
        <input type="email" id="waitlistEmail" placeholder="Enter your email">
        <button class="btn btn-primary" onclick="joinWaitlist()">Join</button>
    </div>
</section>

<div class="toast" id="toast">Thanks! We'll be in touch.</div>

<script>
function joinWaitlist() {
    const email = document.getElementById('waitlistEmail').value;
    if (!email) return;
    
    fetch('/api/lead', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({email: email, source: 'waitlist'})
    }).then(() => {
        document.getElementById('toast').classList.add('show');
        document.getElementById('waitlistEmail').value = '';
        setTimeout(() => document.getElementById('toast').classList.remove('show'), 3000);
    });
}
</script>

<?= $this->endSection() ?>
