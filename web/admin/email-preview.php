<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/zelle-payments.php';

$publicPreview = (string) ($_GET['public'] ?? '') === '1';

if (! $publicPreview) {
    cs_site_admin_require_login();
}

function cs_site_email_preview_type(string $type): string
{
    return match ($type) {
        'balance_due', 'transaction_completed', 'payment_help' => $type,
        default => 'license',
    };
}

function cs_site_email_preview_payload(string $type): array
{
    $type = cs_site_email_preview_type($type);
    $sampleEmail = 'customer@example.com';

    if ($type === 'balance_due') {
        $replyUrl = 'mailto:hello@creditsoft.app?subject=' . rawurlencode('CreditSoft balance due timing') . '&body=' . rawurlencode("Hi CreditSoft,\n\nI expect to send the remaining balance on:\n\n");

        return [
            'label' => 'Balance Due',
            'subject' => 'CreditSoft payment received - balance still due',
            'html' => cs_site_zelle_branded_email([
                'tone' => 'warning',
                'preheader' => 'Your Zelle transaction was completed, but the payment did not cover the full CreditSoft order.',
                'eyebrow' => 'Transaction completed · Balance due',
                'title' => 'Payment received. Balance still needed.',
                'intro' => 'Hi Customer, your bank marked the transaction completed and CreditSoft matched it to your account email. The payment came in below the total needed to activate this order.',
                'paragraphs' => [
                    'The payment is saved in your billing history, but no license is activated from a short payment.',
                    'Because this was a partial payment, the Zelle/Cash App pay-in-full discount was removed. Discounts only apply when the full discounted total is paid in one payment.',
                    'Please send the remaining balance by Zelle to z@creditsoft.app and use this email address as the memo: '.$sampleEmail,
                    'Reply with when you expect to send the remaining balance so we can keep the order moving.',
                    'Once the balance is paid, CreditSoft will finish the license activation or renewal.',
                ],
                'summary' => [
                    'Payment method' => 'Zelle',
                    'Transaction status' => 'Completed by bank',
                    'Payment date' => 'Apr 17, 2026',
                    'Plan' => 'Enterprise / monthly',
                    'Payment received' => '$5.00',
                    'Order total' => '$99.95',
                    'Remaining balance' => '$94.95',
                    'Memo received' => 'Test',
                    'Memo needed' => $sampleEmail,
                    'Transaction number' => 'ZELLE-20260417-EXAMPLE',
                    'Customer confirmation' => '5106633448',
                ],
                'action_url' => $replyUrl,
                'action_label' => 'Reply with payment timing',
                'secondary_url' => cs_site_zelle_absolute_public_url('/client-portal', ['email' => $sampleEmail]),
                'secondary_label' => 'open the billing history',
                'footer_note' => 'No license is activated from a short payment. This protects the customer record and keeps the billing history clean.',
            ]),
        ];
    }

    if ($type === 'transaction_completed') {
        return [
            'label' => 'Transaction Completed',
            'subject' => 'CreditSoft Transaction Complete - receipt and license',
            'html' => cs_site_zelle_branded_email([
                'tone' => 'standard',
                'preheader' => 'Transaction complete. Your CreditSoft receipt and license are ready.',
                'eyebrow' => 'Transaction Complete',
                'title' => 'Your CreditSoft receipt and license.',
                'intro' => 'Hi Customer, CreditSoft received and matched your payment. This receipt confirms that your license is active.',
                'paragraphs' => [
                    'Keep this receipt for your records. It includes the payment status, transaction number, license key, expiration date, and setup link.',
                    'To help future receipts stay out of spam, add hello@creditsoft.app to your contacts. You can download the CreditSoft contact card below.',
                ],
                'summary' => [
                    'Payment method' => 'Zelle',
                    'Transaction status' => 'Transaction Complete',
                    'Payment received' => '$89.95',
                    'Plan' => 'Enterprise / monthly',
                    'License key' => 'CS-EXAMPLE-2026-READY',
                    'Transaction number' => 'ZELLE-20260417-EXAMPLE',
                    'Expires' => date('M j, Y', strtotime('+1 month')),
                    'Email delivery' => 'Sent',
                ],
                'action_url' => cs_site_zelle_absolute_public_url('/login', ['email' => $sampleEmail]),
                'action_label' => 'Set your password',
                'secondary_url' => cs_site_zelle_contact_card_url(),
                'secondary_label' => 'download the CreditSoft contact card',
                'footer_note' => 'If anything looks off, reply to this email and we will help.',
            ]),
        ];
    }

    if ($type === 'payment_help') {
        return [
            'label' => 'Payment Help Ticket',
            'subject' => 'CreditSoft payment help ticket received',
            'html' => cs_site_zelle_branded_email([
                'tone' => 'standard',
                'preheader' => 'CreditSoft received your payment help request and will use it to match your payment.',
                'eyebrow' => 'Payment help ticket',
                'title' => 'We received your payment help request.',
                'intro' => 'Hi Customer, CreditSoft has your payment help request. This gives support the details needed to match a Zelle or Cash App payment that did not automatically connect to a license.',
                'paragraphs' => [
                    'Support will compare your payer name, transaction or confirmation number, memo, amount, and screenshot proof against payment mail and checkout records.',
                    'Please remember to include your CreditSoft account email in the payment memo in the future so your license can match automatically.',
                ],
                'summary' => [
                    'Ticket number' => 'PAY-20260417-EXAMPLE',
                    'Customer email' => $sampleEmail,
                    'Payment method' => 'Zelle / Cash App',
                    'Amount reported' => '$89.95',
                    'Transaction or confirmation' => '5106633448',
                    'Screenshot proof' => 'Attached',
                    'Status' => 'Support review',
                ],
                'action_url' => cs_site_zelle_absolute_public_url('/client-portal', ['email' => $sampleEmail]),
                'action_label' => 'Open billing history',
                'secondary_url' => cs_site_zelle_absolute_public_url('/payment-help'),
                'secondary_label' => 'update payment help details',
                'footer_note' => 'If the ticket details match a trusted payment, CreditSoft can issue or finish the license from the Payments panel.',
            ]),
        ];
    }

    return [
        'label' => 'Receipt + License',
        'subject' => 'CreditSoft Transaction Complete - receipt and license',
        'html' => cs_site_zelle_branded_email([
            'tone' => 'standard',
            'preheader' => 'Transaction complete. Your CreditSoft receipt and license are ready.',
            'eyebrow' => 'Transaction Complete',
            'title' => 'Your CreditSoft receipt and license.',
            'intro' => 'Hi Customer, CreditSoft received and matched your payment. This receipt confirms that your license is active.',
            'paragraphs' => [
                'Keep this receipt for your records. It includes the payment status, transaction number, license key, expiration date, and setup link.',
                'To help future receipts stay out of spam, add hello@creditsoft.app to your contacts. You can download the CreditSoft contact card below.',
            ],
            'summary' => [
                'Payment method' => 'Zelle',
                'Transaction status' => 'Transaction Complete',
                'Amount paid' => '$89.95',
                'Plan' => 'Enterprise / monthly',
                'Transaction number' => 'ZELLE-20260417-EXAMPLE',
                'License key' => 'CS-EXAMPLE-2026-READY',
                'Expires' => date('M j, Y', strtotime('+1 month')),
                'Email delivery' => 'Sent',
            ],
            'action_url' => cs_site_zelle_absolute_public_url('/login', ['email' => $sampleEmail]),
            'action_label' => 'Set your password',
            'secondary_url' => cs_site_zelle_contact_card_url(),
            'secondary_label' => 'download the CreditSoft contact card',
            'footer_note' => 'If anything looks off, reply to this email and we will help.',
        ]),
    ];
}

function cs_site_email_preview_payment_payload(int $messageId): ?array
{
    if ($messageId <= 0) {
        return null;
    }

    $pdo = cs_site_admin_db();
    if (! $pdo instanceof PDO) {
        return null;
    }

    cs_site_zelle_ensure_tables($pdo);
    $stmt = $pdo->prepare('SELECT * FROM zelle_payment_messages WHERE id = ? LIMIT 1');
    $stmt->execute([$messageId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);

    if (! is_array($message) || trim((string) ($message['license_key'] ?? '')) === '') {
        return null;
    }

    $customerEmail = cs_site_zelle_normalize_email((string) ($message['sender_email'] ?? 'customer@example.com')) ?: 'customer@example.com';
    $customerName = cs_site_zelle_clean_sender_display_name((string) ($message['sender_name'] ?? 'Customer')) ?: 'Customer';
    $expiresAt = '';

    if (! empty($message['license_id'])) {
        $licenseStmt = $pdo->prepare('SELECT expires_at FROM licenses WHERE id = ? LIMIT 1');
        $licenseStmt->execute([(int) $message['license_id']]);
        $expiresAt = (string) ($licenseStmt->fetchColumn() ?: '');
    }

    return [
        'label' => 'Receipt + License',
        'subject' => 'CreditSoft Transaction Complete - receipt and license',
        'html' => cs_site_zelle_branded_email([
            'tone' => 'standard',
            'preheader' => 'Transaction complete. Your CreditSoft receipt and license are ready.',
            'eyebrow' => 'Transaction Complete',
            'title' => 'Your CreditSoft receipt and license.',
            'intro' => 'Hi ' . $customerName . ', CreditSoft received and matched your payment. This receipt confirms that your license is active.',
            'paragraphs' => [
                'Keep this receipt for your records. It includes the payment status, transaction number, license key, expiration date, and setup link.',
                'To help future receipts stay out of spam, add hello@creditsoft.app to your contacts. You can download the CreditSoft contact card below.',
            ],
            'summary' => [
                'Payment method' => 'Zelle',
                'Transaction status' => 'Transaction Complete',
                'Amount paid' => isset($message['amount']) && is_numeric($message['amount']) ? cs_site_zelle_money((float) $message['amount']) : 'Paid',
                'Plan' => trim(cs_site_zelle_label_from_key((string) ($message['plan_key'] ?? 'CreditSoft')) . ' / ' . strtolower(cs_site_zelle_label_from_key((string) ($message['billing'] ?? 'monthly')))),
                'Transaction number' => (string) ($message['transaction_id'] ?? ''),
                'License key' => (string) ($message['license_key'] ?? ''),
                'Expires' => $expiresAt !== '' ? date('M j, Y', strtotime($expiresAt)) : 'Active',
                'License email' => ! empty($message['email_sent_at']) ? 'Sent ' . date('M j, Y g:i a', strtotime((string) $message['email_sent_at'])) : 'Not sent yet',
            ],
            'action_url' => (string) ($message['onboarding_url'] ?? '') ?: cs_site_zelle_absolute_public_url('/client-portal', ['email' => $customerEmail]),
            'action_label' => ! empty($message['onboarding_url']) ? 'Set your password' : 'Open billing history',
            'secondary_url' => cs_site_zelle_contact_card_url(),
            'secondary_label' => 'download the CreditSoft contact card',
            'footer_note' => 'If anything looks off, reply to this email and we will help.',
        ]),
    ];
}

$type = cs_site_email_preview_type((string) ($_GET['type'] ?? 'license'));
$messageId = ! $publicPreview ? (int) ($_GET['message_id'] ?? 0) : 0;
$payload = cs_site_email_preview_payment_payload($messageId) ?? cs_site_email_preview_payload($type);

if (($_GET['raw'] ?? '') === '1') {
    header('Content-Type: text/html; charset=UTF-8');
    echo $payload['html'];
    exit;
}

$currentType = cs_site_email_preview_type($type);
$rawPreviewQuery = ['type' => $currentType, 'raw' => '1'];
if ($messageId > 0) {
    $rawPreviewQuery['message_id'] = $messageId;
}
$previewUrl = $publicPreview
    ? cs_site_public_url('/admin/email-preview.php', ['type' => $currentType, 'raw' => '1', 'public' => '1'])
    : cs_site_admin_url('/email-preview.php', $rawPreviewQuery);
$licensePreviewUrl = $publicPreview
    ? cs_site_public_url('/admin/email-preview.php', ['type' => 'license', 'public' => '1'])
    : cs_site_admin_url('/email-preview.php', ['type' => 'license']);
$completedPreviewUrl = $publicPreview
    ? cs_site_public_url('/admin/email-preview.php', ['type' => 'transaction_completed', 'public' => '1'])
    : cs_site_admin_url('/email-preview.php', ['type' => 'transaction_completed']);
$balancePreviewUrl = $publicPreview
    ? cs_site_public_url('/admin/email-preview.php', ['type' => 'balance_due', 'public' => '1'])
    : cs_site_admin_url('/email-preview.php', ['type' => 'balance_due']);
$paymentHelpPreviewUrl = $publicPreview
    ? cs_site_public_url('/admin/email-preview.php', ['type' => 'payment_help', 'public' => '1'])
    : cs_site_admin_url('/email-preview.php', ['type' => 'payment_help']);
$templateOptions = [
    'transaction_completed' => ['label' => 'Transaction completed', 'url' => $completedPreviewUrl],
    'license' => ['label' => 'Receipt + license', 'url' => $licensePreviewUrl],
    'balance_due' => ['label' => 'Balance due', 'url' => $balancePreviewUrl],
    'payment_help' => ['label' => 'Payment help ticket', 'url' => $paymentHelpPreviewUrl],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CreditSoft Email Preview</title>
    <style>
        :root { --ink:#111827; --muted:#64748b; --line:#e5e0d0; --paper:#f5f2e8; --yellow:#facc15; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; background:radial-gradient(circle at top left,#fffdf2 0,#f5f2e8 48%,#eee7d4 100%); color:var(--ink); font-family:Inter,Arial,sans-serif; }
        .preview-shell { width:min(1120px, calc(100% - 32px)); margin:0 auto; padding:32px 0 46px; }
        .preview-top { display:flex; justify-content:space-between; gap:18px; align-items:end; margin-bottom:20px; }
        .preview-top h1 { margin:0 0 8px; font-size:36px; line-height:1; }
        .preview-top p { margin:0; color:var(--muted); max-width:660px; line-height:1.55; }
        .preview-actions { display:flex; flex-wrap:wrap; gap:10px; }
        .preview-actions a { display:inline-flex; align-items:center; justify-content:center; padding:11px 14px; border-radius:12px; border:1px solid var(--line); background:#fff; color:var(--ink); text-decoration:none; font-size:13px; font-weight:800; }
        .preview-actions a.is-active { background:#111827; color:#fff; border-color:#111827; }
        .template-menu { position:relative; }
        .template-menu summary {
            list-style:none;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:9px;
            padding:11px 14px;
            border-radius:12px;
            border:1px solid #111827;
            background:#111827;
            color:#fff;
            font-size:13px;
            font-weight:800;
            cursor:pointer;
        }
        .template-menu summary::-webkit-details-marker { display:none; }
        .template-menu summary::after { content:'▾'; font-size:11px; opacity:.72; }
        .template-menu[open] summary::after { transform:rotate(180deg); }
        .template-menu-panel {
            position:absolute;
            right:0;
            top:calc(100% + 8px);
            z-index:20;
            min-width:245px;
            display:grid;
            gap:6px;
            padding:8px;
            border-radius:16px;
            border:1px solid var(--line);
            background:#fff;
            box-shadow:0 22px 44px rgba(15,23,42,.16);
        }
        .template-menu-panel a {
            justify-content:flex-start;
            width:100%;
            border:0;
            border-radius:11px;
            padding:10px 11px;
            background:transparent;
        }
        .template-menu-panel a:hover,
        .template-menu-panel a.is-active { background:#111827; color:#fff; }
        .preview-frame { background:#111827; border-radius:28px; padding:16px; box-shadow:0 28px 60px rgba(15,23,42,.18); }
        .preview-browser { display:flex; justify-content:space-between; gap:12px; align-items:center; padding:0 6px 14px; color:#cbd5e1; font-size:12px; }
        .preview-dots { display:flex; gap:7px; }
        .preview-dots span { width:11px; height:11px; border-radius:999px; display:block; }
        .preview-dots span:nth-child(1) { background:#ef4444; }
        .preview-dots span:nth-child(2) { background:#f59e0b; }
        .preview-dots span:nth-child(3) { background:#22c55e; }
        iframe { width:100%; min-height:860px; border:0; border-radius:18px; background:#fff; }
        .subject { background:#fff; border:1px solid var(--line); border-radius:18px; padding:14px 16px; margin-bottom:16px; color:var(--muted); }
        .subject strong { color:var(--ink); }
        @media (max-width:760px) { .preview-top { display:grid; align-items:start; } iframe { min-height:760px; } }
    </style>
</head>
<body>
<main class="preview-shell">
    <div class="preview-top">
        <div>
            <h1>Branded Email Preview</h1>
            <p>This is the customer-facing CreditSoft email shell. Use the template menu to preview payment-completed, license-ready, balance-due, and payment-help messages from one place.</p>
        </div>
        <div class="preview-actions">
            <details class="template-menu">
                <summary><?= htmlspecialchars((string) ($templateOptions[$currentType]['label'] ?? 'Choose template'), ENT_QUOTES, 'UTF-8') ?></summary>
                <div class="template-menu-panel">
                    <?php foreach ($templateOptions as $templateType => $template): ?>
                        <a class="<?= $currentType === $templateType ? 'is-active' : '' ?>" href="<?= htmlspecialchars((string) $template['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $template['label'], ENT_QUOTES, 'UTF-8') ?></a>
                    <?php endforeach; ?>
                </div>
            </details>
        </div>
    </div>
    <div class="subject"><strong>Subject:</strong> <?= htmlspecialchars((string) $payload['subject'], ENT_QUOTES, 'UTF-8') ?></div>
    <section class="preview-frame" aria-label="<?= htmlspecialchars((string) $payload['label'], ENT_QUOTES, 'UTF-8') ?> email preview">
        <div class="preview-browser">
            <div class="preview-dots"><span></span><span></span><span></span></div>
            <div><?= htmlspecialchars((string) $payload['label'], ENT_QUOTES, 'UTF-8') ?> sample</div>
        </div>
        <iframe title="CreditSoft branded email preview" src="<?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?>"></iframe>
    </section>
</main>
</body>
</html>
