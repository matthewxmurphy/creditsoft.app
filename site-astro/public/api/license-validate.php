<?php
declare(strict_types=1);

require_once __DIR__ . '/license-validate-request.php';

// public_html/api on the server; the private config remains outside webroot.
$request = CreditSoftLicenseValidation::prepare(
    __DIR__ . '/license.php',
    dirname(__DIR__, 2) . '/credit_config.php'
);
if ($request === null) { exit; }
$level = CreditSoftLicenseValidation::startLegacyResponseFilter($request['key']);
try {
    // Global include preserves the legacy pricing/helper variable scope.
    require __DIR__ . '/license.php';
} catch (Throwable $error) {
    CreditSoftLicenseValidation::legacyException($level);
    exit;
}
ob_end_flush();
