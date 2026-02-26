<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing - CreditSoft</title>
    <meta name="description" content="Simple, transparent pricing for credit repair software. Starter $49/mo, Professional $99/mo, Enterprise $199/mo.">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://www.creditsoft.app/pricing">
    
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://www.creditsoft.app/pricing">
    <meta property="og:title" content="Pricing - CreditSoft">
    <meta property="og:description" content="Simple, transparent pricing. No hidden fees.">
    
    <meta name="twitter:card" content="summary">
    <meta name="twitter:url" content="https://www.creditsoft.app/pricing">
    <meta name="twitter:title" content="Pricing - CreditSoft">
    <meta name="twitter:description" content="Simple, transparent pricing. No hidden fees.">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-9QJTYCN2FZ"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-9QJTYCN2FZ');
    </script>
    <style>
        :root { --primary: #2563eb; --primary-dark: #1d4ed8; --success: #10b981; --dark: #0f172a; --light: #f8fafc; --gray: #64748b; --border: #e2e8f0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--light); color: var(--dark); line-height: 1.6; }
        
        .hero { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #2563eb 100%); color: white; padding: 140px 20px 60px; text-align: center; }
        .hero h1 { font-size: 42px; font-weight: 800; margin-bottom: 12px; }
        .hero p { font-size: 18px; opacity: 0.9; }
        
        .nav { position: absolute; top: 0; left: 0; right: 0; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; z-index: 100; }
        .nav-logo img { height: 70px; }
        .nav-links { display: flex; gap: 28px; align-items: center; }
        .nav-links a { color: white; text-decoration: none; font-weight: 500; font-size: 15px; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 60px 20px; }
        
        .pricing-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; max-width: 1000px; margin: 0 auto; }
        .pricing-card { background: white; border: 2px solid var(--border); border-radius: 16px; padding: 32px; text-align: center; transition: transform 0.2s, border-color 0.2s; }
        .pricing-card:hover { transform: translateY(-4px); border-color: var(--primary); }
        .pricing-card.featured { border-color: var(--primary); background: linear-gradient(135deg, rgba(37,99,235,0.05), rgba(37,99,235,0.1)); }
        .pricing-card h3 { font-size: 24px; margin-bottom: 8px; }
        .pricing-card .price { font-size: 48px; font-weight: 800; color: var(--dark); margin-bottom: 8px; }
        .pricing-card .price span { font-size: 16px; font-weight: 400; color: var(--gray); }
        .pricing-card .desc { color: var(--gray); margin-bottom: 24px; }
        .pricing-features { list-style: none; text-align: left; margin-bottom: 24px; }
        .pricing-features li { padding: 10px 0; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid var(--border); }
        .pricing-features li:last-child { border-bottom: none; }
        .pricing-features li::before { content: '✓'; color: var(--success); font-weight: bold; }
        
        .btn { padding: 14px 28px; border-radius: 10px; font-size: 16px; font-weight: 600; text-decoration: none; transition: all 0.2s; display: inline-block; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        
        .faq { max-width: 800px; margin: 60px auto 0; }
        .faq h2 { text-align: center; margin-bottom: 32px; }
        .faq-item { background: white; padding: 20px; border-radius: 12px; margin-bottom: 16px; }
        .faq-item h4 { margin-bottom: 8px; }
        .faq-item p { color: var(--gray); font-size: 14px; }
        
        .footer { background: #0a0f1a; color: var(--gray); padding: 40px 0; text-align: center; font-size: 14px; }
        .footer a { color: var(--primary); text-decoration: none; }
        
        @media (max-width: 768px) { .nav-links { display: none; } }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-logo"><a href="/"><img src="/assets/images/CreditSoft.png" alt="CreditSoft"></a></div>
        <div class="nav-links">
            <a href="/#features">Features</a>
            <a href="/pricing">Pricing</a>
            <a href="/about">About</a>
            <a href="/security">Security</a>
            <a href="/legal">Legal</a>
            <a href="/#waitlist" style="background:var(--primary);padding:10px 20px;border-radius:8px;">Get Early Access</a>
        </div>
    </nav>
    
    <section class="hero">
        <h1>Simple, Transparent Pricing</h1>
        <p>No hidden fees. No surprises. Just powerful credit repair software.</p>
    </section>
    
    <div class="container">
        <div class="pricing-grid">
            <div class="pricing-card">
                <h3>Starter</h3>
                <div class="price">$49<span>/mo</span></div>
                <p class="desc">Perfect for new credit repair pros</p>
                <ul class="pricing-features">
                    <li>Up to 25 clients</li>
                    <li>Metro2 error detection</li>
                    <li>Basic dispute templates</li>
                    <li>Email support</li>
                    <li>Monthly reports</li>
                </ul>
                <a href="/subscribe?plan=starter" class="btn btn-primary">Start Free Trial</a>
            </div>
            
            <div class="pricing-card featured">
                <h3>Professional</h3>
                <div class="price">$99<span>/mo</span></div>
                <p class="desc">Most popular choice</p>
                <ul class="pricing-features">
                    <li>Unlimited clients</li>
                    <li>50-state CRO rules</li>
                    <li>AI dispute variations</li>
                    <li>Client portal</li>
                    <li>Priority support</li>
                </ul>
                <a href="/subscribe?plan=professional" class="btn btn-primary">Start Free Trial</a>
            </div>
            
            <div class="pricing-card">
                <h3>Enterprise</h3>
                <div class="price">$199<span>/mo</span></div>
                <p class="desc">For agencies & teams</p>
                <ul class="pricing-features">
                    <li>Everything in Professional</li>
                    <li>Multi-user access</li>
                    <li>White-label</li>
                    <li>API access</li>
                    <li>Dedicated support</li>
                </ul>
                <a href="/subscribe?plan=enterprise" class="btn btn-primary">Contact Sales</a>
            </div>
        </div>
        
        <div class="faq">
            <h2>Frequently Asked Questions</h2>
            
            <div class="faq-item">
                <h4>Is there a free trial?</h4>
                <p>Yes! All plans come with a 14-day free trial. No credit card required to start.</p>
            </div>
            
            <div class="faq-item">
                <h4>Can I change plans later?</h4>
                <p>Absolutely. Upgrade or downgrade your plan at any time from your account settings.</p>
            </div>
            
            <div class="faq-item">
                <h4>What payment methods do you accept?</h4>
                <p>We accept all major credit cards through our secure payment processor.</p>
            </div>
            
            <div class="faq-item">
                <h4>Is my data secure?</h4>
                <p>Yes. Your data stays on YOUR server. We never host your client data on our infrastructure.</p>
            </div>
        </div>
    </div>
    
    <footer class="footer">
        <p>© 2026 CreditSoft. All rights reserved. · <a href="/terms">Terms</a> · <a href="/privacy">Privacy</a> · <a href="https://www.matthewxmurphy.com">Matthew Murphy</a></p>
    </footer>
</body>
</html>
