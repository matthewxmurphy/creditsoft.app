<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/zelle-payments.php';

$config = cs_site_zelle_mailbox_config();
$isCli = PHP_SAPI === 'cli';

if (! $isCli) {
    $token = trim((string) ($_GET['token'] ?? ''));
    $expected = trim((string) ($config['cron_token'] ?? ''));

    if ($expected === '' || ! hash_equals($expected, $token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }
}

$httpStatus = 200;

try {
    $result = cs_site_zelle_process_inbox(100);

    if (! $isCli && (string) ($_GET['retry_email'] ?? '') === '1') {
        $pdo = cs_site_admin_db();
        if ($pdo instanceof PDO) {
            $retry = cs_site_zelle_send_pending_license_emails($pdo, 25, true);
            $result['license_email_attempted'] = (int) ($result['license_email_attempted'] ?? 0) + (int) ($retry['attempted'] ?? 0);
            $result['license_email_sent'] = (int) ($result['license_email_sent'] ?? 0) + (int) ($retry['sent'] ?? 0);
            $result['license_email_failed'] = (int) ($result['license_email_failed'] ?? 0) + (int) ($retry['failed'] ?? 0);
            $result['forced_email_retry'] = $retry;
        }
    }
} catch (Throwable $exception) {
    error_log('CreditSoft Zelle checker failed: ' . $exception->getMessage());
    $httpStatus = 500;
    $result = [
        'success' => false,
        'error' => 'Zelle checker failed before it could finish.',
        'exception' => get_class($exception),
        'detail' => $exception->getMessage(),
    ];
}

if (! $isCli && (string) ($_GET['debug'] ?? '') === '1') {
    $paymentData = cs_site_zelle_payment_data();
    $recentMessages = [];

    foreach (array_slice((array) ($paymentData['messages'] ?? []), 0, 12) as $message) {
        $senderEmail = (string) ($message['sender_email'] ?? '');
        $fromEmail = (string) ($message['from_email'] ?? '');
        $recentMessages[] = [
            'id' => (int) ($message['id'] ?? 0),
            'received_at' => (string) ($message['received_at'] ?? ''),
            'transaction_id' => (string) ($message['transaction_id'] ?? ''),
            'amount' => $message['amount'] ?? null,
            'expected_amount' => $message['expected_amount'] ?? null,
            'balance_due' => $message['balance_due'] ?? null,
            'sender_name' => (string) ($message['sender_name'] ?? ''),
            'sender_email_hint' => $senderEmail !== '' && str_contains($senderEmail, '@') ? '***' . strstr($senderEmail, '@') : '',
            'from_domain' => $fromEmail !== '' && str_contains($fromEmail, '@') ? substr(strrchr($fromEmail, '@') ?: '', 1) : '',
            'status' => (string) ($message['status'] ?? ''),
            'payment_status' => (string) ($message['payment_status'] ?? ''),
            'match_type' => (string) ($message['match_type'] ?? ''),
            'email_sent' => ! empty($message['email_sent_at']),
            'balance_email_sent' => ! empty($message['balance_email_sent_at']),
            'email_attempt_count' => (int) ($message['email_attempt_count'] ?? 0),
            'email_last_error' => (string) ($message['email_last_error'] ?? ''),
            'processed_at' => (string) ($message['processed_at'] ?? ''),
        ];
    }

    $result['debug'] = [
        'stats' => $paymentData['stats'] ?? [],
        'recent_messages' => $recentMessages,
        'data_error' => $paymentData['error'] ?? null,
    ];
}

if (! $isCli) {
    http_response_code($httpStatus);
    header('Content-Type: application/json');
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit(empty($result['success']) ? 1 : 0);
