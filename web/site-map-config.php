<?php
declare(strict_types=1);

function creditsoft_site_base_url(): string
{
    return 'https://www.creditsoft.app';
}

function creditsoft_site_route_overrides(): array
{
    return [
        '' => 'index.php',
    ];
}

function creditsoft_site_sitemap_excluded_basenames(): array
{
    return [
        '404',
        'activate',
        'app',
        'checkout',
        'cro-rules',
        'footer',
        'header',
        'index',
        'lead-intake',
        'login',
        'meta-conversions-api',
        'meta-social-manager',
        'payment-ticket-attachment',
        'pricing-config',
        'shared-footer',
        'shared-nav',
        'site-content-config',
        'site-map-config',
        'site-tracking-config',
        'sitemap',
        'robots',
    ];
}

function creditsoft_site_public_routes(): array
{
    $routes = [];

    foreach (creditsoft_site_route_overrides() as $route => $file) {
        $path = __DIR__ . '/' . $file;

        if (is_file($path)) {
            $routes[$route] = $path;
        }
    }

    foreach (glob(__DIR__ . '/*.php') ?: [] as $path) {
        $basename = basename($path, '.php');

        if (in_array($basename, creditsoft_site_sitemap_excluded_basenames(), true)) {
            continue;
        }

        if (! array_key_exists($basename, $routes)) {
            $routes[$basename] = $path;
        }
    }

    ksort($routes);

    return $routes;
}

function creditsoft_site_sitemap_urls(): array
{
    $baseUrl = creditsoft_site_base_url();
    $routes = creditsoft_site_public_routes();
    $urls = [];

    foreach ($routes as $route => $path) {
        $loc = $route === '' ? $baseUrl . '/' : $baseUrl . '/' . rawurlencode($route);
        $urls[] = [
            'loc' => $loc,
            'lastmod' => gmdate('c', (int) filemtime($path)),
        ];
    }

    usort($urls, static function (array $a, array $b): int {
        return strcmp($a['loc'], $b['loc']);
    });

    return $urls;
}
