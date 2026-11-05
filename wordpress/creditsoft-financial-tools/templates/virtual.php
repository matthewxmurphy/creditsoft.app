<?php
/**
 * Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
 * Product/support: https://builtnotbegged.com. Recorded policy identity: Matthew X Murphy.
 * SPDX-License-Identifier: GPL-2.0-or-later. Adapter 2026.10.1 UNRELEASED.
 * Existing theme chrome is retained; no Gantry or Elementor runtime is bundled.
 */
defined('ABSPATH') || exit;
$content = CreditSoft_Financial_Tools::virtualContent();
$content .= apply_filters('creditsoft_financial_tools_virtual_after_content', '');
if (!wp_is_block_theme()) {
    get_header();
    echo '<main id="primary">' . $content . '</main>';
    get_footer();
    return;
}
// Block themes print the import map in wp_head; their parts must register dependencies first.
ob_start();
block_template_part('header');
$header = (string) ob_get_clean();
ob_start();
block_template_part('footer');
$footer = (string) ob_get_clean();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<?php echo $header; ?>
<main id="primary"><?php echo $content; ?></main>
<?php echo $footer; ?>
<?php wp_footer(); ?></body></html>
