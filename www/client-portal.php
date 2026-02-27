<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal - CreditSoft</title>
    <meta name="description" content="Two-layer client portal: Intranet (local encrypted) + Website Widget. Bank-level security with white-label options.">
    <link rel="canonical" href="https://www.creditsoft.app/client-portal">
    <meta property="og:url" content="https://www.creditsoft.app/client-portal">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-9QJTYCN2FZ"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-9QJTYCN2FZ');</script>
    <style>
        :root{--primary:#2563eb;--dark:#0f172a;--light:#f8fafc;--gray:#64748b;--border:#e2e8f0;--success:#10b981}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',sans-serif;background:var(--light);color:var(--dark);line-height:1.6}
        .hero{background:linear-gradient(135deg,#0f172a,#1e3a5f,#2563eb);color:white;padding:140px 20px 60px}
        .hero h1{font-size:38px;font-weight:700;margin-bottom:12px}
        .hero p{opacity:0.9;max-width:700px}
        .nav{position:absolute;top:0;left:0;right:0;padding:20px 40px;display:flex;justify-content:space-between;align-items:center;z-index:100}
        .nav-logo img{height:70px}
        .nav-links{display:flex;gap:28px}
        .nav-links a{color:white;text-decoration:none;font-weight:500}
        .container{max-width:900px;margin:0 auto;padding:40px 20px}
        .intro{background:white;padding:32px;border-radius:16px;margin-bottom:24px}
        .intro h2{font-size:24px;margin-bottom:12px}
        .intro p{color:var(--gray)}
        .two-layer{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px}
        .layer-card{background:white;padding:32px;border-radius:16px}
        .layer-card h3{font-size:20px;margin-bottom:16px;display:flex;align-items:center;gap:12px}
        .layer-card .icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px}
        .layer-card.local .icon{background:#dbeafe;color:var(--primary)}
        .layer-card.widget .icon{background:#d1fae5;color:var(--success)}
        .layer-card ul{list-style:none;padding:0}
        .layer-card li{padding:8px 0;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px}
        .layer-card li:last-child{border:none}
        .layer-card li::before{content:'✓';color:var(--success);font-weight:bold}
        .how-it-works{background:white;padding:32px;border-radius:16px;margin-bottom:24px}
        .how-it-works h2{font-size:24px;margin-bottom:20px}
        .step{display:flex;gap:16px;padding:16px 0;border-bottom:1px solid var(--border)}
        .step:last-child{border:none}
        .step-num{width:36px;height:36px;background:var(--primary);color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0}
        .step h4{font-size:16px;margin-bottom:4px}
        .step p{font-size:14px;color:var(--gray)}
        .comparison{background:white;padding:32px;border-radius:16px;margin-bottom:24px}
        .comparison h2{font-size:24px;margin-bottom:20px}
        .comp-table{width:100%;border-collapse:collapse}
        .comp-table th,.comp-table td{padding:14px;text-align:left;border-bottom:1px solid var(--border)}
        .comp-table th{background:var(--light);font-weight:600}
        .comp-table .our{background:#d1fae5}
        .comp-table .competitor{background:#fee2e2}
        .vpn-section{background:linear-gradient(135deg,#0f172a,#1e3a5f);color:white;padding:32px;border-radius:16px;margin-bottom:24px}
        .vpn-section h3{font-size:20px;margin-bottom:12px}
        .vpn-section p{opacity:0.9;font-size:15px}
        .vpn-section ul{margin-top:16px;padding-left:20px}
        .vpn-section li{margin-bottom:8px}
        .example{background:white;padding:32px;border-radius:16px;margin-bottom:24px}
        .example h2{font-size:24px;margin-bottom:16px}
        .screenshot{background:var(--light);padding:20px;border-radius:12px;text-align:center;margin-bottom:16px}
        .screenshot p{font-size:14px;color:var(--gray)}
        .footer{background:#0a0f1a;color:var(--gray);padding:40px 0;text-align:center;font-size:14px}
        .footer a{color:var(--primary);text-decoration:none}
        @media(max-width:768px){.nav-links{display:none};.two-layer{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-logo"><a href="/"><img src="/assets/images/CreditSoft.png" alt="CreditSoft"></a></div>
        <div class="nav-links">
            <a href="/#features">Features</a>
            <a href="/pricing">Pricing</a>
            <a href="/about">About</a>
            <a href="/#waitlist" style="background:var(--primary);padding:10px 20px;border-radius:8px;">Get Early Access</a>
        </div>
    </nav>
    <section class="hero">
        <h1>Client Portal</h1>
        <p>Two layers of security. Your clients see progress without you hosting sensitive data on public sites.</p>
    </section>
    <div class="container">
        <div class="intro">
            <h2>The CreditSoft Difference</h2>
            <p>Most credit repair software hosts your client data on THEIR servers. We don't. CreditSoft gives you two secure layers:</p>
        </div>
        <div class="two-layer">
            <div class="layer-card local">
                <h3><span class="icon">💻</span> Layer 1: Intranet</h3>
                <p style="color:var(--gray);margin-bottom:16px;font-size:14px;">Your private, local credit repair software</p>
                <ul>
                    <li>Runs on YOUR computer/server</li>
                    <li>Bank-level AES-256 encryption</li>
                    <li>Only YOUR network can access it</li>
                    <li>Full client management</li>
                    <li>Dispute letter generation</li>
                    <li>Metro2 error detection</li>
                    <li>No monthly "cloud" fees</li>
                </ul>
            </div>
            <div class="layer-card widget">
                <h3><span class="icon">🌐</span> Layer 2: Client Widget</h3>
                <p style="color:var(--gray);margin-bottom:16px;font-size:14px;">What your clients see on YOUR website</p>
                <ul>
                    <li>White-labeled (your branding)</li>
                    <li>Clients see ONLY their progress</li>
                    <li>No sensitive data on your site</li>
                    <li>Links to your site or standalone</li>
                    <li>Real-time updates</li>
                    <li>Mobile responsive</li>
                    <li>Works with any website</li>
                </ul>
            </div>
        </div>
        <div class="vpn-section">
            <h3>🔐 Remote Access with VPN</h3>
            <p>Need to access your intranet from another location? Here's how it works:</p>
            <ul>
                <li>Connect via your own VPN (we support Tailnet, WireGuard, OpenVPN, etc.)</li>
                <li>Your intranet is now accessible ONLY via the VPN</li>
                <li>When you're remote, connect to VPN, then access your software</li>
                <li>Your data never touches the public internet unencrypted</li>
                <li>It's like having your office computer with you, securely</li>
            </ul>
        </div>
        <div class="how-it-works">
            <h2>How It Works</h2>
            <div class="step">
                <div class="step-num">1</div>
                <div><h4>Run CreditSoft on your computer</h4><p>Install locally. Your data stays on your machine.</p></div>
            </div>
            <div class="step">
                <div class="step-num">2</div>
                <div><h4>Add your clients</h4><p>Import credit reports, track disputes, manage everything locally.</p></div>
            </div>
            <div class="step">
                <div class="step-num">3</div>
                <div><h4>Generate client widget code</h4><p>One click generates a secure code snippet for your website.</p></div>
            </div>
            <div class="step">
                <div class="step-num">4</div>
                <div><h4>Client sees progress only</h4><p>Your client logs into YOUR website widget. They see updates. No sensitive data leaves your computer.</p></div>
            </div>
        </div>
        <div class="comparison">
            <h2>vs. Competitors</h2>
            <table class="comp-table">
                <tr><th>Feature</th><th class="our">CreditSoft</th><th class="competitor">CDM / CRC</th></tr>
                <tr><td>Client data location</td><td class="our">Your server</td><td class="competitor">Their cloud</td></tr>
                <tr><td>PII on your website</td><td class="our">None</td><td class="competitor">Yes</td></tr>
                <tr><td>White-label</td><td class="our">Full</td><td class="competitor">Limited</td></tr>
                <tr><td>Monthly fees</td><td class="our">Software only</td><td class="competitor">Plus cloud storage</td></tr>
                <tr><td>Data portability</td><td class="our">You own it</td><td class="competitor">Stuck on their platform</td></tr>
            </table>
        </div>
        <div class="example">
            <h2>What Your Clients See</h2>
            <div class="screenshot">
                <div style="background:white;padding:24px;border-radius:8px;max-width:400px;margin:0 auto;text-align:left">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;border-bottom:1px solid var(--border);padding-bottom:16px">
                        <div style="width:40px;height:40px;background:var(--primary);border-radius:8px"></div>
                        <div><div style="font-weight:600">Your Company Name</div><div style="font-size:12px;color:var(--gray)">Client Portal</div></div>
                    </div>
                    <div style="margin-bottom:16px"><div style="font-size:12px;color:var(--gray);margin-bottom:4px">Credit Score</div><div style="font-size:32px;font-weight:700;color:var(--success)">720</div></div>
                    <div style="margin-bottom:16px"><div style="font-size:12px;color:var(--gray);margin-bottom:4px">Items Disputed</div><div style="font-weight:600">12 items</div></div>
                    <div style="margin-bottom:16px"><div style="font-size:12px;color:var(--gray);margin-bottom:4px">Progress</div><div style="background:var(--light);height:8px;border-radius:4px"><div style="background:var(--success);width:65%;height:100%;border-radius:4px"></div></div><div style="font-size:12px;color:var(--gray);margin-top:4px">65% Complete</div></div>
                    <div style="padding:12px;background:var(--light);border-radius:8px;font-size:13px">✅ Late payment removed<br>✅ Collection deleted<br>⏳ Dispute in progress</div>
                </div>
            </div>
            <p>Your logo, your colors, your website. Client sees ONLY progress - no SSN, no addresses, no sensitive data.</p>
        </div>
    </div>
    <footer class="footer"><p>© 2026 CreditSoft · <a href="/pricing">Pricing</a> · <a href="/privacy">Privacy</a></p></footer>
</body>
</html>
