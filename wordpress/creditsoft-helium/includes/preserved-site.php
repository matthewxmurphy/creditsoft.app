<?php
/**
 * Preserved public design integration for native WordPress Pages and Gantry 6.
 * Copyright 2026 Matthew C. Murphy. GPL-2.0-or-later.
 * Source fragments supply shared presentation only; requests never import Pages.
 */
defined('ABSPATH') || exit;

function creditsoft_preserved_manifest(): array
{
    static $manifest;
    if (isset($manifest)) {
        return $manifest;
    }
    $file = get_stylesheet_directory() . '/content/preserved/manifest.json';
    $decoded = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;
    $manifest = is_array($decoded) && ($decoded['format'] ?? '') === 'creditsoft-preserved-site-v1'
        ? $decoded : [];
    return $manifest;
}

function creditsoft_preserved_design_enabled(): bool
{
    return (bool) get_option('creditsoft_preserved_design') && creditsoft_preserved_manifest() !== [];
}

/** Resolve only packaged fragments inside this theme's preserved content directory. */
function creditsoft_preserved_fragment(string $relative): string
{
    if ($relative === '' || str_contains($relative, "\0")) {
        return '';
    }
    $root = realpath(get_stylesheet_directory() . '/content/preserved');
    $file = $root ? realpath($root . '/' . $relative) : false;
    if (!$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_readable($file)) {
        return '';
    }
    return creditsoft_preserved_resolve_assets((string) file_get_contents($file));
}

function creditsoft_preserved_resolve_assets(string $html): string
{
    return str_replace('__CREDITSOFT_ASSET_BASE__', esc_url(get_stylesheet_directory_uri() . '/assets/preserved'), $html);
}

function creditsoft_preserved_page(): array
{
    $manifest = creditsoft_preserved_manifest();
    $route = get_post_meta(get_queried_object_id(), '_creditsoft_preserved_route', true);
    foreach ($manifest['pages'] ?? [] as $page) {
        if (is_array($page) && ($page['route'] ?? null) === $route) {
            return $page;
        }
    }
    if (is_404()) {
        foreach ($manifest['pages'] ?? [] as $page) {
            if (($page['route'] ?? null) === '/') {
                return $page;
            }
        }
    }
    return [];
}

add_action('template_redirect', static function (): void {
    if (!creditsoft_preserved_design_enabled() || !is_404() || !in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
        return;
    }
    $path = trim((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');
    $aliases = ['subscribe' => '/checkout/?plan=professional&billing=monthly&payment=zelle', 'customer-dashboard' => '/account/'];
    if (!isset($aliases[$path]) || get_page_by_path($path, OBJECT, 'page')) {
        return;
    }
    $target = $aliases[$path];
    if ($path === 'customer-dashboard' && !empty($_SERVER['QUERY_STRING'])) {
        $target .= '?' . wp_unslash($_SERVER['QUERY_STRING']);
    }
    wp_safe_redirect(home_url($target), 301, 'CreditSoft');
    exit;
}, 0);

// Native Pages remain the source of truth, including the previously imported
// pages whose image URLs still use the old public asset paths.
add_action('wp', static function (): void {
    if (creditsoft_preserved_design_enabled() && is_page() && creditsoft_preserved_page()) {
        remove_filter('the_content', 'wpautop');
        remove_filter('the_content', 'wptexturize');
    }
});
add_filter('the_content', static function (string $html): string {
    if (!creditsoft_preserved_design_enabled() || !is_page() || !creditsoft_preserved_page()) {
        return $html;
    }
    $base = get_stylesheet_directory_uri() . '/assets/preserved';
    $savedBase = get_post_meta(get_queried_object_id(), '_creditsoft_preserved_asset_base', true);
    if (is_string($savedBase) && $savedBase !== '') {
        $html = str_replace($savedBase, $base, $html);
    }
    $assetRoot = realpath(get_stylesheet_directory() . '/assets/preserved');
    return preg_replace_callback('~\\b(src|href|poster)=([\"\\\'])(/[^\"\\\']*)\\2~i', static function (array $match) use ($base, $assetRoot): string {
        $path = wp_parse_url(html_entity_decode($match[3]), PHP_URL_PATH);
        $file = is_string($path) && $assetRoot ? realpath($assetRoot . $path) : false;
        if (!$file || !is_file($file) || !str_starts_with($file, $assetRoot . DIRECTORY_SEPARATOR)) {
            return $match[0];
        }
        return $match[1] . '=' . $match[2] . esc_url($base . $match[3]) . $match[2];
    }, $html) ?? $html;
}, 20);

/** Saved Gantry particle HTML overrides the packaged, captured shared shell. */
function creditsoft_preserved_shell(string $part, string $override = ''): string
{
    if (!in_array($part, ['header', 'footer'], true) || !creditsoft_preserved_design_enabled()) {
        return '';
    }
    $manifest = creditsoft_preserved_manifest();
    $html = trim($override) !== ''
        ? creditsoft_preserved_resolve_assets($override)
        : creditsoft_preserved_fragment((string) ($manifest['shared'][$part . '_file'] ?? ''));
    return apply_filters('creditsoft_preserved_shell_html', $html, $part);
}

add_filter('body_class', static function (array $classes): array {
    if (creditsoft_preserved_design_enabled()) {
        $classes[] = 'creditsoft-preserved-design';
    }
    return $classes;
});

// Native Pages retain their captured titles while remaining editable in WordPress.
add_filter('pre_get_document_title', static function (string $title): string {
    if (!creditsoft_preserved_design_enabled() || !is_page()) {
        return $title;
    }
    return (string) (get_post_meta(get_queried_object_id(), '_creditsoft_preserved_title', true) ?: $title);
}, 20);

add_filter('wp_robots', static function (array $robots): array {
    $route = get_post_meta(get_queried_object_id(), '_creditsoft_preserved_route', true);
    if (in_array($route, ['/account', '/login', '/sign-in', '/checkout', '/renewal'], true)) {
        $robots['noindex'] = true;
        unset($robots['index']);
    }
    return $robots;
});

add_action('wp_enqueue_scripts', static function (): void {
    if (!creditsoft_preserved_design_enabled()) {
        return;
    }
    // Gutenberg's editor remains available; its generic frontend CSS would change the captured design.
    foreach (['wp-block-library', 'wp-block-library-theme', 'global-styles', 'classic-theme-styles', 'creditsoft-shell'] as $handle) {
        wp_dequeue_style($handle);
    }
    wp_dequeue_script('creditsoft-shell');
}, PHP_INT_MAX);

add_action('wp_head', static function (): void {
    if (!creditsoft_preserved_design_enabled()) {
        return;
    }
    $page = creditsoft_preserved_page();
    $description = get_post_meta(get_queried_object_id(), '_creditsoft_preserved_description', true);
    if ($description !== '') {
        printf('<meta name="description" content="%s" />' . "\n", esc_attr($description));
    }
    if (!is_404()) {
        $title = get_post_meta(get_queried_object_id(), '_creditsoft_preserved_title', true) ?: wp_get_document_title();
        $image = get_stylesheet_directory_uri() . '/assets/preserved/og-image.png';
        foreach (['og:site_name' => 'CreditSoft', 'og:type' => 'website', 'og:title' => $title, 'og:description' => $description, 'og:url' => get_permalink(), 'og:image' => $image] as $property => $value) {
            printf('<meta property="%s" content="%s" />' . "\n", esc_attr($property), esc_attr($value));
        }
        printf('<meta name="twitter:card" content="summary_large_image" /><meta name="twitter:image" content="%s" />' . "\n", esc_url($image));
    }
    // These are versioned theme files captured by the migration, never request-supplied HTML.
    echo creditsoft_preserved_fragment((string) ($page['head_file'] ?? ''));
    foreach ($page['styles'] ?? [] as $tag) {
        if (is_string($tag)) {
            echo creditsoft_preserved_resolve_assets($tag) . "\n";
        }
    }
    // Gantry retains its outline/particle wrappers without adding visual layout rules.
    echo '<style id="creditsoft-gantry-preserved-layout">'
        . 'body.creditsoft-preserved-design #g-page-surround,'
        . 'body.creditsoft-preserved-design #g-navigation,'
        . 'body.creditsoft-preserved-design #g-container-main,'
        . 'body.creditsoft-preserved-design #g-mainbar,'
        . 'body.creditsoft-preserved-design #g-footer,'
        . 'body.creditsoft-preserved-design #g-page-surround>.g-container,'
        . 'body.creditsoft-preserved-design .g-grid,'
        . 'body.creditsoft-preserved-design .g-block,'
        . 'body.creditsoft-preserved-design .g-content,'
        . 'body.creditsoft-preserved-design .platform-content,'
        . 'body.creditsoft-preserved-design .plugin-content{display:contents}'
        . '.creditsoft-not-found{max-width:1200px;margin:auto;padding:100px 24px}.creditsoft-not-found h1{font-size:clamp(34px,6vw,64px)}.creditsoft-not-found p{line-height:1.7}.creditsoft-not-found a{display:inline-flex;padding:16px 24px;background:#f1c27a;color:#101114;border-radius:16px;font-weight:700}'
        . '</style>' . "\n";
}, 100);

require_once __DIR__ . '/refinements.php';

add_action('wp_footer', static function (): void {
    if (!creditsoft_preserved_design_enabled()) {
        return;
    }
    $manifest = creditsoft_preserved_manifest();
    $page = creditsoft_preserved_page();
    echo creditsoft_preserved_fragment((string) ($manifest['shared']['body_end_file'] ?? ''));
    echo creditsoft_preserved_fragment((string) ($page['body_end_file'] ?? ''));
    foreach ($page['scripts'] ?? [] as $tag) {
        if (is_string($tag)) {
            echo creditsoft_preserved_resolve_assets($tag) . "\n";
        }
    }
}, 100);
