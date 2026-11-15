<?php
/** Explicit native WordPress import for the preserved CreditSoft design.
 * Run: wp eval-file wordpress/migration/import-pages.php /absolute/manifest.json
 * Existing pages are never overwritten. Re-running only creates missing pages.
 */
defined('ABSPATH') || exit;

$manifestPath = $args[0] ?? '';
if (!is_readable($manifestPath)) {
    WP_CLI::error('Pass the readable, reviewed preserved-site manifest.');
}
$manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
$sourceRoot = dirname(realpath($manifestPath));
if (($manifest['format'] ?? '') !== 'creditsoft-preserved-site-v1' || empty($manifest['pages'])) {
    WP_CLI::error('Unsupported or empty CreditSoft manifest.');
}
if (get_stylesheet() !== 'creditsoft-helium' || !class_exists('Gantry\\Framework\\Gantry')) {
    WP_CLI::error('Activate the CreditSoft Gantry theme and framework first.');
}
$theme = wp_get_theme(get_template());
if (version_compare($theme->get('Version'), '6.0.0-dev', '<')) {
    WP_CLI::error('This migration requires the Gantry 6 Helium parent.');
}
$assetBase = get_stylesheet_directory_uri() . '/assets/preserved';
$created = [];
$preserved = [];
$adopted = [];
$adoptExisting = in_array('adopt-existing', $args, true);
foreach ($manifest['pages'] as $page) {
    $route = $page['route'];
    $slug = trim($route, '/');
    $slug = $slug === '' ? 'home' : $slug;
    if (!preg_match('~^[a-z0-9/-]+$~D', $slug)) {
        WP_CLI::error('Invalid page path: ' . $route);
    }
    $existing = get_page_by_path($slug, OBJECT, 'page');
    if ($existing) {
        $preserved[$route] = $existing->ID;
        if ($adoptExisting) {
            // Only presentation metadata changes. Native page text, revisions and edits survive.
            foreach ([
                '_wp_page_template' => 'templates/creditsoft-preserved.php',
                '_creditsoft_preserved_route' => $route,
                '_creditsoft_preserved_title' => $page['title'],
                '_creditsoft_preserved_description' => $page['description'] ?? '',
                '_creditsoft_preserved_asset_base' => $assetBase,
                '_creditsoft_preserved_source_sha256' => $page['source_sha256'],
            ] as $key => $value) {
                update_post_meta($existing->ID, $key, $value);
            }
            $adopted[$route] = $existing->ID;
        }
        continue;
    }
    $file = realpath($sourceRoot . '/' . $page['content_file']);
    if (!$file || !str_starts_with($file, $sourceRoot . '/') || !is_readable($file)) {
        WP_CLI::error('Missing content file for ' . $route);
    }
    if (hash_file('sha256', $file) !== $page['content_sha256']) {
        WP_CLI::error('Content checksum changed for ' . $route);
    }
    $html = str_replace('__CREDITSOFT_ASSET_BASE__', $assetBase, file_get_contents($file));
    $parentId = 0;
    if (str_contains($slug, '/')) {
        $parentPath = dirname($slug);
        $parent = get_page_by_path($parentPath, OBJECT, 'page');
        if (!$parent) {
            WP_CLI::error('Import parent before child: ' . $route);
        }
        $parentId = $parent->ID;
    }
    $postId = wp_insert_post(wp_slash([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => $page['heading'] ?: $page['title'],
        'post_name' => basename($slug),
        'post_parent' => $parentId,
        'post_content' => "<!-- wp:html -->\n" . $html . "\n<!-- /wp:html -->",
        'meta_input' => [
            '_wp_page_template' => 'templates/creditsoft-preserved.php',
            '_creditsoft_preserved_route' => $route,
            '_creditsoft_preserved_title' => $page['title'],
            '_creditsoft_preserved_description' => $page['description'] ?? '',
            '_creditsoft_preserved_asset_base' => $assetBase,
            '_creditsoft_preserved_source_sha256' => $page['source_sha256'],
        ],
    ]), true);
    if (is_wp_error($postId)) {
        WP_CLI::error($postId->get_error_message());
    }
    $created[$route] = $postId;
}
$all = $created + $preserved;
if (isset($all['/']) && !get_option('page_on_front')) {
    update_option('show_on_front', 'page');
    update_option('page_on_front', $all['/']);
}
update_option('creditsoft_preserved_design', 1);
update_option('creditsoft_preserved_source', [
    'captured_at' => $manifest['captured_at'] ?? '',
    'manifest_sha256' => hash_file('sha256', $manifestPath),
]);
flush_rewrite_rules(false);
WP_CLI::success(wp_json_encode(['created' => $created, 'existing_preserved' => $preserved, 'adopted_metadata_only' => $adopted]));
