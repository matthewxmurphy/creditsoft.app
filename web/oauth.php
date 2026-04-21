<?php
declare(strict_types=1);

require_once __DIR__ . '/api/v1/_bridge.php';

$publicCallbackUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'creditsoft.app') . '/oauth.php';
$state = trim((string) ($_GET['state'] ?? ''));

if (str_starts_with($state, 'threads_')) {
    creditsoft_bridge_forward('threads/callback', $publicCallbackUrl);
}

creditsoft_bridge_forward('meta/callback', $publicCallbackUrl);
