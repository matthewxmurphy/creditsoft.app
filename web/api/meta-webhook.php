<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/site-tracking-config.php';
require_once dirname(__DIR__) . '/meta-social-manager.php';

$tracking = creditsoft_site_tracking_load();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $mode = trim((string) ($_GET['hub_mode'] ?? $_GET['hub.mode'] ?? ''));
    $token = trim((string) ($_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? ''));
    $challenge = trim((string) ($_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? ''));
    $expected = trim((string) ($tracking['meta_webhook_verify_token'] ?? ''));

    if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo $challenge;
        exit;
    }

    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Meta webhook verification failed.']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);

if (! is_array($payload)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Meta webhook payload was not valid JSON.']);
    exit;
}

$processed = 0;
$summary = [
    'object' => (string) ($payload['object'] ?? ''),
    'entries' => count($payload['entry'] ?? []),
    'leadgen_ids' => [],
];

foreach (($payload['entry'] ?? []) as $entry) {
    if (! is_array($entry)) {
        continue;
    }

    foreach (($entry['changes'] ?? []) as $change) {
        if (! is_array($change)) {
            continue;
        }

        if ((string) ($change['field'] ?? '') !== 'leadgen') {
            continue;
        }

        $value = is_array($change['value'] ?? null) ? $change['value'] : [];
        $leadgenId = trim((string) ($value['leadgen_id'] ?? ''));

        if ($leadgenId === '') {
            continue;
        }

        $summary['leadgen_ids'][] = $leadgenId;
        $result = creditsoft_meta_social_process_leadgen_id($leadgenId, [
            'form_id' => trim((string) ($value['form_id'] ?? '')),
            'page_id' => trim((string) ($value['page_id'] ?? '')),
        ], $tracking);

        if (! empty($result['success'])) {
            $processed++;
        }
    }
}

$summary['processed'] = $processed;
creditsoft_meta_social_record_webhook($payload, $summary);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'processed' => $processed]);
