<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>50-State CRO Rules - CreditSoft</title>
    <meta name="description" content="Credit Repair Organization requirements by state. Bond amounts, registration, fee limits - know your state's requirements.">
    <link rel="canonical" href="https://www.creditsoft.app/cro-rules">
    <meta property="og:url" content="https://www.creditsoft.app/cro-rules">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-9QJTYCN2FZ"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-9QJTYCN2FZ');</script>
    <style>
        :root{--primary:#2563eb;--dark:#0f172a;--light:#f8fafc;--gray:#64748b;--border:#e2e8f0;--success:#10b981;--warning:#f59e0b;--danger:#ef4444}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',sans-serif;background:var(--light);color:var(--dark);line-height:1.6}
        .hero{background:linear-gradient(135deg,#0f172a,#1e3a5f,#2563eb);color:white;padding:140px 20px 60px}
        .hero h1{font-size:38px;font-weight:700;margin-bottom:12px}
        .hero p{opacity:0.9}
        .nav{position:absolute;top:0;left:0;right:0;padding:20px 40px;display:flex;justify-content:space-between;align-items:center;z-index:100}
        .nav-logo img{height:70px}
        .nav-links{display:flex;gap:28px}
        .nav-links a{color:white;text-decoration:none;font-weight:500}
        .container{max-width:900px;margin:0 auto;padding:40px 20px}
        .intro{background:white;padding:32px;border-radius:16px;margin-bottom:24px}
        .intro h2{font-size:24px;margin-bottom:12px}
        .intro p{color:var(--gray)}
        .alert{background:#fef3c7;border:1px solid #fcd34d;border-radius:12px;padding:16px;margin-bottom:24px}
        .alert-title{font-weight:600;color:#92400e;margin-bottom:4px}
        .alert p{font-size:14px;color:#92400e}
        .state-selector{background:white;padding:24px;border-radius:16px;margin-bottom:24px}
        .state-selector label{display:block;font-weight:600;margin-bottom:12px}
        .state-selector select{width:100%;padding:14px;border:2px solid var(--border);border-radius:10px;font-size:16px}
        .state-info{display:none}
        .state-info.active{display:block}
        .state-header{background:linear-gradient(135deg,var(--primary),#3b82f6);color:white;padding:24px;border-radius:16px;margin-bottom:20px}
        .state-header h3{font-size:24px;margin-bottom:8px}
        .requirements{background:white;padding:24px;border-radius:16px}
        .req-item{display:flex;gap:12px;padding:16px 0;border-bottom:1px solid var(--border)}
        .req-item:last-child{border-bottom:none}
        .req-icon{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
        .req-icon.required{background:#fee2e2;color:var(--danger)}
        .req-icon.optional{background:#d1fae5;color:var(--success)}
        .req-icon.info{background:#dbeafe;color:var(--primary)}
        .req-content h4{font-size:16px;margin-bottom:4px}
        .req-content p{font-size:14px;color:var(--gray)}
        .all-states{background:white;padding:24px;border-radius:16px}
        .states-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px}
        .state-badge{display:block;padding:8px 12px;background:var(--light);border-radius:8px;text-align:center;font-size:14px;color:var(--gray);text-decoration:none}
        .state-badge:hover{background:var(--primary);color:white}
        .footer{background:#0a0f1a;color:var(--gray);padding:40px 0;text-align:center;font-size:14px}
        .footer a{color:var(--primary);text-decoration:none}
        @media(max-width:768px){.nav-links{display:none}}
    </style>
</head>
<body>
    <nav class="nav">
        <div class="nav-logo"><a href="/"><img src="/assets/images/CreditSoft.png" alt="CreditSoft"></a></div>
        <div class="nav-links">
            <a href="/#features">Features</a>
            <a href="/pricing">Pricing</a>
            <a href="/about">About</a>
            <a href="/client-portal">Client Portal</a>
            <a href="/#waitlist" style="background:var(--primary);padding:10px 20px;border-radius:8px;">Get Early Access</a>
        </div>
    </nav>
    <section class="hero">
        <h1>50-State CRO Rules</h1>
        <p>Know your state's requirements. We'll help you stay compliant.</p>
    </section>
    <div class="container">
        <div class="alert">
            <div class="alert-title">⚠️ Important Compliance Notice</div>
            <p>Credit repair laws vary significantly by state. You MUST be compliant in EACH state where you take clients. Operating without proper registration can result in fines, lawsuits, and criminal charges. CreditSoft helps you track requirements - but ultimately YOU are responsible for compliance.</p>
        </div>
        <div class="intro">
            <h2>Select Your State</h2>
            <p>Choose your state to see CRO requirements. CreditSoft automatically applies these rules to your workflows.</p>
        </div>
        <div class="state-selector">
            <label>Select your state:</label>
            <select id="stateSelect" onchange="showState()">
                <option value="">-- Select a State --</option>
                <option value="AL">Alabama</option>
                <option value="AK">Alaska</option>
                <option value="AZ">Arizona</option>
                <option value="AR">Arkansas</option>
                <option value="CA">California</option>
                <option value="CO">Colorado</option>
                <option value="CT">Connecticut</option>
                <option value="DE">Delaware</option>
                <option value="FL">Florida</option>
                <option value="GA">Georgia</option>
                <option value="HI">Hawaii</option>
                <option value="ID">Idaho</option>
                <option value="IL">Illinois</option>
                <option value="IN">Indiana</option>
                <option value="IA">Iowa</option>
                <option value="KS">Kansas</option>
                <option value="KY">Kentucky</option>
                <option value="LA">Louisiana</option>
                <option value="ME">Maine</option>
                <option value="MD">Maryland</option>
                <option value="MA">Massachusetts</option>
                <option value="MI">Michigan</option>
                <option value="MN">Minnesota</option>
                <option value="MS">Mississippi</option>
                <option value="MO">Missouri</option>
                <option value="MT">Montana</option>
                <option value="NE">Nebraska</option>
                <option value="NV">Nevada</option>
                <option value="NH">New Hampshire</option>
                <option value="NJ">New Jersey</option>
                <option value="NM">New Mexico</option>
                <option value="NY">New York</option>
                <option value="NC">North Carolina</option>
                <option value="ND">North Dakota</option>
                <option value="OH">Ohio</option>
                <option value="OK">Oklahoma</option>
                <option value="OR">Oregon</option>
                <option value="PA">Pennsylvania</option>
                <option value="RI">Rhode Island</option>
                <option value="SC">South Carolina</option>
                <option value="SD">South Dakota</option>
                <option value="TN">Tennessee</option>
                <option value="TX">Texas</option>
                <option value="UT">Utah</option>
                <option value="VT">Vermont</option>
                <option value="VA">Virginia</option>
                <option value="WA">Washington</option>
                <option value="WV">West Virginia</option>
                <option value="WI">Wisconsin</option>
                <option value="WY">Wyoming</option>
            </select>
        </div>
        <div id="stateInfo" class="state-info">
            <div class="state-header">
                <h3 id="stateName">State Name</h3>
                <p id="stateStatus">CRO Status: </p>
            </div>
            <div class="requirements" id="requirements"></div>
        </div>
        <div class="all-states">
            <h3 style="margin-bottom:16px">All States</h3>
            <div class="states-grid">
                <a href="#" class="state-badge" onclick="selectState('CA');return false;">CA</a>
                <a href="#" class="state-badge" onclick="selectState('TX');return false;">TX</a>
                <a href="#" class="state-badge" onclick="selectState('FL');return false;">FL</a>
                <a href="#" class="state-badge" onclick="selectState('NY');return false;">NY</a>
                <a href="#" class="state-badge" onclick="selectState('GA');return false;">GA</a>
                <a href="#" class="state-badge" onclick="selectState('IL');return false;">IL</a>
                <a href="#" class="state-badge" onclick="selectState('OH');return false;">OH</a>
                <a href="#" class="state-badge" onclick="selectState('NC');return false;">NC</a>
                <a href="#" class="state-badge" onclick="selectState('PA');return false;">PA</a>
                <a href="#" class="state-badge" onclick="selectState('MI');return false;">MI</a>
            </div>
        </div>
    </div>
    <footer class="footer"><p>© 2026 CreditSoft · <a href="/pricing">Pricing</a> · <a href="/privacy">Privacy</a></p></footer>
    <script>
    const statesData = {
        'CA':{name:'California',status:'Licensed - Strict',fees:'$10/setup, $25/mo max',bond:'$100,000',registration:'CRO Registration Required',disclosures:'Yes - 5 days before contract'},
        'TX':{name:'Texas',status:'Licensed',fees:'Reasonable & customary',bond:'$50,000',registration:'CSO Registration Required',disclosures:'Yes'},
        'FL':{name:'Florida',status:'Licensed',fees:'$25 activation, $99/mo',bond:'$50,000',registration:'CRO Registration',disclosures:'Yes'},
        'NY':{name:'New York',status:'Strict',fees:'No advance fees',bond:'$50,000',registration:'Registration Required',disclosures:'Comprehensive'},
        'GA':{name:'Georgia',status:'Licensed',fees:'$20 activation, $90/mo',bond:'$25,000',registration:'CRO License',disclosures:'Yes'},
        'IL':{name:'Illinois',status:'Licensed',fees:'$100 activation, $150/mo',bond:'$10,000',registration:'CRO License',disclosures:'Yes'},
        'OH':{name:'Ohio',status:'Licensed',fees:'$25 activation, $125/mo',bond:'$50,000',registration:'CRO License',disclosures:'Yes'},
        'NC':{name:'North Carolina',status:'Licensed',fees:'$50 activation, $75/mo',bond:'$20,000',registration:'CRO License',disclosures:'Yes'},
        'PA':{name:'Pennsylvania',status:'Registered',fees:'Reasonable',bond:'None',registration:'Registration',disclosures:'Standard'},
        'MI':{name:'Michigan',status:'Licensed',fees:'$50 activation, $100/mo',bond:'$25,000',registration:'CRO License',disclosures:'Yes'},
        'AL':{name:'Alabama',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'AK':{name:'Alaska',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'AZ':{name:'Arizona',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'AR':{name:'Arkansas',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'CO':{name:'Colorado',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'CT':{name:'Connecticut',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'DE':{name:'Delaware',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'HI':{name:'Hawaii',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'ID':{name:'Idaho',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'IN':{name:'Indiana',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'IA':{name:'Iowa',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'KS':{name:'Kansas',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'KY':{name:'Kentucky',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'LA':{name:'Louisiana',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'ME':{name:'Maine',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'MD':{name:'Maryland',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'MA':{name:'Massachusetts',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'MN':{name:'Minnesota',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'MS':{name:'Mississippi',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'MO':{name:'Missouri',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'MT':{name:'Montana',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'NE':{name:'Nebraska',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'NV':{name:'Nevada',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'NH':{name:'New Hampshire',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'NJ':{name:'New Jersey',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'NM':{name:'New Mexico',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'ND':{name:'North Dakota',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'OK':{name:'Oklahoma',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'OR':{name:'Oregon',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'RI':{name:'Rhode Island',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'SC':{name:'South Carolina',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'SD':{name:'South Dakota',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'TN':{name:'Tennessee',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'UT':{name:'Utah',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'VT':{name:'Vermont',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'VA':{name:'Virginia',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'WA':{name:'Washington',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'WV':{name:'West Virginia',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'WI':{name:'Wisconsin',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
        'WY':{name:'Wyoming',status:'No State License',fees:'Permitted',bond:'None',registration:'None',disclosures:'FCRA only'},
    };
    function selectState(code) {
        document.getElementById('stateSelect').value = code;
        showState();
    }
    function showState() {
        const code = document.getElementById('stateSelect').value;
        const info = document.getElementById('stateInfo');
        if (!code || !statesData[code]) {
            info.classList.remove('active');
            return;
        }
        const s = statesData[code];
        document.getElementById('stateName').textContent = s.name;
        document.getElementById('stateStatus').textContent = 'Status: ' + s.status;
        let html = '';
        html += '<div class="req-item"><div class="req-icon required">💰</div><div class="req-content"><h4>Fee Limits</h4><p>' + s.fees + '</p></div></div>';
        html += '<div class="req-item"><div class="req-icon required">🛡️</div><div class="req-content"><h4>Bond Required</h4><p>' + s.bond + '</p></div></div>';
        html += '<div class="req-item"><div class="req-icon info">📋</div><div class="req-content"><h4>Registration</h4><p>' + s.registration + '</p></div></div>';
        html += '<div class="req-item"><div class="req-icon optional">📝</div><div class="req-content"><h4>Disclosures</h4><p>' + s.disclosures + '</p></div></div>';
        document.getElementById('requirements').innerHTML = html;
        info.classList.add('active');
    }
    </script>
</body>
</html>
