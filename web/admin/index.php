<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/site-content-config.php';
require_once dirname(__DIR__) . '/site-tracking-config.php';
require_once dirname(__DIR__) . '/site-seo-config.php';
require_once dirname(__DIR__) . '/meta-social-manager.php';
require_once __DIR__ . '/zelle-payments.php';

$admin = cs_site_admin_require_login();
$panel = trim((string) ($_GET['panel'] ?? 'dashboard'));
$validPanels = ['dashboard', 'plans', 'content', 'licenses', 'payments', 'customers', 'leads', 'assessments', 'seo', 'social', 'ops'];

if (! in_array($panel, $validPanels, true)) {
    $panel = 'dashboard';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedPanel = trim((string) ($_POST['panel'] ?? $panel));
    $redirectPanel = in_array($postedPanel, $validPanels, true) ? $postedPanel : $panel;

    if (! cs_site_admin_verify_csrf($_POST['csrf'] ?? null)) {
        cs_site_admin_flash('error', 'The admin form expired. Please try again.');
        cs_site_admin_redirect(cs_site_admin_url('/', ['panel' => $redirectPanel]));
    }

    $action = trim((string) ($_POST['action'] ?? ''));
    $isAutosaveRequest = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        && (string) ($_POST['autosave'] ?? '') === '1';

    if ($action === 'save_pricing') {
        $input = [
            'note' => $_POST['note'] ?? '',
            'plans' => $_POST['plans'] ?? [],
        ];

        $saved = creditsoft_site_pricing_save(is_array($input) ? $input : []);

        if ($saved) {
            if ($isAutosaveRequest) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Pricing saved']);
                exit;
            }
            cs_site_admin_flash('success', 'Plans updated. Public pricing and the site checkout lane will read the new values.');
        } else {
            if ($isAutosaveRequest) {
                http_response_code(422);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Pricing could not be saved']);
                exit;
            }
            cs_site_admin_flash('error', 'The pricing changes could not be saved yet.');
        }

        cs_site_admin_redirect(cs_site_admin_url('/', ['panel' => 'plans']));
    }

    if ($action === 'save_content') {
        $input = [
            'home' => $_POST['home'] ?? [],
            'pricing' => $_POST['pricing'] ?? [],
        ];

        $saved = creditsoft_site_content_save(is_array($input) ? $input : []);

        if ($saved) {
            if ($isAutosaveRequest) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Content saved']);
                exit;
            }
            cs_site_admin_flash('success', 'Site copy updated. Homepage and pricing now read the new content.');
        } else {
            if ($isAutosaveRequest) {
                http_response_code(422);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Content could not be saved']);
                exit;
            }
            cs_site_admin_flash('error', 'The site copy changes could not be saved yet.');
        }

        cs_site_admin_redirect(cs_site_admin_url('/', ['panel' => 'content']));
    }

    if ($action === 'save_tracking') {
        $input = [
            'google_measurement_id' => $_POST['google_measurement_id'] ?? '',
            'meta_pixel_id' => $_POST['meta_pixel_id'] ?? '',
            'meta_app_id' => $_POST['meta_app_id'] ?? '',
            'meta_business_id' => $_POST['meta_business_id'] ?? '',
            'meta_management_token' => $_POST['meta_management_token'] ?? '',
            'meta_webhook_verify_token' => $_POST['meta_webhook_verify_token'] ?? '',
            'facebook_page_id' => $_POST['facebook_page_id'] ?? '',
            'facebook_username' => $_POST['facebook_username'] ?? '',
            'instagram_business_id' => $_POST['instagram_business_id'] ?? '',
            'instagram_username' => $_POST['instagram_username'] ?? '',
            'threads_profile_id' => $_POST['threads_profile_id'] ?? '',
            'threads_username' => $_POST['threads_username'] ?? '',
            'x_username' => $_POST['x_username'] ?? '',
            'meta_ad_account_id' => $_POST['meta_ad_account_id'] ?? '',
            'lead_form_name' => $_POST['lead_form_name'] ?? '',
            'campaign_objective' => $_POST['campaign_objective'] ?? '',
            'meta_capi_token' => $_POST['meta_capi_token'] ?? '',
            'meta_capi_test_event_code' => $_POST['meta_capi_test_event_code'] ?? '',
            'weekly_budget' => $_POST['weekly_budget'] ?? '',
            'daily_cap' => $_POST['daily_cap'] ?? '',
            'monthly_cap' => $_POST['monthly_cap'] ?? '',
            'whatsapp_enabled' => $_POST['whatsapp_enabled'] ?? '0',
            'whatsapp_display_number' => $_POST['whatsapp_display_number'] ?? '',
            'whatsapp_phone_number_id' => $_POST['whatsapp_phone_number_id'] ?? '',
            'whatsapp_business_account_id' => $_POST['whatsapp_business_account_id'] ?? '',
            'whatsapp_verify_token' => $_POST['whatsapp_verify_token'] ?? '',
            'whatsapp_default_message' => $_POST['whatsapp_default_message'] ?? '',
        ];

        $saved = creditsoft_site_tracking_save(is_array($input) ? $input : []);

        if ($saved) {
            if ($isAutosaveRequest) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Meta and ops settings saved']);
                exit;
            }
            cs_site_admin_flash('success', 'Tracking and social channel settings updated.');
        } else {
            if ($isAutosaveRequest) {
                http_response_code(422);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Meta and ops settings could not be saved']);
                exit;
            }
            cs_site_admin_flash('error', 'The tracking settings could not be saved yet.');
        }

        cs_site_admin_redirect(cs_site_admin_url('/', ['panel' => 'social']));
    }

    if ($action === 'save_seo') {
        $currentSeo = creditsoft_site_seo_load();
        $mergedSeo = $currentSeo;

        if (array_key_exists('default_og_image', $_POST)) {
            $mergedSeo['default_og_image'] = (string) ($_POST['default_og_image'] ?? '');
        }

        if (is_array($_POST['pages'] ?? null)) {
            foreach ((array) $_POST['pages'] as $slug => $pageData) {
                if (! is_array($pageData)) {
                    continue;
                }

                $slugKey = creditsoft_site_seo_slug_key((string) $slug);
                $existing = is_array($mergedSeo['pages'][$slugKey] ?? null) ? $mergedSeo['pages'][$slugKey] : [];
                $mergedSeo['pages'][$slugKey] = array_merge($existing, $pageData);
            }
        }

        $saved = creditsoft_site_seo_save($mergedSeo);

        if ($saved) {
            if ($isAutosaveRequest) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'SEO saved']);
                exit;
            }
            cs_site_admin_flash('success', 'SEO metadata updated.');
        } else {
            if ($isAutosaveRequest) {
                http_response_code(422);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'SEO could not be saved']);
                exit;
            }
            cs_site_admin_flash('error', 'The SEO settings could not be saved yet.');
        }

        cs_site_admin_redirect(cs_site_admin_url('/', ['panel' => 'seo']));
    }

    if ($action === 'upload_seo_image') {
        $result = creditsoft_site_seo_upload_image((string) ($_POST['page_slug'] ?? ''), $_FILES['seo_image_file'] ?? []);

        if (! empty($result['success'])) {
            cs_site_admin_flash('success', (string) ($result['message'] ?? 'SEO image uploaded.'));
        } else {
            cs_site_admin_flash('error', (string) ($result['message'] ?? 'SEO image could not be uploaded.'));
        }

        cs_site_admin_redirect(cs_site_admin_url('/', ['panel' => 'seo']));
    }

    if ($action === 'discover_meta_channels') {
        $result = creditsoft_meta_social_discover_channels(creditsoft_site_tracking_load());

        if (! empty($result['success'])) {
            $message = 'Meta channel discovery finished.';

            if (! empty($result['instagram_business_id'])) {
                $message .= ' Instagram business ID found: ' . (string) $result['instagram_business_id'];
            } else {
                $message .= ' No Instagram business account came back on the linked Page yet.';
            }

            cs_site_admin_flash('success', $message);
        } else {
            cs_site_admin_flash('error', (string) ($result['error'] ?? 'Meta channel discovery did not finish.'));
        }

        cs_site_admin_redirect(cs_site_admin_url('/', ['panel' => 'social']));
    }

    if ($action === 'sync_meta_leads') {
        $result = creditsoft_meta_social_sync_leads(creditsoft_site_tracking_load());

        if (! empty($result['success'])) {
            cs_site_admin_flash('success', 'Meta lead sync finished. New leads imported this run: ' . (int) ($result['new_imported'] ?? 0) . '.');
        } else {
            cs_site_admin_flash('error', (string) ($result['error'] ?? 'Meta lead sync did not finish.'));
        }

        cs_site_admin_redirect(cs_site_admin_url('/', ['panel' => 'social']));
    }

    if ($action === 'sync_meta_report') {
        $result = creditsoft_meta_social_sync_ad_report(creditsoft_site_tracking_load());

        if (! empty($result['success'])) {
            cs_site_admin_flash('success', 'Meta ad report refreshed. Campaigns read: ' . (int) ($result['campaign_count'] ?? 0) . '.');
        } else {
            cs_site_admin_flash('error', (string) ($result['error'] ?? 'Meta ad reporting could not be refreshed.'));
        }

        cs_site_admin_redirect(cs_site_admin_url('/', ['panel' => 'social']));
    }

    if ($action === 'create_social_post') {
        $created = creditsoft_meta_social_create_post([
            'platform' => $_POST['post_platform'] ?? 'facebook',
            'title' => $_POST['post_title'] ?? '',
            'body' => $_POST['post_body'] ?? '',
            'link' => $_POST['post_link'] ?? '',
            'scheduled_at' => $_POST['post_scheduled_at'] ?? '',
        ]);

        if (! empty($created['success'])) {
            cs_site_admin_flash('success', 'Post draft saved to the queue.');
        } else {
            cs_site_admin_flash('error', (string) ($created['error'] ?? 'Post draft could not be saved.'));
        }

        cs_site_admin_redirect(cs_site_admin_url('/', ['panel' => 'social']));
    }

    if ($action === 'publish_social_post') {
        $result = creditsoft_meta_social_publish_post((string) ($_POST['post_id'] ?? ''), creditsoft_site_tracking_load());

        if (! empty($result['success'])) {
            cs_site_admin_flash('success', 'The post went live on Facebook.');
        } else {
            cs_site_admin_flash('error', (string) ($result['error'] ?? 'CreditSoft could not publish that draft yet.'));
        }

        cs_site_admin_redirect(cs_site_admin_url('/', ['panel' => 'social']));
    }

    if ($action === 'sync_meta_replies') {
        $result = creditsoft_meta_social_sync_replies(creditsoft_site_tracking_load());

        if (! empty($result['success'])) {
            cs_site_admin_flash('success', 'Reply queue refreshed. New open items: ' . (int) ($result['new_items'] ?? 0) . '.');
        } else {
            cs_site_admin_flash('error', (string) ($result['error'] ?? 'Reply sync did not finish.'));
        }

        cs_site_admin_redirect(cs_site_admin_url('/', ['panel' => 'social']));
    }

    if ($action === 'save_reply_draft') {
        $result = creditsoft_meta_social_save_reply_draft(
            (string) ($_POST['reply_id'] ?? ''),
            (string) ($_POST['reply_message'] ?? '')
        );

        if (! empty($result['success'])) {
            cs_site_admin_flash('success', 'Reply draft saved.');
        } else {
            cs_site_admin_flash('error', (string) ($result['error'] ?? 'Reply draft could not be saved.'));
        }

        cs_site_admin_redirect(cs_site_admin_url('/', ['panel' => 'social']));
    }

    if ($action === 'send_social_reply') {
        $result = creditsoft_meta_social_send_reply(
            (string) ($_POST['reply_id'] ?? ''),
            (string) ($_POST['reply_message'] ?? ''),
            creditsoft_site_tracking_load()
        );

        if (! empty($result['success'])) {
            cs_site_admin_flash('success', 'Reply sent to Facebook.');
        } else {
            cs_site_admin_flash('error', (string) ($result['error'] ?? 'CreditSoft could not send the reply yet.'));
        }

        cs_site_admin_redirect(cs_site_admin_url('/', ['panel' => 'social']));
    }

    if ($action === 'close_social_reply') {
        $result = creditsoft_meta_social_mark_reply_done((string) ($_POST['reply_id'] ?? ''));

        if (! empty($result['success'])) {
            cs_site_admin_flash('success', 'Reply thread closed.');
        } else {
            cs_site_admin_flash('error', (string) ($result['error'] ?? 'Reply thread could not be closed.'));
        }

        cs_site_admin_redirect(cs_site_admin_url('/', ['panel' => 'social']));
    }

    if ($action === 'create_license') {
        $created = cs_site_admin_create_license(
            (string) ($_POST['customer_email'] ?? ''),
            (string) ($_POST['plan_key'] ?? ''),
            (string) ($_POST['duration'] ?? 'monthly'),
            (string) ($_POST['customer_name'] ?? '')
        );

        if (! empty($created['success'])) {
            $message = 'License created successfully.';

            if (! empty($created['license_key'])) {
                $message .= ' Key: ' . (string) $created['license_key'];
            }

            if (! empty($created['expires_at'])) {
                $message .= ' Expires: ' . date('M j, Y', strtotime((string) $created['expires_at']));
            }

            cs_site_admin_flash('success', $message);
        } else {
            cs_site_admin_flash('error', (string) ($created['message'] ?? 'Could not create the license yet.'));
        }

        cs_site_admin_redirect(cs_site_admin_url('/', ['panel' => $redirectPanel]));
    }

    if ($action === 'run_zelle_payment_checker') {
        $result = cs_site_zelle_process_inbox(100);

        if (! empty($result['success'])) {
            cs_site_admin_flash(
                'success',
                'Zelle inbox checked. Fetched ' . (int) ($result['fetched'] ?? 0) . ' trusted payment candidate(s). Processed ' . (int) ($result['processed'] ?? 0) . ', balance due ' . (int) ($result['balance_due'] ?? 0) . ', needs review ' . (int) ($result['needs_review'] ?? 0) . ', skipped ' . (int) ($result['skipped'] ?? 0) . ', deleted from inbox ' . (int) ($result['deleted'] ?? 0) . '.'
                . ' License emails attempted ' . (int) ($result['license_email_attempted'] ?? 0) . ', sent ' . (int) ($result['license_email_sent'] ?? 0) . ', failed ' . (int) ($result['license_email_failed'] ?? 0) . '.'
            );
        } else {
            cs_site_admin_flash('error', (string) ($result['error'] ?? 'Could not check the Zelle inbox yet.'));
        }

        cs_site_admin_redirect(cs_site_admin_url('/', ['panel' => 'payments']));
    }

    if ($action === 'retry_zelle_payment_message') {
        $pdo = cs_site_admin_db();
        $result = $pdo instanceof PDO
            ? cs_site_zelle_retry_message($pdo, (int) ($_POST['message_id'] ?? 0))
            : ['success' => false, 'message' => 'The admin database is not available.'];

        if (! empty($result['success'])) {
            cs_site_admin_flash('success', (string) ($result['message'] ?? 'Payment message retried.'));
        } else {
            cs_site_admin_flash('error', (string) ($result['message'] ?? 'Could not retry that payment message.'));
        }

        cs_site_admin_redirect(cs_site_admin_url('/', ['panel' => 'payments']));
    }

    if ($action === 'force_issue_zelle_payment_message') {
        $pdo = cs_site_admin_db();
        $result = $pdo instanceof PDO
            ? cs_site_zelle_force_issue_message(
                $pdo,
                (int) ($_POST['message_id'] ?? 0),
                (string) ($_POST['plan_key'] ?? 'enterprise'),
                (string) ($_POST['billing'] ?? 'monthly'),
                is_numeric($_POST['amount'] ?? null) ? round((float) $_POST['amount'], 2) : null,
            )
            : ['success' => false, 'message' => 'The admin database is not available.'];

        if (! empty($result['success'])) {
            $message = (string) ($result['message'] ?? 'License issued.');
            if (! empty($result['license_key'])) {
                $message .= ' Key: ' . (string) $result['license_key'];
            }
            if (isset($result['email_sent'])) {
                $message .= ! empty($result['email_sent']) ? ' Email sent.' : ' Email did not send from the server mail path.';
            }
            cs_site_admin_flash('success', $message);
        } else {
            cs_site_admin_flash('error', (string) ($result['message'] ?? 'Could not issue that license.'));
        }

        cs_site_admin_redirect(cs_site_admin_url('/', ['panel' => 'payments']));
    }

    if ($action === 'resend_zelle_license_email') {
        $pdo = cs_site_admin_db();
        $result = $pdo instanceof PDO
            ? cs_site_zelle_resend_license_email($pdo, (int) ($_POST['message_id'] ?? 0))
            : ['success' => false, 'message' => 'The admin database is not available.'];

        if (! empty($result['success'])) {
            cs_site_admin_flash('success', (string) ($result['message'] ?? 'License email sent.'));
        } else {
            cs_site_admin_flash('error', (string) ($result['message'] ?? 'Could not send the license email.'));
        }

        cs_site_admin_redirect(cs_site_admin_url('/', ['panel' => 'payments']));
    }
}

$dashboard = cs_site_admin_dashboard_data();
$pricing = creditsoft_site_pricing_load();
$siteContent = creditsoft_site_content_load();
$siteTracking = creditsoft_site_tracking_load();
$siteSeo = creditsoft_site_seo_load();
$seoRows = creditsoft_site_seo_page_rows();
$socialData = creditsoft_meta_social_dashboard($siteTracking);
$zellePayments = cs_site_zelle_payment_data();
$zelleAttentionCount = (int) ($zellePayments['stats']['needs_review'] ?? 0) + (int) ($zellePayments['stats']['balance_due'] ?? 0) + (int) ($zellePayments['stats']['open_tickets'] ?? 0);
$zelleMailbox = cs_site_zelle_mailbox_config();
$licenseData = cs_site_admin_license_data();
$flashSuccess = cs_site_admin_flash('success');
$flashError = cs_site_admin_flash('error');

$stats = $dashboard['stats'];
$leads = $dashboard['leads'];
$assessments = $dashboard['assessments'];
$topSources = array_slice($dashboard['sources'], 0, 6, true);
$topWorkflows = array_slice($dashboard['workflow_counts'], 0, 6, true);
$topMonitoring = array_slice($dashboard['monitoring_counts'], 0, 6, true);
$topMerchant = array_slice($dashboard['merchant_counts'], 0, 6, true);
$licenseStats = $licenseData['stats'];
$licenses = $licenseData['licenses'];
$customers = $licenseData['customers'];
$customerCount = count($customers);
$adminTwoFactorEnabled = cs_site_admin_user_has_two_factor(cs_site_admin_find_user((string) ($admin['email'] ?? '')) ?: $admin);

$panelHero = [
    'dashboard' => [
        'title' => 'CreditSoft site control.',
        'copy' => 'Keep public pricing, intake flow, customer visibility, and license ops lined up with what the website is actually selling.',
    ],
    'plans' => [
        'title' => 'Plans and pricing.',
        'copy' => 'Control list price, sale price, annual savings, and the public plan story from one place.',
    ],
    'content' => [
        'title' => 'Site content.',
        'copy' => 'Adjust homepage and pricing copy without touching the public PHP templates by hand.',
    ],
    'licenses' => [
        'title' => 'License creation and billing lane.',
        'copy' => 'Issue keys, tie them to real customers, and keep the public package line synced with the license stack.',
    ],
    'payments' => [
        'title' => 'Zelle payment processing.',
        'copy' => 'Match trusted bank mail to checkout intent, issue licenses automatically when the amount is right, and surface only the payments that need a human decision.',
    ],
    'customers' => [
        'title' => 'Customers and license owners.',
        'copy' => 'See who owns a live license, what office they belong to, and create a new license without leaving this lane.',
    ],
    'leads' => [
        'title' => 'Lead intake.',
        'copy' => 'Review public-site leads, stack details, merchant status, and fit-check answers in one read.',
    ],
    'assessments' => [
        'title' => 'Assessments and quiz traffic.',
        'copy' => 'Watch quiz submissions and red-flag results separately from the main office-fit intake.',
    ],
    'seo' => [
        'title' => 'SEO and social previews.',
        'copy' => 'See the live sitemap, adjust page-level titles and descriptions, and control which OG image shows up when the site gets shared.',
    ],
    'social' => [
        'title' => 'Social / Meta lane.',
        'copy' => 'Keep Meta traffic, public channel identity, and WhatsApp handoff in one control lane so CreditSoft can help drive and catch demand instead of reacting after the fact.',
    ],
    'ops' => [
        'title' => 'Site ops.',
        'copy' => 'Keep the public site lane clean: security, routing, funnel status, and admin controls belong here.',
    ],
];
$activeHero = $panelHero[$panel] ?? $panelHero['dashboard'];
$weeklyBudgetValue = (float) ($siteTracking['weekly_budget'] ?? 0);
$suggestedDailyCap = $weeklyBudgetValue > 0 ? round($weeklyBudgetValue / 7, 2) : null;
$suggestedMonthlyCap = $weeklyBudgetValue > 0 ? round($weeklyBudgetValue * 4.3333333333, 2) : null;
$metaCapiToken = trim((string) ($siteTracking['meta_capi_token'] ?? ''));
$metaCapiTokenHint = $metaCapiToken !== ''
    ? ('Saved token on file · ' . str_repeat('*', max(6, max(strlen($metaCapiToken) - 4, 0))) . substr($metaCapiToken, -4))
    : '';
$metaManagementToken = trim((string) ($siteTracking['meta_management_token'] ?? ''));
$metaManagementTokenHint = $metaManagementToken !== ''
    ? ('Saved token on file · ' . str_repeat('*', max(6, max(strlen($metaManagementToken) - 4, 0))) . substr($metaManagementToken, -4))
    : '';
$leadSync = $socialData['lead_sync'];
$adReport = $socialData['ad_report'];
$socialPosts = $socialData['posts'];
$socialReplies = $socialData['replies'];
$openReplies = $socialData['open_replies'];
$webhookStatus = $socialData['webhooks'];
$metaWebhookUrl = cs_site_public_url('/api/meta-webhook.php');
$whatsAppEnabled = ! empty($siteTracking['whatsapp_enabled']);
$whatsAppDisplayNumber = trim((string) ($siteTracking['whatsapp_display_number'] ?? ''));
$whatsAppReady = creditsoft_site_tracking_whatsapp_ready($siteTracking);
$whatsAppChatUrl = creditsoft_site_tracking_whatsapp_chat_url($siteTracking);
$whatsAppConfigured = $whatsAppEnabled
    || $whatsAppDisplayNumber !== ''
    || trim((string) ($siteTracking['whatsapp_phone_number_id'] ?? '')) !== ''
    || trim((string) ($siteTracking['whatsapp_business_account_id'] ?? '')) !== '';
$channelCount = count(array_filter([
    trim((string) ($siteTracking['facebook_page_id'] ?? '')),
    trim((string) ($siteTracking['instagram_business_id'] ?? '')),
    trim((string) ($siteTracking['threads_profile_id'] ?? '')) !== '' ? trim((string) ($siteTracking['threads_profile_id'] ?? '')) : trim((string) ($siteTracking['threads_username'] ?? '')),
    trim((string) ($siteTracking['x_username'] ?? '')),
    $whatsAppConfigured ? 'whatsapp' : '',
], static fn ($value): bool => trim((string) $value) !== ''));
$channelsReady = $channelCount > 0;

function admin_nav_active(string $panel, string $current): string
{
    return $panel === $current ? ' is-active' : '';
}

function admin_panel_visible(string $panel, string $current): string
{
    return $panel === $current ? '' : ' hidden';
}

function admin_escape(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function admin_url(string $path = '/', array $query = []): string
{
    return admin_escape(cs_site_admin_url($path, $query));
}

function public_url(string $path = '/', array $query = []): string
{
    return admin_escape(cs_site_public_url($path, $query));
}

function admin_format_money($amount): string
{
    if ($amount === null || $amount === '') {
        return '—';
    }

    return '$' . number_format((float) $amount, 2);
}

function admin_format_datetime(?string $value, string $fallback = '—'): string
{
    if (! $value) {
        return $fallback;
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('M j, Y g:i a', $timestamp) : $fallback;
}

function admin_brand_mark(string $brand, bool $monochrome = false): string
{
    if ($brand === 'whatsapp') {
        return sprintf(
            '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none"><path d="M12 3.25C7.42 3.25 3.75 6.85 3.75 11.26C3.75 13 4.33 14.67 5.38 16.03L4.45 20.25L8.8 19.08C9.99 19.83 11.37 20.25 12.8 20.25C17.38 20.25 21.05 16.66 21.05 12.24C21.05 7.84 16.95 3.25 12 3.25Z" fill="%1$s" stroke="%2$s" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.4 8.98C9.73 8.74 10.06 8.8 10.28 9.09L11.28 10.45C11.48 10.72 11.47 11.09 11.22 11.31L10.74 11.73C10.56 11.88 10.51 12.14 10.62 12.35C11.17 13.42 12.07 14.3 13.17 14.82C13.38 14.92 13.63 14.87 13.78 14.69L14.17 14.24C14.4 13.98 14.79 13.96 15.07 14.17L16.39 15.14C16.68 15.36 16.74 15.68 16.52 16.01C16.04 16.71 15.35 17.06 14.61 16.95C12.28 16.6 9.21 13.59 8.47 11.21C8.25 10.48 8.65 9.75 9.4 8.98Z" fill="%3$s"/></svg>',
            $monochrome ? 'none' : '#25D366',
            $monochrome ? 'currentColor' : '#25D366',
            $monochrome ? 'currentColor' : 'white'
        );
    }

    return sprintf(
        '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none"><path d="M3.25 14.4C3.25 10.05 5.23 7.1 7.76 7.1C9.82 7.1 11.12 9.31 12 11.23C12.88 9.31 14.18 7.1 16.24 7.1C18.77 7.1 20.75 10.05 20.75 14.4C20.75 16.61 19.83 18.13 18.36 18.13C16.47 18.13 14.98 15.59 12.96 11.53L12.01 9.61L11.04 11.53C9.03 15.59 7.53 18.13 5.64 18.13C4.17 18.13 3.25 16.61 3.25 14.4Z" stroke="%1$s" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        $monochrome ? 'currentColor' : '#0866FF'
    );
}

function admin_icon(string $name): string
{
    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h7v7H4zm9 0h7v5h-7zM4 13h5v7H4zm7 3h9v4h-9z"/></svg>',
        'plans' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v2H4zm0 5h16v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm3 3v2h4v-2zm6 0v2h4v-2z"/></svg>',
        'licenses' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3zm4 1v8l4-2 4 2V8z"/></svg>',
        'payments' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 6.5A2.5 2.5 0 0 1 7.5 4h9A2.5 2.5 0 0 1 19 6.5v11A2.5 2.5 0 0 1 16.5 20h-9A2.5 2.5 0 0 1 5 17.5zm2.2.5h9.6A1.2 1.2 0 0 0 15.6 5.8H8.4A1.2 1.2 0 0 0 7.2 7zm0 3v7.5A1.2 1.2 0 0 0 8.4 18.7h7.2a1.2 1.2 0 0 0 1.2-1.2V10zm2 2h5v1.8h-5zm0 3h3.7v1.8H9.2z"/></svg>',
        'customers' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 11a4 4 0 1 0-3.999-4A4 4 0 0 0 16 11zm-8 1a3 3 0 1 0-3-3 3 3 0 0 0 3 3zm8 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4zM8 14c-.29 0-.62.02-.97.05C4.65 14.26 2 15.44 2 18v2h4v-2c0-1.51.81-2.84 2.18-3.82A7.5 7.5 0 0 0 8 14z"/></svg>',
        'leads' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 11a4 4 0 1 0-3.999-4A4 4 0 0 0 16 11zm-8 1a3 3 0 1 0-3-3 3 3 0 0 0 3 3zm8 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4zM8 14c-.29 0-.62.02-.97.05C4.65 14.26 2 15.44 2 18v2h4v-2c0-1.51.81-2.84 2.18-3.82A7.5 7.5 0 0 0 8 14z"/></svg>',
        'assessments' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zm2 4v2h10V7zm0 4v2h10v-2zm0 4v2h6v-2z"/></svg>',
        'seo' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.5 4a6.5 6.5 0 1 1-4.02 11.6L3 19v-4.48l1.6-1.08A6.5 6.5 0 0 1 10.5 4Zm0 2a4.5 4.5 0 1 0 2.92 7.93l.58-.5H16l-.77-1.8.45-.6A4.5 4.5 0 0 0 10.5 6Zm7.75 10.25 2.75 2.75-1.5 1.5-2.75-2.75z"/></svg>',
        'social' => admin_brand_mark('meta', true),
        'ops' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.8 2.2a1 1 0 0 1 2.4 0l.4 2.1a8.2 8.2 0 0 1 1.9.8l1.8-1.2a1 1 0 0 1 1.7 1.2l-1.1 1.8c.3.3.6.7.8 1.1l2.1-.4a1 1 0 0 1 1.1 1v2a1 1 0 0 1-1.1 1l-2.1-.4a8.3 8.3 0 0 1-.8 1.9l1.2 1.8a1 1 0 0 1-1.2 1.7l-1.8-1.1a8.3 8.3 0 0 1-1.9.8l-.4 2.1a1 1 0 0 1-1 .8h-2a1 1 0 0 1-1-.8l-.4-2.1a8.3 8.3 0 0 1-1.9-.8l-1.8 1.1a1 1 0 0 1-1.7-1.2l1.1-1.8a8.2 8.2 0 0 1-.8-1.9l-2.1.4a1 1 0 0 1-1.1-1v-2a1 1 0 0 1 1.1-1l2.1.4c.2-.4.5-.8.8-1.1L4 5.2A1 1 0 0 1 5.2 3.5L7 4.6a8.2 8.2 0 0 1 1.9-.8zM12 9a3 3 0 1 0 3 3 3 3 0 0 0-3-3z"/></svg>',
        'portal' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3H4zm0 5h16v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zm5 2v2h6v-2zm0 4v2h4v-2z"/></svg>',
        'site' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm6.92 9h-3.08a15.7 15.7 0 0 0-1-4.06A8.03 8.03 0 0 1 18.92 11zM12 4.05c.8 1.06 1.78 3.23 2.05 6H9.95c.27-2.77 1.25-4.94 2.05-6zM5.08 13h3.08a15.7 15.7 0 0 0 1 4.06A8.03 8.03 0 0 1 5.08 13zm0-2a8.03 8.03 0 0 1 4.08-4.06A15.7 15.7 0 0 0 8.16 11zm6.92 8.95c-.8-1.06-1.78-3.23-2.05-5.95h4.1c-.27 2.72-1.25 4.89-2.05 5.95zM14.84 17.06a15.7 15.7 0 0 0 1-4.06h3.08a8.03 8.03 0 0 1-4.08 4.06z"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17v-3H3v-4h7V7l5 5zm2-12h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-7v-2h7V7h-7z"/></svg>',
    ];

    return $icons[$name] ?? $icons['dashboard'];
}

function admin_payment_icon(string $name): string
{
    $icons = [
        'mailbox' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v11a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 17.5zm2.2.75 5.8 4.16 5.8-4.16A1.2 1.2 0 0 0 16.6 6h-9.2a1.2 1.2 0 0 0-1.2 1.25zm0 2.35v7.9a1.2 1.2 0 0 0 1.2 1.2h9.2a1.2 1.2 0 0 0 1.2-1.2V9.6l-5.12 3.67a1.16 1.16 0 0 1-1.36 0z"/></svg>',
        'database' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3c4.42 0 8 1.57 8 3.5v11c0 1.93-3.58 3.5-8 3.5s-8-1.57-8-3.5v-11C4 4.57 7.58 3 12 3zm0 2c-3.4 0-5.73.94-6 1.5.27.56 2.6 1.5 6 1.5s5.73-.94 6-1.5C17.73 5.94 15.4 5 12 5zM6 9v2.5c.27.56 2.6 1.5 6 1.5s5.73-.94 6-1.5V9c-1.44.73-3.55 1-6 1s-4.56-.27-6-1zm0 5v3.5c.27.56 2.6 1.5 6 1.5s5.73-.94 6-1.5V14c-1.44.73-3.55 1-6 1s-4.56-.27-6-1z"/></svg>',
        'review' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 3 6v6c0 5.15 3.84 8.9 9 10 5.16-1.1 9-4.85 9-10V6zm0 3.15 6 2.67V12c0 3.82-2.46 6.49-6 7.75C8.46 18.49 6 15.82 6 12V7.82zm-1 3.35h2v5h-2zm0 6.5h2v2h-2z"/></svg>',
        'balance' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9 9.01 9.01 0 0 0-9-9zm1 14h-2v-1.25a4.7 4.7 0 0 1-2.6-.96l1-1.56a3.72 3.72 0 0 0 2.34.84c.93 0 1.43-.31 1.43-.85 0-.51-.43-.73-1.72-1.04-1.93-.47-2.85-1.17-2.85-2.61 0-1.32.94-2.27 2.4-2.55V5.8h2v1.2a4.2 4.2 0 0 1 2.18.79l-.92 1.59a3.17 3.17 0 0 0-1.9-.64c-.79 0-1.22.28-1.22.75 0 .5.43.69 1.85 1.05 1.79.45 2.7 1.2 2.7 2.63 0 1.42-1.01 2.39-2.69 2.63z"/></svg>',
        'ticket' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a3 3 0 0 0 0 6v3a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-3a3 3 0 0 0 0-6zm6 1v2h6V7zm0 4v2h6v-2zm0 4v2h4v-2z"/></svg>',
        'processed' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.6 16.6 5.4 12.4 4 13.8 9.6 19.4 20.5 8.5 19.1 7.1z"/></svg>',
        'license' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h10a3 3 0 0 1 3 3v15l-4-2-4 2-4-2-4 2V6a3 3 0 0 1 3-3zm1 5v2h8V8zm0 4v2h6v-2z"/></svg>',
        'proof' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4zm2 2v12h12V6zm2 2h5v2H8zm0 4h8v2H8zm0 4h4v2H8z"/></svg>',
    ];

    return $icons[$name] ?? $icons['processed'];
}

function admin_payment_brand_logo(string $brand): string
{
    if ($brand === 'zelle') {
        return '<img src="' . admin_escape(cs_site_public_url('/assets/payments/zelle.svg')) . '" alt="Zelle">';
    }

    if ($brand === 'cashapp') {
        return '<span class="cash-app-logo" aria-label="Cash App"><span>$</span></span>';
    }

    return '';
}

function admin_info_pop(string $title, string $copy, string $label = '?'): string
{
    return '<span class="info-pop is-light">'
        . '<button type="button" class="info-pop-trigger" aria-label="' . admin_escape($title) . ' help">' . admin_escape($label) . '</button>'
        . '<span class="info-pop-panel">'
        . '<span class="info-pop-title">' . admin_escape($title) . '</span>'
        . '<span class="info-pop-copy">' . admin_escape($copy) . '</span>'
        . '</span>'
        . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CreditSoft Site Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #111827;
            --muted: #6b7280;
            --line: rgba(17,24,39,.08);
            --line-strong: rgba(17,24,39,.16);
            --card: rgba(255,255,255,.92);
            --rail: #0b0b0f;
            --rail-accent: #facc15;
            --shadow: 0 22px 50px rgba(15,23,42,.10);
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; font-family: 'Inter', sans-serif; background: radial-gradient(circle at top left, #fffef8 0%, #f6f3e7 45%, #f7f5ee 100%); color: var(--ink); }
        a { color: inherit; text-decoration: none; }
        .admin-shell { min-height: 100vh; display: grid; grid-template-columns: 72px minmax(0, 1fr); }
        .rail { background: var(--rail); color: rgba(255,255,255,.72); display: flex; flex-direction: column; align-items: center; padding: 14px 0 12px; gap: 14px; position: sticky; top: 0; height: 100vh; }
        .rail-brand { width: 34px; height: 34px; border-radius: 12px; background: var(--rail-accent); color: #18181b; display: grid; place-items: center; box-shadow: inset 0 0 0 1px rgba(0,0,0,.08); }
        .rail-brand svg, .rail-item__icon svg, .rail-item svg { width: 19px; height: 19px; display: block; fill: currentColor; }
        .rail-nav { display: flex; flex-direction: column; gap: 10px; align-items: center; width: 100%; }
        .rail-spacer { flex: 1; }
        .rail-item { width: 42px; height: 42px; border-radius: 14px; display: grid; place-items: center; position: relative; color: rgba(255,255,255,.72); transition: background .18s ease, color .18s ease, transform .18s ease; }
        .rail-item:hover { background: rgba(255,255,255,.08); color: white; transform: translateY(-1px); }
        .rail-item.is-active { background: #facc15; color: #111827; }
        .rail-item--social:hover { background: rgba(8,102,255,.20); color: #fff; }
        .rail-item--social.is-active { background: linear-gradient(135deg, #0866ff, #16a34a); color: #fff; box-shadow: 0 14px 30px rgba(8,102,255,.28); }
        .rail-badge { position: absolute; top: -2px; right: -2px; min-width: 18px; height: 18px; border-radius: 999px; padding: 0 5px; background: #f59e0b; color: #111827; font-size: 11px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; border: 2px solid var(--rail); }
        .main { display: flex; flex-direction: column; min-width: 0; }
        .topbar { height: 76px; display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 18px 26px 14px; border-bottom: 1px solid var(--line); background: rgba(255,255,255,.78); backdrop-filter: blur(20px); position: sticky; top: 0; z-index: 10; }
        .topbar-left { display: flex; align-items: center; gap: 16px; min-width: 0; }
        .topbar-brand img { height: 38px; width: auto; display: block; }
        .topbar-kicker { font-size: 11px; letter-spacing: .22em; text-transform: uppercase; color: #9ca3af; font-weight: 800; }
        .topbar-title { font-size: 17px; font-weight: 800; line-height: 1.1; }
        .topbar-subtitle { font-size: 13px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .chip { padding: 9px 12px; border-radius: 12px; border: 1px solid var(--line); background: rgba(255,255,255,.94); font-size: 11px; font-weight: 800; letter-spacing: .10em; text-transform: uppercase; color: #6b7280; }
        .chip.is-good { color: #166534; background: #f0fdf4; border-color: #bbf7d0; }
        .user-pill { display: inline-flex; align-items: center; gap: 10px; padding: 8px 12px; border-radius: 16px; background: white; border: 1px solid var(--line); }
        .user-avatar { width: 34px; height: 34px; border-radius: 999px; background: linear-gradient(135deg, #dbeafe, #f5f3ff); display: grid; place-items: center; font-weight: 800; color: #1d4ed8; }
        .user-copy { display: grid; gap: 2px; }
        .user-name { font-size: 13px; font-weight: 700; }
        .user-role { font-size: 11px; letter-spacing: .14em; text-transform: uppercase; color: #9ca3af; font-weight: 800; }
        .content { padding: 24px 24px 16px; display: grid; gap: 22px; }
        .workspace-tabs { display: flex; flex-wrap: wrap; gap: 10px; }
        .workspace-tab { padding: 10px 14px; border-radius: 12px; border: 1px solid var(--line); background: rgba(255,255,255,.9); font-size: 11px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: #6b7280; }
        .workspace-tab.is-active { color: #111827; border-color: #111827; background: white; }
        .hero-row { display: flex; justify-content: space-between; gap: 20px; align-items: end; }
        .hero-copy h1 { margin: 0 0 8px; font-size: 36px; line-height: 1; }
        .hero-copy p { margin: 0; color: var(--muted); max-width: 700px; }
        .hero-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .hero-btn { display: inline-flex; align-items: center; justify-content: center; padding: 11px 15px; border-radius: 12px; border: 1px solid var(--line); background: white; font-weight: 700; cursor: pointer; }
        .hero-btn.is-primary { background: #111827; color: white; border-color: #111827; }
        .hero-btn.is-secondary { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .stats { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 22px; box-shadow: var(--shadow); }
        .stat-card { padding: 22px; display: grid; gap: 10px; min-height: 144px; }
        .eyebrow { font-size: 11px; letter-spacing: .22em; text-transform: uppercase; color: #9ca3af; font-weight: 800; }
        .stat-value { font-size: 52px; line-height: .9; font-weight: 800; }
        .stat-copy { color: var(--muted); font-size: 14px; }
        .overview-grid { display: grid; grid-template-columns: minmax(0, 1.45fr) minmax(360px, .9fr); gap: 18px; }
        .section-card { padding: 22px; display: grid; gap: 18px; }
        .section-card.payment-section { padding: 16px; gap: 12px; }
        .section-card.payment-section .section-head { align-items: center; }
        .section-card.payment-section .section-head h2 { font-size: 23px; }
        .section-card.payment-section .section-head p { font-size: 13px; max-width: 780px; }
        .section-head { display: flex; justify-content: space-between; gap: 12px; align-items: end; }
        .section-head h2 { margin: 0; font-size: 27px; line-height: 1; }
        .section-head h2 .info-pop { vertical-align: middle; transform: translateY(-2px); }
        .section-head p { margin: 6px 0 0; color: var(--muted); }
        .mini-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .mini-card { border-radius: 18px; border: 1px solid var(--line); background: linear-gradient(180deg, rgba(255,255,255,.92), rgba(248,250,252,.96)); padding: 16px; }
        .detail-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .detail-card { border-radius: 18px; border: 1px solid var(--line); background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(248,250,252,.94)); padding: 16px; display: grid; gap: 10px; min-height: 100%; }
        .detail-card strong { font-size: 20px; line-height: 1.05; }
        .detail-card .detail-copy { color: var(--muted); font-size: 13px; line-height: 1.55; }
        .detail-card .detail-stack { display: flex; gap: 8px; flex-wrap: wrap; }
        .lead-list { display: grid; gap: 12px; }
        .lead-row { display: grid; grid-template-columns: minmax(220px, 1fr) minmax(140px, .55fr) minmax(170px, .65fr) minmax(120px, .45fr) minmax(140px, .55fr); gap: 14px; padding: 16px 18px; border: 1px solid var(--line); border-radius: 22px; background: rgba(255,255,255,.88); align-items: start; }
        .lead-name { font-weight: 800; font-size: 17px; }
        .lead-sub { color: var(--muted); font-size: 13px; margin-top: 4px; line-height: 1.45; }
        .label-stack { display: grid; gap: 8px; }
        .soft-label { display: inline-flex; align-items: center; gap: 8px; padding: 8px 11px; border-radius: 12px; font-size: 11px; letter-spacing: .12em; text-transform: uppercase; font-weight: 800; border: 1px solid var(--line); background: #f8fafc; color: #6b7280; width: fit-content; }
        .soft-label.is-new { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
        .soft-label.is-qualified { background: #ecfccb; border-color: #d9f99d; color: #365314; }
        .soft-label.is-converted { background: #dcfce7; border-color: #bbf7d0; color: #166534; }
        .soft-label.is-alert { background: #fff7ed; border-color: #fed7aa; color: #b45309; }
        .note-box { padding: 14px 16px; border-radius: 18px; background: #f8fafc; border: 1px dashed var(--line-strong); color: var(--muted); line-height: 1.55; }
        .pill-list { display: flex; gap: 8px; flex-wrap: wrap; }
        .pill { display: inline-flex; align-items: center; padding: 9px 12px; border-radius: 12px; background: #f8fafc; border: 1px solid var(--line); color: #374151; font-size: 12px; font-weight: 700; }
        .table-wrap { overflow: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 980px; }
        th, td { padding: 14px 10px; border-bottom: 1px solid var(--line); vertical-align: top; text-align: left; }
        th { font-size: 11px; letter-spacing: .18em; text-transform: uppercase; color: #9ca3af; font-weight: 800; }
        td strong { display: block; font-size: 15px; }
        td small { color: var(--muted); display: block; margin-top: 4px; line-height: 1.45; }
        .empty { padding: 28px; text-align: center; color: var(--muted); border: 1px dashed var(--line-strong); border-radius: 20px; background: rgba(255,255,255,.72); }
        .bottom-bar { margin-top: auto; display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 12px 24px 14px; border-top: 1px solid var(--line); background: rgba(255,255,255,.86); }
        .bottom-left, .bottom-right { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .status-chip { padding: 7px 10px; border-radius: 10px; border: 1px solid var(--line); background: white; font-size: 11px; letter-spacing: .14em; text-transform: uppercase; font-weight: 800; color: #6b7280; }
        .status-chip.is-live { color: #166534; border-color: #bbf7d0; background: #f0fdf4; }
        .status-chip.is-alert { color: #92400e; border-color: #fcd34d; background: #fffbeb; }
        .flash-banner { padding: 16px 18px; border-radius: 20px; border: 1px solid var(--line); font-weight: 600; }
        .flash-banner.is-success { background: #f0fdf4; border-color: #86efac; color: #166534; }
        .flash-banner.is-error { background: #fff1f2; border-color: #fda4af; color: #9f1239; }
        .admin-form-grid { display: grid; gap: 14px; }
        .admin-form-grid.is-two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .admin-form-grid.is-three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .admin-field { display: grid; gap: 8px; }
        .admin-field label { font-size: 11px; letter-spacing: .16em; text-transform: uppercase; color: #9ca3af; font-weight: 800; }
        .admin-input,
        .admin-select,
        .admin-textarea {
            width: 100%;
            border: 1px solid var(--line-strong);
            border-radius: 16px;
            padding: 13px 15px;
            font: inherit;
            color: var(--ink);
            background: rgba(255,255,255,.96);
        }
        .admin-textarea { min-height: 112px; resize: vertical; }
        .admin-checkbox { display: inline-flex; align-items: center; gap: 10px; font-weight: 700; color: #374151; }
        .plan-editor-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .plan-card { border-radius: 24px; border: 1px solid var(--line); background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(248,250,252,.92)); padding: 20px; display: grid; gap: 16px; }
        .price-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .form-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .helper-copy { color: var(--muted); font-size: 13px; line-height: 1.55; }
        .autosave-note { display: flex; align-items: center; justify-content: space-between; gap: 12px; font-size: 12px; color: var(--muted); margin-top: 8px; }
        .autosave-status { min-height: 16px; font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        .autosave-status.is-saving { color: #92400e; }
        .autosave-status.is-saved { color: #15803d; }
        .autosave-status.is-error { color: #b91c1c; }
        .section-head-copy { display: grid; gap: 6px; }
        .social-overview { grid-template-columns: 1fr; }
        .social-showcase { display: grid; gap: 18px; }
        .social-brand-row { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; }
        .social-brand-stack { display: flex; gap: 12px; align-items: center; }
        .social-platform-mark { width: 54px; height: 54px; border-radius: 18px; display: grid; place-items: center; box-shadow: 0 18px 36px rgba(15,23,42,.08); }
        .social-platform-mark.is-meta { background: linear-gradient(180deg, #eff6ff, #dbeafe); color: #0866ff; }
        .social-platform-mark.is-whatsapp { background: linear-gradient(180deg, #ecfdf5, #dcfce7); color: #16a34a; }
        .social-platform-mark svg { width: 30px; height: 30px; display: block; }
        .social-brand-copy { display: grid; gap: 8px; max-width: 760px; }
        .social-brand-copy h2 { margin: 0; font-size: 31px; line-height: 1.02; }
        .social-brand-copy p { margin: 0; color: var(--muted); line-height: 1.6; }
        .social-kicker-row { display: flex; gap: 10px; flex-wrap: wrap; }
        .social-kicker-pill { display: inline-flex; align-items: center; gap: 8px; padding: 9px 12px; border-radius: 999px; border: 1px solid var(--line); background: rgba(255,255,255,.86); color: #374151; font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        .social-signal-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .social-signal { border-radius: 20px; border: 1px solid var(--line); padding: 18px; display: grid; gap: 10px; background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(248,250,252,.92)); }
        .social-signal.is-warm { background: linear-gradient(180deg, #fff9ec, #fffef9); border-color: #f7d78a; }
        .social-signal.is-blue { background: linear-gradient(180deg, #f4f9ff, #ffffff); border-color: #c9defe; }
        .social-signal.is-green { background: linear-gradient(180deg, #f4fff8, #ffffff); border-color: #bbf7d0; }
        .social-signal h3 { margin: 0; font-size: 22px; line-height: 1.05; }
        .social-signal p { margin: 0; color: var(--muted); line-height: 1.55; }
        .social-signal-meta { display: inline-flex; width: fit-content; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 999px; font-size: 11px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; background: rgba(17,24,39,.05); color: #374151; }
        .social-signal-meta.is-good { background: #ecfdf5; color: #166534; }
        .social-signal-meta.is-alert { background: #fff7ed; color: #b45309; }
        .info-pop { position: relative; display: inline-flex; align-items: center; }
        .info-pop-trigger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: rgba(255,255,255,.94);
            color: #111827;
            font-size: 13px;
            font-weight: 900;
            cursor: default;
            box-shadow: 0 14px 30px rgba(15,23,42,.08);
        }
        .info-pop-panel {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            z-index: 20;
            width: min(320px, 72vw);
            padding: 14px 16px;
            border-radius: 18px;
            border: 1px solid rgba(15,23,42,.08);
            background: rgba(17,24,39,.96);
            color: #f8fafc;
            box-shadow: 0 24px 48px rgba(15,23,42,.28);
            display: none;
        }
        .info-pop:hover .info-pop-panel,
        .info-pop:focus-within .info-pop-panel { display: grid; gap: 8px; }
        .info-pop-title { font-size: 12px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: rgba(255,255,255,.72); }
        .info-pop-copy { font-size: 13px; line-height: 1.65; color: rgba(255,255,255,.92); }
        .info-pop-list { display: grid; gap: 6px; padding-left: 16px; margin: 0; color: rgba(255,255,255,.88); font-size: 13px; line-height: 1.55; }
        .info-pop.is-light .info-pop-trigger { width: 26px; height: 26px; border-color: rgba(15,23,42,.10); background: rgba(255,255,255,.82); color: #64748b; box-shadow: none; }
        .info-pop.is-light .info-pop-trigger:hover { color: #111827; border-color: rgba(17,24,39,.24); }
        .info-pop.is-light .info-pop-panel { background: rgba(255,255,255,.98); color: #111827; border-color: rgba(15,23,42,.12); box-shadow: 0 22px 46px rgba(15,23,42,.16); }
        .info-pop.is-light .info-pop-title { color: #64748b; }
        .info-pop.is-light .info-pop-copy { color: #334155; }
        .payment-command { display: grid; grid-template-columns: minmax(0, .9fr) minmax(480px, 1.1fr); gap: 12px; align-items: start; }
        .payment-command-panel { border-radius: 18px; border: 1px solid rgba(15,23,42,.10); background: linear-gradient(135deg, rgba(255,255,255,.98), rgba(250,248,240,.94)); padding: 14px; display: grid; gap: 12px; min-height: 100%; }
        .payment-command-title { display: flex; align-items: flex-start; gap: 10px; }
        .payment-icon { width: 36px; height: 36px; border-radius: 12px; display: grid; place-items: center; background: #111827; color: #fff; flex: 0 0 auto; }
        .payment-icon svg { width: 19px; height: 19px; display: block; fill: currentColor; }
        .payment-icon.is-zelle { background: #6d1ed4; box-shadow: 0 18px 30px rgba(109,30,212,.16); }
        .payment-icon.is-good { background: #15803d; box-shadow: 0 18px 30px rgba(21,128,61,.14); }
        .payment-icon.is-warn { background: #b45309; box-shadow: 0 18px 30px rgba(180,83,9,.14); }
        .payment-icon.is-muted { background: #475569; box-shadow: 0 18px 30px rgba(71,85,105,.12); }
        .payment-title-stack { display: grid; gap: 5px; min-width: 0; }
        .payment-title-line { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .payment-title-line strong { font-size: 16px; line-height: 1.1; }
        .payment-muted { color: var(--muted); font-size: 12px; line-height: 1.42; }
        .payment-health-list { display: grid; gap: 10px; }
        .payment-health-row { display: grid; grid-template-columns: 28px minmax(0, 1fr) auto; gap: 10px; align-items: center; padding-top: 8px; border-top: 1px solid var(--line); }
        .payment-health-row:first-child { border-top: 0; padding-top: 0; }
        .payment-mini-icon { width: 28px; height: 28px; border-radius: 9px; display: grid; place-items: center; background: rgba(17,24,39,.06); color: #475569; }
        .payment-mini-icon svg { width: 15px; height: 15px; fill: currentColor; }
        .payment-health-copy { display: grid; gap: 2px; }
        .payment-health-copy strong { font-size: 13px; }
        .payment-health-copy span { color: var(--muted); font-size: 11px; line-height: 1.35; }
        .payment-state-dot { width: 10px; height: 10px; border-radius: 999px; background: #f59e0b; box-shadow: 0 0 0 4px rgba(245,158,11,.14); }
        .payment-state-dot.is-good { background: #10b981; box-shadow: 0 0 0 4px rgba(16,185,129,.14); }
        .payment-state-dot.is-alert { background: #f97316; box-shadow: 0 0 0 4px rgba(249,115,22,.14); }
        .payment-metric-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 9px; }
        .payment-metric { border-radius: 15px; border: 1px solid rgba(15,23,42,.08); background: rgba(255,255,255,.86); padding: 10px; display: grid; gap: 7px; min-height: 96px; }
        .payment-metric-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .payment-metric-top .payment-mini-icon { background: rgba(109,30,212,.08); color: #6d1ed4; }
        .payment-metric.is-alert .payment-mini-icon { background: #fff7ed; color: #b45309; }
        .payment-metric.is-good .payment-mini-icon { background: #ecfdf5; color: #15803d; }
        .payment-metric-value { font-size: 29px; font-weight: 850; letter-spacing: -.04em; line-height: .9; }
        .payment-metric-label { color: #64748b; font-size: 10px; font-weight: 850; letter-spacing: .12em; text-transform: uppercase; }
        .payment-message-list { display: grid; gap: 12px; }
        .payment-message-card { display: grid; grid-template-columns: minmax(185px, .9fr) minmax(210px, 1fr) minmax(150px, .72fr) minmax(210px, 1fr) minmax(136px, auto); gap: 10px; align-items: start; padding: 12px; border: 1px solid rgba(15,23,42,.09); border-radius: 17px; background: rgba(255,255,255,.9); }
        .payment-message-card.is-processed { border-color: rgba(21,128,61,.18); box-shadow: inset 4px 0 0 rgba(21,128,61,.6); }
        .payment-message-card.is-balance_due { border-color: rgba(180,83,9,.20); box-shadow: inset 4px 0 0 rgba(180,83,9,.65); }
        .payment-message-card.is-needs_review { border-color: rgba(249,115,22,.22); box-shadow: inset 4px 0 0 rgba(249,115,22,.70); }
        .payment-message-primary { display: flex; gap: 9px; align-items: flex-start; min-width: 0; }
        .payment-message-primary .payment-mini-icon { width: 32px; height: 32px; border-radius: 11px; color: #fff; background: #475569; flex: 0 0 auto; }
        .payment-message-card.is-processed .payment-message-primary .payment-mini-icon { background: #15803d; }
        .payment-message-card.is-balance_due .payment-message-primary .payment-mini-icon,
        .payment-message-card.is-needs_review .payment-message-primary .payment-mini-icon { background: #b45309; }
        .payment-cell { display: grid; gap: 3px; min-width: 0; }
        .payment-cell-label { color: #94a3b8; font-size: 9px; font-weight: 850; letter-spacing: .14em; text-transform: uppercase; }
        .payment-cell strong { font-size: 13px; line-height: 1.18; overflow-wrap: anywhere; }
        .payment-cell span { color: var(--muted); font-size: 11px; line-height: 1.32; overflow-wrap: anywhere; }
        .payment-proof-list { display: grid; gap: 3px; }
        .payment-proof-list span::before { content: ''; display: inline-block; width: 6px; height: 6px; border-radius: 999px; background: #cbd5e1; margin-right: 7px; transform: translateY(-1px); }
        .payment-actions { display: grid; gap: 6px; justify-items: stretch; }
        .payment-actions .hero-btn { width: 100%; padding: 7px 9px; font-size: 11px; border-radius: 10px; }
        .payment-done { color: #15803d; font-size: 12px; font-weight: 850; letter-spacing: .12em; text-transform: uppercase; }
        .payment-table-note { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: 12px; line-height: 1.45; }
        .payment-table-note .payment-mini-icon { flex: 0 0 auto; }
        .payment-brand-strip { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .payment-brand-card { display: flex; align-items: center; gap: 9px; min-width: 0; border-radius: 14px; border: 1px solid rgba(15,23,42,.08); background: rgba(255,255,255,.88); padding: 8px 10px; }
        .payment-brand-logo { width: 58px; min-width: 58px; height: 30px; display: flex; align-items: center; justify-content: center; }
        .payment-brand-logo img { max-width: 56px; max-height: 24px; display: block; object-fit: contain; }
        .payment-brand-copy { display: grid; gap: 2px; min-width: 0; }
        .payment-brand-copy strong { font-size: 12px; line-height: 1.1; }
        .payment-brand-copy span { color: var(--muted); font-size: 10.5px; line-height: 1.3; }
        .cash-app-logo { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; background: #00d632; color: #fff; box-shadow: inset 0 0 0 1px rgba(0,0,0,.08), 0 12px 22px rgba(0,214,50,.22); }
        .cash-app-logo span { width: 21px; height: 21px; border-radius: 7px; display: grid; place-items: center; border: 2px solid currentColor; font-size: 16px; font-weight: 950; line-height: 1; font-family: Inter, sans-serif; transform: rotate(-8deg); }
        .payment-mail-state { display: inline-flex; align-items: center; justify-content: center; gap: 6px; width: 100%; border-radius: 10px; padding: 7px 8px; font-size: 10px; font-weight: 850; letter-spacing: .10em; text-transform: uppercase; border: 1px solid rgba(15,23,42,.10); background: #f8fafc; color: #64748b; }
        .payment-mail-state::before { content: ''; width: 8px; height: 8px; border-radius: 999px; background: currentColor; }
        .payment-mail-state.is-sent,
        .payment-mail-state.is-opened { background: #ecfdf5; border-color: #bbf7d0; color: #15803d; }
        .payment-mail-state.is-pending { background: #fff7ed; border-color: #fed7aa; color: #b45309; }
        .payment-mail-state.is-missing { background: #f8fafc; color: #64748b; }
        .social-quick-grid { display: grid; gap: 12px; }
        .social-quick-card { border-radius: 20px; border: 1px solid var(--line); padding: 18px; background: rgba(255,255,255,.92); display: grid; gap: 12px; }
        .social-quick-card h3 { margin: 0; font-size: 19px; }
        .social-quick-card p { margin: 0; color: var(--muted); line-height: 1.55; }
        .social-link-list { display: flex; flex-wrap: wrap; gap: 10px; }
        .social-link-chip { display: inline-flex; align-items: center; justify-content: center; padding: 10px 13px; border-radius: 12px; border: 1px solid var(--line); background: #fff; font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #374151; }
        .social-link-chip.is-dark { background: #111827; border-color: #111827; color: #fff; }
        .social-link-chip.is-whatsapp { background: #ecfdf5; border-color: #86efac; color: #166534; }
        .social-readiness { display: grid; gap: 10px; }
        .social-readiness-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 11px 0; border-top: 1px solid var(--line); }
        .social-readiness-row:first-child { border-top: 0; padding-top: 0; }
        .social-readiness-copy { display: grid; gap: 3px; }
        .social-readiness-copy strong { font-size: 14px; }
        .social-readiness-copy span { font-size: 13px; color: var(--muted); }
        .social-state-dot { width: 11px; height: 11px; border-radius: 999px; background: #f59e0b; box-shadow: 0 0 0 4px rgba(245,158,11,.15); flex: 0 0 auto; }
        .social-state-dot.is-good { background: #10b981; box-shadow: 0 0 0 4px rgba(16,185,129,.16); }
        .social-form-shell { display: grid; gap: 18px; }
        .seo-page-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .seo-page-card { border-radius: 18px; border: 1px solid var(--line); background: rgba(255,255,255,.92); padding: 16px; display: grid; gap: 8px; }
        .seo-page-card strong { font-size: 18px; line-height: 1.2; }
        .seo-page-card p { margin: 0; color: var(--muted); line-height: 1.55; }
        .seo-image-preview { display: flex; align-items: center; justify-content: center; min-height: 160px; border-radius: 18px; border: 1px solid var(--line); background: rgba(255,255,255,.92); padding: 14px; overflow: hidden; }
        .seo-image-preview img { display: block; width: 100%; max-width: 360px; max-height: 170px; object-fit: cover; border-radius: 12px; box-shadow: 0 14px 36px rgba(15,23,42,.10); }
        .seo-upload-form { display: grid; gap: 12px; }
        .seo-upload-drop { display: grid; gap: 10px; padding: 18px; border-radius: 20px; border: 1px dashed var(--line-strong); background: rgba(255,255,255,.82); color: #374151; cursor: pointer; }
        .seo-upload-drop strong { font-size: 15px; }
        .seo-upload-drop input[type="file"] { width: 100%; }
        .social-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; align-items: start; }
        .social-form-card { border-radius: 22px; border: 1px solid var(--line); background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.92)); padding: 18px; display: grid; gap: 14px; min-height: 100%; }
        .social-form-card.is-whatsapp { background: linear-gradient(180deg, #f5fff8, rgba(248,250,252,.96)); border-color: #bbf7d0; }
        .social-form-card h3 { margin: 0; font-size: 20px; }
        .social-form-card p { margin: 0; color: var(--muted); line-height: 1.55; font-size: 14px; }
        .social-form-card .section-head { align-items: start; justify-content: space-between; gap: 14px; }
        .social-budget-note { padding: 14px 16px; border-radius: 18px; border: 1px solid #bfdbfe; background: linear-gradient(180deg, #f4f9ff, #ffffff); color: #1e3a8a; line-height: 1.55; }
        .social-stack { display: grid; gap: 18px; }
        .social-action-bar { display: flex; flex-wrap: wrap; gap: 10px; }
        .social-summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .social-summary-card { border-radius: 18px; border: 1px solid var(--line); padding: 16px; background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.92)); display: grid; gap: 6px; }
        .social-summary-card strong { font-size: 31px; line-height: 1; }
        .social-summary-card span { color: var(--muted); font-size: 13px; line-height: 1.5; }
        .social-split-grid { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(360px, .9fr); gap: 18px; }
        .social-stack-card { border-radius: 22px; border: 1px solid var(--line); background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.92)); padding: 18px; display: grid; gap: 16px; }
        .social-stack-card h3 { margin: 0; font-size: 21px; }
        .social-stack-card p { margin: 0; color: var(--muted); line-height: 1.55; }
        .social-meta-list { display: grid; gap: 10px; }
        .social-meta-row { display: flex; justify-content: space-between; gap: 12px; align-items: start; padding-top: 10px; border-top: 1px solid var(--line); }
        .social-meta-row:first-child { padding-top: 0; border-top: 0; }
        .social-meta-row strong { display: block; font-size: 14px; }
        .social-meta-row span { color: var(--muted); font-size: 13px; }
        .social-meta-value { font-size: 13px; font-weight: 700; color: #111827; text-align: right; }
        .social-kpi-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .social-kpi { border-radius: 18px; border: 1px solid var(--line); background: rgba(255,255,255,.88); padding: 14px; display: grid; gap: 6px; }
        .social-kpi strong { font-size: 28px; line-height: 1; }
        .social-kpi span { color: var(--muted); font-size: 13px; }
        .social-mini-table { width: 100%; min-width: 0; }
        .social-mini-table th,
        .social-mini-table td { padding: 11px 8px; }
        .social-mini-table th { font-size: 10px; letter-spacing: .16em; }
        .social-inline-form { display: grid; gap: 12px; }
        .social-inline-form .admin-field { gap: 6px; }
        .social-post-list,
        .social-reply-list { display: grid; gap: 12px; }
        .social-post-item,
        .social-reply-item { border-radius: 20px; border: 1px solid var(--line); background: rgba(255,255,255,.9); padding: 16px; display: grid; gap: 12px; }
        .social-item-top { display: flex; justify-content: space-between; gap: 12px; align-items: start; }
        .social-item-meta { display: flex; gap: 8px; flex-wrap: wrap; }
        .social-tag { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 7px 10px; background: #f8fafc; border: 1px solid var(--line); color: #475569; font-size: 11px; font-weight: 800; letter-spacing: .10em; text-transform: uppercase; }
        .social-tag.is-good { background: #ecfdf5; border-color: #bbf7d0; color: #166534; }
        .social-tag.is-alert { background: #fff7ed; border-color: #fed7aa; color: #b45309; }
        .social-item-copy { color: var(--muted); line-height: 1.6; white-space: pre-wrap; }
        .social-item-actions { display: flex; flex-wrap: wrap; gap: 10px; }
        .social-item-actions form { display: inline-flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .social-item-actions .admin-input,
        .social-item-actions .admin-textarea,
        .social-inline-form .admin-input,
        .social-inline-form .admin-textarea,
        .social-inline-form .admin-select { background: #fff; }
        .social-collapse-stack { display: grid; gap: 12px; }
        .social-collapse {
            border-radius: 20px;
            border: 1px solid var(--line);
            background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.92));
            overflow: hidden;
        }
        .social-collapse summary {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            cursor: pointer;
            list-style: none;
            padding: 16px 18px;
        }
        .social-collapse summary::-webkit-details-marker { display: none; }
        .social-collapse[open] summary { border-bottom: 1px solid var(--line); }
        .social-collapse-label { display: grid; gap: 3px; }
        .social-collapse-label strong { font-size: 15px; }
        .social-collapse-label span { font-size: 13px; color: var(--muted); line-height: 1.5; }
        .social-collapse-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 70px;
            padding: 7px 10px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: #fff;
            color: #475569;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .10em;
            text-transform: uppercase;
        }
        .social-collapse-body { padding: 16px 18px 18px; display: grid; gap: 14px; }
        .license-key { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; font-size: 13px; letter-spacing: .06em; }
        .stats-inline { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .hidden { display: none !important; }
        @media (max-width: 1260px) {
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .overview-grid, .seo-page-grid { grid-template-columns: 1fr; }
            .mini-grid, .admin-form-grid.is-three, .stats-inline, .social-form-grid, .social-signal-grid, .social-summary-grid, .social-split-grid, .social-kpi-grid, .payment-command, .payment-message-card { grid-template-columns: 1fr; }
            .payment-metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .lead-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .plan-editor-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 860px) {
            .admin-shell { grid-template-columns: 1fr; }
            .rail { position: static; height: auto; flex-direction: row; justify-content: space-between; padding: 12px 14px; }
            .rail-nav { flex-direction: row; flex-wrap: wrap; justify-content: center; }
            .rail-spacer { display: none; }
            .topbar { position: static; padding: 16px 18px 14px; flex-direction: column; align-items: stretch; }
            .content { padding: 18px; }
            .hero-row { flex-direction: column; align-items: stretch; }
            .hero-copy h1 { font-size: 31px; }
            .stats, .detail-grid, .admin-form-grid.is-two, .price-grid, .payment-metric-grid, .payment-brand-strip { grid-template-columns: 1fr; }
            .payment-health-row { grid-template-columns: 32px minmax(0, 1fr); }
            .payment-health-row .info-pop { grid-column: 2; justify-self: start; }
            .lead-row { grid-template-columns: 1fr; }
            .bottom-bar { padding: 14px 18px 18px; flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="admin-shell">
    <aside class="rail" aria-label="Site admin rail">
        <a href="<?= admin_url('/') ?>" class="rail-brand" aria-label="CreditSoft site admin home"><?= admin_icon('dashboard') ?></a>
        <nav class="rail-nav">
            <a class="rail-item<?= admin_nav_active('dashboard', $panel) ?>" href="<?= admin_url('/', ['panel' => 'dashboard']) ?>" title="Overview"><?= admin_icon('dashboard') ?></a>
            <a class="rail-item<?= admin_nav_active('plans', $panel) ?>" href="<?= admin_url('/', ['panel' => 'plans']) ?>" title="Plans"><?= admin_icon('plans') ?></a>
            <a class="rail-item<?= admin_nav_active('content', $panel) ?>" href="<?= admin_url('/', ['panel' => 'content']) ?>" title="Site content"><?= admin_icon('site') ?></a>
            <a class="rail-item<?= admin_nav_active('licenses', $panel) ?>" href="<?= admin_url('/', ['panel' => 'licenses']) ?>" title="Licenses"><?= admin_icon('licenses') ?><?php if (($licenseStats['total'] ?? 0) > 0): ?><span class="rail-badge"><?= (int) $licenseStats['total'] ?></span><?php endif; ?></a>
            <a class="rail-item<?= admin_nav_active('payments', $panel) ?>" href="<?= admin_url('/', ['panel' => 'payments']) ?>" title="Payments"><?= admin_icon('payments') ?><?php if ($zelleAttentionCount > 0): ?><span class="rail-badge"><?= $zelleAttentionCount ?></span><?php endif; ?></a>
            <a class="rail-item<?= admin_nav_active('customers', $panel) ?>" href="<?= admin_url('/', ['panel' => 'customers']) ?>" title="Customers"><?= admin_icon('customers') ?><?php if ($customerCount > 0): ?><span class="rail-badge"><?= $customerCount ?></span><?php endif; ?></a>
            <a class="rail-item<?= admin_nav_active('leads', $panel) ?>" href="<?= admin_url('/', ['panel' => 'leads']) ?>" title="Leads"><?= admin_icon('leads') ?><?php if (($stats['total_leads'] ?? 0) > 0): ?><span class="rail-badge"><?= (int) $stats['total_leads'] ?></span><?php endif; ?></a>
            <a class="rail-item<?= admin_nav_active('assessments', $panel) ?>" href="<?= admin_url('/', ['panel' => 'assessments']) ?>" title="Assessments"><?= admin_icon('assessments') ?><?php if (($stats['assessment_results'] ?? 0) > 0): ?><span class="rail-badge"><?= (int) $stats['assessment_results'] ?></span><?php endif; ?></a>
            <a class="rail-item<?= admin_nav_active('seo', $panel) ?>" href="<?= admin_url('/', ['panel' => 'seo']) ?>" title="SEO"><?= admin_icon('seo') ?></a>
            <a class="rail-item rail-item--social<?= admin_nav_active('social', $panel) ?>" href="<?= admin_url('/', ['panel' => 'social']) ?>" title="Social / Meta"><?= admin_icon('social') ?></a>
            <a class="rail-item<?= admin_nav_active('ops', $panel) ?>" href="<?= admin_url('/', ['panel' => 'ops']) ?>" title="Site ops"><?= admin_icon('ops') ?></a>
            <a class="rail-item" href="<?= public_url('/client-portal') ?>" title="Portal entry"><?= admin_icon('portal') ?></a>
            <a class="rail-item" href="<?= public_url('/') ?>" title="Public site"><?= admin_icon('site') ?></a>
        </nav>
        <div class="rail-spacer"></div>
        <a class="rail-item" href="<?= admin_url('/logout') ?>" title="Logout"><?= admin_icon('logout') ?></a>
    </aside>
    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <a class="topbar-brand" href="<?= public_url('/') ?>" aria-label="CreditSoft home"><img src="<?= admin_escape(public_url('/assets/images/CreditSoft.png')) ?>" alt="CreditSoft"></a>
                <div class="topbar-copy">
                    <div class="topbar-kicker">CreditSoft site</div>
                    <div class="topbar-title">Website leads, customers, and license ops</div>
                    <div class="topbar-subtitle">Public-site intake, pricing, customer visibility, and license management in one admin lane.</div>
                </div>
            </div>
            <div class="topbar-right">
                <span class="chip<?= $dashboard['database_connected'] ? ' is-good' : '' ?>"><?= $dashboard['database_connected'] ? 'Lead DB live' : 'Lead DB unavailable' ?></span>
                <span class="chip<?= $dashboard['turnstile_enabled'] ? ' is-good' : '' ?>"><?= $dashboard['turnstile_enabled'] ? 'Turnstile on' : 'Turnstile missing' ?></span>
                <div class="user-pill">
                    <div class="user-avatar"><?= admin_escape(strtoupper(substr((string) ($admin['name'] ?? 'A'), 0, 1))) ?></div>
                    <div class="user-copy">
                        <span class="user-name"><?= admin_escape($admin['name'] ?? 'CreditSoft Admin') ?></span>
                        <span class="user-role"><?= admin_escape($admin['role'] ?? 'Site admin') ?></span>
                    </div>
                </div>
            </div>
        </header>
        <section class="content">
            <?php if ($flashSuccess): ?>
                <div class="flash-banner is-success"><?= admin_escape($flashSuccess) ?></div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="flash-banner is-error"><?= admin_escape($flashError) ?></div>
            <?php endif; ?>

            <div class="workspace-tabs">
                <a class="workspace-tab<?= admin_nav_active('dashboard', $panel) ?>" href="<?= admin_url('/', ['panel' => 'dashboard']) ?>">Overview</a>
                <a class="workspace-tab<?= admin_nav_active('plans', $panel) ?>" href="<?= admin_url('/', ['panel' => 'plans']) ?>">Plans</a>
                <a class="workspace-tab<?= admin_nav_active('content', $panel) ?>" href="<?= admin_url('/', ['panel' => 'content']) ?>">Content</a>
                <a class="workspace-tab<?= admin_nav_active('licenses', $panel) ?>" href="<?= admin_url('/', ['panel' => 'licenses']) ?>">Licenses</a>
                <a class="workspace-tab<?= admin_nav_active('payments', $panel) ?>" href="<?= admin_url('/', ['panel' => 'payments']) ?>">Payments</a>
                <a class="workspace-tab<?= admin_nav_active('customers', $panel) ?>" href="<?= admin_url('/', ['panel' => 'customers']) ?>">Customers</a>
                <a class="workspace-tab<?= admin_nav_active('leads', $panel) ?>" href="<?= admin_url('/', ['panel' => 'leads']) ?>">Leads</a>
                <a class="workspace-tab<?= admin_nav_active('assessments', $panel) ?>" href="<?= admin_url('/', ['panel' => 'assessments']) ?>">Assessments</a>
                <a class="workspace-tab<?= admin_nav_active('seo', $panel) ?>" href="<?= admin_url('/', ['panel' => 'seo']) ?>">SEO</a>
                <a class="workspace-tab<?= admin_nav_active('social', $panel) ?>" href="<?= admin_url('/', ['panel' => 'social']) ?>">Social</a>
                <a class="workspace-tab<?= admin_nav_active('ops', $panel) ?>" href="<?= admin_url('/', ['panel' => 'ops']) ?>">Site ops</a>
            </div>

            <div class="hero-row">
                <div class="hero-copy">
                    <h1><?= admin_escape($activeHero['title']) ?></h1>
                    <p><?= admin_escape($activeHero['copy']) ?></p>
                </div>
                <div class="hero-actions">
                    <?php if ($panel === 'customers' || $panel === 'licenses'): ?>
                        <a class="hero-btn is-primary" href="<?= admin_url('/', ['panel' => 'customers']) ?>#create-license">Create license</a>
                    <?php elseif ($panel === 'payments'): ?>
                        <form method="post" class="form-actions" style="margin:0;">
                            <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
                            <input type="hidden" name="panel" value="payments">
                            <input type="hidden" name="action" value="run_zelle_payment_checker">
                            <button type="submit" class="hero-btn is-primary">Run inbox check</button>
                        </form>
                    <?php elseif ($panel === 'seo'): ?>
                        <a class="hero-btn is-primary" href="<?= public_url('/sitemap.xml') ?>" target="_blank" rel="noopener">Open sitemap</a>
                    <?php elseif ($panel === 'social'): ?>
                        <a class="hero-btn is-primary" href="<?= admin_escape(creditsoft_site_tracking_events_manager_url($siteTracking)) ?>" target="_blank" rel="noopener">Open Events Manager</a>
                    <?php else: ?>
                        <a class="hero-btn is-primary" href="<?= public_url('/subscribe') ?>">Run the intake</a>
                    <?php endif; ?>
                    <?php if ($panel === 'social'): ?>
                        <a class="hero-btn is-secondary" href="<?= admin_escape(creditsoft_site_tracking_ads_manager_url($siteTracking)) ?>" target="_blank" rel="noopener">Open Ads Manager</a>
                        <?php if ($whatsAppChatUrl !== null): ?>
                            <a class="hero-btn" href="<?= admin_escape($whatsAppChatUrl) ?>" target="_blank" rel="noopener">Open WhatsApp</a>
                        <?php else: ?>
                            <a class="hero-btn" href="#whatsapp-support">WhatsApp setup</a>
                        <?php endif; ?>
                    <?php elseif ($panel === 'seo'): ?>
                        <a class="hero-btn is-secondary" href="<?= public_url('/robots.txt') ?>" target="_blank" rel="noopener">Open robots</a>
                        <a class="hero-btn" href="https://developers.facebook.com/tools/debug/" target="_blank" rel="noopener">Open debugger</a>
                    <?php elseif ($panel === 'payments'): ?>
                        <a class="hero-btn is-secondary" href="<?= public_url('/checkout') ?>" target="_blank" rel="noopener">Open checkout</a>
                        <a class="hero-btn" href="<?= public_url('/pricing') ?>" target="_blank" rel="noopener">Open pricing</a>
                    <?php else: ?>
                        <a class="hero-btn is-secondary" href="<?= public_url('/pricing') ?>">Open public pricing</a>
                        <a class="hero-btn" href="<?= public_url('/client-portal') ?>">Open portal page</a>
                    <?php endif; ?>
                    <?php if ($panel !== 'licenses' && $panel !== 'social' && $panel !== 'payments'): ?>
                        <a class="hero-btn" href="<?= admin_url('/', ['panel' => 'licenses']) ?>">License lane</a>
                    <?php endif; ?>
                </div>
            </div>

            <section class="stats">
                <article class="card stat-card"><span class="eyebrow">Total leads</span><span class="stat-value"><?= (int) ($stats['total_leads'] ?? 0) ?></span><span class="stat-copy">Every public-site lead currently on file.</span></article>
                <article class="card stat-card"><span class="eyebrow">Active licenses</span><span class="stat-value"><?= (int) ($licenseStats['active'] ?? 0) ?></span><span class="stat-copy">Live customer licenses the site knows about right now.</span></article>
                <article class="card stat-card"><span class="eyebrow">Customers</span><span class="stat-value"><?= $customerCount ?></span><span class="stat-copy">Distinct customer emails joined to the license lane.</span></article>
                <article class="card stat-card"><span class="eyebrow">Assessments</span><span class="stat-value"><?= (int) ($stats['assessment_results'] ?? 0) ?></span><span class="stat-copy">Quiz and red-flag results captured by the site.</span></article>
                <article class="card stat-card"><span class="eyebrow">Converted</span><span class="stat-value"><?= (int) ($stats['converted_leads'] ?? 0) ?></span><span class="stat-copy">Leads already marked as won or activated.</span></article>
            </section>

            <?php if (! empty($dashboard['database_error'])): ?>
                <section class="card section-card">
                    <div class="note-box">The site admin lane could not read part of the lead data yet: <?= admin_escape((string) $dashboard['database_error']) ?></div>
                </section>
            <?php endif; ?>
            <?php if (! empty($licenseData['error'])): ?>
                <section class="card section-card">
                    <div class="note-box">The license lane is only partially available right now: <?= admin_escape((string) $licenseData['error']) ?></div>
                </section>
            <?php endif; ?>

            <section class="overview-grid<?= admin_panel_visible('dashboard', $panel) ?>">
                <article class="card section-card">
                    <div class="section-head"><div><span class="eyebrow">Recent lead flow</span><h2>Latest office fit checks</h2><p>The most recent website leads, plus the stack details they shared after the first form.</p></div></div>
                    <?php if ($leads === []): ?>
                        <div class="empty">No leads are saved yet. Once the public intake runs, they will show up here.</div>
                    <?php else: ?>
                        <div class="lead-list">
                            <?php foreach (array_slice($leads, 0, 6) as $lead): ?>
                                <?php $status = strtolower((string) ($lead['status'] ?? 'new')); ?>
                                <article class="lead-row">
                                    <div>
                                        <div class="lead-name"><?= admin_escape($lead['name'] ?: 'Unnamed lead') ?></div>
                                        <div class="lead-sub"><?= admin_escape($lead['email']) ?><?= $lead['company'] ? ' · ' . admin_escape($lead['company']) : '' ?></div>
                                        <?php if (! empty($lead['biggest_pain'])): ?><div class="note-box" style="margin-top:12px;"><?= nl2br(admin_escape((string) $lead['biggest_pain'])) ?></div><?php endif; ?>
                                    </div>
                                    <div class="label-stack">
                                        <span class="soft-label<?= $status === 'qualified' ? ' is-qualified' : ($status === 'converted' ? ' is-converted' : ' is-new') ?>"><?= admin_escape(cs_site_admin_badge_label($status)) ?></span>
                                        <span class="soft-label"><?= admin_escape(cs_site_admin_badge_label((string) ($lead['source'] ?? 'website'))) ?></span>
                                    </div>
                                    <div><strong><?= admin_escape($lead['client_count'] ?: 'No client count yet') ?></strong><div class="lead-sub"><?= admin_escape($lead['current_workflow'] ?: 'No current software saved') ?></div></div>
                                    <div><strong><?= admin_escape($lead['merchant_status'] ?: 'Merchant unknown') ?></strong><div class="lead-sub"><?= admin_escape($lead['merchant_provider'] ?: ($lead['payment_methods'] ?: 'No provider saved')) ?></div></div>
                                    <div><strong><?= admin_escape(date('M j, Y', strtotime((string) $lead['created_at']))) ?></strong><div class="lead-sub"><?= admin_escape($lead['monitoring_systems'] ?: 'No monitoring saved') ?></div></div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
                <article class="card section-card">
                    <div class="section-head"><div><span class="eyebrow">What the site is hearing</span><h2>Stack signals</h2><p>This is the quick read on what offices are already using and how they are taking payments.</p></div></div>
                    <div class="mini-grid">
                        <div class="mini-card"><span class="eyebrow">Monitoring</span><div class="pill-list" style="margin-top:12px;"><?php foreach ($topMonitoring ?: ['No monitoring data yet' => 0] as $label => $count): ?><span class="pill"><?= admin_escape($label) ?><?= $count ? ' · ' . (int) $count : '' ?></span><?php endforeach; ?></div></div>
                        <div class="mini-card"><span class="eyebrow">Current workflow</span><div class="pill-list" style="margin-top:12px;"><?php foreach ($topWorkflows ?: ['No workflow data yet' => 0] as $label => $count): ?><span class="pill"><?= admin_escape($label) ?><?= $count ? ' · ' . (int) $count : '' ?></span><?php endforeach; ?></div></div>
                        <div class="mini-card"><span class="eyebrow">Merchant status</span><div class="pill-list" style="margin-top:12px;"><?php foreach ($topMerchant ?: ['No payment data yet' => 0] as $label => $count): ?><span class="pill"><?= admin_escape($label) ?><?= $count ? ' · ' . (int) $count : '' ?></span><?php endforeach; ?></div></div>
                    </div>
                    <div class="note-box">Offices that finish the second step are the real signal. That is where you learn what monitoring they use, what CRM they are trying to escape, whether they outsource, how they take payments, and whether they even like their current website.</div>
                </article>
            </section>

            <section class="card section-card<?= admin_panel_visible('plans', $panel) ?>">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">Public pricing control</span>
                        <h2>Plans, sale language, and list pricing</h2>
                        <p>Adjust the public pricing copy here. The public pricing page, checkout page, and update lane will all read from this same stored plan file.</p>
                    </div>
                </div>
                <form method="post" class="admin-form-grid" data-autosave="true">
                    <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
                    <input type="hidden" name="action" value="save_pricing">
                    <input type="hidden" name="panel" value="plans">

                    <div class="admin-field">
                        <label for="pricing-note">Pricing note</label>
                        <textarea class="admin-textarea" id="pricing-note" name="note"><?= admin_escape((string) ($pricing['note'] ?? '')) ?></textarea>
                    </div>

                    <div class="plan-editor-grid">
                        <?php foreach (($pricing['plans'] ?? []) as $planKey => $plan): ?>
                            <article class="plan-card">
                                <div class="section-head">
                                    <div>
                                        <span class="eyebrow"><?= admin_escape(str_replace('_', ' ', $planKey)) ?></span>
                                        <h2 style="font-size:22px;"><?= admin_escape((string) ($plan['name'] ?? 'Plan')) ?></h2>
                                    </div>
                                    <label class="admin-checkbox">
                                        <input type="checkbox" name="plans[<?= admin_escape($planKey) ?>][featured]" value="1"<?= ! empty($plan['featured']) ? ' checked' : '' ?>>
                                        Featured
                                    </label>
                                </div>

                                <div class="admin-form-grid is-two">
                                    <div class="admin-field">
                                        <label>Plan name</label>
                                        <input class="admin-input" type="text" name="plans[<?= admin_escape($planKey) ?>][name]" value="<?= admin_escape((string) ($plan['name'] ?? '')) ?>">
                                    </div>
                                    <div class="admin-field">
                                        <label>Lifetime badge</label>
                                        <input class="admin-input" type="text" name="plans[<?= admin_escape($planKey) ?>][sale_badge_lifetime]" value="<?= admin_escape((string) ($plan['sale_badge_lifetime'] ?? '')) ?>">
                                    </div>
                                </div>

                                <div class="price-grid">
                                    <div class="admin-field">
                                        <label>Monthly price</label>
                                        <input class="admin-input" type="number" step="0.01" name="plans[<?= admin_escape($planKey) ?>][monthly]" value="<?= admin_escape((string) ($plan['monthly'] ?? '0')) ?>">
                                    </div>
                                    <div class="admin-field">
                                        <label>Monthly list price</label>
                                        <input class="admin-input" type="number" step="0.01" name="plans[<?= admin_escape($planKey) ?>][list_monthly]" value="<?= admin_escape((string) ($plan['list_monthly'] ?? '0')) ?>">
                                    </div>
                                    <div class="admin-field">
                                        <label>Yearly price</label>
                                        <input class="admin-input" type="number" step="0.01" name="plans[<?= admin_escape($planKey) ?>][yearly]" value="<?= admin_escape((string) ($plan['yearly'] ?? '0')) ?>">
                                    </div>
                                    <div class="admin-field">
                                        <label>Yearly list price</label>
                                        <input class="admin-input" type="number" step="0.01" name="plans[<?= admin_escape($planKey) ?>][list_yearly]" value="<?= admin_escape((string) ($plan['list_yearly'] ?? '0')) ?>">
                                    </div>
                                </div>

                                <div class="admin-form-grid is-two">
                                    <div class="admin-field">
                                        <label>Monthly badge</label>
                                        <input class="admin-input" type="text" name="plans[<?= admin_escape($planKey) ?>][sale_badge_monthly]" value="<?= admin_escape((string) ($plan['sale_badge_monthly'] ?? '')) ?>">
                                    </div>
                                    <div class="admin-field">
                                        <label>Yearly badge</label>
                                        <input class="admin-input" type="text" name="plans[<?= admin_escape($planKey) ?>][sale_badge_yearly]" value="<?= admin_escape((string) ($plan['sale_badge_yearly'] ?? '')) ?>">
                                    </div>
                                </div>

                                <div class="admin-field">
                                    <label>Monthly description</label>
                                    <textarea class="admin-textarea" name="plans[<?= admin_escape($planKey) ?>][description][monthly]"><?= admin_escape((string) (($plan['description']['monthly'] ?? ''))) ?></textarea>
                                </div>
                                <div class="admin-field">
                                    <label>Yearly description</label>
                                    <textarea class="admin-textarea" name="plans[<?= admin_escape($planKey) ?>][description][yearly]"><?= admin_escape((string) (($plan['description']['yearly'] ?? ''))) ?></textarea>
                                </div>
                                <div class="admin-field">
                                    <label>Lifetime description</label>
                                    <textarea class="admin-textarea" name="plans[<?= admin_escape($planKey) ?>][description][lifetime]"><?= admin_escape((string) (($plan['description']['lifetime'] ?? ''))) ?></textarea>
                                </div>
                                <div class="admin-field">
                                    <label>Features</label>
                                    <textarea class="admin-textarea" name="plans[<?= admin_escape($planKey) ?>][features]"><?= admin_escape(implode("\n", (array) ($plan['features'] ?? []))) ?></textarea>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="hero-btn is-primary">Save pricing changes</button>
                        <a class="hero-btn" href="/pricing" target="_blank" rel="noreferrer">Open public pricing</a>
                    </div>
                    <div class="helper-copy">Stored config path: <?= admin_escape(creditsoft_site_pricing_storage_path()) ?></div>
                    <div class="autosave-note"><span>Tab out of a field and CreditSoft saves the pricing file for you.</span><span class="autosave-status" aria-live="polite"></span></div>
                </form>
            </section>

            <section class="card section-card<?= admin_panel_visible('content', $panel) ?>">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">Main site content</span>
                        <h2>Homepage and pricing copy</h2>
                        <p>This is the starter CMS lane for the current public site. Keep the live PHP website editable without replacing the whole stack first.</p>
                    </div>
                </div>
                <form method="post" class="admin-form-grid" data-autosave="true">
                    <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
                    <input type="hidden" name="action" value="save_content">
                    <input type="hidden" name="panel" value="content">

                    <article class="plan-card">
                        <div class="section-head">
                            <div>
                                <span class="eyebrow">Homepage hero</span>
                                <h2 style="font-size:22px;">Top-of-page story</h2>
                            </div>
                        </div>

                        <div class="admin-form-grid is-two">
                            <div class="admin-field">
                                <label>Hero badge</label>
                                <input class="admin-input" type="text" name="home[hero_badge]" value="<?= admin_escape((string) ($siteContent['home']['hero_badge'] ?? '')) ?>">
                            </div>
                            <div class="admin-field">
                                <label>Hero title highlight</label>
                                <input class="admin-input" type="text" name="home[hero_title_highlight]" value="<?= admin_escape((string) ($siteContent['home']['hero_title_highlight'] ?? '')) ?>">
                            </div>
                        </div>
                        <div class="admin-field">
                            <label>Hero title primary</label>
                            <input class="admin-input" type="text" name="home[hero_title_primary]" value="<?= admin_escape((string) ($siteContent['home']['hero_title_primary'] ?? '')) ?>">
                        </div>
                        <div class="admin-field">
                            <label>Hero copy</label>
                            <textarea class="admin-textarea" name="home[hero_copy]"><?= admin_escape((string) ($siteContent['home']['hero_copy'] ?? '')) ?></textarea>
                        </div>
                        <div class="admin-form-grid is-two">
                            <div class="admin-field">
                                <label>Primary CTA label</label>
                                <input class="admin-input" type="text" name="home[primary_cta_label]" value="<?= admin_escape((string) ($siteContent['home']['primary_cta_label'] ?? '')) ?>">
                            </div>
                            <div class="admin-field">
                                <label>Primary CTA link</label>
                                <input class="admin-input" type="text" name="home[primary_cta_href]" value="<?= admin_escape((string) ($siteContent['home']['primary_cta_href'] ?? '')) ?>">
                            </div>
                        </div>
                        <div class="admin-form-grid is-two">
                            <div class="admin-field">
                                <label>Secondary CTA label</label>
                                <input class="admin-input" type="text" name="home[secondary_cta_label]" value="<?= admin_escape((string) ($siteContent['home']['secondary_cta_label'] ?? '')) ?>">
                            </div>
                            <div class="admin-field">
                                <label>Secondary CTA link</label>
                                <input class="admin-input" type="text" name="home[secondary_cta_href]" value="<?= admin_escape((string) ($siteContent['home']['secondary_cta_href'] ?? '')) ?>">
                            </div>
                        </div>
                    </article>

                    <article class="plan-card">
                        <div class="section-head">
                            <div>
                                <span class="eyebrow">Homepage support</span>
                                <h2 style="font-size:22px;">Pricing preview and fit check</h2>
                            </div>
                        </div>
                        <div class="admin-field">
                            <label>Pricing preview heading</label>
                            <input class="admin-input" type="text" name="home[pricing_heading]" value="<?= admin_escape((string) ($siteContent['home']['pricing_heading'] ?? '')) ?>">
                        </div>
                        <div class="admin-field">
                            <label>Pricing preview subtitle</label>
                            <textarea class="admin-textarea" name="home[pricing_subtitle]"><?= admin_escape((string) ($siteContent['home']['pricing_subtitle'] ?? '')) ?></textarea>
                        </div>
                        <div class="admin-field">
                            <label>Pricing preview note</label>
                            <textarea class="admin-textarea" name="home[pricing_note]"><?= admin_escape((string) ($siteContent['home']['pricing_note'] ?? '')) ?></textarea>
                        </div>
                        <div class="admin-field">
                            <label>Fit-check heading</label>
                            <input class="admin-input" type="text" name="home[fit_check_heading]" value="<?= admin_escape((string) ($siteContent['home']['fit_check_heading'] ?? '')) ?>">
                        </div>
                        <div class="admin-field">
                            <label>Fit-check subtitle</label>
                            <textarea class="admin-textarea" name="home[fit_check_subtitle]"><?= admin_escape((string) ($siteContent['home']['fit_check_subtitle'] ?? '')) ?></textarea>
                        </div>
                        <div class="admin-field">
                            <label>Fit-check intro</label>
                            <textarea class="admin-textarea" name="home[fit_check_intro]"><?= admin_escape((string) ($siteContent['home']['fit_check_intro'] ?? '')) ?></textarea>
                        </div>
                    </article>

                    <article class="plan-card">
                        <div class="section-head">
                            <div>
                                <span class="eyebrow">Pricing page</span>
                                <h2 style="font-size:22px;">Live plan page copy</h2>
                            </div>
                        </div>
                        <div class="admin-field">
                            <label>Pricing eyebrow</label>
                            <input class="admin-input" type="text" name="pricing[eyebrow]" value="<?= admin_escape((string) ($siteContent['pricing']['eyebrow'] ?? '')) ?>">
                        </div>
                        <div class="admin-field">
                            <label>Pricing title</label>
                            <input class="admin-input" type="text" name="pricing[title]" value="<?= admin_escape((string) ($siteContent['pricing']['title'] ?? '')) ?>">
                        </div>
                        <div class="admin-field">
                            <label>Pricing subtitle</label>
                            <textarea class="admin-textarea" name="pricing[subtitle]"><?= admin_escape((string) ($siteContent['pricing']['subtitle'] ?? '')) ?></textarea>
                        </div>
                        <div class="admin-field">
                            <label>Pricing note</label>
                            <textarea class="admin-textarea" name="pricing[note]"><?= admin_escape((string) ($siteContent['pricing']['note'] ?? '')) ?></textarea>
                        </div>
                        <div class="admin-form-grid is-three">
                            <div class="admin-field">
                                <label>Support card 1 title</label>
                                <input class="admin-input" type="text" name="pricing[support_card_one_title]" value="<?= admin_escape((string) ($siteContent['pricing']['support_card_one_title'] ?? '')) ?>">
                            </div>
                            <div class="admin-field">
                                <label>Support card 2 title</label>
                                <input class="admin-input" type="text" name="pricing[support_card_two_title]" value="<?= admin_escape((string) ($siteContent['pricing']['support_card_two_title'] ?? '')) ?>">
                            </div>
                            <div class="admin-field">
                                <label>Support card 3 title</label>
                                <input class="admin-input" type="text" name="pricing[support_card_three_title]" value="<?= admin_escape((string) ($siteContent['pricing']['support_card_three_title'] ?? '')) ?>">
                            </div>
                        </div>
                        <div class="admin-form-grid is-three">
                            <div class="admin-field">
                                <label>Support card 1 text</label>
                                <textarea class="admin-textarea" name="pricing[support_card_one_text]"><?= admin_escape((string) ($siteContent['pricing']['support_card_one_text'] ?? '')) ?></textarea>
                            </div>
                            <div class="admin-field">
                                <label>Support card 2 text</label>
                                <textarea class="admin-textarea" name="pricing[support_card_two_text]"><?= admin_escape((string) ($siteContent['pricing']['support_card_two_text'] ?? '')) ?></textarea>
                            </div>
                            <div class="admin-field">
                                <label>Support card 3 text</label>
                                <textarea class="admin-textarea" name="pricing[support_card_three_text]"><?= admin_escape((string) ($siteContent['pricing']['support_card_three_text'] ?? '')) ?></textarea>
                            </div>
                        </div>
                    </article>

                    <div class="form-actions">
                        <button type="submit" class="hero-btn is-primary">Save site copy</button>
                        <a class="hero-btn" href="/" target="_blank" rel="noreferrer">Open home</a>
                        <a class="hero-btn" href="/pricing" target="_blank" rel="noreferrer">Open pricing</a>
                    </div>
                    <div class="helper-copy">Stored content path: <?= admin_escape(creditsoft_site_content_storage_path()) ?></div>
                    <div class="autosave-note"><span>Content saves when you leave a field, so you can keep moving through the page.</span><span class="autosave-status" aria-live="polite"></span></div>
                </form>
            </section>

            <section class="card section-card<?= admin_panel_visible('licenses', $panel) ?>">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">License lane</span>
                        <h2>Create and review licenses</h2>
                        <p>This uses the same public plan prices, but maps the public package into the real license feature set so Enterprise Pro gets the browser-companion entitlement.</p>
                    </div>
                </div>

                <?php if (! $licenseData['available']): ?>
                    <div class="empty">License tables are not available on this host yet, so this lane cannot create or list licenses right now.</div>
                <?php else: ?>
                    <div class="stats-inline">
                        <div class="mini-card"><span class="eyebrow">Total licenses</span><div class="stat-value" style="font-size:34px;"><?= (int) ($licenseStats['total'] ?? 0) ?></div></div>
                        <div class="mini-card"><span class="eyebrow">Active</span><div class="stat-value" style="font-size:34px;"><?= (int) ($licenseStats['active'] ?? 0) ?></div></div>
                        <div class="mini-card"><span class="eyebrow">Expired</span><div class="stat-value" style="font-size:34px;"><?= (int) ($licenseStats['expired'] ?? 0) ?></div></div>
                        <div class="mini-card"><span class="eyebrow">Auto renew</span><div class="stat-value" style="font-size:34px;"><?= (int) ($licenseStats['auto_renew'] ?? 0) ?></div></div>
                    </div>

                    <form method="post" class="admin-form-grid">
                        <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
                        <input type="hidden" name="action" value="create_license">
                        <input type="hidden" name="panel" value="licenses">

                        <div class="admin-form-grid is-two">
                            <div class="admin-field">
                                <label for="customer-name">Customer name</label>
                                <input class="admin-input" id="customer-name" type="text" name="customer_name" placeholder="CreditSoft office name">
                            </div>
                            <div class="admin-field">
                                <label for="customer-email">Customer email</label>
                                <input class="admin-input" id="customer-email" type="email" name="customer_email" placeholder="owner@office.com" required>
                            </div>
                        </div>

                        <div class="admin-form-grid is-two">
                            <div class="admin-field">
                                <label for="plan-key">Public plan</label>
                                <select class="admin-select" id="plan-key" name="plan_key">
                                    <?php foreach (($pricing['plans'] ?? []) as $planKey => $plan): ?>
                                        <option value="<?= admin_escape($planKey) ?>"><?= admin_escape((string) ($plan['name'] ?? $planKey)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="admin-field">
                                <label for="duration">Duration</label>
                                <select class="admin-select" id="duration" name="duration">
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                    <option value="lifetime">Lifetime</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="hero-btn is-primary">Create license</button>
                        </div>
                    </form>

                    <?php if ($licenses === []): ?>
                        <div class="empty">No licenses have been created yet.</div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead><tr><th>License</th><th>Customer</th><th>Plan</th><th>Billing</th><th>Status</th><th>Amount</th><th>Expires</th><th>Last payment</th></tr></thead>
                                <tbody>
                                <?php foreach ($licenses as $license): ?>
                                    <tr>
                                        <td><strong class="license-key"><?= admin_escape((string) ($license['license_key'] ?? '')) ?></strong><small><?= admin_escape((string) ($license['domain'] ?? 'No domain bound')) ?></small></td>
                                        <td><strong><?= admin_escape((string) (($license['customer_name'] ?: $license['customer_email']) ?? 'Unknown customer')) ?></strong><small><?= admin_escape((string) ($license['customer_email'] ?? '')) ?></small></td>
                                        <td><strong><?= admin_escape(cs_site_admin_badge_label((string) ($license['plan'] ?? ''))) ?></strong></td>
                                        <td><strong><?= admin_escape(cs_site_admin_badge_label((string) ($license['billing_cycle'] ?? 'one time'))) ?></strong><small><?= ! empty($license['auto_renew']) ? 'Auto renew on' : 'Auto renew off' ?></small></td>
                                        <td><strong><?= admin_escape(cs_site_admin_badge_label((string) ($license['status'] ?? 'unknown'))) ?></strong><small><?= admin_escape((string) ($license['last_payment_status'] ?? '')) ?></small></td>
                                        <td><strong><?= admin_format_money($license['amount'] ?? null) ?></strong></td>
                                        <td><strong><?= admin_format_datetime($license['expires_at'] ?? null) ?></strong><small><?= admin_escape((string) ($license['next_billing'] ?? '')) ?></small></td>
                                        <td><strong><?= admin_format_datetime($license['last_payment_at'] ?? null) ?></strong><small><?= admin_escape((string) (($license['failed_attempts'] ?? null) !== null ? 'Failed attempts: ' . (int) $license['failed_attempts'] : '')) ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <section class="card section-card payment-section<?= admin_panel_visible('payments', $panel) ?>">
                <div class="section-head">
                    <div class="section-head-copy">
                        <span class="eyebrow">Payment console</span>
                        <h2>Zelle mailbox and license automation</h2>
                        <p>One read for what is connected, what matched, what emailed, and which payments still need an operator decision.</p>
                    </div>
                    <div class="form-actions">
                        <form method="post" class="form-actions" style="margin:0;">
                            <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
                            <input type="hidden" name="panel" value="payments">
                            <input type="hidden" name="action" value="run_zelle_payment_checker">
                            <button type="submit" class="hero-btn is-primary">Run inbox check</button>
                        </form>
                        <a class="hero-btn" href="<?= public_url('/checkout') ?>" target="_blank" rel="noopener">Open checkout</a>
                        <a class="hero-btn" href="<?= public_url('/admin/email-preview.php', ['type' => 'balance_due', 'public' => '1']) ?>" target="_blank" rel="noopener">Preview email</a>
                    </div>
                </div>
                <div class="payment-command">
                    <article class="payment-command-panel">
                        <div class="payment-command-title">
                            <span class="payment-icon is-zelle"><?= admin_payment_icon('mailbox') ?></span>
                            <div class="payment-title-stack">
                                <div class="payment-title-line">
                                    <strong><?= admin_escape((string) ($zellePayments['mailbox'] ?? 'Zelle mailbox')) ?></strong>
                                    <?= admin_info_pop('Dedicated mailbox', 'This mailbox should only receive Zelle payment notifications. CreditSoft ignores unrelated mail before matching amounts or issuing licenses.') ?>
                                </div>
                                <span class="payment-muted"><?= admin_escape(implode(' · ', array_filter([(string) ($zelleMailbox['host'] ?? ''), (string) ($zelleMailbox['folder'] ?? '')], static fn (string $value): bool => $value !== ''))) ?></span>
                                <span class="payment-muted">Outbound sender: <?= admin_escape((string) ($zellePayments['from_email'] ?? '')) ?></span>
                            </div>
                        </div>
                        <div class="payment-brand-strip">
                            <div class="payment-brand-card">
                                <span class="payment-brand-logo"><?= admin_payment_brand_logo('zelle') ?></span>
                                <span class="payment-brand-copy">
                                    <strong>Zelle</strong>
                                    <span>Bank email match and license automation.</span>
                                </span>
                            </div>
                            <div class="payment-brand-card">
                                <span class="payment-brand-logo"><?= admin_payment_brand_logo('cashapp') ?></span>
                                <span class="payment-brand-copy">
                                    <strong>Cash App</strong>
                                    <span>Manual proof lane until inbox automation is added.</span>
                                </span>
                            </div>
                        </div>
                        <div class="payment-health-list">
                            <div class="payment-health-row">
                                <span class="payment-mini-icon"><?= admin_payment_icon('mailbox') ?></span>
                                <span class="payment-health-copy">
                                    <strong><?= ! empty($zellePayments['configured']) ? 'Mailbox password is saved' : 'Mailbox password missing' ?></strong>
                                    <span><?= ! empty($zellePayments['imap_enabled']) ? 'IMAP extension is enabled and ready for inbox checks.' : 'Enable PHP IMAP before inbox checks can read Zelle mail.' ?></span>
                                </span>
                                <?= admin_info_pop('Mailbox check', 'The checker opens the dedicated inbox, reads only expected Zelle subjects, validates bank headers, and ignores unrelated messages.') ?>
                            </div>
                            <div class="payment-health-row">
                                <span class="payment-mini-icon"><?= admin_payment_icon('database') ?></span>
                                <span class="payment-health-copy">
                                    <strong><?= ! empty($zellePayments['available']) ? 'Payment data is live' : 'Payment data is limited' ?></strong>
                                    <span><?= ! empty($zellePayments['error']) ? admin_escape((string) $zellePayments['error']) : 'Message history, tickets, checkout notices, and license actions are available.' ?></span>
                                </span>
                                <span class="payment-state-dot<?= ! empty($zellePayments['available']) ? ' is-good' : ' is-alert' ?>" aria-hidden="true"></span>
                            </div>
                            <div class="payment-health-row">
                                <span class="payment-mini-icon"><?= admin_payment_icon('proof') ?></span>
                                <span class="payment-health-copy">
                                    <strong>Trusted sender rules are enforced</strong>
                                    <span>Chase/Zelle subject, sender domain, return path, and DKIM clues are checked before a message enters the queue.</span>
                                </span>
                                <?= admin_info_pop('Why this matters', 'A random Supabase, support, or marketing email should never become a payment. The queue starts with bank-trust checks before customer matching.') ?>
                            </div>
                        </div>
                    </article>
                    <article class="payment-command-panel">
                        <div class="payment-command-title">
                            <span class="payment-icon is-good"><?= admin_payment_icon('processed') ?></span>
                            <div class="payment-title-stack">
                                <div class="payment-title-line">
                                    <strong>Automation snapshot</strong>
                                    <?= admin_info_pop('Default behavior', 'When a trusted payment matches the expected amount, CreditSoft creates or extends the license and sends the branded license email automatically.') ?>
                                </div>
                                <span class="payment-muted">Use the warning counts first. Processed and email-sent counts are the proof that the automation is working.</span>
                            </div>
                        </div>
                        <div class="payment-metric-grid">
                            <div class="payment-metric is-good">
                                <div class="payment-metric-top"><span class="payment-metric-label">Processed</span><span class="payment-mini-icon"><?= admin_payment_icon('processed') ?></span></div>
                                <span class="payment-metric-value"><?= (int) ($zellePayments['stats']['processed'] ?? 0) ?></span>
                                <span class="payment-muted">Matched payments that created or renewed a license.</span>
                            </div>
                            <div class="payment-metric<?= (($zellePayments['stats']['needs_review'] ?? 0) > 0) ? ' is-alert' : '' ?>">
                                <div class="payment-metric-top"><span class="payment-metric-label">Review</span><span class="payment-mini-icon"><?= admin_payment_icon('review') ?></span></div>
                                <span class="payment-metric-value"><?= (int) ($zellePayments['stats']['needs_review'] ?? 0) ?></span>
                                <span class="payment-muted">Trusted bank mail without enough safe proof to issue.</span>
                            </div>
                            <div class="payment-metric<?= (($zellePayments['stats']['balance_due'] ?? 0) > 0) ? ' is-alert' : '' ?>">
                                <div class="payment-metric-top"><span class="payment-metric-label">Short pays</span><span class="payment-mini-icon"><?= admin_payment_icon('balance') ?></span></div>
                                <span class="payment-metric-value"><?= (int) ($zellePayments['stats']['balance_due'] ?? 0) ?></span>
                                <span class="payment-muted">Matched customers that still owe a balance.</span>
                            </div>
                            <div class="payment-metric<?= (($zellePayments['stats']['open_tickets'] ?? 0) > 0) ? ' is-alert' : '' ?>">
                                <div class="payment-metric-top"><span class="payment-metric-label">Tickets</span><span class="payment-mini-icon"><?= admin_payment_icon('ticket') ?></span></div>
                                <span class="payment-metric-value"><?= (int) ($zellePayments['stats']['open_tickets'] ?? 0) ?></span>
                                <span class="payment-muted">Customer “paid but no license” support requests.</span>
                            </div>
                            <div class="payment-metric is-good">
                                <div class="payment-metric-top"><span class="payment-metric-label">License emails sent</span><span class="payment-mini-icon"><?= admin_payment_icon('mailbox') ?></span></div>
                                <span class="payment-metric-value"><?= (int) ($zellePayments['stats']['email_sent'] ?? 0) ?></span>
                                <span class="payment-muted">Sold licenses with a recorded branded email send.</span>
                            </div>
                            <div class="payment-metric">
                                <div class="payment-metric-top"><span class="payment-metric-label">Total mail</span><span class="payment-mini-icon"><?= admin_payment_icon('database') ?></span></div>
                                <span class="payment-metric-value"><?= (int) ($zellePayments['stats']['total'] ?? 0) ?></span>
                                <span class="payment-muted">Trusted payment messages stored in history.</span>
                            </div>
                        </div>
                    </article>
                </div>
                <div class="payment-table-note"><span class="payment-mini-icon"><?= admin_payment_icon('review') ?></span><span>Needs review means the payment email passed bank-trust checks, but CreditSoft could not safely prove the customer, memo, transaction, plan, or amount without help.</span></div>
            </section>

            <section class="card section-card payment-section<?= admin_panel_visible('payments', $panel) ?>">
                <div class="section-head">
                    <div class="section-head-copy">
                        <span class="eyebrow">Customer payment help</span>
                        <h2>“I paid but no license” tickets <?= admin_info_pop('Ticket review', 'These are customer-submitted payment-help forms. Compare the payer name, screenshot, memo, amount, and transaction or confirmation number before issuing anything manually.') ?></h2>
                        <p>These are customer-submitted tickets for payments that did not automatically match. Use them to compare payer name, memo, amount, transaction ID, and screenshot proof against the Zelle mailbox.</p>
                    </div>
                    <div class="form-actions">
                        <a class="hero-btn" href="<?= public_url('/payment-help') ?>" target="_blank" rel="noopener">Open help page</a>
                    </div>
                </div>
                <?php if (($zellePayments['tickets'] ?? []) === []): ?>
                    <div class="empty">No customer payment help tickets yet.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Ticket</th><th>Customer</th><th>Payment source</th><th>Amount</th><th>Memo</th><th>Attachment</th></tr></thead>
                            <tbody>
                            <?php foreach (array_slice((array) ($zellePayments['tickets'] ?? []), 0, 10) as $ticket): ?>
                                <?php $hasAttachment = trim((string) ($ticket['attachment_path'] ?? '')) !== ''; ?>
                                <tr>
                                    <td><strong><?= admin_escape((string) ($ticket['ticket_number'] ?? '')) ?></strong><small><?= admin_escape(admin_format_datetime((string) ($ticket['created_at'] ?? null))) ?></small><small><?= admin_escape(cs_site_admin_badge_label((string) ($ticket['status'] ?? 'new'))) ?></small></td>
                                    <td><strong><?= admin_escape((string) (($ticket['customer_name'] ?: $ticket['customer_email']) ?? 'Unknown')) ?></strong><small><?= admin_escape((string) ($ticket['customer_email'] ?? '')) ?></small><small><?= admin_escape((string) ($ticket['customer_phone'] ?? '')) ?></small></td>
                                    <td><strong><?= admin_escape((string) ($ticket['payer_name'] ?? '')) ?></strong><small><?= admin_escape((string) ($ticket['payment_source'] ?? '')) ?></small><small><?= admin_escape((string) ($ticket['transaction_id'] ?? '')) ?></small></td>
                                    <td><strong><?= admin_format_money($ticket['amount'] ?? null) ?></strong><small><?= admin_escape((string) ($ticket['payment_date'] ?? '')) ?></small></td>
                                    <td><strong><?= admin_escape((string) ($ticket['memo_used'] ?? 'No memo provided')) ?></strong><small><?= admin_escape((string) ($ticket['notes'] ?? '')) ?></small></td>
                                    <td>
                                        <?php if ($hasAttachment): ?>
                                            <a class="hero-btn" style="padding:8px 10px; font-size:12px;" href="<?= public_url('/admin/payment-ticket-attachment.php', ['id' => (int) ($ticket['id'] ?? 0), 'token' => (string) ($ticket['attachment_download_token'] ?? '')]) ?>" target="_blank" rel="noopener">Open proof</a>
                                            <small><?= admin_escape((string) ($ticket['attachment_original_name'] ?? 'Screenshot')) ?></small>
                                        <?php else: ?>
                                            <small>No attachment</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card section-card payment-section<?= admin_panel_visible('payments', $panel) ?>">
                <div class="section-head">
                    <div class="section-head-copy">
                        <span class="eyebrow">Inbox review</span>
                        <h2>Recent payment messages <?= admin_info_pop('Operator order', 'Start with orange rows. Green rows already matched, issued, and emailed. The action column only appears when CreditSoft still needs help.') ?></h2>
                        <p>Processed payments, short pays, and messages that still need a human look are shown here with the transaction IDs kept for billing history.</p>
                    </div>
                </div>
                <?php $zelleReviewMessages = array_values(array_filter((array) ($zellePayments['messages'] ?? []), static fn (array $message): bool => in_array((string) ($message['status'] ?? ''), ['processed', 'balance_due', 'needs_review'], true))); ?>
                <?php if ($zelleReviewMessages === []): ?>
                    <div class="empty">No processed, balance-due, or needs-review payment messages yet. Run the inbox check once the mailbox has fresh Zelle mail.</div>
                <?php else: ?>
                    <div class="payment-message-list">
                            <?php foreach (array_slice($zelleReviewMessages, 0, 8) as $message): ?>
                                <?php
                                    $messageStatus = (string) ($message['status'] ?? 'needs_review');
                                    $messageStatusClass = preg_replace('/[^a-z0-9_-]+/i', '', $messageStatus) ?: 'needs_review';
                                    $messageStatusCopy = match ($messageStatus) {
                                        'processed' => 'Processed and saved',
                                        'balance_due' => 'Matched but short',
                                        default => 'Needs manual review',
                                    };
                                    $transactionId = trim((string) ($message['transaction_id'] ?? ''));
                                    $balanceDue = isset($message['balance_due']) && is_numeric($message['balance_due']) ? (float) $message['balance_due'] : null;
                                    $expectedAmount = isset($message['expected_amount']) && is_numeric($message['expected_amount']) ? (float) $message['expected_amount'] : null;
                                    $messageMeta = json_decode((string) ($message['metadata_json'] ?? ''), true);
                                    $messageMeta = is_array($messageMeta) ? $messageMeta : [];
                                    $emailDetection = is_array($messageMeta['email_detection'] ?? null) ? $messageMeta['email_detection'] : [];
                                    $expectedPaymentMeta = is_array($messageMeta['expected_payment'] ?? null) ? $messageMeta['expected_payment'] : [];
                                    $requiresMainLicense = ! empty($expectedPaymentMeta['requires_main_license']);
                                    $memoText = trim((string) ($emailDetection['memo_text'] ?? ''));
                                    $headerTrust = is_array($messageMeta['header_trust'] ?? null) ? $messageMeta['header_trust'] : [];
                                    $headerStatus = trim((string) ($headerTrust['status_label'] ?? ''));
                                    $emailSentAt = trim((string) ($message['email_sent_at'] ?? ''));
                                    $emailOpenedAt = trim((string) ($message['email_opened_at'] ?? ''));
                                    $emailOpenCount = (int) ($message['email_open_count'] ?? 0);
                                    $emailAttemptCount = (int) ($message['email_attempt_count'] ?? 0);
                                    $emailLastError = trim((string) ($message['email_last_error'] ?? ''));
                                    if ($emailOpenedAt !== '') {
                                        $emailStatusLine = 'Opened ' . admin_format_datetime($emailOpenedAt) . ($emailOpenCount > 1 ? ' · ' . $emailOpenCount . ' opens' : '');
                                        $emailStateClass = 'is-opened';
                                        $emailStateLabel = 'Email opened';
                                    } elseif ($emailSentAt !== '') {
                                        $emailStatusLine = 'Email sent ' . admin_format_datetime($emailSentAt) . ' · not opened yet';
                                        $emailStateClass = 'is-sent';
                                        $emailStateLabel = 'License email sent';
                                    } elseif ($emailAttemptCount > 0 && $emailLastError !== '') {
                                        $emailStatusLine = 'Email pending retry · ' . $emailLastError;
                                        $emailStateClass = 'is-pending';
                                        $emailStateLabel = 'Email retry pending';
                                    } else {
                                        $emailStatusLine = 'No email sent';
                                        $emailStateClass = 'is-missing';
                                        $emailStateLabel = 'No email sent';
                                    }
                                ?>
                                <article class="payment-message-card is-<?= admin_escape($messageStatusClass) ?>">
                                    <div class="payment-message-primary">
                                        <span class="payment-mini-icon"><?= admin_payment_icon($messageStatus === 'processed' ? 'processed' : ($messageStatus === 'balance_due' ? 'balance' : 'review')) ?></span>
                                        <div class="payment-cell">
                                            <span class="payment-cell-label">Status</span>
                                            <strong><?= admin_escape(cs_site_admin_badge_label($messageStatus)) ?></strong>
                                            <span><?= admin_escape($messageStatusCopy) ?></span>
                                            <span><?= admin_escape(admin_format_datetime((string) ($message['received_at'] ?? $message['created_at'] ?? null))) ?></span>
                                        </div>
                                    </div>
                                    <div class="payment-cell">
                                        <span class="payment-cell-label">Sender and proof</span>
                                        <strong><?= admin_escape((string) ($message['sender_name'] ?? $message['from_name'] ?? 'Unknown sender')) ?></strong>
                                        <span><?= admin_escape((string) ($message['sender_email'] ?? $message['from_email'] ?? '')) ?></span>
                                        <?php if (! empty($message['sender_phone'])): ?><span><?= admin_escape((string) $message['sender_phone']) ?></span><?php endif; ?>
                                        <?php if ($headerStatus !== ''): ?><span>Bank proof: <?= admin_escape($headerStatus) ?></span><?php endif; ?>
                                    </div>
                                    <div class="payment-cell">
                                        <span class="payment-cell-label">Amount</span>
                                        <strong><?= admin_escape(cs_site_zelle_money(isset($message['amount']) ? (float) $message['amount'] : null)) ?></strong>
                                        <span>Expected <?= admin_escape(cs_site_zelle_money($expectedAmount)) ?></span>
                                        <?php if ($balanceDue !== null && $balanceDue > 0): ?><span>Balance due <?= admin_escape(cs_site_zelle_money($balanceDue)) ?></span><?php endif; ?>
                                        <?php if ($requiresMainLicense): ?><span>Main license required for node</span><?php endif; ?>
                                    </div>
                                    <div class="payment-cell">
                                        <span class="payment-cell-label">Match details</span>
                                        <strong><?= admin_escape(cs_site_admin_badge_label((string) ($message['match_type'] ?? 'unmatched'))) ?></strong>
                                        <div class="payment-proof-list">
                                            <span><?= admin_escape(cs_site_admin_badge_label((string) ($message['payment_status'] ?? 'unknown'))) ?></span>
                                            <?php if (! empty($message['plan_key'])): ?><span><?= admin_escape((string) $message['plan_key']) ?><?= ! empty($message['billing']) ? ' · ' . admin_escape((string) $message['billing']) : '' ?></span><?php endif; ?>
                                            <?php if ($transactionId !== ''): ?><span>Txn <?= admin_escape($transactionId) ?></span><?php endif; ?>
                                            <?php if ($memoText !== ''): ?><span>Memo <?= admin_escape($memoText) ?></span><?php endif; ?>
                                            <span><?= admin_escape($emailStatusLine) ?></span>
                                        </div>
                                    </div>
                                    <div class="payment-actions">
                                        <span class="payment-mail-state <?= admin_escape($emailStateClass) ?>"><?= admin_escape($emailStateLabel) ?></span>
                                        <?php if (in_array($messageStatus, ['needs_review', 'balance_due'], true)): ?>
                                            <form method="post" style="margin:0;">
                                                <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
                                                <input type="hidden" name="panel" value="payments">
                                                <input type="hidden" name="action" value="retry_zelle_payment_message">
                                                <input type="hidden" name="message_id" value="<?= (int) ($message['id'] ?? 0) ?>">
                                                <button type="submit" class="hero-btn">Retry match</button>
                                            </form>
                                            <form method="post" style="margin:0;">
                                                <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
                                                <input type="hidden" name="panel" value="payments">
                                                <input type="hidden" name="action" value="force_issue_zelle_payment_message">
                                                <input type="hidden" name="message_id" value="<?= (int) ($message['id'] ?? 0) ?>">
                                                <input type="hidden" name="plan_key" value="enterprise">
                                                <input type="hidden" name="billing" value="monthly">
                                                <input type="hidden" name="amount" value="89.95">
                                                <button type="submit" class="hero-btn is-primary">Issue Enterprise</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="payment-done">Done</span>
                                            <?php if (! empty($message['license_key'])): ?>
                                                <span class="payment-cell">
                                                    <span class="payment-cell-label">License</span>
                                                    <strong class="license-key"><?= admin_escape((string) ($message['license_key'] ?? '')) ?></strong>
                                                </span>
                                                <a class="hero-btn" href="<?= admin_escape(cs_site_admin_url('/email-preview.php', ['type' => 'license', 'message_id' => (int) ($message['id'] ?? 0)])) ?>">Preview email</a>
                                                <?php if ($emailSentAt === ''): ?>
                                                    <form method="post" style="margin:0;">
                                                        <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
                                                        <input type="hidden" name="panel" value="payments">
                                                        <input type="hidden" name="action" value="resend_zelle_license_email">
                                                        <input type="hidden" name="message_id" value="<?= (int) ($message['id'] ?? 0) ?>">
                                                        <button type="submit" class="hero-btn is-primary">Send license email</button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card section-card payment-section<?= admin_panel_visible('payments', $panel) ?>">
                <div class="section-head">
                    <div class="section-head-copy">
                        <span class="eyebrow">Queued notices</span>
                        <h2>Checkout and renewal payment notices <?= admin_info_pop('Checkout intent', 'These notices are what the customer said they intended to pay. The inbox matcher compares this intent against the bank email before issuing a license.') ?></h2>
                        <p>These queued notices come from checkout and renewal request files, so you can compare them against what landed in the inbox.</p>
                    </div>
                </div>
                <?php if (($zellePayments['notices'] ?? []) === []): ?>
                    <div class="empty">No queued checkout or renewal notices are waiting right now.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Submitted</th><th>Customer</th><th>Plan</th><th>Billing</th><th>Amount</th><th>Source</th></tr></thead>
                            <tbody>
                            <?php foreach (array_slice((array) ($zellePayments['notices'] ?? []), 0, 12) as $notice): ?>
                                <tr>
                                    <td><strong><?= admin_escape(admin_format_datetime((string) ($notice['submitted_at'] ?? null))) ?></strong><small><?= admin_escape((string) ($notice['office_name'] ?? $notice['customer_name'] ?? '')) ?></small></td>
                                    <td><strong><?= admin_escape((string) ($notice['customer_email'] ?? $notice['payer_email'] ?? 'No email saved')) ?></strong><small><?= admin_escape((string) ($notice['customer_phone'] ?? $notice['payer_phone'] ?? $notice['payment_source'] ?? '')) ?></small></td>
                                    <td><strong><?= admin_escape(cs_site_admin_badge_label((string) ($notice['plan'] ?? 'checkout'))) ?></strong><small><?= admin_escape((string) ($notice['plan_name'] ?? '')) ?></small></td>
                                    <td><strong><?= admin_escape(cs_site_admin_badge_label((string) ($notice['billing'] ?? 'monthly'))) ?></strong><small><?= admin_escape((string) ($notice['source'] ?? ($notice['_source_path'] ?? ''))) ?></small></td>
                                    <td>
                                        <strong>Sent <?= admin_escape(cs_site_zelle_money(isset($notice['payment_amount_sent']) ? (float) $notice['payment_amount_sent'] : (isset($notice['amount']) ? (float) $notice['amount'] : null))) ?></strong>
                                        <small>Expected <?= admin_escape(cs_site_zelle_money(isset($notice['amount']) ? (float) $notice['amount'] : null)) ?></small>
                                        <?php if (! empty($notice['payment_memo_email'])): ?><small>Memo: <?= admin_escape((string) $notice['payment_memo_email']) ?></small><?php endif; ?>
                                        <?php if (! empty($notice['payment_transaction_id'])): ?><small>Code: <?= admin_escape((string) $notice['payment_transaction_id']) ?></small><?php endif; ?>
                                    </td>
                                    <td><strong><?= admin_escape(basename((string) ($notice['_source_path'] ?? ''))) ?></strong><small><?= admin_escape((string) ($notice['ip_address'] ?? '')) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card section-card<?= admin_panel_visible('customers', $panel) ?>">
                <div class="section-head">
                    <div>
                        <span class="eyebrow">Customer lane</span>
                        <h2>Customers tied to website or licenses</h2>
                        <p>This is the quick read on who owns a license, what office they are tied to, and whether they already exist in the site lead funnel.</p>
                    </div>
                </div>

                <?php if (! $licenseData['available']): ?>
                    <div class="empty">Customers will populate here once the license tables are available on this host.</div>
                <?php elseif ($customers === []): ?>
                    <article class="plan-card" id="create-license">
                        <div class="section-head">
                            <div>
                                <span class="eyebrow">Quick create</span>
                                <h2 style="font-size:22px;">Create the first license</h2>
                                <p>Make a key right here without leaving the customers lane.</p>
                            </div>
                        </div>
                        <form method="post" class="admin-form-grid">
                            <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
                            <input type="hidden" name="action" value="create_license">
                            <input type="hidden" name="panel" value="customers">
                            <div class="admin-form-grid is-two">
                                <div class="admin-field">
                                    <label for="customer-name-inline-empty">Customer name</label>
                                    <input class="admin-input" id="customer-name-inline-empty" type="text" name="customer_name" placeholder="Office or owner name">
                                </div>
                                <div class="admin-field">
                                    <label for="customer-email-inline-empty">Customer email</label>
                                    <input class="admin-input" id="customer-email-inline-empty" type="email" name="customer_email" placeholder="owner@office.com" required>
                                </div>
                            </div>
                            <div class="admin-form-grid is-two">
                                <div class="admin-field">
                                    <label for="plan-key-inline-empty">Plan</label>
                                    <select class="admin-select" id="plan-key-inline-empty" name="plan_key">
                                        <?php foreach (($pricing['plans'] ?? []) as $planKey => $plan): ?>
                                            <option value="<?= admin_escape($planKey) ?>"><?= admin_escape((string) ($plan['name'] ?? $planKey)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="admin-field">
                                    <label for="duration-inline-empty">Billing cycle</label>
                                    <select class="admin-select" id="duration-inline-empty" name="duration">
                                        <option value="monthly">Monthly</option>
                                        <option value="yearly">Yearly</option>
                                        <option value="lifetime">Lifetime</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="hero-btn is-primary">Create license</button>
                                <a class="hero-btn" href="<?= admin_url('/', ['panel' => 'licenses']) ?>">Open full license lane</a>
                            </div>
                        </form>
                    </article>
                <?php else: ?>
                    <article class="plan-card" id="create-license">
                        <div class="section-head">
                            <div>
                                <span class="eyebrow">Quick create</span>
                                <h2 style="font-size:22px;">Create a license from the customer lane</h2>
                                <p>Issue a new key here, or jump into the full license panel if you want the wider billing view.</p>
                            </div>
                        </div>
                        <form method="post" class="admin-form-grid">
                            <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
                            <input type="hidden" name="action" value="create_license">
                            <input type="hidden" name="panel" value="customers">
                            <div class="admin-form-grid is-two">
                                <div class="admin-field">
                                    <label for="customer-name-inline">Customer name</label>
                                    <input class="admin-input" id="customer-name-inline" type="text" name="customer_name" placeholder="Office or owner name">
                                </div>
                                <div class="admin-field">
                                    <label for="customer-email-inline">Customer email</label>
                                    <input class="admin-input" id="customer-email-inline" type="email" name="customer_email" placeholder="owner@office.com" required>
                                </div>
                            </div>
                            <div class="admin-form-grid is-two">
                                <div class="admin-field">
                                    <label for="plan-key-inline">Plan</label>
                                    <select class="admin-select" id="plan-key-inline" name="plan_key">
                                        <?php foreach (($pricing['plans'] ?? []) as $planKey => $plan): ?>
                                            <option value="<?= admin_escape($planKey) ?>"><?= admin_escape((string) ($plan['name'] ?? $planKey)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="admin-field">
                                    <label for="duration-inline">Billing cycle</label>
                                    <select class="admin-select" id="duration-inline" name="duration">
                                        <option value="monthly">Monthly</option>
                                        <option value="yearly">Yearly</option>
                                        <option value="lifetime">Lifetime</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="hero-btn is-primary">Create license</button>
                                <a class="hero-btn" href="<?= admin_url('/', ['panel' => 'licenses']) ?>">Open full license lane</a>
                            </div>
                        </form>
                    </article>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Customer</th><th>Company</th><th>Phone</th><th>Lead status</th><th>Licenses</th><th>Plans</th><th>Latest expiration</th></tr></thead>
                            <tbody>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td><strong><?= admin_escape((string) (($customer['display_name'] ?: $customer['customer_email']) ?? 'Unknown customer')) ?></strong><small><?= admin_escape((string) ($customer['customer_email'] ?? '')) ?></small></td>
                                    <td><strong><?= admin_escape((string) ($customer['company'] ?? 'No company saved')) ?></strong></td>
                                    <td><strong><?= admin_escape((string) ($customer['phone'] ?? 'No phone saved')) ?></strong></td>
                                    <td><strong><?= admin_escape(cs_site_admin_badge_label((string) ($customer['lead_status'] ?? 'not tracked'))) ?></strong></td>
                                    <td><strong><?= (int) ($customer['license_count'] ?? 0) ?> total</strong><small><?= (int) ($customer['active_licenses'] ?? 0) ?> active</small></td>
                                    <td><strong><?= admin_escape((string) ($customer['plans'] ?? 'No plans')) ?></strong></td>
                                    <td><strong><?= admin_format_datetime($customer['latest_expiration'] ?? null) ?></strong><small><?= admin_format_datetime($customer['latest_created'] ?? null) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card section-card<?= admin_panel_visible('leads', $panel) ?>">
                <div class="section-head"><div><span class="eyebrow">Lead table</span><h2>All saved leads</h2><p>Joined website leads plus office-fit qualification details, ordered newest first.</p></div></div>
                <?php if ($leads === []): ?>
                    <div class="empty">No leads have been saved yet.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Lead</th><th>Source</th><th>Status</th><th>Clients</th><th>Monitoring</th><th>Current stack</th><th>Merchant</th><th>Website / Outsourcing</th><th>Created</th></tr></thead>
                            <tbody>
                            <?php foreach ($leads as $lead): ?>
                                <tr>
                                    <td><strong><?= admin_escape($lead['name'] ?: 'Unnamed lead') ?></strong><small><?= admin_escape($lead['email']) ?></small><small><?= admin_escape(trim(((string) ($lead['company'] ?? '')) . (($lead['phone'] ?? '') ? ' · ' . (string) $lead['phone'] : ''))) ?></small></td>
                                    <td><strong><?= admin_escape(cs_site_admin_badge_label((string) ($lead['source'] ?? 'website'))) ?></strong><small><?= admin_escape((string) ($lead['plan_interest'] ?? '')) ?></small></td>
                                    <td><strong><?= admin_escape(cs_site_admin_badge_label((string) ($lead['status'] ?? 'new'))) ?></strong><small><?= admin_escape((string) ($lead['roi_visibility'] ?? '')) ?></small></td>
                                    <td><strong><?= admin_escape($lead['client_count'] ?: 'Unknown') ?></strong><small><?= admin_escape($lead['team_size'] ?: '') ?></small></td>
                                    <td><strong><?= admin_escape($lead['monitoring_systems'] ?: 'Not saved') ?></strong><small><?= admin_escape($lead['switch_timeline'] ?: '') ?></small></td>
                                    <td><strong><?= admin_escape($lead['current_workflow'] ?: 'Not saved') ?></strong><small><?= admin_escape($lead['primary_goal'] ?: '') ?></small></td>
                                    <td><strong><?= admin_escape($lead['merchant_status'] ?: 'Unknown') ?></strong><small><?= admin_escape($lead['merchant_provider'] ?: ($lead['payment_methods'] ?: '')) ?></small></td>
                                    <td><strong><?= admin_escape($lead['website_status'] ?: 'Unknown') ?><?= $lead['website_sentiment'] ? ' · ' . admin_escape($lead['website_sentiment']) : '' ?></strong><small><?= admin_escape($lead['outsourcing_status'] ?: '') ?><?= $lead['outsourcing_notes'] ? ' · ' . admin_escape($lead['outsourcing_notes']) : '' ?></small></td>
                                    <td><strong><?= admin_escape(date('M j, Y', strtotime((string) $lead['created_at']))) ?></strong><small><?= admin_escape(date('g:i a', strtotime((string) $lead['created_at']))) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card section-card<?= admin_panel_visible('assessments', $panel) ?>">
                <div class="section-head"><div><span class="eyebrow">Quiz and red-flag traffic</span><h2>Assessment leads</h2><p>These are the public quiz submissions flowing through the website, separate from the office-fit intake.</p></div></div>
                <?php if ($assessments === []): ?>
                    <div class="empty">No assessment results are saved yet.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Lead</th><th>Assessment</th><th>Score</th><th>Coupon</th><th>Submitted</th></tr></thead>
                            <tbody>
                            <?php foreach ($assessments as $assessment): ?>
                                <tr>
                                    <td><strong><?= admin_escape($assessment['name'] ?: 'Unnamed lead') ?></strong><small><?= admin_escape($assessment['email'] ?: '') ?></small></td>
                                    <td><strong><?= admin_escape($assessment['assessment_label'] ?: cs_site_admin_badge_label((string) ($assessment['source'] ?? 'assessment'))) ?></strong><small><?= admin_escape(cs_site_admin_badge_label((string) ($assessment['source'] ?? 'assessment'))) ?></small></td>
                                    <td><strong><?= $assessment['score'] !== null ? (int) $assessment['score'] : '—' ?><?= $assessment['max_score'] !== null ? '/' . (int) $assessment['max_score'] : '' ?></strong></td>
                                    <td><strong><?= admin_escape($assessment['coupon_code'] ?: 'No coupon') ?></strong></td>
                                    <td><strong><?= admin_escape(date('M j, Y', strtotime((string) $assessment['created_at']))) ?></strong><small><?= admin_escape(date('g:i a', strtotime((string) $assessment['created_at']))) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="overview-grid<?= admin_panel_visible('ops', $panel) ?>">
                <article class="card section-card">
                    <div class="section-head"><div><span class="eyebrow">Site ops</span><h2>What this admin lane controls</h2><p>This is the public website side, not the intranet. Keep it focused on lead intake, portal entry, public content, pricing, and site-side follow-up.</p></div></div>
                    <div class="pill-list"><?php foreach ($topSources ?: ['No lead sources yet' => 0] as $source => $count): ?><span class="pill"><?= admin_escape(cs_site_admin_badge_label($source)) ?><?= $count ? ' · ' . (int) $count : '' ?></span><?php endforeach; ?></div>
                    <div class="note-box">The website admin lane is where you watch the intake funnel, portal entry points, fit-checks, quizzes, pricing, and website ops. It should stay separate from the intranet casework lane so public-site work never muddies the office production stack.</div>
                </article>
                <article class="card section-card">
                    <div class="section-head"><div><span class="eyebrow">Security and routing</span><h2>Current status</h2></div></div>
                    <div class="pill-list">
                        <span class="pill"><?= $dashboard['database_connected'] ? 'Lead database connected' : 'Lead database unavailable' ?></span>
                        <span class="pill"><?= $dashboard['turnstile_enabled'] ? 'Turnstile verified on login' : 'Turnstile not configured' ?></span>
                        <span class="pill"><?= $adminTwoFactorEnabled ? 'Admin 2FA enabled' : 'Admin 2FA not enabled yet' ?></span>
                        <span class="pill"><?= $licenseData['available'] ? 'License tables detected' : 'License tables missing' ?></span>
                        <span class="pill">Portal entry at <?= admin_escape(parse_url(cs_site_public_url('/client-portal'), PHP_URL_PATH) ?: '/client-portal') ?></span>
                        <span class="pill">Admin entry at <?= admin_escape(cs_site_has_admin_subdomain() ? ('https://' . cs_site_admin_host() . '/login') : '/admin/login') ?></span>
                    </div>
                    <div class="note-box">If you later want more public-site admin pages, this is the shell to extend: same rail, same top bar, and more menu items added over time instead of rebuilding the lane again. <a href="<?= admin_url('/two-factor') ?>">Open the 2FA page</a>.</div>
                </article>
            </section>

            <?php require __DIR__ . '/seo-panel.php'; ?>

            <section class="overview-grid<?= admin_panel_visible('social', $panel) ?>">
                <article class="card section-card social-overview">
                    <div class="social-showcase">
                        <div class="section-head">
                            <div class="social-brand-row">
                                <div class="social-brand-stack" aria-hidden="true">
                                    <span class="social-platform-mark is-meta"><?= admin_brand_mark('meta') ?></span>
                                    <span class="social-platform-mark is-whatsapp"><?= admin_brand_mark('whatsapp') ?></span>
                                </div>
                                <div class="social-brand-copy">
                                    <span class="eyebrow">Social / Meta command</span>
                                    <h2>Meta traffic, channel identity, and WhatsApp handoff in one lane.</h2>
                                    <p>Keep the growth-side stack tight: measurement, brand surfaces, reply work, and the chat handoff that should catch warmer leads before they drift away.</p>
                                </div>
                            </div>
                            <div class="info-pop">
                                <button type="button" class="info-pop-trigger" aria-label="Meta control help">?</button>
                                <div class="info-pop-panel">
                                    <div class="info-pop-title">What belongs here</div>
                                    <div class="info-pop-copy">This page should answer four questions fast: is tracking live, are server-side events ready, are the channel identities saved, and can the office move a lead into WhatsApp without friction.</div>
                                </div>
                            </div>
                        </div>
                        <div class="social-kicker-row">
                            <span class="social-kicker-pill">Meta traffic</span>
                            <span class="social-kicker-pill">WhatsApp handoff</span>
                            <span class="social-kicker-pill">Lead sync</span>
                            <span class="social-kicker-pill">Reply queue</span>
                        </div>
                    </div>
                    <div class="social-signal-grid">
                        <article class="social-signal is-blue">
                            <span class="eyebrow">Browser tracking</span>
                            <h3><?= ! empty($siteTracking['meta_pixel_id']) ? 'Pixel live on the site' : 'Pixel still needs setup' ?></h3>
                            <p><?= ! empty($siteTracking['meta_pixel_id']) ? 'PageView is already attached to the public site.' : 'Add the Pixel ID so browser traffic starts landing in Events Manager.' ?></p>
                            <span class="social-signal-meta<?= ! empty($siteTracking['meta_pixel_id']) ? ' is-good' : ' is-alert' ?>"><?= ! empty($siteTracking['meta_pixel_id']) ? 'Pixel ready' : 'Pixel missing' ?></span>
                        </article>
                        <article class="social-signal is-green">
                            <span class="eyebrow">Server-side events</span>
                            <h3><?= ! empty($siteTracking['meta_pixel_id']) && ! empty($siteTracking['meta_capi_token']) ? 'Lead events can post from PHP' : 'CAPI still needs one last piece' ?></h3>
                            <p><?= ! empty($siteTracking['meta_pixel_id']) && ! empty($siteTracking['meta_capi_token']) ? 'Lead and intake events can now send from the backend too.' : 'Save the server token with the Pixel ID so lead events are not browser-only.' ?></p>
                            <span class="social-signal-meta<?= ! empty($siteTracking['meta_pixel_id']) && ! empty($siteTracking['meta_capi_token']) ? ' is-good' : ' is-alert' ?>"><?= ! empty($siteTracking['meta_pixel_id']) && ! empty($siteTracking['meta_capi_token']) ? 'CAPI ready' : 'CAPI incomplete' ?></span>
                        </article>
                        <article class="social-signal is-warm">
                            <span class="eyebrow">WhatsApp handoff</span>
                            <h3><?= $whatsAppReady ? 'WhatsApp can catch warm leads fast' : ($whatsAppEnabled ? 'WhatsApp is staged but still needs one more pass' : 'WhatsApp handoff is still off') ?></h3>
                            <p><?= $whatsAppReady ? ($whatsAppDisplayNumber !== '' ? ('Leads can move into ' . admin_escape($whatsAppDisplayNumber) . ' with a prefilled handoff message.') : 'The business number lane is ready for click-to-chat and warm follow-up.') : ($whatsAppEnabled ? 'Finish the business number details so Meta ads and site visitors have a clean chat destination.' : 'Turn on WhatsApp support so the office has a faster handoff than email alone.') ?></p>
                            <span class="social-signal-meta<?= $whatsAppReady ? ' is-good' : ' is-alert' ?>"><?= $whatsAppReady ? 'WhatsApp ready' : ($whatsAppEnabled ? 'Needs number details' : 'WhatsApp off') ?></span>
                        </article>
                        <article class="social-signal">
                            <span class="eyebrow">Connected channels</span>
                            <h3><?= $channelsReady ? ($channelCount . ' public social surfaces are mapped in') : 'Channel IDs still need to land here' ?></h3>
                            <p><?= $channelsReady ? 'Facebook, Instagram, Threads, X, and WhatsApp can now live in one source of truth instead of scattered notes.' : 'Save the public page and channel identities here so the lane stops depending on memory.' ?></p>
                            <span class="social-signal-meta<?= $channelsReady ? ' is-good' : '' ?>"><?= $channelsReady ? 'Channels mapped' : 'Channels waiting' ?></span>
                        </article>
                    </div>
                </article>
                <article class="card section-card">
                    <div class="section-head">
                        <div class="section-head-copy">
                            <span class="eyebrow">Quick links</span>
                            <h2>Jump into the live surfaces</h2>
                            <p>Keep the few real tools close: events, ads, public channels, and the WhatsApp handoff itself.</p>
                        </div>
                    </div>
                    <div class="social-link-list">
                        <a class="social-link-chip is-dark" href="<?= admin_escape(creditsoft_site_tracking_events_manager_url($siteTracking)) ?>" target="_blank" rel="noopener">Events Manager</a>
                        <a class="social-link-chip" href="<?= admin_escape(creditsoft_site_tracking_app_dashboard_url($siteTracking)) ?>" target="_blank" rel="noopener">Meta App</a>
                        <a class="social-link-chip" href="<?= admin_escape(creditsoft_site_tracking_ads_manager_url($siteTracking)) ?>" target="_blank" rel="noopener">Ads Manager</a>
                        <a class="social-link-chip" href="<?= admin_escape(creditsoft_site_tracking_threads_profile_url($siteTracking)) ?>" target="_blank" rel="noopener">Threads</a>
                        <a class="social-link-chip" href="<?= admin_escape(creditsoft_site_tracking_public_x_url($siteTracking) ?? 'https://x.com/') ?>" target="_blank" rel="noopener">X</a>
                        <?php if ($whatsAppChatUrl !== null): ?>
                            <a class="social-link-chip is-whatsapp" href="<?= admin_escape($whatsAppChatUrl) ?>" target="_blank" rel="noopener">WhatsApp chat</a>
                        <?php else: ?>
                            <a class="social-link-chip is-whatsapp" href="#whatsapp-support">WhatsApp setup</a>
                        <?php endif; ?>
                    </div>
                    <details class="social-collapse">
                        <summary>
                            <span class="social-collapse-label">
                                <strong>Show setup notes</strong>
                                <span>Readiness, app-vs-pixel reminder, and what still needs to be connected.</span>
                            </span>
                            <span class="social-collapse-pill">Open</span>
                        </summary>
                        <div class="social-collapse-body">
                            <div class="social-readiness">
                                <div class="social-readiness-row">
                                    <div class="social-readiness-copy"><strong>Tracking core</strong><span>Pixel plus server-side event token</span></div>
                                    <span class="social-state-dot<?= ! empty($siteTracking['meta_pixel_id']) && ! empty($siteTracking['meta_capi_token']) ? ' is-good' : '' ?>"></span>
                                </div>
                                <div class="social-readiness-row">
                                    <div class="social-readiness-copy"><strong>App and ad account</strong><span>Meta app ID and ad account for traffic work</span></div>
                                    <span class="social-state-dot<?= ! empty($siteTracking['meta_app_id']) && ! empty($siteTracking['meta_ad_account_id']) ? ' is-good' : '' ?>"></span>
                                </div>
                                <div class="social-readiness-row">
                                    <div class="social-readiness-copy"><strong>Channel identities</strong><span>Facebook, Instagram, Threads, and X source-of-truth</span></div>
                                    <span class="social-state-dot<?= $channelsReady ? ' is-good' : '' ?>"></span>
                                </div>
                                <div class="social-readiness-row">
                                    <div class="social-readiness-copy"><strong>WhatsApp handoff</strong><span>Business number and chat destination for warmer leads</span></div>
                                    <span class="social-state-dot<?= $whatsAppReady ? ' is-good' : '' ?>"></span>
                                </div>
                            </div>
                            <div class="note-box">The App ID and the Pixel ID are different objects. Website traffic shows up under the Pixel, not the app dashboard. WhatsApp belongs in this same lane because the Meta business, ads, and chat handoff should all stay mapped together.</div>
                        </div>
                    </details>
                </article>
            </section>

            <section class="card section-card<?= admin_panel_visible('social', $panel) ?>">
                <div class="section-head">
                    <div class="section-head-copy">
                        <span class="eyebrow">Meta configuration</span>
                        <h2>Tracking, channels, WhatsApp, and ad defaults</h2>
                        <p>Keep the live setup visible in four focused groups so the lane reads cleanly even before you open the deeper notes.</p>
                    </div>
                </div>
                <form method="post" class="admin-form-grid" data-autosave="true">
                    <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
                    <input type="hidden" name="action" value="save_tracking">
                    <input type="hidden" name="panel" value="social">
                    <div class="social-form-shell">
                        <div class="social-form-grid">
                            <article class="social-form-card">
                                <div class="section-head">
                                    <div>
                                        <span class="eyebrow">Tracking core</span>
                                        <h3>Browser and server events</h3>
                                        <p>Pixel, CAPI, test events, and webhook verification.</p>
                                    </div>
                                    <div class="info-pop">
                                        <button type="button" class="info-pop-trigger" aria-label="Tracking core help">?</button>
                                        <div class="info-pop-panel">
                                            <div class="info-pop-title">Tracking core</div>
                                            <ul class="info-pop-list">
                                                <li>Pixel ID powers browser events.</li>
                                                <li>CAPI token powers server-side lead events.</li>
                                                <li>Management token is the broader Graph token for lead forms, ads, publishing, and replies.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="admin-field">
                                    <label for="google_measurement_id">Google measurement ID</label>
                                    <input class="admin-input" id="google_measurement_id" name="google_measurement_id" type="text" value="<?= admin_escape((string) ($siteTracking['google_measurement_id'] ?? '')) ?>" placeholder="G-XXXXXXXXXX">
                                </div>
                                <div class="admin-field">
                                    <label for="meta_pixel_id">Meta Pixel ID</label>
                                    <input class="admin-input" id="meta_pixel_id" name="meta_pixel_id" type="text" inputmode="numeric" value="<?= admin_escape((string) ($siteTracking['meta_pixel_id'] ?? '')) ?>" placeholder="123456789012345">
                                </div>
                                <div class="admin-field">
                                    <label for="meta_capi_token">Meta CAPI access token</label>
                                    <input class="admin-input" id="meta_capi_token" name="meta_capi_token" type="password" value="" placeholder="<?= admin_escape($metaCapiTokenHint !== '' ? $metaCapiTokenHint : 'Leave blank to keep the saved token') ?>">
                                    <?php if ($metaCapiTokenHint !== ''): ?><div class="helper-copy">Current saved token stays in place unless you paste a replacement.</div><?php endif; ?>
                                </div>
                                <div class="admin-field">
                                    <label for="meta_management_token">Meta management token</label>
                                    <input class="admin-input" id="meta_management_token" name="meta_management_token" type="password" value="" placeholder="<?= admin_escape($metaManagementTokenHint !== '' ? $metaManagementTokenHint : 'Use this for lead sync, ads, posting, and replies') ?>">
                                    <?php if ($metaManagementTokenHint !== ''): ?><div class="helper-copy">This is the broader Graph token for lead forms, ad insights, Facebook publishing, and reply sync.</div><?php endif; ?>
                                </div>
                                <div class="admin-field">
                                    <label for="meta_capi_test_event_code">Meta test event code</label>
                                    <input class="admin-input" id="meta_capi_test_event_code" name="meta_capi_test_event_code" type="text" value="<?= admin_escape((string) ($siteTracking['meta_capi_test_event_code'] ?? '')) ?>" placeholder="Optional test code for Events Manager">
                                </div>
                                <div class="admin-field">
                                    <label for="lead_form_name">Lead form name</label>
                                    <input class="admin-input" id="lead_form_name" name="lead_form_name" type="text" value="<?= admin_escape((string) ($siteTracking['lead_form_name'] ?? '')) ?>" placeholder="CreditSoft office fit check">
                                </div>
                                <div class="admin-field">
                                    <label for="meta_webhook_verify_token">Meta webhook verify token</label>
                                    <input class="admin-input" id="meta_webhook_verify_token" name="meta_webhook_verify_token" type="text" value="<?= admin_escape((string) ($siteTracking['meta_webhook_verify_token'] ?? '')) ?>" placeholder="Used for /api/meta-webhook.php verification">
                                </div>
                            </article>

                            <article class="social-form-card">
                                <div class="section-head">
                                    <div>
                                        <span class="eyebrow">Public channels</span>
                                        <h3>Meta identities</h3>
                                        <p>Saved IDs for the public brand surfaces.</p>
                                    </div>
                                    <div class="info-pop">
                                        <button type="button" class="info-pop-trigger" aria-label="Meta identities help">?</button>
                                        <div class="info-pop-panel">
                                            <div class="info-pop-title">Identity mapping</div>
                                            <div class="info-pop-copy">This is the source-of-truth lane for the public Page, Instagram business account, and Threads identity so later posting and reporting do not depend on memory.</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="admin-field">
                                    <label for="meta_app_id">Meta App ID</label>
                                    <input class="admin-input" id="meta_app_id" name="meta_app_id" type="text" inputmode="numeric" value="<?= admin_escape((string) ($siteTracking['meta_app_id'] ?? '')) ?>" placeholder="Meta app dashboard ID">
                                </div>
                                <div class="admin-field">
                                    <label for="meta_business_id">Meta Business ID</label>
                                    <input class="admin-input" id="meta_business_id" name="meta_business_id" type="text" inputmode="numeric" value="<?= admin_escape((string) ($siteTracking['meta_business_id'] ?? '')) ?>" placeholder="Optional business manager ID">
                                </div>
                                <div class="admin-field">
                                    <label for="facebook_page_id">Facebook Page ID</label>
                                    <input class="admin-input" id="facebook_page_id" name="facebook_page_id" type="text" inputmode="numeric" value="<?= admin_escape((string) ($siteTracking['facebook_page_id'] ?? '')) ?>" placeholder="Connected public page ID">
                                </div>
                                <div class="admin-field">
                                    <label for="facebook_username">Facebook username</label>
                                    <input class="admin-input" id="facebook_username" name="facebook_username" type="text" value="<?= admin_escape((string) ($siteTracking['facebook_username'] ?? 'GetCreditSoft')) ?>" placeholder="GetCreditSoft">
                                </div>
                                <div class="admin-field">
                                    <label for="instagram_business_id">Instagram business ID</label>
                                    <input class="admin-input" id="instagram_business_id" name="instagram_business_id" type="text" inputmode="numeric" value="<?= admin_escape((string) ($siteTracking['instagram_business_id'] ?? '')) ?>" placeholder="Instagram business account ID">
                                </div>
                                <div class="admin-field">
                                    <label for="instagram_username">Instagram username</label>
                                    <input class="admin-input" id="instagram_username" name="instagram_username" type="text" value="<?= admin_escape((string) ($siteTracking['instagram_username'] ?? '')) ?>" placeholder="@creditsoftapp">
                                </div>
                                <div class="admin-field">
                                    <label for="threads_profile_id">Threads profile ID</label>
                                    <input class="admin-input" id="threads_profile_id" name="threads_profile_id" type="text" inputmode="numeric" value="<?= admin_escape((string) ($siteTracking['threads_profile_id'] ?? '')) ?>" placeholder="Threads profile/account ID">
                                </div>
                                <div class="admin-field">
                                    <label for="threads_username">Threads username</label>
                                    <input class="admin-input" id="threads_username" name="threads_username" type="text" value="<?= admin_escape((string) ($siteTracking['threads_username'] ?? '')) ?>" placeholder="@creditsoftapp">
                                </div>
                                <div class="admin-field">
                                    <label for="x_username">X username</label>
                                    <input class="admin-input" id="x_username" name="x_username" type="text" value="<?= admin_escape((string) ($siteTracking['x_username'] ?? '')) ?>" placeholder="@creditsoftapp">
                                </div>
                            </article>

                            <article class="social-form-card is-whatsapp">
                                <div class="section-head">
                                    <div>
                                        <span class="eyebrow">WhatsApp support</span>
                                        <h3>Business number and handoff</h3>
                                        <p>Store the business number, Meta-side IDs, and the default handoff copy in the same lane as the rest of the social setup.</p>
                                    </div>
                                    <div class="info-pop">
                                        <button type="button" class="info-pop-trigger" aria-label="WhatsApp support help">?</button>
                                        <div class="info-pop-panel">
                                            <div class="info-pop-title">Why it belongs here</div>
                                            <div class="info-pop-copy">If a lead comes from Meta or the website, the office should be able to move them into chat without hunting through another settings page. Keep the number, the Meta business IDs, and the prefilled message together.</div>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="whatsapp_enabled" value="0">
                                <label class="admin-checkbox" for="whatsapp_enabled">
                                    <input id="whatsapp_enabled" name="whatsapp_enabled" type="checkbox" value="1"<?= $whatsAppEnabled ? ' checked' : '' ?>>
                                    Enable WhatsApp handoff inside the social lane
                                </label>
                                <div class="admin-field">
                                    <label for="whatsapp_display_number">WhatsApp display number</label>
                                    <input class="admin-input" id="whatsapp_display_number" name="whatsapp_display_number" type="text" value="<?= admin_escape($whatsAppDisplayNumber) ?>" placeholder="+1 (555) 123-4567">
                                </div>
                                <div class="admin-field">
                                    <label for="whatsapp_phone_number_id">WhatsApp phone number ID</label>
                                    <input class="admin-input" id="whatsapp_phone_number_id" name="whatsapp_phone_number_id" type="text" inputmode="numeric" value="<?= admin_escape((string) ($siteTracking['whatsapp_phone_number_id'] ?? '')) ?>" placeholder="Meta phone number ID">
                                </div>
                                <div class="admin-field">
                                    <label for="whatsapp_business_account_id">WhatsApp business account ID</label>
                                    <input class="admin-input" id="whatsapp_business_account_id" name="whatsapp_business_account_id" type="text" inputmode="numeric" value="<?= admin_escape((string) ($siteTracking['whatsapp_business_account_id'] ?? '')) ?>" placeholder="Meta WABA ID">
                                </div>
                                <div class="admin-field">
                                    <label for="whatsapp_verify_token">WhatsApp verify token</label>
                                    <input class="admin-input" id="whatsapp_verify_token" name="whatsapp_verify_token" type="text" value="<?= admin_escape((string) ($siteTracking['whatsapp_verify_token'] ?? '')) ?>" placeholder="Optional webhook verify token">
                                </div>
                                <div class="admin-field">
                                    <label for="whatsapp_default_message">Default click-to-chat message</label>
                                    <textarea class="admin-textarea" id="whatsapp_default_message" name="whatsapp_default_message" placeholder="Hi CreditSoft, I want to learn more about the software."><?= admin_escape((string) ($siteTracking['whatsapp_default_message'] ?? '')) ?></textarea>
                                </div>
                            </article>

                            <article class="social-form-card">
                                <div class="section-head">
                                    <div>
                                        <span class="eyebrow">Ad defaults</span>
                                        <h3>Budget and traffic rules</h3>
                                        <p>Ad account, objective, and budget baseline.</p>
                                    </div>
                                    <div class="info-pop">
                                        <button type="button" class="info-pop-trigger" aria-label="Ad defaults help">?</button>
                                        <div class="info-pop-panel">
                                            <div class="info-pop-title">Budget helper</div>
                                            <div class="info-pop-copy">This is where we keep the small-budget starter lane grounded. For CreditSoft, a lean weekly number is more useful than pretending we are managing a giant ad spend.</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="admin-field">
                                    <label for="meta_ad_account_id">Meta ad account ID</label>
                                    <input class="admin-input" id="meta_ad_account_id" name="meta_ad_account_id" type="text" value="<?= admin_escape((string) ($siteTracking['meta_ad_account_id'] ?? '')) ?>" placeholder="act_1234567890">
                                </div>
                                <div class="admin-field">
                                    <label for="campaign_objective">Campaign objective</label>
                                    <select class="admin-input" id="campaign_objective" name="campaign_objective">
                                        <?php foreach (['OUTCOME_LEADS', 'OUTCOME_TRAFFIC', 'OUTCOME_SALES', 'OUTCOME_AWARENESS'] as $objective): ?>
                                            <option value="<?= admin_escape($objective) ?>"<?= ($siteTracking['campaign_objective'] ?? 'OUTCOME_LEADS') === $objective ? ' selected' : '' ?>><?= admin_escape($objective) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="admin-field">
                                    <label for="weekly_budget">Weekly budget</label>
                                    <input class="admin-input" id="weekly_budget" name="weekly_budget" type="text" inputmode="decimal" value="<?= admin_escape((string) ($siteTracking['weekly_budget'] ?? '')) ?>" placeholder="10.00">
                                </div>
                                <div class="admin-field">
                                    <label for="daily_cap">Daily cap</label>
                                    <input class="admin-input" id="daily_cap" name="daily_cap" type="text" inputmode="decimal" value="<?= admin_escape((string) ($siteTracking['daily_cap'] ?? '')) ?>" placeholder="25.00">
                                </div>
                                <div class="admin-field">
                                    <label for="monthly_cap">Monthly cap</label>
                                    <input class="admin-input" id="monthly_cap" name="monthly_cap" type="text" inputmode="decimal" value="<?= admin_escape((string) ($siteTracking['monthly_cap'] ?? '')) ?>" placeholder="500.00">
                                </div>
                                <?php if ($weeklyBudgetValue > 0): ?>
                                    <div class="social-budget-note">A weekly budget of <strong>$<?= admin_escape(number_format($weeklyBudgetValue, 2)) ?></strong> works out to about <strong>$<?= admin_escape(number_format((float) $suggestedDailyCap, 2)) ?>/day</strong> and <strong>$<?= admin_escape(number_format((float) $suggestedMonthlyCap, 2)) ?>/month</strong>. That is enough to test real traffic without pretending this is a giant spend.</div>
                                <?php else: ?>
                                    <div class="social-budget-note">For a lean starter lane, put <strong>10.00</strong> in weekly budget. CreditSoft will treat that like roughly <strong>$1.43/day</strong> or <strong>$43.33/month</strong> when we shape the reporting and ad helper around it.</div>
                                <?php endif; ?>
                            </article>
                        </div>
                        <details class="social-collapse">
                            <summary>
                                <span class="social-collapse-label">
                                    <strong>Open the field guide</strong>
                                    <span>What the Meta and WhatsApp fields actually do.</span>
                                </span>
                                <span class="social-collapse-pill">Open</span>
                            </summary>
                            <div class="social-collapse-body">
                                <div class="helper-copy">Google is already running site-wide. The Meta Pixel ID powers browser tracking, and the Meta CAPI token lets CreditSoft send server-side starter-lead, qualified-intake, and assessment events from the website backend. The management token is the broader Graph token for lead forms, ads, Page posting, and comment replies. Instagram and Threads do not auto-connect just because the Meta app exists, so we save those channel IDs here too. The WhatsApp fields keep the business number, WABA mapping, and default click-to-chat message in the same control lane. Leave token fields blank when you are only updating other settings and want to keep the saved value.</div>
                            </div>
                        </details>
                        <div class="autosave-note"><span>Leave a field and the social config saves automatically.</span><span class="autosave-status" aria-live="polite"></span></div>
                    </div>
                </form>
            </section>

            <section id="whatsapp-support" class="card section-card<?= admin_panel_visible('social', $panel) ?>">
                <div class="section-head">
                    <div class="section-head-copy">
                        <span class="eyebrow">WhatsApp handoff</span>
                        <h2>Warm leads can move into chat without leaving the lane</h2>
                        <p>This is the quick read for screenshots and daily use: whether WhatsApp is on, which number it points to, and whether the prefilled handoff message is ready.</p>
                    </div>
                    <div class="social-action-bar">
                        <?php if ($whatsAppChatUrl !== null): ?>
                            <a class="hero-btn is-primary" href="<?= admin_escape($whatsAppChatUrl) ?>" target="_blank" rel="noopener">Open chat link</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="social-summary-grid">
                    <article class="social-summary-card">
                        <span class="eyebrow">Lane</span>
                        <strong><?= $whatsAppReady ? 'Ready' : ($whatsAppEnabled ? 'Setup' : 'Off') ?></strong>
                        <span><?= $whatsAppReady ? 'The chat handoff is ready to use.' : ($whatsAppEnabled ? 'The lane is on, but it still needs more detail.' : 'WhatsApp is not enabled yet.') ?></span>
                    </article>
                    <article class="social-summary-card">
                        <span class="eyebrow">Display number</span>
                        <strong><?= admin_escape($whatsAppDisplayNumber !== '' ? $whatsAppDisplayNumber : '—') ?></strong>
                        <span><?= $whatsAppDisplayNumber !== '' ? 'This is the number the office can hand people into.' : 'Save the business number for click-to-chat and screenshots.' ?></span>
                    </article>
                    <article class="social-summary-card">
                        <span class="eyebrow">Phone ID</span>
                        <strong><?= admin_escape((string) ((($siteTracking['whatsapp_phone_number_id'] ?? '') !== '') ? ($siteTracking['whatsapp_phone_number_id'] ?? '') : '—')) ?></strong>
                        <span>Meta phone number ID for the WhatsApp business lane.</span>
                    </article>
                    <article class="social-summary-card">
                        <span class="eyebrow">Business ID</span>
                        <strong><?= admin_escape((string) ((($siteTracking['whatsapp_business_account_id'] ?? '') !== '') ? ($siteTracking['whatsapp_business_account_id'] ?? '') : '—')) ?></strong>
                        <span>WhatsApp business account mapping saved with the rest of the channel setup.</span>
                    </article>
                </div>
                <details class="social-collapse">
                    <summary>
                        <span class="social-collapse-label">
                            <strong>Open WhatsApp handoff details</strong>
                            <span>See the default message, verify token, and the current click-to-chat destination.</span>
                        </span>
                        <span class="social-collapse-pill"><?= $whatsAppReady ? 'Ready' : 'Needs setup' ?></span>
                    </summary>
                    <div class="social-collapse-body">
                        <div class="social-meta-list">
                            <div class="social-meta-row">
                                <div>
                                    <strong>Click-to-chat destination</strong>
                                    <span>This is the public link CreditSoft can use when a lead should move into WhatsApp.</span>
                                </div>
                                <span class="social-meta-value"><?= admin_escape($whatsAppChatUrl ?? 'Save the display number first') ?></span>
                            </div>
                            <div class="social-meta-row">
                                <div>
                                    <strong>Default handoff message</strong>
                                    <span>Prefill a short opener so the office sees context the moment chat starts.</span>
                                </div>
                                <span class="social-meta-value"><?= admin_escape((string) (($siteTracking['whatsapp_default_message'] ?? '') !== '' ? ($siteTracking['whatsapp_default_message'] ?? '') : 'No default message yet')) ?></span>
                            </div>
                            <div class="social-meta-row">
                                <div>
                                    <strong>Verify token</strong>
                                    <span>Only needed if you later wire WhatsApp webhooks or callbacks.</span>
                                </div>
                                <span class="social-meta-value"><?= admin_escape((string) (($siteTracking['whatsapp_verify_token'] ?? '') !== '' ? 'Saved' : 'Not saved')) ?></span>
                            </div>
                        </div>
                        <div class="note-box">For screenshots, this section now tells the whole story quickly: Meta traffic on one side, WhatsApp handoff on the other, and no need to explain where the chat destination lives.</div>
                    </div>
                </details>
            </section>

            <section class="social-stack<?= admin_panel_visible('social', $panel) ?>">
                <section class="card section-card">
                    <div class="section-head">
                        <div class="section-head-copy">
                            <span class="eyebrow">Lead ads</span>
                            <h2>Lead sync and webhook lane</h2>
                            <p>Browser events and CAPI handle measurement. This is the lane that actually lands Meta lead-form data in the CreditSoft lead funnel.</p>
                        </div>
                        <div class="social-action-bar">
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
                                <input type="hidden" name="panel" value="social">
                                <input type="hidden" name="action" value="discover_meta_channels">
                                <button type="submit" class="hero-btn">Discover IG from Page</button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
                                <input type="hidden" name="panel" value="social">
                                <input type="hidden" name="action" value="sync_meta_leads">
                                <button type="submit" class="hero-btn is-primary">Sync lead ads now</button>
                            </form>
                        </div>
                    </div>
                    <div class="social-summary-grid">
                        <article class="social-summary-card">
                            <span class="eyebrow">Imported</span>
                            <strong><?= (int) ($leadSync['imported_total'] ?? 0) ?></strong>
                            <span>Total Meta leads already landed in CreditSoft.</span>
                        </article>
                        <article class="social-summary-card">
                            <span class="eyebrow">New last run</span>
                            <strong><?= (int) ($leadSync['new_last_run'] ?? 0) ?></strong>
                            <span>Fresh lead ad rows from the most recent sync.</span>
                        </article>
                        <article class="social-summary-card">
                            <span class="eyebrow">Forms seen</span>
                            <strong><?= count($leadSync['forms'] ?? []) ?></strong>
                            <span>Lead forms currently visible through the saved token.</span>
                        </article>
                        <article class="social-summary-card">
                            <span class="eyebrow">Webhook</span>
                            <strong><?= ! empty($siteTracking['meta_webhook_verify_token']) ? 'Ready' : 'Wait' ?></strong>
                            <span><?= ! empty($siteTracking['meta_webhook_verify_token']) ? 'Verification token is saved.' : 'Save a verification token before wiring Meta webhooks.' ?></span>
                        </article>
                    </div>
                    <?php if (! empty($leadSync['last_error'])): ?>
                        <div class="note-box">Last Meta lead sync issue: <?= admin_escape((string) $leadSync['last_error']) ?></div>
                    <?php endif; ?>
                    <div class="social-collapse-stack">
                        <details class="social-collapse">
                            <summary>
                                <span class="social-collapse-label">
                                    <strong>Webhook details</strong>
                                    <span>URL, verify token, last webhook, and last manual sync.</span>
                                </span>
                                <span class="social-collapse-pill">Open</span>
                            </summary>
                            <div class="social-collapse-body">
                                <div class="social-meta-list">
                                    <div class="social-meta-row">
                                        <div>
                                            <strong>Webhook URL</strong>
                                            <span>Use this in the Meta app if you want incoming leadgen notifications to hit CreditSoft automatically.</span>
                                        </div>
                                        <span class="social-meta-value"><?= admin_escape($metaWebhookUrl) ?></span>
                                    </div>
                                    <div class="social-meta-row">
                                        <div>
                                            <strong>Verify token</strong>
                                            <span>This must match the token saved in the social settings card above.</span>
                                        </div>
                                        <span class="social-meta-value"><?= admin_escape((string) ($siteTracking['meta_webhook_verify_token'] ?? 'Not saved')) ?></span>
                                    </div>
                                    <div class="social-meta-row">
                                        <div>
                                            <strong>Last webhook</strong>
                                            <span>CreditSoft records the latest delivery so you can tell whether Meta actually called home.</span>
                                        </div>
                                        <span class="social-meta-value"><?= admin_escape(admin_date((string) ($webhookStatus['last_received_at'] ?? ''), 'No webhook yet')) ?></span>
                                    </div>
                                    <div class="social-meta-row">
                                        <div>
                                            <strong>Last sync</strong>
                                            <span>Manual sync is the safe fallback while the webhook lane is being approved.</span>
                                        </div>
                                        <span class="social-meta-value"><?= admin_escape(admin_date((string) ($leadSync['last_sync_at'] ?? ''), 'Not synced yet')) ?></span>
                                    </div>
                                </div>
                            </div>
                        </details>
                        <details class="social-collapse">
                            <summary>
                                <span class="social-collapse-label">
                                    <strong>Recent imported lead ads</strong>
                                    <span>The last few lead-form rows already landed in CreditSoft.</span>
                                </span>
                                <span class="social-collapse-pill"><?= ! empty($leadSync['recent']) ? count(array_slice($leadSync['recent'], 0, 5)) . ' items' : 'Empty' ?></span>
                            </summary>
                            <div class="social-collapse-body">
                                <?php if (! empty($leadSync['recent'])): ?>
                                    <div class="lead-list">
                                        <?php foreach (array_slice($leadSync['recent'], 0, 5) as $item): ?>
                                            <div class="social-post-item">
                                                <div class="social-item-top">
                                                    <div>
                                                        <strong><?= admin_escape((string) (($item['name'] ?? '') ?: ($item['email'] ?? 'Unknown lead'))) ?></strong>
                                                        <div class="social-item-copy"><?= admin_escape((string) (($item['email'] ?? '') ?: ($item['phone'] ?? 'No email or phone returned'))) ?></div>
                                                    </div>
                                                    <div class="social-item-meta">
                                                        <span class="social-tag is-good">Imported</span>
                                                        <?php if (! empty($item['form_name'])): ?><span class="social-tag"><?= admin_escape((string) $item['form_name']) ?></span><?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="social-item-copy">Campaign: <?= admin_escape((string) (($item['campaign_name'] ?? '') ?: 'Not provided by Meta')) ?> · Imported <?= admin_escape(admin_date((string) ($item['imported_at'] ?? ''), 'just now')) ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty">No Meta leads have been imported yet. Save the Facebook Page ID, give CreditSoft a management token, and run the lead sync once.</div>
                                <?php endif; ?>
                            </div>
                        </details>
                    </div>
                </section>

                <section class="social-split-grid">
                    <article class="card section-card">
                        <div class="section-head">
                            <div class="section-head-copy">
                                <span class="eyebrow">Ad reporting</span>
                                <h2>Simple ads read</h2>
                                <p>A lightweight last-30-days read so you can see whether the budget is doing anything before opening Ads Manager.</p>
                            </div>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
                                <input type="hidden" name="panel" value="social">
                                <input type="hidden" name="action" value="sync_meta_report">
                                <button type="submit" class="hero-btn is-primary">Refresh ad report</button>
                            </form>
                        </div>
                        <div class="social-kpi-grid">
                            <article class="social-kpi">
                                <span class="eyebrow">Spend</span>
                                <strong>$<?= admin_escape(number_format((float) ($adReport['totals']['spend'] ?? 0), 2)) ?></strong>
                                <span>Last 30 days across the saved ad account.</span>
                            </article>
                            <article class="social-kpi">
                                <span class="eyebrow">Leads</span>
                                <strong><?= (int) ($adReport['totals']['leads'] ?? 0) ?></strong>
                                <span>Meta lead actions seen in campaign insights.</span>
                            </article>
                            <article class="social-kpi">
                                <span class="eyebrow">CPL</span>
                                <strong><?= ($adReport['totals']['cpl'] ?? null) !== null ? '$' . admin_escape(number_format((float) $adReport['totals']['cpl'], 2)) : '—' ?></strong>
                                <span>Average cost per lead across the current report window.</span>
                            </article>
                        </div>
                        <?php if (! empty($adReport['last_error'])): ?><div class="note-box">Last ad-report issue: <?= admin_escape((string) $adReport['last_error']) ?></div><?php endif; ?>
                        <details class="social-collapse">
                            <summary>
                                <span class="social-collapse-label">
                                    <strong>Campaign detail and starter strategy</strong>
                                    <span>Per-campaign read plus the small-budget note.</span>
                                </span>
                                <span class="social-collapse-pill"><?= ! empty($adReport['campaigns']) ? count($adReport['campaigns']) . ' campaigns' : 'No data' ?></span>
                            </summary>
                            <div class="social-collapse-body">
                                <?php if (! empty($adReport['campaigns'])): ?>
                                    <div class="table-wrap">
                                        <table class="social-mini-table">
                                            <thead>
                                            <tr>
                                                <th>Campaign</th>
                                                <th>Objective</th>
                                                <th>Spend</th>
                                                <th>Clicks</th>
                                                <th>Leads</th>
                                                <th>CPL</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($adReport['campaigns'] as $campaign): ?>
                                                <tr>
                                                    <td><strong><?= admin_escape((string) ($campaign['campaign_name'] ?? 'Untitled campaign')) ?></strong></td>
                                                    <td><?= admin_escape((string) ($campaign['objective'] ?? '')) ?></td>
                                                    <td>$<?= admin_escape(number_format((float) ($campaign['spend'] ?? 0), 2)) ?></td>
                                                    <td><?= (int) ($campaign['clicks'] ?? 0) ?></td>
                                                    <td><?= (int) ($campaign['leads'] ?? 0) ?></td>
                                                    <td><?= ($campaign['cpl'] ?? null) !== null ? '$' . admin_escape(number_format((float) $campaign['cpl'], 2)) : '—' ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="empty">No ad-report snapshot yet. Save the ad account ID and the management token, then refresh the report.</div>
                                <?php endif; ?>
                                <div class="helper-copy">Best starter lane for a tiny budget: use the Leads objective, broad or Advantage+ audience, and let the pixel/lead events build signal first. A lookalike audience is usually worth testing after you have a real lead seed, not before.</div>
                            </div>
                        </details>
                    </article>

                    <article class="card section-card">
                        <div class="section-head">
                            <div class="section-head-copy">
                                <span class="eyebrow">Post and reply lane</span>
                                <h2>Drafts and reply queue</h2>
                                <p>Starter operational lane for social work. Drafts and replies are tucked away until you need them.</p>
                            </div>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
                                <input type="hidden" name="panel" value="social">
                                <input type="hidden" name="action" value="sync_meta_replies">
                                <button type="submit" class="hero-btn">Refresh replies</button>
                            </form>
                        </div>
                        <div class="social-kpi-grid">
                            <article class="social-kpi">
                                <span class="eyebrow">Drafts</span>
                                <strong><?= count($socialPosts) ?></strong>
                                <span>Saved post drafts in the queue.</span>
                            </article>
                            <article class="social-kpi">
                                <span class="eyebrow">Open replies</span>
                                <strong><?= count($openReplies) ?></strong>
                                <span>Comments still waiting for a response.</span>
                            </article>
                            <article class="social-kpi">
                                <span class="eyebrow">Last reply sync</span>
                                <strong><?= admin_escape(admin_date((string) ($socialData['replies']['last_sync_at'] ?? ''), 'Never')) ?></strong>
                                <span>Latest time CreditSoft read Page comments.</span>
                            </article>
                        </div>
                        <?php if (! empty($socialData['replies']['last_error'])): ?><div class="note-box">Last reply-sync issue: <?= admin_escape((string) $socialData['replies']['last_error']) ?></div><?php endif; ?>
                    </article>
                </section>

                <section class="social-split-grid">
                    <article class="card section-card">
                        <div class="section-head">
                            <div class="section-head-copy">
                                <span class="eyebrow">Post queue</span>
                                <h2>Create the next post</h2>
                                <p>Open this when you actually want to draft or publish something.</p>
                            </div>
                        </div>
                        <details class="social-collapse">
                            <summary>
                                <span class="social-collapse-label">
                                    <strong>Open draft editor and queue</strong>
                                    <span>Create the next post and review the saved drafts.</span>
                                </span>
                                <span class="social-collapse-pill"><?= count($socialPosts) > 0 ? count($socialPosts) . ' drafts' : 'Empty' ?></span>
                            </summary>
                            <div class="social-collapse-body">
                                <form method="post" class="social-inline-form">
                                    <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
                                    <input type="hidden" name="panel" value="social">
                                    <input type="hidden" name="action" value="create_social_post">
                                    <div class="admin-form-grid is-three">
                                        <div class="admin-field">
                                            <label for="post_platform">Platform</label>
                                            <select class="admin-input" id="post_platform" name="post_platform">
                                                <option value="facebook">Facebook Page</option>
                                                <option value="instagram">Instagram draft</option>
                                                <option value="threads">Threads draft</option>
                                            </select>
                                        </div>
                                        <div class="admin-field">
                                            <label for="post_title">Internal note</label>
                                            <input class="admin-input" id="post_title" name="post_title" type="text" placeholder="April product push">
                                        </div>
                                        <div class="admin-field">
                                            <label for="post_link">Link</label>
                                            <input class="admin-input" id="post_link" name="post_link" type="text" placeholder="https://creditsoft.app/pricing">
                                        </div>
                                    </div>
                                    <div class="admin-field">
                                        <label for="post_body">Post copy</label>
                                        <textarea class="admin-textarea" id="post_body" name="post_body" placeholder="Write the post here."></textarea>
                                    </div>
                                    <div class="form-actions">
                                        <button type="submit" class="hero-btn is-primary">Save draft</button>
                                    </div>
                                </form>

                                <?php if ($socialPosts !== []): ?>
                                    <div class="social-post-list">
                                        <?php foreach (array_slice($socialPosts, 0, 6) as $post): ?>
                                            <article class="social-post-item">
                                                <div class="social-item-top">
                                                    <div>
                                                        <strong><?= admin_escape((string) (($post['title'] ?? '') ?: ucfirst((string) ($post['platform'] ?? 'Post')) . ' draft')) ?></strong>
                                                        <div class="social-item-copy"><?= admin_escape((string) ($post['body'] ?? '')) ?></div>
                                                    </div>
                                                    <div class="social-item-meta">
                                                        <span class="social-tag<?= ($post['status'] ?? '') === 'published' ? ' is-good' : ((($post['status'] ?? '') === 'error') ? ' is-alert' : '') ?>"><?= admin_escape((string) ($post['status'] ?? 'draft')) ?></span>
                                                        <span class="social-tag"><?= admin_escape((string) ($post['platform'] ?? 'facebook')) ?></span>
                                                    </div>
                                                </div>
                                                <div class="social-item-copy">Created <?= admin_escape(admin_date((string) ($post['created_at'] ?? ''), 'just now')) ?><?php if (! empty($post['link'])): ?> · <?= admin_escape((string) $post['link']) ?><?php endif; ?></div>
                                                <?php if (! empty($post['publish_error'])): ?><div class="note-box"><?= admin_escape((string) $post['publish_error']) ?></div><?php endif; ?>
                                                <div class="social-item-actions">
                                                    <?php if (($post['status'] ?? 'draft') !== 'published'): ?>
                                                        <form method="post">
                                                            <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
                                                            <input type="hidden" name="panel" value="social">
                                                            <input type="hidden" name="action" value="publish_social_post">
                                                            <input type="hidden" name="post_id" value="<?= admin_escape((string) ($post['id'] ?? '')) ?>">
                                                            <button type="submit" class="hero-btn is-primary">Publish now</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty">No queued posts yet. Start with a short Facebook post that points people back to pricing or the subscribe flow.</div>
                                <?php endif; ?>
                            </div>
                        </details>
                    </article>

                    <article class="card section-card">
                        <div class="section-head">
                            <div class="section-head-copy">
                                <span class="eyebrow">Reply queue</span>
                                <h2>Open comments</h2>
                                <p>Open this when you want to work the queued Facebook comments.</p>
                            </div>
                        </div>
                        <details class="social-collapse">
                            <summary>
                                <span class="social-collapse-label">
                                    <strong>Open reply queue</strong>
                                    <span>Review and answer the queued Facebook comments.</span>
                                </span>
                                <span class="social-collapse-pill"><?= count($openReplies) > 0 ? count($openReplies) . ' open' : 'Empty' ?></span>
                            </summary>
                            <div class="social-collapse-body">
                                <?php if ($openReplies !== []): ?>
                                    <div class="social-reply-list">
                                        <?php foreach (array_slice($openReplies, 0, 6) as $reply): ?>
                                            <article class="social-reply-item">
                                                <div class="social-item-top">
                                                    <div>
                                                        <strong><?= admin_escape((string) ($reply['author_name'] ?? 'Facebook user')) ?></strong>
                                                        <div class="social-item-copy"><?= admin_escape((string) ($reply['message'] ?? '')) ?></div>
                                                    </div>
                                                    <div class="social-item-meta">
                                                        <span class="social-tag is-alert">Open</span>
                                                        <span class="social-tag"><?= admin_escape(admin_date((string) ($reply['created_time'] ?? ''), 'now')) ?></span>
                                                    </div>
                                                </div>
                                                <div class="social-item-copy">Post: <?= admin_escape((string) (($reply['post_message'] ?? '') ?: 'Facebook post')) ?></div>
                                                <div class="social-item-actions">
                                                    <form method="post">
                                                        <input type="hidden" name="csrf" value="<?= admin_escape(cs_site_admin_csrf_token()) ?>">
                                                        <input type="hidden" name="panel" value="social">
                                                        <input type="hidden" name="reply_id" value="<?= admin_escape((string) ($reply['id'] ?? '')) ?>">
                                                        <textarea class="admin-textarea" name="reply_message" placeholder="Write the reply here."><?= admin_escape((string) ($reply['draft_reply'] ?? '')) ?></textarea>
                                                        <button type="submit" name="action" value="save_reply_draft" class="hero-btn">Save draft</button>
                                                        <button type="submit" name="action" value="send_social_reply" class="hero-btn is-primary">Send reply</button>
                                                        <button type="submit" name="action" value="close_social_reply" class="hero-btn">Close</button>
                                                    </form>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="empty">No open reply items yet. Use “Refresh replies” after the Page has recent comments and CreditSoft will queue them here.</div>
                                <?php endif; ?>
                            </div>
                        </details>
                    </article>
                </section>
            </section>
        </section>
        <footer class="bottom-bar">
            <div class="bottom-left">
                <span class="status-chip">Site admin</span>
                <span class="status-chip<?= $dashboard['database_connected'] ? ' is-live' : ' is-alert' ?>"><?= $dashboard['database_connected'] ? 'Lead DB connected' : 'Lead DB offline' ?></span>
                <span class="status-chip<?= $dashboard['turnstile_enabled'] ? ' is-live' : ' is-alert' ?>"><?= $dashboard['turnstile_enabled'] ? 'Turnstile on' : 'Turnstile off' ?></span>
                <span class="status-chip<?= $licenseData['available'] ? ' is-live' : ' is-alert' ?>"><?= $licenseData['available'] ? 'License lane live' : 'License lane missing' ?></span>
            </div>
            <div class="bottom-right">
                <span class="status-chip">Public site</span>
                <a class="status-chip" href="<?= public_url('/') ?>">Open home</a>
                <a class="status-chip" href="<?= public_url('/pricing') ?>">Open pricing</a>
                <a class="status-chip" href="<?= public_url('/client-portal') ?>">Open portal</a>
            </div>
        </footer>
    </main>
</div>
<script>
(() => {
    const autosaveForms = document.querySelectorAll('form[data-autosave="true"]');

    autosaveForms.forEach((form) => {
        const statusNode = form.querySelector('.autosave-status');
        let timer = null;
        let inFlightController = null;
        let lastSerialized = '';

        const setStatus = (message = '', state = '') => {
            if (!statusNode) {
                return;
            }

            statusNode.textContent = message;
            statusNode.classList.remove('is-saving', 'is-saved', 'is-error');

            if (state) {
                statusNode.classList.add(state);
            }
        };

        const serialize = () => new URLSearchParams(new FormData(form)).toString();

        const saveForm = () => {
            const formData = new FormData(form);
            formData.set('autosave', '1');
            const serialized = new URLSearchParams(formData).toString();

            if (serialized === lastSerialized) {
                return;
            }

            lastSerialized = serialized;

            if (inFlightController) {
                inFlightController.abort();
            }

            inFlightController = new AbortController();
            setStatus('Saving...', 'is-saving');

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                signal: inFlightController.signal,
            })
                .then(async (response) => {
                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok || !payload.success) {
                        throw new Error(payload.message || 'Save failed');
                    }

                    setStatus(payload.message || 'Saved', 'is-saved');
                    window.setTimeout(() => {
                        setStatus('Saved', 'is-saved');
                    }, 50);
                })
                .catch((error) => {
                    if (error.name === 'AbortError') {
                        return;
                    }

                    setStatus(error.message || 'Save failed', 'is-error');
                });
        };

        form.querySelectorAll('input, textarea, select').forEach((field) => {
            if (field.type === 'hidden' || field.type === 'submit' || field.type === 'button') {
                return;
            }

            const scheduleSave = () => {
                window.clearTimeout(timer);
                timer = window.setTimeout(saveForm, 180);
            };

            field.addEventListener('focusout', scheduleSave);
            field.addEventListener('change', scheduleSave);
        });
    });
})();
</script>
</body>
</html>
