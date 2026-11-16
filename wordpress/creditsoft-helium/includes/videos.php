<?php
/** SPDX-License-Identifier: GPL-2.0-or-later */
defined('ABSPATH') || exit;

/** Resolve a selected local Media Library attachment for the public player. */
function creditsoft_helium_video_media_path($value, array $mime_types): string
{
    $id = is_scalar($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
    $attachment = $id ? get_post($id) : null;
    if (!$attachment || $attachment->post_type !== 'attachment'
        || !in_array($attachment->post_mime_type, $mime_types, true)
        || get_post_status($attachment) !== 'publish') {
        return '';
    }

    if ($attachment->post_parent) {
        $parent = get_post($attachment->post_parent);
        if (!$parent || $parent->post_status !== 'publish' || $parent->post_password !== '') {
            return '';
        }
    }

    $file = get_attached_file($attachment->ID);
    $url = wp_get_attachment_url($attachment->ID);
    if (!is_string($file) || !is_file($file) || !is_readable($file) || !is_string($url)) {
        return '';
    }

    $home = wp_parse_url(home_url('/'));
    $media = wp_parse_url($url);
    $scheme = strtolower($home['scheme'] ?? '');
    $default_port = $scheme === 'https' ? 443 : 80;
    if (!in_array($scheme, ['http', 'https'], true) || empty($home['host']) || !is_array($media)
        || strtolower($media['scheme'] ?? '') !== $scheme
        || strcasecmp($media['host'] ?? '', $home['host']) !== 0
        || ($media['port'] ?? $default_port) !== ($home['port'] ?? $default_port)
        || isset($media['user']) || isset($media['pass']) || isset($media['query']) || isset($media['fragment'])) {
        return '';
    }

    $path = $media['path'] ?? '';
    $decoded = rawurldecode($path);
    if (!str_starts_with($decoded, '/') || str_starts_with($decoded, '//')
        || str_contains($decoded, '\\') || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $decoded)
        || sanitize_url($path) !== $path) {
        return '';
    }
    return $path;
}

/** Render the editable library widget using the two legacy watch slugs. */
function creditsoft_helium_video_library($attributes): string
{
    $attributes = shortcode_atts([
        'companion' => '',
        'companion_poster' => '',
        'app' => '',
        'app_poster' => '',
    ], $attributes, 'creditsoft_video_library');

    $clips = [
        'smartcredit-companion' => [
            'key' => 'companion',
            'title' => 'Companion plugin walkthrough',
            'description' => 'See the companion plugin pull SmartCredit data into CreditSoft and hand the file over for review.',
        ],
        'creditsoft-app' => [
            'key' => 'app',
            'title' => 'CreditSoft app overview',
            'description' => 'See how CreditSoft runs as a local web app on your private network and installs like a native app in Chrome.',
        ],
    ];

    $watch = $_GET['watch'] ?? '';
    $selected = is_string($watch) && isset($clips[$watch]) ? $watch : 'smartcredit-companion';
    $clip = $clips[$selected];
    $source = creditsoft_helium_video_media_path($attributes[$clip['key']], ['video/mp4']);
    $poster = $source !== '' ? creditsoft_helium_video_media_path(
        $attributes[$clip['key'] . '_poster'],
        ['image/jpeg', 'image/png', 'image/webp']
    ) : '';
    $page = get_permalink() ?: home_url('/videos/');

    ob_start(); ?>
    <section class="creditsoft-video-library">
        <nav aria-label="Product videos">
            <ul>
                <?php foreach ($clips as $slug => $item) : ?>
                    <li><a href="<?php echo esc_url(add_query_arg('watch', $slug, $page)); ?>"<?php if ($slug === $selected) : ?> aria-current="page"<?php endif; ?>><?php echo esc_html($item['title']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <h2><?php echo esc_html($clip['title']); ?></h2>
        <p><?php echo esc_html($clip['description']); ?></p>
        <?php if ($source !== '') : ?>
            <video controls preload="metadata" playsinline src="<?php echo esc_url($source); ?>"<?php if ($poster !== '') : ?> poster="<?php echo esc_url($poster); ?>"<?php endif; ?>></video>
        <?php else : ?>
            <p>This video is currently unavailable.</p>
        <?php endif; ?>
    </section>
    <?php return (string) ob_get_clean();
}

add_shortcode('creditsoft_video_library', 'creditsoft_helium_video_library');
