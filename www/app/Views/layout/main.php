<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'CreditSoft' ?></title>
    <meta name="description" content="<?= $page_description ?? 'Metro2-First Credit Repair Software' ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-9QJTYCN2FZ"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'G-9QJTYCN2FZ');
    </script>
    <!-- Meta Pixel placeholder -->
    <!--
    <script>
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window,document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init','YOUR_PIXEL_ID');fbq('track','PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id=YOUR_PIXEL_ID&ev=PageView&noscript=1"/></noscript>
    -->
    <?= $this->renderSection('styles') ?>
    <style>
        :root { --primary: #2563eb; --primary-dark: #1d4ed8; --success: #10b981; --dark: #0f172a; --light: #f8fafc; --gray: #64748b; --border: #e2e8f0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: var(--light); color: var(--dark); line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .btn { display: inline-block; padding: 14px 28px; border-radius: 10px; font-size: 16px; font-weight: 600; text-decoration: none; transition: all 0.2s; cursor: pointer; border: none; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); text-decoration: none; }
        .nav { position: absolute; top: 0; left: 0; right: 0; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; z-index: 100; }
        .nav-logo img { height: 70px; }
        .nav-links { display: flex; gap: 32px; align-items: center; }
        .nav-links a { color: white; text-decoration: none; font-weight: 500; opacity: 0.9; transition: opacity 0.2s; }
        .nav-links a:hover { opacity: 1; text-decoration: none; }
        .nav-cta { background: var(--primary); padding: 10px 20px; border-radius: 8px; }
        
        .footer { background: #0a0f1a; color: var(--gray); padding: 60px 0 30px; font-size: 14px; }
        .footer-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 40px; max-width: 1100px; margin: 0 auto 40px; text-align: left; }
        .footer h4 { color: white; font-size: 16px; margin-bottom: 16px; }
        .footer ul { list-style: none; }
        .footer li { margin-bottom: 10px; }
        .footer a { color: var(--gray); text-decoration: none; transition: color 0.2s; }
        .footer a:hover { color: var(--primary); text-decoration: none; }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,0.1); padding-top: 30px; max-width: 1100px; margin: 0 auto; text-align: center; }
        .footer-bottom p { opacity: 0.7; }
        
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
        .toast { background: var(--dark); color: white; padding: 16px 24px; border-radius: 10px; margin-bottom: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); animation: slideIn 0.3s ease; display: flex; align-items: center; gap: 12px; }
        .toast-success { border-left: 4px solid var(--success); }
        .toast-error { border-left: 4px solid #ef4444; }
        .toast-info { border-left: 4px solid var(--primary); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        
        .legal-hero { background: var(--dark); color: white; padding: 120px 20px 60px; text-align: center; }
        .legal-hero h1 { font-size: 42px; margin-bottom: 12px; }
        .legal-content { max-width: 800px; margin: 0 auto; padding: 60px 20px; }
        .legal-content h2 { font-size: 24px; margin: 32px 0 16px; color: var(--dark); }
        .legal-content p, .legal-content li { color: var(--gray); line-height: 1.7; margin-bottom: 12px; }
        .legal-content ul { padding-left: 24px; margin-bottom: 24px; }
        .last-updated { color: var(--gray); font-size: 14px; margin-bottom: 40px; }
        
        @media (max-width: 768px) {
            .nav { padding: 16px 20px; }
            .nav-links { display: none; }
        }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-logo">
            <a href="/"><img src="/assets/images/CreditSoft.png" alt="CreditSoft"></a>
        </div>
        <div class="nav-links">
            <a href="/#features">Features</a>
            <a href="/pricing">Pricing</a>
            <a href="/presales-tips">Pre-Sales Tips</a>
            <a href="/subscribe" class="nav-cta">Get Early Access</a>
        </div>
    </nav>

    <?= $this->renderSection('content') ?>

    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <h4>Product</h4>
                    <ul>
                        <li><a href="/#features">Features</a></li>
                        <li><a href="/pricing">Pricing</a></li>
                        <li><a href="/presales-tips">Pre-Sales Tips</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="/terms">Terms of Service</a></li>
                        <li><a href="/privacy">Privacy Policy</a></li>
                        <li><a href="/cookies">Cookie Policy</a></li>
                        <li><a href="/legal">Legal</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Support</h4>
                    <ul>
                        <li><a href="/subscribe">Get Early Access</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© 2026 · <a href="https://www.matthewxmurphy.com" target="_blank" rel="noopener">Matthew Murphy</a> · <a href="https://www.net30hosting.com" target="_blank" rel="noopener">Hosted on Net30Hosting</a></p>
            </div>
        </div>
    </footer>
    
    <div class="toast-container" id="toastContainer"></div>
    
    <?= $this->renderSection('scripts') ?>
</body>
</html>
