<?php
declare(strict_types=1);

require_once __DIR__ . '/site-map-config.php';

function creditsoft_site_seo_defaults(): array
{
    return [
        'default_og_image' => '/assets/images/og-image.png',
        'pages' => [],
    ];
}

function creditsoft_site_seo_storage_path(): string
{
    return dirname(__DIR__) . '/web-meta/site-seo.json';
}

function creditsoft_site_seo_slug_key(string $slug): string
{
    $slug = trim($slug, '/');

    return $slug === '' ? 'home' : $slug;
}

function creditsoft_site_seo_normalize_image_path(string $path, string $default = '/assets/images/og-image.png'): string
{
    $path = trim($path);

    if ($path === '') {
        return $default;
    }

    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }

    return '/' . ltrim($path, '/');
}

function creditsoft_site_seo_sanitize(array $input): array
{
    $defaults = creditsoft_site_seo_defaults();
    $pages = is_array($input['pages'] ?? null) ? $input['pages'] : [];
    $cleanPages = [];

    foreach ($pages as $slug => $page) {
        if (! is_array($page)) {
            continue;
        }

        $slug = trim((string) $slug, '/');
        $slugKey = creditsoft_site_seo_slug_key($slug);

        $cleanPages[$slugKey] = [
            'title' => trim((string) ($page['title'] ?? '')),
            'description' => trim((string) ($page['description'] ?? '')),
            'og_image' => creditsoft_site_seo_normalize_image_path(
                (string) ($page['og_image'] ?? ''),
                $defaults['default_og_image']
            ),
        ];
    }

    return [
        'default_og_image' => creditsoft_site_seo_normalize_image_path(
            (string) ($input['default_og_image'] ?? $defaults['default_og_image']),
            $defaults['default_og_image']
        ),
        'pages' => $cleanPages,
    ];
}

function creditsoft_site_seo_load(): array
{
    static $cached = null;

    if (is_array($cached)) {
        return $cached;
    }

    $defaults = creditsoft_site_seo_defaults();
    $path = creditsoft_site_seo_storage_path();

    if (! is_file($path)) {
        return $cached = $defaults;
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    if (! is_array($decoded)) {
        return $cached = $defaults;
    }

    return $cached = creditsoft_site_seo_sanitize($decoded);
}

function creditsoft_site_seo_save(array $input): bool
{
    $clean = creditsoft_site_seo_sanitize($input);
    $encoded = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (! is_string($encoded)) {
        return false;
    }

    $path = creditsoft_site_seo_storage_path();
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        return false;
    }

    return file_put_contents($path, $encoded . PHP_EOL) !== false;
}

function creditsoft_site_seo_extract_from_source(string $slug, string $path): array
{
    $defaultOgImage = creditsoft_site_seo_defaults()['default_og_image'];
    $contents = (string) file_get_contents($path);
    $fallbackTitle = $slug === '' ? 'CreditSoft - Metro2-First Credit Repair Software' : ucwords(str_replace('-', ' ', $slug)) . ' - CreditSoft';
    $fallbackDescription = 'CreditSoft helps local-first credit repair companies run the website, portal, and operations stack in one place.';

    if (basename($path) === 'index.php') {
        return [
            'title' => 'CreditSoft - Metro2-First Credit Repair Software',
            'description' => 'AI-powered, Metro2-first credit repair platform with built-in FCRA/FDCPA compliance. A complete local-first ecosystem - CRM, client portal, intranet, and website - all running on your own infrastructure, not someone else\'s cloud.',
            'og_image' => creditsoft_site_base_url() . $defaultOgImage,
        ];
    }

    preg_match('/\$page_title\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/i', $contents, $titleMatch);
    preg_match('/\$page_description\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/i', $contents, $descriptionMatch);
    preg_match('/\$page_og_image\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/i', $contents, $imageMatch);

    $titleBase = trim((string) ($titleMatch[1] ?? ''));
    $description = trim((string) ($descriptionMatch[1] ?? ''));
    $image = trim((string) ($imageMatch[1] ?? ''));

    return [
        'title' => $titleBase !== '' ? ($titleBase . ' - CreditSoft') : $fallbackTitle,
        'description' => $description !== '' ? $description : $fallbackDescription,
        'og_image' => $image !== '' ? creditsoft_site_seo_normalize_image_path($image, $defaultOgImage) : (creditsoft_site_base_url() . $defaultOgImage),
    ];
}

function creditsoft_site_seo_effective_for_slug(string $slug, array $defaults): array
{
    $config = creditsoft_site_seo_load();
    $slugKey = creditsoft_site_seo_slug_key($slug);
    $pageConfig = is_array($config['pages'][$slugKey] ?? null) ? $config['pages'][$slugKey] : [];
    $defaultOgImage = creditsoft_site_seo_normalize_image_path((string) ($config['default_og_image'] ?? ''), '/assets/images/og-image.png');

    $title = trim((string) ($pageConfig['title'] ?? ''));
    $description = trim((string) ($pageConfig['description'] ?? ''));
    $ogImage = trim((string) ($pageConfig['og_image'] ?? ''));

    return [
        'title' => $title !== '' ? $title : (string) ($defaults['title'] ?? 'CreditSoft'),
        'description' => $description !== '' ? $description : (string) ($defaults['description'] ?? ''),
        'og_image' => creditsoft_site_seo_normalize_image_path(
            $ogImage !== '' ? $ogImage : ((string) ($defaults['og_image'] ?? $defaultOgImage)),
            $defaultOgImage
        ),
    ];
}

function creditsoft_site_seo_page_rows(): array
{
    $config = creditsoft_site_seo_load();
    $defaultOgImage = creditsoft_site_seo_normalize_image_path((string) ($config['default_og_image'] ?? ''), '/assets/images/og-image.png');
    $rows = [];

    foreach (creditsoft_site_public_routes() as $slug => $path) {
        $slugKey = creditsoft_site_seo_slug_key($slug);
        $source = creditsoft_site_seo_extract_from_source($slug, $path);
        $effective = creditsoft_site_seo_effective_for_slug($slug, $source);

        $rows[] = [
            'slug' => $slug,
            'slug_key' => $slugKey,
            'label' => $slug === '' ? 'Home' : ucwords(str_replace('-', ' ', $slug)),
            'url' => $slug === '' ? creditsoft_site_base_url() . '/' : creditsoft_site_base_url() . '/' . $slug,
            'lastmod' => gmdate('c', (int) filemtime($path)),
            'source_title' => $source['title'],
            'source_description' => $source['description'],
            'source_og_image' => creditsoft_site_seo_normalize_image_path((string) $source['og_image'], $defaultOgImage),
            'title' => $effective['title'],
            'description' => $effective['description'],
            'og_image' => creditsoft_site_seo_normalize_image_path((string) $effective['og_image'], $defaultOgImage),
            'has_override' => isset($config['pages'][$slugKey]),
        ];
    }

    return $rows;
}

function creditsoft_site_seo_upload_image(string $slug, array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload did not finish cleanly.'];
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');

    if ($tmpName === '' || ! is_uploaded_file($tmpName)) {
        return ['success' => false, 'message' => 'CreditSoft could not read the uploaded image.'];
    }

    $mime = mime_content_type($tmpName) ?: '';
    $extensionMap = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    $extension = $extensionMap[$mime] ?? null;

    if ($extension === null) {
        return ['success' => false, 'message' => 'Use PNG, JPG, or WebP for the social preview image.'];
    }

    $slugKey = creditsoft_site_seo_slug_key($slug);
    $filename = ($slugKey === 'home' ? 'home' : preg_replace('/[^a-z0-9-]+/i', '-', $slugKey)) . '-og.' . $extension;
    $relativePath = '/assets/images/seo/' . $filename;
    $absolutePath = __DIR__ . $relativePath;
    $directory = dirname($absolutePath);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        return ['success' => false, 'message' => 'CreditSoft could not create the SEO image folder yet.'];
    }

    if (! move_uploaded_file($tmpName, $absolutePath)) {
        return ['success' => false, 'message' => 'CreditSoft could not move the uploaded image into place.'];
    }

    $current = creditsoft_site_seo_load();
    $current['pages'][$slugKey] = [
        'title' => trim((string) ($current['pages'][$slugKey]['title'] ?? '')),
        'description' => trim((string) ($current['pages'][$slugKey]['description'] ?? '')),
        'og_image' => $relativePath,
    ];

    if (! creditsoft_site_seo_save($current)) {
        return ['success' => false, 'message' => 'The image uploaded, but the SEO config did not save yet.'];
    }

    return [
        'success' => true,
        'message' => 'OG image uploaded and assigned to the page.',
        'path' => $relativePath,
    ];
}
