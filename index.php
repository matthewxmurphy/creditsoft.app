<?php
/**
 * CreditSoft - Main Entry Point
 * Shows installer if not installed, otherwise shows landing page
 */

$installerPath = __DIR__ . '/installer';
$installedFile = __DIR__ . '/writable/.installed';

if (is_dir($installerPath) && !file_exists($installedFile)) {
    // Show installer at root
    require __DIR__ . '/installer/index.php';
    exit;
}

// Show landing page (app.php contains the landing page HTML)
require __DIR__ . '/app.php';
