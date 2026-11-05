<?php
/**
 * CreditSoft Financial Tools: actual WordPress acceptance, not a WordPress stub.
 * Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
 * Product/support: https://builtnotbegged.com. Recorded policy: Matthew X Murphy.
 * SPDX-License-Identifier: GPL-2.0-or-later
 * Adapter 2026.10.1, unreleased. Run with WP-CLI eval-file in a disposable site.
 * The fixture supplies CREDITSOFT_TEST_SITE, an isolated DB name and HTTP origin;
 * it captures the post IDs/rewrite rules before activating the plugin in
 * CREDITSOFT_NATIVE_BASELINE. No real site, credentials or provider is needed.
 */
if (!defined('WP_CLI') || !WP_CLI || !defined('CREDITSOFT_TEST_SITE') || CREDITSOFT_TEST_SITE !== 'task118-disposable'
    || !defined('DB_NAME') || preg_match('/^csft118_[a-f0-9]{8}$/D', DB_NAME) !== 1
    || wp_parse_url(home_url(), PHP_URL_HOST) !== '127.0.0.1') {
    throw new RuntimeException('Use only the dedicated disposable WordPress fixture.');
}
$origin = getenv('CREDITSOFT_TEST_HTTP_ORIGIN');
$baseline = json_decode((string) getenv('CREDITSOFT_NATIVE_BASELINE'), true);
if (!is_string($origin) || preg_match('~^http://csft118-[a-f0-9]{8}-wp$~D', $origin) !== 1 || !is_array($baseline)) {
    throw new RuntimeException('Missing isolated fixture inputs.');
}
$checks = 0;
$requests = 0;
$created = [];
$check = static function (bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) throw new RuntimeException($label);
};
$postIds = static function (): array {
    global $wpdb;
    return array_map('intval', $wpdb->get_col("SELECT ID FROM {$wpdb->posts} ORDER BY ID"));
};
$request = static function (string $path, string $method = 'GET') use ($origin, &$requests): array {
    $host = wp_parse_url(home_url(), PHP_URL_HOST) . ':' . wp_parse_url(home_url(), PHP_URL_PORT);
    $response = wp_remote_request($origin . $path, ['method' => $method, 'redirection' => 0, 'timeout' => 15, 'headers' => ['Host' => $host]]);
    ++$requests;
    if (is_wp_error($response)) throw new RuntimeException('Owned fixture HTTP request failed.');
    return [(int) wp_remote_retrieve_response_code($response), wp_remote_retrieve_body($response), wp_remote_retrieve_header($response, 'location')];
};
$document = static function (string $html): DOMXPath {
    $previous = libxml_use_internal_errors(true);
    try { $doc = new DOMDocument(); $doc->loadHTML($html, LIBXML_NONET); return new DOMXPath($doc); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
};
$hasAssets = static function (DOMXPath $xp) use ($check): void {
    $check($xp->query('//link[contains(@href,"/creditsoft-financial-tools/assets/adapter.css")]')->length === 1, 'Actual response includes the calculator stylesheet once.');
    $check($xp->query('//script[@type="module" and contains(@src,"/creditsoft-financial-tools/assets/client.mjs")]')->length === 1, 'Actual response includes the executable module once.');
};
$makePage = static function (string $slug, string $content, int $parent = 0, string $status = 'publish') use (&$created): int {
    $id = wp_insert_post(['post_type' => 'page', 'post_status' => $status, 'post_title' => 'CreditSoft test ' . $slug,
        'post_name' => $slug, 'post_content' => $content, 'post_parent' => $parent], true);
    if (is_wp_error($id)) throw new RuntimeException('Could not create a synthetic test page.');
    $created[] = (int) $id;
    return (int) $id;
};
try {
    $check(class_exists('CreditSoft_Financial_Tools') && shortcode_exists('creditsoft_tool'), 'Real plugin activated and shortcode registered.');
    $block = WP_Block_Type_Registry::get_instance()->get_registered('creditsoft/financial-tool');
    $check($block !== null && is_callable($block->render_callback), 'Real dynamic block registered.');
    $check($postIds() === $baseline['posts'], 'Activation created no persistent pages.');
    $check(get_option('rewrite_rules') === $baseline['rewrite'], 'Activation changed no rewrite rules.');
    $slugs = ['budget','payoff','utilization','prep','strategy','simulator','optimizer','readiness','negative','comparison','timeline','recommendation'];
    $catalog = CreditSoft_Financial_Tools::catalog();
    $check(array_column($catalog['tools'], 'slug') === $slugs, 'All twelve original slugs retained.');
    [$status,$html] = $request('/tools/');
    $xp = $document($html);
    $check($status === 200 && $xp->query('//a[contains(@class,"tool-card")]')->length === 12, 'Virtual index exposes all twelve tools.');
    foreach ($catalog['tools'] as $tool) {
        [$status,$html] = $request('/tools/' . $tool['slug'] . '/');
        $xp = $document($html);
        $check($status === 200 && $xp->query('//*[@data-financial-tool="' . $tool['slug'] . '"]')->length === 1, 'Virtual calculator has its exact selector.');
        $check($xp->query('//form[contains(@class,"tool-inputs") and @method="dialog"]')->length === 1, 'One local-only calculator form per virtual route.');
        $check(str_contains($html, esc_html($tool['method'])) && str_contains($html, 'Credit Sense Credit Repair'), 'Assumptions and normal attribution remain present.');
        $hasAssets($xp);
        foreach ($tool['fields'] as $field) {
            if ($field['type'] === 'checkboxes') {
                $check($xp->query('//input[@type="checkbox" and @name="' . $field['name'] . '"]')->length === count($field['options']), 'Checkbox group keeps its actual field name and choices.');
            } else {
                $node = $xp->query('//*[@name="' . $field['name'] . '"]')->item(0);
                $check($node !== null && $xp->query('//label[@for="' . $node->getAttribute('id') . '"]')->length === 1, 'Actual field and associated label exist.');
                if ($field['type'] === 'number') foreach (['value','min','max','step'] as $attribute) $check($node->getAttribute($attribute) === (string) $field[$attribute], 'Original numeric default or constraint preserved.');
                if ($field['type'] === 'textarea') $check($node->getAttribute('maxlength') === '4000', 'Worksheet text remains bounded.');
            }
        }
        if (isset($tool['rows'])) $check($xp->query('//*[@data-tool-row]')->length === 3 && $xp->query('//*[@data-tool-rows]/*[@data-tool-row]')->length === 2, 'Two initial rows and one inert template are present.');
        $check(!str_contains($html, 'googletagmanager') && !str_contains($html, 'fbevents'), 'No calculator tracker is added.');
    }
    [$status,$html] = $request('/tools/not-a-tool/');
    $check($status === 404 && !str_contains($html, 'data-financial-tool='), 'Unknown route is not a calculator.');
    [$status,$body] = $request('/tools/not-a-tool/', 'HEAD');
    $check($status === 404 && $body === '', 'Unknown HEAD retains its bodyless 404.');
    [$status,$body] = $request('/tools/budget/', 'HEAD');
    $check($status === 200 && $body === '', 'HEAD preserves the route without a response body.');
    [$status,$body] = $request('/tools/budget/', 'POST');
    $check($status === 404 && !str_contains($body, 'data-financial-tool='), 'POST cannot invoke a virtual calculator.');
    $privateTools = $makePage('tools', 'PRIVATE TOOLS CONTENT', 0, 'private');
    $privateBudget = $makePage('budget', 'PRIVATE BUDGET CONTENT', $privateTools, 'private');
    foreach (['/tools/', '/tools/budget/'] as $path) {
        [$status,$body] = $request($path);$xp = $document($body);
        $check($status === 404 && !str_contains($body, 'data-financial-tool=') && !str_contains($body, 'PRIVATE TOOLS CONTENT')
            && !str_contains($body, 'PRIVATE BUDGET CONTENT') && $xp->query('//nav[contains(@class,"tools-grid")]')->length === 0, 'Anonymous private page ownership is not replaced by a public tool.');
        [$status,$body] = $request($path, 'HEAD');
        $check($status === 404 && $body === '', 'Private page HEAD remains a bodyless 404.');
    }
    wp_delete_post($privateBudget, true);wp_delete_post($privateTools, true);
    $makePage('budget', 'EXISTING ROOT BUDGET PAGE');
    [$status,$html,$location] = $request('/tools/budget/');
    $check($status === 200 && !$location && str_contains($html, 'data-financial-tool="budget"'), 'Core canonical guessing cannot redirect the unowned calculator to root budget.');
    $makePage('csft-shortcode', '[creditsoft_tool slug="budget"]');
    $makePage('csft-block', '<!-- wp:creditsoft/financial-tool {"slug":"utilization"} /-->');
    foreach (['/csft-shortcode/' => 'budget', '/csft-block/' => 'utilization'] as $path => $slug) {
        [$status,$html] = $request($path);$xp = $document($html);
        $check($status === 200 && $xp->query('//*[@data-financial-tool="' . $slug . '"]')->length === 1, 'Actual published content renders its shortcode or block.');
        $hasAssets($xp);
    }
    $makePage('csft-duplicate', '[creditsoft_tool slug="budget"] <!-- wp:creditsoft/financial-tool {"slug":"payoff"} /-->');
    $makePage('csft-duplicate-reverse', '<!-- wp:creditsoft/financial-tool {"slug":"utilization"} /--> [creditsoft_tool slug="budget"]');
    foreach (['/csft-duplicate/','/csft-duplicate-reverse/'] as $path) {
        [$status,$html] = $request($path);$xp = $document($html);
        $check($status === 200 && $xp->query('//form[contains(@class,"tool-inputs")]')->length === 1 && str_contains($html, 'One financial tool can be shown per page.'), 'Mixed duplicates expose one live form and a useful link notice.');
    }
    $makePage('csft-invalid', '[creditsoft_tool slug="not-a-tool"] <!-- wp:creditsoft/financial-tool {"slug":12} /-->');
    [$status,$html] = $request('/csft-invalid/');
    $check($status === 200 && !str_contains($html, 'data-financial-tool=') && substr_count($html, 'Choose a known financial tool.') === 2, 'Invalid shortcode and raw typed block selectors do not become a valid default.');
    $toolsPage = $makePage('tools', 'EXISTING TOOLS INDEX');
    $makePage('budget', 'EXISTING HIERARCHICAL BUDGET', $toolsPage);
    foreach (['/tools/' => 'EXISTING TOOLS INDEX','/tools/budget/' => 'EXISTING HIERARCHICAL BUDGET'] as $path => $marker) {
        [$status,$html] = $request($path);
        $check($status === 200 && str_contains($html, $marker) && !str_contains($html, 'data-financial-tool='), 'Real existing content retains route ownership.');
    }
} catch (Throwable $failure) {
    echo 'FAIL after ' . $checks . ' checks; ' . $requests . " HTTP requests.\n";
    throw $failure;
} finally {
    foreach (array_reverse($created) as $id) wp_delete_post($id, true);
}
$remaining = $postIds();
$check(array_diff($baseline['posts'], $remaining) === [], 'All original fixture posts remain.');
$check(array_intersect($created, $remaining) === [], 'Every explicitly owned synthetic page was removed.');
$extra = array_diff($remaining, $baseline['posts']);
if ($extra !== []) {
    $types = [];
    foreach ($extra as $id) { $post = get_post($id); $types[] = $post->post_type . '/' . $post->post_status; }
    echo 'Additional WordPress rows after actual HTTP: ' . implode(', ', $types) . ".\n";
}
echo 'PASS: ' . $checks . ' WordPress checks; ' . $requests . ' actual HTTP requests; WP ' . get_bloginfo('version') . '; PHP ' . PHP_VERSION . ".\n";
