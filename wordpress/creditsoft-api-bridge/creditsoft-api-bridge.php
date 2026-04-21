<?php
/**
 * Plugin Name: CreditSoft API Bridge
 * Plugin URI: https://www.creditsoft.app/
 * Description: Free CreditSoft website bridge for lead intake, affiliate tracking, and API proxying. Requires a valid CreditSoft API key.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: CreditSoft
 * License: GPLv2 or later
 * Text Domain: creditsoft-api-bridge
 */

if (! defined('ABSPATH')) {
    exit;
}

final class CreditSoft_API_Bridge
{
    private const VERSION = '0.1.0';
    private const OPTION_NAME = 'creditsoft_api_bridge_settings';
    private const COOKIE_NAME = 'creditsoft_affiliate';
    private const QUERY_VAR = 'creditsoft_bridge_path';
    private const DEFAULT_TARGET = 'https://www.creditsoft.app/api/v1';

    public static function boot(): void
    {
        add_action('init', [__CLASS__, 'add_rewrite_rule']);
        add_filter('query_vars', [__CLASS__, 'add_query_var']);
        add_action('template_redirect', [__CLASS__, 'maybe_proxy_api_request'], 0);
        add_action('init', [__CLASS__, 'capture_affiliate_cookie'], 1);

        add_action('admin_menu', [__CLASS__, 'add_settings_page']);
        add_action('admin_post_creditsoft_api_bridge_save', [__CLASS__, 'save_settings']);

        add_action('admin_post_creditsoft_api_bridge_lead', [__CLASS__, 'handle_lead_form']);
        add_action('admin_post_nopriv_creditsoft_api_bridge_lead', [__CLASS__, 'handle_lead_form']);

        add_shortcode('creditsoft_lead_form', [__CLASS__, 'render_lead_form']);
    }

    public static function activate(): void
    {
        self::add_rewrite_rule();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    public static function add_rewrite_rule(): void
    {
        add_rewrite_rule('^api/v1/?(.*)$', 'index.php?'.self::QUERY_VAR.'=$matches[1]', 'top');
    }

    public static function add_query_var(array $vars): array
    {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    public static function maybe_proxy_api_request(): void
    {
        $path = get_query_var(self::QUERY_VAR, null);

        if ($path === null || $path === false) {
            return;
        }

        $path = trim((string) $path, '/');

        if ($path === '' || $path === 'index.php') {
            self::send_json(200, [
                'name' => 'CreditSoft WordPress API Bridge',
                'success' => self::target_base_url() !== '',
                'configured' => self::target_base_url() !== '',
                'version' => self::VERSION,
                'message' => self::target_base_url() !== ''
                    ? 'WordPress bridge is configured and ready to forward API requests.'
                    : 'WordPress bridge is installed, but no CreditSoft API target is configured yet.',
                'lead_shortcode' => '[creditsoft_lead_form]',
                'api_base' => home_url('/api/v1'),
            ]);
        }

        self::proxy_to_creditsoft($path);
    }

    public static function capture_affiliate_cookie(): void
    {
        if (is_admin() || headers_sent()) {
            return;
        }

        $affiliate = self::incoming_affiliate_key();

        if ($affiliate === '') {
            return;
        }

        setcookie(
            self::COOKIE_NAME,
            $affiliate,
            [
                'expires' => time() + DAY_IN_SECONDS * 30,
                'path' => COOKIEPATH ?: '/',
                'domain' => COOKIE_DOMAIN,
                'secure' => is_ssl(),
                'httponly' => false,
                'samesite' => 'Lax',
            ]
        );

        $_COOKIE[self::COOKIE_NAME] = $affiliate;
    }

    public static function add_settings_page(): void
    {
        add_options_page(
            'CreditSoft API Bridge',
            'CreditSoft API Bridge',
            'manage_options',
            'creditsoft-api-bridge',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function render_settings_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = self::settings();
        $target = self::target_base_url();
        $has_key = self::api_key() !== '';
        ?>
        <div class="wrap">
            <h1>CreditSoft API Bridge</h1>
            <p>
                This free add-on keeps the CreditSoft API key on the WordPress server, tracks affiliate/referral links,
                and forwards website leads into the CreditSoft intranet.
            </p>

            <?php if (isset($_GET['creditsoft_saved'])) : ?>
                <div class="notice notice-success is-dismissible"><p>CreditSoft bridge settings saved.</p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('creditsoft_api_bridge_save'); ?>
                <input type="hidden" name="action" value="creditsoft_api_bridge_save" />

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="creditsoft_target_base_url">CreditSoft API base URL</label></th>
                            <td>
                                <input
                                    id="creditsoft_target_base_url"
                                    name="target_base_url"
                                    type="url"
                                    class="regular-text code"
                                    value="<?php echo esc_attr($target); ?>"
                                    placeholder="<?php echo esc_attr(self::DEFAULT_TARGET); ?>"
                                />
                                <p class="description">Use the stable CreditSoft website bridge or the approved office API lane. Include <code>/api/v1</code>.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="creditsoft_api_key">CreditSoft API key</label></th>
                            <td>
                                <input
                                    id="creditsoft_api_key"
                                    name="api_key"
                                    type="password"
                                    class="regular-text"
                                    value=""
                                    autocomplete="new-password"
                                    placeholder="<?php echo $has_key ? esc_attr('Key saved - leave blank to keep it') : esc_attr('Paste website API key'); ?>"
                                />
                                <p class="description">Required for lead forms. The key is sent server-side and is never printed into the public page.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="creditsoft_default_affiliate_key">Default affiliate key</label></th>
                            <td>
                                <input
                                    id="creditsoft_default_affiliate_key"
                                    name="default_affiliate_key"
                                    type="text"
                                    class="regular-text"
                                    value="<?php echo esc_attr((string) ($settings['default_affiliate_key'] ?? '')); ?>"
                                    placeholder="credit-sense"
                                />
                                <p class="description">Query strings like <code>?affiliate=credit-sense</code>, <code>?affiliate_key=...</code>, <code>?aff=...</code>, or <code>?ref=...</code> override this value for 30 days.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="creditsoft_default_status">Default lead status</label></th>
                            <td>
                                <input
                                    id="creditsoft_default_status"
                                    name="default_status"
                                    type="text"
                                    class="regular-text"
                                    value="<?php echo esc_attr((string) ($settings['default_status'] ?? 'lead')); ?>"
                                />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="creditsoft_success_message">Success message</label></th>
                            <td>
                                <input
                                    id="creditsoft_success_message"
                                    name="success_message"
                                    type="text"
                                    class="regular-text"
                                    value="<?php echo esc_attr((string) ($settings['success_message'] ?? 'Thanks. Your request was sent.')); ?>"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button('Save CreditSoft bridge'); ?>
            </form>

            <h2>Shortcode</h2>
            <p>Add this to any WordPress page:</p>
            <p><code>[creditsoft_lead_form]</code></p>
            <p>Optional example:</p>
            <p><code>[creditsoft_lead_form title="Request portal access" button="Request access" affiliate_key="credit-sense"]</code></p>
        </div>
        <?php
    }

    public static function save_settings(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized.');
        }

        check_admin_referer('creditsoft_api_bridge_save');

        $current = self::settings();
        $incoming_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash((string) $_POST['api_key'])) : '';

        $settings = [
            'target_base_url' => self::normalize_base_url(
                isset($_POST['target_base_url']) ? wp_unslash((string) $_POST['target_base_url']) : self::DEFAULT_TARGET
            ),
            'api_key' => $incoming_key !== '' ? $incoming_key : (string) ($current['api_key'] ?? ''),
            'default_affiliate_key' => self::sanitize_key_value(
                isset($_POST['default_affiliate_key']) ? wp_unslash((string) $_POST['default_affiliate_key']) : ''
            ),
            'default_status' => self::sanitize_key_value(
                isset($_POST['default_status']) ? wp_unslash((string) $_POST['default_status']) : 'lead'
            ) ?: 'lead',
            'success_message' => sanitize_text_field(
                isset($_POST['success_message']) ? wp_unslash((string) $_POST['success_message']) : 'Thanks. Your request was sent.'
            ),
        ];

        update_option(self::OPTION_NAME, $settings, false);

        wp_safe_redirect(add_query_arg('creditsoft_saved', '1', admin_url('options-general.php?page=creditsoft-api-bridge')));
        exit;
    }

    public static function render_lead_form(array $atts = []): string
    {
        $atts = shortcode_atts([
            'title' => 'Request portal access',
            'button' => 'Send request',
            'affiliate_key' => '',
            'show_score' => 'false',
        ], $atts, 'creditsoft_lead_form');

        $message = self::lead_form_notice();
        $affiliate = self::resolve_affiliate_key((string) $atts['affiliate_key']);
        $show_score = filter_var($atts['show_score'], FILTER_VALIDATE_BOOL);

        ob_start();
        ?>
        <form class="creditsoft-lead-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('creditsoft_api_bridge_lead'); ?>
            <input type="hidden" name="action" value="creditsoft_api_bridge_lead" />
            <input type="hidden" name="page_url" value="<?php echo esc_url(self::current_url()); ?>" />
            <input type="hidden" name="page_title" value="<?php echo esc_attr(wp_get_document_title()); ?>" />
            <input type="hidden" name="affiliate_key" value="<?php echo esc_attr($affiliate); ?>" />

            <?php if ($message !== '') : ?>
                <p class="creditsoft-lead-form__notice"><?php echo esc_html($message); ?></p>
            <?php endif; ?>

            <h3><?php echo esc_html((string) $atts['title']); ?></h3>
            <p class="creditsoft-lead-form__row">
                <label>
                    <span>First name</span>
                    <input name="first_name" type="text" autocomplete="given-name" required />
                </label>
                <label>
                    <span>Last name</span>
                    <input name="last_name" type="text" autocomplete="family-name" required />
                </label>
            </p>
            <p class="creditsoft-lead-form__row">
                <label>
                    <span>Email</span>
                    <input name="email" type="email" autocomplete="email" />
                </label>
                <label>
                    <span>Phone</span>
                    <input name="phone" type="tel" autocomplete="tel" />
                </label>
            </p>
            <?php if ($show_score) : ?>
                <p>
                    <label>
                        <span>Current score</span>
                        <input name="current_score" type="number" min="300" max="850" inputmode="numeric" />
                    </label>
                </p>
            <?php endif; ?>
            <p>
                <label>
                    <span>What are you trying to fix or qualify for?</span>
                    <textarea name="goals" rows="4"></textarea>
                </label>
            </p>
            <p class="creditsoft-lead-form__hp">
                <label>
                    <span>Company</span>
                    <input name="company" type="text" tabindex="-1" autocomplete="off" />
                </label>
            </p>
            <button type="submit"><?php echo esc_html((string) $atts['button']); ?></button>
        </form>
        <style>
            .creditsoft-lead-form{display:grid;gap:1rem;max-width:720px}
            .creditsoft-lead-form h3{margin:0}
            .creditsoft-lead-form label{display:grid;gap:.35rem;font-weight:600}
            .creditsoft-lead-form input,.creditsoft-lead-form textarea{box-sizing:border-box;width:100%;border:1px solid #d6d3d1;border-radius:8px;padding:.75rem;font:inherit}
            .creditsoft-lead-form button{width:fit-content;border:0;border-radius:8px;background:#111827;color:#fff;padding:.8rem 1.1rem;font-weight:700;cursor:pointer}
            .creditsoft-lead-form__row{display:grid;gap:1rem;margin:0}
            .creditsoft-lead-form__notice{border:1px solid #d6d3d1;border-radius:8px;padding:.75rem;background:#f8fafc}
            .creditsoft-lead-form__hp{position:absolute;left:-9999px;opacity:0}
            @media (min-width: 720px){.creditsoft-lead-form__row{grid-template-columns:1fr 1fr}}
        </style>
        <?php

        return (string) ob_get_clean();
    }

    public static function handle_lead_form(): void
    {
        check_admin_referer('creditsoft_api_bridge_lead');

        $redirect = wp_get_referer() ?: home_url('/');

        if (! empty($_POST['company'])) {
            wp_safe_redirect(add_query_arg('creditsoft_bridge', 'ok', $redirect));
            exit;
        }

        $first_name = sanitize_text_field(wp_unslash((string) ($_POST['first_name'] ?? '')));
        $last_name = sanitize_text_field(wp_unslash((string) ($_POST['last_name'] ?? '')));

        if ($first_name === '' || $last_name === '') {
            wp_safe_redirect(add_query_arg('creditsoft_bridge', 'missing', $redirect));
            exit;
        }

        $affiliate = self::resolve_affiliate_key(
            isset($_POST['affiliate_key']) ? wp_unslash((string) $_POST['affiliate_key']) : ''
        );

        $payload = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => sanitize_email(wp_unslash((string) ($_POST['email'] ?? ''))),
            'phone' => sanitize_text_field(wp_unslash((string) ($_POST['phone'] ?? ''))),
            'status' => self::default_status(),
            'goals' => sanitize_textarea_field(wp_unslash((string) ($_POST['goals'] ?? ''))),
            'external_reference' => self::external_reference($first_name, $last_name),
            'metadata' => self::lead_metadata(),
        ];

        $score = absint($_POST['current_score'] ?? 0);
        if ($score >= 300 && $score <= 850) {
            $payload['current_score'] = $score;
        }

        if ($affiliate !== '') {
            $payload['affiliate_key'] = $affiliate;
            $payload['metadata']['affiliate_query_key'] = $affiliate;
        }

        $payload = array_filter($payload, static fn ($value) => $value !== '' && $value !== null);
        $result = self::post_client($payload);

        $status = is_wp_error($result) ? 'error' : 'success';
        wp_safe_redirect(add_query_arg('creditsoft_bridge', $status, $redirect));
        exit;
    }

    private static function proxy_to_creditsoft(string $path): void
    {
        $target = self::target_base_url();

        if ($target === '') {
            self::send_json(503, [
                'message' => 'CreditSoft API target is not configured.',
            ]);
        }

        $url = rtrim($target, '/').'/'.$path;
        $query = self::filtered_query_string();
        if ($query !== '') {
            $url .= '?'.$query;
        }

        $headers = self::forward_headers();
        $api_key = self::api_key();
        if ($api_key !== '' && empty($headers['Authorization']) && empty($headers['authorization'])) {
            $headers['Authorization'] = 'Bearer '.$api_key;
        }

        $response = wp_remote_request($url, [
            'method' => strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            'headers' => $headers,
            'body' => file_get_contents('php://input'),
            'timeout' => 30,
            'redirection' => 0,
        ]);

        self::relay_response($response);
    }

    private static function post_client(array $payload)
    {
        $api_key = self::api_key();

        if ($api_key === '') {
            return new WP_Error('creditsoft_missing_key', 'CreditSoft API key is not configured.');
        }

        $response = wp_remote_post(rtrim(self::target_base_url(), '/').'/clients', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            return new WP_Error('creditsoft_api_error', 'CreditSoft rejected the lead.', [
                'status' => $status,
                'body' => wp_remote_retrieve_body($response),
            ]);
        }

        return $response;
    }

    private static function relay_response($response): void
    {
        if (is_wp_error($response)) {
            self::send_json(502, [
                'message' => $response->get_error_message(),
            ]);
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');

        status_header($status > 0 ? $status : 502);

        if ($content_type) {
            header('Content-Type: '.$content_type);
        }

        echo wp_remote_retrieve_body($response);
        exit;
    }

    private static function send_json(int $status, array $payload): void
    {
        status_header($status);
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode($payload);
        exit;
    }

    private static function forward_headers(): array
    {
        $headers = function_exists('getallheaders') ? (array) getallheaders() : [];
        $allowed = [
            'accept',
            'authorization',
            'content-type',
            'if-modified-since',
            'if-none-match',
            'x-creditsoft-token',
            'x-requested-with',
        ];
        $clean = [];

        foreach ($headers as $name => $value) {
            if (! in_array(strtolower((string) $name), $allowed, true)) {
                continue;
            }

            $clean[(string) $name] = (string) $value;
        }

        return $clean;
    }

    private static function filtered_query_string(): string
    {
        $query = [];
        parse_str((string) ($_SERVER['QUERY_STRING'] ?? ''), $query);
        unset($query[self::QUERY_VAR]);

        return http_build_query($query);
    }

    private static function lead_form_notice(): string
    {
        $state = isset($_GET['creditsoft_bridge']) ? sanitize_key((string) wp_unslash($_GET['creditsoft_bridge'])) : '';

        if ($state === 'success') {
            $settings = self::settings();

            return (string) ($settings['success_message'] ?? 'Thanks. Your request was sent.');
        }

        if ($state === 'missing') {
            return 'Please add your first and last name.';
        }

        if ($state === 'error') {
            return 'The request could not be sent right now. Please try again or call the team.';
        }

        return '';
    }

    private static function resolve_affiliate_key(string $shortcode_affiliate = ''): string
    {
        $incoming = self::incoming_affiliate_key();
        if ($incoming !== '') {
            return $incoming;
        }

        $cookie = isset($_COOKIE[self::COOKIE_NAME]) ? self::sanitize_key_value(wp_unslash((string) $_COOKIE[self::COOKIE_NAME])) : '';
        if ($cookie !== '') {
            return $cookie;
        }

        $shortcode_affiliate = self::sanitize_key_value($shortcode_affiliate);
        if ($shortcode_affiliate !== '') {
            return $shortcode_affiliate;
        }

        $settings = self::settings();

        return self::sanitize_key_value((string) ($settings['default_affiliate_key'] ?? ''));
    }

    private static function incoming_affiliate_key(): string
    {
        foreach (['affiliate_key', 'creditsoft_affiliate', 'affiliate', 'aff', 'ref'] as $key) {
            if (! isset($_GET[$key])) {
                continue;
            }

            $value = self::sanitize_key_value(wp_unslash((string) $_GET[$key]));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private static function lead_metadata(): array
    {
        return [
            'source' => 'wordpress_plugin',
            'site_url' => home_url('/'),
            'page_url' => esc_url_raw(wp_unslash((string) ($_POST['page_url'] ?? self::current_url()))),
            'page_title' => sanitize_text_field(wp_unslash((string) ($_POST['page_title'] ?? wp_get_document_title()))),
            'referrer' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash((string) $_SERVER['HTTP_REFERER'])) : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_USER_AGENT'])) : '',
            'utm' => self::utm_values(),
            'plugin' => [
                'name' => 'creditsoft-api-bridge',
                'version' => self::VERSION,
            ],
        ];
    }

    private static function utm_values(): array
    {
        $values = [];

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
            if (isset($_GET[$key])) {
                $values[$key] = sanitize_text_field(wp_unslash((string) $_GET[$key]));
            }
        }

        return $values;
    }

    private static function external_reference(string $first_name, string $last_name): string
    {
        $host = wp_parse_url(home_url('/'), PHP_URL_HOST) ?: 'wordpress';
        $hash = substr(wp_hash($first_name.'|'.$last_name.'|'.microtime(true)), 0, 12);

        return 'wp:'.$host.':'.gmdate('YmdHis').':'.$hash;
    }

    private static function current_url(): string
    {
        $scheme = is_ssl() ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? wp_parse_url(home_url('/'), PHP_URL_HOST));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

        return esc_url_raw($scheme.'://'.$host.$uri);
    }

    private static function default_status(): string
    {
        $settings = self::settings();
        $status = self::sanitize_key_value((string) ($settings['default_status'] ?? 'lead'));

        return $status !== '' ? $status : 'lead';
    }

    private static function target_base_url(): string
    {
        $settings = self::settings();

        return self::normalize_base_url((string) ($settings['target_base_url'] ?? self::DEFAULT_TARGET));
    }

    private static function api_key(): string
    {
        $settings = self::settings();

        return trim((string) ($settings['api_key'] ?? ''));
    }

    private static function settings(): array
    {
        $settings = get_option(self::OPTION_NAME, []);

        return is_array($settings) ? $settings : [];
    }

    private static function normalize_base_url(string $url): string
    {
        $url = esc_url_raw(trim($url));

        if ($url === '') {
            return self::DEFAULT_TARGET;
        }

        $url = rtrim($url, '/');

        return str_ends_with($url, '/api/v1') ? $url : $url.'/api/v1';
    }

    private static function sanitize_key_value(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_.-]+/', '-', $value) ?: '';

        return trim($value, '-');
    }
}

CreditSoft_API_Bridge::boot();

register_activation_hook(__FILE__, ['CreditSoft_API_Bridge', 'activate']);
register_deactivation_hook(__FILE__, ['CreditSoft_API_Bridge', 'deactivate']);
