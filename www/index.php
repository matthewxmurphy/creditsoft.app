<?php
/**
 * CreditSoft Storefront - Landing Page
 */

// Load config
$config_path = dirname(__DIR__) . '/credit_config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

// Form handling
$submitted = false;
$error = '';
$success = '';
$new_joiner = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cf-turnstile-response'])) {
    $turnstile_secret = defined('TURNSTILE_SECRET_KEY') ? TURNSTILE_SECRET_KEY : '';
    
    if ($turnstile_secret) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'secret' => $turnstile_secret,
            'response' => $_POST['cf-turnstile-response'],
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $turnstile_result = json_decode(curl_exec($ch), true);
        curl_close($ch);
        
        if (empty($turnstile_result['success'])) {
            $error = 'Please complete the security check';
        }
    }
    
    if (!$error) {
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $domain = $email ? substr(strrchr($email, '@'), 1) : '';
        
        if ($email && $domain && checkdnsrr($domain, 'MX')) {
            $name = htmlspecialchars($_POST['name'] ?? '');
            
            // Save lead
            if (defined('DB_HOST') && defined('DB_NAME')) {
                try {
                    $pdo = new PDO(
                        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                        DB_USER,
                        DB_PASS,
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );
                    $stmt = $pdo->prepare("INSERT IGNORE INTO leads (name, email, source, created_at) VALUES (?, ?, 'waitlist', NOW())");
                    $stmt->execute([$name, $email]);
                } catch (Exception $e) {}
            }
            
            $success = "Thanks $name! You're on the list. We'll be in touch soon.";
            $new_joiner = $name;
            $submitted = true;
        } else {
            $error = 'Please enter a valid email address';
        }
    }
}

// Social proof data (early adopters)
$early_adopters = [
    ['name' => 'Ashley M.', 'location' => 'Kansas City, MO', 'role' => 'Credit Repair Pro'],
    ['name' => 'Sarah J.', 'location' => 'Austin, TX', 'role' => 'Agency Owner'],
    ['name' => 'Michael R.', 'location' => 'Miami, FL', 'role' => 'CRO'],
    ['name' => 'Jennifer L.', 'location' => 'Los Angeles, CA', 'role' => 'Finance Coach'],
    ['name' => 'David K.', 'location' => 'Chicago, IL', 'role' => 'Debt Consultant'],
];

// Testimonials
$testimonials = [
    ['quote' => "Finally software that understands Metro2 compliance. My disputes are actually getting results.", 'name' => 'Sarah M.', 'role' => 'Credit Repair Specialist, TX', 'image' => 'SM'],
    ['quote' => "Switched from CRC and saved $200/mo. The local hosting means my client data stays with me.", 'name' => 'James L.', 'role' => 'Agency Owner, FL', 'image' => 'JL'],
    ['quote' => "The 50-state CRO rules alone are worth the price. No more guessing about state requirements.", 'name' => 'Maria K.', 'role' => 'CRO, CA', 'image' => 'MK'],
    ['quote' => "As a new credit repair pro, the templates and workflows saved me weeks of research.", 'name' => 'Tom B.', 'role' => 'Startup, NY', 'image' => 'TB'],
    ['quote' => "The Metro2 error detection caught 15 items my previous software missed.", 'name' => 'Linda P.', 'role' => 'Senior Advisor, GA', 'image' => 'LP'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CreditSoft - Metro2-First Credit Repair Software</title>
    <meta name="description" content="Stop relying on AI letters. Real results come from Metro2 compliance and proper dispute workflows. Built for credit repair professionals.">
    <meta name="keywords" content="credit repair software, Metro2, credit dispute, FCRA, FDCPA, CRO, credit repair CRM">
    <meta name="author" content="CreditSoft">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://www.creditsoft.app/">
    <meta property="og:title" content="CreditSoft - Metro2-First Credit Repair Software">
    <meta property="og:description" content="Built on compliance. Designed for results. The credit repair software that understands Metro2.">
    <meta property="og:image" content="https://www.creditsoft.app/assets/images/og-image.png">
    
    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://www.creditsoft.app/">
    <meta name="twitter:title" content="CreditSoft - Metro2-First Credit Repair Software">
    <meta name="twitter:description" content="Built on compliance. Designed for results. The credit repair software that understands Metro2.">
    <meta name="twitter:image" content="https://www.creditsoft.app/assets/images/og-image.png">
    
    <!-- Canonical -->
    <link rel="canonical" href="https://www.creditsoft.app/">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    
    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-9QJTYCN2FZ"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-9QJTYCN2FZ');
      
      // Google Consent Mode
      gtag('consent', 'default', {
        'ad_storage': 'denied',
        'analytics_storage': 'denied',
        'ad_user_data': 'denied',
        'ad_personalization': 'denied'
      });
    </script>
    
    <style>
        :root { --primary: #2563eb; --primary-dark: #1d4ed8; --success: #10b981; --dark: #0f172a; --light: #f8fafc; --gray: #64748b; --border: #e2e8f0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--light); color: var(--dark); line-height: 1.6; }
        
        /* Toast Notifications */
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 99999; }
        .toast { background: white; color: var(--dark); padding: 16px 20px; border-radius: 12px; margin-bottom: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); animation: slideIn 0.4s ease, fadeOut 0.4s ease 4.6s forwards; display: flex; align-items: center; gap: 12px; min-width: 300px; border-left: 4px solid var(--success); }
        .toast-info { border-left-color: var(--primary); }
        .toast-icon { width: 36px; height: 36px; border-radius: 50%; background: var(--light); display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .toast-content { flex: 1; }
        .toast-title { font-weight: 600; font-size: 14px; }
        .toast-subtitle { font-size: 12px; color: var(--gray); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
        
        /* Hero */
        .hero { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #2563eb 100%); color: white; padding: 140px 20px 100px; text-align: center; position: relative; overflow: hidden; }
        .hero::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.03) 0%, transparent 50%); animation: rotate 60s linear infinite; }
        @keyframes rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .hero-content { position: relative; max-width: 900px; margin: 0 auto; }
        .logo img { height: 100px; margin-bottom: 24px; }
        .badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.5); color: #34d399; padding: 8px 16px; border-radius: 50px; font-size: 14px; font-weight: 600; margin-bottom: 20px; }
        .hero h1 { font-size: 52px; font-weight: 800; line-height: 1.1; margin-bottom: 20px; }
        .hero h1 span { color: #60a5fa; }
        .hero p { font-size: 20px; opacity: 0.9; max-width: 600px; margin: 0 auto 32px; }
        
        /* Nav */
        .nav { position: absolute; top: 0; left: 0; right: 0; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; z-index: 100; }
        .nav-logo img { height: 70px; }
        .nav-links { display: flex; gap: 28px; align-items: center; }
        .nav-links a { color: white; text-decoration: none; font-weight: 500; opacity: 0.9; font-size: 15px; }
        .nav-links a:hover { opacity: 1; }
        .nav-cta { background: var(--primary); padding: 10px 20px; border-radius: 8px; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .cta-group { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
        .btn { padding: 14px 28px; border-radius: 10px; font-size: 16px; font-weight: 600; text-decoration: none; transition: all 0.2s; cursor: pointer; border: none; display: inline-block; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); }
        .btn-outline { background: transparent; color: white; border: 2px solid rgba(255,255,255,0.3); }
        .btn-outline:hover { border-color: white; }
        
        /* Sections */
        section { padding: 80px 0; }
        .features { background: white; }
        .features h2 { text-align: center; font-size: 36px; margin-bottom: 12px; }
        .features .subtitle { text-align: center; color: var(--gray); margin-bottom: 48px; font-size: 18px; }
        
        /* Feature Grid */
        .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; }
        .feature-card { background: var(--light); padding: 28px; border-radius: 14px; transition: transform 0.2s; text-decoration: none; color: inherit; display: block; }
        .feature-card:hover { transform: translateY(-4px); }
        .feature-icon { width: 52px; height: 52px; background: linear-gradient(135deg, var(--primary), #3b82f6); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; }
        .feature-icon svg { width: 26px; height: 26px; color: white; }
        .feature-card h3 { font-size: 18px; margin-bottom: 10px; }
        .feature-card p { color: var(--gray); font-size: 14px; }
        
        /* Pricing */
        .pricing { background: var(--light); }
        .pricing h2 { text-align: center; font-size: 36px; margin-bottom: 12px; }
        .pricing .subtitle { text-align: center; color: var(--gray); margin-bottom: 48px; font-size: 18px; }
        .pricing-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; max-width: 1000px; margin: 0 auto; }
        .pricing-card { background: white; border: 2px solid var(--border); border-radius: 16px; padding: 32px; text-align: center; transition: transform 0.2s, border-color 0.2s; }
        .pricing-card:hover { transform: translateY(-4px); border-color: var(--primary); }
        .pricing-card.featured { border-color: var(--primary); background: linear-gradient(135deg, rgba(37,99,235,0.05), rgba(37,99,235,0.1)); }
        .pricing-card h3 { font-size: 24px; margin-bottom: 8px; }
        .pricing-card .price { font-size: 48px; font-weight: 800; color: var(--dark); margin-bottom: 8px; }
        .pricing-card .price span { font-size: 16px; font-weight: 400; color: var(--gray); }
        .pricing-card .desc { color: var(--gray); margin-bottom: 24px; }
        .pricing-features { list-style: none; text-align: left; margin-bottom: 24px; }
        .pricing-features li { padding: 8px 0; display: flex; align-items: center; gap: 10px; }
        .pricing-features li::before { content: '✓'; color: var(--success); font-weight: bold; }
        
        /* Testimonials Carousel */
        .testimonials { background: white; overflow: hidden; }
        .testimonials h2 { text-align: center; font-size: 36px; margin-bottom: 12px; }
        .testimonials .subtitle { text-align: center; color: var(--gray); margin-bottom: 48px; }
        
        .carousel-wrapper { position: relative; max-width: 1000px; margin: 0 auto; overflow: hidden; }
        .carousel-track { display: flex; transition: transform 0.5s ease; }
        .carousel-slide { min-width: 100%; padding: 0 20px; }
        .testimonial-card { background: var(--light); padding: 32px; border-radius: 16px; text-align: center; max-width: 700px; margin: 0 auto; }
        .testimonial-avatar { width: 64px; height: 64px; background: linear-gradient(135deg, var(--primary), #3b82f6); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; font-weight: 700; margin: 0 auto 16px; }
        .testimonial-quote { font-size: 18px; font-style: italic; margin-bottom: 16px; color: var(--dark); }
        .testimonial-author { font-weight: 600; }
        .testimonial-role { font-size: 14px; color: var(--gray); }
        
        .carousel-dots { display: flex; justify-content: center; gap: 8px; margin-top: 24px; }
        .carousel-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--border); cursor: pointer; transition: background 0.3s; }
        .carousel-dot.active { background: var(--primary); }
        
        .carousel-nav { position: absolute; top: 50%; transform: translateY(-50%); width: 44px; height: 44px; background: white; border: 1px solid var(--border); border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .carousel-nav:hover { background: var(--light); }
        .carousel-prev { left: 0; }
        .carousel-next { right: 0; }
        
        /* Early Adopters */
        .early-adopters { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%); color: white; padding: 60px 0; text-align: center; }
        .early-adopters h2 { font-size: 28px; margin-bottom: 8px; }
        .early-adopters .subtitle { opacity: 0.8; margin-bottom: 32px; }
        .adopters-grid { display: flex; justify-content: center; flex-wrap: wrap; gap: 16px; max-width: 800px; margin: 0 auto; }
        .adopter-badge { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); padding: 10px 20px; border-radius: 50px; font-size: 14px; }
        .adopter-badge .name { font-weight: 600; color: #34d399; }
        
        /* Comparison */
        .comparison-table { width: 100%; border-collapse: collapse; background: white; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .comparison-table th, .comparison-table td { padding: 16px 20px; text-align: center; border-bottom: 1px solid var(--border); }
        .comparison-table th { background: var(--dark); color: white; font-weight: 600; }
        .comparison-table th:first-child { text-align: left; }
        .comparison-table td:first-child { text-align: left; font-weight: 500; }
        .our-feature { background: rgba(37, 99, 235, 0.08); font-weight: 600; }
        
        /* Waitlist */
        .waitlist { background: var(--dark); color: white; text-align: center; padding: 80px 20px; }
        .waitlist h2 { font-size: 36px; margin-bottom: 12px; }
        .waitlist .subtitle { opacity: 0.8; margin-bottom: 32px; font-size: 18px; }
        .form-box { max-width: 440px; margin: 0 auto; background: rgba(255,255,255,0.05); padding: 32px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1); }
        .form-group { margin-bottom: 18px; text-align: left; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 14px; }
        .form-group input { width: 100%; padding: 14px 16px; border: 1px solid rgba(255,255,255,0.2); border-radius: 10px; background: rgba(255,255,255,0.05); color: white; font-size: 16px; }
        .form-group input:focus { outline: none; border-color: var(--primary); }
        .turnstile { margin: 20px 0; display: flex; justify-content: center; }
        .btn-submit { background: var(--success); color: white; width: 100%; }
        .btn-submit:hover { background: #059669; }
        .form-note { font-size: 13px; opacity: 0.6; margin-top: 16px; }
        .success-msg { background: rgba(16, 185, 129, 0.2); border: 1px solid var(--success); color: #34d399; padding: 20px; border-radius: 12px; font-size: 18px; }
        .error-msg { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5; padding: 14px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        
        /* Footer */
        .footer { background: #0a0f1a; color: var(--gray); padding: 60px 0 30px; font-size: 14px; }
        .footer-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 40px; max-width: 1100px; margin: 0 auto 40px; text-align: left; }
        .footer h4 { color: white; font-size: 16px; margin-bottom: 16px; }
        .footer ul { list-style: none; }
        .footer li { margin-bottom: 10px; }
        .footer a { color: var(--gray); text-decoration: none; }
        .footer a:hover { color: var(--primary); }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,0.1); padding-top: 30px; max-width: 1100px; margin: 0 auto; text-align: center; }
        
        /* Chat Widget */
        .chat-widget { position: fixed; bottom: 20px; right: 20px; z-index: 9998; font-family: 'Inter', sans-serif; }
        .chat-header { background: var(--primary); color: white; padding: 14px 20px; border-radius: 12px 12px 0 0; cursor: pointer; display: flex; justify-content: space-between; font-weight: 600; }
        .chat-body { background: white; width: 360px; height: 420px; border-radius: 12px 0 12px 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); display: flex; flex-direction: column; }
        .chat-body.collapsed { display: none; }
        .chat-messages { flex: 1; padding: 16px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; }
        .chat-message { padding: 10px 14px; border-radius: 12px; font-size: 14px; max-width: 85%; }
        .chat-message.bot { background: var(--light); align-self: flex-start; }
        .chat-message.user { background: var(--primary); color: white; align-self: flex-end; }
        .chat-input { padding: 12px; border-top: 1px solid var(--border); display: flex; gap: 8px; }
        .chat-input input { flex: 1; padding: 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; }
        .chat-input button { background: var(--primary); color: white; border: none; padding: 10px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        
        /* Cookie Consent */
        .cookie-banner { position: fixed; bottom: 0; left: 0; right: 0; background: white; padding: 24px; box-shadow: 0 -4px 20px rgba(0,0,0,0.1); z-index: 99997; display: none; }
        .cookie-banner.show { display: block; animation: slideUp 0.4s ease; }
        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
        .cookie-content { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 24px; flex-wrap: wrap; }
        .cookie-text h3 { font-size: 18px; margin-bottom: 8px; }
        .cookie-text p { font-size: 14px; color: var(--gray); }
        .cookie-text a { color: var(--primary); }
        .cookie-buttons { display: flex; gap: 12px; }
        .cookie-btn { padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .cookie-btn-accept { background: var(--primary); color: white; border: none; }
        .cookie-btn-decline { background: transparent; color: var(--gray); border: 1px solid var(--border); }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 { font-size: 32px; }
            .nav-links { display: none; }
            .carousel-nav { display: none; }
            .cookie-content { flex-direction: column; text-align: center; }
            .cookie-buttons { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="toast-container" id="toastContainer"></div>
    
    <section class="hero">
        <nav class="nav">
            <div class="nav-logo"><a href="/"><img src="/assets/images/CreditSoft.png" alt="CreditSoft"></a></div>
            <div class="nav-links">
                <a href="#features">Features</a>
                <a href="/pricing">Pricing</a>
                <a href="/about">About</a>
                <a href="/security">Security</a>
                <a href="/legal">Legal</a>
                <a href="#waitlist" class="nav-cta">Get Early Access</a>
            </div>
        </nav>
        <div class="hero-content">
            <div class="badge">🎯 <span>Metro2-First Credit Repair</span></div>
            <h1>Credit Repair Software<br><span>Built on Compliance</span></h1>
            <p>Stop relying on "AI letters". Real results come from Metro2 compliance and proper dispute workflows.</p>
            <div class="cta-group">
                <a href="#waitlist" class="btn btn-primary">Get Early Access</a>
                <a href="#comparison" class="btn btn-outline">Compare Features</a>
            </div>
        </div>
    </section>
    
    <!-- Early Adopters -->
    <section class="early-adopters">
        <div class="container">
            <h2>🚀 Early Adopters</h2>
            <p class="subtitle">Join professionals already on the waitlist</p>
            <div class="adopters-grid">
                <?php foreach ($early_adopters as $adopter): ?>
                <div class="adopter-badge">
                    <span class="name"><?= $adopter['name'] ?></span> from <?= $adopter['location'] ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    
    <section class="features" id="features">
        <div class="container">
            <h2>Why CreditSoft Wins</h2>
            <p class="subtitle">Built different because we understand credit repair</p>
            <div class="feature-grid">
                <a href="/metro2" class="feature-card">
                    <div class="feature-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg></div>
                    <h3>30+ Metro2 Error Codes</h3>
                    <p>Built-in detection for every Metro2 violation. Account mismatches, late payments that aren't yours, fake collections - we catch them all.</p>
                </a>
                <a href="/cro-rules" class="feature-card">
                    <div class="feature-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg></div>
                    <h3>50-State CRO Rules</h3>
                    <p>Know your state's requirements. Bond amounts, registration, fee limits - our knowledge base has it all built in.</p>
                </a>
                <a href="/compliance" class="feature-card">
                    <div class="feature-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                    <h3>FCRA/FDCPA Compliant</h3>
                    <p>Dispute the right way. Our workflows follow federal law so you stay compliant while getting results.</p>
                </a>
                <a href="/disputes" class="feature-card">
                    <div class="feature-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg></div>
                    <h3>Smart Disputes</h3>
                    <p>AI-assisted backed by Metro2 compliance. Not just letters - workflows that follow the rules.</p>
                </a>
                <a href="/client-portal" class="feature-card">
                    <div class="feature-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg></div>
                    <h3>Client Portal</h3>
                    <p>White-labeled widget. Clients see progress without you hosting their sensitive data on public sites.</p>
                </a>
                <a href="/reporting" class="feature-card">
                    <div class="feature-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg></div>
                    <h3>Monthly Comparisons</h3>
                    <p>Track credit score changes over time. Visual reports showing progress and new issues.</p>
                </a>
            </div>
        </div>
    </section>
    
    <section class="pricing" id="pricing">
        <div class="container">
            <h2>Simple, Transparent Pricing</h2>
            <p class="subtitle">No hidden fees. No surprises. Just powerful credit repair software.</p>
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
        </div>
    </section>
    
    <section class="testimonials" id="testimonials">
        <div class="container">
            <h2>What Our Users Say</h2>
            <p class="subtitle">Trusted by credit repair professionals nationwide</p>
            
            <div class="carousel-wrapper">
                <button class="carousel-nav carousel-prev" onclick="moveCarousel(-1)">‹</button>
                <div class="carousel-track" id="carouselTrack">
                    <?php foreach ($testimonials as $t): ?>
                    <div class="carousel-slide">
                        <div class="testimonial-card">
                            <div class="testimonial-avatar"><?= $t['image'] ?></div>
                            <p class="testimonial-quote">"<?= $t['quote'] ?>"</p>
                            <div class="testimonial-author"><?= $t['name'] ?></div>
                            <div class="testimonial-role"><?= $t['role'] ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button class="carousel-nav carousel-next" onclick="moveCarousel(1)">›</button>
            </div>
            <div class="carousel-dots" id="carouselDots"></div>
        </div>
    </section>
    
    <section class="features" id="comparison">
        <div class="container">
            <h2>How We Compare</h2>
            <p class="subtitle">See why credit repair professionals choose CreditSoft</p>
            <table class="comparison-table">
                <thead><tr><th>Feature</th><th>CreditSoft</th><th>CDM</th><th>CRC</th></tr></thead>
                <tbody>
                    <tr><td>Metro2 Error Detection</td><td class="our-feature">30+ Codes Built-In</td><td>Limited</td><td>Basic</td></tr>
                    <tr><td>50-State CRO Rules</td><td class="our-feature">✓ Built In</td><td>Add-on</td><td>External</td></tr>
                    <tr><td>FCRA/FDCPA Workflows</td><td class="our-feature">✓ Built In</td><td>✓</td><td>✓</td></tr>
                    <tr><td>PII on Customer Websites</td><td class="our-feature">❌ Never</td><td>Yes</td><td>Yes</td></tr>
                    <tr><td>Update Server</td><td class="our-feature">✓ Your Server</td><td>Their Server</td><td>Their Server</td></tr>
                    <tr><td>Monthly Price</td><td class="our-feature">From $49</td><td>$107-329</td><td>$99-299</td></tr>
                </tbody>
            </table>
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
                    <?php if ($error): ?><div class="error-msg"><?= $error ?></div><?php endif; ?>
                    <form method="POST">
                        <div class="form-group"><label>Name</label><input type="text" name="name" required placeholder="Your name"></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" required placeholder="you@company.com"></div>
                        <div class="turnstile"><div class="cf-turnstile" data-sitekey="<?= defined('TURNSTILE_SITE_KEY') ? TURNSTILE_SITE_KEY : '' ?>"></div></div>
                        <button type="submit" class="btn btn-submit">Join Waitlist</button>
                        <p class="form-note">We respect your privacy. hello@creditsoft.app for all comms.</p>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div><h4>Product</h4><ul><li><a href="/#features">Features</a></li><li><a href="/pricing">Pricing</a></li><li><a href="/about">About</a></li><li><a href="/security">Security</a></li></ul></div>
                <div><h4>Resources</h4><ul><li><a href="/presales-tips">Pre-Sales Tips</a></li><li><a href="/#testimonials">Testimonials</a></li></ul></div>
                <div><h4>Legal</h4><ul><li><a href="/terms">Terms of Service</a></li><li><a href="/privacy">Privacy Policy</a></li><li><a href="/cookies">Cookie Policy</a></li><li><a href="/legal">Legal</a></li></ul></div>
                <div><h4>Support</h4><ul><li><a href="/subscribe">Get Early Access</a></li><li><a href="mailto:hello@creditsoft.app">Contact Us</a></li></ul></div>
            </div>
            <div class="footer-bottom">
                <p>© 2026 CreditSoft. All rights reserved. · <a href="https://www.matthewxmurphy.com" target="_blank">Matthew Murphy</a> · <a href="https://www.net30hosting.com" target="_blank">Hosted on Net30Hosting</a></p>
            </div>
        </div>
    </footer>
    
    <!-- Chat Widget -->
    <div class="chat-widget">
        <div class="chat-header" onclick="toggleChat()"><span>💬 Chat</span><span id="chatToggle">−</span></div>
        <div class="chat-body" id="chatBody">
            <div class="chat-messages" id="chatMessages">
                <div class="chat-message bot">Hi! Ask me about CreditSoft!</div>
            </div>
            <div class="chat-input">
                <input type="text" id="chatInput" placeholder="Type a message..." onkeypress="if(event.key==='Enter')sendChat()">
                <button onclick="sendChat()">Send</button>
            </div>
        </div>
    </div>
    
    <!-- Cookie Consent Banner -->
    <div class="cookie-banner" id="cookieBanner">
        <div class="cookie-content">
            <div class="cookie-text">
                <h3>🍪 We value your privacy</h3>
                <p>We use cookies to enhance your experience and analyze our traffic. By clicking "Accept", you consent to our use of cookies. Read our <a href="/privacy">Privacy Policy</a> and <a href="/cookies">Cookie Policy</a>.</p>
                <p style="margin-top:8px;font-size:12px;color:var(--gray);">Complies with: GDPR, CCPA/CPRA, DMA, LGPD, POPIA, Google Consent Mode v2</p>
            </div>
            <div class="cookie-buttons">
                <button class="cookie-btn cookie-btn-decline" onclick="declineCookies()">Decline</button>
                <button class="cookie-btn cookie-btn-accept" onclick="acceptCookies()">Accept All</button>
            </div>
        </div>
    </div>
    
    <script>
    // Toast notifications
    function showToast(icon, title, subtitle) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerHTML = '<div class="toast-icon">' + icon + '</div><div class="toast-content"><div class="toast-title">' + title + '</div><div class="toast-subtitle">' + subtitle + '</div></div>';
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }
    
    // Show early adopter toast on load
    setTimeout(() => showToast('🚀', 'Ashley M. joined the waitlist', 'Kansas City, MO - Credit Repair Pro'), 2000);
    setTimeout(() => showToast('👋', 'Sarah J. is an Early Adopter', 'Austin, TX'), 5000);
    setTimeout(() => showToast('⭐', 'New review from Maria K.', 'CA - 5 stars'), 8000);
    
    <?php if ($new_joiner): ?>
    setTimeout(() => showToast('🎉', '<?= addslashes($new_joiner) ?> joined!', 'Welcome to the waitlist!'), 1000);
    <?php endif; ?>
    
    // Carousel
    let currentSlide = 0;
    const track = document.getElementById('carouselTrack');
    const slides = <?= count($testimonials) ?>;
    
    function moveCarousel(direction) {
        currentSlide = (currentSlide + direction + slides) % slides;
        track.style.transform = 'translateX(-' + (currentSlide * 100) + '%)';
        updateDots();
    }
    
    function updateDots() {
        const dotsContainer = document.getElementById('carouselDots');
        dotsContainer.innerHTML = '';
        for (let i = 0; i < slides; i++) {
            const dot = document.createElement('div');
            dot.className = 'carousel-dot' + (i === currentSlide ? ' active' : '');
            dot.onclick = () => { currentSlide = i; track.style.transform = 'translateX(-' + (i * 100) + '%)'; updateDots(); };
            dotsContainer.appendChild(dot);
        }
    }
    updateDots();
    setInterval(() => moveCarousel(1), 6000);
    
    // Chat
    let chatOpen = true;
    function toggleChat() {
        chatOpen = !chatOpen;
        document.getElementById('chatBody').classList.toggle('collapsed', !chatOpen);
        document.getElementById('chatToggle').textContent = chatOpen ? '−' : '+';
    }
    
    async function sendChat() {
        const input = document.getElementById('chatInput');
        const msg = input.value.trim();
        if (!msg) return;
        
        const msgs = document.getElementById('chatMessages');
        msgs.innerHTML += '<div class="chat-message user">' + msg + '</div>';
        msgs.innerHTML += '<div class="chat-message bot">Thinking...</div>';
        msgs.scrollTop = msgs.scrollHeight;
        input.value = '';
        
        try {
            const res = await fetch('/api/chat.php', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({message: msg})});
            const data = await res.json();
            msgs.lastElementChild.remove();
            msgs.innerHTML += '<div class="chat-message bot">' + (data.reply || 'Sorry, try again.') + '</div>';
        } catch(e) {
            msgs.lastElementChild.remove();
            msgs.innerHTML += '<div class="chat-message bot">Sorry, something went wrong.</div>';
        }
        msgs.scrollTop = msgs.scrollHeight;
    }
    
    // Cookie Consent
    function getCookie(name) { const v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)'); return v ? v[2] : null; }
    
    if (!getCookie('cookie_consent')) {
        document.getElementById('cookieBanner').classList.add('show');
    }
    
    function acceptCookies() {
        document.cookie = 'cookie_consent=accepted;max-age=31536000;path=/';
        document.getElementById('cookieBanner').classList.remove('show');
        
        gtag('consent', 'update', {
            'ad_storage': 'granted',
            'analytics_storage': 'granted',
            'ad_user_data': 'granted',
            'ad_personalization': 'granted'
        });
    }
    
    function declineCookies() {
        document.cookie = 'cookie_consent=declined;max-age=31536000;path=/';
        document.getElementById('cookieBanner').classList.remove('show');
    }
    </script>
</body>
</html>
