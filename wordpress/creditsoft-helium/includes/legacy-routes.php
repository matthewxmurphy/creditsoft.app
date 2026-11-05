<?php
/**
 * Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
 * Product/support https://builtnotbegged.com; recorded policy identity Matthew X Murphy.
 * SPDX-License-Identifier: GPL-2.0-or-later.
 */
defined('ABSPATH') || exit;

/** Preserve the single Astro About alias after the operator publishes its replacement. */
function creditsoft_helium_redirect_legacy_about(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, ['GET', 'HEAD'], true) || !is_404() || is_preview()) {
        return;
    }

    $path = wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $alias = wp_parse_url(home_url('/about'), PHP_URL_PATH);
    if (!is_string($alias) || ($path !== $alias && $path !== $alias . '/')) {
        return;
    }

    // This exact alias has one intended target. Leave it missing instead of letting core guess another page.
    add_filter('do_redirect_guess_404_permalink', '__return_false');

    // Draft/private Pages reserve the legacy slug even when the visitor sees a 404.
    if (get_page_by_path('about', OBJECT, ['page'])) {
        return;
    }
    $target = get_page_by_path('about-us', OBJECT, ['page']);
    if (!$target || $target->post_status !== 'publish' || $target->post_password !== '') {
        return;
    }

    $destination = get_permalink($target);
    // Require the intended same-site path, including the preview base, even if a plugin filters permalinks.
    if (!$destination || untrailingslashit($destination) !== untrailingslashit(home_url('/about-us'))) {
        return;
    }
    // An origin-relative Location preserves the preview's IPv6 authority without a safe-redirect host fallback.
    $location = wp_parse_url($destination, PHP_URL_PATH);
    if (is_string($location) && str_starts_with($location, '/') && !str_starts_with($location, '//')
        && wp_safe_redirect($location, 301, 'CreditSoft')) {
        exit;
    }
}

// Decide this exact alias before WordPress tries to guess a similar slug on a 404.
add_action('template_redirect', 'creditsoft_helium_redirect_legacy_about', 1);

/** Preserve the old static homepage address, including campaign parameters. */
function creditsoft_helium_redirect_legacy_home(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, ['GET', 'HEAD'], true) || !is_404() || is_preview() || get_query_var('preview') !== '') {
        return;
    }

    $request = explode('?', wp_unslash($_SERVER['REQUEST_URI'] ?? ''), 2);
    $alias = wp_parse_url(home_url('/index.html'), PHP_URL_PATH);
    if (!is_string($alias) || $request[0] !== $alias) {
        return;
    }

    $location = wp_parse_url(home_url('/'), PHP_URL_PATH);
    if (!is_string($location) || !str_starts_with($location, '/') || str_starts_with($location, '//')) {
        return;
    }
    $query = $request[1] ?? '';
    if ($query !== '') {
        $location .= '?' . $query;
    }
    if (wp_safe_redirect($location, 301, 'CreditSoft')) {
        exit;
    }
}

add_action('template_redirect', 'creditsoft_helium_redirect_legacy_home', 1);

/** Keep unresolved CreditSoft customer sign-in paths separate from staff access. */
function creditsoft_helium_preserve_missing_customer_sign_in(): void
{
    if (!is_404()) {
        return;
    }

    $path = explode('?', wp_unslash($_SERVER['REQUEST_URI'] ?? ''), 2)[0];
    foreach (['/login', '/sign-in'] as $slug) {
        $customer_path = wp_parse_url(home_url($slug), PHP_URL_PATH);
        if (is_string($customer_path) && ($path === $customer_path || $path === $customer_path . '/')) {
            remove_action('template_redirect', 'redirect_canonical');
            remove_action('template_redirect', 'wp_redirect_admin_locations', 1000);
            return;
        }
    }
}

add_action('template_redirect', 'creditsoft_helium_preserve_missing_customer_sign_in', 1);

/** Preserve the legacy sitemap address when WordPress is installed below the host root. */
function creditsoft_helium_redirect_legacy_sitemap(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!is_404() || !in_array($method, ['GET', 'HEAD'], true) || is_preview() || get_query_var('preview') !== '') {
        return;
    }

    $home = trailingslashit(home_url('/'));
    $base = wp_parse_url($home, PHP_URL_PATH);
    $path = explode('?', wp_unslash($_SERVER['REQUEST_URI'] ?? ''), 2)[0];
    if (!is_string($base) || $base === '/' || $path !== $base . 'sitemap.xml'
        || !wp_sitemaps_get_server()->sitemaps_enabled()) {
        return;
    }

    $index = get_sitemap_url('index');
    if (!is_string($index) || !str_starts_with($index, $home)) {
        return;
    }

    // A relative Location keeps the IPv6 authority from the original request.
    $location = wp_make_link_relative($index);
    if ($location === $base . 'wp-sitemap.xml'
        && str_starts_with($location, '/') && !str_starts_with($location, '//')
        && wp_sanitize_redirect($location) === $location && wp_safe_redirect($location, 301, 'CreditSoft')) {
        exit;
    }
}

add_action('template_redirect', 'creditsoft_helium_redirect_legacy_sitemap', 99);

/** Keep the published icon URLs available to clients that request them directly. */
function creditsoft_helium_redirect_legacy_icon(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, ['GET', 'HEAD'], true) || is_preview() || get_query_var('preview') !== '') {
        return;
    }

    $home = wp_parse_url(home_url('/'));
    if (!is_array($home) || !isset($home['scheme'], $home['host'])) {
        return;
    }
    $base = trailingslashit($home['path'] ?? '/');
    $path = explode('?', wp_unslash($_SERVER['REQUEST_URI'] ?? ''), 2)[0];
    if (!str_starts_with($path, $base)) {
        return;
    }
    $icon = substr($path, strlen($base));
    if (!in_array($icon, ['favicon.svg', 'favicon.ico', 'apple-touch-icon.png'], true)) {
        return;
    }

    $is_ico = $icon === 'favicon.ico';
    if ((!is_404() && !($is_ico && is_favicon())) || ($is_ico && (has_site_icon() || get_site_icon_url(32)))) {
        return;
    }
    if (!is_file(get_stylesheet_directory() . '/assets/' . $icon)) {
        return;
    }

    $asset = wp_parse_url(get_stylesheet_directory_uri() . '/assets/' . $icon);
    $scheme = strtolower($home['scheme']);
    $default_port = $scheme === 'https' ? 443 : 80;
    if (!in_array($scheme, ['http', 'https'], true) || !is_array($asset)
        || strtolower($asset['scheme'] ?? '') !== $scheme
        || strcasecmp($asset['host'] ?? '', $home['host']) !== 0
        || ($asset['port'] ?? $default_port) !== ($home['port'] ?? $default_port)
        || isset($asset['user']) || isset($asset['pass']) || isset($asset['query']) || isset($asset['fragment'])) {
        return;
    }
    $location = $asset['path'] ?? '';
    if ($location === $path || !str_starts_with($location, '/') || str_starts_with($location, '//')
        || wp_sanitize_redirect($location) !== $location) {
        return;
    }
    if (wp_safe_redirect($location, 302, 'CreditSoft')) {
        exit;
    }
}

add_action('template_redirect', 'creditsoft_helium_redirect_legacy_icon', 1);
