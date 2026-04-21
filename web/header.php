<?php
$page_title = $page_title ?? 'CreditSoft';
$page_description = $page_description ?? 'Metro2-First Credit Repair Software';
$page_hero = $page_hero ?? false;
$hero_title = $hero_title ?? '';
$hero_subtitle = $hero_subtitle ?? '';
$hero_class = trim((string) ($hero_class ?? ''));
$page_robots = $page_robots ?? 'index, follow';
$page_canonical_url = $page_canonical_url ?? 'https://www.creditsoft.app/' . basename($_SERVER['REQUEST_URI'], '.php');
require_once __DIR__ . '/site-tracking-config.php';
require_once __DIR__ . '/site-seo-config.php';
$tracking = creditsoft_site_tracking_load();
$googleMeasurementId = trim((string) ($tracking['google_measurement_id'] ?? ''));
$metaPixelId = trim((string) ($tracking['meta_pixel_id'] ?? ''));
$page_slug = $page_slug ?? trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$page_seo = creditsoft_site_seo_effective_for_slug($page_slug, [
    'title' => trim((string) ($page_title ?? 'CreditSoft')) . ' - CreditSoft',
    'description' => trim((string) ($page_description ?? 'Metro2-First Credit Repair Software')),
    'og_image' => $page_og_image ?? '/assets/images/og-image.png',
]);
$page_meta_title = trim((string) ($page_seo['title'] ?? 'CreditSoft'));
$page_meta_description = trim((string) ($page_seo['description'] ?? 'Metro2-First Credit Repair Software'));
$page_meta_image = trim((string) ($page_seo['og_image'] ?? '/assets/images/og-image.png'));
$page_meta_image = str_starts_with($page_meta_image, 'http://') || str_starts_with($page_meta_image, 'https://')
    ? $page_meta_image
    : ('https://www.creditsoft.app' . $page_meta_image);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_meta_title, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($page_meta_description, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="<?= htmlspecialchars($page_robots, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="canonical" href="<?= htmlspecialchars($page_canonical_url, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($page_canonical_url, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($page_meta_title, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($page_meta_description, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($page_meta_image, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($page_meta_title, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($page_meta_description, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($page_meta_image, ENT_QUOTES, 'UTF-8') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="/assets/css/main.css">
    <?php if ($googleMeasurementId !== ''): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($googleMeasurementId, ENT_QUOTES, 'UTF-8') ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '<?= htmlspecialchars($googleMeasurementId, ENT_QUOTES, 'UTF-8') ?>');
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
        body { font-family: 'Inter', sans-serif; background: var(--light); color: var(--dark); line-height: 1.6; padding: 0; margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .container-narrow { max-width: 800px; margin: 0 auto; padding: 0 24px; }
        .page-content { padding: 60px 24px; max-width: 1200px; margin: 0 auto; }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .btn { display: inline-block; padding: 14px 28px; border-radius: 10px; font-size: 16px; font-weight: 600; text-decoration: none; transition: all 0.2s; border: none; cursor: pointer; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); text-decoration: none; }
        
        .site-header { position: absolute; top: 0; left: 0; right: 0; z-index: 120; }
        .nav-utility { position: relative; z-index: 3; background: #0a1120; color: #dbe5f2; border-bottom: 1px solid rgba(255,255,255,0.08); box-shadow: 0 10px 24px rgba(2, 6, 23, 0.18); }
        .nav-utility__inner { max-width: 1380px; margin: 0 auto; padding: 11px 26px; display: flex; justify-content: space-between; gap: 18px; align-items: center; min-height: 42px; }
        .nav-utility__copy { font-size: 13px; line-height: 1.35; color: #e2e8f0; font-weight: 700; letter-spacing: .01em; }
        .nav-utility__contact { display: inline-flex; align-items: center; gap: 8px; color: #f8fafc; font-weight: 700; font-size: 13px; text-decoration: none; white-space: nowrap; }
        .nav-utility__contact svg { width: 16px; height: 16px; fill: currentColor; opacity: .92; }
        .nav-utility__contact:hover { color: #93c5fd; text-decoration: none; }
        .nav { position: relative; top: auto; left: auto; right: auto; z-index: auto; display: block; padding: 0; background: rgba(255,255,255,0.98); border-bottom: 1px solid rgba(15,23,42,0.08); box-shadow: 0 18px 44px rgba(15,23,42,0.08); }
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
            left: 50%;
            width: min(880px, calc(100vw - 32px));
            padding: 16px;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid rgba(148, 163, 184, 0.22);
            box-shadow: 0 28px 70px rgba(15, 23, 42, 0.18);
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            opacity: 0;
            visibility: hidden;
            transform: translate(-50%, 10px);
            transition: opacity .2s ease, transform .2s ease, visibility .2s ease;
            z-index: 40;
        }
        @media (max-width: 980px) {
            .nav-dropdown { grid-template-columns: 1fr; width: min(560px, calc(100vw - 32px)); }
        }
        .nav-dropdown::before {
            content: '';
            position: absolute;
            top: -8px;
            left: calc(50% - 8px);
            width: 16px;
            height: 16px;
            background: rgba(255, 255, 255, 0.98);
            border-left: 1px solid rgba(148, 163, 184, 0.22);
            border-top: 1px solid rgba(148, 163, 184, 0.22);
            transform: rotate(45deg);
        }
        .nav-item--has-menu:hover .nav-dropdown,
        .nav-item--has-menu:focus-within .nav-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translate(-50%, 0);
        }
        .nav-dropdown__item {
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr);
            gap: 14px;
            align-items: start;
            padding: 14px 16px;
            border-radius: 16px;
            color: #0f172a;
            text-decoration: none;
            background: transparent;
            transition: background .18s ease, transform .18s ease;
            text-align: left;
            white-space: normal;
        }
        .nav-links .nav-dropdown__item,
        .nav-links .nav-dropdown__copy strong,
        .nav-links .nav-dropdown__copy span {
            white-space: normal;
        }
        .nav-dropdown__item::after { display: none; }
        .nav-dropdown__item:hover {
            background: #eff6ff;
            transform: translateX(2px);
            text-decoration: none;
        }
        .nav-dropdown__icon {
            width: 34px;
            height: 34px;
            border-radius: 0;
            background: transparent;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #2563eb;
        }
        .nav-dropdown__icon svg { width: 22px; height: 22px; fill: currentColor; }
        .nav-dropdown__icon i { font-size: 22px; line-height: 1; }
        .nav-dropdown__copy { display: grid; gap: 4px; justify-items: start; text-align: left; min-width: 0; }
        .nav-dropdown__copy strong { display: block; width: 100%; font-size: 16px; line-height: 1.2; color: #0f172a; text-align: left; }
        .nav-dropdown__copy span { display: block; width: 100%; font-size: 13px; line-height: 1.55; color: #64748b; font-weight: 500; max-width: none; text-align: left; }
        .nav-actions { display: flex; align-items: center; gap: 10px; }
        .nav-cta { display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 10px; background: #f97316; color: white; font-weight: 800; font-size: 14px; text-decoration: none; box-shadow: 0 10px 22px rgba(249,115,22,0.18); white-space: nowrap; }
        .nav-cta:hover { background: #ea580c; transform: translateY(-1px); text-decoration: none; }
        .nav-secondary { display: inline-flex; align-items: center; justify-content: center; padding: 9px 16px; border-radius: 10px; border: 2px solid #93c5fd; color: #1d4ed8; font-weight: 800; font-size: 14px; text-decoration: none; background: rgba(255,255,255,0.85); white-space: nowrap; }
        .nav-secondary:hover { background: #eff6ff; text-decoration: none; }
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
        
        .hero { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #2563eb 100%); color: white; padding: 206px 20px 82px; }
        .hero-shell { max-width: 1380px; margin: 0 auto; padding: 0 26px; }
        .hero-content { max-width: 900px; margin: 0 auto; text-align: center; }
        .hero h1 { font-size: 42px; font-weight: 800; margin-bottom: 12px; }
        .hero p { font-size: 18px; opacity: 0.9; max-width: 600px; margin: 0 auto; }
        .hero--left .hero-content { max-width: 860px; margin: 0; text-align: left; }
        .hero--left p { max-width: 760px; margin: 0; }
        .hero--legal { background: var(--dark); }
        
        .main-content { padding: 60px 24px; max-width: 1200px; margin: 0 auto; }
        .nav-spacer { height: 138px; }
        
        .footer { background: #0a0f1a; color: var(--gray); padding: 40px 0; text-align: center; font-size: 14px; }
        .footer a { color: var(--primary); text-decoration: none; }
        
        @media(max-width: 768px) { 
            .nav-utility__inner { padding: 9px 18px; flex-direction: column; align-items: flex-start; min-height: 0; }
            .nav-shell { grid-template-columns: 1fr auto; min-height: 78px; padding: 0 18px; }
            .nav-logo a { transform: translateY(6px); }
            .nav-logo img { height: 68px; }
            .nav-links, .nav-actions { display: none; }
            .hamburger { display: flex; }
            .hamburger span { background: #1e293b; }
            .hero { padding-top: 186px; }
            .hero-shell { padding: 0 18px; }
            .nav-spacer { height: 130px; }
        }
        @media(max-width: 420px) {
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
    <?php require __DIR__ . '/shared-nav.php'; ?>
    <?php if (! $page_hero): ?>
    <div class="nav-spacer"></div>
    <?php endif; ?>
    <?php if ($page_hero): ?>
    <section class="hero<?= $hero_class !== '' ? ' ' . htmlspecialchars($hero_class, ENT_QUOTES, 'UTF-8') : '' ?>">
        <div class="hero-shell">
            <div class="hero-content">
                <h1><?= $hero_title ?></h1>
                <p><?= $hero_subtitle ?></p>
            </div>
        </div>
    </section>
    <?php endif; ?>
