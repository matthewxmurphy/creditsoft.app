<?php
/**
 * Plugin Name: CreditSoft Financial Tools
 * Description: Twelve existing local calculators through WordPress routes, a shortcode and a block.
 * Version: 2026.10.1
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Matthew C. Murphy
 * Author URI: https://MatthewxMurphy.com
 * Plugin URI: https://builtnotbegged.com
 * License: GPL-2.0-or-later
 * Update URI: false
 *
 * Copyright 2026 Matthew C. Murphy. New adapter files only; see NOTICE.md for adopted modules.
 * Recorded project policy identity: Matthew X Murphy.
 * SPDX-License-Identifier: GPL-2.0-or-later
 * UNRELEASED source preparation; no deployment or public package release is implied.
 */

defined('ABSPATH') || exit;

final class CreditSoft_Financial_Tools
{
    public const VERSION = '2026.10.1';
    private static bool $rendered = false;
    private static ?string $virtual = null;

    public static function boot(): void
    {
        add_action('init', [self::class, 'register']);
        add_filter('redirect_canonical', [self::class, 'canonicalRedirect']);
        add_filter('exit_on_http_head', [self::class, 'headExit']);
        add_filter('template_include', [self::class, 'virtualTemplate'], 99);
        add_filter('document_title_parts', [self::class, 'documentTitle']);
        add_action('wp_sitemaps_init', [self::class, 'registerSitemap']);
    }

    public static function register(): void
    {
        $stylesheet = 'assets/adapter.css';
        wp_register_style('creditsoft-financial-tools', plugins_url($stylesheet, __FILE__), [], self::assetVersion($stylesheet));
        wp_register_script_module('creditsoft-financial-tools', plugins_url('assets/client.mjs', __FILE__), [], self::VERSION);
        $editorScript = 'assets/editor.js';
        wp_register_script('creditsoft-financial-tools-editor', plugins_url($editorScript, __FILE__),
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor'], self::assetVersion($editorScript), true);
        add_shortcode('creditsoft_tool', [self::class, 'shortcode']);
        register_block_type(__DIR__ . '/blocks/financial-tool', ['render_callback' => [self::class, 'block']]);
    }

    private static function assetVersion(string $asset): string
    {
        // Private ZIP replacements can change the asset while keeping the proposal version.
        $path = __DIR__ . '/' . $asset;
        $hash = is_readable($path) ? hash_file('sha256', $path) : false;
        return $hash === false ? self::VERSION : self::VERSION . '-' . substr($hash, 0, 12);
    }

    public static function registerSitemap(WP_Sitemaps $server): void
    {
        if (!$server->sitemaps_enabled()) {
            return;
        }
        require_once __DIR__ . '/includes/sitemap.php';
        wp_register_sitemap_provider('creditsofttools', new CreditSoft_Financial_Tools_Sitemap());
    }

    /** The catalog is generated from the unchanged public module, never from request data. */
    public static function catalog(): array
    {
        static $catalog;
        if ($catalog === null) {
            $raw = file_get_contents(__DIR__ . '/assets/catalog.json');
            $catalog = $raw === false ? [] : json_decode($raw, true);
        }
        return is_array($catalog) ? $catalog : [];
    }

    private static function find(string $slug): ?array
    {
        foreach (self::catalog()['tools'] ?? [] as $tool) {
            if (($tool['slug'] ?? null) === $slug) {
                return $tool;
            }
        }
        return null;
    }

    public static function shortcode($attributes): string
    {
        $slug = is_array($attributes) ? ($attributes['slug'] ?? null) : null;
        return is_string($slug) ? self::render($slug) : self::invalid();
    }

    public static function block(array $attributes, string $content = '', ?WP_Block $block = null): string
    {
        // Core may replace a malformed declared attribute with its default. Do not advertise another tool.
        $raw = $block?->parsed_block['attrs'] ?? [];
        if (is_array($raw) && array_key_exists('slug', $raw) && !is_string($raw['slug'])) {
            return self::invalid();
        }
        return is_string($attributes['slug'] ?? null) ? self::render($attributes['slug'], $block !== null) : self::invalid();
    }

    private static function invalid(): string
    {
        return '<p class="creditsoft-financial-tools-notice" role="status">Choose a known financial tool.</p>';
    }

    /** One live form per page preserves the published client's first-root contract. */
    public static function render(string $slug, bool $isBlock = false): string
    {
        $tool = self::find($slug);
        if ($tool === null) {
            return self::invalid();
        }
        if (self::$rendered) {
            return '<p class="creditsoft-financial-tools-notice" role="status">One financial tool can be shown per page. <a href="'
                . esc_url(home_url('/tools/' . $slug . '/')) . '">Open this tool on its own page.</a></p>';
        }
        self::$rendered = true;
        wp_enqueue_style('creditsoft-financial-tools');
        wp_enqueue_script_module('creditsoft-financial-tools');
        $classes = 'creditsoft-financial-tools financial-tools';
        $wrapper = $isBlock
            ? get_block_wrapper_attributes(['class' => $classes])
            : 'class="' . esc_attr($classes) . '"';
        ob_start();
        ?>
        <section <?php echo $wrapper; ?> data-financial-tool="<?php echo esc_attr($slug); ?>">
            <header class="tools-intro">
                <a class="tool-back" href="<?php echo esc_url(home_url('/tools/')); ?>">← All financial tools</a>
                <p class="tool-kicker"><?php echo esc_html($tool['category']); ?></p>
                <h1><?php echo esc_html($tool['title']); ?></h1>
                <p class="tool-lede"><?php echo esc_html($tool['description']); ?></p>
                <p class="tool-privacy">Your calculator entries stay in this page. No account is needed to calculate.</p>
                <?php self::attribution(); ?>
            </header>
            <div class="tool-workspace">
                <form class="tool-inputs" method="dialog" autocomplete="off" onsubmit="return false">
                    <h2>Your inputs</h2>
                    <p class="tool-hint">Prefilled amounts are examples. Replace them with the numbers you want to explore.</p>
                    <div class="tool-fields"><?php foreach ($tool['fields'] as $field) { self::field($field); } ?></div>
                    <?php if (isset($tool['rows'])) : $rows = $tool['rows']; ?>
                        <div data-tool-rows>
                            <?php for ($i = 1; $i <= 2; $i++) : ?>
                                <fieldset class="tool-account" data-tool-row>
                                    <legend><?php echo esc_html($rows['label'] . ' ' . $i); ?></legend>
                                    <div class="tool-fields"><?php foreach ($rows['fields'] as $field) { self::field($field, 'row-' . $i); } ?></div>
                                    <button class="tool-remove" type="button" data-remove-row>Remove <?php echo esc_html(strtolower($rows['label'])); ?></button>
                                </fieldset>
                            <?php endfor; ?>
                        </div>
                        <template data-row-template><fieldset class="tool-account" data-tool-row>
                            <legend><?php echo esc_html($rows['label']); ?></legend>
                            <div class="tool-fields"><?php foreach ($rows['fields'] as $field) { self::field($field, 'template'); } ?></div>
                            <button class="tool-remove" type="button" data-remove-row>Remove <?php echo esc_html(strtolower($rows['label'])); ?></button>
                        </fieldset></template>
                        <button class="tool-add" type="button" data-add-row data-row-label="<?php echo esc_attr($rows['label']); ?>" disabled>Add <?php echo esc_html(strtolower($rows['label'])); ?></button>
                        <p class="tool-hint">Up to 20 entries. Example amounts are filled in; replace them with your own.</p>
                    <?php endif; ?>
                    <p class="tool-error" role="alert" data-tool-error hidden></p>
                    <div class="tool-actions">
                        <button class="button primary" type="button" data-calculate disabled><?php echo $tool['category'] === 'Planning worksheets' ? 'Build worksheet' : 'Update result'; ?></button>
                        <button class="button secondary" type="reset">Reset</button>
                    </div>
                    <noscript><p>Enable JavaScript to calculate locally. Your numbers are never submitted by this page.</p></noscript>
                </form>
                <section class="tool-output" aria-label="Your result" aria-live="polite" aria-atomic="true">
                    <p class="tool-kicker">Your result</p><h2 data-result-headline>Ready when you are</h2>
                    <p data-result-summary>Review the example inputs and update the result.</p>
                    <dl class="tool-metrics" data-result-metrics></dl><ul class="tool-result-list" data-result-items></ul>
                    <button class="tool-print" type="button" data-print disabled>Print worksheet</button>
                </section>
            </div>
            <section class="tool-method"><h2>How this tool works</h2><p><?php echo esc_html($tool['method']); ?></p>
                <p>Reference: <a href="<?php echo esc_url($tool['source'][1]); ?>"><?php echo esc_html($tool['source'][0]); ?></a>.</p><?php self::attribution(); ?>
            </section>
            <nav class="tool-related" aria-label="Other financial tools">
                <?php foreach (self::catalog()['tools'] as $other) : if ($other['slug'] === $slug) { continue; } ?>
                    <a href="<?php echo esc_url(home_url('/tools/' . $other['slug'] . '/')); ?>"><?php echo esc_html($other['title']); ?></a>
                <?php endforeach; ?>
            </nav>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private static function attribution(): void
    {
        echo '<p class="tool-attribution">Adapted from the financial planning tools at <a href="'
            . esc_url(self::catalog()['sourceLink']) . '">Credit Sense Credit Repair</a>.</p>';
    }

    private static function field(array $field, string $prefix = 'tool'): void
    {
        $id = $prefix . '-' . $field['name'];
        if ($field['type'] === 'checkboxes') {
            echo '<fieldset class="tool-choices"><legend>' . esc_html($field['label']) . '</legend>';
            foreach ($field['options'] as $option) {
                echo '<label><input type="checkbox" name="' . esc_attr($field['name']) . '" value="' . esc_attr($option)
                    . '"><span>' . esc_html($option) . '</span></label>';
            }
            echo '</fieldset>';
            return;
        }
        echo '<div class="tool-field"><label for="' . esc_attr($id) . '">' . esc_html($field['label']) . '</label>';
        if ($field['type'] === 'select') {
            echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($field['name']) . '">';
            foreach ($field['options'] as [$value, $label]) {
                echo '<option value="' . esc_attr($value) . '">' . esc_html($label) . '</option>';
            }
            echo '</select>';
        } elseif ($field['type'] === 'textarea') {
            echo '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($field['name'])
                . '" rows="3" maxlength="4000" autocomplete="off" placeholder="Write your notes here"></textarea>';
        } else {
            echo '<input id="' . esc_attr($id) . '" name="' . esc_attr($field['name']) . '" type="number"';
            foreach (['value', 'min', 'max', 'step'] as $attribute) {
                echo ' ' . $attribute . '="' . esc_attr((string) $field[$attribute]) . '"';
            }
            echo ' required inputmode="decimal" autocomplete="off">';
        }
        echo '</div>';
    }

    public static function index(): string
    {
        wp_enqueue_style('creditsoft-financial-tools');
        ob_start(); ?>
        <section class="creditsoft-financial-tools financial-tools">
            <header class="tools-intro"><h1>Free financial tools</h1>
                <p class="tool-lede">Twelve calculators and planning worksheets. No account required.</p>
                <p class="tool-privacy">Calculator entries stay in your browser.</p>
                <?php self::attribution(); ?>
            </header>
            <nav aria-label="Financial tools" class="tools-grid">
                <?php foreach (self::catalog()['tools'] as $tool) : ?>
                    <a class="tool-card" href="<?php echo esc_url(home_url('/tools/' . $tool['slug'] . '/')); ?>">
                        <span class="tool-kicker"><?php echo esc_html($tool['category']); ?></span>
                        <h2><?php echo esc_html($tool['title']); ?></h2><p><?php echo esc_html($tool['description']); ?></p><span>Open tool →</span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <p class="tool-note">Calculations use the assumptions shown on each page. Credit planning worksheets do not predict a credit score, loan approval or dispute outcome.</p>
        </section>
        <?php return (string) ob_get_clean();
    }

    /** Match only an otherwise-unowned request; selection has no rewrite or query side effect. */
    private static function eligibleVirtualSlug(): ?string
    {
        if (!is_404() || is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)
            || !in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
            return null;
        }
        $path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $base = wp_parse_url(home_url('/'), PHP_URL_PATH);
        if (!is_string($path) || !is_string($base) || !str_starts_with($path, $base)) {
            return null;
        }
        $relative = substr($path, strlen($base));
        if ($relative === 'tools' || $relative === 'tools/') {
            $slug = '';
        } elseif (preg_match('~^tools/([a-z-]+)/?$~D', $relative, $match) === 1 && self::find($match[1]) !== null) {
            $slug = $match[1];
        } else {
            return null;
        }
        return self::hasReservedPage($slug) ? null : $slug;
    }

    private static function hasReservedPage(string $slug): bool
    {
        // A non-public page still owns its exact URL even when an anonymous query is a 404.
        $pagePath = $slug === '' ? 'tools' : 'tools/' . $slug;
        return get_page_by_path($pagePath, OBJECT, ['page']) !== null;
    }

    private static function virtualPath(string $slug): string
    {
        return $slug === '' ? '/tools/' : '/tools/' . $slug . '/';
    }

    /** List only the tool URLs this adapter can serve with front-controller rewrites. */
    public static function sitemapUrls(): array
    {
        global $wp_rewrite;
        if (!$wp_rewrite->using_mod_rewrite_permalinks()) {
            return [];
        }

        $slugs = [''];
        foreach (self::catalog()['tools'] ?? [] as $tool) {
            $slug = $tool['slug'] ?? null;
            if (is_string($slug) && preg_match('/^[a-z-]+$/D', $slug) === 1) {
                $slugs[] = $slug;
            }
        }

        $entries = [];
        foreach ($slugs as $slug) {
            $path = self::virtualPath($slug);
            if (self::hasReservedPage($slug) || !apply_filters('creditsoft_financial_tools_sitemap_include', true, $path)) {
                continue;
            }
            $url = home_url($path);
            if (!url_to_postid($url)) {
                $entries[] = ['loc' => $url];
            }
        }
        return $entries;
    }

    /** Prevent core's 404 permalink guess only for this exact virtual candidate. */
    public static function canonicalRedirect($redirect)
    {
        return self::eligibleVirtualSlug() !== null ? false : $redirect;
    }

    /** Core exits HEAD before template_include; give only our eligible route its normal status. */
    public static function headExit($exit)
    {
        if ($exit && self::eligibleVirtualSlug() !== null) {
            status_header(200);
        }
        return $exit;
    }

    public static function virtualTemplate(string $template): string
    {
        // Recheck after template_redirect: a custom handler that resolved the request still wins.
        $slug = self::eligibleVirtualSlug();
        if ($slug === null) {
            return $template;
        }
        self::$virtual = $slug;
        global $wp_query;
        $wp_query->is_404 = false;
        status_header(200);
        return __DIR__ . '/templates/virtual.php';
    }

    public static function virtualContent(): string
    {
        return self::$virtual === '' ? self::index() : self::render((string) self::$virtual);
    }

    /** Render the canonical address and published description for the virtual template. */
    public static function printHeadMetadata(): void
    {
        if (self::$virtual === null) {
            return;
        }

        printf("<link rel=\"canonical\" href=\"%s\" />\n", esc_url(home_url(self::virtualPath(self::$virtual))));
        // The index copy comes from site-astro/src/pages/tools/index.astro.
        $description = self::$virtual === ''
            ? 'Twelve free calculators and planning worksheets for budgeting, debt payoff, utilization and credit conversations. No account required.'
            : (self::find(self::$virtual)['description'] ?? '');
        if (is_string($description) && $description !== '') {
            printf("<meta name=\"description\" content=\"%s\" />\n", esc_attr($description));
        }
    }

    public static function documentTitle(array $parts): array
    {
        if (self::$virtual !== null) {
            $parts['title'] = self::$virtual === '' ? 'Free Financial Tools' : self::find(self::$virtual)['title'];
        }
        return $parts;
    }
}

CreditSoft_Financial_Tools::boot();
