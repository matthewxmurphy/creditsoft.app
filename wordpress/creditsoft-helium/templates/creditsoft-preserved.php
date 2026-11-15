<?php
/**
 * Template Name: CreditSoft Preserved Design
 * Template Post Type: page
 * Copyright 2026 Matthew C. Murphy. GPL-2.0-or-later.
 * WordPress owns this content; Gantry renders the shared header, footer and outline.
 */
defined('ABSPATH') || exit;
get_header();
while (have_posts()) {
    the_post();
    // The pre-existing migration stored main's children; new seeds retain main.
    $hasMain = (bool) preg_match('/<main\\b/i', get_the_content());
    if (!$hasMain) {
        $page = creditsoft_preserved_page();
        $source = creditsoft_preserved_fragment((string) ($page['content_file'] ?? ''));
        // Keep calculator/account page hooks and spacing on pre-existing Pages.
        echo preg_match('/^<main\\b[^>]*>/i', ltrim($source), $match)
            ? $match[0] : '<main id="creditsoft-content">';
    }
    the_content();
    if (!$hasMain) {
        echo '</main>';
    }
}
get_footer();
