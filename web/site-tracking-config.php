<?php
declare(strict_types=1);

function creditsoft_site_tracking_defaults(): array
{
    return [
        'google_measurement_id' => 'G-9QJTYCN2FZ',
        'meta_pixel_id' => '',
        'meta_app_id' => '',
        'meta_business_id' => '',
        'meta_management_token' => '',
        'meta_webhook_verify_token' => '',
        'facebook_page_id' => '',
        'facebook_username' => 'GetCreditSoft',
        'instagram_business_id' => '',
        'instagram_username' => '',
        'threads_profile_id' => '',
        'threads_username' => '',
        'x_username' => '',
        'meta_ad_account_id' => '',
        'lead_form_name' => '',
        'campaign_objective' => 'OUTCOME_LEADS',
        'meta_capi_token' => '',
        'meta_capi_test_event_code' => '',
        'weekly_budget' => '',
        'daily_cap' => '',
        'monthly_cap' => '',
        'whatsapp_enabled' => false,
        'whatsapp_display_number' => '',
        'whatsapp_phone_number_id' => '',
        'whatsapp_business_account_id' => '',
        'whatsapp_verify_token' => '',
        'whatsapp_default_message' => '',
    ];
}

function creditsoft_site_tracking_storage_path(): string
{
    return dirname(__DIR__) . '/web-meta/site-tracking.json';
}

function creditsoft_site_tracking_sanitize(array $input, ?array $current = null): array
{
    $defaults = creditsoft_site_tracking_defaults();
    $current = is_array($current) ? $current : $defaults;
    $incomingToken = trim((string) ($input['meta_capi_token'] ?? ''));
    $incomingTestCode = trim((string) ($input['meta_capi_test_event_code'] ?? ''));
    $incomingManagementToken = trim((string) ($input['meta_management_token'] ?? ''));
    $incomingWhatsAppEnabled = $input['whatsapp_enabled'] ?? ($current['whatsapp_enabled'] ?? $defaults['whatsapp_enabled']);

    return [
        'google_measurement_id' => strtoupper(trim((string) ($input['google_measurement_id'] ?? $defaults['google_measurement_id']))),
        'meta_pixel_id' => preg_replace('/[^0-9]/', '', trim((string) ($input['meta_pixel_id'] ?? $defaults['meta_pixel_id']))) ?: '',
        'meta_app_id' => preg_replace('/[^0-9]/', '', trim((string) ($input['meta_app_id'] ?? $defaults['meta_app_id']))) ?: '',
        'meta_business_id' => preg_replace('/[^0-9]/', '', trim((string) ($input['meta_business_id'] ?? $defaults['meta_business_id']))) ?: '',
        'meta_management_token' => $incomingManagementToken !== '' ? preg_replace('/\s+/', '', $incomingManagementToken) : trim((string) ($current['meta_management_token'] ?? '')),
        'meta_webhook_verify_token' => trim((string) ($input['meta_webhook_verify_token'] ?? ($current['meta_webhook_verify_token'] ?? ''))),
        'facebook_page_id' => preg_replace('/[^0-9]/', '', trim((string) ($input['facebook_page_id'] ?? $defaults['facebook_page_id']))) ?: '',
        'facebook_username' => ltrim(trim((string) ($input['facebook_username'] ?? $defaults['facebook_username'])), '@/'),
        'instagram_business_id' => preg_replace('/[^0-9]/', '', trim((string) ($input['instagram_business_id'] ?? $defaults['instagram_business_id']))) ?: '',
        'instagram_username' => ltrim(trim((string) ($input['instagram_username'] ?? $defaults['instagram_username'])), '@'),
        'threads_profile_id' => preg_replace('/[^0-9]/', '', trim((string) ($input['threads_profile_id'] ?? $defaults['threads_profile_id']))) ?: '',
        'threads_username' => ltrim(trim((string) ($input['threads_username'] ?? $defaults['threads_username'])), '@'),
        'x_username' => ltrim(trim((string) ($input['x_username'] ?? $defaults['x_username'])), '@'),
        'meta_ad_account_id' => preg_replace('/[^0-9act_]/i', '', trim((string) ($input['meta_ad_account_id'] ?? $defaults['meta_ad_account_id']))) ?: '',
        'lead_form_name' => trim((string) ($input['lead_form_name'] ?? $defaults['lead_form_name'])),
        'campaign_objective' => trim((string) ($input['campaign_objective'] ?? $defaults['campaign_objective'])) ?: $defaults['campaign_objective'],
        'meta_capi_token' => $incomingToken !== '' ? preg_replace('/\s+/', '', $incomingToken) : trim((string) ($current['meta_capi_token'] ?? '')),
        'meta_capi_test_event_code' => $incomingTestCode !== '' ? trim($incomingTestCode) : trim((string) ($current['meta_capi_test_event_code'] ?? '')),
        'weekly_budget' => preg_replace('/[^0-9.]/', '', trim((string) ($input['weekly_budget'] ?? $defaults['weekly_budget']))) ?: '',
        'daily_cap' => preg_replace('/[^0-9.]/', '', trim((string) ($input['daily_cap'] ?? $defaults['daily_cap']))) ?: '',
        'monthly_cap' => preg_replace('/[^0-9.]/', '', trim((string) ($input['monthly_cap'] ?? $defaults['monthly_cap']))) ?: '',
        'whatsapp_enabled' => filter_var($incomingWhatsAppEnabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? in_array((string) $incomingWhatsAppEnabled, ['1', 'true', 'on', 'yes'], true),
        'whatsapp_display_number' => trim((string) ($input['whatsapp_display_number'] ?? $defaults['whatsapp_display_number'])),
        'whatsapp_phone_number_id' => preg_replace('/[^0-9]/', '', trim((string) ($input['whatsapp_phone_number_id'] ?? $defaults['whatsapp_phone_number_id']))) ?: '',
        'whatsapp_business_account_id' => preg_replace('/[^0-9]/', '', trim((string) ($input['whatsapp_business_account_id'] ?? $defaults['whatsapp_business_account_id']))) ?: '',
        'whatsapp_verify_token' => trim((string) ($input['whatsapp_verify_token'] ?? ($current['whatsapp_verify_token'] ?? ''))),
        'whatsapp_default_message' => trim((string) ($input['whatsapp_default_message'] ?? $defaults['whatsapp_default_message'])),
    ];
}

function creditsoft_site_tracking_load(): array
{
    static $cached = null;

    if (is_array($cached)) {
        return $cached;
    }

    $defaults = creditsoft_site_tracking_defaults();
    $path = creditsoft_site_tracking_storage_path();

    if (! is_file($path)) {
        return $cached = $defaults;
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    if (! is_array($decoded)) {
        return $cached = $defaults;
    }

    return $cached = creditsoft_site_tracking_sanitize($decoded, $defaults);
}

function creditsoft_site_tracking_save(array $input): bool
{
    $clean = creditsoft_site_tracking_sanitize($input, creditsoft_site_tracking_load());
    $encoded = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (! is_string($encoded)) {
        return false;
    }

    $path = creditsoft_site_tracking_storage_path();
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        return false;
    }

    return file_put_contents($path, $encoded . PHP_EOL) !== false;
}

function creditsoft_site_tracking_events_manager_url(array $tracking): string
{
    $pixelId = trim((string) ($tracking['meta_pixel_id'] ?? ''));

    if ($pixelId !== '') {
        return 'https://business.facebook.com/events_manager2/list/pixel/' . rawurlencode($pixelId);
    }

    return 'https://business.facebook.com/events_manager2/list/pixel';
}

function creditsoft_site_tracking_app_dashboard_url(array $tracking): string
{
    $appId = trim((string) ($tracking['meta_app_id'] ?? ''));

    if ($appId !== '') {
        return 'https://developers.facebook.com/apps/' . rawurlencode($appId) . '/dashboard/';
    }

    return 'https://developers.facebook.com/apps/';
}

function creditsoft_site_tracking_threads_profile_url(array $tracking): string
{
    $username = trim((string) ($tracking['threads_username'] ?? ''));

    if ($username !== '') {
        return 'https://www.threads.com/@' . rawurlencode($username);
    }

    return 'https://www.threads.com/';
}

function creditsoft_site_tracking_public_facebook_url(array $tracking): ?string
{
    $username = ltrim(trim((string) ($tracking['facebook_username'] ?? '')), '@/');
    $pageId = trim((string) ($tracking['facebook_page_id'] ?? ''));

    if ($username !== '') {
        return 'https://www.facebook.com/' . rawurlencode($username);
    }

    if ($pageId !== '') {
        return 'https://www.facebook.com/profile.php?id=' . rawurlencode($pageId);
    }

    return null;
}

function creditsoft_site_tracking_public_threads_url(array $tracking): ?string
{
    $username = trim((string) ($tracking['threads_username'] ?? ''));

    if ($username !== '') {
        return 'https://www.threads.com/@' . rawurlencode(ltrim($username, '@'));
    }

    return null;
}

function creditsoft_site_tracking_public_instagram_url(array $tracking): ?string
{
    $username = trim((string) ($tracking['instagram_username'] ?? ''));

    if ($username === '') {
        $username = trim((string) ($tracking['threads_username'] ?? ''));
    }

    if ($username !== '') {
        return 'https://www.instagram.com/' . rawurlencode(ltrim($username, '@')) . '/';
    }

    return null;
}

function creditsoft_site_tracking_public_x_url(array $tracking): ?string
{
    $username = trim((string) ($tracking['x_username'] ?? ''));

    if ($username !== '') {
        return 'https://x.com/' . rawurlencode(ltrim($username, '@'));
    }

    return null;
}

function creditsoft_site_tracking_ads_manager_url(array $tracking): string
{
    $adAccountId = trim((string) ($tracking['meta_ad_account_id'] ?? ''));

    if ($adAccountId !== '') {
        $normalized = str_starts_with(strtolower($adAccountId), 'act_') ? $adAccountId : ('act_' . preg_replace('/[^0-9]/', '', $adAccountId));

        return 'https://adsmanager.facebook.com/adsmanager/manage/accounts?act=' . rawurlencode($normalized);
    }

    return 'https://adsmanager.facebook.com/';
}

function creditsoft_site_tracking_whatsapp_chat_url(array $tracking): ?string
{
    $displayNumber = trim((string) ($tracking['whatsapp_display_number'] ?? ''));
    $digits = preg_replace('/[^0-9]/', '', $displayNumber) ?: '';

    if ($digits === '') {
        return null;
    }

    $url = 'https://wa.me/' . rawurlencode($digits);
    $message = trim((string) ($tracking['whatsapp_default_message'] ?? ''));

    if ($message !== '') {
        $url .= '?text=' . rawurlencode($message);
    }

    return $url;
}

function creditsoft_site_tracking_whatsapp_ready(array $tracking): bool
{
    $enabled = ! empty($tracking['whatsapp_enabled']);
    $displayNumber = preg_replace('/[^0-9]/', '', trim((string) ($tracking['whatsapp_display_number'] ?? ''))) ?: '';
    $phoneNumberId = trim((string) ($tracking['whatsapp_phone_number_id'] ?? ''));
    $businessAccountId = trim((string) ($tracking['whatsapp_business_account_id'] ?? ''));

    return $enabled && ($displayNumber !== '' || $phoneNumberId !== '' || $businessAccountId !== '');
}

function creditsoft_site_tracking_capi_ready(array $tracking): bool
{
    return trim((string) ($tracking['meta_pixel_id'] ?? '')) !== ''
        && trim((string) ($tracking['meta_capi_token'] ?? '')) !== '';
}
