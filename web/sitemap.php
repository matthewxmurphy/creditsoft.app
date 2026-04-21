<?php
declare(strict_types=1);

require_once __DIR__ . '/site-map-config.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=900');

$urls = creditsoft_site_sitemap_urls();

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

foreach ($urls as $url) {
    $loc = htmlspecialchars((string) $url['loc'], ENT_XML1, 'UTF-8');
    $lastmod = htmlspecialchars((string) $url['lastmod'], ENT_XML1, 'UTF-8');

    echo "  <url>\n";
    echo "    <loc>{$loc}</loc>\n";
    echo "    <lastmod>{$lastmod}</lastmod>\n";
    echo "  </url>\n";
}

echo "</urlset>\n";
