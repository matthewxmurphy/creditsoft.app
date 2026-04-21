<?php
declare(strict_types=1);

require_once __DIR__ . '/site-map-config.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: public, max-age=900');

$baseUrl = creditsoft_site_base_url();

echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /admin/\n";
echo "Disallow: /api/\n";
echo "Disallow: /vendor/\n";
echo "Sitemap: {$baseUrl}/sitemap.xml\n";
