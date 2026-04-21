<?php
declare(strict_types=1);

require_once __DIR__ . '/_bridge.php';

$requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
$bridgePath = preg_replace('#^/api/v1/?#', '', $requestPath) ?? '';
$bridgePath = trim($bridgePath, '/');

if ($bridgePath !== '' && $bridgePath !== 'index.php') {
    creditsoft_bridge_forward($bridgePath);
}

$config = creditsoft_bridge_load_config();
$targetBaseUrls = creditsoft_bridge_target_base_urls($config);

creditsoft_bridge_json($targetBaseUrls !== [] ? 200 : 503, [
    'name' => 'CreditSoft Website API Bridge',
    'success' => $targetBaseUrls !== [],
    'configured' => $targetBaseUrls !== [],
    'targets' => count($targetBaseUrls),
    'queued_leads' => is_dir(creditsoft_bridge_outbox_dir()) ? count(glob(creditsoft_bridge_outbox_dir() . '/*.json') ?: []) : 0,
    'message' => $targetBaseUrls !== []
        ? 'Website bridge is configured and ready to forward API requests.'
        : 'Website bridge is installed, but no office API target is configured yet.',
    'meta_callback' => '/oauth.php',
    'meta_api_callback' => '/api/v1/meta/callback',
    'meta_deauthorize' => '/deauthorize.php',
    'meta_data_deletion' => '/data-deletion.php',
]);
