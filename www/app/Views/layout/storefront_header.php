<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'CreditSoft' ?> - CreditSoft</title>
    <meta name="description" content="<?= $page_description ?? 'Metro2-First Credit Repair Software' ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://www.creditsoft.app/">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://www.creditsoft.app/">
    <meta property="og:title" content="<?= $page_title ?? 'CreditSoft' ?> - CreditSoft">
    <meta property="og:description" content="<?= $page_description ?? 'Metro2-First Credit Repair Software' ?>">
    <meta property="og:image" content="https://www.creditsoft.app/assets/images/og-image.png">
    <meta name="twitter:card" content="summary_large_image">
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
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .btn { display: inline-block; padding: 14px 28px; border-radius: 10px; font-size: 16px; font-weight: 600; text-decoration: none; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); text-decoration: none; }
        
        .nav { position: absolute; top: 0; left: 0; right: 0; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; z-index: 100; }
        .nav-logo img { height: 70px; }
        .nav-links { display: flex; gap: 28px; align-items: center; }
        .nav-links a { color: white; text-decoration: none; font-weight: 500; opacity: 0.9; }
        .nav-links a:hover { opacity: 1; }
        .nav-cta { background: var(--primary); padding: 10px 20px; border-radius: 8px; }
        
        .hamburger { display: none; flex-direction: column; gap: 5px; cursor: pointer; padding: 10px; }
        .hamburger span { width: 25px; height: 3px; background: white; border-radius: 2px; transition: all 0.3s; }
        .hamburger.active span:nth-child(1) { transform: rotate(45deg) translate(5px, 5px); }
        .hamburger.active span:nth-child(2) { opacity: 0; }
        .hamburger.active span:nth-child(3) { transform: rotate(-45deg) translate(5px, -5px); }
        
        .mobile-menu { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.98); z-index: 99; padding: 100px 20px 20px; }
        .mobile-menu.active { display: flex; flex-direction: column; gap: 20px; }
        .mobile-menu a { color: white; text-decoration: none; font-weight: 500; font-size: 20px; padding: 15px 0; border-bottom: 1px solid rgba(255,255,255,0.1); }
        
        .hero { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #2563eb 100%); color: white; padding: 140px 20px 60px; text-align: center; }
        .hero h1 { font-size: 42px; font-weight: 800; margin-bottom: 12px; }
        .hero p { font-size: 18px; opacity: 0.9; }
        
        .footer { background: #0a0f1a; color: var(--gray); padding: 40px 0; text-align: center; font-size: 14px; }
        .footer a { color: var(--primary); text-decoration: none; }
        
        @media(max-width: 768px) { 
            .nav-links { display: none; }
            .hamburger { display: flex; }
        }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-logo"><a href="/"><img src="/assets/images/CreditSoft.png" alt="CreditSoft"></a></div>
        <div class="hamburger" onclick="toggleMenu()">
            <span></span><span></span><span></span>
        </div>
        <div class="nav-links">
            <a href="/quiz">Take Quiz</a>
            <a href="/#features">Features</a>
            <a href="/pricing">Pricing</a>
            <a href="/about">About</a>
            <a href="/security">Security</a>
            <a href="/legal">Legal</a>
            <a href="/#waitlist" class="nav-cta">Get Early Access</a>
        </div>
    </nav>
    <div class="mobile-menu" id="mobileMenu">
        <a href="/quiz" onclick="toggleMenu()">Take Quiz</a>
        <a href="/#features" onclick="toggleMenu()">Features</a>
        <a href="/pricing" onclick="toggleMenu()">Pricing</a>
        <a href="/about" onclick="toggleMenu()">About</a>
        <a href="/security" onclick="toggleMenu()">Security</a>
        <a href="/legal" onclick="toggleMenu()">Legal</a>
        <a href="/#waitlist" class="nav-cta" onclick="toggleMenu()">Get Early Access</a>
    </div>
    <script>
    function toggleMenu() {
        document.querySelector('.hamburger').classList.toggle('active');
        document.getElementById('mobileMenu').classList.toggle('active');
    }
    </script>
    <?php if (isset($page_hero) && $page_hero): ?>
    <section class="hero">
        <h1><?= $hero_title ?? '' ?></h1>
        <p><?= $hero_subtitle ?? '' ?></p>
    </section>
    <?php endif; ?>
