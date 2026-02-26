<?php

define('CI_ENVIRONMENT', 'development');

$installerPath = __DIR__ . '/installer';
$installedFile = __DIR__ . '/writable/.installed';

if (is_dir($installerPath) && !file_exists($installedFile)) {
    require __DIR__ . '/installer/index.php';
    exit;
}

require __DIR__ . '/app.php';
