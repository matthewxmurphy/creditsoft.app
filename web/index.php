<?php
/**
 * CreditSoft Storefront - Landing Page
 */

// Load config
$config_path = dirname(__DIR__) . '/credit_config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

require_once __DIR__ . '/lead-intake.php';
require_once __DIR__ . '/site-content-config.php';
require_once __DIR__ . '/site-tracking-config.php';
require_once __DIR__ . '/site-seo-config.php';
$error = '';
$starter_plan = creditsoft_lead_clean_text($_GET['plan'] ?? '', 80);
$site_content = creditsoft_site_content_load();
$home_content = $site_content['home'] ?? [];
$tracking = creditsoft_site_tracking_load();
$googleMeasurementId = trim((string) ($tracking['google_measurement_id'] ?? ''));
$metaPixelId = trim((string) ($tracking['meta_pixel_id'] ?? ''));
$homeSeo = creditsoft_site_seo_effective_for_slug('', [
    'title' => 'CreditSoft - Metro2-First Credit Repair Software',
    'description' => 'AI-powered, Metro2-first credit repair platform with built-in FCRA/FDCPA compliance. A complete local-first ecosystem - CRM, client portal, intranet, and website - all running on your own infrastructure, not someone else\'s cloud.',
    'og_image' => '/assets/images/og-image.png',
]);
$homeMetaTitle = trim((string) ($homeSeo['title'] ?? 'CreditSoft - Metro2-First Credit Repair Software'));
$homeMetaDescription = trim((string) ($homeSeo['description'] ?? 'AI-powered, Metro2-first credit repair platform with built-in FCRA/FDCPA compliance. A complete local-first ecosystem - CRM, client portal, intranet, and website - all running on your own infrastructure, not someone else\'s cloud.'));
$homeMetaImage = trim((string) ($homeSeo['og_image'] ?? '/assets/images/og-image.png'));
$homeMetaImage = str_starts_with($homeMetaImage, 'http://') || str_starts_with($homeMetaImage, 'https://')
    ? $homeMetaImage
    : ('https://www.creditsoft.app' . $homeMetaImage);

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
    <title><?= htmlspecialchars($homeMetaTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($homeMetaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="keywords" content="credit repair software, Metro2, credit dispute, FCRA, FDCPA, CRO, credit repair CRM, branded credit repair website">
    <meta name="author" content="CreditSoft">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://www.creditsoft.app/">
    <meta property="og:title" content="<?= htmlspecialchars($homeMetaTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($homeMetaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($homeMetaImage, ENT_QUOTES, 'UTF-8') ?>">
    
    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://www.creditsoft.app/">
    <meta name="twitter:title" content="<?= htmlspecialchars($homeMetaTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($homeMetaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($homeMetaImage, ENT_QUOTES, 'UTF-8') ?>">
    
    <!-- Canonical -->
    <link rel="canonical" href="https://www.creditsoft.app/">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <!-- Google Analytics -->
    <?php if ($googleMeasurementId !== ''): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($googleMeasurementId, ENT_QUOTES, 'UTF-8') ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '<?= htmlspecialchars($googleMeasurementId, ENT_QUOTES, 'UTF-8') ?>');
      
      // Google Consent Mode
      gtag('consent', 'default', {
        'ad_storage': 'denied',
        'analytics_storage': 'denied',
        'ad_user_data': 'denied',
        'ad_personalization': 'denied'
      });
    </script>
    <?php endif; ?>
    <?php if ($metaPixelId !== ''): ?>
    <script>
      !function(f,b,e,v,n,t,s)
      {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
      n.callMethod.apply(n,arguments):n.queue.push(arguments)};
      if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
      n.queue=[];t=b.createElement(e);t.async=!0;
      t.src=v;s=b.getElementsByTagName(e)[0];
      s.parentNode.insertBefore(t,s)}(window, document,'script',
      'https://connect.facebook.net/en_US/fbevents.js');
      fbq('init', '<?= htmlspecialchars($metaPixelId, ENT_QUOTES, 'UTF-8') ?>');
      fbq('track', 'PageView');
    </script>
    <noscript>
      <img height="1" width="1" style="display:none"
           src="https://www.facebook.com/tr?id=<?= htmlspecialchars($metaPixelId, ENT_QUOTES, 'UTF-8') ?>&ev=PageView&noscript=1"/>
    </noscript>
    <?php endif; ?>
    
    <style>
        :root { --primary: #2563eb; --primary-dark: #1d4ed8; --success: #10b981; --dark: #0f172a; --light: #f8fafc; --gray: #64748b; --border: #e2e8f0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--light); color: var(--dark); line-height: 1.6; }
        
        /* Toast Notifications */
        .toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 99999; }
        .toast { background: white; color: var(--dark); padding: 16px 20px; border-radius: 12px; margin-bottom: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); animation: slideIn 0.4s ease, fadeOut 0.4s ease 4.6s forwards; display: flex; align-items: center; gap: 12px; min-width: 300px; border-left: 4px solid var(--success); }
        .toast-info { border-left-color: var(--primary); }
        .toast-icon { width: 36px; height: 36px; border-radius: 50%; background: var(--light); display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .toast-content { flex: 1; }
        .toast-title { font-weight: 600; font-size: 14px; }
        .toast-subtitle { font-size: 12px; color: var(--gray); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
        
        .site-header { position: absolute; top: 0; left: 0; right: 0; z-index: 120; }
        .nav-utility { position: relative; z-index: 3; background: #0a1120; color: #dbe5f2; border-bottom: 1px solid rgba(255,255,255,0.08); box-shadow: 0 10px 24px rgba(2, 6, 23, 0.18); }
        .nav-utility__inner { max-width: 1380px; margin: 0 auto; padding: 11px 26px; display: flex; justify-content: space-between; gap: 18px; align-items: center; min-height: 42px; }
        .nav-utility__copy { font-size: 13px; line-height: 1.35; color: #e2e8f0; font-weight: 700; letter-spacing: .01em; }
        .nav-utility__contact { display: inline-flex; align-items: center; gap: 8px; color: #f8fafc; font-weight: 700; font-size: 13px; text-decoration: none; white-space: nowrap; }
        .nav-utility__contact svg { width: 16px; height: 16px; fill: currentColor; opacity: .92; }
        .nav-utility__contact:hover { color: #93c5fd; text-decoration: none; }
        .nav { background: rgba(255,255,255,0.98); border-bottom: 1px solid rgba(15,23,42,0.08); box-shadow: 0 18px 44px rgba(15,23,42,0.08); }
        .nav-shell { max-width: 1380px; margin: 0 auto; padding: 0 26px; min-height: 86px; display: grid; grid-template-columns: auto minmax(0,1fr) auto; align-items: center; gap: 20px; }
        .nav-logo { position: relative; z-index: 2; }
        .nav-logo a { display: inline-flex; align-items: center; transform: translateY(6px); }
        .nav-logo img { height: 78px; width: auto; display: block; filter: drop-shadow(0 16px 20px rgba(15,23,42,0.10)); }
        .nav-links { display: flex; gap: 18px; align-items: center; justify-content: center; flex-wrap: nowrap; min-width: 0; }
        .nav-links a { color: #1e293b; text-decoration: none; font-weight: 700; font-size: 13px; position: relative; padding: 8px 0; opacity: 1; white-space: nowrap; }
        .nav-links a::after { content: ''; position: absolute; left: 0; right: 0; bottom: -2px; height: 2px; background: #2563eb; transform: scaleX(0); transform-origin: center; transition: transform .2s ease; }
        .nav-links a:hover { color: #0f172a; text-decoration: none; }
        .nav-links a:hover::after,
        .nav-item--has-menu:hover .nav-trigger::after,
        .nav-item--has-menu:focus-within .nav-trigger::after { transform: scaleX(1); }
        .nav-item { position: relative; display: flex; align-items: center; }
        .nav-trigger { display: inline-flex; align-items: center; gap: 6px; }
        .nav-trigger svg { width: 14px; height: 14px; fill: currentColor; transition: transform .2s ease; }
        .nav-item--has-menu:hover .nav-trigger svg,
        .nav-item--has-menu:focus-within .nav-trigger svg { transform: rotate(180deg); }
        .nav-dropdown {
            position: absolute;
            top: calc(100% + 18px);
            left: 0;
            width: min(560px, calc(100vw - 32px));
            padding: 16px;
            border-radius: 24px;
            background: rgba(8, 13, 25, 0.98);
            border: 1px solid rgba(148, 163, 184, 0.12);
            box-shadow: 0 28px 70px rgba(2, 6, 23, 0.38);
            display: grid;
            gap: 8px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: opacity .2s ease, transform .2s ease, visibility .2s ease;
            z-index: 40;
        }
        .nav-dropdown::before {
            content: '';
            position: absolute;
            top: -8px;
            left: 30px;
            width: 16px;
            height: 16px;
            background: rgba(8, 13, 25, 0.98);
            border-left: 1px solid rgba(148, 163, 184, 0.12);
            border-top: 1px solid rgba(148, 163, 184, 0.12);
            transform: rotate(45deg);
        }
        .nav-item--has-menu:hover .nav-dropdown,
        .nav-item--has-menu:focus-within .nav-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .nav-dropdown__item {
            display: grid;
            grid-template-columns: 54px minmax(0, 1fr);
            gap: 16px;
            align-items: start;
            padding: 14px 16px;
            border-radius: 16px;
            color: #e2e8f0;
            text-decoration: none;
            background: transparent;
            transition: background .18s ease, transform .18s ease;
            text-align: left;
        }
        .nav-dropdown__item::after { display: none; }
        .nav-dropdown__item:hover {
            background: rgba(37, 99, 235, 0.14);
            transform: translateX(2px);
            text-decoration: none;
        }
        .nav-dropdown__icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            background: rgba(255,255,255,0.05);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #93c5fd;
        }
        .nav-dropdown__icon svg { width: 22px; height: 22px; fill: currentColor; }
        .nav-dropdown__icon i { font-size: 22px; line-height: 1; }
        .nav-dropdown__copy { display: grid; gap: 4px; justify-items: start; text-align: left; }
        .nav-dropdown__copy strong { display: block; width: 100%; font-size: 16px; line-height: 1.2; color: #f8fafc; text-align: left; }
        .nav-dropdown__copy span { display: block; width: 100%; font-size: 13px; line-height: 1.55; color: #94a3b8; font-weight: 500; max-width: none; text-align: left; }
        .nav-actions { display: flex; align-items: center; gap: 10px; }
        .nav-cta { display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 10px; background: #f97316; color: white; font-weight: 800; font-size: 14px; text-decoration: none; box-shadow: 0 10px 22px rgba(249,115,22,0.18); white-space: nowrap; }
        .nav-cta:hover { background: #ea580c; transform: translateY(-1px); text-decoration: none; }
        .nav-secondary { display: inline-flex; align-items: center; justify-content: center; padding: 9px 16px; border-radius: 10px; border: 2px solid #93c5fd; color: #1d4ed8; font-weight: 800; font-size: 14px; text-decoration: none; background: rgba(255,255,255,0.85); white-space: nowrap; }
        .nav-secondary:hover { background: #eff6ff; text-decoration: none; }

        /* Hero */
        .hero { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #2563eb 100%); color: white; padding: 198px 20px 100px; text-align: center; position: relative; overflow: hidden; }
        .hero::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.03) 0%, transparent 50%); animation: rotate 60s linear infinite; }
        @keyframes rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .hero-content { position: relative; max-width: 900px; margin: 0 auto; }
        .logo img { height: 100px; margin-bottom: 24px; }
        .badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.5); color: #34d399; padding: 8px 16px; border-radius: 50px; font-size: 14px; font-weight: 600; margin-bottom: 20px; }
        .hero h1 { font-size: 52px; font-weight: 800; line-height: 1.1; margin-bottom: 20px; }
        .hero h1 span { color: #60a5fa; }
        .hero p { font-size: 20px; opacity: 0.9; max-width: 600px; margin: 0 auto 32px; }
        .hamburger { display: none; width: 48px; height: 48px; align-items: center; justify-content: center; flex-direction: column; gap: 5px; cursor: pointer; padding: 0; justify-self: end; border: 1px solid rgba(15,23,42,0.1); border-radius: 14px; background: white; box-shadow: 0 10px 24px rgba(15,23,42,0.08); }
        .hamburger span { width: 24px; height: 2px; background: #1e293b; border-radius: 999px; transition: transform 0.25s ease, opacity 0.2s ease; }
        .hamburger.active span:nth-child(1) { transform: rotate(45deg) translate(5px, 5px); }
        .hamburger.active span:nth-child(2) { opacity: 0; }
        .hamburger.active span:nth-child(3) { transform: rotate(-45deg) translate(5px, -5px); }
        body.creditsoft-menu-open { overflow: hidden; }
        .mobile-menu { display: block; position: fixed; top: var(--creditsoft-mobile-menu-top, 152px); left: 0; right: 0; bottom: 0; z-index: 100000; padding: 12px 14px max(18px, env(safe-area-inset-bottom)); background: rgba(10, 17, 32, 0.42); backdrop-filter: blur(10px); opacity: 0; pointer-events: none; transform: translateY(-8px); transition: opacity .2s ease, transform .2s ease; }
        .mobile-menu.active { opacity: 1; pointer-events: auto; transform: translateY(0); }
        .mobile-menu__panel { width: min(100%, 520px); max-height: 100%; margin: 0 auto; overflow: hidden; border: 1px solid rgba(226,232,240,0.95); border-radius: 22px 22px 0 0; background: rgba(255,255,255,0.98); box-shadow: 0 28px 70px rgba(15,23,42,0.22); }
        .mobile-menu__inner { max-height: calc(100dvh - var(--creditsoft-mobile-menu-top, 152px) - 24px); overflow-y: auto; padding: 14px; display: grid; gap: 10px; }
        .mobile-menu a,
        .mobile-menu__trigger { min-height: 46px; display: flex; align-items: center; gap: 10px; width: 100%; padding: 11px 12px; border: 1px solid transparent; border-radius: 14px; color: #0f172a; background: transparent; text-decoration: none; font: inherit; font-size: 15px; font-weight: 800; text-align: left; }
        .mobile-menu a:hover,
        .mobile-menu__trigger:hover { background: #f8fafc; color: #0f172a; text-decoration: none; }
        .mobile-menu__trigger { justify-content: space-between; cursor: pointer; }
        .mobile-menu__trigger span,
        .mobile-menu__primary a { display: flex; align-items: center; gap: 10px; }
        .mobile-menu__trigger i:not(.mobile-menu__chevron),
        .mobile-menu__primary a i { width: 18px; color: #2563eb; text-align: center; }
        .mobile-menu__chevron { color: #64748b; font-size: 13px; transition: transform .2s ease; }
        .mobile-menu__group.is-open .mobile-menu__chevron { transform: rotate(180deg); }
        .mobile-submenu { display: none; flex-direction: column; gap: 4px; margin: 4px 0 4px 18px; padding: 4px 0 8px 12px; border-left: 2px solid #fbbf24; }
        .mobile-menu__group.is-open .mobile-submenu { display: flex; }
        .mobile-submenu a { min-height: 40px; padding: 8px 12px; color: #475569; font-size: 14px; font-weight: 750; }
        .mobile-menu__primary { display: grid; gap: 4px; }
        .mobile-menu__actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding-top: 10px; border-top: 1px solid #e2e8f0; }
        .mobile-menu .nav-cta,
        .mobile-menu .nav-secondary { min-height: 46px; justify-content: center; border-bottom: none; padding: 12px 14px; border-radius: 14px; font-size: 14px; }
        .mobile-menu .nav-cta { background: #f97316; color: white; }
        .mobile-menu .nav-secondary { border: 1px solid rgba(37,99,235,0.28); color: #1d4ed8; background: #eff6ff; }
        
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
        
        /* Homepage Pricing Preview */
        .pricing-preview { background: linear-gradient(180deg, #eef4ff 0%, #f8fafc 100%); }
        .pricing-preview h2 { text-align: center; font-size: 36px; margin-bottom: 12px; }
        .pricing-preview .subtitle { text-align: center; color: var(--gray); margin-bottom: 18px; font-size: 18px; max-width: 760px; margin-left: auto; margin-right: auto; }
        .pricing-preview-note { text-align:center; color:var(--gray); font-size:14px; margin-bottom:34px; }
        .pricing-preview-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(310px,1fr)); gap:24px; max-width:1080px; margin:0 auto; }
        .pricing-preview-card { background:white; border:2px solid var(--border); border-radius:20px; padding:34px 30px; box-shadow:0 20px 48px rgba(15,23,42,.07); transition:transform .18s,border-color .18s,box-shadow .18s; }
        .pricing-preview-card:hover { transform:translateY(-4px); border-color:#93c5fd; box-shadow:0 26px 56px rgba(15,23,42,.1); }
        .pricing-preview-card.featured { border-color:var(--primary); background:linear-gradient(135deg, rgba(37,99,235,.06), rgba(255,255,255,.96)); }
        .plan-kicker { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; font-size:12px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; background:#dbeafe; color:#1d4ed8; margin-bottom:18px; }
        .plan-kicker.alt { background:#dcfce7; color:#166534; }
        .pricing-preview-card h3 { font-size:28px; margin-bottom:8px; }
        .plan-price-stack { margin-bottom:14px; }
        .plan-list-price { display:block; font-size:15px; color:var(--gray); text-decoration:line-through; margin-bottom:4px; }
        .plan-sale-price { font-size:42px; font-weight:800; line-height:1; color:var(--primary); }
        .plan-sale-price span { font-size:16px; font-weight:500; color:var(--gray); }
        .plan-copy { color:var(--gray); margin-bottom:18px; min-height:64px; }
        .plan-meta-band { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:18px; }
        .plan-meta { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; background:#f8fafc; border:1px solid var(--border); color:#0f172a; font-size:13px; font-weight:600; }
        .plan-feature-list { list-style:none; margin:0 0 22px; padding:0; }
        .plan-feature-list li { padding:10px 0; display:flex; align-items:flex-start; gap:10px; border-bottom:1px solid var(--border); }
        .plan-feature-list li:last-child { border-bottom:none; }
        .plan-feature-list li::before { content:'✓'; color:var(--success); font-weight:700; }
        .pricing-preview-actions { display:flex; gap:12px; flex-wrap:wrap; margin-top:auto; }
        .pricing-preview-actions .btn { flex:1; min-width:180px; text-align:center; }
        .pricing-support-strip { margin:30px auto 0; max-width:1080px; display:grid; grid-template-columns:2fr 1fr; gap:20px; }
        .pricing-support-card { background:white; border:1px solid var(--border); border-radius:18px; padding:22px 24px; box-shadow:0 12px 32px rgba(15,23,42,.04); }
        .pricing-support-card h4 { font-size:17px; margin-bottom:8px; }
        .pricing-support-card p { color:var(--gray); margin:0; }
        .pricing-support-card strong { color:var(--dark); }
        
        /* Testimonials Carousel */
        .testimonials { background: white; overflow: hidden; display: none; }
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
        .comparison-shell { background: white; border-radius: 20px; box-shadow: 0 18px 42px rgba(15,23,42,.08); overflow: hidden; border: 1px solid rgba(226,232,240,.9); }
        .comparison-table { width: 100%; border-collapse: collapse; background: white; }
        .comparison-table th, .comparison-table td { padding: 16px 20px; text-align: center; border-bottom: 1px solid var(--border); vertical-align: middle; }
        .comparison-table th { background: var(--dark); color: white; font-weight: 700; font-size: 15px; }
        .comparison-table th:first-child,
        .comparison-table td:first-child { text-align: left; }
        .comparison-table td:first-child { font-weight: 700; color: #0f172a; width: 34%; }
        .our-feature { background: rgba(37, 99, 235, 0.08); font-weight: 700; color: #0f172a; }
        .them-feature { color: #475569; }
        .comparison-note { padding: 18px 20px; background: #f8fafc; color: #475569; font-size: 14px; border-top: 1px solid var(--border); }
        
        /* Office Intake */
        .waitlist { background: linear-gradient(180deg, #eef4ff 0%, #f8fafc 100%); color: var(--dark); text-align: left; padding: 82px 20px; border-top: 1px solid var(--border); }
        .waitlist-grid { max-width: 1120px; margin: 0 auto; display: grid; grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.05fr); gap: 28px; align-items: start; }
        .waitlist-intro, .form-box { background: white; border: 1px solid var(--border); border-radius: 24px; padding: 30px; box-shadow: 0 18px 40px rgba(15,23,42,0.06); }
        .waitlist h2 { font-size: 38px; line-height: 1.08; margin-bottom: 12px; }
        .waitlist .subtitle { color: var(--gray); margin-bottom: 18px; font-size: 18px; }
        .waitlist-points { list-style: none; display: grid; gap: 12px; margin: 22px 0 0; padding: 0; }
        .waitlist-points li { padding: 14px 16px; border-radius: 18px; background: #f8fafc; border: 1px solid var(--border); color: var(--dark); }
        .form-box h3 { font-size: 24px; margin-bottom: 8px; }
        .form-box p { color: var(--gray); margin-bottom: 18px; }
        .starter-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .form-group { margin-bottom: 0; text-align: left; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 700; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: var(--gray); }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 14px 16px; border: 1px solid var(--border); border-radius: 14px; background: white; color: var(--dark); font-size: 16px; font-family: inherit; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(37,99,235,0.12); }
        .btn-submit { background: var(--success); color: white; width: 100%; margin-top: 18px; }
        .btn-submit:hover { background: #059669; }
        .form-note { font-size: 13px; color: var(--gray); margin-top: 14px; }
        .error-msg { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 14px; border-radius: 14px; margin-bottom: 16px; font-size: 14px; }
        
        /* Footer */
        .footer { background: radial-gradient(circle at top left, rgba(37,99,235,0.18), transparent 26%), linear-gradient(180deg, #0a0f1a 0%, #070b13 100%); color: #94a3b8; padding: 42px 0 22px; font-size: 14px; border-top: 1px solid rgba(148,163,184,0.14); }
        .footer-shell { max-width: 1100px; margin: 0 auto; display: grid; grid-template-columns: minmax(0, 1.1fr) repeat(3, minmax(0, 0.82fr)); gap: 24px; align-items: start; }
        .footer-brand { display:grid; gap:12px; }
        .footer-brand img { height: 60px; width: auto; display:block; }
        .footer-brand p { margin:0; max-width:340px; color:#94a3b8; line-height:1.55; }
        .footer-socials { display:flex; gap:10px; flex-wrap:wrap; }
        .footer-socials a { width:40px; height:40px; border-radius:999px; border:1px solid rgba(148,163,184,0.18); background:rgba(255,255,255,0.03); display:inline-flex; align-items:center; justify-content:center; color:#e2e8f0; text-decoration:none; transition:transform .18s,border-color .18s,background .18s,color .18s; }
        .footer-socials a:hover { transform:translateY(-2px); border-color:rgba(96,165,250,0.5); background:rgba(37,99,235,0.14); color:white; }
        .footer-badge { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; border:1px solid rgba(96,165,250,0.24); background:rgba(37,99,235,0.12); color:#dbeafe; font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
        .footer h4 { color: #e2e8f0; font-size: 12px; letter-spacing:.14em; text-transform:uppercase; margin-bottom: 12px; }
        .footer ul { list-style: none; margin:0; padding:0; }
        .footer li { margin-bottom: 9px; }
        .footer a { color: #94a3b8; text-decoration: none; }
        .footer a:hover { color: #ffffff; }
        .footer-bottom { border-top: 1px solid rgba(148,163,184,0.12); padding-top: 18px; max-width: 1100px; margin: 20px auto 0; display:flex; justify-content:space-between; gap:16px; align-items:center; flex-wrap:wrap; color:#64748b; text-align:left; }
        
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
            .nav-utility__inner { padding: 9px 18px; flex-direction: column; align-items: flex-start; min-height: 0; }
            .nav-shell { grid-template-columns: 1fr auto; min-height: 78px; padding: 0 18px; }
            .nav-logo a { transform: translateY(6px); }
            .nav-logo img { height: 68px; }
            .nav-links, .nav-actions { display: none; }
            .hamburger { display: flex; }
            .hero { padding-top: 176px; }
            .carousel-nav { display: none; }
            .cookie-content { flex-direction: column; text-align: center; }
            .cookie-buttons { width: 100%; justify-content: center; }
            .pricing-support-strip { grid-template-columns: 1fr; }
            .footer-shell { grid-template-columns: 1fr; }
            .footer-bottom { flex-direction: column; align-items:flex-start; }
            .waitlist-grid,
            .starter-form-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 420px) {
            .nav-utility__inner { padding: 8px 14px; gap: 6px; }
            .nav-shell { min-height: 72px; padding: 0 12px; gap: 10px; }
            .nav-logo a { max-width: calc(100vw - 86px); transform: translateY(4px); }
            .nav-logo img { height: 58px; max-width: 100%; object-fit: contain; }
            .hamburger { width: 44px; height: 44px; flex: 0 0 44px; border-radius: 12px; }
            .mobile-menu { padding-left: 10px; padding-right: 10px; }
            .mobile-menu__panel { border-radius: 18px 18px 0 0; }
            .mobile-menu__inner { padding: 12px; }
            .mobile-menu a,
            .mobile-menu__trigger { font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="toast-container" id="toastContainer"></div>
    
    <section class="hero">
        <?php require __DIR__ . '/shared-nav.php'; ?>
        <div class="hero-content">
            <div class="badge">🎯 <span><?= htmlspecialchars((string) ($home_content['hero_badge'] ?? 'Metro2-First Credit Repair'), ENT_QUOTES, 'UTF-8') ?></span></div>
            <h1><?= htmlspecialchars((string) ($home_content['hero_title_primary'] ?? 'Compliance-First Credit Repair Platform'), ENT_QUOTES, 'UTF-8') ?><?php if (trim((string) ($home_content['hero_title_highlight'] ?? '')) !== ''): ?><br><span><?= htmlspecialchars((string) $home_content['hero_title_highlight'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?></h1>
            <p><?= htmlspecialchars((string) ($home_content['hero_copy'] ?? 'Operate an AI-driven, local-first CRM and intranet, manage Metro2 cases correctly, and deploy a branded website seamlessly tied to intake, client portal, and automated updates.'), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="cta-group">
                <a href="<?= htmlspecialchars((string) ($home_content['primary_cta_href'] ?? '/subscribe'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary"><?= htmlspecialchars((string) ($home_content['primary_cta_label'] ?? 'Start Office Fit Check'), ENT_QUOTES, 'UTF-8') ?></a>
                <a href="<?= htmlspecialchars((string) ($home_content['secondary_cta_href'] ?? '/lawsuit-test'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline"><?= htmlspecialchars((string) ($home_content['secondary_cta_label'] ?? 'Take the Red Flags Quiz'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        </div>
    </section>
    
    <!-- Early Adopters -->
    <section class="early-adopters">
        <div class="container">
            <h2>🚀 Early Adopters</h2>
            <p class="subtitle">Join offices already moving into the CreditSoft rollout queue</p>
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
                    <h3>30+ Metro2 Error Codes Covered</h3>
                    <p>AI-driven detection for Metro2 violations — identifying account mismatches, inaccurate late payments, and invalid collections with precision.</p>
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
                    <h3>Branded Website + Portal</h3>
                    <p>Managed public front end tied into the CRM/intranet so leads, portal logins, and client updates stay in one branded lane.</p>
                </a>
                <a href="/reporting" class="feature-card">
                    <div class="feature-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg></div>
                    <h3>Monthly Comparisons</h3>
                    <p>Track credit score changes over time. Visual reports showing progress and new issues.</p>
                </a>
                <a href="/email-delivery" class="feature-card">
                    <div class="feature-icon"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l8.4 5.6a1 1 0 001.2 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg></div>
                    <h3>SMTP + Email Delivery</h3>
                    <p>Microsoft 365, Google Workspace, Amazon SES, SendGrid, Mailgun, Zoho Mail, Postmark, Brevo, SMTP.com, and custom SMTP support for CRM notices, billing follow-up, and provider-login alerts.</p>
                </a>
            </div>
        </div>
    </section>
    
    <section class="pricing-preview section-pad" id="pricing">
        <div class="container">
            <h2><?= htmlspecialchars((string) ($home_content['pricing_heading'] ?? 'Packaging built for real offices.'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="subtitle"><?= htmlspecialchars((string) ($home_content['pricing_subtitle'] ?? 'Choose the software lane that fits your workflow, then add the browser companion, branded website, or legal-intake lane only if you need them.'), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="pricing-preview-note"><?= htmlspecialchars((string) ($home_content['pricing_note'] ?? 'The homepage stays focused on the product. Full plan details, discounts, and add-ons live on the pricing page.'), ENT_QUOTES, 'UTF-8') ?></p>

            <div class="pricing-support-strip">
                <div class="pricing-support-card">
                    <h4>Packaged software lanes</h4>
                    <p>Local-first CRM and intranet workflows for Metro2 review, letters, briefs, audit trails, client portals, and office operations.</p>
                </div>
                <div class="pricing-support-card">
                    <h4>Browser companion</h4>
                    <p>Office-paired browser automation for supported provider imports, direct API capture routing, and less manual intake work.</p>
                </div>
                <div class="pricing-support-card">
                    <h4>Managed websites</h4>
                    <p>Branded public sites tied into CreditSoft CRM, portal, intake, and intranet handoff instead of a disconnected brochure.</p>
                </div>
            </div>

            <div class="pricing-preview-actions" style="justify-content:center;margin-top:2rem;">
                <a href="/pricing" class="btn btn-primary">See Full Pricing</a>
                <a href="/subscribe?plan=enterprise" class="btn btn-outline" style="color:var(--primary);border-color:var(--primary)">Start Free Trial</a>
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
            <h2>CreditSoft vs the usual stack</h2>
            <p class="subtitle">A cleaner view of what is built in here versus what offices usually have to piece together somewhere else.</p>
            <div class="comparison-shell">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Feature</th>
                            <th>CreditSoft</th>
                            <th>Typical stack</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Intranet based workspace</td><td class="our-feature">Yes. The office runs from the intranet itself.</td><td class="them-feature">Usually not. Often a cloud CRM plus scattered tools.</td></tr>
                        <tr><td>PWA support</td><td class="our-feature">Yes. Installable office experience on supported systems.</td><td class="them-feature">Usually browser-only without a real install feel.</td></tr>
                        <tr><td>Metro2 error detection</td><td class="our-feature">30+ codes built in</td><td class="them-feature">Usually limited, basic, or manual.</td></tr>
                        <tr><td>50-state CRO rules</td><td class="our-feature">Built in</td><td class="them-feature">Often external, partial, or an add-on lane.</td></tr>
                        <tr><td>FCRA / FDCPA workflow support</td><td class="our-feature">Built into the working system</td><td class="them-feature">Usually present in pieces, not as the core product shape.</td></tr>
                        <tr><td>SMTP/email providers</td><td class="our-feature">Microsoft 365, Google Workspace, Amazon SES, SendGrid, Mailgun, Zoho Mail, Postmark, Brevo, SMTP.com, and custom SMTP</td><td class="them-feature">Often Zapier, manual SMTP, or a separate SaaS add-on.</td></tr>
                        <tr><td>Browser companion automation</td><td class="our-feature">Office-paired companion lane</td><td class="them-feature">Often missing or pushed into third-party automations.</td></tr>
                        <tr><td>Branded websites and portal</td><td class="our-feature">Part of the same stack</td><td class="them-feature">Usually separate vendors, extra fees, or disconnected pages.</td></tr>
                        <tr><td>Billing and revenue workspace</td><td class="our-feature">Inside the office layer</td><td class="them-feature">Usually tracked somewhere else entirely.</td></tr>
                        <tr><td>PII on public-facing sites</td><td class="our-feature">Not the default lane</td><td class="them-feature">Often routed through hosted public forms and external pages.</td></tr>
                        <tr><td>Update server control</td><td class="our-feature">Your update lane</td><td class="them-feature">Usually their server, their cadence, their dependency.</td></tr>
                    </tbody>
                </table>
                <div class="comparison-note">The point is not naming competitors. The point is showing that CreditSoft is shaped like an office operating system, not just a dispute editor bolted onto other services.</div>
            </div>
        </div>
    </section>
    
    <section class="waitlist" id="waitlist">
        <div class="waitlist-grid">
            <div class="waitlist-intro">
                <h2><?= htmlspecialchars((string) ($home_content['fit_check_heading'] ?? 'Start the office fit check.'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="subtitle"><?= htmlspecialchars((string) ($home_content['fit_check_subtitle'] ?? 'The homepage should not end in a random waitlist box. Start here, then we take you to the second step where we learn how your office works right now.'), ENT_QUOTES, 'UTF-8') ?></p>
                <ul class="waitlist-points">
                    <li>Step 1 saves the office contact so the intake does not disappear if they get interrupted.</li>
                    <li>Step 2 asks about client volume, monitoring sources, current software, merchant setup, website, outsourcing, and ROI visibility.</li>
                    <li>That gives us a real rollout conversation instead of a useless name-and-email waitlist.</li>
                </ul>
            </div>
            <div class="form-box">
                <h3>Kick off the intake</h3>
                <p><?= htmlspecialchars((string) ($home_content['fit_check_intro'] ?? 'Give us the basic office contact here. The next screen handles the real qualification questions.'), ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                <form method="POST" action="/subscribe">
                    <input type="hidden" name="intake_stage" value="starter">
                    <input type="hidden" name="lead_source" value="homepage_intake">
                    <input type="hidden" name="plan_interest" value="<?= htmlspecialchars($starter_plan, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="starter-form-grid">
                        <div class="form-group">
                            <label for="starterName">Name</label>
                            <input id="starterName" type="text" name="name" required placeholder="Your name" value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="form-group">
                            <label for="starterEmail">Work email</label>
                            <input id="starterEmail" type="email" name="email" required placeholder="you@company.com" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="form-group">
                            <label for="starterCompany">Office name</label>
                            <input id="starterCompany" type="text" name="company" placeholder="Office or company name" value="<?= htmlspecialchars($_POST['company'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="form-group">
                            <label for="starterPhone">Phone</label>
                            <input id="starterPhone" type="tel" name="phone" placeholder="(555) 555-5555" value="<?= htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-submit">Continue to office questions</button>
                    <p class="form-note">This goes to the second form. We use the extra step to understand your current stack before we talk rollout, migration, or pricing.</p>
                </form>
            </div>
        </div>
    </section>
    
    <?php require __DIR__ . '/shared-footer.php'; ?>
    
    <!-- Chat Widget -->
    <div class="chat-widget">
        <div class="chat-header" onclick="toggleChat()"><span>💬 Chat</span><span id="chatToggle">+</span></div>
        <div class="chat-body collapsed" id="chatBody">
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
                <p class="mt-1 text-xs text-gray">Complies with: GDPR, CCPA/CPRA, DMA, LGPD, POPIA, Google Consent Mode v2</p>
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
    
    // Show proof toasts on load
    setTimeout(() => showToast('🚀', 'Ashley M. started a rollout review', 'Kansas City, MO - Credit Repair Pro'), 2000);
    setTimeout(() => showToast('👋', 'Sarah J. is moving off legacy tools', 'Austin, TX'), 5000);
    setTimeout(() => showToast('⭐', 'Maria K. requested a migration plan', 'CA - 5 star feedback'), 8000);
    
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
    let chatOpen = false;
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
