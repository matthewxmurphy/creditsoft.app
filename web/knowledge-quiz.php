<?php
$page_title = 'Credit Repair Knowledge Quiz';
$page_description = 'Test your credit repair knowledge with our free quiz and earn a unique 25% off code with a perfect score.';
$page_hero = true;
$hero_title = '📝 Credit Repair Knowledge Quiz';
$hero_subtitle = 'Score 10 out of 10 and earn a unique 25% off code.';
?>
<?php include 'header.php'; ?>

<style>
.intro{background:white;padding:36px;border-radius:20px;margin-bottom:24px;text-align:center;border:1px solid var(--border);box-shadow:0 20px 48px rgba(15,23,42,.08)}
.intro p{max-width:720px;margin:14px auto 0;color:var(--gray)}
.quiz-form{display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:720px;margin:22px auto 0}
.quiz-card{background:white;padding:32px;border-radius:20px;margin-bottom:24px;display:none;border:1px solid var(--border);box-shadow:0 18px 42px rgba(15,23,42,.06)}
.quiz-card.active{display:block}
.question{font-size:22px;font-weight:700;margin-bottom:10px}
.question-note{color:var(--gray);margin-bottom:20px}
.true-false{display:flex;gap:16px}
.tf-btn{flex:1;padding:20px;border:2px solid var(--border);border-radius:16px;text-align:center;cursor:pointer;font-weight:700;background:white;transition:transform .18s,border-color .18s,box-shadow .18s}
.tf-btn:hover{border-color:#93c5fd;box-shadow:0 12px 24px rgba(37,99,235,.08);transform:translateY(-1px)}
.progress{background:#dbeafe;height:10px;border-radius:999px;margin-bottom:24px;overflow:hidden}
.progress-bar{background:linear-gradient(90deg,var(--primary),#60a5fa);height:100%;border-radius:999px;transition:width 0.3s}
.score{text-align:center;padding:42px;background:white;border:1px solid var(--border);border-radius:20px;box-shadow:0 18px 42px rgba(15,23,42,.06)}
.score .number{font-size:64px;font-weight:800;color:var(--primary)}
.score-band{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:999px;background:#eef6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;margin:18px 0}
.quiz-options{display:flex;flex-direction:column;gap:8px}
.coupon-success{background:linear-gradient(135deg,#16a34a,#15803d);color:white;padding:22px;border-radius:18px;margin:20px auto;font-weight:800;max-width:420px;box-shadow:0 18px 42px rgba(22,163,74,.22)}
.coupon-success small{display:block;font-size:12px;opacity:.8;letter-spacing:.08em;text-transform:uppercase}
.coupon-code{display:block;font-size:34px;letter-spacing:.08em;margin:10px 0}
.result-copy{max-width:720px;margin:0 auto;color:var(--gray)}
.result-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:24px}
.field-error{margin-top:16px;color:#b91c1c;font-weight:600;display:none}
.quiz-disclaimer{margin-top:28px;padding:18px 20px;border-top:1px solid var(--border);font-size:13px;color:var(--gray)}
@media(max-width:768px){
    .quiz-form{grid-template-columns:1fr}
    .true-false{flex-direction:column}
}
</style>

<div class="container section">
    <div class="intro" id="intro">
        <h2>How well do you know credit repair?</h2>
        <p>Take our free quiz. A perfect 10/10 score earns a unique 25% off code.</p>
        <div class="quiz-form">
            <input type="text" id="quizName" placeholder="Your Name (optional)">
            <input type="email" id="quizEmail" placeholder="Your Email">
        </div>
        <div class="field-error" id="quizError">Enter a valid email before starting.</div>
        <button class="btn btn-primary" onclick="startQuiz()">Start Quiz</button>
    </div>
    <div class="progress" id="progress" style="display:none"><div class="progress-bar" id="progressBar"></div></div>
    <div id="quizContainer"></div>
    <div class="score" id="scoreCard" style="display:none">
        <div class="number"><span id="scoreNum">0</span>/10</div>
        <div class="score-band" id="scoreBand">Knowledge check complete</div>
        <p id="scoreMsg" class="result-copy mt-2"></p>
        <div id="couponSlot"></div>
        <div class="result-actions">
            <a href="/pricing" class="btn btn-primary">See pricing</a>
            <button class="btn" style="background:#e2e8f0;color:#0f172a" onclick="startQuiz()">Try Again</button>
        </div>
    </div>
    <div class="quiz-disclaimer">
        This quiz is educational only. It does not provide legal advice or promise any specific dispute, compliance, or business outcome.
    </div>
</div>

<script>
const questions = [
    {q:"Metro2 is used by all three credit bureaus.",type:"tf",correct:true},
    {q:"You can dispute items without written permission.",type:"tf",correct:false},
    {q:"Which code indicates account number mismatch?",type:"mc",options:["A","B","F","I"],correct:0},
    {q:"CRO Act prohibits charging advance fees.",type:"tf",correct:false},
    {q:"FCRA = Fair Credit Reporting Act.",type:"tf",correct:true},
    {q:"Which state requires $100,000 bond?",type:"mc",options:["TX","CA","FL","GA"],correct:1},
    {q:"All states require credit repair license.",type:"tf",correct:false},
    {q:"FDCPA = Fair Debt Collection Practices Act.",type:"tf",correct:true},
    {q:"Bureaus must investigate within 30 days.",type:"tf",correct:true},
    {q:"You can guarantee credit score improvements.",type:"tf",correct:false},
];
let currentQ = 0, score = 0;

function startQuiz() {
    const email = document.getElementById('quizEmail').value.trim();
    const emailOk = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/.test(email);
    if (!emailOk) {
        document.getElementById('quizError').style.display = 'block';
        return;
    }

    document.getElementById('quizError').style.display = 'none';
    currentQ = score = 0;
    document.getElementById('intro').style.display = 'none';
    document.getElementById('scoreCard').style.display = 'none';
    document.getElementById('progress').style.display = 'block';
    showQuestion();
}

function showQuestion() {
    const q = questions[currentQ];
    document.getElementById('progressBar').style.width = (currentQ / questions.length * 100) + '%';
    let html = '<div class="quiz-card active"><div class="question">Q' + (currentQ + 1) + ': ' + q.q + '</div><div class="question-note">Choose the strongest answer based on credit repair fundamentals.</div>';
    if (q.type === 'tf') {
        html += '<div class="true-false"><div class="tf-btn" onclick="answer(' + (q.correct?'true':'false') + ',true)">True</div><div class="tf-btn" onclick="answer(' + (q.correct?'false':'true') + ',true)">False</div></div>';
    } else {
        html += '<div class="quiz-options">';
        q.options.forEach((o,i) => { html += '<div class="tf-btn" onclick="answer('+i+',false)">'+o+'</div>'; });
        html += '</div>';
    }
    html += '</div>';
    document.getElementById('quizContainer').innerHTML = html;
}

function answer(val, isTF) {
    const q = questions[currentQ];
    if ((isTF && val===q.correct) || (!isTF && val===q.correct)) score++;
    currentQ++;
    currentQ >= questions.length ? showScore() : showQuestion();
}

async function showScore() {
    document.getElementById('progressBar').style.width = '100%';
    document.getElementById('quizContainer').innerHTML = '';
    document.getElementById('scoreCard').style.display = 'block';
    document.getElementById('scoreNum').textContent = score;
    document.getElementById('scoreBand').textContent = score === 10 ? 'Perfect score unlocked' : (score >= 7 ? 'Strong result' : 'Keep sharpening');
    document.getElementById('couponSlot').innerHTML = '';

    const name = document.getElementById('quizName').value.trim();
    const email = document.getElementById('quizEmail').value;

    let couponHtml = '';
    try {
        const res = await fetch('/api/lead.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                name: name,
                email: email,
                source: 'knowledge_quiz',
                score: score,
                max_score: questions.length
            })
        });
        const data = await res.json();
        if (data && data.coupon && data.coupon.code) {
            couponHtml = '<div class="coupon-success"><small>Perfect score reward</small><span class="coupon-code">' + data.coupon.code + '</span><span>25% off your first year.</span></div>';
        }
    } catch (e) {}

    document.getElementById('scoreMsg').textContent = score >= 7
        ? 'Nice work. Your result is saved, and a perfect score unlocks a unique 25% off code.'
        : 'You finished the quiz. Your result is saved, and you can always try again to improve your score.';
    document.getElementById('couponSlot').innerHTML = couponHtml;
}
</script>

<?php include 'footer.php'; ?>
