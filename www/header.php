<?php
$page_title = $page_title ?? 'CreditSoft';
$page_description = $page_description ?? 'Metro2-First Credit Repair Software';
$page_hero = $page_hero ?? false;
$hero_title = $hero_title ?? '';
$hero_subtitle = $hero_subtitle ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - CreditSoft</title>
    <meta name="description" content="<?= $page_description ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://www.creditsoft.app/<?= basename($_SERVER['REQUEST_URI'], '.php') ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://www.creditsoft.app/<?= basename($_SERVER['REQUEST_URI'], '.php') ?>">
    <meta property="og:title" content="<?= $page_title ?> - CreditSoft">
    <meta property="og:description" content="<?= $page_description ?>">
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
        
        .hero { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #2563eb 100%); color: white; padding: 140px 20px 60px; text-align: center; }
        .hero h1 { font-size: 42px; font-weight: 800; margin-bottom: 12px; }
        .hero p { font-size: 18px; opacity: 0.9; }
        
        .footer { background: #0a0f1a; color: var(--gray); padding: 40px 0; text-align: center; font-size: 14px; }
        .footer a { color: var(--primary); text-decoration: none; }
        
        @media(max-width: 768px) { .nav-links { display: none; } }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-logo"><a href="/"><img src="/assets/images/CreditSoft.png" alt="CreditSoft"></a></div>
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
    <?php if ($page_hero): ?>
    <section class="hero">
        <h1><?= $hero_title ?></h1>
        <p><?= $hero_subtitle ?></p>
    </section>
    <?php endif; ?>
