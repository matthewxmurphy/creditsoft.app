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
