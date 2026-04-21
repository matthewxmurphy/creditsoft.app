<?php
declare(strict_types=1);

$config_path = dirname(__DIR__) . '/credit_config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

require_once __DIR__ . '/lead-intake.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$turnstile_site_key = defined('TURNSTILE_SITE_KEY') ? TURNSTILE_SITE_KEY : '';
$turnstile_secret = defined('TURNSTILE_SECRET_KEY') ? TURNSTILE_SECRET_KEY : '';

$starter_error = '';
$qualify_error = '';
$completed = false;
$step = 'starter';

$monitoring_options = [
    'SmartCredit',
    'Credit Karma',
    'IdentityIQ',
    'MyScoreIQ',
    'Experian',
    'TransUnion',
    'Equifax',
    'Other',
    'Not sure yet',
];

$workflow_options = [
    'Credit Repair Cloud',
    'DisputeFox',
    'White Label CRO',
    'Client Dispute Manager',
    'Manual docs and spreadsheets',
    'Another CRM or custom system',
    'No real system yet',
];

$merchant_status_options = ['Yes', 'No', 'In progress', 'Not sure'];
$website_status_options = ['Yes', 'No', 'In progress'];
$website_sentiment_options = ['Yes', 'No', 'Mixed feelings'];
$outsourcing_status_options = ['Yes', 'No', 'Only some parts'];
$roi_options = ['No idea yet', 'Rough idea only', 'We track it sometimes', 'We know our ROI and review it regularly'];
$team_size_options = ['Just me', '2-3 people', '4-10 people', '11+ people'];
$timeline_options = ['ASAP', 'This month', 'Next 90 days', 'Just researching'];
$client_count_options = ['0-25', '26-100', '101-250', '251-500', '500+'];

$intake = $_SESSION['creditsoft_lead_intake'] ?? [];
$plan_interest = creditsoft_lead_clean_text($_GET['plan'] ?? ($intake['plan_interest'] ?? ''), 80);

$starter = [
    'name' => creditsoft_lead_clean_text($intake['name'] ?? '', 255),
    'email' => creditsoft_lead_clean_text($intake['email'] ?? '', 255),
    'phone' => creditsoft_lead_clean_text($intake['phone'] ?? '', 50),
    'company' => creditsoft_lead_clean_text($intake['company'] ?? '', 255),
    'plan_interest' => $plan_interest,
    'lead_source' => creditsoft_lead_clean_text($intake['lead_source'] ?? 'subscribe_starter', 50) ?: 'subscribe_starter',
];

$qualify = [
    'client_count' => '',
    'monitoring_systems' => [],
    'current_workflow' => '',
    'merchant_status' => '',
    'merchant_provider' => '',
    'payment_methods' => '',
    'website_status' => '',
    'website_sentiment' => '',
    'outsourcing_status' => '',
    'outsourcing_notes' => '',
    'roi_visibility' => '',
    'team_size' => '',
    'switch_timeline' => '',
    'biggest_pain' => '',
    'primary_goal' => '',
    'additional_notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['intake_stage'] ?? '') === 'starter') {
    $starter['name'] = creditsoft_lead_clean_text($_POST['name'] ?? '', 255);
    $starter['email'] = strtolower(creditsoft_lead_clean_text($_POST['email'] ?? '', 255));
    $starter['phone'] = creditsoft_lead_clean_text($_POST['phone'] ?? '', 50);
    $starter['company'] = creditsoft_lead_clean_text($_POST['company'] ?? '', 255);
    $starter['plan_interest'] = creditsoft_lead_clean_text($_POST['plan_interest'] ?? '', 80);
    $starter['lead_source'] = creditsoft_lead_clean_text($_POST['lead_source'] ?? 'subscribe_starter', 50) ?: 'subscribe_starter';

    if ($starter['name'] === '' || $starter['email'] === '') {
        $starter_error = 'Please give us your name and a valid work email so we can hold your place in the intake.';
    } elseif (! creditsoft_lead_email_is_valid($starter['email'])) {
        $starter_error = 'Please use a valid email address.';
    } else {
        $lead = creditsoft_lead_upsert_basic([
            'name' => $starter['name'],
            'email' => $starter['email'],
            'phone' => $starter['phone'],
            'company' => $starter['company'],
            'plan_interest' => $starter['plan_interest'],
            'source' => $starter['lead_source'],
        ]);

        $_SESSION['creditsoft_lead_intake'] = [
            'lead_id' => (int) ($lead['id'] ?? 0),
            'name' => $lead['name'] ?? $starter['name'],
            'email' => $lead['email'] ?? $starter['email'],
            'phone' => $lead['phone'] ?? $starter['phone'],
            'company' => $lead['company'] ?? $starter['company'],
            'plan_interest' => $starter['plan_interest'],
            'lead_source' => $starter['lead_source'],
        ];

        if (! empty($lead['is_new']) && (int) ($lead['id'] ?? 0) > 0) {
            creditsoft_meta_capi_send_event('Lead', [
                'email' => (string) ($lead['email'] ?? $starter['email']),
                'phone' => (string) ($lead['phone'] ?? $starter['phone']),
                'external_id' => 'lead:' . (int) ($lead['id'] ?? 0),
            ], [
                'event_id' => 'lead-' . (int) ($lead['id'] ?? 0) . '-starter',
                'content_name' => $starter['plan_interest'] !== '' ? ('Office fit check · ' . $starter['plan_interest']) : 'Office fit check',
                'content_category' => 'office_fit_starter',
            ]);
        }

        header('Location: /subscribe?step=qualify');
        exit;
    }
}

if (($_GET['step'] ?? '') === 'qualify' && !empty($_SESSION['creditsoft_lead_intake']['email'])) {
    $step = 'qualify';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['intake_stage'] ?? '') === 'qualify') {
    $step = 'qualify';

    $starter['name'] = creditsoft_lead_clean_text($_POST['name'] ?? ($intake['name'] ?? ''), 255);
    $starter['email'] = strtolower(creditsoft_lead_clean_text($_POST['email'] ?? ($intake['email'] ?? ''), 255));
    $starter['phone'] = creditsoft_lead_clean_text($_POST['phone'] ?? ($intake['phone'] ?? ''), 50);
    $starter['company'] = creditsoft_lead_clean_text($_POST['company'] ?? ($intake['company'] ?? ''), 255);
    $starter['plan_interest'] = creditsoft_lead_clean_text($_POST['plan_interest'] ?? ($intake['plan_interest'] ?? ''), 80);
    $starter['lead_source'] = creditsoft_lead_clean_text($_POST['lead_source'] ?? ($intake['lead_source'] ?? 'subscribe_starter'), 50) ?: 'subscribe_starter';

    foreach (array_keys($qualify) as $key) {
        if ($key === 'monitoring_systems') {
            $qualify[$key] = creditsoft_lead_clean_list($_POST[$key] ?? []);
            continue;
        }

        $qualify[$key] = creditsoft_lead_clean_text($_POST[$key] ?? '', 4000);
    }

    if ($turnstile_secret !== '') {
        $response_token = trim((string) ($_POST['cf-turnstile-response'] ?? ''));

        if ($response_token === '') {
            $qualify_error = 'Please complete the security check.';
        } else {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://challenges.cloudflare.com/turnstile/v0/siteverify');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'secret' => $turnstile_secret,
                'response' => $response_token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $turnstile_result = json_decode((string) curl_exec($ch), true);

            if (empty($turnstile_result['success'])) {
                $qualify_error = 'Please complete the security check.';
            }
        }
    }

    if ($qualify_error === '' && ($starter['name'] === '' || $starter['email'] === '' || ! creditsoft_lead_email_is_valid($starter['email']))) {
        $qualify_error = 'We lost the basic company contact. Please start again from the first step.';
    } elseif ($qualify_error === '' && $qualify['client_count'] === '') {
        $qualify_error = 'Please tell us roughly how many clients you have.';
    } elseif ($qualify_error === '' && $qualify['current_workflow'] === '') {
        $qualify_error = 'Please tell us what you currently use to do the work.';
    } elseif ($qualify_error === '' && $qualify['merchant_status'] === '') {
        $qualify_error = 'Please tell us whether you already have a merchant setup.';
    } elseif ($qualify_error === '' && $qualify['website_status'] === '') {
        $qualify_error = 'Please tell us whether you already have a website.';
    } elseif ($qualify_error === '' && $qualify['outsourcing_status'] === '') {
        $qualify_error = 'Please tell us whether you outsource any of the work.';
    } elseif ($qualify_error === '' && $qualify['roi_visibility'] === '') {
        $qualify_error = 'Please tell us how well you know your ROI right now.';
    }

    if ($qualify_error === '') {
        $lead = creditsoft_lead_upsert_basic([
            'name' => $starter['name'],
            'email' => $starter['email'],
            'phone' => $starter['phone'],
            'company' => $starter['company'],
            'plan_interest' => $starter['plan_interest'],
            'source' => $starter['lead_source'] === 'homepage_intake' ? 'homepage_qualified' : 'subscribe_qualified',
        ]);

        $lead_id = (int) ($lead['id'] ?? ($intake['lead_id'] ?? 0));

        if ($lead_id <= 0 || ! creditsoft_lead_save_qualification($lead_id, array_merge($starter, $qualify, [
            'source' => $starter['lead_source'] === 'homepage_intake' ? 'homepage_qualified' : 'subscribe_qualified',
        ]))) {
            $qualify_error = 'We could not save the company profile yet. Please try again in a moment.';
        } else {
            creditsoft_meta_capi_send_event('CompleteRegistration', [
                'email' => (string) ($lead['email'] ?? $starter['email']),
                'phone' => (string) ($lead['phone'] ?? $starter['phone']),
                'external_id' => 'lead:' . $lead_id,
            ], [
                'event_id' => 'lead-' . $lead_id . '-qualified',
                'content_name' => $starter['plan_interest'] !== '' ? ('Qualified office fit check · ' . $starter['plan_interest']) : 'Qualified office fit check',
                'content_category' => 'office_fit_qualification',
                'status' => 'qualified',
            ]);

            $_SESSION['creditsoft_lead_intake_completed'] = [
                'name' => $starter['name'],
                'email' => $starter['email'],
                'company' => $starter['company'],
                'lead_id' => $lead_id,
            ];
            unset($_SESSION['creditsoft_lead_intake']);

            header('Location: /subscribe?step=done');
            exit;
        }
    }
}

if (($_GET['step'] ?? '') === 'done' && ! empty($_SESSION['creditsoft_lead_intake_completed'])) {
    $completed = true;
}

$completed_lead = $_SESSION['creditsoft_lead_intake_completed'] ?? null;

if ($completed) {
    unset($_SESSION['creditsoft_lead_intake_completed']);
}

$page_title = 'Office Fit Check';
$page_description = 'Answer a short set of questions so we can size the right CreditSoft plan, migration path, and rollout for your company.';
$page_hero = true;
$hero_title = $completed
    ? 'Thanks. We have what we need.'
    : ($step === 'qualify'
        ? 'Help us size the rollout.'
        : 'See if CreditSoft fits your company.');
$hero_subtitle = $completed
    ? 'Your company details are saved. We can now review fit, migration needs, and the right pricing lane.'
    : ($step === 'qualify'
        ? 'Tell us what you use today, how many clients you carry, and where the current stack is slowing the company down.'
        : 'Start with your contact info. Then answer the business questions that actually shape pricing, migration, and launch.');

require __DIR__ . '/header.php';
?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<style>
    .intake-shell { max-width: 1180px; margin: 0 auto; padding: 42px 20px 0; display: grid; gap: 26px; }
    .intake-progress { display: flex; gap: 12px; flex-wrap: wrap; }
    .progress-pill { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 999px; border: 1px solid var(--border); background: white; color: var(--gray); font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .progress-pill.active { background: #dbeafe; border-color: #93c5fd; color: #1d4ed8; }
    .intake-grid { display: grid; grid-template-columns: minmax(0, .95fr) minmax(0, 1.05fr); gap: 24px; }
    .intake-card { background: white; border: 1px solid var(--border); border-radius: 28px; padding: 28px; box-shadow: 0 18px 42px rgba(15,23,42,.06); }
    .intake-kicker { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; background: #eff6ff; color: #1d4ed8; font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; margin-bottom: 16px; }
    .intake-card h2, .intake-card h3 { font-size: 30px; line-height: 1.08; margin-bottom: 10px; }
    .intake-card p { color: var(--gray); margin-bottom: 16px; }
    .detail-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
    .detail-list li { border: 1px solid var(--border); background: #f8fafc; border-radius: 18px; padding: 14px 16px; color: var(--dark); }
    .intake-form { display: grid; gap: 16px; }
    .question-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .form-group { display: grid; gap: 7px; }
    .form-group.full { grid-column: 1 / -1; }
    .form-group label { font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: var(--gray); }
    .form-group input,
    .form-group select,
    .form-group textarea { width: 100%; padding: 14px 16px; border: 1px solid var(--border); border-radius: 16px; background: white; color: var(--dark); font-size: 16px; font-family: inherit; }
    .form-group textarea { min-height: 130px; resize: vertical; }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(37,99,235,0.12); }
    .choice-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .choice-option { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 16px; border: 1px solid var(--border); background: #f8fafc; color: var(--dark); }
    .choice-option input { width: 18px; height: 18px; accent-color: var(--primary); }
    .cta-row { display: flex; gap: 14px; flex-wrap: wrap; align-items: center; }
    .btn-secondary { background: white; color: var(--dark); border: 1px solid var(--border); }
    .btn-secondary:hover { background: #f8fafc; text-decoration: none; }
    .error-msg { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 14px 16px; border-radius: 16px; }
    .turnstile-wrap { display: flex; justify-content: center; padding: 6px 0; }
    .context-strip { display: grid; gap: 10px; padding: 16px 18px; border-radius: 18px; background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%); border: 1px solid #dbeafe; }
    .context-strip strong { color: var(--dark); }
    .completion-grid { display: grid; gap: 18px; }
    .completion-card { background: white; border: 1px solid var(--border); border-radius: 24px; padding: 26px; box-shadow: 0 18px 40px rgba(15,23,42,.06); }
    .completion-card h2 { font-size: 32px; margin-bottom: 10px; }
    .completion-meta { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-top: 18px; }
    .completion-meta div { border: 1px solid var(--border); border-radius: 18px; padding: 14px 16px; background: #f8fafc; }
    .completion-meta strong { display: block; font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: var(--gray); margin-bottom: 4px; }
    @media (max-width: 960px) {
        .intake-grid,
        .question-grid,
        .completion-meta,
        .choice-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="intake-shell">
    <div class="intake-progress">
        <span class="progress-pill <?= $step === 'starter' && ! $completed ? 'active' : '' ?>">Step 1: company contact</span>
        <span class="progress-pill <?= $step === 'qualify' ? 'active' : '' ?>">Step 2: company questions</span>
        <span class="progress-pill <?= $completed ? 'active' : '' ?>">Submitted</span>
    </div>

    <?php if ($completed && is_array($completed_lead)): ?>
        <div class="completion-grid">
            <section class="completion-card">
                <span class="intake-kicker">Saved</span>
                <h2>Your company profile is saved.</h2>
                <p>We now have the business context that matters: current stack, payment setup, outsourcing, monitoring, and the company size behind the move.</p>
                <div class="completion-meta">
                    <div>
                        <strong>Office</strong>
                        <span><?= htmlspecialchars($completed_lead['company'] ?: 'Not provided yet', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div>
                        <strong>Contact</strong>
                        <span><?= htmlspecialchars($completed_lead['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div>
                        <strong>Email</strong>
                        <span><?= htmlspecialchars($completed_lead['email'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </section>

            <section class="completion-card">
                <span class="intake-kicker">Next</span>
                <h3>What to do next</h3>
                <ul class="detail-list">
                    <li>We can review the current stack before talking migration, rollout, or pricing.</li>
                    <li>If you already know the company needs help, review pricing and migration while we look things over.</li>
                    <li>If you want to add more context, email <a href="mailto:hello@creditsoft.app">hello@creditsoft.app</a> from the same address you used here.</li>
                </ul>
                <div class="cta-row" style="margin-top:18px;">
                    <a href="/migration" class="btn btn-primary">See migration</a>
                    <a href="/pricing" class="btn btn-secondary">See pricing</a>
                    <a href="/roadmap" class="btn btn-secondary">See roadmap</a>
                </div>
            </section>
        </div>
    <?php else: ?>
        <div class="intake-grid">
            <section class="intake-card">
                <span class="intake-kicker"><?= $step === 'qualify' ? 'Why step two matters' : 'How this works' ?></span>
                <h2><?= $step === 'qualify' ? 'This is where we size the move.' : 'Start with contact, then company details.' ?></h2>
                <p>
                    <?= $step === 'qualify'
                        ? 'Now we can see client volume, monitoring sources, current software, payment setup, website status, outsourcing, and whether the move needs to be small or more involved.'
                        : 'The first step is just the company contact. The second step gives us the few details that actually shape migration, rollout, and pricing.' ?>
                </p>
                <ul class="detail-list">
                    <li>Client count tells us whether this is a small company, a growing team, or a larger migration.</li>
                    <li>Monitoring and current software tell us what import and workflow pain the company is actually living with.</li>
                    <li>Merchant setup, website, outsourcing, and ROI keep the rollout conversation grounded in reality.</li>
                </ul>
            </section>

            <section class="intake-card">
                <?php if ($step === 'qualify'): ?>
                    <span class="intake-kicker">Step 2</span>
                    <h3>Office questions</h3>
                    <p>Answer the real business questions so we can size pricing, migration, and launch without guessing.</p>

                    <div class="context-strip">
                        <div><strong>Contact</strong> <?= htmlspecialchars($starter['name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($starter['email'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div><strong>Office</strong> <?= htmlspecialchars($starter['company'] ?: 'Not provided yet', ENT_QUOTES, 'UTF-8') ?></div>
                        <?php if ($starter['plan_interest'] !== ''): ?>
                            <div><strong>Plan interest</strong> <?= htmlspecialchars($starter['plan_interest'], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($qualify_error !== ''): ?><div class="error-msg" style="margin-top:16px;"><?= htmlspecialchars($qualify_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

                    <form class="intake-form" method="POST">
                        <input type="hidden" name="intake_stage" value="qualify">
                        <input type="hidden" name="name" value="<?= htmlspecialchars($starter['name'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="email" value="<?= htmlspecialchars($starter['email'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="phone" value="<?= htmlspecialchars($starter['phone'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="company" value="<?= htmlspecialchars($starter['company'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="plan_interest" value="<?= htmlspecialchars($starter['plan_interest'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="lead_source" value="<?= htmlspecialchars($starter['lead_source'], ENT_QUOTES, 'UTF-8') ?>">

                        <div class="question-grid">
                            <div class="form-group">
                                <label for="client_count">How many clients do you have?</label>
                                <select id="client_count" name="client_count" required>
                                    <option value="">Choose one</option>
                                    <?php foreach ($client_count_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>" <?= $qualify['client_count'] === $option ? 'selected' : '' ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="current_workflow">What do you use to do the work today?</label>
                                <select id="current_workflow" name="current_workflow" required>
                                    <option value="">Choose one</option>
                                    <?php foreach ($workflow_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>" <?= $qualify['current_workflow'] === $option ? 'selected' : '' ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group full">
                                <label>Which monitoring systems do you use?</label>
                                <div class="choice-grid">
                                    <?php foreach ($monitoring_options as $option): ?>
                                        <label class="choice-option">
                                            <input type="checkbox" name="monitoring_systems[]" value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($option, $qualify['monitoring_systems'], true) ? 'checked' : '' ?>>
                                            <span><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="merchant_status">Do you already have a merchant?</label>
                                <select id="merchant_status" name="merchant_status" required>
                                    <option value="">Choose one</option>
                                    <?php foreach ($merchant_status_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>" <?= $qualify['merchant_status'] === $option ? 'selected' : '' ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="merchant_provider">If yes, who is it?</label>
                                <input id="merchant_provider" type="text" name="merchant_provider" placeholder="Authorize.net, NMI, GOAT Payments, etc." value="<?= htmlspecialchars($qualify['merchant_provider'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="form-group full">
                                <label for="payment_methods">If no merchant, how do you take payments now?</label>
                                <input id="payment_methods" type="text" name="payment_methods" placeholder="Zelle, Cash App, Apple Pay, invoices, manual ACH, or anything else" value="<?= htmlspecialchars($qualify['payment_methods'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="form-group">
                                <label for="website_status">Do you currently have a website?</label>
                                <select id="website_status" name="website_status" required>
                                    <option value="">Choose one</option>
                                    <?php foreach ($website_status_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>" <?= $qualify['website_status'] === $option ? 'selected' : '' ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="website_sentiment">If you have one, do you like it?</label>
                                <select id="website_sentiment" name="website_sentiment">
                                    <option value="">Choose one</option>
                                    <?php foreach ($website_sentiment_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>" <?= $qualify['website_sentiment'] === $option ? 'selected' : '' ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="outsourcing_status">Do you outsource any of the work?</label>
                                <select id="outsourcing_status" name="outsourcing_status" required>
                                    <option value="">Choose one</option>
                                    <?php foreach ($outsourcing_status_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>" <?= $qualify['outsourcing_status'] === $option ? 'selected' : '' ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="roi_visibility">Do you have any idea what your ROI is?</label>
                                <select id="roi_visibility" name="roi_visibility" required>
                                    <option value="">Choose one</option>
                                    <?php foreach ($roi_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>" <?= $qualify['roi_visibility'] === $option ? 'selected' : '' ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="team_size">How big is the team?</label>
                                <select id="team_size" name="team_size">
                                    <option value="">Choose one</option>
                                    <?php foreach ($team_size_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>" <?= $qualify['team_size'] === $option ? 'selected' : '' ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="switch_timeline">When would you want to switch?</label>
                                <select id="switch_timeline" name="switch_timeline">
                                    <option value="">Choose one</option>
                                    <?php foreach ($timeline_options as $option): ?>
                                        <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>" <?= $qualify['switch_timeline'] === $option ? 'selected' : '' ?>><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group full">
                                <label for="outsourcing_notes">If you outsource, what parts?</label>
                                <input id="outsourcing_notes" type="text" name="outsourcing_notes" placeholder="Disputes, client updates, calls, letters, websites, or anything else" value="<?= htmlspecialchars($qualify['outsourcing_notes'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="form-group full">
                                <label for="biggest_pain">Biggest pain right now</label>
                                <textarea id="biggest_pain" name="biggest_pain" placeholder="Where is the company feeling the most friction right now?"><?= htmlspecialchars($qualify['biggest_pain'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                            <div class="form-group full">
                                <label for="primary_goal">What do you most want the next system to fix?</label>
                                <textarea id="primary_goal" name="primary_goal" placeholder="Better workflows, monitoring imports, websites, portal, payments, migration help, automation, compliance, or something else."><?= htmlspecialchars($qualify['primary_goal'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                            <div class="form-group full">
                                <label for="additional_notes">Anything else we should know?</label>
                                <textarea id="additional_notes" name="additional_notes" placeholder="Add anything useful here, including odd edge cases or what you are nervous about."><?= htmlspecialchars($qualify['additional_notes'], ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                        </div>

                        <div class="turnstile-wrap">
                            <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($turnstile_site_key, ENT_QUOTES, 'UTF-8') ?>"></div>
                        </div>

                        <div class="cta-row">
                            <button type="submit" class="btn btn-primary">Submit company profile</button>
                            <a href="/pricing" class="btn btn-secondary">Review pricing again</a>
                        </div>
                    </form>
                <?php else: ?>
                    <span class="intake-kicker">Step 1</span>
                    <h3>Company contact</h3>
                    <p>Start with the person and company name. The next step covers the business questions that shape the switch.</p>

                    <?php if ($starter_error !== ''): ?><div class="error-msg"><?= htmlspecialchars($starter_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

                    <form class="intake-form" method="POST">
                        <input type="hidden" name="intake_stage" value="starter">
                        <input type="hidden" name="lead_source" value="<?= htmlspecialchars($starter['lead_source'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="question-grid">
                            <div class="form-group">
                                <label for="starter_name">Name</label>
                                <input id="starter_name" type="text" name="name" required placeholder="Your name" value="<?= htmlspecialchars($starter['name'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="form-group">
                                <label for="starter_email">Work email</label>
                                <input id="starter_email" type="email" name="email" required placeholder="you@company.com" value="<?= htmlspecialchars($starter['email'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="form-group">
                                <label for="starter_company">Office name</label>
                                <input id="starter_company" type="text" name="company" placeholder="Company name" value="<?= htmlspecialchars($starter['company'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="form-group">
                                <label for="starter_phone">Phone</label>
                                <input id="starter_phone" type="tel" name="phone" placeholder="(555) 555-5555" value="<?= htmlspecialchars($starter['phone'], ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="form-group full">
                                <label for="plan_interest">Plan interest</label>
                                <select id="plan_interest" name="plan_interest">
                                    <option value="">Not sure yet</option>
                                    <option value="Enterprise" <?= $starter['plan_interest'] === 'Enterprise' ? 'selected' : '' ?>>Enterprise</option>
                                    <option value="Enterprise Pro" <?= $starter['plan_interest'] === 'Enterprise Pro' ? 'selected' : '' ?>>Enterprise Pro</option>
                                    <option value="Lifetime" <?= $starter['plan_interest'] === 'Lifetime' ? 'selected' : '' ?>>Lifetime interest</option>
                                </select>
                            </div>
                        </div>

                        <div class="cta-row">
                            <button type="submit" class="btn btn-primary">Continue</button>
                            <a href="/pricing" class="btn btn-secondary">Review pricing first</a>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/footer.php'; ?>
