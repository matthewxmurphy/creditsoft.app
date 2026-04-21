<?php
declare(strict_types=1);

require_once __DIR__ . '/site-tracking-config.php';
require_once __DIR__ . '/lead-intake.php';

function creditsoft_meta_social_storage_path(): string
{
    return dirname(__DIR__) . '/web-meta/meta-social.json';
}

function creditsoft_meta_social_log_path(): string
{
    return dirname(__DIR__) . '/web-meta/meta-social.log';
}

function creditsoft_meta_social_log(string $message, array $context = []): void
{
    $path = creditsoft_meta_social_log_path();
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        return;
    }

    $line = '[' . gmdate('c') . '] ' . $message;

    if ($context !== []) {
        $encoded = json_encode($context, JSON_UNESCAPED_SLASHES);
        if (is_string($encoded)) {
            $line .= ' ' . $encoded;
        }
    }

    @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function creditsoft_meta_social_defaults(): array
{
    return [
        'lead_sync' => [
            'last_sync_at' => null,
            'last_error' => null,
            'imported_total' => 0,
            'new_last_run' => 0,
            'forms' => [],
            'recent' => [],
            'imports' => [],
        ],
        'ad_report' => [
            'last_sync_at' => null,
            'last_error' => null,
            'window' => 'last_30d',
            'totals' => [
                'spend' => 0.0,
                'impressions' => 0,
                'reach' => 0,
                'clicks' => 0,
                'leads' => 0,
                'ctr' => 0.0,
                'cpl' => null,
            ],
            'campaigns' => [],
        ],
        'posts' => [],
        'replies' => [
            'last_sync_at' => null,
            'last_error' => null,
            'items' => [],
        ],
        'webhooks' => [
            'last_received_at' => null,
            'last_summary' => null,
            'deliveries' => [],
        ],
    ];
}

function creditsoft_meta_social_load(): array
{
    $cached = $GLOBALS['creditsoft_meta_social_cache'] ?? null;

    if (is_array($cached)) {
        return $cached;
    }

    $defaults = creditsoft_meta_social_defaults();
    $path = creditsoft_meta_social_storage_path();

    if (! is_file($path)) {
        return $GLOBALS['creditsoft_meta_social_cache'] = $defaults;
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    if (! is_array($decoded)) {
        return $GLOBALS['creditsoft_meta_social_cache'] = $defaults;
    }

    $cached = array_replace_recursive($defaults, $decoded);
    $GLOBALS['creditsoft_meta_social_cache'] = $cached;

    return $cached;
}

function creditsoft_meta_social_save(array $state): bool
{
    $clean = array_replace_recursive(creditsoft_meta_social_defaults(), $state);
    $path = creditsoft_meta_social_storage_path();
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        return false;
    }

    $encoded = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (! is_string($encoded)) {
        return false;
    }

    $saved = file_put_contents($path, $encoded . PHP_EOL, LOCK_EX) !== false;

    if ($saved) {
        $GLOBALS['creditsoft_meta_social_cache'] = $clean;
    }

    return $saved;
}

function creditsoft_meta_social_management_token(array $tracking): string
{
    $management = trim((string) ($tracking['meta_management_token'] ?? ''));

    if ($management !== '') {
        return $management;
    }

    return trim((string) ($tracking['meta_capi_token'] ?? ''));
}

function creditsoft_meta_social_graph_request(string $path, array $query = [], string $method = 'GET', array $payload = [], ?string $token = null): array
{
    $tracking = creditsoft_site_tracking_load();
    $token = trim((string) ($token ?? creditsoft_meta_social_management_token($tracking)));

    if ($token === '') {
        return ['success' => false, 'error' => 'Meta management token is not saved yet.'];
    }

    if (! function_exists('curl_init')) {
        return ['success' => false, 'error' => 'cURL is not available on this host.'];
    }

    $path = '/' . ltrim($path, '/');
    $query['access_token'] = $token;
    $url = 'https://graph.facebook.com/v25.0' . $path . '?' . http_build_query($query);

    $ch = curl_init($url);
    $method = strtoupper($method);

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($payload);
    }

    curl_setopt_array($ch, $options);

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;

    if ($curlError !== '') {
        creditsoft_meta_social_log('graph_curl_error', ['path' => $path, 'error' => $curlError]);

        return ['success' => false, 'error' => $curlError];
    }

    if ($status < 200 || $status >= 300) {
        $message = is_array($decoded) ? (string) (($decoded['error']['message'] ?? '') ?: 'Meta returned an error.') : 'Meta returned an error.';
        creditsoft_meta_social_log('graph_http_error', ['path' => $path, 'status' => $status, 'message' => $message]);

        return [
            'success' => false,
            'status' => $status,
            'error' => $message,
            'response' => is_array($decoded) ? $decoded : $raw,
        ];
    }

    return [
        'success' => true,
        'status' => $status,
        'data' => is_array($decoded) ? $decoded : [],
        'raw' => $raw,
    ];
}

function creditsoft_meta_social_sanitize_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?: '';

    return trim($value, '_');
}

function creditsoft_meta_social_value_to_string(mixed $value): string
{
    if (is_array($value)) {
        $parts = [];

        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $parts[] = $item;
            }
        }

        return implode(', ', $parts);
    }

    return trim((string) $value);
}

function creditsoft_meta_social_extract_field_map(array $fieldData): array
{
    $map = [];

    foreach ($fieldData as $field) {
        if (! is_array($field)) {
            continue;
        }

        $name = creditsoft_meta_social_sanitize_key((string) ($field['name'] ?? ''));

        if ($name === '') {
            continue;
        }

        $map[$name] = creditsoft_meta_social_value_to_string($field['values'] ?? '');
    }

    return $map;
}

function creditsoft_meta_social_first(array $fields, array $keys): string
{
    foreach ($keys as $key) {
        $value = trim((string) ($fields[$key] ?? ''));

        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function creditsoft_meta_social_lookup_by_fragments(array $fields, array $fragments): string
{
    foreach ($fields as $key => $value) {
        $matched = true;

        foreach ($fragments as $fragment) {
            if (! str_contains($key, $fragment)) {
                $matched = false;
                break;
            }
        }

        if ($matched) {
            $text = trim((string) $value);

            if ($text !== '') {
                return $text;
            }
        }
    }

    return '';
}

function creditsoft_meta_social_map_qualification(array $fields): array
{
    $mapped = [
        'client_count' => creditsoft_meta_social_first($fields, ['client_count', 'number_of_clients', 'clients']) ?: creditsoft_meta_social_lookup_by_fragments($fields, ['client']),
        'current_workflow' => creditsoft_meta_social_first($fields, ['current_workflow', 'workflow', 'software']) ?: creditsoft_meta_social_lookup_by_fragments($fields, ['workflow']),
        'merchant_status' => creditsoft_meta_social_first($fields, ['merchant_status', 'merchant_account']) ?: creditsoft_meta_social_lookup_by_fragments($fields, ['merchant']),
        'website_status' => creditsoft_meta_social_first($fields, ['website_status', 'do_you_have_a_website']) ?: creditsoft_meta_social_lookup_by_fragments($fields, ['website']),
        'outsourcing_status' => creditsoft_meta_social_first($fields, ['outsourcing_status', 'outsource']) ?: creditsoft_meta_social_lookup_by_fragments($fields, ['outsource']),
        'roi_visibility' => creditsoft_meta_social_first($fields, ['roi_visibility', 'roi']) ?: creditsoft_meta_social_lookup_by_fragments($fields, ['roi']),
        'team_size' => creditsoft_meta_social_first($fields, ['team_size']) ?: creditsoft_meta_social_lookup_by_fragments($fields, ['team']),
        'switch_timeline' => creditsoft_meta_social_first($fields, ['switch_timeline', 'timeline']) ?: creditsoft_meta_social_lookup_by_fragments($fields, ['timeline']),
        'biggest_pain' => creditsoft_meta_social_first($fields, ['biggest_pain', 'pain_point']) ?: creditsoft_meta_social_lookup_by_fragments($fields, ['pain']),
        'primary_goal' => creditsoft_meta_social_first($fields, ['primary_goal', 'goal']) ?: creditsoft_meta_social_lookup_by_fragments($fields, ['goal']),
        'additional_notes' => creditsoft_meta_social_first($fields, ['additional_notes', 'notes']),
    ];

    return array_filter($mapped, static fn ($value) => trim((string) $value) !== '');
}

function creditsoft_meta_social_trim_recent_imports(array $imports, int $limit = 200): array
{
    uasort($imports, static function (array $left, array $right): int {
        return strcmp((string) ($right['imported_at'] ?? ''), (string) ($left['imported_at'] ?? ''));
    });

    return array_slice($imports, 0, $limit, true);
}

function creditsoft_meta_social_import_lead(array $leadRow, array $context = [], ?array $tracking = null): array
{
    $tracking = is_array($tracking) ? $tracking : creditsoft_site_tracking_load();
    $state = creditsoft_meta_social_load();
    $leadgenId = trim((string) ($leadRow['id'] ?? $context['leadgen_id'] ?? ''));

    if ($leadgenId === '') {
        return ['success' => false, 'error' => 'Meta lead payload did not include a lead ID.'];
    }

    if (! empty($state['lead_sync']['imports'][$leadgenId])) {
        return ['success' => true, 'imported' => false, 'entry' => $state['lead_sync']['imports'][$leadgenId]];
    }

    $fieldMap = creditsoft_meta_social_extract_field_map($leadRow['field_data'] ?? []);
    $email = strtolower(creditsoft_meta_social_first($fieldMap, ['email', 'work_email']));
    $phone = creditsoft_meta_social_first($fieldMap, ['phone_number', 'phone', 'work_phone']);
    $company = creditsoft_meta_social_first($fieldMap, ['company_name', 'business_name', 'company']);
    $firstName = creditsoft_meta_social_first($fieldMap, ['first_name']);
    $lastName = creditsoft_meta_social_first($fieldMap, ['last_name']);
    $fullName = creditsoft_meta_social_first($fieldMap, ['full_name', 'name']);
    $name = trim($fullName !== '' ? $fullName : trim($firstName . ' ' . $lastName));

    if ($email === '' && $phone === '' && $name === '') {
        return ['success' => false, 'error' => 'Meta lead did not include usable contact fields yet.'];
    }

    $planInterest = creditsoft_meta_social_first($fieldMap, ['plan_interest', 'product_interest']);
    $formName = trim((string) ($context['form_name'] ?? ''));
    $campaignName = trim((string) ($leadRow['campaign_name'] ?? $context['campaign_name'] ?? ''));
    $source = 'meta_lead_ad';

    $lead = creditsoft_lead_upsert_basic([
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'company' => $company,
        'plan_interest' => $planInterest,
        'source' => $source,
    ]);

    $leadId = (int) ($lead['id'] ?? 0);
    $qualification = creditsoft_meta_social_map_qualification($fieldMap);

    if ($leadId > 0 && $qualification !== []) {
        creditsoft_lead_save_qualification($leadId, array_merge([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'company' => $company,
            'plan_interest' => $planInterest,
            'source' => $source,
        ], $qualification));
    }

    $entry = [
        'leadgen_id' => $leadgenId,
        'lead_id' => $leadId,
        'email' => $email,
        'phone' => $phone,
        'name' => $name,
        'company' => $company,
        'form_id' => trim((string) ($leadRow['form_id'] ?? $context['form_id'] ?? '')),
        'form_name' => $formName,
        'campaign_name' => $campaignName,
        'created_time' => trim((string) ($leadRow['created_time'] ?? '')),
        'imported_at' => gmdate('c'),
        'source' => $source,
        'field_map' => $fieldMap,
    ];

    $state['lead_sync']['imports'][$leadgenId] = $entry;
    $state['lead_sync']['imports'] = creditsoft_meta_social_trim_recent_imports($state['lead_sync']['imports']);
    $state['lead_sync']['imported_total'] = count($state['lead_sync']['imports']);
    $state['lead_sync']['recent'] = array_slice(array_merge([$entry], array_values(array_filter(
        $state['lead_sync']['recent'],
        static fn (array $row): bool => (string) ($row['leadgen_id'] ?? '') !== $leadgenId
    ))), 0, 20);

    creditsoft_meta_social_save($state);

    return ['success' => true, 'imported' => true, 'entry' => $entry];
}

function creditsoft_meta_social_process_leadgen_id(string $leadgenId, array $context = [], ?array $tracking = null): array
{
    $tracking = is_array($tracking) ? $tracking : creditsoft_site_tracking_load();
    $leadgenId = trim($leadgenId);

    if ($leadgenId === '') {
        return ['success' => false, 'error' => 'Leadgen ID is missing.'];
    }

    $response = creditsoft_meta_social_graph_request('/' . $leadgenId, [
        'fields' => 'id,created_time,field_data,ad_id,ad_name,campaign_id,campaign_name,form_id,is_organic,platform',
    ], 'GET', [], creditsoft_meta_social_management_token($tracking));

    if (! $response['success']) {
        return $response;
    }

    return creditsoft_meta_social_import_lead($response['data'], $context, $tracking);
}

function creditsoft_meta_social_sync_leads(?array $tracking = null): array
{
    $tracking = is_array($tracking) ? $tracking : creditsoft_site_tracking_load();
    $pageId = trim((string) ($tracking['facebook_page_id'] ?? ''));

    if ($pageId === '') {
        return ['success' => false, 'error' => 'Save the Facebook Page ID first so CreditSoft knows where to pull lead forms from.'];
    }

    $formsResponse = creditsoft_meta_social_graph_request('/' . $pageId . '/leadgen_forms', [
        'fields' => 'id,name,status,leads_count,created_time',
        'limit' => 15,
    ], 'GET', [], creditsoft_meta_social_management_token($tracking));

    if (! $formsResponse['success']) {
        $state = creditsoft_meta_social_load();
        $state['lead_sync']['last_sync_at'] = gmdate('c');
        $state['lead_sync']['last_error'] = (string) ($formsResponse['error'] ?? 'Lead form sync failed.');
        creditsoft_meta_social_save($state);

        return $formsResponse;
    }

    $state = creditsoft_meta_social_load();
    $forms = $formsResponse['data']['data'] ?? [];
    $newCount = 0;

    foreach ($forms as $form) {
        if (! is_array($form)) {
            continue;
        }

        $formId = trim((string) ($form['id'] ?? ''));

        if ($formId === '') {
            continue;
        }

        $state['lead_sync']['forms'][$formId] = [
            'id' => $formId,
            'name' => trim((string) ($form['name'] ?? 'Untitled form')),
            'status' => trim((string) ($form['status'] ?? 'UNKNOWN')),
            'leads_count' => (int) ($form['leads_count'] ?? 0),
            'created_time' => trim((string) ($form['created_time'] ?? '')),
            'last_seen_at' => gmdate('c'),
        ];

        $leadsResponse = creditsoft_meta_social_graph_request('/' . $formId . '/leads', [
            'fields' => 'id,created_time,field_data,ad_id,ad_name,campaign_id,campaign_name,form_id,is_organic,platform',
            'limit' => 25,
        ], 'GET', [], creditsoft_meta_social_management_token($tracking));

        if (! $leadsResponse['success']) {
            $state['lead_sync']['last_error'] = (string) ($leadsResponse['error'] ?? 'Meta lead sync failed on one form.');
            continue;
        }

        foreach (($leadsResponse['data']['data'] ?? []) as $leadRow) {
            if (! is_array($leadRow)) {
                continue;
            }

            $imported = creditsoft_meta_social_import_lead($leadRow, [
                'form_id' => $formId,
                'form_name' => (string) ($form['name'] ?? ''),
            ], $tracking);

            if (! empty($imported['success']) && ! empty($imported['imported'])) {
                $newCount++;
            }
        }
    }

    $state = creditsoft_meta_social_load();
    $state['lead_sync']['last_sync_at'] = gmdate('c');
    $state['lead_sync']['last_error'] = null;
    $state['lead_sync']['new_last_run'] = $newCount;
    $state['lead_sync']['imported_total'] = count($state['lead_sync']['imports']);
    creditsoft_meta_social_save($state);

    return [
        'success' => true,
        'forms_seen' => count($forms),
        'new_imported' => $newCount,
        'imported_total' => $state['lead_sync']['imported_total'],
    ];
}

function creditsoft_meta_social_action_value(array $items, array $needles): float
{
    $total = 0.0;

    foreach ($items as $item) {
        if (! is_array($item)) {
            continue;
        }

        $type = strtolower(trim((string) ($item['action_type'] ?? '')));

        foreach ($needles as $needle) {
            if ($type === strtolower($needle)) {
                $total += (float) ($item['value'] ?? 0);
                break;
            }
        }
    }

    return $total;
}

function creditsoft_meta_social_sync_ad_report(?array $tracking = null): array
{
    $tracking = is_array($tracking) ? $tracking : creditsoft_site_tracking_load();
    $adAccountId = trim((string) ($tracking['meta_ad_account_id'] ?? ''));

    if ($adAccountId === '') {
        return ['success' => false, 'error' => 'Save the Meta ad account ID first so CreditSoft knows which account to read.'];
    }

    $normalizedAccount = str_starts_with(strtolower($adAccountId), 'act_') ? $adAccountId : ('act_' . preg_replace('/[^0-9]/', '', $adAccountId));
    $response = creditsoft_meta_social_graph_request('/' . $normalizedAccount . '/insights', [
        'fields' => 'campaign_id,campaign_name,objective,spend,impressions,reach,clicks,ctr,cpc,cpm,actions,cost_per_action_type',
        'date_preset' => 'last_30d',
        'level' => 'campaign',
        'limit' => 25,
    ], 'GET', [], creditsoft_meta_social_management_token($tracking));

    $state = creditsoft_meta_social_load();

    if (! $response['success']) {
        $state['ad_report']['last_sync_at'] = gmdate('c');
        $state['ad_report']['last_error'] = (string) ($response['error'] ?? 'Could not read Ads Manager insights.');
        creditsoft_meta_social_save($state);

        return $response;
    }

    $campaigns = [];
    $totals = [
        'spend' => 0.0,
        'impressions' => 0,
        'reach' => 0,
        'clicks' => 0,
        'leads' => 0,
        'ctr' => 0.0,
        'cpl' => null,
    ];

    $leadTypes = [
        'lead',
        'onsite_conversion.lead_grouped',
        'offsite_conversion.fb_pixel_lead',
        'omni_lead',
        'submit_application',
    ];

    foreach (($response['data']['data'] ?? []) as $row) {
        if (! is_array($row)) {
            continue;
        }

        $leads = (int) round(creditsoft_meta_social_action_value($row['actions'] ?? [], $leadTypes));
        $spend = (float) ($row['spend'] ?? 0);
        $impressions = (int) ($row['impressions'] ?? 0);
        $reach = (int) ($row['reach'] ?? 0);
        $clicks = (int) ($row['clicks'] ?? 0);

        $campaign = [
            'campaign_id' => trim((string) ($row['campaign_id'] ?? '')),
            'campaign_name' => trim((string) ($row['campaign_name'] ?? 'Untitled campaign')),
            'objective' => trim((string) ($row['objective'] ?? '')),
            'spend' => $spend,
            'impressions' => $impressions,
            'reach' => $reach,
            'clicks' => $clicks,
            'ctr' => (float) ($row['ctr'] ?? 0),
            'cpc' => isset($row['cpc']) ? (float) $row['cpc'] : null,
            'cpm' => isset($row['cpm']) ? (float) $row['cpm'] : null,
            'leads' => $leads,
            'cpl' => $leads > 0 ? round($spend / $leads, 2) : null,
        ];

        $campaigns[] = $campaign;
        $totals['spend'] += $spend;
        $totals['impressions'] += $impressions;
        $totals['reach'] += $reach;
        $totals['clicks'] += $clicks;
        $totals['leads'] += $leads;
    }

    usort($campaigns, static fn (array $left, array $right): int => ($right['spend'] <=> $left['spend']));

    if ($totals['impressions'] > 0) {
        $totals['ctr'] = round(($totals['clicks'] / $totals['impressions']) * 100, 2);
    }

    if ($totals['leads'] > 0) {
        $totals['cpl'] = round($totals['spend'] / $totals['leads'], 2);
    }

    $state['ad_report']['last_sync_at'] = gmdate('c');
    $state['ad_report']['last_error'] = null;
    $state['ad_report']['totals'] = $totals;
    $state['ad_report']['campaigns'] = array_slice($campaigns, 0, 12);
    creditsoft_meta_social_save($state);

    return [
        'success' => true,
        'campaign_count' => count($campaigns),
        'totals' => $totals,
    ];
}

function creditsoft_meta_social_create_post(array $payload): array
{
    $state = creditsoft_meta_social_load();
    $body = trim((string) ($payload['body'] ?? ''));

    if ($body === '') {
        return ['success' => false, 'error' => 'Write the post copy first.'];
    }

    $platform = strtolower(trim((string) ($payload['platform'] ?? 'facebook')));
    if (! in_array($platform, ['facebook', 'instagram', 'threads'], true)) {
        $platform = 'facebook';
    }

    $post = [
        'id' => 'post_' . bin2hex(random_bytes(5)),
        'platform' => $platform,
        'title' => trim((string) ($payload['title'] ?? '')),
        'body' => $body,
        'link' => trim((string) ($payload['link'] ?? '')),
        'status' => 'draft',
        'created_at' => gmdate('c'),
        'scheduled_at' => trim((string) ($payload['scheduled_at'] ?? '')),
        'published_at' => null,
        'published_id' => null,
        'publish_error' => null,
    ];

    array_unshift($state['posts'], $post);
    $state['posts'] = array_slice($state['posts'], 0, 60);
    creditsoft_meta_social_save($state);

    return ['success' => true, 'post' => $post];
}

function creditsoft_meta_social_publish_post(string $postId, ?array $tracking = null): array
{
    $tracking = is_array($tracking) ? $tracking : creditsoft_site_tracking_load();
    $state = creditsoft_meta_social_load();

    foreach ($state['posts'] as $index => $post) {
        if ((string) ($post['id'] ?? '') !== $postId) {
            continue;
        }

        $platform = (string) ($post['platform'] ?? 'facebook');

        if ($platform !== 'facebook') {
            $state['posts'][$index]['status'] = 'draft';
            $state['posts'][$index]['publish_error'] = ucfirst($platform) . ' publishing is not wired yet. Keep the draft here for the next pass.';
            creditsoft_meta_social_save($state);

            return ['success' => false, 'error' => $state['posts'][$index]['publish_error']];
        }

        $pageId = trim((string) ($tracking['facebook_page_id'] ?? ''));

        if ($pageId === '') {
            return ['success' => false, 'error' => 'Save the Facebook Page ID first so CreditSoft knows where to publish.'];
        }

        $payload = ['message' => (string) ($post['body'] ?? '')];

        if (trim((string) ($post['link'] ?? '')) !== '') {
            $payload['link'] = trim((string) $post['link']);
        }

        $response = creditsoft_meta_social_graph_request('/' . $pageId . '/feed', [], 'POST', $payload, creditsoft_meta_social_management_token($tracking));

        if (! $response['success']) {
            $state['posts'][$index]['status'] = 'error';
            $state['posts'][$index]['publish_error'] = (string) ($response['error'] ?? 'Meta could not publish the post.');
            creditsoft_meta_social_save($state);

            return $response;
        }

        $state['posts'][$index]['status'] = 'published';
        $state['posts'][$index]['published_id'] = trim((string) ($response['data']['id'] ?? ''));
        $state['posts'][$index]['published_at'] = gmdate('c');
        $state['posts'][$index]['publish_error'] = null;
        creditsoft_meta_social_save($state);

        return ['success' => true, 'published_id' => $state['posts'][$index]['published_id']];
    }

    return ['success' => false, 'error' => 'That draft could not be found anymore.'];
}

function creditsoft_meta_social_sync_replies(?array $tracking = null): array
{
    $tracking = is_array($tracking) ? $tracking : creditsoft_site_tracking_load();
    $pageId = trim((string) ($tracking['facebook_page_id'] ?? ''));

    if ($pageId === '') {
        return ['success' => false, 'error' => 'Save the Facebook Page ID first so CreditSoft knows which page to read.'];
    }

    $response = creditsoft_meta_social_graph_request('/' . $pageId . '/feed', [
        'fields' => 'id,message,created_time,permalink_url,comments.limit(20){id,from,message,created_time,permalink_url,like_count}',
        'limit' => 10,
    ], 'GET', [], creditsoft_meta_social_management_token($tracking));

    $state = creditsoft_meta_social_load();

    if (! $response['success']) {
        $state['replies']['last_sync_at'] = gmdate('c');
        $state['replies']['last_error'] = (string) ($response['error'] ?? 'Could not read page comments.');
        creditsoft_meta_social_save($state);

        return $response;
    }

    $items = $state['replies']['items'] ?? [];
    $newCount = 0;

    foreach (($response['data']['data'] ?? []) as $post) {
        if (! is_array($post)) {
            continue;
        }

        foreach (($post['comments']['data'] ?? []) as $comment) {
            if (! is_array($comment)) {
                continue;
            }

            $commentId = trim((string) ($comment['id'] ?? ''));

            if ($commentId === '') {
                continue;
            }

            $current = $items[$commentId] ?? [];
            $isNew = $current === [];

            $items[$commentId] = array_merge($current, [
                'id' => $commentId,
                'post_id' => trim((string) ($post['id'] ?? '')),
                'post_message' => trim((string) ($post['message'] ?? '')),
                'post_permalink' => trim((string) ($post['permalink_url'] ?? '')),
                'author_name' => trim((string) ($comment['from']['name'] ?? 'Facebook user')),
                'author_id' => trim((string) ($comment['from']['id'] ?? '')),
                'message' => trim((string) ($comment['message'] ?? '')),
                'permalink' => trim((string) ($comment['permalink_url'] ?? '')),
                'created_time' => trim((string) ($comment['created_time'] ?? '')),
                'status' => (string) ($current['status'] ?? 'open'),
                'draft_reply' => (string) ($current['draft_reply'] ?? ''),
                'replied_at' => (string) ($current['replied_at'] ?? ''),
                'published_reply_id' => (string) ($current['published_reply_id'] ?? ''),
                'last_seen_at' => gmdate('c'),
            ]);

            if ($isNew) {
                $newCount++;
            }
        }
    }

    uasort($items, static function (array $left, array $right): int {
        $leftWeight = (($left['status'] ?? 'open') === 'open') ? 0 : 1;
        $rightWeight = (($right['status'] ?? 'open') === 'open') ? 0 : 1;

        if ($leftWeight !== $rightWeight) {
            return $leftWeight <=> $rightWeight;
        }

        return strcmp((string) ($right['created_time'] ?? ''), (string) ($left['created_time'] ?? ''));
    });

    $state['replies']['items'] = array_slice($items, 0, 60, true);
    $state['replies']['last_sync_at'] = gmdate('c');
    $state['replies']['last_error'] = null;
    creditsoft_meta_social_save($state);

    return ['success' => true, 'new_items' => $newCount, 'total_items' => count($state['replies']['items'])];
}

function creditsoft_meta_social_save_reply_draft(string $replyId, string $message): array
{
    $state = creditsoft_meta_social_load();

    if (! isset($state['replies']['items'][$replyId])) {
        return ['success' => false, 'error' => 'That reply thread could not be found.'];
    }

    $state['replies']['items'][$replyId]['draft_reply'] = trim($message);
    creditsoft_meta_social_save($state);

    return ['success' => true];
}

function creditsoft_meta_social_send_reply(string $replyId, string $message, ?array $tracking = null): array
{
    $tracking = is_array($tracking) ? $tracking : creditsoft_site_tracking_load();
    $state = creditsoft_meta_social_load();

    if (! isset($state['replies']['items'][$replyId])) {
        return ['success' => false, 'error' => 'That reply thread could not be found.'];
    }

    $message = trim($message);

    if ($message === '') {
        return ['success' => false, 'error' => 'Write the reply first.'];
    }

    $response = creditsoft_meta_social_graph_request('/' . $replyId . '/comments', [], 'POST', [
        'message' => $message,
    ], creditsoft_meta_social_management_token($tracking));

    if (! $response['success']) {
        return $response;
    }

    $state['replies']['items'][$replyId]['status'] = 'replied';
    $state['replies']['items'][$replyId]['draft_reply'] = $message;
    $state['replies']['items'][$replyId]['replied_at'] = gmdate('c');
    $state['replies']['items'][$replyId]['published_reply_id'] = trim((string) ($response['data']['id'] ?? ''));
    creditsoft_meta_social_save($state);

    return ['success' => true, 'reply_id' => $state['replies']['items'][$replyId]['published_reply_id']];
}

function creditsoft_meta_social_mark_reply_done(string $replyId): array
{
    $state = creditsoft_meta_social_load();

    if (! isset($state['replies']['items'][$replyId])) {
        return ['success' => false, 'error' => 'That reply thread could not be found.'];
    }

    $state['replies']['items'][$replyId]['status'] = 'closed';
    creditsoft_meta_social_save($state);

    return ['success' => true];
}

function creditsoft_meta_social_discover_channels(?array $tracking = null): array
{
    $tracking = is_array($tracking) ? $tracking : creditsoft_site_tracking_load();
    $pageId = trim((string) ($tracking['facebook_page_id'] ?? ''));

    if ($pageId === '') {
        return ['success' => false, 'error' => 'Save the Facebook Page ID first so CreditSoft can inspect the linked Instagram account.'];
    }

    $response = creditsoft_meta_social_graph_request('/' . $pageId, [
        'fields' => 'id,name,instagram_business_account{id,username},connected_instagram_account{id,username}',
    ], 'GET', [], creditsoft_meta_social_management_token($tracking));

    if (! $response['success']) {
        return $response;
    }

    $pageData = $response['data'];
    $instagram = $pageData['instagram_business_account'] ?? $pageData['connected_instagram_account'] ?? [];

    $input = $tracking;
    $updated = false;

    if (! empty($pageData['id']) && trim((string) ($input['facebook_page_id'] ?? '')) === '') {
        $input['facebook_page_id'] = preg_replace('/[^0-9]/', '', (string) $pageData['id']) ?: '';
        $updated = true;
    }

    if (is_array($instagram)) {
        $instagramId = preg_replace('/[^0-9]/', '', (string) ($instagram['id'] ?? '')) ?: '';
        $instagramUsername = ltrim(trim((string) ($instagram['username'] ?? '')), '@');

        if ($instagramId !== '') {
            $input['instagram_business_id'] = $instagramId;
            $updated = true;
        }

        if ($instagramUsername !== '') {
            $input['instagram_username'] = $instagramUsername;
            $updated = true;
        }
    }

    if ($updated) {
        creditsoft_site_tracking_save($input);
    }

    return [
        'success' => true,
        'facebook_page_id' => trim((string) ($pageData['id'] ?? '')),
        'facebook_page_name' => trim((string) ($pageData['name'] ?? '')),
        'instagram_business_id' => trim((string) ($instagram['id'] ?? '')),
        'instagram_username' => ltrim(trim((string) ($instagram['username'] ?? '')), '@'),
    ];
}

function creditsoft_meta_social_record_webhook(array $payload, array $summary = []): void
{
    $state = creditsoft_meta_social_load();
    $delivery = [
        'received_at' => gmdate('c'),
        'summary' => $summary,
    ];

    $state['webhooks']['last_received_at'] = $delivery['received_at'];
    $state['webhooks']['last_summary'] = $summary;
    $state['webhooks']['deliveries'] = array_slice(array_merge([$delivery], $state['webhooks']['deliveries']), 0, 25);
    creditsoft_meta_social_save($state);

    creditsoft_meta_social_log('webhook_received', $summary);
}

function creditsoft_meta_social_dashboard(array $tracking): array
{
    $state = creditsoft_meta_social_load();
    $posts = array_values($state['posts'] ?? []);
    $replies = array_values($state['replies']['items'] ?? []);
    $openReplies = array_values(array_filter($replies, static fn (array $item): bool => ($item['status'] ?? 'open') === 'open'));

    return [
        'lead_sync' => $state['lead_sync'],
        'ad_report' => $state['ad_report'],
        'posts' => $posts,
        'replies' => $replies,
        'open_replies' => $openReplies,
        'webhooks' => $state['webhooks'],
        'management_token_ready' => creditsoft_meta_social_management_token($tracking) !== '',
        'publishing_ready' => trim((string) ($tracking['facebook_page_id'] ?? '')) !== '' && creditsoft_meta_social_management_token($tracking) !== '',
    ];
}
