<?php
defined('WP_CLI') && WP_CLI || exit;
if (!in_array(untrailingslashit(home_url()), ['https://creditsoft.app', 'https://staging.creditsoft.app'], true) || !function_exists('cph_mobile_navigation')) {
    WP_CLI::error('Select CreditSoft with the shared CPH Mobile Navigation plugin active.');
}
$locations = get_nav_menu_locations();
if (!empty($locations['cph_mobile']) && wp_get_nav_menu_object($locations['cph_mobile'])) {
    WP_CLI::success('Preserved the existing selected mobile menu.');
    return;
}
$existing = wp_get_nav_menu_object('CreditSoft mobile');
$menu = $existing ? (int) $existing->term_id : wp_create_nav_menu('CreditSoft mobile');
if (is_wp_error($menu)) {
    WP_CLI::error($menu->get_error_message());
}
if (!wp_get_nav_menu_items($menu)) {
    $source = json_decode(file_get_contents(get_stylesheet_directory() . '/content/navigation.json'), true, 512, JSON_THROW_ON_ERROR);
    $items = $source['creditsoft-primary'];
    $items[] = ['label' => 'Login', 'url' => '/login/'];
    $items[] = ['label' => 'Checkout', 'url' => '/subscribe'];
    $add = static function (array $items, int $parent = 0) use (&$add, $menu): void {
        foreach ($items as $item) {
            $url = $item['url'];
            if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
                $url = home_url($url);
            }
            $id = wp_update_nav_menu_item($menu, 0, [
                'menu-item-title' => $item['label'],
                'menu-item-url' => $url,
                'menu-item-type' => 'custom',
                'menu-item-parent-id' => $parent,
                'menu-item-status' => 'publish',
            ]);
            if (is_wp_error($id)) {
                WP_CLI::error($id->get_error_message());
            }
            if (!empty($item['children'])) {
                $add($item['children'], $id);
            }
        }
    };
    $add($items);
}
$locations['cph_mobile'] = $menu;
set_theme_mod('nav_menu_locations', $locations);
update_option('blogname', 'CreditSoft');
WP_CLI::success(wp_json_encode(['menu_id' => $menu, 'items' => count(wp_get_nav_menu_items($menu)), 'other_locations_preserved' => true]));
