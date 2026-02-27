<?= $this->extend('layout/storefront') ?>

<?= $this->section('content') ?>

<style>
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
.true-false{display:flex;gap:16px}
.tf-btn{flex:1;padding:20px;border:2px solid var(--border);border-radius:12px;text-align:center;font-size:18px;font-weight:600;cursor:pointer;transition:all 0.2s}
.tf-btn:hover{border-color:var(--primary)}
.tf-btn.selected{border-color:var(--primary);background:var(--primary);color:white}
.progress{background:var(--border);height:8px;border-radius:4px;margin-bottom:24px}
.progress-bar{background:var(--primary);height:100%;border-radius:4px;transition:width 0.3s}
.score{text-align:center;padding:40px}
.score h2{font-size:32px;margin-bottom:16px}
.score .number{font-size:64px;font-weight:800;color:var(--primary)}
@media(max-width:768px){.true-false{flex-direction:column}}
</style>

<div class="container" style="padding: 40px 20px;">
    <div class="intro" id="intro">
        <h2>How well do you know credit repair?</h2>
        <p>Take our free quiz to test your knowledge. True/False and Multiple Choice questions about Metro2, FCRA, FDCPA, and credit repair best practices.</p>
        <p style="margin-top:16px;color:var(--gray);font-size:14px;">🎯 Perfect score = 25% off your first year!</p>
        <div style="margin-top:24px;text-align:left;max-width:400px;margin-left:auto;margin-right:auto;">
            <input type="text" id="quizName" placeholder="Your Name" style="width:100%;padding:14px;border:2px solid var(--border);border-radius:10px;margin-bottom:12px;font-size:16px;">
            <input type="email" id="quizEmail" placeholder="Your Email" style="width:100%;padding:14px;border:2px solid var(--border);border-radius:10px;margin-bottom:12px;font-size:16px;">
        </div>
        <button class="btn btn-primary" onclick="startQuiz()" style="width:200px;margin-top:16px;">Start Quiz</button>
    </div>
    <div class="progress" id="progress" style="display:none"><div class="progress-bar" id="progressBar"></div></div>
    <div id="quizContainer"></div>
    <div class="score" id="scoreCard" style="display:none">
        <h2>Your Score</h2>
        <div class="number"><span id="scoreNum">0</span>/25</div>
        <p id="scoreMsg" style="margin-top:16px;color:var(--gray)"></p>
        <button class="btn btn-primary" onclick="startQuiz()">Try Again</button>
        <div style="margin-top:24px"><a href="/subscribe" class="btn btn-primary">Get CreditSoft</a></div>
    </div>
</div>

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
    
    const name = document.getElementById('quizName').value || 'there';
    const email = document.getElementById('quizEmail').value;
    
    if (email) {
        fetch('/api/lead', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({name: name, email: email, source: 'quiz', score: score})
        }).catch(() => {});
    }
    
    let msg = '';
    let couponHtml = '';
    if (score === 25) {
        msg = "Perfect score! 🎉 You really know your credit repair!";
        couponHtml = '<div style="background:linear-gradient(135deg,#10b981,#059669);color:white;padding:20px;border-radius:12px;margin:20px 0;"><div style="font-size:14px;opacity:0.9;">🎉 25% OFF YOUR FIRST YEAR</div><div style="font-size:28px;font-weight:800;margin:8px 0;">QUIZMASTER25</div><div style="font-size:12px;opacity:0.8;">Use code at checkout</div></div>';
    } else if (score >= 20) msg = "Excellent! You really know your credit repair!";
    else if (score >= 15) msg = "Good job! You have solid knowledge.";
    else if (score >= 10) msg = "Not bad! There's room to learn.";
    else msg = "Time to study up! CreditSoft can help.";
    
    document.getElementById('scoreMsg').innerHTML = msg + couponHtml;
}
</script>

<?= $this->endSection() ?>
