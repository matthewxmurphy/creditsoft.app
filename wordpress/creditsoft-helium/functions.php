<?php
/**
 * Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
 * Product/support https://builtnotbegged.com; recorded policy identity Matthew X Murphy.
 * SPDX-License-Identifier: GPL-2.0-or-later. CreditSoft child 2026.10.1 UNRELEASED.
 * Registers presentation only: activation never creates/imports pages, menus or content.
 */
defined('ABSPATH') || exit;
const CREDITSOFT_HELIUM_VERSION = '2026.10.1';
add_action('after_setup_theme', static function (): void {
    register_nav_menus([
        'creditsoft-primary' => 'CreditSoft primary navigation',
        'creditsoft-footer-explore' => 'CreditSoft footer: Explore',
        'creditsoft-footer-office' => 'CreditSoft footer: Office',
        'creditsoft-footer-company' => 'CreditSoft footer: Company',
        'creditsoft-footer-social' => 'CreditSoft footer: Social links',
    ]);
});
add_filter('body_class', static function (array $classes): array {
    $classes[] = 'creditsoft-site';
    return $classes;
});
add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style('creditsoft-shell', get_stylesheet_directory_uri() . '/assets/site-shell.css', [], CREDITSOFT_HELIUM_VERSION);
    wp_enqueue_script('creditsoft-shell', get_stylesheet_directory_uri() . '/assets/site-shell.js', [], CREDITSOFT_HELIUM_VERSION, true);
});
add_action('wp_head', static function (): void {
    if (has_site_icon() || is_customize_preview()) {
        return;
    }

    $gantry_assets = [];
    if (class_exists(\Gantry\Framework\Gantry::class)) {
        $gantry = \Gantry\Framework\Gantry::instance();
        if (isset($gantry['config'])) {
            $gantry_assets = $gantry['config']->get('page.assets', []);
        }
    }

    $asset_base = get_stylesheet_directory_uri() . '/assets/';
    if (empty($gantry_assets['favicon']) && empty($gantry_assets['touchicon'])) {
        printf("<link rel=\"icon\" href=\"%s\" type=\"image/svg+xml\" />\n", esc_url($asset_base . 'favicon.svg'));
        printf("<link rel=\"alternate icon\" href=\"%s\" />\n", esc_url($asset_base . 'favicon.ico'));
    }
    if (empty($gantry_assets['touchicon'])) {
        printf("<link rel=\"apple-touch-icon\" href=\"%s\" />\n", esc_url($asset_base . 'apple-touch-icon.png'));
    }
}, 100);

require_once get_stylesheet_directory() . '/includes/legacy-routes.php';

/** Explicit import preparation only; preserve external/mailto links and each local path/query verbatim. */
function creditsoft_helium_rebase_template_links(array $template): array
{
    $walk = static function (array &$elements) use (&$walk): void {
        foreach ($elements as &$element) {
            if (!is_array($element)) {
                continue;
            }
            $url = $element['settings']['link']['url'] ?? null;
            if (is_string($url) && str_starts_with($url, '/') && !str_starts_with($url, '//')) {
                $element['settings']['link']['url'] = home_url($url);
            }
            if (isset($element['elements']) && is_array($element['elements'])) {
                $walk($element['elements']);
            }
        }
    };
    if (isset($template['content']) && is_array($template['content'])) {
        $walk($template['content']);
    }
    return $template;
}

/** Read-only source export; never imports, selects or rewrites a WordPress page. */
function creditsoft_helium_homepage_template(): array
{
    $path = get_stylesheet_directory() . '/content/homepage.elementor.json';
    if (!is_readable($path)) {
        throw new RuntimeException('CreditSoft homepage source template is unavailable.');
    }
    $template = json_decode((string) file_get_contents($path), true);
    if (!is_array($template)) {
        throw new RuntimeException('CreditSoft homepage source template is invalid.');
    }
    return creditsoft_helium_rebase_template_links($template);
}

/** Prepare one explicitly selected static marketing export; never create or replace a page. */
function creditsoft_helium_marketing_template(string $slug): array
{
    $files = [
        'about-us' => 'about-us.elementor.json',
        'resources' => 'resources.elementor.json',
        'docs' => 'docs.elementor.json',
        'multi-office-nodes' => 'multi-office-nodes.elementor.json',
        'cookies' => 'cookies.elementor.json',
        'privacy' => 'privacy.elementor.json',
        'terms' => 'terms.elementor.json',
        'releases' => 'releases.elementor.json',
    ];
    if (!isset($files[$slug])) {
        throw new InvalidArgumentException('CreditSoft marketing template is not supported.');
    }
    $path = get_stylesheet_directory() . '/content/' . $files[$slug];
    if (!is_readable($path)) {
        throw new RuntimeException('CreditSoft marketing source template is unavailable.');
    }
    $template = json_decode((string) file_get_contents($path), true);
    if (!is_array($template)) {
        throw new RuntimeException('CreditSoft marketing source template is invalid.');
    }
    if ($slug === 'multi-office-nodes') {
        // Resolve this one published diagram locally; import preparation never fetches a URL.
        $prepareImage = static function (array &$elements) use (&$prepareImage): void {
            foreach ($elements as &$element) {
                if (!is_array($element)) {
                    continue;
                }
                if (($element['widgetType'] ?? null) === 'image'
                    && ($element['settings']['image']['url'] ?? null) === '/assets/images/product-proof/multi-office-node-topology.png') {
                    $element['settings']['image']['url'] = get_stylesheet_directory_uri() . '/assets/multi-office-node-topology.png';
                }
                if (isset($element['elements']) && is_array($element['elements'])) {
                    $prepareImage($element['elements']);
                }
            }
        };
        $prepareImage($template['content']);
    }
    return creditsoft_helium_rebase_template_links($template);
}

require_once get_stylesheet_directory() . '/includes/contact-capture.php';
