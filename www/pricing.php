<?php
$page_title = 'Pricing';
$page_description = 'Credit repair software pricing. Unlimited users. No per-seat fees.';
$page_hero = true;
$hero_title = 'Simple, Transparent Pricing';
$hero_subtitle = 'No hidden fees. No surprises. Just powerful credit repair software.';
?>
<?php include 'header.php'; ?>

<style>
.billing-toggle { display: flex; justify-content: center; gap: 8px; margin-bottom: 40px; }
.billing-btn { padding: 12px 24px; border: 2px solid var(--border); background: white; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; }
.billing-btn.active { border-color: var(--primary); background: var(--primary); color: white; }
.pricing-card .save-badge { background: var(--success); color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; margin-bottom: 8px; }
.pricing-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; max-width: 1000px; margin: 0 auto; }
.pricing-card { background: white; border: 2px solid var(--border); border-radius: 16px; padding: 32px; text-align: center; transition: transform 0.2s, border-color 0.2s; }
.pricing-card:hover { transform: translateY(-4px); border-color: var(--primary); }
.pricing-card.featured { border-color: var(--primary); background: linear-gradient(135deg, rgba(37,99,235,0.05), rgba(37,99,235,0.1)); }
.pricing-card h3 { font-size: 24px; margin-bottom: 8px; }
.pricing-card .desc { color: var(--gray); margin-bottom: 24px; }
.pricing-features { list-style: none; text-align: left; margin-bottom: 24px; }
.pricing-features li { padding: 10px 0; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid var(--border); }
.pricing-features li:last-child { border-bottom: none; }
.pricing-features li::before { content: '✓'; color: var(--success); font-weight: bold; }
.faq { max-width: 800px; margin: 60px auto 0; }
.faq h2 { text-align: center; margin-bottom: 32px; }
.faq-item { background: white; padding: 20px; border-radius: 12px; margin-bottom: 16px; }
.faq-item h4 { margin-bottom: 8px; }
.faq-item p { color: var(--gray); font-size: 14px; }
</style>

<div class="container" style="padding: 40px 20px;">
    <div class="billing-toggle">
        <button class="billing-btn active" onclick="setBilling('monthly')">Monthly</button>
        <button class="billing-btn" onclick="setBilling('yearly')">Yearly <span style="color:var(--success)">Save 20%</span></button>
        <button class="billing-btn" onclick="setBilling('lifetime')">Lifetime</button>
    </div>
    
    <div class="pricing-grid" id="pricingGrid"></div>
    
    <script>
    const plans = {
        starter: {name:'Starter',features:['Up to 25 clients','Metro2 error detection','Basic dispute templates','Email support','Monthly reports']},
        professional: {name:'Professional',features:['Unlimited clients','50-state CRO rules','AI dispute variations','Client portal','Priority support','Chrome Extension']},
        enterprise: {name:'Enterprise',features:['Everything in Professional','White-label','API access','Dedicated support']}
    };
    
    let billing = 'monthly';
    function setBilling(b) {
        billing = b;
        document.querySelectorAll('.billing-btn').forEach((btn,i) => {
            btn.classList.toggle('active', ['monthly','yearly','lifetime'][i] === b);
        });
        renderPricing();
    }
    
    function renderPricing() {
        let html = '';
        for (const [key, plan] of Object.entries(plans)) {
            const featured = key === 'professional' ? ' featured' : '';
            html += '<div class="pricing-card' + featured + '">';
            if (billing === 'yearly') html += '<span class="save-badge">Save 20%</span>';
            if (billing === 'lifetime') html += '<span class="save-badge">Best Value</span>';
            html += '<h3>' + plan.name + '</h3>';
            html += '<p class="desc">' + (billing === 'lifetime' ? 'Pay once, own forever' : (billing === 'yearly' ? 'Billed annually' : 'Unlimited users • No per-seat fees')) + '</p>';
            html += '<ul class="pricing-features">';
            for (const f of plan.features) html += '<li>' + f + '</li>';
            html += '</ul>';
            const link = billing === 'lifetime' ? '/subscribe?plan=' + key + '&billing=lifetime' : '/subscribe?plan=' + key;
            const btnText = key === 'enterprise' ? 'Contact Sales' : 'Start Free Trial';
            html += '<a href="' + link + '" class="btn btn-primary">' + btnText + '</a></div>';
        }
        document.getElementById('pricingGrid').innerHTML = html;
    }
    renderPricing();
    </script>
    
    <div class="faq">
        <h2>Frequently Asked Questions</h2>
        <div class="faq-item">
            <h4>Is there a free trial?</h4>
            <p>Yes! All plans come with a 14-day free trial. No credit card required.</p>
        </div>
        <div class="faq-item">
            <h4>Can I change plans later?</h4>
            <p>Absolutely. Upgrade or downgrade anytime.</p>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
