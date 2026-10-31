<?php
/**
 * Focused URL preparation check; reserved IPv6 strings are never contacted.
 * Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
 * Product/support: https://builtnotbegged.com. Recorded identity: Matthew X Murphy.
 * SPDX-License-Identifier: GPL-2.0-or-later
 */
if (!defined('WP_CLI') || !WP_CLI || !defined('CREDITSOFT_STACK_TEST_SITE') || CREDITSOFT_STACK_TEST_SITE !== 'task118-stack-disposable'
    || preg_match('/^csstack118_[a-f0-9]{8}$/D', DB_NAME) !== 1) throw new RuntimeException('Use only the owned stack fixture.');
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) throw new RuntimeException($label); };
$before = home_url(); $page = get_option('page_on_front');
$filter = static function (string $url, string $path): string { return 'http://[2001:db8::118]/~fixture' . ($path === '' ? '' : '/' . ltrim($path, '/')); };
$input = ['content' => [['settings' => ['link' => ['url' => '/pricing-plan?from=wp#details']], 'elements' => [
    ['settings' => ['link' => ['url' => '/login']]], ['settings' => ['link' => ['url' => 'https://example.invalid/external']]],
    ['settings' => ['link' => ['url' => 'mailto:sample@example.invalid']]], ['settings' => ['link' => ['url' => '#section']]],
    ['settings' => ['link' => ['url' => '//example.invalid/asset']]],
]]], 'title' => 'Source remains unchanged'];
$original = $input;
add_filter('home_url', $filter, 10, 2);
try {
    $out = creditsoft_helium_rebase_template_links($input);
    $check($out['content'][0]['settings']['link']['url'] === 'http://[2001:db8::118]/~fixture/pricing-plan?from=wp#details', 'Local path, query and fragment retain the configured WP base.');
    $links = array_column(array_column($out['content'][0]['elements'], 'settings'), 'link');
    $check($links[0]['url'] === 'http://[2001:db8::118]/~fixture/login', 'Nested internal links retain IPv6 and preview path.');
    foreach ([1 => 'https://example.invalid/external', 2 => 'mailto:sample@example.invalid', 3 => '#section', 4 => '//example.invalid/asset'] as $index => $expected) {
        $check($links[$index]['url'] === $expected, 'External, mail and document-local links remain unchanged.');
    }
    $check($input === $original, 'Explicit preparation does not mutate the supplied export.');
    $template = creditsoft_helium_homepage_template();
    $urls = [];
    $collect = static function (array $elements) use (&$collect, &$urls): void {
        foreach ($elements as $element) {
            if (is_string($element['settings']['link']['url'] ?? null)) $urls[] = $element['settings']['link']['url'];
            if (isset($element['elements']) && is_array($element['elements'])) $collect($element['elements']);
        }
    };
    $collect($template['content']);
    $check(count(array_filter($urls, static fn ($url) => str_starts_with($url, 'http://[2001:db8::118]/~fixture/'))) > 0, 'The actual source homepage uses explicit base-aware preparation.');
} finally {
    remove_filter('home_url', $filter, 10);
}
$check(home_url() === $before && get_option('page_on_front') === $page, 'Temporary URL filter is removed and no homepage is imported or selected.');
echo 'PASS: ' . $checks . " focused URL-base checks; no network requests.\n";
