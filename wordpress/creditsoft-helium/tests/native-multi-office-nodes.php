<?php
/**
 * Focused real WordPress/Elementor checks for the new topology page and local PNG.
 * Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
 * Product/support: https://builtnotbegged.com. Recorded identity: Matthew X Murphy.
 * SPDX-License-Identifier: GPL-2.0-or-later
 * No previous page, calculator, editor-UI or real infrastructure acceptance replay.
 */
if (!defined('WP_CLI') || !WP_CLI || !defined('CREDITSOFT_TOPOLOGY_TEST_SITE')
    || CREDITSOFT_TOPOLOGY_TEST_SITE !== 'task118-topology-disposable'
    || preg_match('/^cstopology118_[a-f0-9]{8}$/D', DB_NAME) !== 1
    || wp_parse_url(home_url(), PHP_URL_HOST) !== '127.0.0.1') throw new RuntimeException('Use only the owned topology fixture.');
$origin = getenv('CREDITSOFT_TOPOLOGY_HTTP_ORIGIN');
if (!is_string($origin) || preg_match('~^http://cstopology118-[a-f0-9]{8}-wp$~D', $origin) !== 1) throw new RuntimeException('Missing owned topology HTTP origin.');
$checks = 0; $requests = 0;
$check = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) throw new RuntimeException($label); };
$request = static function (string $path) use ($origin, &$requests): array {
    ++$requests;
    $response = wp_remote_get($origin . $path, ['redirection' => 0, 'timeout' => 20,
        'headers' => ['Host' => wp_parse_url(home_url(), PHP_URL_HOST) . ':' . wp_parse_url(home_url(), PHP_URL_PORT)]]);
    if (is_wp_error($response)) throw new RuntimeException('Owned topology request failed.');
    return [(int) wp_remote_retrieve_response_code($response), wp_remote_retrieve_body($response), (string) wp_remote_retrieve_header($response, 'content-type')];
};
$dom = static function (string $html): DOMXPath {
    $old = libxml_use_internal_errors(true);
    try { $doc = new DOMDocument(); $doc->loadHTML($html, LIBXML_NONET); return new DOMXPath($doc); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($old); }
};
$plain = static fn (string $html): string => str_replace(["\u{2018}", "\u{2019}"], "'", trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
$collect = static function (array $elements) use (&$collect): array {
    $result = ['images' => [], 'links' => []];
    foreach ($elements as $element) {
        if (($element['widgetType'] ?? null) === 'image') $result['images'][] = $element['settings'];
        if (is_string($element['settings']['link']['url'] ?? null)) $result['links'][] = $element['settings']['link']['url'];
        if (isset($element['elements']) && is_array($element['elements'])) {
            $nested = $collect($element['elements']);
            $result['images'] = array_merge($result['images'], $nested['images']);
            $result['links'] = array_merge($result['links'], $nested['links']);
        }
    }
    return $result;
};
$heading = 'Multi-office nodes for different cities, states, or VA offices';
$alt = 'CreditSoft node topology visual showing a primary office node, peer office replica, backup-light weak node, and off-node backup lanes.';
$sourceImage = '/assets/images/product-proof/multi-office-node-topology.png';
$asset = '/assets/multi-office-node-topology.png';
$front = get_option('page_on_front');
try {
    $raw = json_decode(file_get_contents(get_stylesheet_directory() . '/content/multi-office-nodes.elementor.json'), true, 512, JSON_THROW_ON_ERROR);
    $data = creditsoft_helium_marketing_template('multi-office-nodes');
    $rawParts = $collect($raw['content']); $parts = $collect($data['content']);
    $check(count($parts['images']) === 1 && $rawParts['images'][0]['image']['url'] === $sourceImage, 'One known published image source is prepared.');
    $imageUrl = get_stylesheet_directory_uri() . $asset;
    $check($parts['images'][0]['image']['url'] === $imageUrl && $parts['images'][0]['image']['id'] === 0, 'Image resolves only to the copied local child asset, without a media import.');
    $check($parts['images'][0]['image']['alt'] === $alt && $parts['images'][0]['image_size'] === 'full', 'Source alt and full image size remain present.');
    $check($parts['images'][0]['link_to'] === 'file' && $parts['images'][0]['open_lightbox'] === 'yes', 'Free native image widget retains file lightbox behavior.');
    $beforeHome = home_url(); $beforeStyle = get_stylesheet_directory_uri();
    $homeFilter = static fn (string $url, string $path): string => 'http://[2001:db8::118]/~fixture' . ($path === '' ? '' : '/' . ltrim($path, '/'));
    $styleFilter = static fn (): string => 'http://[2001:db8::118]/~fixture/wp-content/themes/creditsoft-helium';
    add_filter('home_url', $homeFilter, 10, 2); add_filter('stylesheet_directory_uri', $styleFilter, 10, 0);
    try {
        $preview = $collect(creditsoft_helium_marketing_template('multi-office-nodes')['content']);
        $check($preview['images'][0]['image']['url'] === 'http://[2001:db8::118]/~fixture/wp-content/themes/creditsoft-helium' . $asset, 'New image retains an IPv6 preview installation base without fetching it.');
        foreach (['contact-us', 'login', 'features'] as $path) $check(in_array('http://[2001:db8::118]/~fixture/' . $path, $preview['links'], true), 'This page call to action retains its configured base.');
    } finally { remove_filter('home_url', $homeFilter, 10); remove_filter('stylesheet_directory_uri', $styleFilter, 10); }
    $check(home_url() === $beforeHome && get_stylesheet_directory_uri() === $beforeStyle && get_option('page_on_front') === $front, 'Preparation and temporary URL filters do not import/select a page.');

    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    if (!$admins) throw new RuntimeException('Synthetic core-install administrator required.');
    wp_set_current_user((int) $admins[0]);
    $page = \Elementor\Plugin::instance()->documents->create('wp-page', ['post_title' => $data['title'], 'post_status' => 'publish', 'post_name' => 'multi-office-nodes']);
    if (is_wp_error($page)) throw new RuntimeException('Owned topology page could not be created.');
    $settings = array_merge($data['page_settings'] ?? [], ['template' => 'templates/creditsoft-home.php']);
    $check($page->save(['elements' => $data['content'], 'settings' => $settings]), 'Explicit real Elementor save imports the new page.');
    update_post_meta($page->get_main_id(), '_wp_page_template', 'templates/creditsoft-home.php');
    $path = wp_parse_url(get_permalink($page->get_main_id()), PHP_URL_PATH);
    $check($path === '/multi-office-nodes/', 'New page retains the source public path.');
    [$status, $html] = $request($path); $xp = $dom($html); $copy = $plain($html);
    $check($status === 200 && str_contains($html, 'id="g-page-surround"'), 'New topology page renders in the existing Gantry shell.');
    $check($xp->query('//h1[normalize-space(.)="' . $heading . '"]')->length === 1, 'Source topology heading appears once.');
    foreach ([
        "The intended deployment is not one fragile machine. Offices can run primary, replica, and backup-light nodes in different places, with each node's backup role matched to the hardware it actually has.",
        'Replica roles should match the weakest node', 'Weak offices can run backup-light', 'Wasabi, Dropbox, Google Drive, and local drives',
        'A cluster is only as durable as the smallest node allowed to replicate, so strong nodes should carry the heavy backup work.',
        'A low-disk or low-memory office can stay useful for local work and manager visibility without being forced to store every archive.',
        'Off-node backup lanes keep replicas honest: Wasabi for durable archives, Dropbox or Google Drive for handoff, and removable drives for local copies.',
        'Do not let the weakest machine define every office.', 'Backups should not depend on only another office computer.',
        'The node can be flexible, but the math still has to be honest.',
        'Multi-office only works when each node has an honest role. Strong nodes can replicate and store archives. Weak nodes can still participate without carrying work they cannot safely hold.',
        'CreditSoft should let each office choose local, cloud, and peer-mirror lanes based on the hardware and compliance posture of that location.',
        'CreditSoft should run on supported office hardware without pretending all hardware has the same storage, memory, or uptime profile.',
        'Strong nodes protect the cluster', 'Weak nodes stay backup-light', 'Visibility stays separate from storage',
        'Desktops, workstations, or servers with enough disk and memory can act as active replicas and receive peer backup copies.',
        'Small offices, low-storage laptops, or small ARM devices can keep local workflow online while skipping heavy peer archive duties.',
        'Managers can see node health and office activity without forcing every machine to behave like the main archive server.',
        'Wasabi archive', 'Dropbox or Google Drive handoff', 'Local flash and external drives',
        'Use S3-compatible Wasabi storage as the durable off-machine archive when a local node should not be the only safety net.',
        'Use familiar shared-folder lanes for exports, staged document mirrors, and non-technical handoff workflows.',
        'Support USB flash drives, external SSDs, NVMe/SATA storage, and large local drives for offices that want physical backup copies.',
        'Supported targets', 'Capacity follows the role', 'Locations are the point',
        'Plan for macOS, Ubuntu/Linux/BSD, Windows 11+, and small ARM systems when they have enough memory, disk, and container support.',
        'A backup node needs real storage. A visibility node can be lighter. A replica node should be sized like it may need to recover the office.',
        'Nodes are meant for different cities, states, or VA offices so one building, ISP, or workstation does not become the whole business.',
        'Click the screenshot to open the full-size shadow-box view.',
    ] as $text) $check(str_contains($copy, $text), 'Source topology description, details and context remain readable.');
    $check($xp->query('//img[@src="' . $imageUrl . '" and @alt="' . $alt . '"]')->length === 1, 'Actual page binds the source alt to one local topology image.');
    $check($xp->query('//a[@href="' . $imageUrl . '" and @data-elementor-open-lightbox="yes"]')->length === 1, 'Actual page links its full-size local image through the native lightbox.');
    $check($xp->query('//figcaption[normalize-space(.)="Replica and backup topology"]')->length === 1, 'Source diagram caption is preserved.');
    [$status, $png, $mime] = $request(wp_parse_url($imageUrl, PHP_URL_PATH));
    $size = getimagesizefromstring($png);
    $check($status === 200 && str_starts_with($mime, 'image/png') && $size !== false && $size[0] === 1600 && $size[1] === 1000, 'Actual local image is a decodable PNG at the reviewed dimensions.');
    $check($png === file_get_contents(get_stylesheet_directory() . $asset), 'Actual image bytes match the approved copied asset.');

    $edited = $data['content']; $changed = false;
    $edit = static function (array &$elements) use (&$edit, &$changed, $heading): void {
        foreach ($elements as &$element) {
            if (($element['widgetType'] ?? null) === 'heading' && ($element['settings']['title'] ?? null) === $heading) { $element['settings']['title'] = 'Temporary local topology edit'; $changed = true; }
            if (isset($element['elements']) && is_array($element['elements'])) $edit($element['elements']);
        }
    };
    $edit($edited);
    $check($changed && $page->save(['elements' => $edited, 'settings' => $settings]), 'The new page remains natively editable.');
    creditsoft_helium_marketing_template('multi-office-nodes');
    [$status, $editedHtml] = $request($path);
    $check($status === 200 && str_contains($plain($editedHtml), 'Temporary local topology edit'), 'Reading source again preserves an explicit editor change.');
    $check($page->save(['elements' => $data['content'], 'settings' => $settings]), 'Original source content is explicitly restored.');
    [$status, $restored] = $request($path);
    $check($status === 200 && str_contains($plain($restored), $heading) && get_option('page_on_front') === $front, 'Restored new page renders without changing the frontpage selection.');
    echo 'PASS: ' . $checks . ' new topology checks; ' . $requests . ' actual HTTP requests; WP ' . get_bloginfo('version') . '; PHP ' . PHP_VERSION . ".\n";
} catch (Throwable $failure) {
    echo 'FAIL after ' . $checks . ' checks; ' . $requests . " HTTP requests.\n";
    throw $failure;
}
