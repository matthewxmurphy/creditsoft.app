<?php
$page_title = 'Credit Repair Quiz';
$page_description = 'Test your credit repair knowledge with our free quiz.';
$page_hero = true;
$hero_title = '📝 Credit Repair Knowledge Quiz';
$hero_subtitle = 'Test your knowledge';
?>
<?php include 'header.php'; ?>

<style>
.intro{background:white;padding:32px;border-radius:16px;margin-bottom:24px;text-align:center}
.quiz-card{background:white;padding:32px;border-radius:16px;margin-bottom:24px;display:none}
.quiz-card.active{display:block}
.question{font-size:18px;font-weight:600;margin-bottom:20px}
.true-false{display:flex;gap:16px}
.tf-btn{flex:1;padding:20px;border:2px solid var(--border);border-radius:12px;text-align:center;cursor:pointer}
.progress{background:var(--border);height:8px;border-radius:4px;margin-bottom:24px}
.progress-bar{background:var(--primary);height:100%;border-radius:4px;transition:width 0.3s}
.score{text-align:center;padding:40px}
.score .number{font-size:64px;font-weight:800;color:var(--primary)}
</style>

<div class="container" style="padding: 40px 20px;">
    <div class="intro" id="intro">
        <h2>How well do you know credit repair?</h2>
        <p>Take our free quiz. 🎯 Perfect score = 25% off!</p>
        <input type="email" id="quizEmail" placeholder="Your Email" style="width:100%;max-width:300px;padding:14px;border:2px solid var(--border);border-radius:10px;margin:16px 0;font-size:16px;">
        <button class="btn btn-primary" onclick="startQuiz()">Start Quiz</button>
    </div>
    <div class="progress" id="progress" style="display:none"><div class="progress-bar" id="progressBar"></div></div>
    <div id="quizContainer"></div>
    <div class="score" id="scoreCard" style="display:none">
        <div class="number"><span id="scoreNum">0</span>/10</div>
        <p id="scoreMsg" style="margin-top:16px;color:var(--gray)"></p>
        <button class="btn btn-primary" onclick="startQuiz()">Try Again</button>
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
    currentQ = score = 0;
    document.getElementById('intro').style.display = 'none';
    document.getElementById('scoreCard').style.display = 'none';
    document.getElementById('progress').style.display = 'block';
    showQuestion();
}

function showQuestion() {
    const q = questions[currentQ];
    document.getElementById('progressBar').style.width = (currentQ / questions.length * 100) + '%';
    let html = '<div class="quiz-card active"><div class="question">Q' + (currentQ + 1) + ': ' + q.q + '</div>';
    if (q.type === 'tf') {
        html += '<div class="true-false"><div class="tf-btn" onclick="answer(' + (q.correct?'true':'false') + ',true)">True</div><div class="tf-btn" onclick="answer(' + (q.correct?'false':'true') + ',true)">False</div></div>';
    } else {
        html += '<div style="display:flex;flex-direction:column;gap:8px;">';
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

function showScore() {
    document.getElementById('progressBar').style.width = '100%';
    document.getElementById('quizContainer').innerHTML = '';
    document.getElementById('scoreCard').style.display = 'block';
    document.getElementById('scoreNum').textContent = score;
    const email = document.getElementById('quizEmail').value;
    if(email) fetch('/api/lead.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email:email,source:'quiz',score:score})}).catch(()=>{});
    let c = score===10?'<div style="background:#10b981;color:white;padding:20px;border-radius:12px;margin:20px 0;font-weight:800;">QUIZMASTER25<br><small>25% off!</small></div>':'';
    document.getElementById('scoreMsg').innerHTML = (score>=7?'Great job!':'Keep learning!') + c;
}
</script>

<?php include 'footer.php'; ?>
