<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bridge.php';

$publicCallbackUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'creditsoft.app') . ($_SERVER['REQUEST_URI'] ?? '/api/v1/threads/callback.php');

creditsoft_bridge_forward('threads/callback', $publicCallbackUrl);
