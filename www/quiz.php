<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credit Repair Knowledge Quiz - CreditSoft</title>
    <meta name="description" content="Test your credit repair knowledge with our free quiz. 25 questions True/False and Multiple Choice.">
    <link rel="canonical" href="https://www.creditsoft.app/quiz">
    <meta property="og:url" content="https://www.creditsoft.app/quiz">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-9QJTYCN2FZ"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-9QJTYCN2FZ');</script>
    <style>
        :root{--primary:#2563eb;--success:#10b981;--dark:#0f172a;--light:#f8fafc;--gray:#64748b;--border:#e2e8f0}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Inter',sans-serif;background:var(--light);color:var(--dark);line-height:1.6}
        .hero{background:linear-gradient(135deg,#0f172a,#1e3a5f,#2563eb);color:white;padding:120px 20px 60px}
        .hero h1{font-size:36px;font-weight:700;margin-bottom:12px}
        .nav{position:absolute;top:0;left:0;right:0;padding:20px 40px;display:flex;justify-content:space-between;align-items:center;z-index:100}
        .nav-logo img{height:70px}
        .nav-links{display:flex;gap:28px}
        .nav-links a{color:white;text-decoration:none;font-weight:500}
        .container{max-width:800px;margin:0 auto;padding:40px 20px}
        .intro{background:white;padding:32px;border-radius:16px;margin-bottom:24px;text-align:center}
        .intro h2{font-size:24px;margin-bottom:12px}
        .intro p{color:var(--gray)}
        .quiz-card{background:white;padding:32px;border-radius:16px;margin-bottom:24px;display:none}
        .quiz-card.active{display:block}
        .question{font-size:18px;font-weight:600;margin-bottom:20px}
        .options{display:flex;flex-direction:column;gap:12px}
        .option{cursor:pointer;padding:16px 20px;border:2px solid var(--border);border-radius:12px;transition:all 0.2s}
        .option:hover{border-color:var(--primary);background:var(--light)}
        .option.selected{border-color:var(--primary);background:rgba(37,99,235,0.1)}
        .option.correct{border-color:var(--success);background:rgba(16,185,129,0.1)}
        .option.wrong{border-color:#ef4444;background:rgba(239,68,68,0.1)}
        .true-false{display:flex;gap:16px}
        .tf-btn{flex:1;padding:20px;border:2px solid var(--border);border-radius:12px;text-align:center;font-size:18px;font-weight:600;cursor:pointer;transition:all 0.2s}
        .tf-btn:hover{border-color:var(--primary)}
        .tf-btn.selected{border-color:var(--primary);background:var(--primary);color:white}
        .btn{background:var(--primary);color:white;padding:14px 28px;border-radius:10px;font-size:16px;font-weight:600;border:none;cursor:pointer;margin-top:20px}
        .btn:disabled{background:var(--gray);cursor:not-allowed}
        .progress{background:var(--border);height:8px;border-radius:4px;margin-bottom:24px}
        .progress-bar{background:var(--primary);height:100%;border-radius:4px;transition:width 0.3s}
        .score{text-align:center;padding:40px}
        .score h2{font-size:32px;margin-bottom:16px}
        .score .number{font-size:64px;font-weight:800;color:var(--primary)}
        .footer{background:#0a0f1a;color:var(--gray);padding:40px 0;text-align:center;font-size:14px}
        .footer a{color:var(--primary);text-decoration:none}
        @media(max-width:768px){.nav-links{display:none};.true-false{flex-direction:column}}
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
        <h1>📝 Credit Repair Knowledge Quiz</h1>
        <p>Test your knowledge with 25 questions</p>
    </section>
    <div class="container">
        <div class="intro" id="intro">
            <h2>How well do you know credit repair?</h2>
            <p>Take our free quiz to test your knowledge. True/False and Multiple Choice questions about Metro2, FCRA, FDCPA, and credit repair best practices.</p>
            <button class="btn" onclick="startQuiz()">Start Quiz</button>
        </div>
        <div class="progress" id="progress" style="display:none"><div class="progress-bar" id="progressBar"></div></div>
        <div id="quizContainer"></div>
        <div class="score" id="scoreCard" style="display:none">
            <h2>Your Score</h2>
            <div class="number"><span id="scoreNum">0</span>/25</div>
            <p id="scoreMsg" style="margin-top:16px;color:var(--gray)"></p>
            <button class="btn" onclick="startQuiz()">Try Again</button>
            <div style="margin-top:24px"><a href="/subscribe" class="btn">Get CreditSoft</a></div>
        </div>
    </div>
    <footer class="footer"><p>© 2026 CreditSoft · <a href="/pricing">Pricing</a> · <a href="/privacy">Privacy</a></p></footer>
    <script>
    const questions = [
        {q:"Metro2 is the data reporting format used by all three credit bureaus.",type:"tf",correct:true},
        {q:"You can dispute items on someone's credit report without their written permission.",type:"tf",correct:false},
        {q:"Which Metro2 code indicates an account number mismatch?",type:"mc",options:["A - Account Number","B - Balance","F - Future Date","I - Identity"],correct:0},
        {q:"The CRO Act prohibits charging advance fees for credit repair services.",type:"tf",correct:false},
        {q:"FCRA stands for Fair Credit Reporting Act.",type:"tf",correct:true},
        {q:"Which state requires a $100,000 bond for credit repair companies?",type:"mc",options:["Texas","California","Florida","Georgia"],correct:1},
        {q:"You must be licensed to operate as a credit repair company in all 50 states.",type:"tf",correct:false},
        {q:"FDCPA stands for Fair Debt Collection Practices Act.",type:"tf",correct:true},
        {q:"A 'Z' Metro2 code indicates a zero balance that should have been deleted.",type:"tf",correct:true},
        {q:"Credit bureaus must investigate disputes within 30 days.",type:"tf",correct:true},
        {q:"Which is NOT a Metro2 error code category?",type:"mc",options:["Account Status","Payment History","Social Security Number","Favorite Color"],correct:3},
        {q:"You can guarantee specific credit score improvements to clients.",type:"tf",correct:false},
        {q:"The 'Keyer' Metro2 code indicates a manual entry error.",type:"tf",correct:true},
        {q:"All credit repair companies must provide a written contract before charging fees.",type:"tf",correct:true},
        {q:"Which state has the strictest credit repair laws?",type:"mc",options:["Texas","Florida","California","New York"],correct:2},
        {q:"Consumers have the right to dispute inaccurate information directly with creditors.",type:"tf",correct:true},
        {q:"An 'R' Metro2 code indicates the account has been reaged.",type:"tf",correct:true},
        {q:"You need a law degree to provide credit repair services.",type:"tf",correct:false},
        {q:"The 'New Address' Metro2 code is used when a consumer moves.",type:"tf",correct:true},
        {q:"Credit repair software must comply with FCRA to be legal.",type:"tf",correct:true},
        {q:"Which bureau is NOT one of the three major credit bureaus?",type:"mc",options:["Equifax","Experian","TransUnion","Innovis"],correct:3},
        {q:"Negative information generally stays on a credit report for 7 years.",type:"tf",correct:true},
        {q:"Bankruptcies can stay on a credit report for up to 10 years.",type:"tf",correct:true},
        {q:"The 'S' Metro2 code indicates a status error.",type:"tf",correct:true},
        {q:"Late payments can only be disputed if they're less than 7 years old.",type:"tf",correct:false},
    ];
    let currentQ = 0, score = 0, answers = [];
    
    function startQuiz() {
        currentQ = score = 0; answers = [];
        document.getElementById('intro').style.display = 'none';
        document.getElementById('quizContainer').innerHTML = '';
        document.getElementById('scoreCard').style.display = 'none';
        document.getElementById('progress').style.display = 'block';
        showQuestion();
    }
    
    function showQuestion() {
        const q = questions[currentQ];
        const progress = ((currentQ) / questions.length) * 100;
        document.getElementById('progressBar').style.width = progress + '%';
        
        let html = '<div class="quiz-card active"><div class="question">Q' + (currentQ + 1) + ': ' + q.q + '</div>';
        
        if (q.type === 'tf') {
            html += '<div class="true-false"><div class="tf-btn" onclick="answer(' + (q.correct ? 'true' : 'false') + ', true)">True</div><div class="tf-btn" onclick="answer(' + (q.correct ? 'false' : 'true') + ', true)">False</div></div>';
        } else {
            html += '<div class="options">';
            q.options.forEach((opt, i) => {
                html += '<div class="option" onclick="answer(' + i + ', false)">' + opt + '</div>';
            });
            html += '</div>';
        }
        html += '</div>';
        document.getElementById('quizContainer').innerHTML = html;
    }
    
    function answer(val, isTF) {
        const q = questions[currentQ];
        let correct = false;
        if (isTF) correct = val === q.correct;
        else correct = val === q.correct;
        
        if (correct) score++;
        answers.push({q:currentQ, correct:correct});
        
        currentQ++;
        if (currentQ >= questions.length) {
            showScore();
        } else {
            showQuestion();
        }
    }
    
    function showScore() {
        document.getElementById('progressBar').style.width = '100%';
        document.getElementById('quizContainer').innerHTML = '';
        document.getElementById('scoreCard').style.display = 'block';
        document.getElementById('scoreNum').textContent = score;
        
        let msg = '';
        if (score >= 20) msg = "Excellent! You really know your credit repair!";
        else if (score >= 15) msg = "Good job! You have solid knowledge.";
        else if (score >= 10) msg = "Not bad! There's room to learn.";
        else msg = "Time to study up! CreditSoft can help.";
        document.getElementById('scoreMsg').textContent = msg;
    }
    </script>
</body>
</html>
