<?php
defined('WP_CLI') && WP_CLI || exit;
if (untrailingslashit(home_url()) !== 'https://creditsoft.app' || get_stylesheet() !== 'creditsoft-helium') {
    WP_CLI::error('Select the live CreditSoft WordPress conversion.');
}
$updated = [];
foreach (['home', 'account', 'roadmap'] as $slug) {
    $page = get_page_by_path($slug, OBJECT, 'page');
    if (!$page) {
        WP_CLI::error('Missing converted page ' . $slug);
    }
    $content = str_replace('Astro', 'WordPress', $page->post_content);
    if ($content !== $page->post_content) {
        wp_save_post_revision($page->ID);
        $result = wp_update_post(wp_slash(['ID' => $page->ID, 'post_content' => $content]), true);
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        $updated[] = $page->ID;
    }
}
WP_CLI::success(wp_json_encode(['revisioned_page_updates' => $updated]));
