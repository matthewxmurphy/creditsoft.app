<?php
$page_title = 'Credit Report Red Flags Quiz';
$page_description = 'Take a short quiz and see how well you can spot the credit report warning signs that matter.';
$page_hero = true;
$hero_title = 'Credit Report Red Flags Quiz';
$hero_subtitle = 'A quick quiz about the warning signs that show up in real credit report problems.';
?>
<?php include 'header.php'; ?>

<style>
.intro{background:white;padding:36px;border-radius:20px;margin-bottom:24px;text-align:center;border:1px solid var(--border);box-shadow:0 20px 48px rgba(15,23,42,.08)}
.intro p{max-width:720px;margin:14px auto 0;color:var(--gray)}
.quiz-form{display:grid;grid-template-columns:1fr 1fr;gap:12px;max-width:720px;margin:22px auto 0}
.quiz-card{background:white;padding:32px;border-radius:20px;margin-bottom:24px;display:none;border:1px solid var(--border);box-shadow:0 18px 42px rgba(15,23,42,.06)}
.quiz-card.active{display:block}
.question{font-size:22px;font-weight:700;margin-bottom:10px}
.question-note{font-size:15px;color:var(--gray);margin-bottom:24px}
.quiz-options{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.option-btn{padding:18px;border:2px solid var(--border);border-radius:16px;text-align:center;cursor:pointer;font-weight:700;background:white;transition:transform .18s,border-color .18s,box-shadow .18s}
.option-btn:hover{border-color:#93c5fd;box-shadow:0 12px 24px rgba(37,99,235,.08);transform:translateY(-1px)}
.progress{background:#dbeafe;height:10px;border-radius:999px;margin-bottom:24px;overflow:hidden;display:none}
.progress-bar{background:linear-gradient(90deg,var(--primary),#60a5fa);height:100%;border-radius:999px;transition:width .3s}
.score{background:white;text-align:center;padding:42px;border-radius:20px;border:1px solid var(--border);box-shadow:0 18px 42px rgba(15,23,42,.06);display:none}
.score .number{font-size:64px;font-weight:800;color:var(--primary)}
.score-band{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:999px;background:#eef6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;margin:18px 0}
.result-copy{max-width:720px;margin:0 auto;color:var(--gray)}
.result-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:24px}
.disclaimer{margin-top:28px;padding:18px 20px;border-top:1px solid var(--border);font-size:13px;color:var(--gray)}
.field-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;max-width:720px;margin:20px auto 0}
.field-grid input{width:100%;padding:14px 16px;border:1px solid var(--border);border-radius:12px;font-size:16px}
.helper-strip{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:28px 0 0}
.helper-card{background:#f8fafc;border:1px solid var(--border);border-radius:16px;padding:18px;text-align:left}
.helper-card h3{font-size:15px;margin-bottom:8px}
.helper-card p{font-size:14px;color:var(--gray);margin:0}
.field-error{margin-top:16px;color:#b91c1c;font-weight:600;display:none}
@media(max-width:768px){
  .quiz-form,.field-grid,.helper-strip,.quiz-options{grid-template-columns:1fr}
}
</style>

<div class="container section">
    <div class="intro" id="intro">
        <h2>Can you spot the credit report problems?</h2>
        <p>
            This quiz is built to teach what real warning signs look like. It focuses on the kinds of reporting mistakes,
            bureau mismatches, and collection problems people miss all the time.
        </p>
        <div class="quiz-form">
            <input type="text" id="leadName" placeholder="Your Name (optional)">
            <input type="email" id="leadEmail" placeholder="Your Email">
        </div>
        <div class="field-error" id="quizError">Enter a valid email before starting.</div>
        <button class="btn btn-primary" style="margin-top:18px" onclick="startCheck()">Start Quiz</button>
        <div class="helper-strip">
            <div class="helper-card">
                <h3>Good for</h3>
                <p>Learning what kinds of credit report problems actually matter instead of just guessing.</p>
            </div>
            <div class="helper-card">
                <h3>Looks at</h3>
                <p>Missing bureau entries, conflicting balances, unverifiable collections, and items that keep coming back.</p>
            </div>
            <div class="helper-card">
                <h3>Reward</h3>
                <p>Finish the quiz, learn something useful, and move into the next step with a clearer idea of what to watch for.</p>
            </div>
        </div>
    </div>

    <div class="progress" id="progress"><div class="progress-bar" id="progressBar"></div></div>
    <div id="quizContainer"></div>

    <div class="score" id="scoreCard">
        <div class="number"><span id="scoreNum">0</span>/16</div>
        <div class="score-band" id="scoreBand">Quiz complete</div>
        <p id="scoreMsg" class="result-copy"></p>
        <div class="result-actions">
            <a href="/pricing" class="btn btn-primary">See pricing</a>
            <button class="btn" style="background:#e2e8f0;color:#0f172a" onclick="startCheck()">Try Again</button>
        </div>
    </div>
    <p class="disclaimer">
        This quiz is educational only. It is not legal advice and does not establish that any claim, dispute, or lawsuit lane will succeed.
    </p>
</div>

<script>
const questions = [
    {
        q: "Is the same account showing different balances, status, or payment history across bureaus?",
        prompt: "Conflicting bureau reporting is often a better factual lead than a generic “I don’t like this account.”"
    },
    {
        q: "Did an item come back after it was disputed or supposedly corrected before?",
        prompt: "Re-reporting and reinsertion patterns deserve closer review, especially if the response never explained what changed."
    },
    {
        q: "Is a collection still reporting after payment, settlement, or a dispute where validation stayed unclear?",
        prompt: "Collectors and furnishers should not be treated the same way if the reporting trail is incomplete or unsupported."
    },
    {
        q: "Does the report show a late payment, last-payment date, or delinquency pattern that does not match the consumer’s records?",
        prompt: "Date and payment-history mismatches are stronger when they can be tied to statements, settlement letters, or other proof."
    },
    {
        q: "Is an account missing from one bureau while similar reporting appears on another bureau?",
        prompt: "Single-bureau or missing-bureau behavior can be worth review when the account should be reporting consistently."
    },
    {
        q: "Has a debt collector contacted the consumer about a debt they do not recognize or could not verify?",
        prompt: "That points more toward collection validation and FDCPA-style review than a standard bureau-only correction lane."
    },
    {
        q: "Did the bureau or furnisher answer a dispute without clearly explaining how the item was verified?",
        prompt: "Weak verification language often leaves a factual review opening, especially when the data still conflicts."
    },
    {
        q: "Has the consumer been denied credit or quoted worse terms because of items they believe are inaccurate?",
        prompt: "A real consequence can raise the urgency, even though the underlying report facts still matter most."
    }
];

const optionWeights = {
    yes: 2,
    maybe: 1,
    no: 0,
};

let currentQ = 0;
let score = 0;

function startCheck() {
    const email = document.getElementById('leadEmail').value.trim();
    const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    if (!emailOk) {
        document.getElementById('quizError').style.display = 'block';
        return;
    }

    document.getElementById('quizError').style.display = 'none';
    currentQ = 0;
    score = 0;
    document.getElementById('intro').style.display = 'none';
    document.getElementById('scoreCard').style.display = 'none';
    document.getElementById('progress').style.display = 'block';
    showQuestion();
}

function showQuestion() {
    const q = questions[currentQ];
    document.getElementById('progressBar').style.width = ((currentQ / questions.length) * 100) + '%';
    document.getElementById('quizContainer').innerHTML = `
        <div class="quiz-card active">
            <div class="question">Q${currentQ + 1}: ${q.q}</div>
            <div class="question-note">${q.prompt}</div>
            <div class="quiz-options">
                <button class="option-btn" onclick="answer('yes')">Yes</button>
                <button class="option-btn" onclick="answer('maybe')">Maybe</button>
                <button class="option-btn" onclick="answer('no')">No</button>
            </div>
        </div>
    `;
}

function answer(value) {
    score += optionWeights[value] || 0;
    currentQ += 1;
    if (currentQ >= questions.length) {
        showResult();
    } else {
        showQuestion();
    }
}

function resultMeta(total) {
    if (total >= 12) {
        return {
            band: 'You spot the big red flags',
            copy: 'You picked up on the strongest warning signs. That is exactly the kind of attention to detail people need when they read a credit report.'
        };
    }
    if (total >= 8) {
        return {
            band: 'Strong eye for problems',
            copy: 'You caught a lot of the important warning signs. A little more practice and you will spot these patterns even faster.'
        };
    }
    if (total >= 4) {
        return {
            band: 'Good start',
            copy: 'You caught some of the warning signs, but a few important issues still slipped by. That is normal, and it is why structured review matters.'
        };
    }

    return {
        band: 'More to learn',
        copy: 'This one was tougher than it looked. Most people miss these patterns until they know what to watch for.'
    };
}

function showResult() {
    document.getElementById('progressBar').style.width = '100%';
    document.getElementById('quizContainer').innerHTML = '';
    document.getElementById('scoreCard').style.display = 'block';
    document.getElementById('scoreNum').textContent = score;

    const meta = resultMeta(score);
    document.getElementById('scoreBand').textContent = meta.band;
    document.getElementById('scoreMsg').textContent = meta.copy;

    const email = document.getElementById('leadEmail').value.trim();
    const name = document.getElementById('leadName').value.trim();
    if (email) {
        fetch('/api/lead.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                name,
                email,
                source: 'lawsuit_test',
                score,
                max_score: 16,
                result_band: meta.band,
                assessment_label: 'Credit Report Red Flags Quiz',
                metadata: {
                    result_band: meta.band,
                    result_copy: meta.copy
                }
            }),
        }).catch(() => {});
    }
}
</script>

<?php include 'footer.php'; ?>
