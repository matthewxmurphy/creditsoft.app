<?php
/**
 * Template Name: CreditSoft Elementor Home
 * Template Post Type: page
 * Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
 * Product/support https://builtnotbegged.com; recorded policy identity Matthew X Murphy.
 * SPDX-License-Identifier: GPL-2.0-or-later. Child 2026.10.1 UNRELEASED.
 * Helium header/footer buffer into Gantry's page-plugin Twig document; do not start another one.
 */
defined('ABSPATH') || exit;
get_header();
while (have_posts()) {
    the_post();
    echo '<div class="creditsoft-page-content">';
    the_content();
    echo '</div>';
}
get_footer();
