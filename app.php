<?php
// Form submission handling
$submitted = false;
$error = '';
$success = '';

// Turnstile validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cf-turnstile-response'])) {
    $turnstile_secret = getenv('TURNSTILE_SECRET') ?: 'YOUR_TURNSTILE_SECRET_KEY';
    $turnstile_response = $_POST['cf-turnstile-response'];
    
    // Verify Turnstile
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'secret' => $turnstile_secret,
        'response' => $turnstile_response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $turnstile_result = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    if (!$turnstile_result['success']) {
        $error = 'Please complete the security check';
    } else {
        // Validate email has MX record
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $domain = substr(strrchr($email, '@'), 1);
        
        if (!checkdnsrr($domain, 'MX')) {
            $error = 'Please enter a valid email address with a working mail server';
        } else {
            // Save lead (minimal info: name + email only)
            $name = htmlspecialchars($_POST['name']);
            
            // TODO: Save to database
            // For now, just show success
            $success = "Thanks $name! You're on the list. We'll be in touch soon.";
            $submitted = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CreditSoft - Metro2-First Credit Repair Software</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --success: #10b981;
            --dark: #0f172a;
            --light: #f8fafc;
            --gray: #64748b;
            --border: #e2e8f0;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--light); color: var(--dark); line-height: 1.6; }
        
        .hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #2563eb 100%);
            color: white;
            padding: 80px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.03) 0%, transparent 50%);
            animation: rotate 60s linear infinite;
        }
        @keyframes rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        
        .hero-content { position: relative; max-width: 900px; margin: 0 auto; }
        
        .logo { margin-bottom: 24px; }
        .logo img { height: 60px; }
        
        .badge {
            display: inline-block;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.5);
            color: #34d399;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .hero h1 { font-size: 48px; font-weight: 800; line-height: 1.1; margin-bottom: 20px; }
        .hero h1 span { color: #60a5fa; }
        .hero p { font-size: 20px; opacity: 0.9; max-width: 600px; margin: 0 auto 32px; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        
        .cta-group { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
        .btn {
            padding: 14px 28px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); }
        .btn-outline { background: transparent; color: white; border: 2px solid rgba(255,255,255,0.3); }
        .btn-outline:hover { border-color: white; }
        
        section { padding: 80px 0; }
        .features { background: white; }
        .features h2 { text-align: center; font-size: 36px; margin-bottom: 12px; }
        .features .subtitle { text-align: center; color: var(--gray); margin-bottom: 48px; font-size: 18px; }
        
        .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; }
        .feature-card {
            background: var(--light);
            padding: 28px;
            border-radius: 14px;
            transition: transform 0.2s;
        }
        .feature-card:hover { transform: translateY(-4px); }
        .feature-icon {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, var(--primary), #3b82f6);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }
        .feature-icon svg { width: 26px; height: 26px; color: white; }
        .feature-card h3 { font-size: 18px; margin-bottom: 10px; }
        .feature-card p { color: var(--gray); font-size: 14px; }
        
        .comparison { background: var(--light); }
        .comparison h2 { text-align: center; font-size: 36px; margin-bottom: 12px; }
        .comparison .subtitle { text-align: center; color: var(--gray); margin-bottom: 48px; }
        
        .comparison-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .comparison-table th, .comparison-table td {
            padding: 16px 20px;
            text-align: center;
            border-bottom: 1px solid var(--border);
        }
        .comparison-table th { background: var(--dark); color: white; font-weight: 600; }
        .comparison-table th:first-child { text-align: left; }
        .comparison-table td:first-child { text-align: left; font-weight: 500; }
        .comparison-table tr:last-child td { border-bottom: none; }
        .check { color: var(--success); font-size: 20px; }
        .cross { color: #ef4444; font-size: 20px; }
        .our-feature { background: rgba(37, 99, 235, 0.08); font-weight: 600; }
        
        .metro2-section {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
            color: white;
            padding: 80px 0;
        }
        .metro2-section h2 { font-size: 36px; margin-bottom: 16px; }
        .metro2-section p { opacity: 0.8; font-size: 18px; margin-bottom: 32px; max-width: 600px; }
        
        .metro2-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; }
        .metro2-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            padding: 24px;
            border-radius: 12px;
        }
        .metro2-card h3 { font-size: 18px; margin-bottom: 10px; color: #60a5fa; }
        .metro2-card p { opacity: 0.7; font-size: 14px; }
        
        .waitlist { background: var(--dark); color: white; text-align: center; padding: 80px 20px; }
        .waitlist h2 { font-size: 36px; margin-bottom: 12px; }
        .waitlist .subtitle { opacity: 0.8; margin-bottom: 32px; font-size: 18px; }
        
        .form-box {
            max-width: 440px;
            margin: 0 auto;
            background: rgba(255,255,255,0.05);
            padding: 32px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .form-group { margin-bottom: 18px; text-align: left; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; }
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            background: rgba(255,255,255,0.05);
            color: white;
            font-size: 16px;
        }
        .form-group input:focus { outline: none; border-color: var(--primary); }
        
        .turnstile { margin: 20px 0; display: flex; justify-content: center; }
        
        .btn-submit { background: var(--success); color: white; width: 100%; }
        .btn-submit:hover { background: #059669; }
        
        .form-note { font-size: 13px; opacity: 0.6; margin-top: 16px; }
        
        .success-msg {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid var(--success);
            color: #34d399;
            padding: 20px;
            border-radius: 12px;
            font-size: 18px;
        }
        
        .error-msg {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid #ef4444;
            color: #fca5a5;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        
        .footer { background: #0a0f1a; color: var(--gray); padding: 40px 0; text-align: center; font-size: 14px; }
        .footer a { color: var(--primary); text-decoration: none; }
        
        @media (max-width: 768px) {
            .hero h1 { font-size: 32px; }
            .comparison-table { font-size: 14px; }
            .comparison-table th, .comparison-table td { padding: 12px 8px; }
        }
    </style>
</head>
<body>
    <section class="hero">
        <div class="hero-content">
            <div class="logo">
                <img src="assets/images/CreditSoft.png" alt="CreditSoft" height="60">
            </div>
            <div class="badge">🎯 Metro2-First Credit Repair</div>
            <h1>Credit Repair Software<br><span>Built on Compliance</span></h1>
            <p>Stop relying on "AI letters". Real results come from Metro2 compliance and proper dispute workflows.</p>
            <div class="cta-group">
                <a href="#waitlist" class="btn btn-primary">Get Early Access</a>
                <a href="#comparison" class="btn btn-outline">Compare Features</a>
            </div>
        </div>
    </section>
    
    <section class="features" id="features">
        <div class="container">
            <h2>Why CreditSoft Wins</h2>
            <p class="subtitle">Built different because we understand credit repair</p>
            
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <h3>30+ Metro2 Error Codes</h3>
                    <p>Built-in detection for every Metro2 violation. Account mismatches, late payments that aren't yours, fake collections - we catch them all.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    </div>
                    <h3>50-State CRO Rules</h3>
                    <p>Know your state's requirements. Bond amounts, registration, fee limits - our knowledge base has it all built in.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3>FCRA/FDCPA Compliant</h3>
                    <p>Dispute the right way. Our workflows follow federal law so you stay compliant while getting results.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h3>Smart Disputes</h3>
                    <p>AI-assisted backed by Metro2 compliance. Not just letters - workflows that follow the rules.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <h3>Client Portal</h3>
                    <p>White-labeled widget. Clients see progress without you hosting their sensitive data on public sites.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    </div>
                    <h3>Monthly Comparisons</h3>
                    <p>Track credit score changes over time. Visual reports showing progress and new issues.</p>
                </div>
            </div>
        </div>
    </section>
    
    <section class="comparison" id="comparison">
        <div class="container">
            <h2>How We Compare</h2>
            <p class="subtitle">See why credit repair professionals choose CreditSoft</p>
            
            <table class="comparison-table">
                <thead>
                    <tr>
                        <th>Feature</th>
                        <th>CreditSoft</th>
                        <th>CDM</th>
                        <th>CRC</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Metro2 Error Detection</td>
                        <td class="our-feature">30+ Codes Built-In</td>
                        <td>Limited</td>
                        <td>Basic</td>
                    </tr>
                    <tr>
                        <td>50-State CRO Rules</td>
                        <td class="our-feature">✓ Built In</td>
                        <td>Add-on</td>
                        <td>External</td>
                    </tr>
                    <tr>
                        <td>FCRA/FDCPA Workflows</td>
                        <td class="our-feature">✓ Built In</td>
                        <td>✓</td>
                        <td>✓</td>
                    </tr>
                    <tr>
                        <td>Dispute Letter Vault (AI Variations)</td>
                        <td class="our-feature">✓ Metro2-Backed</td>
                        <td>✓</td>
                        <td>✓</td>
                    </tr>
                    <tr>
                        <td>PII on Customer Websites</td>
                        <td class="our-feature">❌ Never</td>
                        <td>Yes</td>
                        <td>Yes</td>
                    </tr>
                    <tr>
                        <td>Update Server</td>
                        <td class="our-feature">✓ Your Server</td>
                        <td>Their Server</td>
                        <td>Their Server</td>
                    </tr>
                    <tr>
                        <td>Transparent Pricing</td>
                        <td class="our-feature">✓ Real Costs</td>
                        <td>Hidden fees</td>
                        <td>Hidden fees</td>
                    </tr>
                    <tr>
                        <td>License Key Required</td>
                        <td class="our-feature">✓ Yes</td>
                        <td>✓</td>
                        <td>✓</td>
                    </tr>
                    <tr>
                        <td>Monthly Price</td>
                        <td class="our-feature">Coming Soon</td>
                        <td>$107-329</td>
                        <td>$99-299</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
    
    <section class="metro2-section">
        <div class="container">
            <h2>Why Metro2 Wins</h2>
            <p>AI letters are just templates with different words. Metro2 compliance is what actually gets items removed.</p>
            
            <div class="metro2-grid">
                <div class="metro2-card">
                    <h3>📋 Account Number Mismatch</h3>
                    <p>When the reported account number doesn't match consumer records - delete it.</p>
                </div>
                <div class="metro2-card">
                    <h3>💳 Late Payments Not Yours</h3>
                    <p>Late payments that belong to someone else - delete it.</p>
                </div>
                <div class="metro2-card">
                    <h3>🏦 Fake Collections</h3>
                    <p>Collections that should have been deleted - we find them.</p>
                </div>
                <div class="metro2-card">
                    <h3>📅 Future Dates</h3>
                    <p>Dates in the future on your report - clear violation.</p>
                </div>
                <div class="metro2-card">
                    <h3>🔢 SSN Mismatches</h3>
                    <p>Someone else's SSN on your file - identity theft indicator.</p>
                </div>
                <div class="metro2-card">
                    <h3>⚖️ Wrong Account Type</h3>
                    <p>Revolving reported as installment - disputes fail without Metro2.</p>
                </div>
            </div>
        </div>
    </section>
    
    <section class="waitlist" id="waitlist">
        <div class="container">
            <h2>Join the Waitlist</h2>
            <p class="subtitle">Be first to know when we launch. Early adopters get exclusive pricing.</p>
            
            <div class="form-box">
                <?php if ($success): ?>
                    <div class="success-msg"><?= $success ?></div>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="error-msg"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name" required placeholder="Your name">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" required placeholder="you@company.com">
                        </div>
                        
                        <div class="turnstile">
                            <div class="cf-turnstile" data-sitekey="YOUR_TURNSTILE_SITE_KEY"></div>
                        </div>
                        
                        <button type="submit" class="btn btn-submit">Join Waitlist</button>
                        <p class="form-note">We respect your privacy. hello@creditsoft.app for all comms.</p>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <footer class="footer">
        <div class="container">
            <p>© 2026 CreditSoft. Built on Metro2 Compliance.</p>
        </div>
    </footer>
</body>
</html>
