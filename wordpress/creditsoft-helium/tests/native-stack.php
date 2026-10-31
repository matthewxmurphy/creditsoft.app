<?php
/**
 * New Gantry/Elementor compatibility checks; no replay of the calculator engine suite.
 * Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
 * Product/support: https://builtnotbegged.com. Recorded identity: Matthew X Murphy.
 * SPDX-License-Identifier: GPL-2.0-or-later
 * Use WP-CLI only in the explicitly owned disposable stack fixture.
 */
if (!defined('WP_CLI') || !WP_CLI || !defined('CREDITSOFT_STACK_TEST_SITE')
    || CREDITSOFT_STACK_TEST_SITE !== 'task118-stack-disposable'
    || preg_match('/^csstack118_[a-f0-9]{8}$/D', DB_NAME) !== 1
    || wp_parse_url(home_url(), PHP_URL_HOST) !== '127.0.0.1') throw new RuntimeException('Use the dedicated isolated stack fixture.');
$origin = getenv('CREDITSOFT_STACK_HTTP_ORIGIN');
$existing = (int) getenv('CREDITSOFT_STACK_EXISTING_FRONT');
if (!is_string($origin) || preg_match('~^http://csstack118-[a-f0-9]{8}-wp$~D', $origin) !== 1 || $existing < 1) throw new RuntimeException('Missing owned stack inputs.');
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$checks = 0; $requests = 0;
$check = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) throw new RuntimeException($label); };
$http = static function (string $path) use ($origin, &$requests): string {
    ++$requests;
    $r = wp_remote_get($origin . $path, ['redirection' => 0, 'timeout' => 20,
        'headers' => ['Host' => wp_parse_url(home_url(), PHP_URL_HOST) . ':' . wp_parse_url(home_url(), PHP_URL_PORT)]]);
    if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200) throw new RuntimeException('Owned stack page was not HTTP200.');
    return wp_remote_retrieve_body($r);
};
$chrome = static function (string $html) use ($check): void {
    $check(str_contains($html, 'id="g-page-surround"'), 'Actual Gantry theme shell renders the page.');
};
$calculator = static function (string $html, string $slug) use ($check, $chrome): void {
    $chrome($html);
    $check(str_contains($html, 'data-financial-tool="' . $slug . '"'), 'Selected calculator renders inside the new stack.');
    $check(str_contains($html, '/creditsoft-financial-tools/assets/adapter.css') && str_contains($html, '/creditsoft-financial-tools/assets/client.mjs'), 'Real calculator CSS and module are emitted.');
};
try {
    $check(is_plugin_active('gantry5/gantry5.php') && get_plugin_data(WP_PLUGIN_DIR . '/gantry5/gantry5.php')['Version'] === '5.6.4', 'Actual official Gantry5.6.4 active.');
    $check(is_plugin_active('elementor/elementor.php') && ELEMENTOR_VERSION === '4.3.5', 'Actual free Elementor4.3.5 active.');
    $check(get_template() === 'g5_helium' && get_stylesheet() === 'creditsoft-helium', 'Actual Helium parent and CreditSoft child active.');
    $check(wp_get_theme('g5_helium')->get('Version') === '5.6.4', 'Official Helium5.6.4 is the parent.');
    $check(class_exists('Gantry\\Framework\\Gantry'), 'Real Gantry framework loaded.');
    $check((int) get_option('page_on_front') === $existing && get_post($existing)->post_content === 'Existing editor-managed homepage', 'Child activation preserves the existing edited homepage and selection.');
    $initial = $http('/'); $chrome($initial);
    $check(str_contains($initial, 'Existing editor-managed homepage'), 'Existing homepage content still flows through the actual theme.');
    $navigation = json_decode(file_get_contents(get_stylesheet_directory() . '/content/navigation.json'), true, 512, JSON_THROW_ON_ERROR);
    $locations = get_theme_mod('nav_menu_locations', []);
    $addItems = static function (int $menu, array $items, int $parent = 0) use (&$addItems): void {
        foreach ($items as $item) {
            $url = str_starts_with($item['url'], '/') ? home_url($item['url']) : $item['url'];
            $id = wp_update_nav_menu_item($menu, 0, ['menu-item-title' => $item['label'], 'menu-item-url' => $url,
                'menu-item-status' => 'publish', 'menu-item-type' => 'custom', 'menu-item-parent-id' => $parent]);
            if (is_wp_error($id)) throw new RuntimeException('Owned fixture navigation could not be created.');
            if (isset($item['children'])) $addItems($menu, $item['children'], (int) $id);
        }
    };
    foreach ($navigation as $location => $items) {
        $menu = wp_create_nav_menu('Owned stack sample ' . $location);
        if (is_wp_error($menu)) throw new RuntimeException('Owned fixture menu could not be created.');
        $addItems((int) $menu, $items); $locations[$location] = (int) $menu;
    }
    set_theme_mod('nav_menu_locations', $locations);
    foreach (array_keys($navigation) as $location) $check(has_nav_menu($location), 'Source navigation remains editable in native WordPress menus.');
    $data = json_decode(file_get_contents(get_stylesheet_directory() . '/content/homepage.elementor.json'), true, 512, JSON_THROW_ON_ERROR);
    $check(is_array($data['content'] ?? null) && $data['content'] !== [], 'Source supplies ordinary editable Elementor elements.');
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    if (!$admins) throw new RuntimeException('The synthetic core-install admin is required.');
    wp_set_current_user((int) $admins[0]);
    $documents = \Elementor\Plugin::instance()->documents;
    $home = $documents->create('wp-page', ['post_title' => 'CreditSoft homepage', 'post_status' => 'publish', 'post_name' => 'creditsoft-home']);
    if (is_wp_error($home)) throw new RuntimeException('Actual Elementor document creation failed.');
    $settings = array_merge($data['page_settings'] ?? [], ['template' => 'templates/creditsoft-home.php']);
    $check($home->save(['elements' => $data['content'], 'settings' => $settings]), 'Real Elementor document saves source content.');
    $homeId = $home->get_main_id();
    update_post_meta($homeId, '_wp_page_template', 'templates/creditsoft-home.php');
    update_option('show_on_front', 'page'); update_option('page_on_front', $homeId);
    $hero = 'Build, Launch, and Run a Credit Repair Office Without Looking Like Generic SaaS';
    $html = $http('/'); $chrome($html);
    $check(str_contains($html, $hero) && str_contains($html, 'Welcome to CreditSoft'), 'Published Astro hero content renders through the selected page template.');
    $check(str_contains($html, 'elementor-element'), 'Real Elementor frontend renders the document.');
    $edited = $data['content']; $changed = false;
    $edit = static function (array &$elements) use (&$edit, &$changed, $hero): void {
        foreach ($elements as &$element) {
            if (($element['widgetType'] ?? '') === 'heading' && ($element['settings']['title'] ?? '') === $hero) {
                $element['settings']['title'] = 'Temporary local editor check'; $changed = true;
            }
            if (isset($element['elements']) && is_array($element['elements'])) $edit($element['elements']);
        }
    };
    $edit($edited);
    $check($changed && $home->save(['elements' => $edited, 'settings' => $settings]), 'Native Elementor saves an actual heading edit.');
    $check(str_contains($http('/'), 'Temporary local editor check'), 'Actual frontend reflects the saved edit.');
    $check($home->save(['elements' => $data['content'], 'settings' => $settings]), 'Original source content restored through Elementor save.');
    $check(str_contains($http('/'), $hero), 'Restored source heading renders again.');
    foreach (['budget', 'optimizer'] as $slug) $calculator($http('/tools/' . $slug . '/'), $slug);
    $index = $http('/tools/'); $chrome($index);
    $check(str_contains($index, 'Free financial tools'), 'Virtual tools index retains the Gantry shell.');
    $calc = $documents->create('wp-page', ['post_title' => 'Elementor calculator compatibility', 'post_status' => 'publish', 'post_name' => 'elementor-calculator']);
    if (is_wp_error($calc)) throw new RuntimeException('Actual Elementor calculator page creation failed.');
    $elements = [['id' => 'cstwrap', 'elType' => 'container', 'settings' => [], 'elements' => [
        ['id' => 'csttool', 'elType' => 'widget', 'widgetType' => 'shortcode', 'settings' => ['shortcode' => '[creditsoft_tool slug="budget"]'], 'elements' => []]]]];
    $check($calc->save(['elements' => $elements, 'settings' => $settings]), 'Free Elementor shortcode widget saves natively.');
    update_post_meta($calc->get_main_id(), '_wp_page_template', 'templates/creditsoft-home.php');
    $calculator($http('/elementor-calculator/'), 'budget');
    echo 'PASS: ' . $checks . ' new stack checks; ' . $requests . ' HTTP requests; WP ' . get_bloginfo('version') . '; PHP ' . PHP_VERSION . ".\n";
    echo 'BROWSER_PAGES home=' . $homeId . ' elementorCalculator=' . $calc->get_main_id() . "\n";
} catch (Throwable $failure) {
    echo 'FAIL after ' . $checks . ' checks; ' . $requests . " HTTP requests.\n";
    throw $failure;
}
