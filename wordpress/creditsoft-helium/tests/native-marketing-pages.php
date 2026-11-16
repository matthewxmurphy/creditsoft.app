<?php
/**
 * Focused actual WordPress/Elementor checks for the three new static marketing exports.
 * Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
 * Product/support: https://builtnotbegged.com. Recorded identity: Matthew X Murphy.
 * SPDX-License-Identifier: GPL-2.0-or-later
 * No previous homepage/calculator tests, endpoint calls, forms or service accounts.
 */
if (!defined('WP_CLI') || !WP_CLI || !defined('CREDITSOFT_MARKETING_TEST_SITE')
    || CREDITSOFT_MARKETING_TEST_SITE !== 'task118-marketing-disposable'
    || preg_match('/^csmarket118_[a-f0-9]{8}$/D', DB_NAME) !== 1
    || wp_parse_url(home_url(), PHP_URL_HOST) !== '127.0.0.1') throw new RuntimeException('Use only the owned marketing-page fixture.');
$origin = getenv('CREDITSOFT_MARKETING_HTTP_ORIGIN');
$existing = (int) getenv('CREDITSOFT_MARKETING_EXISTING_PAGE');
if (!is_string($origin) || preg_match('~^http://csmarket118-[a-f0-9]{8}-wp$~D', $origin) !== 1 || $existing < 1) throw new RuntimeException('Missing isolated fixture inputs.');
$checks = 0; $requests = 0;
$check = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) throw new RuntimeException($label); };
$http = static function (string $path) use ($origin, &$requests): string {
    ++$requests;
    $r = wp_remote_get($origin . $path, ['redirection' => 0, 'timeout' => 20,
        'headers' => ['Host' => wp_parse_url(home_url(), PHP_URL_HOST) . ':' . wp_parse_url(home_url(), PHP_URL_PORT)]]);
    if (is_wp_error($r) || wp_remote_retrieve_response_code($r) !== 200) throw new RuntimeException('Owned new marketing page was not HTTP200.');
    return wp_remote_retrieve_body($r);
};
$document = static function (string $html): DOMXPath {
    $old = libxml_use_internal_errors(true);
    try { $doc = new DOMDocument(); $doc->loadHTML($html, LIBXML_NONET); return new DOMXPath($doc); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($old); }
};
$text = static fn (string $html): string => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
$headings = [
    'about-us' => 'Built for offices that want a stronger public face and a stronger operating core.',
    'resources' => 'CreditSoft user guide and admin docs',
    'docs' => 'Updates are for installers. People go to the public site.',
];
$prepared = []; $beforeFront = get_option('page_on_front');
$beforeContent = get_post($existing)->post_content;
try {
    if (!function_exists('creditsoft_helium_marketing_template') || !class_exists('Elementor\\Plugin')) throw new RuntimeException('The new export helper and existing Elementor stack are required.');
    foreach ($headings as $slug => $heading) {
        $prepared[$slug] = creditsoft_helium_marketing_template($slug);
        $check(($prepared[$slug]['type'] ?? null) === 'page' && is_array($prepared[$slug]['content'] ?? null), 'New source export is a real editable page document.');
    }
    $check(get_option('page_on_front') === $beforeFront && get_post($existing)->post_content === $beforeContent, 'Preparing new exports does not import, replace or select a page.');
    try { creditsoft_helium_marketing_template('not-a-page'); $unknown = false; }
    catch (InvalidArgumentException) { $unknown = true; }
    $check($unknown, 'Only the three new source pages can be prepared.');

    $beforeUrl = home_url();
    $filter = static fn (string $url, string $path): string => 'http://[2001:db8::118]/~fixture' . ($path === '' ? '' : '/' . ltrim($path, '/'));
    $links = static function (array $elements) use (&$links): array {
        $out = [];
        foreach ($elements as $element) {
            if (is_string($element['settings']['link']['url'] ?? null)) $out[] = $element['settings']['link']['url'];
            if (isset($element['elements']) && is_array($element['elements'])) $out = array_merge($out, $links($element['elements']));
        }
        return $out;
    };
    add_filter('home_url', $filter, 10, 2);
    try {
        $aboutLinks = $links(creditsoft_helium_marketing_template('about-us')['content']);
        $resourceLinks = $links(creditsoft_helium_marketing_template('resources')['content']);
        $check(in_array('http://[2001:db8::118]/~fixture/features', $aboutLinks, true)
            && in_array('http://[2001:db8::118]/~fixture/contact-us', $aboutLinks, true), 'New About calls to action retain the configured WordPress base.');
        $check(in_array('http://[2001:db8::118]/~fixture/docs', $resourceLinks, true), 'New Resources API-doc link retains the configured base.');
        $docs = creditsoft_helium_marketing_template('docs');
        $check(str_contains(wp_json_encode($docs, JSON_UNESCAPED_SLASHES), 'GET or POST https://api.creditsoft.app/license/validate'), 'Docs endpoint remains literal reference text, never a rebased request.');
    } finally { remove_filter('home_url', $filter, 10); }
    $check(home_url() === $beforeUrl, 'Temporary URL-only filter is restored without a network request.');

    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    if (!$admins) throw new RuntimeException('The synthetic core-install admin is required.');
    wp_set_current_user((int) $admins[0]);
    $documents = \Elementor\Plugin::instance()->documents;
    foreach ($headings as $slug => $heading) {
        $data = $prepared[$slug];
        $page = $documents->create('wp-page', ['post_title' => $data['title'], 'post_status' => 'publish', 'post_name' => $slug]);
        if (is_wp_error($page)) throw new RuntimeException('New owned document creation failed.');
        $settings = array_merge($data['page_settings'] ?? [], ['template' => 'templates/creditsoft-home.php']);
        $check($page->save(['elements' => $data['content'], 'settings' => $settings]), 'Explicit import saves the new document through real Elementor.');
        $id = $page->get_main_id(); update_post_meta($id, '_wp_page_template', 'templates/creditsoft-home.php');
        $path = wp_parse_url(get_permalink($id), PHP_URL_PATH);
        $check($path === '/' . $slug . '/', 'New document uses its intended public path.');
        $html = $http($path); $xp = $document($html); $plain = $text($html);
        $check(str_contains($html, 'id="g-page-surround"') && str_contains($html, '/creditsoft-helium/assets/site-shell.css'), 'New page renders through the delivered Gantry shell and local styling.');
        $check($xp->query('//h1[normalize-space(.)="' . $heading . '"]')->length === 1, 'Source page heading is preserved once.');
        if ($slug === 'about-us') {
            foreach ([
                'Most credit repair software looks like generic SaaS with a credit niche pasted on top.',
                'CreditSoft is trying to feel like the office, the website, the portal, the updates lane, and the migration story were all planned together.',
                'That means design matters, but so does the workflow underneath it: local-first operations, migration-aware setup, a branded website, and reporting the office can actually brag about.',
                'The strongest replacement candidate keeps the brand story and the product story aligned.',
            ] as $copy) $check(str_contains($plain, $copy), 'New About source copy remains present.');
        }
        $codes = []; foreach ($xp->query('//code') as $node) $codes[] = trim($node->textContent);
        if ($slug === 'resources') {
            foreach (['user-guide', 'admin-guide', 'crm-guide', 'ai-settings'] as $anchor) $check($xp->query('//*[@id="' . $anchor . '"]')->length === 1, 'Each required Resources anchor exists exactly once.');
            foreach (['POST /api/v1/clients', 'POST /api/v1/clients/{cuid}/documents', 'POST /api/v1/browser-companion/intake',
                'POST /api/v1/cluster-actions/apply', 'POST /api/v1/cluster-db-events/receive', 'GET /api/v1/meta/callback',
                '/resources#/user-guide/introduction'] as $code) $check(in_array($code, $codes, true), 'Resources preserves literal route and legacy guide reference text.');
            foreach (['CRM webhooks live in the intranet API bridge.', 'The intranet already has the AI control surface.',
                'OpenRouter, Ollama Cloud, and OpenCode Zen provider settings used by the intranet assistant.'] as $copy) $check(str_contains($plain, $copy), 'Resources source guidance remains readable without a provider connection.');
        }
        if ($slug === 'docs') {
            foreach (['GET /api/update-feed', 'GET or POST https://api.creditsoft.app/license/validate', 'GET /api/install-ads', 'GET /api/site-data', 'GET /api/leads'] as $code) $check(in_array($code, $codes, true), 'Docs preserves exact endpoint reference text without calling it.');
            $check(str_contains($plain, 'Human visitors should be sent back to www.creditsoft.app.'), 'Docs retains its human-versus-installer boundary.');
        }
        $edited = $data['content']; $changed = false; $temporary = 'Temporary local editor check for ' . $slug;
        $edit = static function (array &$elements) use (&$edit, &$changed, $heading, $temporary): void {
            foreach ($elements as &$element) {
                if (($element['widgetType'] ?? '') === 'heading' && ($element['settings']['title'] ?? '') === $heading) { $element['settings']['title'] = $temporary; $changed = true; }
                if (isset($element['elements']) && is_array($element['elements'])) $edit($element['elements']);
            }
        };
        $edit($edited);
        $check($changed && $page->save(['elements' => $edited, 'settings' => $settings]), 'New page remains editable through native Elementor save.');
        creditsoft_helium_marketing_template($slug);
        $check(str_contains($text($http($path)), $temporary), 'Preparing source again preserves an existing editor change.');
        $check($page->save(['elements' => $data['content'], 'settings' => $settings]), 'Source content is restored explicitly, not automatically.');
        $check(str_contains($text($http($path)), $heading), 'Restored new page renders its original source heading.');
    }
    $check(get_option('page_on_front') === $beforeFront && get_post($existing)->post_content === $beforeContent, 'Explicit new-page imports preserved the existing edited page and frontpage selection.');
    echo 'PASS: ' . $checks . ' new marketing-page checks; ' . $requests . ' actual HTTP requests; WP ' . get_bloginfo('version') . '; PHP ' . PHP_VERSION . ".\n";
} catch (Throwable $failure) {
    echo 'FAIL after ' . $checks . ' checks; ' . $requests . " HTTP requests.\n";
    throw $failure;
}
