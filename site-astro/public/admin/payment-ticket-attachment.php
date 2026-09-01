<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/zelle-payments.php';

$ticketId = (int) ($_GET['id'] ?? 0);
$providedToken = trim((string) ($_GET['token'] ?? ''));

if ($ticketId <= 0) {
    http_response_code(404);
    exit('Attachment not found.');
}

$pdo = cs_site_admin_db();

if (! $pdo instanceof PDO) {
    http_response_code(503);
    exit('Admin database unavailable.');
}

cs_site_zelle_ensure_tables($pdo);
$stmt = $pdo->prepare('SELECT attachment_path, attachment_original_name, attachment_mime, attachment_download_token FROM payment_support_tickets WHERE id = ? LIMIT 1');
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (! is_array($ticket) || trim((string) ($ticket['attachment_path'] ?? '')) === '') {
    http_response_code(404);
    exit('Attachment not found.');
}

$storedToken = trim((string) ($ticket['attachment_download_token'] ?? ''));
$tokenAllowed = $providedToken !== '' && $storedToken !== '' && hash_equals($storedToken, $providedToken);

if (! $tokenAllowed && ! cs_site_admin_is_authenticated()) {
    cs_site_admin_require_login();
}

$storedName = basename((string) $ticket['attachment_path']);
$path = cs_site_zelle_payment_ticket_upload_dir() . '/' . $storedName;

if (! is_file($path)) {
    http_response_code(404);
    exit('Attachment not found.');
}

$downloadName = (string) ($ticket['attachment_original_name'] ?? $storedName);
$downloadName = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $downloadName) ?: $storedName;
$mime = trim((string) ($ticket['attachment_mime'] ?? '')) ?: 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $downloadName) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
