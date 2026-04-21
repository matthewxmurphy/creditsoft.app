<?php
declare(strict_types=1);

require_once __DIR__ . '/admin/bootstrap.php';
require_once __DIR__ . '/admin/zelle-payments.php';

$token = (string) ($_GET['t'] ?? '');
cs_site_zelle_record_email_open($token);

header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

echo base64_decode('R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==');
