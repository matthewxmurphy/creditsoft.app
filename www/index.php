<?php

define('CI_ENVIRONMENT', 'development');

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

$installerPath = __DIR__ . '/installer';
$installedFile = __DIR__ . '/writable/.installed';

if (is_dir($installerPath) && !file_exists($installedFile)) {
    require __DIR__ . '/installer/index.php';
    exit;
}

require __DIR__ . '/app.php';
