<?php
$page_title = 'CreditSoft Quizzes';
$page_description = 'Choose a CreditSoft quiz: spot credit report red flags or test your credit repair knowledge.';
$page_hero = true;
$hero_title = 'CreditSoft quizzes';
$hero_subtitle = 'Learn something useful, test what you know, and unlock the next step.';
?>
<?php include 'header.php'; ?>

<style>
.assessment-shell{display:grid;grid-template-columns:minmax(0,1.12fr) minmax(320px,.88fr);gap:28px;align-items:start}
.assessment-lead{background:white;border:1px solid var(--border);border-radius:24px;padding:36px;box-shadow:0 24px 60px rgba(15,23,42,.08)}
.assessment-lead h2{font-size:34px;line-height:1.08;margin-bottom:14px}
.assessment-lead p{color:var(--gray);font-size:17px;max-width:700px}
.assessment-strip{display:flex;flex-wrap:wrap;gap:12px;margin-top:24px}
.signal-pill{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
.signal-pill.primary{background:#dbeafe;color:#1d4ed8}
.signal-pill.success{background:#dcfce7;color:#166534}
.signal-pill.dark{background:#e2e8f0;color:#0f172a}
.assessment-side{display:flex;flex-direction:column;gap:18px}
.assessment-mini{background:#0f172a;color:white;border-radius:20px;padding:26px}
.assessment-mini h3{font-size:18px;margin-bottom:8px}
.assessment-mini p{opacity:.78;margin:0}
.assessment-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:24px;margin-top:28px}
.assessment-card{background:white;border:1px solid var(--border);border-radius:24px;padding:30px;display:flex;flex-direction:column;gap:16px;box-shadow:0 18px 38px rgba(15,23,42,.06);transition:transform .18s,border-color .18s,box-shadow .18s}
.assessment-card:hover{transform:translateY(-4px);border-color:#93c5fd;box-shadow:0 24px 48px rgba(15,23,42,.1);text-decoration:none}
.assessment-card.consumer{background:linear-gradient(180deg,#ffffff 0%,#f8fbff 100%)}
.assessment-card.operator{background:linear-gradient(180deg,#ffffff 0%,#fbfbfd 100%)}
.assessment-kicker{display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
.assessment-kicker.consumer{color:#1d4ed8}
.assessment-kicker.operator{color:#0f172a}
.assessment-card h3{font-size:28px;line-height:1.1;color:var(--dark);margin:0}
.assessment-card p{color:var(--gray);margin:0}
.assessment-points{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:12px}
.assessment-points li{padding-left:20px;position:relative;color:var(--dark)}
.assessment-points li::before{content:'';position:absolute;left:0;top:11px;width:7px;height:7px;border-radius:999px;background:var(--success)}
.assessment-meta{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;padding-top:8px;border-top:1px solid var(--border);font-size:14px;color:var(--gray)}
.assessment-meta strong{display:block;color:var(--dark);font-size:15px}
.assessment-actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:auto}
.assessment-actions .btn{flex:1;min-width:180px;text-align:center}
.assessment-secondary{background:#f8fafc;color:var(--dark)}
.assessment-paths{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin-top:28px}
.assessment-path{background:white;border:1px solid var(--border);border-radius:22px;padding:24px;box-shadow:0 16px 34px rgba(15,23,42,.05)}
.assessment-path h3{font-size:20px;margin-bottom:8px}
.assessment-path p{color:var(--gray);margin-bottom:14px}
.assessment-path ul{list-style:none;margin:0;padding:0;display:grid;gap:10px}
.assessment-path li{color:var(--dark);padding-left:18px;position:relative}
.assessment-path li::before{content:'';position:absolute;left:0;top:10px;width:6px;height:6px;border-radius:999px;background:var(--primary)}
.assessment-learn{background:linear-gradient(135deg,#eff6ff,#ffffff);border:1px solid #bfdbfe;border-radius:24px;padding:28px;display:grid;grid-template-columns:1.15fr .85fr;gap:24px;align-items:start}
.assessment-learn h2{font-size:30px;margin-bottom:10px}
.assessment-learn p{color:var(--gray)}
.assessment-learn ul{list-style:none;margin:18px 0 0;padding:0;display:grid;gap:12px}
.assessment-learn li{padding:14px 16px;border-radius:16px;background:rgba(255,255,255,.85);border:1px solid rgba(191,219,254,.9);color:var(--dark)}
.assessment-learn .learn-side{background:#0f172a;color:white;border-radius:20px;padding:22px}
.assessment-learn .learn-side h3{font-size:18px;margin-bottom:8px}
.assessment-learn .learn-side p{color:rgba(255,255,255,.76);margin:0 0 12px}
.assessment-note{margin-top:28px;background:#fff;border:1px dashed #cbd5e1;border-radius:20px;padding:20px 22px;color:var(--gray)}
.assessment-disclaimer{margin-top:18px;font-size:13px;color:var(--gray);padding:18px 20px;border-top:1px solid var(--border)}
@media(max-width:960px){
    .assessment-shell,.assessment-learn,.assessment-paths{grid-template-columns:1fr}
}
@media(max-width:640px){
    .assessment-lead h2{font-size:28px}
    .assessment-card h3{font-size:24px}
    .assessment-actions .btn{min-width:100%}
}
</style>

<div class="container section">
    <div class="assessment-shell">
        <div class="assessment-lead">
            <h2>Pick the quiz that fits what you want to learn.</h2>
            <p>
                One quiz helps people spot the kinds of report problems that matter.
                The other tests credit repair knowledge and rewards a perfect score with 25% off.
                Together, they create a cleaner starting lane than the usual giant funnel pages that try to sell the whole business dream before teaching anything useful.
            </p>
            <div class="assessment-strip">
                <span class="signal-pill primary">Training-first quizzes</span>
                <span class="signal-pill success">Perfect score earns 25% off</span>
                <span class="signal-pill dark">Built to teach, then convert</span>
            </div>
        </div>
        <div class="assessment-side">
            <div class="assessment-mini">
                <h3>Start here</h3>
                <p>This page is the quiz hub, so visitors can choose the quiz that actually fits them.</p>
            </div>
            <div class="assessment-mini" style="background:#1e293b;">
                <h3>Why it works</h3>
                <p>Teach something useful first, reward that learning, and then invite them into the software or the next business lane.</p>
            </div>
        </div>
    </div>

    <div class="assessment-paths">
        <div class="assessment-path">
            <h3>Start learning</h3>
            <p>Good for someone fixing their own credit, learning the language, and trying to understand what real report issues look like.</p>
            <ul>
                <li>Take the red flags quiz first</li>
                <li>See what kinds of reporting issues matter</li>
                <li>Move into the knowledge quiz when ready</li>
            </ul>
        </div>
        <div class="assessment-path">
            <h3>Start operating</h3>
            <p>Good for someone who wants to understand how this turns into a repeatable office workflow instead of just a one-off fix.</p>
            <ul>
                <li>Learn the basics through the quiz lane</li>
                <li>See the run-business page next</li>
                <li>Understand portal, CRM, and local intranet fit</li>
            </ul>
        </div>
        <div class="assessment-path">
            <h3>Start scaling</h3>
            <p>Good for an office that already has movement and wants less tool sprawl, better operations, and stronger built-in automation.</p>
            <ul>
                <li>Use the quizzes as lead magnets</li>
                <li>Move into built-in automation and scale pages</li>
                <li>Show that CreditSoft teaches and converts</li>
            </ul>
        </div>
    </div>

    <div class="assessment-grid">
        <a class="assessment-card consumer" href="/lawsuit-test">
            <span class="assessment-kicker consumer">Quiz 1</span>
            <h3>Credit report red flags quiz</h3>
            <p>See how well you can spot missing bureau entries, conflicting reporting, and the warning signs people often miss.</p>
            <ul class="assessment-points">
                <li>Built around real report problems, not trivia</li>
                <li>Good for public education and curiosity</li>
                <li>Teaches people what to actually look for</li>
            </ul>
            <div class="assessment-meta">
                <div>
                    <strong>Best for</strong>
                    Spotting warning signs
                </div>
                <div>
                    <strong>Outcome</strong>
                    Quiz score + result band
                </div>
            </div>
            <div class="assessment-actions">
                <span class="btn btn-primary">Open red flags quiz</span>
            </div>
        </a>

        <a class="assessment-card operator" href="/knowledge-quiz">
            <span class="assessment-kicker operator">Quiz 2</span>
            <h3>Credit repair knowledge quiz</h3>
            <p>Test your knowledge around Metro2, FCRA, FDCPA, dispute timing, and credit repair basics. A perfect score unlocks a unique 25%-off code.</p>
            <ul class="assessment-points">
                <li>Great for training, self-testing, and sales conversations</li>
                <li>Perfect score returns a unique 25%-off code</li>
                <li>Rewards learning instead of just collecting a lead</li>
            </ul>
            <div class="assessment-meta">
                <div>
                    <strong>Best for</strong>
                    Learning the basics
                </div>
                <div>
                    <strong>Outcome</strong>
                    Score + coupon if perfect
                </div>
            </div>
            <div class="assessment-actions">
                <span class="btn btn-primary">Open knowledge quiz</span>
                <span class="btn assessment-secondary">25% off for 10/10</span>
            </div>
        </a>
    </div>

    <div class="assessment-learn">
        <div>
            <h2>This should feel more like a learning lane than a dead-end quiz hub.</h2>
            <p>That is the whole point. The quiz page should help someone understand where to start, what they learn next, and how the software grows from personal understanding into office operations.</p>
            <ul>
                <li>Start with the red flags quiz if the goal is spotting report problems.</li>
                <li>Take the knowledge quiz if the goal is testing credit repair fundamentals.</li>
                <li>Move into the business and automation pages if the goal is operating or scaling.</li>
            </ul>
        </div>
        <div class="learn-side">
            <h3>Next steps after the quiz</h3>
            <p><a href="/start-repairing-credit" style="color:#bfdbfe;text-decoration:none;">Start Repairing Credit</a></p>
            <p><a href="/run-a-credit-repair-business" style="color:#bfdbfe;text-decoration:none;">Run a Credit Repair Business</a></p>
            <p><a href="/scale-your-credit-repair-business" style="color:#bfdbfe;text-decoration:none;">Scale Your Credit Repair Business</a></p>
            <p><a href="/built-in-automation" style="color:#bfdbfe;text-decoration:none;">Built-In Automation</a></p>
        </div>
    </div>

    <div class="assessment-note">
        More quiz types can plug into this hub later, but the two live quizzes right now are the credit report red flags quiz and the credit repair knowledge quiz.
    </div>

    <div class="assessment-disclaimer">
        These quizzes are educational only. They do not provide legal advice, and quiz results alone do not establish that any consumer claim or dispute outcome will succeed.
    </div>
</div>

<?php include 'footer.php'; ?>
