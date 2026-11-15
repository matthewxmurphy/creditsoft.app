<?php
/** CreditSoft presentation refinements. Copyright 2026 Matthew C. Murphy. GPL-2.0-or-later. */
defined('ABSPATH') || exit;

/** Fallback only: a saved native cph_mobile menu always owns the drawer links. */
function creditsoft_mobile_navigation_fallback(): array
{
    $file = get_stylesheet_directory() . '/content/navigation.json';
    $navigation = is_readable($file) ? json_decode((string) file_get_contents($file), true) : [];
    $items = is_array($navigation['creditsoft-primary'] ?? null) ? $navigation['creditsoft-primary'] : [];
    $items[] = ['label' => 'Login', 'url' => '/login/'];
    $items[] = ['label' => 'Checkout', 'url' => '/subscribe'];
    $rebase = static function (array $rows) use (&$rebase): array {
        foreach ($rows as &$row) {
            $url = (string) ($row['url'] ?? '');
            if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
                $row['url'] = home_url($url);
            }
            if (!empty($row['children']) && is_array($row['children'])) {
                $row['children'] = $rebase($row['children']);
            }
        }
        return $rows;
    };
    return $rebase($items);
}

add_filter('creditsoft_preserved_shell_html', static function (string $html, string $part): string {
    if ($part !== 'header' || !function_exists('cph_mobile_navigation') || !class_exists('WP_HTML_Tag_Processor')) {
        return $html;
    }
    $id = 'creditsoft-mobile-navigation';
    $drawer = cph_mobile_navigation(creditsoft_mobile_navigation_fallback(), $id);
    if ($drawer === '') {
        return $html;
    }
    $tags = new WP_HTML_Tag_Processor($html);
    $found = false;
    while ($tags->next_tag(['tag_name' => 'BUTTON'])) {
        if (!$tags->has_class('mobile-menu-toggle')) {
            continue;
        }
        // The preserved dropdown listener must not share the CPH drawer trigger.
        $tags->remove_attribute('data-mobile-menu-toggle');
        $tags->set_attribute('data-cph-menu-open', $id);
        $tags->set_attribute('aria-controls', $id);
        $tags->set_attribute('aria-label', 'Open CreditSoft navigation');
        $tags->set_attribute('title', 'Menu');
        $found = true;
        break;
    }
    if (!$found) {
        return $html;
    }
    $logo = '<a class="creditsoft-drawer-brand" href="' . esc_url(home_url('/')) . '" aria-label="CreditSoft home">'
        . '<img src="' . esc_url(get_stylesheet_directory_uri() . '/assets/preserved/brand/creditsoft-wordmark.png') . '" alt="CreditSoft.app" width="1073" height="282" decoding="async"></a>';
    $drawer = preg_replace_callback('~(<div class="cph-menu-top">)<span>.*?</span>~s',
        static fn(array $match): string => $match[1] . $logo, $drawer, 1) ?? $drawer;
    return $tags->get_updated_html() . $drawer;
}, 10, 2);

// Print this stylesheet after each captured page's source styles. All behavior
// stays with the existing consent/chat handlers and the unchanged CPH plugin.
add_action('wp_head', static function (): void {
    if (!creditsoft_preserved_design_enabled()) {
        return;
    }
    $base = get_stylesheet_directory_uri() . '/assets/';
    $root = get_stylesheet_directory() . '/assets/';
    $css = substr(hash_file('sha256', $root . 'creditsoft-refinements.css'), 0, 12);
    $js = substr(hash_file('sha256', $root . 'creditsoft-refinements.js'), 0, 12);
    wp_enqueue_style('creditsoft-refinements', $base . 'creditsoft-refinements.css', [], $css);
    wp_print_styles('creditsoft-refinements');
    wp_enqueue_script('creditsoft-refinements', $base . 'creditsoft-refinements.js', [], $js, ['in_footer' => true, 'strategy' => 'defer']);
}, 120);
