<?php
$page_title = 'Videos';
$page_description = 'CreditSoft product videos, walkthroughs, and companion-plugin demonstrations.';
$page_hero = true;
$hero_title = 'Videos';
$hero_subtitle = 'Product walkthroughs and app demos.';

$videoStatsPath = dirname(__DIR__) . '/web-meta/video-stats.json';
$videoStats = [];

if (! function_exists('creditsoft_format_video_views')) {
    function creditsoft_format_video_views(int $views): string
    {
        if ($views >= 1000000) {
            $value = number_format($views / 1000000, 1);

            return rtrim(rtrim($value, '0'), '.') . 'M views';
        }

        if ($views >= 1000) {
            $value = number_format($views / 1000, 1);

            return rtrim(rtrim($value, '0'), '.') . 'K views';
        }

        return number_format($views) . ' ' . ($views === 1 ? 'view' : 'views');
    }
}

if (is_file($videoStatsPath)) {
    $decodedStats = json_decode((string) file_get_contents($videoStatsPath), true);
    if (is_array($decodedStats)) {
        $videoStats = $decodedStats;
    }
}

$video_library = [
    [
        'slug' => 'smartcredit-companion',
        'category' => 'Training',
        'category_note' => 'Automation walkthroughs and real workflow demos.',
        'title' => 'Companion plugin walkthrough',
        'published_at' => '2026-04-14',
        'description' => 'See the companion plugin pull SmartCredit data into CreditSoft and hand the file over for review.',
        'path' => __DIR__ . '/assets/videos/smartcredit-companion-web.mp4',
        'url' => '/assets/videos/smartcredit-companion-web.mp4',
        'poster_path' => __DIR__ . '/assets/videos/smartcredit-companion-cover.png',
        'poster_url' => '/assets/videos/smartcredit-companion-cover.png',
        'duration' => '35.4s',
        'resolution' => '2216x1440',
        'caption_title' => 'Companion plugin walkthrough',
        'caption_lines' => [
            'This is the CreditSoft companion plugin pulling SmartCredit data into the client file automatically.',
            'Once the capture lands, CreditSoft builds the client profile locally and hands the file into the review lane.',
            'IdentityIQ support is in the product too, but this clip is showing the SmartCredit lane specifically.',
            'No manual data entry. No outsourcing. This run is about 35 seconds, with real speed depending on connection quality and source-site response.',
        ],
    ],
    [
        'slug' => 'creditsoft-app',
        'category' => 'Setup',
        'category_note' => 'Install, launch, and local-app basics.',
        'title' => 'CreditSoft app overview',
        'published_at' => '2026-04-14',
        'description' => 'See how CreditSoft runs as a local web app on your private network and installs like a native app in Chrome.',
        'path' => __DIR__ . '/assets/videos/creditsoft-app-web.mp4',
        'url' => '/assets/videos/creditsoft-app-web.mp4',
        'poster_path' => __DIR__ . '/assets/videos/creditsoft-app-cover.png',
        'poster_url' => '/assets/videos/creditsoft-app-cover.png',
        'duration' => '39.1s',
        'resolution' => '2216x1440',
        'caption_title' => 'CreditSoft app on your private network',
        'caption_lines' => [
            'CreditSoft runs on your private network as a secure web app.',
            'In Chrome, you can install it as an app so it feels much closer to native software on macOS.',
            'It shows up in the dock, opens fast, runs locally, and stays under your company’s control.',
            'There is no dependency on outside cloud servers for the core local workflow. Performance comes from the hardware you choose to run it on.',
            'More power means faster processing, quicker results, and more room to handle multiple client files at once.',
        ],
    ],
];

foreach ($video_library as $index => $video) {
    $video_library[$index]['has_video'] = is_file($video['path']);
    $video_library[$index]['size'] = is_file($video['path']) ? round(filesize($video['path']) / 1048576, 1).' MB' : '';
    $video_library[$index]['poster'] = is_file($video['poster_path']) ? $video['poster_url'] : '';
    $stats = is_array($videoStats[$video['slug']] ?? null) ? $videoStats[$video['slug']] : [];
    $video_library[$index]['views'] = (int) ($stats['views'] ?? 0);
    $video_library[$index]['last_viewed_at'] = (string) ($stats['last_viewed_at'] ?? '');
    $video_library[$index]['share_url'] = 'https://www.creditsoft.app/videos?watch=' . rawurlencode((string) $video['slug']);
    $video_library[$index]['facebook_share_url'] = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode((string) $video_library[$index]['share_url']);
    $viewCount = (int) $video_library[$index]['views'];
    $video_library[$index]['views_label'] = creditsoft_format_video_views($viewCount);
    $publishedAt = (string) ($video['published_at'] ?? '');
    $publishedTimestamp = $publishedAt !== '' ? strtotime($publishedAt) : false;
    if (! is_int($publishedTimestamp) || $publishedTimestamp <= 0) {
        $publishedTimestamp = is_file($video['path']) ? filemtime($video['path']) : false;
    }
    $video_library[$index]['published_label'] = is_int($publishedTimestamp) && $publishedTimestamp > 0 ? date('M j, Y', $publishedTimestamp) : '';
    $video_library[$index]['published_iso'] = is_int($publishedTimestamp) && $publishedTimestamp > 0 ? date('Y-m-d', $publishedTimestamp) : '';
}

$video_sections = [];
foreach ($video_library as $video) {
    $category = (string) ($video['category'] ?? 'Videos');
    if (!isset($video_sections[$category])) {
        $video_sections[$category] = [
            'note' => (string) ($video['category_note'] ?? ''),
            'videos' => [],
        ];
    }
    $video_sections[$category]['videos'][] = $video;
}

$selectedVideo = null;
$requestedVideoSlug = preg_replace('/[^a-z0-9-]/', '', strtolower(trim((string) ($_GET['watch'] ?? ''))));

if ($requestedVideoSlug !== '') {
    foreach ($video_library as $video) {
        if (($video['slug'] ?? '') === $requestedVideoSlug) {
            $selectedVideo = $video;
            break;
        }
    }
}

if (is_array($selectedVideo)) {
    $page_title = $selectedVideo['title'];
    $page_description = $selectedVideo['description'];
    $page_og_image = $selectedVideo['poster_url'];
    $page_canonical_url = $selectedVideo['share_url'];
    $page_hero = false;
}

require __DIR__ . '/header.php';
?>
<style>
    body.lightbox-open { overflow:hidden; }
    .videos-shell {
        max-width: 1120px;
        width:100%;
        margin: 0 auto;
        padding: 34px 20px 56px;
        display: grid;
        gap: 24px;
        box-sizing:border-box;
        overflow-x:hidden;
    }
    .videos-shell.is-watch-page {
        padding-top: 18px;
        gap: 18px;
    }
    .videos-intro {
        display: grid;
        gap: 10px;
        max-width: 980px;
    }
    .videos-intro p {
        margin: 0;
        font-size: 17px;
        line-height: 1.65;
        color: #475569;
        max-width: 940px;
    }
    .video-watch-page {
        display:grid;
        gap:14px;
        padding:18px;
        min-width:0;
        width:100%;
        max-width:100%;
        background:#fff;
        border:1px solid var(--border);
        border-radius:30px;
        box-shadow:0 20px 46px rgba(15,23,42,.08);
    }
    .video-watch-page,
    .video-watch-page * {
        box-sizing:border-box;
    }
    .video-watch-page__frame {
        overflow:hidden;
        min-width:0;
        max-width:100%;
        border-radius:24px;
        background:#020617;
        box-shadow:0 18px 44px rgba(2,6,23,.2);
    }
    .video-watch-page__player {
        display:block;
        width:100%;
        min-width:0;
        max-width:100%;
        height:auto;
        max-height:min(68vh, 640px);
        background:black;
        aspect-ratio:16 / 9;
        object-fit:contain;
    }
    .video-watch-page__body {
        display:grid;
        gap:14px;
    }
    .video-watch-page__heading {
        position:relative;
        display:block;
        min-width:0;
    }
    .video-watch-page__heading-copy {
        display:block;
        min-width:0;
    }
    .video-watch-page__title-row {
        display:flex;
        align-items:flex-start;
        gap:8px;
        width:100%;
        max-width:100%;
        min-width:0;
    }
    .video-watch-page__heading-actions {
        display:flex;
        flex-wrap:nowrap;
        align-items:center;
        justify-content:flex-end;
        gap:0;
    }
    .video-watch-page__eyebrow {
        display:none;
        align-items:center;
        gap:8px;
        width:max-content;
        padding:9px 14px;
        border-radius:999px;
        background:#dbeafe;
        color:#1d4ed8;
        font-size:12px;
        font-weight:800;
        letter-spacing:.14em;
        text-transform:uppercase;
    }
    .video-watch-page__title {
        margin:0;
        flex:0 1 auto;
        min-width:0;
        font-size:clamp(24px, 4.2vw, 38px);
        line-height:1.05;
        letter-spacing:-0.04em;
        color:#0f172a;
        overflow-wrap:break-word;
    }
    .video-watch-page__heading-actions .video-menu__toggle {
        width:36px;
        height:36px;
        padding:0;
        border-radius:0;
        border:none;
        background:transparent;
        box-shadow:none;
        color:#334155;
    }
    .video-watch-page__heading-actions .video-menu__toggle:hover {
        background:transparent;
        border-color:transparent;
        color:#1d4ed8;
    }
    .video-watch-page__stats {
        padding:0 2px;
    }
    .video-watch-page__details {
        display:grid;
        gap:14px;
        min-width:0;
        padding:18px 20px;
        border-radius:22px;
        border:1px solid rgba(148,163,184,.2);
        background:#f8fafc;
    }
    .video-watch-page__details-content {
        position:relative;
        display:grid;
        gap:12px;
        max-height:152px;
        overflow:hidden;
        transition:max-height .22s ease;
    }
    .video-watch-page__details-content::after {
        content:'';
        position:absolute;
        left:0;
        right:0;
        bottom:0;
        height:52px;
        background:linear-gradient(180deg, rgba(248,250,252,0) 0%, rgba(248,250,252,1) 100%);
        pointer-events:none;
    }
    .video-watch-page__details.is-expanded .video-watch-page__details-content {
        max-height:720px;
    }
    .video-watch-page__details.is-expanded .video-watch-page__details-content::after {
        display:none;
    }
    .video-watch-page__details-topline {
        display:flex;
        flex-wrap:wrap;
        gap:10px 18px;
        color:#0f172a;
        font-size:14px;
        font-weight:800;
    }
    .video-watch-page__details-topline span,
    .video-watch-page__details-topline time {
        display:inline-flex;
        align-items:center;
        gap:8px;
    }
    .video-watch-page__details-topline i {
        color:#1d4ed8;
        font-size:13px;
    }
    .video-watch-page__details-lead {
        margin:0;
        min-width:0;
        color:#0f172a;
        font-size:16px;
        line-height:1.72;
        font-weight:700;
        overflow-wrap:anywhere;
    }
    .video-watch-page__details-body {
        display:grid;
        gap:10px;
    }
    .video-watch-page__details-body strong {
        font-size:17px;
        line-height:1.25;
        color:#0f172a;
    }
    .video-watch-page__details-body p {
        margin:0;
        min-width:0;
        color:#334155;
        font-size:15px;
        line-height:1.72;
        overflow-wrap:anywhere;
    }
    .video-watch-page__details-toggle {
        justify-self:flex-start;
        padding:0;
        border:none;
        background:transparent;
        color:#1d4ed8;
        font-size:14px;
        font-weight:800;
        cursor:pointer;
    }
    .video-watch-page__details-toggle:hover {
        text-decoration:underline;
    }
    .video-watch-page__caption {
        padding:0;
    }
    .videos-list {
        display:grid;
        grid-template-columns:repeat(2, minmax(0, 1fr));
        gap:22px;
    }
    .videos-section {
        display:grid;
        gap:16px;
    }
    .videos-section__header {
        display:grid;
        gap:6px;
    }
    .videos-section__header strong {
        font-size:14px;
        font-weight:800;
        letter-spacing:.16em;
        text-transform:uppercase;
        color:#1d4ed8;
    }
    .videos-section__header p {
        margin:0;
        color:#64748b;
        font-size:15px;
        line-height:1.6;
    }
    .video-stage {
        background: white;
        border: 1px solid var(--border);
        border-radius: 26px;
        box-shadow: 0 18px 42px rgba(15,23,42,.06);
        overflow:visible;
        display: grid;
        align-content:start;
        position:relative;
        z-index:1;
    }
    .video-toolbar {
        display:flex;
        justify-content:flex-end;
        align-items:center;
        gap:0;
        padding:0;
        flex:0 0 auto;
    }
    .video-toolbar__actions {
        position:relative;
        display:flex;
        align-items:center;
        z-index:20;
    }
    .video-preview {
        position:relative;
        display:block;
        background:#020617;
        text-align:left;
        aspect-ratio:16 / 9;
        overflow:hidden;
        border-radius:26px 26px 0 0;
    }
    .video-preview__poster {
        position:relative;
        display:block;
        width:100%;
        padding:0;
        border:none;
        background:#020617;
        cursor:pointer;
        text-align:left;
        height:100%;
        color:inherit;
        text-decoration:none;
    }
    .video-preview img {
        width:100%;
        display:block;
        aspect-ratio:16 / 9;
        object-fit:cover;
        height:100%;
    }
    .video-preview__poster::after {
        content:'';
        position:absolute;
        inset:0;
        background:linear-gradient(180deg, rgba(2,6,23,0.04) 0%, rgba(2,6,23,0.38) 100%);
    }
    .video-preview__play {
        position:absolute;
        left:50%;
        top:50%;
        transform:translate(-50%, -50%);
        z-index:2;
        width:78px;
        height:78px;
        border-radius:999px;
        background:rgba(255,255,255,0.92);
        color:#0f172a;
        display:grid;
        place-items:center;
        box-shadow:0 18px 40px rgba(2,6,23,.26);
    }
    .video-preview__play i {
        font-size:26px;
        transform:translateX(2px);
    }
    .video-inline-player {
        position:absolute;
        inset:0;
        display:block;
        width:100%;
        height:100%;
        background:black;
        opacity:0;
        pointer-events:none;
        transition:opacity .18s ease;
    }
    .video-preview.is-previewing .video-inline-player {
        opacity:1;
    }
    .video-preview.is-previewing .video-preview__play {
        opacity:0;
    }
    .video-preview__sound {
        position:absolute;
        right:14px;
        bottom:14px;
        z-index:3;
        width:42px;
        height:42px;
        border:none;
        border-radius:999px;
        background:rgba(8,13,25,0.84);
        color:#f8fafc;
        display:none;
        align-items:center;
        justify-content:center;
        cursor:pointer;
        box-shadow:0 12px 28px rgba(2,6,23,.22);
    }
    .video-preview.is-previewing .video-preview__sound {
        display:inline-flex;
    }
    .video-preview__sound:hover {
        background:rgba(15,23,42,0.94);
    }
    .video-menu {
        position:relative;
    }
    .video-menu__toggle {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:auto;
        height:auto;
        padding:4px 8px;
        border-radius:0;
        border:none;
        background:transparent;
        color:#1e293b;
        font-size:20px;
        line-height:1;
        cursor:pointer;
        box-shadow:none;
    }
    .video-menu__dots {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        font-size:28px;
        font-weight:900;
        line-height:.8;
        transform:translateY(-1px);
    }
    .video-menu__toggle:hover {
        color:#1d4ed8;
    }
    .video-menu__panel {
        position:absolute;
        top:calc(100% + 10px);
        right:0;
        min-width:220px;
        padding:10px;
        border-radius:18px;
        background:rgba(8,13,25,0.98);
        border:1px solid rgba(148,163,184,0.16);
        box-shadow:0 24px 54px rgba(2,6,23,.34);
        display:none;
        z-index:60;
    }
    .video-menu.is-open .video-menu__panel {
        display:grid;
        gap:6px;
    }
    .video-menu__item {
        display:flex;
        align-items:center;
        gap:10px;
        width:100%;
        padding:11px 12px;
        border:none;
        border-radius:12px;
        background:transparent;
        color:#e2e8f0;
        font-size:14px;
        font-weight:700;
        text-decoration:none;
        cursor:pointer;
        text-align:left;
    }
    .video-menu__item:hover {
        background:rgba(37,99,235,.16);
        color:#fff;
        text-decoration:none;
    }
    .video-header {
        display: grid;
        gap: 8px;
        padding: 20px 20px 16px;
    }
    .video-header__top {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:14px;
    }
    .video-header strong {
        font-size: 26px;
        line-height: 1.08;
        letter-spacing: -0.03em;
        color: var(--dark);
    }
    .video-header p {
        margin: 0;
        color: #475569;
        font-size: 16px;
        line-height: 1.65;
        max-width: 760px;
    }
    .videos-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px 18px;
        font-size: 13px;
        line-height: 1.5;
        color: #64748b;
        font-weight: 700;
    }
    .videos-meta span {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .videos-meta span.is-hidden-view {
        display:none;
    }
    .videos-meta i {
        color: var(--primary);
        font-size: 13px;
    }
    .video-actions {
        display:none;
    }
    .video-action {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:42px;
        height:42px;
        border-radius:999px;
        border:1px solid rgba(148,163,184,.22);
        background:#f8fafc;
        color:#1e293b;
        font-size:15px;
        cursor:pointer;
        text-decoration:none;
        box-shadow:0 10px 24px rgba(15,23,42,.05);
    }
    .video-action:hover {
        text-decoration:none;
        background:#eff6ff;
        border-color:#bfdbfe;
        color:#1d4ed8;
    }
    .video-action.is-primary {
        background:#111827;
        color:#fff;
        border-color:#111827;
    }
    .video-action.is-primary:hover {
        background:#1f2937;
        color:#fff;
    }
    .video-caption {
        display: grid;
        gap: 10px;
        padding: 0 20px 20px;
    }
    .video-caption strong {
        font-size: 18px;
        line-height: 1.2;
        color: var(--dark);
    }
    .video-caption p {
        margin: 0;
        color: #334155;
        font-size: 15px;
        line-height: 1.68;
    }
    .video-placeholder {
        padding: 40px 30px;
        color: white;
        text-align: left;
    }
    .video-placeholder h2 {
        font-size: 30px;
        line-height: 1.04;
        margin-bottom: 12px;
    }
    .video-placeholder p {
        color: rgba(255,255,255,.82);
        max-width: 540px;
        margin-bottom: 18px;
    }
    .video-modal {
        position:fixed;
        inset:0;
        background:rgba(2,6,23,0.82);
        backdrop-filter:blur(8px);
        display:none;
        align-items:center;
        justify-content:center;
        padding:28px;
        z-index:300;
    }
    .video-modal.is-open { display:flex; }
    .video-modal__dialog {
        width:min(1080px, 100%);
        background:#07111f;
        color:white;
        border-radius:28px;
        border:1px solid rgba(255,255,255,0.08);
        box-shadow:0 34px 90px rgba(2,6,23,0.4);
        padding:18px;
        position:relative;
        display:grid;
        gap:16px;
    }
    .video-modal__close {
        position:absolute;
        top:-12px;
        right:-12px;
        width:44px;
        height:44px;
        border:none;
        border-radius:999px;
        background:white;
        color:#0f172a;
        font-size:26px;
        line-height:1;
        cursor:pointer;
        box-shadow:0 14px 30px rgba(2,6,23,0.26);
    }
    .video-modal__frame {
        overflow:hidden;
        border-radius:22px;
        background:black;
        position:relative;
    }
    .video-modal__frame video {
        display:block;
        width:100%;
        max-height:min(72vh, 760px);
        background:black;
    }
    .video-modal__next {
        position:absolute;
        right:18px;
        bottom:18px;
        width:min(360px, calc(100% - 36px));
        padding:16px 18px;
        border-radius:20px;
        background:rgba(8,13,25,0.92);
        border:1px solid rgba(255,255,255,0.08);
        box-shadow:0 20px 48px rgba(2,6,23,0.32);
        display:none;
        gap:12px;
    }
    .video-modal__next.is-visible {
        display:grid;
    }
    .video-modal__next small {
        color:#93c5fd;
        font-size:11px;
        font-weight:800;
        letter-spacing:.16em;
        text-transform:uppercase;
    }
    .video-modal__next strong {
        font-size:20px;
        line-height:1.1;
        color:#f8fafc;
    }
    .video-modal__next p {
        margin:0;
        color:#cbd5e1;
        font-size:14px;
        line-height:1.6;
    }
    .video-modal__next-actions {
        display:flex;
        flex-wrap:wrap;
        gap:10px;
    }
    .video-modal__next-actions button {
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:10px 14px;
        border:none;
        border-radius:999px;
        font-size:13px;
        font-weight:800;
        cursor:pointer;
    }
    .video-modal__next-primary {
        background:#2563eb;
        color:#fff;
    }
    .video-modal__next-secondary {
        background:rgba(255,255,255,0.08);
        color:#f8fafc;
    }
    .video-modal__meta {
        display:grid;
        gap:8px;
        padding:4px 4px 2px;
    }
    .video-modal__meta strong {
        font-size:26px;
        line-height:1.08;
        letter-spacing:-0.03em;
    }
    .video-modal__meta p {
        margin:0;
        color:#cbd5e1;
        font-size:16px;
        line-height:1.68;
    }
    .video-modal__actions {
        display:flex;
        flex-wrap:wrap;
        gap:10px;
        padding:0 4px 4px;
    }
    .video-toast {
        position:fixed;
        right:18px;
        bottom:18px;
        padding:12px 14px;
        border-radius:14px;
        background:rgba(15,23,42,0.96);
        color:#f8fafc;
        font-size:13px;
        font-weight:700;
        box-shadow:0 20px 44px rgba(2,6,23,.24);
        opacity:0;
        pointer-events:none;
        transform:translateY(10px);
        transition:opacity .18s ease, transform .18s ease;
        z-index:420;
    }
    .video-toast.is-visible {
        opacity:1;
        transform:translateY(0);
    }
    @media (max-width: 960px) {
        .video-watch-page {
            padding:16px;
        }
        .video-watch-page__title {
            font-size:clamp(23px, 5.6vw, 32px);
        }
        .videos-list { grid-template-columns:1fr; }
        .video-placeholder {
            padding: 30px;
        }
        .video-placeholder h2 {
            font-size: 32px;
        }
    }
    @media (max-width: 720px) {
        .videos-shell {
            padding-top: 28px;
        }
        .videos-shell.is-watch-page {
            width:100vw;
            max-width:100vw;
            margin:0;
            padding:10px 12px 48px;
            gap: 14px;
        }
        .videos-intro p {
            font-size: 16px;
        }
        .video-watch-page {
            padding:12px;
            width:calc(100vw - 24px);
            max-width:calc(100vw - 24px);
            border-radius:22px;
            gap:12px;
        }
        .video-watch-page__frame {
            border-radius:16px;
        }
        .video-watch-page__player {
            max-height:none;
        }
        .video-watch-page__title {
            font-size:clamp(21px, 6vw, 24px);
        }
        .video-watch-page__title-row {
            width:calc(100vw - 76px);
            max-width:calc(100vw - 76px);
        }
        .video-watch-page__heading-actions .video-menu__toggle {
            width:34px;
            height:34px;
        }
        .video-watch-page__details {
            padding:16px;
            border-radius:18px;
        }
        .video-watch-page__details-content {
            max-height:176px;
        }
        .video-watch-page__details-lead {
            font-size:15px;
        }
        .video-watch-page__details-body p {
            font-size:14px;
        }
        .video-stage {
            border-radius: 20px;
        }
        .video-preview__play {
            width:64px;
            height:64px;
        }
        .video-header strong {
            font-size: 23px;
        }
        .videos-meta {
            gap: 7px 13px;
            font-size:12px;
        }
        .video-caption strong {
            font-size: 20px;
        }
        .video-caption p {
            font-size: 15px;
            line-height: 1.65;
        }
        .video-modal {
            padding:16px;
        }
        .video-modal__dialog {
            border-radius:22px;
            padding:14px;
        }
        .video-modal__close {
            top:10px;
            right:10px;
            width:40px;
            height:40px;
            font-size:22px;
        }
    }
</style>

<div class="videos-shell<?= is_array($selectedVideo) ? ' is-watch-page' : '' ?>">
    <?php if (! is_array($selectedVideo)): ?>
        <section class="videos-intro">
            <p>See the product doing real work. These clips show the companion plugin and the local app itself so a company can see how CreditSoft actually behaves before buying it.</p>
        </section>
    <?php endif; ?>

    <?php if (is_array($selectedVideo) && ($selectedVideo['has_video'] ?? false)): ?>
        <section class="video-watch-page">
            <div class="video-watch-page__frame">
                <video
                    class="video-watch-page__player"
                    id="selectedVideoPlayer"
                    data-selected-video-player
                    data-video-slug="<?= htmlspecialchars((string) $selectedVideo['slug'], ENT_QUOTES, 'UTF-8') ?>"
                    controls
                    preload="metadata"
                    playsinline
                    <?= ($selectedVideo['poster'] ?? '') !== '' ? 'poster="' . htmlspecialchars((string) $selectedVideo['poster'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                >
                    <source src="<?= htmlspecialchars((string) $selectedVideo['url'], ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
                </video>
            </div>
            <div class="videos-meta video-watch-page__stats">
                <span data-video-meta="duration" data-video-slug="<?= htmlspecialchars((string) $selectedVideo['slug'], ENT_QUOTES, 'UTF-8') ?>"><i class="fa-regular fa-clock"></i><span class="videos-meta__value"><?= htmlspecialchars((string) $selectedVideo['duration'], ENT_QUOTES, 'UTF-8') ?></span></span>
                <span data-video-meta="resolution" data-video-slug="<?= htmlspecialchars((string) $selectedVideo['slug'], ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-video"></i><span class="videos-meta__value"><?= htmlspecialchars((string) $selectedVideo['resolution'], ENT_QUOTES, 'UTF-8') ?></span></span>
                <?php if (($selectedVideo['size'] ?? '') !== ''): ?>
                    <span data-video-meta="size" data-video-slug="<?= htmlspecialchars((string) $selectedVideo['slug'], ENT_QUOTES, 'UTF-8') ?>"><i class="fa-regular fa-hard-drive"></i><span class="videos-meta__value"><?= htmlspecialchars((string) $selectedVideo['size'], ENT_QUOTES, 'UTF-8') ?></span></span>
                <?php endif; ?>
                <span data-view-label="<?= htmlspecialchars((string) $selectedVideo['slug'], ENT_QUOTES, 'UTF-8') ?>"<?= (int) ($selectedVideo['views'] ?? 0) < 1 ? ' class="is-hidden-view"' : '' ?>><i class="fa-regular fa-eye"></i><?= htmlspecialchars((string) $selectedVideo['views_label'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="video-watch-page__body">
                <div class="video-watch-page__heading">
                    <div class="video-watch-page__heading-copy">
                        <div class="video-watch-page__title-row">
                            <h2 class="video-watch-page__title"><?= htmlspecialchars((string) $selectedVideo['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                            <div class="video-watch-page__heading-actions">
                                <div class="video-menu" data-video-menu>
                                    <button
                                        type="button"
                                        class="video-menu__toggle"
                                        data-video-menu-toggle
                                        aria-haspopup="true"
                                        aria-expanded="false"
                                        aria-label="Open video actions"
                                        title="Video actions"
                                    ><span class="video-menu__dots" aria-hidden="true">&#8942;</span></button>
                                    <div class="video-menu__panel" role="menu">
                                        <a
                                            class="video-menu__item"
                                            href="/videos"
                                        ><i class="fa-solid fa-film"></i><span>All videos</span></a>
                                        <a
                                            class="video-menu__item"
                                            href="<?= htmlspecialchars((string) ($selectedVideo['facebook_share_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            target="_blank"
                                            rel="noopener"
                                        ><i class="fa-brands fa-facebook-f"></i><span>Share on Facebook</span></a>
                                        <button
                                            type="button"
                                            class="video-menu__item"
                                            data-copy-share="<?= htmlspecialchars((string) $selectedVideo['share_url'], ENT_QUOTES, 'UTF-8') ?>"
                                        ><i class="fa-solid fa-link"></i><span>Copy share link</span></button>
                                        <a
                                            class="video-menu__item"
                                            href="<?= htmlspecialchars((string) $selectedVideo['url'], ENT_QUOTES, 'UTF-8') ?>"
                                            target="_blank"
                                            rel="noopener"
                                        ><i class="fa-solid fa-file-video"></i><span>Open video file</span></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="video-watch-page__details" data-watch-description>
                    <div class="video-watch-page__details-content" data-watch-description-content>
                        <?php if (($selectedVideo['published_label'] ?? '') !== ''): ?>
                            <div class="video-watch-page__details-topline">
                                <time datetime="<?= htmlspecialchars((string) ($selectedVideo['published_iso'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-regular fa-calendar"></i>Published <?= htmlspecialchars((string) $selectedVideo['published_label'], ENT_QUOTES, 'UTF-8') ?></time>
                            </div>
                        <?php endif; ?>
                        <p class="video-watch-page__details-lead"><?= htmlspecialchars((string) $selectedVideo['description'], ENT_QUOTES, 'UTF-8') ?></p>
                        <div class="video-watch-page__details-body video-watch-page__caption">
                            <strong>About this video</strong>
                            <?php foreach (($selectedVideo['caption_lines'] ?? []) as $line): ?>
                                <p><?= htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="button" class="video-watch-page__details-toggle" data-watch-description-toggle aria-expanded="false">More</button>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php foreach ($video_sections as $sectionTitle => $section): ?>
        <section class="videos-section">
            <div class="videos-section__header">
                <strong><?= htmlspecialchars($sectionTitle, ENT_QUOTES, 'UTF-8') ?></strong>
                <?php if (($section['note'] ?? '') !== ''): ?>
                    <p><?= htmlspecialchars((string) $section['note'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
            <div class="videos-list">
                <?php foreach ($section['videos'] as $video): ?>
                    <section class="video-stage">
                        <?php if ($video['has_video']): ?>
                            <div
                                class="video-preview"
                                data-video-slug="<?= htmlspecialchars($video['slug'], ENT_QUOTES, 'UTF-8') ?>"
                                data-video-share-url="<?= htmlspecialchars($video['share_url'], ENT_QUOTES, 'UTF-8') ?>"
                                data-video-src="<?= htmlspecialchars($video['url'], ENT_QUOTES, 'UTF-8') ?>"
                                data-video-poster="<?= htmlspecialchars($video['poster'], ENT_QUOTES, 'UTF-8') ?>"
                                data-video-title="<?= htmlspecialchars($video['caption_title'], ENT_QUOTES, 'UTF-8') ?>"
                                data-video-description="<?= htmlspecialchars($video['description'], ENT_QUOTES, 'UTF-8') ?>"
                                data-video-caption="<?= htmlspecialchars(implode('||', $video['caption_lines']), ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <a
                                    href="<?= htmlspecialchars($video['share_url'], ENT_QUOTES, 'UTF-8') ?>"
                                    class="video-preview__poster"
                                    data-video-open-link="<?= htmlspecialchars($video['slug'], ENT_QUOTES, 'UTF-8') ?>"
                                    aria-label="Open <?= htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8') ?> page"
                                >
                                    <?php if ($video['poster'] !== ''): ?>
                                        <img src="<?= htmlspecialchars($video['poster'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                    <?php else: ?>
                                        <div class="video-placeholder">
                                            <h2><?= htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                                            <p><?= htmlspecialchars($video['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <span class="video-preview__play" aria-hidden="true"><i class="fa-solid fa-play"></i></span>
                                </a>
                                <button type="button" class="video-preview__sound" data-video-sound aria-label="Unmute preview" title="Unmute preview"><i class="fa-solid fa-volume-xmark"></i></button>
                                <video class="video-inline-player" muted loop playsinline preload="metadata"></video>
                            </div>
                        <?php else: ?>
                            <div class="video-placeholder">
                                <h2>Video file not found yet.</h2>
                                <p>Drop the MP4 into the website assets folder and this player card will go live without needing a layout change.</p>
                            </div>
                        <?php endif; ?>

                        <div class="video-header">
                            <div class="video-header__top">
                                <strong><?= htmlspecialchars($video['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <div class="video-toolbar">
                                    <div class="video-toolbar__actions">
                                        <div class="video-menu" data-video-menu>
                                            <button
                                                type="button"
                                                class="video-menu__toggle"
                                                data-video-menu-toggle
                                                aria-haspopup="true"
                                                aria-expanded="false"
                                                aria-label="Open video actions"
                                                title="Video actions"
                                            ><span class="video-menu__dots" aria-hidden="true">&#8942;</span></button>
                                            <div class="video-menu__panel" role="menu">
                                                <button
                                                    type="button"
                                                    class="video-menu__item"
                                                    data-video-open="<?= htmlspecialchars($video['slug'], ENT_QUOTES, 'UTF-8') ?>"
                                                ><i class="fa-solid fa-expand"></i><span>Open larger player</span></button>
                                                <button
                                                    type="button"
                                                    class="video-menu__item"
                                                    data-copy-share="<?= htmlspecialchars($video['share_url'], ENT_QUOTES, 'UTF-8') ?>"
                                                ><i class="fa-solid fa-link"></i><span>Copy share link</span></button>
                                                <a
                                                    class="video-menu__item"
                                                    href="<?= htmlspecialchars((string) ($video['facebook_share_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    target="_blank"
                                                    rel="noopener"
                                                ><i class="fa-brands fa-facebook-f"></i><span>Share on Facebook</span></a>
                                                <a
                                                    class="video-menu__item"
                                                    href="<?= htmlspecialchars($video['share_url'], ENT_QUOTES, 'UTF-8') ?>"
                                                ><i class="fa-solid fa-share-nodes"></i><span>Open share page</span></a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p><?= htmlspecialchars($video['description'], ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="videos-meta">
                                <span data-video-meta="duration" data-video-slug="<?= htmlspecialchars($video['slug'], ENT_QUOTES, 'UTF-8') ?>"><i class="fa-regular fa-clock"></i><span class="videos-meta__value"><?= htmlspecialchars($video['duration'], ENT_QUOTES, 'UTF-8') ?></span></span>
                                <span data-video-meta="resolution" data-video-slug="<?= htmlspecialchars($video['slug'], ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-video"></i><span class="videos-meta__value"><?= htmlspecialchars($video['resolution'], ENT_QUOTES, 'UTF-8') ?></span></span>
                                <?php if ($video['size'] !== ''): ?>
                                    <span data-video-meta="size" data-video-slug="<?= htmlspecialchars($video['slug'], ENT_QUOTES, 'UTF-8') ?>"><i class="fa-regular fa-hard-drive"></i><span class="videos-meta__value"><?= htmlspecialchars($video['size'], ENT_QUOTES, 'UTF-8') ?></span></span>
                                <?php endif; ?>
                                <span data-view-label="<?= htmlspecialchars($video['slug'], ENT_QUOTES, 'UTF-8') ?>"<?= (int) $video['views'] < 1 ? ' class="is-hidden-view"' : '' ?>><i class="fa-regular fa-eye"></i><?= htmlspecialchars($video['views_label'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<div class="video-modal" id="videoLightbox" aria-hidden="true">
    <div class="video-modal__dialog" role="dialog" aria-modal="true" aria-label="Video playback preview">
        <button type="button" class="video-modal__close" id="videoLightboxClose" aria-label="Close video preview">&times;</button>
        <div class="video-modal__frame">
            <video id="videoLightboxPlayer" controls preload="metadata" playsinline></video>
            <div class="video-modal__next" id="videoLightboxNext">
                <small>Next up</small>
                <strong id="videoLightboxNextTitle"></strong>
                <p id="videoLightboxNextCopy">Playing next in 5 seconds.</p>
                <div class="video-modal__next-actions">
                    <button type="button" class="video-modal__next-primary" id="videoLightboxNextNow"><i class="fa-solid fa-forward-step"></i>Play now</button>
                    <button type="button" class="video-modal__next-secondary" id="videoLightboxReplay"><i class="fa-solid fa-rotate-right"></i>Replay this one</button>
                </div>
            </div>
        </div>
        <div class="video-modal__meta">
            <strong id="videoLightboxTitle"></strong>
            <div class="video-caption" id="videoLightboxCaption"></div>
        </div>
        <div class="video-modal__actions">
            <button type="button" class="video-action is-primary" id="videoLightboxCopy"><i class="fa-solid fa-link"></i>Copy share link</button>
            <a href="/videos" class="video-action" id="videoLightboxOpenPage"><i class="fa-solid fa-film"></i>Open videos page</a>
        </div>
    </div>
</div>
<div class="video-toast" id="videoToast" aria-live="polite"></div>

<script>
(() => {
    const modal = document.getElementById('videoLightbox');
    const player = document.getElementById('videoLightboxPlayer');
    const title = document.getElementById('videoLightboxTitle');
    const caption = document.getElementById('videoLightboxCaption');
    const closeButton = document.getElementById('videoLightboxClose');
    const copyButton = document.getElementById('videoLightboxCopy');
    const openPageLink = document.getElementById('videoLightboxOpenPage');
    const nextPanel = document.getElementById('videoLightboxNext');
    const nextTitle = document.getElementById('videoLightboxNextTitle');
    const nextCopy = document.getElementById('videoLightboxNextCopy');
    const nextNowButton = document.getElementById('videoLightboxNextNow');
    const replayButton = document.getElementById('videoLightboxReplay');
    const toast = document.getElementById('videoToast');
    const selectedPagePlayer = document.querySelector('[data-selected-video-player]');
    const watchDescription = document.querySelector('[data-watch-description]');
    const watchDescriptionContent = document.querySelector('[data-watch-description-content]');
    const watchDescriptionToggle = document.querySelector('[data-watch-description-toggle]');
    const cards = document.querySelectorAll('.video-preview[data-video-src]');
    const coverLinks = document.querySelectorAll('.video-preview__poster[data-video-open-link]');
    const copyButtons = document.querySelectorAll('[data-copy-share]');
    const openButtons = document.querySelectorAll('[data-video-open]');
    const soundButtons = document.querySelectorAll('[data-video-sound]');
    const menuToggles = document.querySelectorAll('[data-video-menu-toggle]');
    const menus = document.querySelectorAll('[data-video-menu]');
    const metadataNodes = document.querySelectorAll('[data-video-meta][data-video-slug]');

    if (watchDescription instanceof HTMLElement && watchDescriptionContent instanceof HTMLElement && watchDescriptionToggle instanceof HTMLButtonElement) {
        const syncWatchDescription = () => {
            const expanded = watchDescription.classList.contains('is-expanded');
            const needsToggle = expanded || (watchDescriptionContent.scrollHeight - watchDescriptionContent.clientHeight) > 6;
            watchDescriptionToggle.hidden = !needsToggle;
            watchDescriptionToggle.textContent = expanded ? 'Less' : 'More';
            watchDescriptionToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        };

        watchDescriptionToggle.addEventListener('click', () => {
            watchDescription.classList.toggle('is-expanded');
            syncWatchDescription();
        });

        window.addEventListener('resize', syncWatchDescription);
        window.requestAnimationFrame(syncWatchDescription);
    }

    if (!modal || !player || !title || !caption || !closeButton || !copyButton || !openPageLink || !nextPanel || !nextTitle || !nextCopy || !nextNowButton || !replayButton || !toast) {
        return;
    }

    let activeShareUrl = '';
    let activeSlug = '';
    let activeIndex = -1;
    let viewTrackedForCurrentOpen = false;
    let nextCountdown = 5;
    let nextCountdownInterval = null;
    let nextCountdownTimeout = null;

    const orderedCards = Array.from(cards);
    const triggerMap = new Map();
    const metadataBySlug = new Map();
    cards.forEach((card) => {
        triggerMap.set(card.dataset.videoSlug || '', card);
    });
    metadataNodes.forEach((node) => {
        const slug = node.dataset.videoSlug || '';
        if (!slug) {
            return;
        }
        if (!metadataBySlug.has(slug)) {
            metadataBySlug.set(slug, []);
        }
        metadataBySlug.get(slug).push(node);
    });

    const formatDuration = (seconds) => {
        if (!Number.isFinite(seconds) || seconds <= 0) {
            return '';
        }
        if (seconds < 60) {
            return `${seconds.toFixed(1).replace(/\.0$/, '')}s`;
        }
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.round(seconds % 60);
        return `${minutes}m ${String(remainingSeconds).padStart(2, '0')}s`;
    };

    const formatResolution = (width, height) => {
        if (!Number.isFinite(width) || !Number.isFinite(height) || width < 1 || height < 1) {
            return '';
        }
        return `${Math.round(width)}x${Math.round(height)}`;
    };

    const formatSize = (bytes) => {
        if (!Number.isFinite(bytes) || bytes <= 0) {
            return '';
        }
        if (bytes >= 1073741824) {
            return `${(bytes / 1073741824).toFixed(2).replace(/\.00$/, '')} GB`;
        }
        return `${(bytes / 1048576).toFixed(1).replace(/\.0$/, '')} MB`;
    };

    const updateMetadataNodes = (slug, meta) => {
        const nodes = metadataBySlug.get(slug) || [];
        nodes.forEach((node) => {
            const kind = node.dataset.videoMeta || '';
            const valueNode = node.querySelector('.videos-meta__value');
            if (!valueNode) {
                return;
            }
            if (kind === 'duration' && meta.duration) {
                valueNode.textContent = meta.duration;
            }
            if (kind === 'resolution' && meta.resolution) {
                valueNode.textContent = meta.resolution;
            }
            if (kind === 'size' && meta.size) {
                valueNode.textContent = meta.size;
            }
        });
    };

    const getVideoSource = (slug) => {
        if (selectedPagePlayer instanceof HTMLVideoElement && selectedPagePlayer.dataset.videoSlug === slug) {
            const selectedSource = selectedPagePlayer.currentSrc
                || selectedPagePlayer.querySelector('source')?.src
                || '';
            if (selectedSource) {
                return selectedSource;
            }
        }
        const card = triggerMap.get(slug);
        return card?.dataset.videoSrc || '';
    };

    const loadVideoMeta = async (src) => {
        const headPromise = fetch(src, { method: 'HEAD' })
            .then((response) => {
                const length = response.headers.get('content-length');
                return length ? Number(length) : 0;
            })
            .catch(() => 0);

        const mediaPromise = new Promise((resolve, reject) => {
            const probe = document.createElement('video');
            probe.preload = 'metadata';
            probe.muted = true;
            probe.playsInline = true;
            const cleanup = () => {
                probe.onloadedmetadata = null;
                probe.onerror = null;
                probe.removeAttribute('src');
                probe.load();
            };
            probe.onloadedmetadata = () => {
                const payload = {
                    duration: probe.duration,
                    width: probe.videoWidth,
                    height: probe.videoHeight,
                };
                cleanup();
                resolve(payload);
            };
            probe.onerror = () => {
                cleanup();
                reject(new Error('metadata load failed'));
            };
            probe.src = src;
            probe.load();
        }).catch(() => ({}));

        const [sizeBytes, media] = await Promise.all([headPromise, mediaPromise]);
        return {
            duration: formatDuration(Number(media.duration || 0)),
            resolution: formatResolution(Number(media.width || 0), Number(media.height || 0)),
            size: formatSize(Number(sizeBytes || 0)),
        };
    };

    const syncVideoMetadata = () => {
        metadataBySlug.forEach((_, slug) => {
            const src = getVideoSource(slug);
            if (!src) {
                return;
            }
            loadVideoMeta(src)
                .then((meta) => {
                    updateMetadataNodes(slug, meta);
                })
                .catch(() => {});
        });
    };

    const clearNextCountdown = () => {
        window.clearInterval(nextCountdownInterval);
        window.clearTimeout(nextCountdownTimeout);
        nextCountdownInterval = null;
        nextCountdownTimeout = null;
        nextPanel.classList.remove('is-visible');
        nextTitle.textContent = '';
        nextCopy.textContent = 'Playing next in 5 seconds.';
    };

    const closeMenus = (exceptMenu = null) => {
        menus.forEach((menu) => {
            if (exceptMenu && menu === exceptMenu) {
                return;
            }
            menu.classList.remove('is-open');
            const toggle = menu.querySelector('[data-video-menu-toggle]');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    };

    const showToast = (message) => {
        toast.textContent = message;
        toast.classList.add('is-visible');
        window.clearTimeout(showToast._timer);
        showToast._timer = window.setTimeout(() => {
            toast.classList.remove('is-visible');
        }, 2200);
    };

    const copyShareLink = async (url) => {
        try {
            await navigator.clipboard.writeText(url);
            showToast('Share link copied');
        } catch (error) {
            showToast('Could not copy link');
        }
    };

    const updateViewLabels = (slug, views) => {
        const numericViews = Number(views);
        let label = `${numericViews.toLocaleString()} ${numericViews === 1 ? 'view' : 'views'}`;

        if (numericViews >= 1000000) {
            label = `${(numericViews / 1000000).toFixed(1).replace(/\\.0$/, '')}M views`;
        } else if (numericViews >= 1000) {
            label = `${(numericViews / 1000).toFixed(1).replace(/\\.0$/, '')}K views`;
        }

        document.querySelectorAll(`[data-view-label="${slug}"]`).forEach((node) => {
            node.classList.remove('is-hidden-view');
            node.innerHTML = `<i class="fa-regular fa-eye"></i>${label}`;
        });
    };

    const trackView = (slug) => {
        const storageKey = `creditsoft-video-view:${slug}`;
        const now = Date.now();
        const lastTracked = Number(window.localStorage.getItem(storageKey) || '0');

        if (lastTracked && (now - lastTracked) < (6 * 60 * 60 * 1000)) {
            return;
        }

        window.localStorage.setItem(storageKey, String(now));

        fetch('/api/video-event.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                action: 'view',
                slug,
            }),
        })
            .then((response) => response.json().catch(() => ({})))
            .then((payload) => {
                if (payload && payload.success && typeof payload.views !== 'undefined') {
                    updateViewLabels(slug, payload.views);
                }
            })
            .catch(() => {});
    };

    const closeModal = () => {
        clearNextCountdown();
        player.pause();
        player.removeAttribute('src');
        player.removeAttribute('poster');
        player.load();
        title.textContent = '';
        caption.innerHTML = '';
        activeShareUrl = '';
        activeSlug = '';
        activeIndex = -1;
        viewTrackedForCurrentOpen = false;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('lightbox-open');
    };

    const scheduleNextVideo = () => {
        clearNextCountdown();
        if (activeIndex < 0 || orderedCards.length === 0) {
            return;
        }

        const nextIndex = orderedCards.length > 1
            ? (activeIndex + 1) % orderedCards.length
            : activeIndex;
        const nextCard = orderedCards[nextIndex];
        const nextVideoTitle = nextCard?.dataset.videoTitle || nextCard?.closest('.video-stage')?.querySelector('.video-header strong')?.textContent || 'Next video';

        nextCountdown = 5;
        nextTitle.textContent = nextVideoTitle;
        nextCopy.textContent = `Playing next in ${nextCountdown} seconds.`;
        nextPanel.classList.add('is-visible');

        nextCountdownInterval = window.setInterval(() => {
            nextCountdown -= 1;
            if (nextCountdown > 0) {
                nextCopy.textContent = `Playing next in ${nextCountdown} seconds.`;
            }
        }, 1000);

        nextCountdownTimeout = window.setTimeout(() => {
            clearNextCountdown();
            if (nextCard) {
                openVideo(nextCard, true);
            }
        }, 5000);
    };

    const pauseInlinePlayers = (keepCard = null) => {
        cards.forEach((card) => {
            if (keepCard && card === keepCard) {
                return;
            }
            stopPreview(card);
        });
    };

    const stopPreview = (card) => {
        const inlinePlayer = card?.querySelector('.video-inline-player');
        const soundButton = card?.querySelector('[data-video-sound]');
        if (!card || !inlinePlayer) {
            return;
        }
        inlinePlayer.pause();
        inlinePlayer.currentTime = 0;
        inlinePlayer.muted = true;
        card.classList.remove('is-previewing');
        if (soundButton) {
            soundButton.innerHTML = '<i class="fa-solid fa-volume-xmark"></i>';
            soundButton.setAttribute('aria-label', 'Unmute preview');
            soundButton.setAttribute('title', 'Unmute preview');
        }
    };

    const trackViewOnce = (slug) => {
        if (!slug) {
            return;
        }
        trackView(slug);
    };

    const startPreview = (card) => {
        if (!card) {
            return;
        }

        pauseInlinePlayers(card);
        const inlinePlayer = card.querySelector('.video-inline-player');
        const videoSrc = card.dataset.videoSrc || '';

        if (!inlinePlayer || videoSrc === '') {
            return;
        }

        if (inlinePlayer.getAttribute('src') !== videoSrc) {
            inlinePlayer.src = videoSrc;
            inlinePlayer.load();
        }
        inlinePlayer.muted = true;
        card.classList.add('is-previewing');
        inlinePlayer.play().catch(() => {});
    };

    const openVideo = (card, autoplay = true) => {
        clearNextCountdown();
        closeMenus();
        pauseInlinePlayers();
        const videoSrc = card.dataset.videoSrc || '';
        const poster = card.dataset.videoPoster || '';
        const videoTitle = card.dataset.videoTitle || '';
        const slug = card.dataset.videoSlug || '';
        const shareUrl = card.dataset.videoShareUrl || `${window.location.origin}/videos`;
        const lines = (card.dataset.videoCaption || '')
            .split('||')
            .map((line) => line.trim())
            .filter(Boolean);

        if (videoSrc === '') {
            return;
        }

        player.src = videoSrc;
        player.muted = true;
        if (poster !== '') {
            player.poster = poster;
        }
        title.textContent = videoTitle;
        caption.innerHTML = '';
        lines.forEach((line) => {
            const paragraph = document.createElement('p');
            paragraph.textContent = line;
            caption.appendChild(paragraph);
        });
        activeShareUrl = shareUrl;
        activeSlug = slug;
        activeIndex = orderedCards.findIndex((node) => node === card);
        viewTrackedForCurrentOpen = false;
        openPageLink.href = shareUrl;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('lightbox-open');
        player.load();
        const nextUrl = new URL(window.location.href);
        nextUrl.searchParams.set('watch', slug);
        window.history.replaceState({}, '', nextUrl);

        if (autoplay) {
            player.play().catch(() => {});
        }
    };

    cards.forEach((card) => {
        card.addEventListener('mouseenter', () => {
            if (window.matchMedia('(hover: hover)').matches) {
                startPreview(card);
            }
        });
        card.addEventListener('mouseleave', () => {
            stopPreview(card);
        });
        card.addEventListener('focusin', () => {
            if (window.matchMedia('(hover: hover)').matches) {
                startPreview(card);
            }
        });
        card.addEventListener('focusout', (event) => {
            if (!card.contains(event.relatedTarget)) {
                stopPreview(card);
            }
        });
    });

    coverLinks.forEach((link) => {
        link.addEventListener('click', () => {
            const card = link.closest('.video-preview');
            if (card) {
                pauseInlinePlayers();
                closeMenus();
            }
        });
    });

    soundButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const card = button.closest('.video-preview');
            const inlinePlayer = card?.querySelector('.video-inline-player');
            if (!card || !inlinePlayer) {
                return;
            }
            if (!card.classList.contains('is-previewing')) {
                startPreview(card);
            }
            inlinePlayer.muted = !inlinePlayer.muted;
            const muted = inlinePlayer.muted;
            button.innerHTML = muted
                ? '<i class="fa-solid fa-volume-xmark"></i>'
                : '<i class="fa-solid fa-volume-high"></i>';
            button.setAttribute('aria-label', muted ? 'Unmute preview' : 'Mute preview');
            button.setAttribute('title', muted ? 'Unmute preview' : 'Mute preview');
        });
    });

    menuToggles.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const menu = button.closest('[data-video-menu]');
            if (!menu) {
                return;
            }
            const willOpen = !menu.classList.contains('is-open');
            closeMenus(menu);
            menu.classList.toggle('is-open', willOpen);
            button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    });

    openButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const slug = button.dataset.videoOpen || '';
            const card = triggerMap.get(slug);
            if (card) {
                openVideo(card, true);
            }
        });
    });

    copyButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const url = button.dataset.copyShare || `${window.location.origin}/videos`;
            closeMenus();
            copyShareLink(url);
        });
    });

    copyButton.addEventListener('click', () => {
        copyShareLink(activeShareUrl || window.location.href);
    });

    nextNowButton.addEventListener('click', () => {
        if (activeIndex < 0 || orderedCards.length === 0) {
            return;
        }
        const nextIndex = orderedCards.length > 1
            ? (activeIndex + 1) % orderedCards.length
            : activeIndex;
        const nextTrigger = orderedCards[nextIndex];
        clearNextCountdown();
        if (nextTrigger) {
            openVideo(nextTrigger, true);
        }
    });

    replayButton.addEventListener('click', () => {
        clearNextCountdown();
        player.currentTime = 0;
        player.play().catch(() => {});
    });

    player.addEventListener('timeupdate', () => {
        if (!activeSlug || viewTrackedForCurrentOpen) {
            return;
        }

        if (player.currentTime >= 8) {
            viewTrackedForCurrentOpen = true;
            trackView(activeSlug);
        }
    });

    player.addEventListener('play', () => {
        clearNextCountdown();
    });

    player.addEventListener('ended', () => {
        scheduleNextVideo();
    });

    if (selectedPagePlayer instanceof HTMLVideoElement) {
        let selectedPageTracked = false;
        const selectedPageSlug = selectedPagePlayer.dataset.videoSlug || '';
        selectedPagePlayer.addEventListener('timeupdate', () => {
            if (!selectedPageTracked && selectedPageSlug && selectedPagePlayer.currentTime >= 8) {
                selectedPageTracked = true;
                trackView(selectedPageSlug);
            }
        });
    }

    cards.forEach((card) => {
        const previewPlayer = card.querySelector('.video-inline-player');
        const slug = card.dataset.videoSlug || '';
        if (!previewPlayer || !slug) {
            return;
        }
        previewPlayer.addEventListener('timeupdate', () => {
            if (previewPlayer.currentTime >= 8) {
                trackViewOnce(slug);
            }
        });
    });

    closeButton.addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
        if (event.key === 'Escape') {
            closeMenus();
        }
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-video-menu]')) {
            closeMenus();
        }
    });

    syncVideoMetadata();

})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
