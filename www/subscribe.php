<?php
$page_title = "Subscribe - CreditSoft";
$page_description = "Subscribe to CreditSoft credit repair software.";
require __DIR__ . '/header.php';

$plan = $_GET['plan'] ?? 'professional';
$plans = [
    'starter' => ['name' => 'Starter', 'price' => 49, 'features' => ['Up to 25 clients', 'Metro2 error detection', 'Basic dispute templates', 'Email support']],
    'professional' => ['name' => 'Professional', 'price' => 99, 'features' => ['Unlimited clients', '50-state CRO rules', 'AI dispute variations', 'Client portal', 'Priority support']],
    'enterprise' => ['name' => 'Enterprise', 'price' => 199, 'features' => ['Everything in Professional', 'Multi-user access', 'White-label', 'API access', 'Dedicated support']]
];
$selected = $plans[$plan] ?? $plans['professional'];
?>
<style>
    .checkout-hero { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #2563eb 100%); color: white; padding: 120px 20px 60px; text-align: center; }
    .checkout-hero h1 { font-size: 36px; margin-bottom: 12px; }
    .checkout-container { max-width: 900px; margin: 0 auto; padding: 40px 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
    .plan-summary { background: white; padding: 32px; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
    .plan-summary h3 { font-size: 24px; margin-bottom: 16px; }
    .plan-summary .price { font-size: 42px; font-weight: 800; color: var(--primary); margin-bottom: 8px; }
    .plan-summary .price span { font-size: 16px; font-weight: 400; color: var(--gray); }
    .plan-summary ul { list-style: none; margin-top: 20px; }
    .plan-summary li { padding: 8px 0; border-bottom: 1px solid var(--border); }
    .plan-summary li::before { content: '✓'; color: var(--success); margin-right: 10px; }
    .payment-form { background: white; padding: 32px; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
    .payment-form h3 { font-size: 20px; margin-bottom: 20px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
    .form-row.full { grid-template-columns: 1fr; }
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; font-weight: 500; margin-bottom: 6px; font-size: 14px; }
    .form-group input { width: 100%; padding: 12px 14px; border: 1px solid var(--border); border-radius: 8px; font-size: 16px; }
    .form-group input:focus { outline: none; border-color: var(--primary); }
    .form-group .hint { font-size: 12px; color: var(--gray); margin-top: 4px; }
    .btn-submit { background: var(--primary); color: white; width: 100%; padding: 16px; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; }
    .btn-submit:hover { background: var(--primary-dark); }
    .btn-submit:disabled { background: var(--gray); cursor: not-allowed; }
    .error-msg { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 16px; display: none; }
    .success-msg { background: #f0fdf4; border: 1px solid #bbf7d0; color: #16a34a; padding: 20px; border-radius: 10px; text-align: center; display: none; }
    .success-msg h3 { margin-bottom: 8px; }
    .secure-badge { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 16px; font-size: 13px; color: var(--gray); }
    .plan-selector { display: flex; gap: 12px; margin-bottom: 24px; }
    .plan-option { flex: 1; padding: 12px; border: 2px solid var(--border); border-radius: 10px; text-align: center; cursor: pointer; transition: all 0.2s; }
    .plan-option:hover { border-color: var(--primary); }
    .plan-option.active { border-color: var(--primary); background: rgba(37,99,235,0.05); }
    .plan-option .name { font-weight: 600; }
    .plan-option .price { color: var(--primary); font-size: 18px; font-weight: 700; }
    @media (max-width: 768px) { .checkout-container { grid-template-columns: 1fr; } }
</style>

<section class="checkout-hero">
    <h1>Subscribe to CreditSoft</h1>
    <p>Start your free trial today</p>
</section>

<div class="checkout-container">
    <div class="plan-summary">
        <h3><?= $selected['name'] ?> Plan</h3>
        <div class="price">$<?= $selected['price'] ?><span>/month</span></div>
        <ul>
            <?php foreach ($selected['features'] as $feature): ?>
            <li><?= $feature ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <div class="payment-form">
        <h3>Payment Details</h3>
        
        <div class="plan-selector">
            <div class="plan-option <?= $plan == 'starter' ? 'active' : '' ?>" onclick="selectPlan('starter', 49)">
                <div class="name">Starter</div>
                <div class="price">$49/mo</div>
            </div>
            <div class="plan-option <?= $plan == 'professional' ? 'active' : '' ?>" onclick="selectPlan('professional', 99)">
                <div class="name">Professional</div>
                <div class="price">$99/mo</div>
            </div>
            <div class="plan-option <?= $plan == 'enterprise' ? 'active' : '' ?>" onclick="selectPlan('enterprise', 199)">
                <div class="name">Enterprise</div>
                <div class="price">$199/mo</div>
            </div>
        </div>
        
        <div class="error-msg" id="errorMsg"></div>
        
        <div id="paymentForm">
            <div class="form-group">
                <label>Email</label>
                <input type="email" id="email" required placeholder="you@company.com">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Card Number</label>
                    <input type="text" id="cardNumber" placeholder="4111 1111 1111 1111" maxlength="19" required>
                </div>
                <div class="form-group">
                    <label>CVV</label>
                    <input type="text" id="cvv" placeholder="123" maxlength="4" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Expiration</label>
                    <input type="text" id="expDate" placeholder="MM/YY" maxlength="5" required>
                </div>
                <div class="form-group">
                    <label>Cardholder Name</label>
                    <input type="text" id="cardName" placeholder="John Doe" required>
                </div>
            </div>
            
            <button type="button" class="btn-submit" onclick="processPayment()">Subscribe - $99/month</button>
            
            <div class="secure-badge">
                🔒 Secured by Authorize.net
            </div>
        </div>
        
        <div class="success-msg" id="successMsg">
            <h3>Subscription Successful!</h3>
            <p>Welcome to CreditSoft. Check your email for login details.</p>
        </div>
    </div>
</div>

<script>
let currentPlan = '<?= $plan ?>';
let currentPrice = <?= $selected['price'] ?>;

document.getElementById('cardNumber').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    value = value.replace(/(\d{4})/g, '$1 ').trim();
    e.target.value = value;
});

document.getElementById('expDate').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length >= 2) {
        value = value.substring(0,2) + '/' + value.substring(2,4);
    }
    e.target.value = value;
});

function selectPlan(plan, price) {
    currentPlan = plan;
    currentPrice = price;
    document.querySelectorAll('.plan-option').forEach(el => el.classList.remove('active'));
    event.currentTarget.classList.add('active');
    document.querySelector('.btn-submit').textContent = 'Subscribe - $' + price + '/month';
}

function showError(msg) {
    const el = document.getElementById('errorMsg');
    el.textContent = msg;
    el.style.display = 'block';
}

function hideError() {
    document.getElementById('errorMsg').style.display = 'none';
}

async function processPayment() {
    hideError();
    
    const email = document.getElementById('email').value;
    const cardNumber = document.getElementById('cardNumber').value.replace(/\s/g, '');
    const expDate = document.getElementById('expDate').value;
    const cvv = document.getElementById('cvv').value;
    const cardName = document.getElementById('cardName').value;
    
    if (!email || !cardNumber || !expDate || !cvv || !cardName) {
        showError('Please fill in all fields');
        return;
    }
    
    const btn = document.querySelector('.btn-submit');
    btn.disabled = true;
    btn.textContent = 'Processing...';
    
    try {
        const response = await fetch('/api/payment.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'create_subscription',
                plan_id: currentPlan,
                email: email,
                card_number: cardNumber,
                exp_date: expDate,
                cvv: cvv,
                card_name: cardName
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('paymentForm').style.display = 'none';
            document.getElementById('successMsg').style.display = 'block';
            gtag('event', 'purchase', {
                'transaction_id': data.subscription_id,
                'value': currentPrice,
                'currency': 'USD'
            });
        } else {
            showError(data.error || 'Payment failed. Please try again.');
            btn.disabled = false;
            btn.textContent = 'Subscribe - $' + currentPrice + '/month';
        }
    } catch (e) {
        showError('Something went wrong. Please try again.');
        btn.disabled = false;
        btn.textContent = 'Subscribe - $' + currentPrice + '/month';
    }
}
</script>

<?php require __DIR__ . '/footer.php'; ?>
