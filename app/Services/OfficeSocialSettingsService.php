<?php

namespace App\Services;

use App\Models\OfficeSocialSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class OfficeSocialSettingsService
{
    public function load(): array
    {
        $defaults = $this->defaults();
        $record = OfficeSocialSetting::query()->first();

        if ($record && is_array($record->payload)) {
            return $this->sanitize($record->payload);
        }

        $legacy = $this->loadLegacyPayload();

        if (! is_array($legacy)) {
            return $defaults;
        }

        return $this->sanitize($legacy);
    }

    public function save(array $input): array
    {
        $clean = $this->sanitize($input);
        $record = OfficeSocialSetting::query()->firstOrNew();
        $record->payload = $clean;
        $record->save();

        return $clean;
    }

    /**
     * @return array<int, string>
     */
    public function metaScopes(?array $settings = null): array
    {
        $settings ??= $this->load();

        $scopes = [
            'pages_show_list',
            'pages_read_engagement',
        ];

        if ((bool) Arr::get($settings, 'publishing.enabled', false)
            && (bool) Arr::get($settings, 'publishing.facebook_page_posts', true)) {
            $scopes[] = 'pages_manage_posts';
        }

        if ((bool) Arr::get($settings, 'ads.enabled', false)) {
            $scopes[] = 'ads_read';

            if ((bool) Arr::get($settings, 'ads.lead_ads_enabled', false)) {
                $scopes[] = 'ads_management';
                $scopes[] = 'business_management';
                $scopes[] = 'leads_retrieval';
            }
        }

        if ((bool) Arr::get($settings, 'publishing.instagram_posts', false)) {
            $scopes[] = 'instagram_basic';
            $scopes[] = 'instagram_content_publish';
        }

        if ((bool) Arr::get($settings, 'whatsapp.enabled', false)) {
            $scopes[] = 'whatsapp_business_management';
            $scopes[] = 'whatsapp_business_messaging';
        }

        return array_values(array_unique($scopes));
    }

    public function buildMetaConnectUrl(string $redirectUri, string $state): ?string
    {
        $settings = $this->load();
        $appId = trim((string) Arr::get($settings, 'meta.app_id'));

        if ($appId === '') {
            return null;
        }

        $businessLoginConfigId = trim((string) Arr::get($settings, 'meta.business_login_config_id'));

        $params = [
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'state' => $state,
            'auth_type' => 'rerequest',
        ];

        if ($businessLoginConfigId !== '') {
            // Facebook Login for Business expects config_id to replace raw scopes.
            $params['config_id'] = $businessLoginConfigId;
        } else {
            $params['scope'] = implode(',', $this->metaScopes($settings));
        }

        return 'https://www.facebook.com/v25.0/dialog/oauth?'.http_build_query($params);
    }

    /**
     * @return array<int, string>
     */
    public function threadsScopes(): array
    {
        return [
            'threads_basic',
            'threads_content_publish',
            'threads_manage_insights',
            'threads_read_replies',
            'threads_manage_replies',
            'threads_manage_mentions',
            'threads_delete',
            'threads_keyword_search',
            'threads_profile_discovery',
            'threads_location_tagging',
            'threads_share_to_instagram',
        ];
    }

    public function buildThreadsConnectUrl(string $redirectUri, string $state): ?string
    {
        $settings = $this->load();
        $appId = trim((string) Arr::get($settings, 'threads.app_id'))
            ?: trim((string) Arr::get($settings, 'meta.app_id'));

        if ($appId === '') {
            return null;
        }

        return 'https://www.threads.net/oauth/authorize?'.http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', $this->threadsScopes()),
            'response_type' => 'code',
            'state' => $state,
        ]);
    }

    /**
     * @return array{success: bool, message: string, settings?: array<string, mixed>}
     */
    public function handleMetaAuthorizationCode(string $code, string $redirectUri): array
    {
        $settings = $this->load();
        $appId = trim((string) Arr::get($settings, 'meta.app_id'));
        $appSecret = trim((string) Arr::get($settings, 'meta.app_secret'));

        if ($appId === '' || $appSecret === '') {
            return [
                'success' => false,
                'message' => 'Meta App ID and App Secret are required before connecting.',
            ];
        }

        $tokenResponse = Http::acceptJson()->get('https://graph.facebook.com/v25.0/oauth/access_token', [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        if (! $tokenResponse->ok()) {
            return [
                'success' => false,
                'message' => $this->graphErrorMessage($tokenResponse, 'Meta did not return an access token yet.'),
            ];
        }

        $token = (string) data_get($tokenResponse->json(), 'access_token', '');

        if ($token === '') {
            return [
                'success' => false,
                'message' => 'Meta access token was blank.',
            ];
        }

        $longLivedResponse = Http::acceptJson()->get('https://graph.facebook.com/v25.0/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'fb_exchange_token' => $token,
        ]);

        $activeToken = $longLivedResponse->ok()
            ? (string) data_get($longLivedResponse->json(), 'access_token', $token)
            : $token;

        $pagesResponse = Http::acceptJson()->get('https://graph.facebook.com/v25.0/me/accounts', [
            'fields' => 'id,name,tasks,category',
            'access_token' => $activeToken,
        ]);

        $pageTokensResponse = Http::acceptJson()->get('https://graph.facebook.com/v25.0/me/accounts', [
            'fields' => 'id,name,access_token,tasks',
            'access_token' => $activeToken,
        ]);

        $adAccountsResponse = Http::acceptJson()->get('https://graph.facebook.com/v25.0/me/adaccounts', [
            'fields' => 'id,name,account_status',
            'access_token' => $activeToken,
        ]);

        if (! $pagesResponse->ok()) {
            return [
                'success' => false,
                'message' => $this->graphErrorMessage($pagesResponse, 'Meta login succeeded, but CreditSoft could not list Facebook Pages yet.'),
            ];
        }

        $visiblePages = $this->extractMetaPages((array) data_get($pagesResponse->json(), 'data', []));
        $tokenPages = $pageTokensResponse->ok()
            ? $this->extractMetaPages((array) data_get($pageTokensResponse->json(), 'data', []))
            : [];

        if ($visiblePages === []) {
            $permissions = $this->metaPermissionSnapshot($activeToken);
            $hasBusinessLoginConfig = trim((string) Arr::get($settings, 'meta.business_login_config_id')) !== '';

            return [
                'success' => false,
                'message' => $hasBusinessLoginConfig
                    ? 'Meta login succeeded, but Facebook returned zero Pages. Confirm the Business Login configuration includes the Page asset, then remove CreditSoft from Business Tools and reconnect.'
                    : 'Meta granted Page scopes but returned zero Pages. Save the Facebook Login for Business Configuration ID in Social settings, then reconnect.',
                'debug' => [
                    'page_list_count' => 0,
                    'has_business_login_config_id' => $hasBusinessLoginConfig,
                    'granted_scopes' => (array) ($permissions['granted'] ?? []),
                    'declined_scopes' => (array) ($permissions['declined'] ?? []),
                ],
            ];
        }

        $tokensByPageId = collect($tokenPages)->keyBy('id');
        $pages = collect($visiblePages)
            ->map(function (array $page) use ($tokensByPageId): array {
                $tokenPage = $tokensByPageId->get($page['id'], []);

                return [
                    'id' => $page['id'],
                    'name' => $page['name'],
                    'access_token' => (string) ($tokenPage['access_token'] ?? $page['access_token'] ?? ''),
                ];
            })
            ->values()
            ->all();

        $adAccounts = collect((array) data_get($adAccountsResponse->json(), 'data', []))
            ->map(fn (array $account): array => [
                'id' => (string) ($account['id'] ?? ''),
                'name' => (string) ($account['name'] ?? ''),
                'account_status' => (string) ($account['account_status'] ?? ''),
            ])
            ->filter(fn (array $account): bool => $account['id'] !== '')
            ->values()
            ->all();

        $firstPage = $pages[0] ?? null;
        $firstAdAccount = $adAccounts[0] ?? null;
        $selectedPage = $this->selectedMetaPageForSettings([
            ...$settings,
            'meta' => [
                ...(array) Arr::get($settings, 'meta', []),
                'available_pages' => $pages,
            ],
        ]);
        $selectedAdAccount = collect($adAccounts)->firstWhere(
            'id',
            trim((string) Arr::get($settings, 'meta.default_ad_account_id')),
        ) ?: $firstAdAccount;

        if (! is_array($firstPage) || trim((string) ($firstPage['access_token'] ?? '')) === '') {
            return [
                'success' => false,
                'message' => 'Meta returned '.$this->pluralizeCount(count($pages), 'Page').', but not a Page access token. Switch Facebook to the personal profile that clicked Connect Meta, then edit CreditSoft in Facebook Business Tools and confirm the Page is selected for both Page-list and Page content permissions. If it still fails, add the Facebook Login for Business configuration ID and reconnect.',
                'debug' => [
                    'page_list_count' => count($pages),
                    'page_token_count' => count(array_filter($pages, fn (array $page): bool => trim((string) ($page['access_token'] ?? '')) !== '')),
                    'page_token_request_ok' => $pageTokensResponse->ok(),
                    'page_token_error' => $pageTokensResponse->ok() ? '' : $this->graphErrorMessage($pageTokensResponse, 'Page token request failed.'),
                ],
            ];
        }

        $settings['meta']['enabled'] = true;
        $settings['meta']['connection_status'] = 'connected';
        $settings['meta']['user_access_token'] = $activeToken;
        $settings['meta']['last_connected_at'] = now()->toIso8601String();
        $settings['meta']['available_pages'] = $pages;
        $settings['meta']['available_ad_accounts'] = $adAccounts;
        $settings['meta']['page_id'] = (string) ($selectedPage['id'] ?? $firstPage['id'] ?? '');
        $settings['meta']['page_name'] = (string) ($selectedPage['name'] ?? $firstPage['name'] ?? '');
        $settings['meta']['page_access_token'] = (string) ($selectedPage['access_token'] ?? $firstPage['access_token'] ?? '');
        $settings['meta']['default_ad_account_id'] = (string) ($selectedAdAccount['id'] ?? '');

        $whatsappSync = $this->syncWhatsAppAssetsForSettings($settings, $activeToken);
        $settings = $whatsappSync['settings'];

        $saved = $this->save($settings);

        $whatsappPhoneCount = count((array) Arr::get($saved, 'whatsapp.available_phone_numbers', []));

        return [
            'success' => true,
            'message' => $whatsappPhoneCount > 0
                ? 'Meta account connected. Page, ad account, and WhatsApp asset options are ready.'
                : 'Meta account connected.',
            'settings' => $saved,
        ];
    }

    /**
     * @return array{success: bool, message: string, settings?: array<string, mixed>, debug?: array<string, mixed>}
     */
    public function handleThreadsAuthorizationCode(string $code, string $redirectUri): array
    {
        $settings = $this->load();
        $appId = trim((string) Arr::get($settings, 'threads.app_id'))
            ?: trim((string) Arr::get($settings, 'meta.app_id'));
        $appSecret = trim((string) Arr::get($settings, 'threads.app_secret'))
            ?: trim((string) Arr::get($settings, 'meta.app_secret'));

        if ($appId === '' || $appSecret === '') {
            return [
                'success' => false,
                'message' => 'Threads App ID and App Secret are required before connecting. CreditSoft can use the Meta app values if the Threads-specific fields are blank.',
            ];
        }

        $tokenResponse = Http::acceptJson()
            ->asForm()
            ->post('https://graph.threads.net/oauth/access_token', [
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ]);

        if (! $tokenResponse->ok()) {
            return [
                'success' => false,
                'message' => $this->graphErrorMessage($tokenResponse, 'Threads did not return an access token yet.'),
                'debug' => [
                    'http_status' => $tokenResponse->status(),
                    'redirect_uri' => $redirectUri,
                ],
            ];
        }

        $shortToken = trim((string) data_get($tokenResponse->json(), 'access_token', ''));
        $userId = trim((string) data_get($tokenResponse->json(), 'user_id', ''));

        if ($shortToken === '') {
            return [
                'success' => false,
                'message' => 'Threads returned a blank access token.',
            ];
        }

        $longLivedResponse = Http::acceptJson()->get('https://graph.threads.net/access_token', [
            'grant_type' => 'th_exchange_token',
            'client_secret' => $appSecret,
            'access_token' => $shortToken,
        ]);

        $longLivedToken = $longLivedResponse->ok()
            ? trim((string) data_get($longLivedResponse->json(), 'access_token', ''))
            : '';
        $activeToken = $longLivedToken !== '' ? $longLivedToken : $shortToken;

        $settings['threads']['enabled'] = true;
        $settings['threads']['app_id'] = $appId;
        $settings['threads']['app_secret'] = $appSecret;
        $settings['threads']['access_token'] = $activeToken;
        $settings['threads']['user_id'] = $userId;
        $settings['threads']['connection_status'] = 'connected';
        $settings['threads']['last_error'] = '';

        $this->save($settings);

        return $this->runThreadsApiTestSuite();
    }

    /**
     * @return array{success: bool, message: string, settings?: array<string, mixed>}
     */
    public function syncWhatsAppAssets(): array
    {
        $settings = $this->load();
        $token = trim((string) Arr::get($settings, 'meta.user_access_token'));

        if ($token === '') {
            $settings['whatsapp']['connection_status'] = 'not_connected';
            $settings['whatsapp']['last_error'] = 'Connect Meta first so CreditSoft can read WhatsApp Business assets.';
            $saved = $this->save($settings);

            return [
                'success' => false,
                'message' => $settings['whatsapp']['last_error'],
                'settings' => $saved,
            ];
        }

        $result = $this->syncWhatsAppAssetsForSettings($settings, $token);
        $saved = $this->save($result['settings']);

        return [
            'success' => (bool) $result['success'],
            'message' => (string) $result['message'],
            'settings' => $saved,
        ];
    }

    /**
     * @return array{success: bool, message: string, settings: array<string, mixed>}
     */
    public function runMetaApiTestSuite(): array
    {
        $settings = $this->load();
        $selectedPage = $this->selectedMetaPageForSettings($settings);
        $userAccessToken = trim((string) Arr::get($settings, 'meta.user_access_token'));
        $pageAccessToken = trim((string) ($selectedPage['access_token'] ?? '')) ?: trim((string) Arr::get($settings, 'meta.page_access_token'));
        $pageId = trim((string) ($selectedPage['id'] ?? '')) ?: trim((string) Arr::get($settings, 'meta.page_id'));
        $businessId = trim((string) Arr::get($settings, 'meta.business_id')) ?: trim((string) Arr::get($settings, 'website_signals.meta_business_id'));
        $instagramBusinessId = trim((string) Arr::get($settings, 'meta.instagram_business_id')) ?: trim((string) Arr::get($settings, 'website_signals.instagram_business_id'));
        $adAccountId = $this->normalizeAdAccountId(
            trim((string) Arr::get($settings, 'meta.default_ad_account_id'))
                ?: trim((string) Arr::get($settings, 'website_signals.meta_ad_account_id')),
        );
        $adAccountGraphId = $adAccountId !== '' && str_starts_with($adAccountId, 'act_')
            ? $adAccountId
            : ($adAccountId !== '' ? 'act_'.$adAccountId : '');
        $wabaId = trim((string) Arr::get($settings, 'whatsapp.business_account_id')) ?: trim((string) Arr::get($settings, 'website_signals.whatsapp_business_account_id'));
        $readToken = $pageAccessToken !== '' ? $pageAccessToken : $userAccessToken;

        if ($userAccessToken === '') {
            return $this->finishMetaApiTestSuite($settings, [
                $this->metaApiFailedResult(
                    'token',
                    'Meta user token',
                    'token',
                    '/me/permissions',
                    'Connect Meta first. CreditSoft needs a real OAuth token before the Testing tab can see API use.',
                ),
            ]);
        }

        $results = [
            $this->runMetaGraphReadTest(
                'permissions',
                'Permission snapshot',
                'token',
                '/me/permissions',
                [],
                $userAccessToken,
            ),
            $this->runMetaGraphReadTest(
                'page-list',
                'Facebook Page list',
                'pages_show_list',
                '/me/accounts',
                ['fields' => 'id,name,tasks', 'limit' => 10],
                $userAccessToken,
            ),
        ];

        if ($pageId !== '' && $readToken !== '') {
            $results[] = $this->runMetaGraphReadTest(
                'page-read',
                'Selected Page profile',
                'pages_read_engagement',
                "/{$pageId}",
                ['fields' => 'id,name,fan_count,followers_count'],
                $readToken,
            );
            $results[] = $this->runMetaGraphReadTest(
                'page-posts',
                'Selected Page posts',
                'pages_read_engagement',
                "/{$pageId}/posts",
                [
                    'fields' => 'id,message,created_time,permalink_url',
                    'limit' => 5,
                ],
                $readToken,
            );
        } else {
            $results[] = $this->metaApiSkippedResult(
                'page-read',
                'Selected Page profile',
                'pages_read_engagement',
                '/{page_id}',
                'No selected Facebook Page token is saved yet.',
            );
        }

        $results[] = $this->runMetaGraphReadTest(
            'ad-accounts',
            'Ad account list',
            'ads_read',
            '/me/adaccounts',
            ['fields' => 'id,name,account_status', 'limit' => 10],
            $userAccessToken,
        );

        if ($adAccountGraphId !== '') {
            $results[] = $this->runMetaGraphReadTest(
                'selected-ad-account',
                'Selected ad account',
                'ads_read',
                "/{$adAccountGraphId}",
                ['fields' => 'id,name,account_status,currency,timezone_name'],
                $userAccessToken,
            );
        } else {
            $results[] = $this->metaApiSkippedResult(
                'selected-ad-account',
                'Selected ad account',
                'ads_read',
                '/act_{ad_account_id}',
                'No default ad account is selected yet.',
            );
        }

        if ($businessId !== '') {
            $results[] = $this->runMetaGraphReadTest(
                'business',
                'Meta business',
                'business_management',
                "/{$businessId}",
                ['fields' => 'id,name'],
                $userAccessToken,
            );
        } else {
            $results[] = $this->metaApiSkippedResult(
                'business',
                'Meta business',
                'business_management',
                '/{business_id}',
                'No Meta Business ID is saved yet.',
            );
        }

        if ($instagramBusinessId !== '' && $readToken !== '') {
            $results[] = $this->runMetaGraphReadTest(
                'instagram-business',
                'Instagram business profile',
                'instagram_basic',
                "/{$instagramBusinessId}",
                ['fields' => 'id,username,name,profile_picture_url'],
                $readToken,
            );
        } else {
            $results[] = $this->metaApiSkippedResult(
                'instagram-business',
                'Instagram business profile',
                'instagram_basic',
                '/{instagram_business_id}',
                'No Instagram Business ID is saved yet.',
            );
        }

        if ($wabaId !== '') {
            $results[] = $this->runMetaGraphReadTest(
                'whatsapp-phones',
                'WhatsApp phone numbers',
                'whatsapp_business_management',
                "/{$wabaId}/phone_numbers",
                [
                    'fields' => 'id,display_phone_number,verified_name,quality_rating,code_verification_status',
                    'limit' => 10,
                ],
                $userAccessToken,
            );
        } else {
            $results[] = $this->metaApiSkippedResult(
                'whatsapp-phones',
                'WhatsApp phone numbers',
                'whatsapp_business_management',
                '/{waba_id}/phone_numbers',
                'No WhatsApp Business Account ID is saved yet.',
            );
        }

        $results[] = $this->metaApiManualResult(
            'facebook-publish',
            'Facebook Page publishing',
            'pages_manage_posts',
            '/{page_id}/feed',
            'Creating a Page post is a write smoke test. CreditSoft will not post automatically from the API test runner.',
        );
        $results[] = $this->metaApiManualResult(
            'instagram-publish',
            'Instagram publishing',
            'instagram_content_publish',
            '/{instagram_business_id}/media',
            'Publishing Instagram media needs hosted media and explicit smoke-test approval.',
        );
        $results[] = $this->metaApiManualResult(
            'whatsapp-send',
            'WhatsApp messaging',
            'whatsapp_business_messaging',
            '/{phone_number_id}/messages',
            'Sending a WhatsApp message needs a real test recipient and explicit approval.',
        );

        return $this->finishMetaApiTestSuite($settings, $results);
    }

    /**
     * @return array{success: bool, message: string, settings: array<string, mixed>}
     */
    public function runThreadsApiTestSuite(): array
    {
        $settings = $this->load();
        $token = trim((string) Arr::get($settings, 'threads.access_token'));
        $username = trim((string) Arr::get($settings, 'threads.username'))
            ?: trim((string) Arr::get($settings, 'website_signals.threads_username'));

        if ($token === '') {
            return $this->finishThreadsApiTestSuite($settings, [
                $this->metaApiFailedResult(
                    'threads-token',
                    'Threads access token',
                    'threads_basic',
                    '/me',
                    'Paste a Threads user access token first. The Facebook Page token cannot satisfy the Threads API testing checklist.',
                ),
            ]);
        }

        $results = [];
        $profile = $this->runThreadsGraphApiTest(
            'threads-basic',
            'Threads profile',
            'threads_basic',
            'get',
            '/me',
            ['fields' => 'id,name,username,threads_profile_picture_url,threads_biography'],
            $token,
        );
        $results[] = $profile['result'];

        if (($profile['result']['status'] ?? '') === 'failed') {
            return $this->finishThreadsApiTestSuite($settings, $results);
        }

        $profileJson = (array) ($profile['json'] ?? []);
        $threadsUserId = trim((string) ($profileJson['id'] ?? Arr::get($settings, 'threads.user_id', '')));
        $resolvedUsername = trim((string) ($profileJson['username'] ?? $profileJson['name'] ?? $username));

        if ($threadsUserId !== '') {
            $settings['threads']['user_id'] = $threadsUserId;
        }

        if ($resolvedUsername !== '') {
            $settings['threads']['username'] = $resolvedUsername;
            $settings['website_signals']['threads_username'] = $resolvedUsername;
        }

        $posts = $this->runThreadsGraphApiTest(
            'threads-post-list',
            'Threads post list',
            'threads_basic',
            'get',
            '/me/threads',
            [
                'fields' => 'id,text,timestamp,permalink,has_replies',
                'limit' => 5,
            ],
            $token,
        );
        $results[] = $posts['result'];

        $firstThreadId = collect((array) data_get($posts, 'json.data', []))
            ->map(fn ($post): string => is_array($post) ? trim((string) ($post['id'] ?? '')) : '')
            ->first(fn (string $id): bool => $id !== '') ?? '';

        $container = $this->runThreadsGraphApiTest(
            'threads-content-container',
            'Threads unpublished text container',
            'threads_content_publish',
            'post',
            '/me/threads',
            [
                'media_type' => 'TEXT',
                'text' => 'CreditSoft Threads API smoke-test container. Do not publish.',
            ],
            $token,
        );
        $results[] = $container['result'];
        $containerId = trim((string) data_get($container, 'json.id', ''));

        if ($containerId !== '') {
            $settings['threads']['last_container_id'] = $containerId;
        }

        $results[] = $this->runThreadsGraphApiTest(
            'threads-profile-discovery',
            'Threads profile discovery',
            'threads_profile_discovery',
            'get',
            '/profile_lookup',
            ['username' => $resolvedUsername !== '' ? $resolvedUsername : 'threads'],
            $token,
        )['result'];

        $results[] = $this->runThreadsGraphApiTest(
            'threads-keyword-search',
            'Threads keyword search',
            'threads_keyword_search',
            'get',
            '/keyword_search',
            [
                'q' => 'creditsoft',
                'search_type' => 'TOP',
                'fields' => 'id,text,media_type,permalink,timestamp,username',
                'limit' => 5,
            ],
            $token,
        )['result'];

        $results[] = $this->runThreadsGraphApiTest(
            'threads-mentions',
            'Threads mentions',
            'threads_manage_mentions',
            'get',
            '/me/mentions',
            [
                'fields' => 'id,text,permalink,timestamp,username',
                'limit' => 5,
            ],
            $token,
        )['result'];

        $results[] = $this->runThreadsGraphApiTest(
            'threads-location-search',
            'Threads location lookup',
            'threads_location_tagging',
            'get',
            '/location_search',
            [
                'q' => 'New York',
                'fields' => 'id,name,city,country',
                'limit' => 5,
            ],
            $token,
        )['result'];

        $results[] = $this->runThreadsGraphApiTest(
            'threads-insights',
            'Threads user insights',
            'threads_manage_insights',
            'get',
            '/me/threads_insights',
            ['metric' => 'views'],
            $token,
        )['result'];

        if ($firstThreadId !== '') {
            $results[] = $this->runThreadsGraphApiTest(
                'threads-read-replies',
                'Threads replies',
                'threads_read_replies',
                'get',
                "/{$firstThreadId}/replies",
                [
                    'fields' => 'id,text,timestamp,username,hide_status',
                    'reverse' => 'false',
                    'limit' => 5,
                ],
                $token,
            )['result'];
        } else {
            $results[] = $this->metaApiSkippedResult(
                'threads-read-replies',
                'Threads replies',
                'threads_read_replies',
                '/{thread_id}/replies',
                'No existing Threads post was returned, so CreditSoft cannot read replies yet.',
            );
        }

        $results[] = $this->metaApiManualResult(
            'threads-manage-replies',
            'Threads manage replies',
            'threads_manage_replies',
            '/{threads_reply_id}/manage_reply',
            'Managing replies hides or unhides real replies. CreditSoft will keep this as an explicit smoke test.',
        );
        $results[] = $this->metaApiManualResult(
            'threads-delete',
            'Threads delete',
            'threads_delete',
            '/{threads_media_id}',
            'Deleting requires a real published post. CreditSoft will not delete content from the general API test runner.',
        );
        $results[] = $this->metaApiManualResult(
            'threads-share-to-instagram',
            'Threads share to Instagram',
            'threads_share_to_instagram',
            '/{threads_media_id}/share_to_instagram',
            'Sharing to Instagram is a visible cross-post action and needs explicit smoke-test approval.',
        );

        return $this->finishThreadsApiTestSuite($settings, $results);
    }

    /**
     * @return array{success: bool, message: string, settings?: array<string, mixed>, imported_fields?: int}
     */
    public function importFromWebsiteTracking(): array
    {
        if (! is_file($this->websiteTrackingPath())) {
            return [
                'success' => false,
                'message' => 'Website admin tracking file was not found yet.',
            ];
        }

        $tracking = $this->loadWebsiteTracking();

        if ($tracking === []) {
            return [
                'success' => false,
                'message' => 'Website admin tracking settings could not be read.',
            ];
        }

        $settings = $this->load();
        $importedAt = now()->toIso8601String();

        $settings['website_signals'] = [
            'source_path' => $this->websiteTrackingPath(),
            'imported_at' => $importedAt,
            'meta_pixel_id' => $this->trackingValue($tracking, 'meta_pixel_id'),
            'meta_app_id' => $this->trackingValue($tracking, 'meta_app_id'),
            'meta_business_id' => $this->trackingValue($tracking, 'meta_business_id'),
            'meta_management_token' => $this->trackingValue($tracking, 'meta_management_token'),
            'meta_webhook_verify_token' => $this->trackingValue($tracking, 'meta_webhook_verify_token'),
            'facebook_page_id' => $this->trackingValue($tracking, 'facebook_page_id'),
            'instagram_business_id' => $this->trackingValue($tracking, 'instagram_business_id'),
            'instagram_username' => $this->trackingValue($tracking, 'instagram_username'),
            'threads_username' => $this->trackingValue($tracking, 'threads_username'),
            'x_username' => $this->trackingValue($tracking, 'x_username'),
            'meta_ad_account_id' => $this->normalizeAdAccountId($this->trackingValue($tracking, 'meta_ad_account_id')),
            'lead_form_name' => $this->trackingValue($tracking, 'lead_form_name'),
            'campaign_objective' => $this->trackingValue($tracking, 'campaign_objective') ?: 'OUTCOME_LEADS',
            'meta_capi_token' => $this->trackingValue($tracking, 'meta_capi_token'),
            'meta_capi_test_event_code' => $this->trackingValue($tracking, 'meta_capi_test_event_code'),
            'weekly_budget' => $this->trackingValue($tracking, 'weekly_budget'),
            'daily_cap' => $this->trackingValue($tracking, 'daily_cap'),
            'monthly_cap' => $this->trackingValue($tracking, 'monthly_cap'),
            'whatsapp_enabled' => (bool) ($tracking['whatsapp_enabled'] ?? false),
            'whatsapp_display_number' => $this->trackingValue($tracking, 'whatsapp_display_number'),
            'whatsapp_phone_number_id' => $this->trackingValue($tracking, 'whatsapp_phone_number_id'),
            'whatsapp_business_account_id' => $this->trackingValue($tracking, 'whatsapp_business_account_id'),
            'whatsapp_verify_token' => $this->trackingValue($tracking, 'whatsapp_verify_token'),
            'whatsapp_default_message' => $this->trackingValue($tracking, 'whatsapp_default_message'),
        ];

        $importedFieldCount = 0;

        $importedFieldCount += $this->applyImportedValue($settings, 'meta.app_id', $settings['website_signals']['meta_app_id']);
        $importedFieldCount += $this->applyImportedValue($settings, 'meta.business_id', $settings['website_signals']['meta_business_id']);
        $importedFieldCount += $this->applyImportedValue($settings, 'meta.page_id', $settings['website_signals']['facebook_page_id']);
        $importedFieldCount += $this->applyImportedValue($settings, 'meta.instagram_business_id', $settings['website_signals']['instagram_business_id']);
        $importedFieldCount += $this->applyImportedValue($settings, 'meta.default_ad_account_id', $settings['website_signals']['meta_ad_account_id']);
        $importedFieldCount += $this->applyImportedValue($settings, 'threads.username', $settings['website_signals']['threads_username']);
        $importedFieldCount += $this->applyImportedValue($settings, 'ads.default_objective', $settings['website_signals']['campaign_objective']);
        $importedFieldCount += $this->applyImportedValue($settings, 'ads.default_form_name', $settings['website_signals']['lead_form_name']);
        $importedFieldCount += $this->applyImportedValue($settings, 'ads.daily_cap', $settings['website_signals']['daily_cap']);
        $importedFieldCount += $this->applyImportedValue($settings, 'ads.monthly_cap', $settings['website_signals']['monthly_cap']);
        $importedFieldCount += $this->applyImportedValue($settings, 'whatsapp.display_number', $settings['website_signals']['whatsapp_display_number']);
        $importedFieldCount += $this->applyImportedValue($settings, 'whatsapp.phone_number_id', $settings['website_signals']['whatsapp_phone_number_id']);
        $importedFieldCount += $this->applyImportedValue($settings, 'whatsapp.business_account_id', $settings['website_signals']['whatsapp_business_account_id']);
        $importedFieldCount += $this->applyImportedValue($settings, 'whatsapp.verify_token', $settings['website_signals']['whatsapp_verify_token']);

        $managementToken = $settings['website_signals']['meta_management_token'];
        $fallbackToken = $settings['website_signals']['meta_capi_token'];
        $primaryToken = $managementToken !== '' ? $managementToken : $fallbackToken;

        $importedFieldCount += $this->applyImportedValue($settings, 'meta.user_access_token', $primaryToken);

        if ($settings['website_signals']['facebook_page_id'] !== '') {
            $pageId = $settings['website_signals']['facebook_page_id'];
            $existingPageName = trim((string) Arr::get($settings, 'meta.page_name'));
            $currentPageId = trim((string) Arr::get($settings, 'meta.page_id'));

            if ($existingPageName === '' || $currentPageId !== $pageId) {
                $settings['meta']['page_name'] = 'Imported website Page';
            }

            $settings['meta']['available_pages'] = $this->mergeImportedPage(
                (array) Arr::get($settings, 'meta.available_pages', []),
                $pageId,
                trim((string) Arr::get($settings, 'meta.page_name', '')) ?: 'Imported website Page',
            );
        }

        if ($settings['website_signals']['meta_ad_account_id'] !== '') {
            $settings['meta']['available_ad_accounts'] = $this->mergeImportedAdAccount(
                (array) Arr::get($settings, 'meta.available_ad_accounts', []),
                $settings['website_signals']['meta_ad_account_id'],
            );
        }

        $hasImportedMetaFootprint = collect([
            $settings['website_signals']['meta_pixel_id'],
            $settings['website_signals']['facebook_page_id'],
            $settings['website_signals']['meta_management_token'],
            $settings['website_signals']['meta_capi_token'],
            $settings['website_signals']['meta_app_id'],
            $settings['website_signals']['meta_business_id'],
        ])->contains(fn (?string $value): bool => trim((string) $value) !== '');

        if ($hasImportedMetaFootprint) {
            $settings['meta']['enabled'] = true;
        }

        if (trim((string) Arr::get($settings, 'meta.connection_status')) !== 'connected' && $hasImportedMetaFootprint) {
            $settings['meta']['connection_status'] = 'imported';
        }

        $hasImportedAds = collect([
            $settings['website_signals']['meta_ad_account_id'],
            $settings['website_signals']['weekly_budget'],
            $settings['website_signals']['daily_cap'],
            $settings['website_signals']['monthly_cap'],
            $settings['website_signals']['lead_form_name'],
        ])->contains(fn (?string $value): bool => trim((string) $value) !== '');

        $adsLookDefault = trim((string) Arr::get($settings, 'meta.default_ad_account_id')) === ''
            && trim((string) Arr::get($settings, 'ads.default_form_name')) === ''
            && trim((string) Arr::get($settings, 'ads.daily_cap')) === '0'
            && trim((string) Arr::get($settings, 'ads.monthly_cap')) === '0'
            && trim((string) Arr::get($settings, 'ads.default_objective')) === 'OUTCOME_LEADS'
            && ! (bool) Arr::get($settings, 'ads.lead_ads_enabled');

        if ($hasImportedAds) {
            $settings['ads']['enabled'] = true;
        } elseif ($adsLookDefault) {
            $settings['ads']['enabled'] = false;
        }

        $hasImportedWhatsApp = $settings['website_signals']['whatsapp_enabled']
            || collect([
                $settings['website_signals']['whatsapp_display_number'],
                $settings['website_signals']['whatsapp_phone_number_id'],
                $settings['website_signals']['whatsapp_business_account_id'],
            ])->contains(fn (?string $value): bool => trim((string) $value) !== '');

        if ($hasImportedWhatsApp) {
            $settings['whatsapp']['enabled'] = true;
        }

        $saved = $this->save($settings);

        return [
            'success' => true,
            'message' => $importedFieldCount > 0
                ? 'Website admin Meta settings imported into the office lane.'
                : 'Website admin snapshot imported. Nothing new overwrote the office fields yet.',
            'settings' => $saved,
            'imported_fields' => $importedFieldCount,
        ];
    }

    /**
     * @return array{success: bool, message: string, settings?: array<string, mixed>, live_sync?: array<string, mixed>}
     */
    public function syncCreatorChallengeLiveMetrics(): array
    {
        $settings = $this->load();
        $selectedPage = $this->selectedMetaPageForSettings($settings);
        $pageId = trim((string) ($selectedPage['id'] ?? ''));
        $pageName = trim((string) ($selectedPage['name'] ?? ''));
        $pageAccessToken = trim((string) ($selectedPage['access_token'] ?? ''));
        $userAccessToken = trim((string) Arr::get($settings, 'meta.user_access_token'));
        $token = $pageAccessToken !== '' ? $pageAccessToken : $userAccessToken;
        $windowDays = $this->boundedMetricInt(Arr::get($settings, 'creator_challenge.live_sync.window_days', '7'), 7, 1, 30);

        if ($pageId !== '') {
            $settings['meta']['page_id'] = $pageId;
            $settings['meta']['page_name'] = $pageName;
            $settings['meta']['page_access_token'] = $pageAccessToken;
        }

        if ($pageId === '' || $token === '') {
            return $this->syncCreatorChallengeFailure(
                $settings,
                'Connect Meta with a page-readable token first so CreditSoft can pull live posts, comments, and shares.',
            );
        }

        $pageResponse = Http::acceptJson()->get("https://graph.facebook.com/v25.0/{$pageId}", [
            'fields' => 'id,name,fan_count,followers_count',
            'access_token' => $token,
        ]);

        if (! $pageResponse->ok()) {
            return $this->syncCreatorChallengeFailure(
                $settings,
                $this->graphErrorMessage($pageResponse, 'CreditSoft could not read the Facebook Page yet.'),
            );
        }

        $engagementFieldError = '';
        $postsResponse = Http::acceptJson()->get("https://graph.facebook.com/v25.0/{$pageId}/posts", [
            'limit' => 25,
            'since' => now()->subDays($windowDays)->startOfDay()->timestamp,
            'fields' => 'id,message,created_time,permalink_url,shares,reactions.summary(total_count).limit(0),comments.limit(50).summary(true){id,from{id,name},message,created_time,like_count,comments.limit(25){id,from{id,name},message,created_time,like_count}}',
            'access_token' => $token,
        ]);

        if (! $postsResponse->ok()) {
            $engagementFieldError = $this->graphErrorMessage($postsResponse, 'Meta blocked engagement fields for this Page token.');
            $postsResponse = Http::acceptJson()->get("https://graph.facebook.com/v25.0/{$pageId}/posts", [
                'limit' => 25,
                'since' => now()->subDays($windowDays)->startOfDay()->timestamp,
                'fields' => 'id,message,created_time,permalink_url,shares',
                'access_token' => $token,
            ]);
        }

        if (! $postsResponse->ok()) {
            return $this->syncCreatorChallengeFailure(
                $settings,
                $this->graphErrorMessage($postsResponse, 'CreditSoft could not read the Page posts yet.'),
            );
        }

        $pageData = (array) $pageResponse->json();
        $pageCanonicalId = trim((string) ($pageData['id'] ?? $pageId));
        $resolvedPageName = trim((string) ($pageData['name'] ?? $pageName)) ?: ($pageName !== '' ? $pageName : 'Connected Facebook Page');

        $posts = collect((array) data_get($postsResponse->json(), 'data', []))
            ->map(function (mixed $post) use ($pageCanonicalId, $resolvedPageName): ?array {
                if (! is_array($post)) {
                    return null;
                }

                $comments = collect((array) data_get($post, 'comments.data', []));
                $commentLikes = 0;
                $repliedThreads = 0;

                foreach ($comments as $comment) {
                    if (! is_array($comment)) {
                        continue;
                    }

                    $commentLikes += (int) ($comment['like_count'] ?? 0);
                    $replies = collect((array) data_get($comment, 'comments.data', []));
                    $commentLikes += $replies->sum(fn (mixed $reply): int => is_array($reply) ? (int) ($reply['like_count'] ?? 0) : 0);

                    $hasOfficeReply = $replies->contains(function (mixed $reply) use ($pageCanonicalId, $resolvedPageName): bool {
                        if (! is_array($reply)) {
                            return false;
                        }

                        $replyAuthorId = trim((string) data_get($reply, 'from.id', ''));
                        $replyAuthorName = trim((string) data_get($reply, 'from.name', ''));

                        return ($replyAuthorId !== '' && $replyAuthorId === $pageCanonicalId)
                            || ($replyAuthorName !== '' && strcasecmp($replyAuthorName, $resolvedPageName) === 0);
                    });

                    if ($hasOfficeReply) {
                        $repliedThreads++;
                    }
                }

                return [
                    'id' => trim((string) ($post['id'] ?? '')),
                    'message' => trim((string) ($post['message'] ?? '')),
                    'created_time' => trim((string) ($post['created_time'] ?? '')),
                    'permalink_url' => trim((string) ($post['permalink_url'] ?? '')),
                    'comments' => (int) data_get($post, 'comments.summary.total_count', $comments->count()),
                    'public_shares' => (int) data_get($post, 'shares.count', 0),
                    'reactions' => (int) data_get($post, 'reactions.summary.total_count', 0),
                    'comment_likes' => $commentLikes,
                    'replied_threads' => $repliedThreads,
                ];
            })
            ->filter()
            ->values();

        $settings['creator_challenge']['live_sync'] = [
            'status' => $engagementFieldError === '' ? 'live' : 'live_limited',
            'last_synced_at' => now()->toIso8601String(),
            'last_error' => $engagementFieldError === ''
                ? ''
                : 'Limited Meta sync: CreditSoft can read posts and public shares, but Meta blocked comment/reaction fields. '.$engagementFieldError,
            'window_days' => (string) $windowDays,
            'page' => [
                'id' => $pageCanonicalId,
                'name' => $resolvedPageName,
                'fan_count' => (string) ($pageData['fan_count'] ?? ''),
                'followers_count' => (string) ($pageData['followers_count'] ?? ''),
            ],
            'totals' => [
                'posts' => (string) $posts->count(),
                'comments' => (string) $posts->sum('comments'),
                'public_shares' => (string) $posts->sum('public_shares'),
                'reply_windows' => (string) $posts->sum('replied_threads'),
                'leads' => '0',
                'comment_likes' => (string) $posts->sum('comment_likes'),
                'reactions' => (string) $posts->sum('reactions'),
            ],
            'top_posts' => $posts
                ->sortByDesc(function (array $post) use ($settings): int {
                    $commentPoints = $this->boundedMetricInt(Arr::get($settings, 'creator_challenge.comment_points', '6'), 6, 1, 100);
                    $sharePoints = $this->boundedMetricInt(Arr::get($settings, 'creator_challenge.public_share_points', '8'), 8, 1, 100);
                    $postPoints = $this->boundedMetricInt(Arr::get($settings, 'creator_challenge.published_post_points', '3'), 3, 1, 100);
                    $bonusPoints = $this->boundedMetricInt(Arr::get($settings, 'creator_challenge.comment_like_bonus_points', '1'), 1, 1, 100);
                    $bonusStep = $this->boundedMetricInt(Arr::get($settings, 'creator_challenge.comment_like_bonus_step', '5'), 5, 1, 100);

                    return $postPoints
                        + ($post['comments'] * $commentPoints)
                        + ($post['public_shares'] * $sharePoints)
                        + (int) floor(((int) $post['comment_likes']) / $bonusStep) * $bonusPoints;
                })
                ->take(10)
                ->values()
                ->all(),
        ];

        $saved = $this->save($settings);

        return [
            'success' => true,
            'message' => $engagementFieldError === ''
                ? "Live Meta challenge data synced for {$resolvedPageName}."
                : "Limited Meta challenge data synced for {$resolvedPageName}. Posts and public shares are live; comments and reactions need Meta engagement access.",
            'settings' => $saved,
            'live_sync' => (array) Arr::get($saved, 'creator_challenge.live_sync', []),
        ];
    }

    public function defaults(): array
    {
        return [
            'meta' => [
                'enabled' => false,
                'app_id' => '',
                'app_secret' => '',
                'business_login_config_id' => '',
                'business_id' => '',
                'system_user_id' => '',
                'user_access_token' => '',
                'page_id' => '',
                'page_name' => '',
                'page_access_token' => '',
                'instagram_business_id' => '',
                'default_ad_account_id' => '',
                'available_pages' => [],
                'available_ad_accounts' => [],
                'connection_status' => 'not_connected',
                'last_connected_at' => '',
                'api_test' => [
                    'status' => 'not_run',
                    'last_tested_at' => '',
                    'last_error' => '',
                    'results' => [],
                ],
            ],
            'publishing' => [
                'enabled' => false,
                'facebook_page_posts' => true,
                'instagram_posts' => false,
                'approval_required' => true,
                'auto_publish_releases' => true,
                'auto_publish_features' => false,
                'auto_publish_reviews' => false,
                'default_cta' => 'learn_more',
                'cadence' => 'manual',
            ],
            'ads' => [
                'enabled' => false,
                'lead_ads_enabled' => false,
                'default_objective' => 'OUTCOME_LEADS',
                'monthly_cap' => '0',
                'daily_cap' => '0',
                'default_campaign_name' => 'CreditSoft - {date} - Leads',
                'default_destination' => 'website',
                'default_form_name' => '',
            ],
            'whatsapp' => [
                'enabled' => false,
                'lead_handoff_enabled' => true,
                'appointment_reminders_enabled' => false,
                'number_strategy' => 'new_number',
                'display_number' => '',
                'phone_number_id' => '',
                'business_account_id' => '',
                'default_template_name' => '',
                'verify_token' => '',
                'fallback_agent_number' => '',
                'connection_status' => 'not_configured',
                'available_business_accounts' => [],
                'available_phone_numbers' => [],
                'last_synced_at' => '',
                'last_error' => '',
            ],
            'threads' => [
                'enabled' => false,
                'app_id' => '',
                'app_secret' => '',
                'user_id' => '',
                'username' => '',
                'access_token' => '',
                'last_container_id' => '',
                'verification_blocker' => 'meta_verification',
                'manual_workflow_enabled' => true,
                'manual_draft' => '',
                'manual_media_url' => '',
                'manual_published_url' => '',
                'manual_notes' => '',
                'connection_status' => 'not_connected',
                'last_error' => '',
                'api_test' => [
                    'status' => 'not_run',
                    'last_tested_at' => '',
                    'last_error' => '',
                    'results' => [],
                ],
            ],
            'customer_uploads' => [
                'dropbox_request_enabled' => false,
                'dropbox_request_link' => '',
                'google_drive_upload_enabled' => false,
                'google_drive_folder_link' => '',
                'intake_copy' => '',
            ],
            'website_signals' => [
                'source_path' => '',
                'imported_at' => '',
                'meta_pixel_id' => '',
                'meta_app_id' => '',
                'meta_business_id' => '',
                'meta_management_token' => '',
                'meta_webhook_verify_token' => '',
                'facebook_page_id' => '',
                'instagram_business_id' => '',
                'instagram_username' => '',
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
            ],
            'creator_challenge' => [
                'enabled' => false,
                'track_weekly_challenge' => true,
                'require_goal_completion' => true,
                'ai_guidance_enabled' => true,
                'challenge_name' => 'Weekly creator challenge',
                'challenge_window' => 'weekly',
                'leaderboard_depth' => '10',
                'first_place_label' => 'First place winner',
                'second_place_label' => 'Second place',
                'third_place_label' => 'Third place',
                'placement_tier_label' => 'Placement tier',
                'comment_points' => '6',
                'public_share_points' => '8',
                'published_post_points' => '3',
                'comment_like_bonus_points' => '1',
                'comment_like_bonus_step' => '5',
                'goal_posts' => '4',
                'goal_comments' => '20',
                'goal_public_shares' => '10',
                'goal_reply_windows' => '12',
                'goal_leads' => '3',
                'tie_breaker' => 'shares_then_comments',
                'live_sync' => [
                    'status' => 'not_synced',
                    'last_synced_at' => '',
                    'last_error' => '',
                    'window_days' => '7',
                    'page' => [
                        'id' => '',
                        'name' => '',
                        'fan_count' => '',
                        'followers_count' => '',
                    ],
                    'totals' => [
                        'posts' => '0',
                        'comments' => '0',
                        'public_shares' => '0',
                        'reply_windows' => '0',
                        'leads' => '0',
                        'comment_likes' => '0',
                        'reactions' => '0',
                    ],
                    'top_posts' => [],
                ],
            ],
        ];
    }

    protected function sanitize(array $input): array
    {
        $defaults = $this->defaults();

        $pages = collect((array) Arr::get($input, 'meta.available_pages', []))
            ->map(fn ($page): array => [
                'id' => trim((string) Arr::get($page, 'id')),
                'name' => trim((string) Arr::get($page, 'name')),
                'access_token' => trim((string) Arr::get($page, 'access_token')),
            ])
            ->filter(fn (array $page): bool => $page['id'] !== '')
            ->values()
            ->all();

        $adAccounts = collect((array) Arr::get($input, 'meta.available_ad_accounts', []))
            ->map(fn ($account): array => [
                'id' => trim((string) Arr::get($account, 'id')),
                'name' => trim((string) Arr::get($account, 'name')),
                'account_status' => trim((string) Arr::get($account, 'account_status')),
            ])
            ->filter(fn (array $account): bool => $account['id'] !== '')
            ->values()
            ->all();

        $apiTestResults = collect((array) Arr::get($input, 'meta.api_test.results', []))
            ->map(fn ($result): array => [
                'key' => trim((string) Arr::get($result, 'key')),
                'label' => trim((string) Arr::get($result, 'label')),
                'permission' => trim((string) Arr::get($result, 'permission')),
                'endpoint' => trim((string) Arr::get($result, 'endpoint')),
                'status' => trim((string) Arr::get($result, 'status')),
                'http_status' => trim((string) Arr::get($result, 'http_status')),
                'message' => trim((string) Arr::get($result, 'message')),
                'tested_at' => trim((string) Arr::get($result, 'tested_at')),
            ])
            ->filter(fn (array $result): bool => $result['key'] !== '')
            ->values()
            ->all();

        $threadsApiTestResults = collect((array) Arr::get($input, 'threads.api_test.results', []))
            ->map(fn ($result): array => [
                'key' => trim((string) Arr::get($result, 'key')),
                'label' => trim((string) Arr::get($result, 'label')),
                'permission' => trim((string) Arr::get($result, 'permission')),
                'endpoint' => trim((string) Arr::get($result, 'endpoint')),
                'status' => trim((string) Arr::get($result, 'status')),
                'http_status' => trim((string) Arr::get($result, 'http_status')),
                'message' => trim((string) Arr::get($result, 'message')),
                'tested_at' => trim((string) Arr::get($result, 'tested_at')),
            ])
            ->filter(fn (array $result): bool => $result['key'] !== '')
            ->values()
            ->all();

        $whatsappBusinessAccounts = collect((array) Arr::get($input, 'whatsapp.available_business_accounts', []))
            ->map(fn ($account): array => [
                'id' => trim((string) Arr::get($account, 'id')),
                'name' => trim((string) Arr::get($account, 'name')),
                'review_status' => trim((string) Arr::get($account, 'review_status')),
            ])
            ->filter(fn (array $account): bool => $account['id'] !== '')
            ->unique('id')
            ->values()
            ->all();

        $whatsappPhoneNumbers = collect((array) Arr::get($input, 'whatsapp.available_phone_numbers', []))
            ->map(fn ($phone): array => [
                'id' => trim((string) Arr::get($phone, 'id')),
                'display_phone_number' => trim((string) Arr::get($phone, 'display_phone_number')),
                'verified_name' => trim((string) Arr::get($phone, 'verified_name')),
                'business_account_id' => trim((string) Arr::get($phone, 'business_account_id')),
                'business_account_name' => trim((string) Arr::get($phone, 'business_account_name')),
                'quality_rating' => trim((string) Arr::get($phone, 'quality_rating')),
                'code_verification_status' => trim((string) Arr::get($phone, 'code_verification_status')),
                'name_status' => trim((string) Arr::get($phone, 'name_status')),
                'status' => trim((string) Arr::get($phone, 'status')),
                'is_test' => (bool) Arr::get($phone, 'is_test', false),
            ])
            ->filter(fn (array $phone): bool => $phone['id'] !== '')
            ->unique('id')
            ->values()
            ->all();

        $liveTopPosts = collect((array) Arr::get($input, 'creator_challenge.live_sync.top_posts', []))
            ->map(fn ($post): array => [
                'id' => trim((string) Arr::get($post, 'id')),
                'message' => trim((string) Arr::get($post, 'message')),
                'created_time' => trim((string) Arr::get($post, 'created_time')),
                'permalink_url' => trim((string) Arr::get($post, 'permalink_url')),
                'comments' => trim((string) Arr::get($post, 'comments', '0')) ?: '0',
                'public_shares' => trim((string) Arr::get($post, 'public_shares', '0')) ?: '0',
                'reactions' => trim((string) Arr::get($post, 'reactions', '0')) ?: '0',
                'comment_likes' => trim((string) Arr::get($post, 'comment_likes', '0')) ?: '0',
                'replied_threads' => trim((string) Arr::get($post, 'replied_threads', '0')) ?: '0',
            ])
            ->filter(fn (array $post): bool => $post['id'] !== '')
            ->values()
            ->all();

        return [
            'meta' => [
                'enabled' => (bool) Arr::get($input, 'meta.enabled', $defaults['meta']['enabled']),
                'app_id' => trim((string) Arr::get($input, 'meta.app_id', $defaults['meta']['app_id'])),
                'app_secret' => trim((string) Arr::get($input, 'meta.app_secret', $defaults['meta']['app_secret'])),
                'business_login_config_id' => trim((string) Arr::get($input, 'meta.business_login_config_id', $defaults['meta']['business_login_config_id'])),
                'business_id' => trim((string) Arr::get($input, 'meta.business_id', $defaults['meta']['business_id'])),
                'system_user_id' => trim((string) Arr::get($input, 'meta.system_user_id', $defaults['meta']['system_user_id'])),
                'user_access_token' => trim((string) Arr::get($input, 'meta.user_access_token', $defaults['meta']['user_access_token'])),
                'page_id' => trim((string) Arr::get($input, 'meta.page_id', $defaults['meta']['page_id'])),
                'page_name' => trim((string) Arr::get($input, 'meta.page_name', $defaults['meta']['page_name'])),
                'page_access_token' => trim((string) Arr::get($input, 'meta.page_access_token', $defaults['meta']['page_access_token'])),
                'instagram_business_id' => trim((string) Arr::get($input, 'meta.instagram_business_id', $defaults['meta']['instagram_business_id'])),
                'default_ad_account_id' => trim((string) Arr::get($input, 'meta.default_ad_account_id', $defaults['meta']['default_ad_account_id'])),
                'available_pages' => $pages,
                'available_ad_accounts' => $adAccounts,
                'connection_status' => trim((string) Arr::get($input, 'meta.connection_status', $defaults['meta']['connection_status'])) ?: 'not_connected',
                'last_connected_at' => trim((string) Arr::get($input, 'meta.last_connected_at', $defaults['meta']['last_connected_at'])),
                'api_test' => [
                    'status' => trim((string) Arr::get($input, 'meta.api_test.status', $defaults['meta']['api_test']['status'])) ?: 'not_run',
                    'last_tested_at' => trim((string) Arr::get($input, 'meta.api_test.last_tested_at', $defaults['meta']['api_test']['last_tested_at'])),
                    'last_error' => trim((string) Arr::get($input, 'meta.api_test.last_error', $defaults['meta']['api_test']['last_error'])),
                    'results' => $apiTestResults,
                ],
            ],
            'publishing' => [
                'enabled' => (bool) Arr::get($input, 'publishing.enabled', $defaults['publishing']['enabled']),
                'facebook_page_posts' => (bool) Arr::get($input, 'publishing.facebook_page_posts', $defaults['publishing']['facebook_page_posts']),
                'instagram_posts' => (bool) Arr::get($input, 'publishing.instagram_posts', $defaults['publishing']['instagram_posts']),
                'approval_required' => (bool) Arr::get($input, 'publishing.approval_required', $defaults['publishing']['approval_required']),
                'auto_publish_releases' => (bool) Arr::get($input, 'publishing.auto_publish_releases', $defaults['publishing']['auto_publish_releases']),
                'auto_publish_features' => (bool) Arr::get($input, 'publishing.auto_publish_features', $defaults['publishing']['auto_publish_features']),
                'auto_publish_reviews' => (bool) Arr::get($input, 'publishing.auto_publish_reviews', $defaults['publishing']['auto_publish_reviews']),
                'default_cta' => trim((string) Arr::get($input, 'publishing.default_cta', $defaults['publishing']['default_cta'])) ?: 'learn_more',
                'cadence' => trim((string) Arr::get($input, 'publishing.cadence', $defaults['publishing']['cadence'])) ?: 'manual',
            ],
            'ads' => [
                'enabled' => (bool) Arr::get($input, 'ads.enabled', $defaults['ads']['enabled']),
                'lead_ads_enabled' => (bool) Arr::get($input, 'ads.lead_ads_enabled', $defaults['ads']['lead_ads_enabled']),
                'default_objective' => trim((string) Arr::get($input, 'ads.default_objective', $defaults['ads']['default_objective'])) ?: 'OUTCOME_LEADS',
                'monthly_cap' => trim((string) Arr::get($input, 'ads.monthly_cap', $defaults['ads']['monthly_cap'])) ?: '0',
                'daily_cap' => trim((string) Arr::get($input, 'ads.daily_cap', $defaults['ads']['daily_cap'])) ?: '0',
                'default_campaign_name' => trim((string) Arr::get($input, 'ads.default_campaign_name', $defaults['ads']['default_campaign_name'])) ?: 'CreditSoft - {date} - Leads',
                'default_destination' => trim((string) Arr::get($input, 'ads.default_destination', $defaults['ads']['default_destination'])) ?: 'website',
                'default_form_name' => trim((string) Arr::get($input, 'ads.default_form_name', $defaults['ads']['default_form_name'])),
            ],
            'whatsapp' => [
                'enabled' => (bool) Arr::get($input, 'whatsapp.enabled', $defaults['whatsapp']['enabled']),
                'lead_handoff_enabled' => (bool) Arr::get($input, 'whatsapp.lead_handoff_enabled', $defaults['whatsapp']['lead_handoff_enabled']),
                'appointment_reminders_enabled' => (bool) Arr::get($input, 'whatsapp.appointment_reminders_enabled', $defaults['whatsapp']['appointment_reminders_enabled']),
                'number_strategy' => $this->normalizeWhatsAppNumberStrategy(trim((string) Arr::get($input, 'whatsapp.number_strategy', $defaults['whatsapp']['number_strategy']))),
                'display_number' => trim((string) Arr::get($input, 'whatsapp.display_number', $defaults['whatsapp']['display_number'])),
                'phone_number_id' => trim((string) Arr::get($input, 'whatsapp.phone_number_id', $defaults['whatsapp']['phone_number_id'])),
                'business_account_id' => trim((string) Arr::get($input, 'whatsapp.business_account_id', $defaults['whatsapp']['business_account_id'])),
                'default_template_name' => trim((string) Arr::get($input, 'whatsapp.default_template_name', $defaults['whatsapp']['default_template_name'])),
                'verify_token' => trim((string) Arr::get($input, 'whatsapp.verify_token', $defaults['whatsapp']['verify_token'])),
                'fallback_agent_number' => trim((string) Arr::get($input, 'whatsapp.fallback_agent_number', $defaults['whatsapp']['fallback_agent_number'])),
                'connection_status' => trim((string) Arr::get($input, 'whatsapp.connection_status', $defaults['whatsapp']['connection_status'])) ?: 'not_configured',
                'available_business_accounts' => $whatsappBusinessAccounts,
                'available_phone_numbers' => $whatsappPhoneNumbers,
                'last_synced_at' => trim((string) Arr::get($input, 'whatsapp.last_synced_at', $defaults['whatsapp']['last_synced_at'])),
                'last_error' => trim((string) Arr::get($input, 'whatsapp.last_error', $defaults['whatsapp']['last_error'])),
            ],
            'threads' => [
                'enabled' => (bool) Arr::get($input, 'threads.enabled', $defaults['threads']['enabled']),
                'app_id' => trim((string) Arr::get($input, 'threads.app_id', $defaults['threads']['app_id'])),
                'app_secret' => trim((string) Arr::get($input, 'threads.app_secret', $defaults['threads']['app_secret'])),
                'user_id' => trim((string) Arr::get($input, 'threads.user_id', $defaults['threads']['user_id'])),
                'username' => trim((string) Arr::get($input, 'threads.username', Arr::get($input, 'website_signals.threads_username', $defaults['threads']['username']))),
                'access_token' => trim((string) Arr::get($input, 'threads.access_token', $defaults['threads']['access_token'])),
                'last_container_id' => trim((string) Arr::get($input, 'threads.last_container_id', $defaults['threads']['last_container_id'])),
                'verification_blocker' => $this->sanitizeThreadsBlocker(Arr::get($input, 'threads.verification_blocker', $defaults['threads']['verification_blocker'])),
                'manual_workflow_enabled' => (bool) Arr::get($input, 'threads.manual_workflow_enabled', $defaults['threads']['manual_workflow_enabled']),
                'manual_draft' => trim((string) Arr::get($input, 'threads.manual_draft', $defaults['threads']['manual_draft'])),
                'manual_media_url' => trim((string) Arr::get($input, 'threads.manual_media_url', $defaults['threads']['manual_media_url'])),
                'manual_published_url' => trim((string) Arr::get($input, 'threads.manual_published_url', $defaults['threads']['manual_published_url'])),
                'manual_notes' => trim((string) Arr::get($input, 'threads.manual_notes', $defaults['threads']['manual_notes'])),
                'connection_status' => trim((string) Arr::get($input, 'threads.connection_status', $defaults['threads']['connection_status'])) ?: 'not_connected',
                'last_error' => trim((string) Arr::get($input, 'threads.last_error', $defaults['threads']['last_error'])),
                'api_test' => [
                    'status' => trim((string) Arr::get($input, 'threads.api_test.status', $defaults['threads']['api_test']['status'])) ?: 'not_run',
                    'last_tested_at' => trim((string) Arr::get($input, 'threads.api_test.last_tested_at', $defaults['threads']['api_test']['last_tested_at'])),
                    'last_error' => trim((string) Arr::get($input, 'threads.api_test.last_error', $defaults['threads']['api_test']['last_error'])),
                    'results' => $threadsApiTestResults,
                ],
            ],
            'customer_uploads' => [
                'dropbox_request_enabled' => (bool) Arr::get($input, 'customer_uploads.dropbox_request_enabled', $defaults['customer_uploads']['dropbox_request_enabled']),
                'dropbox_request_link' => trim((string) Arr::get($input, 'customer_uploads.dropbox_request_link', $defaults['customer_uploads']['dropbox_request_link'])),
                'google_drive_upload_enabled' => (bool) Arr::get($input, 'customer_uploads.google_drive_upload_enabled', $defaults['customer_uploads']['google_drive_upload_enabled']),
                'google_drive_folder_link' => trim((string) Arr::get($input, 'customer_uploads.google_drive_folder_link', $defaults['customer_uploads']['google_drive_folder_link'])),
                'intake_copy' => trim((string) Arr::get($input, 'customer_uploads.intake_copy', $defaults['customer_uploads']['intake_copy'])),
            ],
            'website_signals' => [
                'source_path' => trim((string) Arr::get($input, 'website_signals.source_path', $defaults['website_signals']['source_path'])),
                'imported_at' => trim((string) Arr::get($input, 'website_signals.imported_at', $defaults['website_signals']['imported_at'])),
                'meta_pixel_id' => trim((string) Arr::get($input, 'website_signals.meta_pixel_id', $defaults['website_signals']['meta_pixel_id'])),
                'meta_app_id' => trim((string) Arr::get($input, 'website_signals.meta_app_id', $defaults['website_signals']['meta_app_id'])),
                'meta_business_id' => trim((string) Arr::get($input, 'website_signals.meta_business_id', $defaults['website_signals']['meta_business_id'])),
                'meta_management_token' => trim((string) Arr::get($input, 'website_signals.meta_management_token', $defaults['website_signals']['meta_management_token'])),
                'meta_webhook_verify_token' => trim((string) Arr::get($input, 'website_signals.meta_webhook_verify_token', $defaults['website_signals']['meta_webhook_verify_token'])),
                'facebook_page_id' => trim((string) Arr::get($input, 'website_signals.facebook_page_id', $defaults['website_signals']['facebook_page_id'])),
                'instagram_business_id' => trim((string) Arr::get($input, 'website_signals.instagram_business_id', $defaults['website_signals']['instagram_business_id'])),
                'instagram_username' => trim((string) Arr::get($input, 'website_signals.instagram_username', $defaults['website_signals']['instagram_username'])),
                'threads_username' => trim((string) Arr::get($input, 'website_signals.threads_username', $defaults['website_signals']['threads_username'])),
                'x_username' => trim((string) Arr::get($input, 'website_signals.x_username', $defaults['website_signals']['x_username'])),
                'meta_ad_account_id' => $this->normalizeAdAccountId(trim((string) Arr::get($input, 'website_signals.meta_ad_account_id', $defaults['website_signals']['meta_ad_account_id']))),
                'lead_form_name' => trim((string) Arr::get($input, 'website_signals.lead_form_name', $defaults['website_signals']['lead_form_name'])),
                'campaign_objective' => trim((string) Arr::get($input, 'website_signals.campaign_objective', $defaults['website_signals']['campaign_objective'])) ?: 'OUTCOME_LEADS',
                'meta_capi_token' => trim((string) Arr::get($input, 'website_signals.meta_capi_token', $defaults['website_signals']['meta_capi_token'])),
                'meta_capi_test_event_code' => trim((string) Arr::get($input, 'website_signals.meta_capi_test_event_code', $defaults['website_signals']['meta_capi_test_event_code'])),
                'weekly_budget' => trim((string) Arr::get($input, 'website_signals.weekly_budget', $defaults['website_signals']['weekly_budget'])),
                'daily_cap' => trim((string) Arr::get($input, 'website_signals.daily_cap', $defaults['website_signals']['daily_cap'])),
                'monthly_cap' => trim((string) Arr::get($input, 'website_signals.monthly_cap', $defaults['website_signals']['monthly_cap'])),
                'whatsapp_enabled' => (bool) Arr::get($input, 'website_signals.whatsapp_enabled', $defaults['website_signals']['whatsapp_enabled']),
                'whatsapp_display_number' => trim((string) Arr::get($input, 'website_signals.whatsapp_display_number', $defaults['website_signals']['whatsapp_display_number'])),
                'whatsapp_phone_number_id' => trim((string) Arr::get($input, 'website_signals.whatsapp_phone_number_id', $defaults['website_signals']['whatsapp_phone_number_id'])),
                'whatsapp_business_account_id' => trim((string) Arr::get($input, 'website_signals.whatsapp_business_account_id', $defaults['website_signals']['whatsapp_business_account_id'])),
                'whatsapp_verify_token' => trim((string) Arr::get($input, 'website_signals.whatsapp_verify_token', $defaults['website_signals']['whatsapp_verify_token'])),
                'whatsapp_default_message' => trim((string) Arr::get($input, 'website_signals.whatsapp_default_message', $defaults['website_signals']['whatsapp_default_message'])),
            ],
            'creator_challenge' => [
                'enabled' => (bool) Arr::get($input, 'creator_challenge.enabled', $defaults['creator_challenge']['enabled']),
                'track_weekly_challenge' => (bool) Arr::get($input, 'creator_challenge.track_weekly_challenge', $defaults['creator_challenge']['track_weekly_challenge']),
                'require_goal_completion' => (bool) Arr::get($input, 'creator_challenge.require_goal_completion', $defaults['creator_challenge']['require_goal_completion']),
                'ai_guidance_enabled' => (bool) Arr::get($input, 'creator_challenge.ai_guidance_enabled', $defaults['creator_challenge']['ai_guidance_enabled']),
                'challenge_name' => trim((string) Arr::get($input, 'creator_challenge.challenge_name', $defaults['creator_challenge']['challenge_name'])) ?: $defaults['creator_challenge']['challenge_name'],
                'challenge_window' => trim((string) Arr::get($input, 'creator_challenge.challenge_window', $defaults['creator_challenge']['challenge_window'])) ?: 'weekly',
                'leaderboard_depth' => trim((string) Arr::get($input, 'creator_challenge.leaderboard_depth', $defaults['creator_challenge']['leaderboard_depth'])) ?: '10',
                'first_place_label' => trim((string) Arr::get($input, 'creator_challenge.first_place_label', $defaults['creator_challenge']['first_place_label'])) ?: $defaults['creator_challenge']['first_place_label'],
                'second_place_label' => trim((string) Arr::get($input, 'creator_challenge.second_place_label', $defaults['creator_challenge']['second_place_label'])) ?: $defaults['creator_challenge']['second_place_label'],
                'third_place_label' => trim((string) Arr::get($input, 'creator_challenge.third_place_label', $defaults['creator_challenge']['third_place_label'])) ?: $defaults['creator_challenge']['third_place_label'],
                'placement_tier_label' => trim((string) Arr::get($input, 'creator_challenge.placement_tier_label', $defaults['creator_challenge']['placement_tier_label'])) ?: $defaults['creator_challenge']['placement_tier_label'],
                'comment_points' => trim((string) Arr::get($input, 'creator_challenge.comment_points', $defaults['creator_challenge']['comment_points'])) ?: '6',
                'public_share_points' => trim((string) Arr::get($input, 'creator_challenge.public_share_points', $defaults['creator_challenge']['public_share_points'])) ?: '8',
                'published_post_points' => trim((string) Arr::get($input, 'creator_challenge.published_post_points', $defaults['creator_challenge']['published_post_points'])) ?: '3',
                'comment_like_bonus_points' => trim((string) Arr::get($input, 'creator_challenge.comment_like_bonus_points', $defaults['creator_challenge']['comment_like_bonus_points'])) ?: '1',
                'comment_like_bonus_step' => trim((string) Arr::get($input, 'creator_challenge.comment_like_bonus_step', $defaults['creator_challenge']['comment_like_bonus_step'])) ?: '5',
                'goal_posts' => trim((string) Arr::get($input, 'creator_challenge.goal_posts', $defaults['creator_challenge']['goal_posts'])) ?: '4',
                'goal_comments' => trim((string) Arr::get($input, 'creator_challenge.goal_comments', $defaults['creator_challenge']['goal_comments'])) ?: '20',
                'goal_public_shares' => trim((string) Arr::get($input, 'creator_challenge.goal_public_shares', $defaults['creator_challenge']['goal_public_shares'])) ?: '10',
                'goal_reply_windows' => trim((string) Arr::get($input, 'creator_challenge.goal_reply_windows', $defaults['creator_challenge']['goal_reply_windows'])) ?: '12',
                'goal_leads' => trim((string) Arr::get($input, 'creator_challenge.goal_leads', $defaults['creator_challenge']['goal_leads'])) ?: '3',
                'tie_breaker' => trim((string) Arr::get($input, 'creator_challenge.tie_breaker', $defaults['creator_challenge']['tie_breaker'])) ?: 'shares_then_comments',
                'live_sync' => [
                    'status' => trim((string) Arr::get($input, 'creator_challenge.live_sync.status', $defaults['creator_challenge']['live_sync']['status'])) ?: 'not_synced',
                    'last_synced_at' => trim((string) Arr::get($input, 'creator_challenge.live_sync.last_synced_at', $defaults['creator_challenge']['live_sync']['last_synced_at'])),
                    'last_error' => trim((string) Arr::get($input, 'creator_challenge.live_sync.last_error', $defaults['creator_challenge']['live_sync']['last_error'])),
                    'window_days' => trim((string) Arr::get($input, 'creator_challenge.live_sync.window_days', $defaults['creator_challenge']['live_sync']['window_days'])) ?: '7',
                    'page' => [
                        'id' => trim((string) Arr::get($input, 'creator_challenge.live_sync.page.id', $defaults['creator_challenge']['live_sync']['page']['id'])),
                        'name' => trim((string) Arr::get($input, 'creator_challenge.live_sync.page.name', $defaults['creator_challenge']['live_sync']['page']['name'])),
                        'fan_count' => trim((string) Arr::get($input, 'creator_challenge.live_sync.page.fan_count', $defaults['creator_challenge']['live_sync']['page']['fan_count'])),
                        'followers_count' => trim((string) Arr::get($input, 'creator_challenge.live_sync.page.followers_count', $defaults['creator_challenge']['live_sync']['page']['followers_count'])),
                    ],
                    'totals' => [
                        'posts' => trim((string) Arr::get($input, 'creator_challenge.live_sync.totals.posts', $defaults['creator_challenge']['live_sync']['totals']['posts'])) ?: '0',
                        'comments' => trim((string) Arr::get($input, 'creator_challenge.live_sync.totals.comments', $defaults['creator_challenge']['live_sync']['totals']['comments'])) ?: '0',
                        'public_shares' => trim((string) Arr::get($input, 'creator_challenge.live_sync.totals.public_shares', $defaults['creator_challenge']['live_sync']['totals']['public_shares'])) ?: '0',
                        'reply_windows' => trim((string) Arr::get($input, 'creator_challenge.live_sync.totals.reply_windows', $defaults['creator_challenge']['live_sync']['totals']['reply_windows'])) ?: '0',
                        'leads' => trim((string) Arr::get($input, 'creator_challenge.live_sync.totals.leads', $defaults['creator_challenge']['live_sync']['totals']['leads'])) ?: '0',
                        'comment_likes' => trim((string) Arr::get($input, 'creator_challenge.live_sync.totals.comment_likes', $defaults['creator_challenge']['live_sync']['totals']['comment_likes'])) ?: '0',
                        'reactions' => trim((string) Arr::get($input, 'creator_challenge.live_sync.totals.reactions', $defaults['creator_challenge']['live_sync']['totals']['reactions'])) ?: '0',
                    ],
                    'top_posts' => $liveTopPosts,
                ],
            ],
        ];
    }

    protected function loadLegacyPayload(): ?array
    {
        $path = storage_path('app/private/office-social-settings.json');

        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function websiteTrackingPath(): string
    {
        return base_path('web-meta/site-tracking.json');
    }

    protected function loadWebsiteTracking(): array
    {
        $configPath = base_path('web/site-tracking-config.php');

        if (! is_file($configPath)) {
            return [];
        }

        require_once $configPath;

        if (! function_exists('creditsoft_site_tracking_load')) {
            return [];
        }

        $tracking = creditsoft_site_tracking_load();

        return is_array($tracking) ? $tracking : [];
    }

    protected function trackingValue(array $tracking, string $key): string
    {
        return trim((string) ($tracking[$key] ?? ''));
    }

    protected function applyImportedValue(array &$settings, string $path, ?string $value): int
    {
        $clean = trim((string) $value);

        if ($clean === '') {
            return 0;
        }

        Arr::set($settings, $path, $clean);

        return 1;
    }

    /**
     * @return array{id: string, name: string, access_token: string}
     */
    protected function selectedMetaPageForSettings(array $settings): array
    {
        $currentPageId = trim((string) Arr::get($settings, 'meta.page_id'));
        $currentPageName = trim((string) Arr::get($settings, 'meta.page_name'));
        $currentPageToken = trim((string) Arr::get($settings, 'meta.page_access_token'));
        $pages = collect((array) Arr::get($settings, 'meta.available_pages', []))
            ->filter(fn ($page): bool => is_array($page))
            ->map(fn (array $page): array => [
                'id' => trim((string) ($page['id'] ?? '')),
                'name' => trim((string) ($page['name'] ?? '')),
                'access_token' => trim((string) ($page['access_token'] ?? '')),
            ])
            ->filter(fn (array $page): bool => $page['id'] !== '')
            ->values();

        $matchedPage = $currentPageId !== ''
            ? $pages->firstWhere('id', $currentPageId)
            : null;

        if (is_array($matchedPage)) {
            return [
                'id' => $matchedPage['id'],
                'name' => $matchedPage['name'] ?: $currentPageName,
                'access_token' => $matchedPage['access_token'] ?: $currentPageToken,
            ];
        }

        $firstTokenPage = $pages->first(fn (array $page): bool => $page['access_token'] !== '');

        if (is_array($firstTokenPage)) {
            return $firstTokenPage;
        }

        $firstPage = $pages->first();

        if (is_array($firstPage)) {
            return $firstPage;
        }

        return [
            'id' => $currentPageId,
            'name' => $currentPageName,
            'access_token' => $currentPageToken,
        ];
    }

    /**
     * @return array{success: bool, message: string, settings: array<string, mixed>}
     */
    protected function syncWhatsAppAssetsForSettings(array $settings, string $token): array
    {
        $permissions = $this->metaPermissionSnapshot($token);
        $granted = (array) ($permissions['granted'] ?? []);

        if (! in_array('whatsapp_business_management', $granted, true)) {
            $settings['whatsapp']['connection_status'] = 'permissions_missing';
            $settings['whatsapp']['last_synced_at'] = now()->toIso8601String();
            $settings['whatsapp']['last_error'] = 'Meta did not grant whatsapp_business_management yet. Add WhatsApp to the Business Login configuration, reconnect, and sync again.';

            return [
                'success' => false,
                'message' => $settings['whatsapp']['last_error'],
                'settings' => $settings,
            ];
        }

        $businessAccounts = [];
        $phoneNumbers = [];
        $businessIds = $this->metaBusinessIdsForToken($settings, $token);
        $firstError = '';

        foreach ($businessIds as $business) {
            $ownedResponse = Http::acceptJson()->get('https://graph.facebook.com/v25.0/'.$business['id'].'/owned_whatsapp_business_accounts', [
                'fields' => 'id,name,account_review_status',
                'access_token' => $token,
            ]);

            if (! $ownedResponse->ok()) {
                $firstError = $firstError ?: $this->graphErrorMessage($ownedResponse, 'Meta could not list WhatsApp Business Accounts yet.');
                $ownedResponse = Http::acceptJson()->get('https://graph.facebook.com/v25.0/'.$business['id'].'/owned_whatsapp_business_accounts', [
                    'fields' => 'id,name',
                    'access_token' => $token,
                ]);
            }

            if (! $ownedResponse->ok()) {
                $firstError = $firstError ?: $this->graphErrorMessage($ownedResponse, 'Meta could not list WhatsApp Business Accounts yet.');
                continue;
            }

            foreach ((array) data_get($ownedResponse->json(), 'data', []) as $account) {
                if (! is_array($account)) {
                    continue;
                }

                $accountId = trim((string) ($account['id'] ?? ''));

                if ($accountId === '') {
                    continue;
                }

                $businessAccounts[$accountId] = [
                    'id' => $accountId,
                    'name' => trim((string) ($account['name'] ?? 'WhatsApp Business Account')),
                    'review_status' => trim((string) ($account['account_review_status'] ?? '')),
                ];
            }
        }

        $configuredWabaId = trim((string) Arr::get($settings, 'whatsapp.business_account_id'));

        if ($configuredWabaId !== '' && ! array_key_exists($configuredWabaId, $businessAccounts)) {
            $businessAccounts[$configuredWabaId] = [
                'id' => $configuredWabaId,
                'name' => 'Configured WhatsApp Business Account',
                'review_status' => '',
            ];
        }

        foreach ($businessAccounts as $account) {
            $accountPhones = $this->whatsappPhoneNumbersForAccount($account, $token);

            if ($accountPhones === []) {
                continue;
            }

            foreach ($accountPhones as $phone) {
                $phoneNumbers[$phone['id']] = $phone;
            }
        }

        $businessAccountList = array_values($businessAccounts);
        $phoneNumberList = array_values($phoneNumbers);
        $selectedPhone = $this->choosePrimaryWhatsAppPhone($phoneNumberList, trim((string) Arr::get($settings, 'whatsapp.phone_number_id')));

        $settings['whatsapp']['available_business_accounts'] = $businessAccountList;
        $settings['whatsapp']['available_phone_numbers'] = $phoneNumberList;
        $settings['whatsapp']['last_synced_at'] = now()->toIso8601String();

        if ($selectedPhone !== null) {
            $settings['whatsapp']['business_account_id'] = trim((string) Arr::get($settings, 'whatsapp.business_account_id')) ?: $selectedPhone['business_account_id'];
            $settings['whatsapp']['phone_number_id'] = trim((string) Arr::get($settings, 'whatsapp.phone_number_id')) ?: $selectedPhone['id'];
            $settings['whatsapp']['display_number'] = trim((string) Arr::get($settings, 'whatsapp.display_number')) ?: $selectedPhone['display_phone_number'];
            $settings['whatsapp']['last_error'] = '';
            $settings['whatsapp']['connection_status'] = $selectedPhone['is_test']
                ? 'test_detected'
                : 'production_detected';

            return [
                'success' => true,
                'message' => $selectedPhone['is_test']
                    ? 'Meta returned a WhatsApp test number. Use it for smoke tests only, then connect a real or coexistence number before enabling production WhatsApp.'
                    : 'WhatsApp Business assets synced from Meta.',
                'settings' => $settings,
            ];
        }

        if ($businessAccountList !== []) {
            $settings['whatsapp']['connection_status'] = 'account_detected';
            $settings['whatsapp']['last_error'] = 'Meta returned a WhatsApp Business Account, but no phone numbers were available yet.';

            return [
                'success' => false,
                'message' => $settings['whatsapp']['last_error'],
                'settings' => $settings,
            ];
        }

        $settings['whatsapp']['connection_status'] = 'not_found';
        $settings['whatsapp']['last_error'] = $firstError ?: 'Meta did not return a WhatsApp Business Account for this business login.';

        return [
            'success' => false,
            'message' => $settings['whatsapp']['last_error'],
            'settings' => $settings,
        ];
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    protected function metaBusinessIdsForToken(array $settings, string $token): array
    {
        $businesses = [];
        $configuredBusinessId = trim((string) Arr::get($settings, 'meta.business_id'));

        if ($configuredBusinessId !== '') {
            $businesses[$configuredBusinessId] = [
                'id' => $configuredBusinessId,
                'name' => 'Configured Meta business',
            ];
        }

        $response = Http::acceptJson()->get('https://graph.facebook.com/v25.0/me/businesses', [
            'fields' => 'id,name',
            'access_token' => $token,
        ]);

        if ($response->ok()) {
            foreach ((array) data_get($response->json(), 'data', []) as $business) {
                if (! is_array($business)) {
                    continue;
                }

                $id = trim((string) ($business['id'] ?? ''));

                if ($id === '') {
                    continue;
                }

                $businesses[$id] = [
                    'id' => $id,
                    'name' => trim((string) ($business['name'] ?? 'Meta business')),
                ];
            }
        }

        return array_values($businesses);
    }

    /**
     * @param  array{id: string, name: string, review_status?: string}  $account
     * @return array<int, array{id: string, display_phone_number: string, verified_name: string, business_account_id: string, business_account_name: string, quality_rating: string, code_verification_status: string, name_status: string, status: string, is_test: bool}>
     */
    protected function whatsappPhoneNumbersForAccount(array $account, string $token): array
    {
        $response = Http::acceptJson()->get('https://graph.facebook.com/v25.0/'.$account['id'].'/phone_numbers', [
            'fields' => 'id,display_phone_number,verified_name,quality_rating,code_verification_status,name_status,status',
            'access_token' => $token,
        ]);

        if (! $response->ok()) {
            $response = Http::acceptJson()->get('https://graph.facebook.com/v25.0/'.$account['id'].'/phone_numbers', [
                'fields' => 'id,display_phone_number,verified_name',
                'access_token' => $token,
            ]);
        }

        if (! $response->ok()) {
            return [];
        }

        return collect((array) data_get($response->json(), 'data', []))
            ->filter(fn ($phone): bool => is_array($phone))
            ->map(function (array $phone) use ($account): array {
                $normalized = [
                    'id' => trim((string) ($phone['id'] ?? '')),
                    'display_phone_number' => trim((string) ($phone['display_phone_number'] ?? '')),
                    'verified_name' => trim((string) ($phone['verified_name'] ?? '')),
                    'business_account_id' => $account['id'],
                    'business_account_name' => $account['name'],
                    'quality_rating' => trim((string) ($phone['quality_rating'] ?? '')),
                    'code_verification_status' => trim((string) ($phone['code_verification_status'] ?? '')),
                    'name_status' => trim((string) ($phone['name_status'] ?? '')),
                    'status' => trim((string) ($phone['status'] ?? '')),
                    'is_test' => false,
                ];

                $normalized['is_test'] = $this->isTestWhatsAppPhone($normalized);

                return $normalized;
            })
            ->filter(fn (array $phone): bool => $phone['id'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{id: string, display_phone_number: string, verified_name: string, business_account_id: string, business_account_name: string, quality_rating: string, code_verification_status: string, name_status: string, status: string, is_test: bool}>  $phones
     * @return array{id: string, display_phone_number: string, verified_name: string, business_account_id: string, business_account_name: string, quality_rating: string, code_verification_status: string, name_status: string, status: string, is_test: bool}|null
     */
    protected function choosePrimaryWhatsAppPhone(array $phones, string $currentPhoneId): ?array
    {
        if ($phones === []) {
            return null;
        }

        if ($currentPhoneId !== '') {
            $current = collect($phones)->firstWhere('id', $currentPhoneId);

            if (is_array($current)) {
                return $current;
            }
        }

        $production = collect($phones)->first(fn (array $phone): bool => ! $phone['is_test']);

        return is_array($production) ? $production : $phones[0];
    }

    /**
     * @param  array{id: string, display_phone_number: string, verified_name: string, business_account_id: string, business_account_name: string, quality_rating: string, code_verification_status: string, name_status: string, status: string, is_test: bool}  $phone
     */
    protected function isTestWhatsAppPhone(array $phone): bool
    {
        $haystack = strtolower(implode(' ', [
            $phone['display_phone_number'],
            $phone['verified_name'],
            $phone['business_account_name'],
            $phone['status'],
        ]));

        return str_contains($haystack, 'test')
            || str_contains($haystack, '+1 555')
            || str_contains($haystack, '1555')
            || str_contains($haystack, '555-');
    }

    protected function normalizeWhatsAppNumberStrategy(string $value): string
    {
        return in_array($value, ['new_number', 'business_app_coexistence', 'migrate_existing_app'], true)
            ? $value
            : 'new_number';
    }

    protected function sanitizeThreadsBlocker(mixed $value): string
    {
        $value = trim((string) $value);

        return in_array($value, ['none', 'meta_verification', 'instagram_login', 'app_review'], true)
            ? $value
            : 'meta_verification';
    }

    /**
     * @return array{success: bool, message: string, settings: array<string, mixed>, live_sync: array<string, mixed>}
     */
    protected function syncCreatorChallengeFailure(array $settings, string $message): array
    {
        $settings['creator_challenge']['live_sync']['status'] = 'error';
        $settings['creator_challenge']['live_sync']['last_synced_at'] = now()->toIso8601String();
        $settings['creator_challenge']['live_sync']['last_error'] = $message;
        $saved = $this->save($settings);

        return [
            'success' => false,
            'message' => $message,
            'settings' => $saved,
            'live_sync' => (array) Arr::get($saved, 'creator_challenge.live_sync', []),
        ];
    }

    protected function graphErrorMessage(\Illuminate\Http\Client\Response $response, string $fallback): string
    {
        return trim((string) data_get($response->json(), 'error.message', '')) ?: $fallback;
    }

    /**
     * @param  array<string, string|int>  $params
     * @return array{key: string, label: string, permission: string, endpoint: string, status: string, http_status: string, message: string, tested_at: string}
     */
    protected function runMetaGraphReadTest(
        string $key,
        string $label,
        string $permission,
        string $endpoint,
        array $params,
        string $token,
    ): array {
        $testedAt = now()->toIso8601String();

        try {
            $response = Http::acceptJson()
                ->timeout(20)
                ->get('https://graph.facebook.com/v25.0/'.ltrim($endpoint, '/'), [
                    ...$params,
                    'access_token' => $token,
                ]);
        } catch (\Throwable $exception) {
            return [
                'key' => $key,
                'label' => $label,
                'permission' => $permission,
                'endpoint' => $endpoint,
                'status' => 'failed',
                'http_status' => '',
                'message' => $exception->getMessage(),
                'tested_at' => $testedAt,
            ];
        }

        return [
            'key' => $key,
            'label' => $label,
            'permission' => $permission,
            'endpoint' => $endpoint,
            'status' => $response->ok() ? 'passed' : 'failed',
            'http_status' => (string) $response->status(),
            'message' => $response->ok()
                ? $this->graphTestSuccessMessage($response)
                : $this->graphErrorMessage($response, 'Meta rejected this API test.'),
            'tested_at' => $testedAt,
        ];
    }

    protected function graphTestSuccessMessage(\Illuminate\Http\Client\Response $response): string
    {
        $json = $response->json();
        $data = data_get($json, 'data');

        if (is_array($data)) {
            return 'Meta returned '.$this->pluralizeCount(count($data), 'record').'.';
        }

        $id = trim((string) data_get($json, 'id', ''));

        if ($id !== '') {
            return "Meta returned object {$id}.";
        }

        return 'Meta returned a successful response.';
    }

    /**
     * @return array{key: string, label: string, permission: string, endpoint: string, status: string, http_status: string, message: string, tested_at: string}
     */
    protected function metaApiFailedResult(
        string $key,
        string $label,
        string $permission,
        string $endpoint,
        string $message,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'permission' => $permission,
            'endpoint' => $endpoint,
            'status' => 'failed',
            'http_status' => '',
            'message' => $message,
            'tested_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{key: string, label: string, permission: string, endpoint: string, status: string, http_status: string, message: string, tested_at: string}
     */
    protected function metaApiManualResult(
        string $key,
        string $label,
        string $permission,
        string $endpoint,
        string $message,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'permission' => $permission,
            'endpoint' => $endpoint,
            'status' => 'manual_required',
            'http_status' => '',
            'message' => $message,
            'tested_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{key: string, label: string, permission: string, endpoint: string, status: string, http_status: string, message: string, tested_at: string}
     */
    protected function metaApiSkippedResult(
        string $key,
        string $label,
        string $permission,
        string $endpoint,
        string $message,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'permission' => $permission,
            'endpoint' => $endpoint,
            'status' => 'skipped',
            'http_status' => '',
            'message' => $message,
            'tested_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, array{key: string, label: string, permission: string, endpoint: string, status: string, http_status: string, message: string, tested_at: string}>  $results
     * @return array{success: bool, message: string, settings: array<string, mixed>}
     */
    protected function finishMetaApiTestSuite(array $settings, array $results): array
    {
        $failed = collect($results)->filter(fn (array $result): bool => $result['status'] === 'failed');
        $manualOrSkipped = collect($results)->filter(
            fn (array $result): bool => in_array($result['status'], ['manual_required', 'skipped'], true),
        );
        $status = $failed->isNotEmpty()
            ? 'failed'
            : ($manualOrSkipped->isNotEmpty() ? 'partial' : 'passed');
        $firstFailed = $failed->first();
        $lastError = is_array($firstFailed) ? (string) ($firstFailed['message'] ?? '') : '';

        $settings['meta']['api_test'] = [
            'status' => $status,
            'last_tested_at' => now()->toIso8601String(),
            'last_error' => $lastError,
            'results' => $results,
        ];

        $saved = $this->save($settings);

        return [
            'success' => $failed->isEmpty(),
            'message' => $failed->isEmpty()
                ? 'Meta API test ran. Read-only Graph calls were sent to Meta; write and send permissions remain manual smoke tests.'
                : 'Meta API test ran with '.$this->pluralizeCount($failed->count(), 'failed call').'. Open readiness for details.',
            'settings' => $saved,
        ];
    }

    /**
     * @param  array<string, string|int>  $params
     * @return array{result: array<string, string>, json: array<string, mixed>}
     */
    protected function runThreadsGraphApiTest(
        string $key,
        string $label,
        string $permission,
        string $method,
        string $endpoint,
        array $params,
        string $token,
    ): array {
        $testedAt = now()->toIso8601String();
        $url = 'https://graph.threads.net/v1.0/'.ltrim($endpoint, '/');

        try {
            $client = Http::acceptJson()->timeout(20);
            $payload = [
                ...$params,
                'access_token' => $token,
            ];

            $response = match (strtolower($method)) {
                'post' => $client->asForm()->post($url, $payload),
                'delete' => $client->delete($url, $payload),
                default => $client->get($url, $payload),
            };
        } catch (\Throwable $exception) {
            return [
                'result' => [
                    'key' => $key,
                    'label' => $label,
                    'permission' => $permission,
                    'endpoint' => strtoupper($method).' '.$endpoint,
                    'status' => 'failed',
                    'http_status' => '',
                    'message' => $exception->getMessage(),
                    'tested_at' => $testedAt,
                ],
                'json' => [],
            ];
        }

        return [
            'result' => [
                'key' => $key,
                'label' => $label,
                'permission' => $permission,
                'endpoint' => strtoupper($method).' '.$endpoint,
                'status' => $response->ok() ? 'passed' : 'failed',
                'http_status' => (string) $response->status(),
                'message' => $response->ok()
                    ? $this->graphTestSuccessMessage($response)
                    : $this->threadsGraphErrorMessage($response),
                'tested_at' => $testedAt,
            ],
            'json' => (array) $response->json(),
        ];
    }

    protected function threadsGraphErrorMessage(\Illuminate\Http\Client\Response $response): string
    {
        $message = trim((string) data_get($response->json(), 'error.message', ''));
        $code = trim((string) data_get($response->json(), 'error.code', ''));

        if ($code === '190' && str_contains(strtolower($message), 'cannot parse access token')) {
            return 'Meta rejected the same graph.threads.net token check shown in Graph API Explorer: OAuthException 190, cannot parse access token. In Graph API Explorer, click Submit on GET /me?fields=id,name first. If Explorer fails too, regenerate with Generate Threads Access Token and copy it with the copy icon. If Explorer passes, paste that copied token here and run again.';
        }

        return $message !== '' ? $message : 'Threads rejected this API test.';
    }

    /**
     * @param  array<int, array{key: string, label: string, permission: string, endpoint: string, status: string, http_status: string, message: string, tested_at: string}>  $results
     * @return array{success: bool, message: string, settings: array<string, mixed>}
     */
    protected function finishThreadsApiTestSuite(array $settings, array $results): array
    {
        $failed = collect($results)->filter(fn (array $result): bool => $result['status'] === 'failed');
        $manualOrSkipped = collect($results)->filter(
            fn (array $result): bool => in_array($result['status'], ['manual_required', 'skipped'], true),
        );
        $status = $failed->isNotEmpty()
            ? 'failed'
            : ($manualOrSkipped->isNotEmpty() ? 'partial' : 'passed');
        $firstFailed = $failed->first();
        $lastError = is_array($firstFailed) ? (string) ($firstFailed['message'] ?? '') : '';

        $settings['threads']['api_test'] = [
            'status' => $status,
            'last_tested_at' => now()->toIso8601String(),
            'last_error' => $lastError,
            'results' => $results,
        ];
        $settings['threads']['connection_status'] = $failed->isEmpty() ? 'tested' : 'test_failed';
        $settings['threads']['last_error'] = $lastError;

        $saved = $this->save($settings);

        return [
            'success' => $failed->isEmpty(),
            'message' => $failed->isEmpty()
                ? 'Threads API test ran against graph.threads.net. Visible or destructive actions remain manual smoke tests.'
                : 'Threads API test ran with '.$this->pluralizeCount($failed->count(), 'failed call').'. Open Threads settings for details.',
            'settings' => $saved,
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array{id: string, name: string, access_token: string}>
     */
    protected function extractMetaPages(array $items): array
    {
        return collect($items)
            ->filter(fn ($page): bool => is_array($page))
            ->map(fn (array $page): array => [
                'id' => trim((string) ($page['id'] ?? '')),
                'name' => trim((string) ($page['name'] ?? '')),
                'access_token' => trim((string) ($page['access_token'] ?? '')),
            ])
            ->filter(fn (array $page): bool => $page['id'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{granted: array<int, string>, declined: array<int, string>}
     */
    protected function metaPermissionSnapshot(string $activeToken): array
    {
        $response = Http::acceptJson()->get('https://graph.facebook.com/v25.0/me/permissions', [
            'access_token' => $activeToken,
        ]);

        if (! $response->ok()) {
            return [
                'granted' => [],
                'declined' => [],
            ];
        }

        $permissions = collect((array) data_get($response->json(), 'data', []))
            ->filter(fn ($permission): bool => is_array($permission))
            ->map(fn (array $permission): array => [
                'permission' => trim((string) ($permission['permission'] ?? '')),
                'status' => trim((string) ($permission['status'] ?? '')),
            ])
            ->filter(fn (array $permission): bool => $permission['permission'] !== '')
            ->values();

        return [
            'granted' => $permissions
                ->filter(fn (array $permission): bool => $permission['status'] === 'granted')
                ->pluck('permission')
                ->values()
                ->all(),
            'declined' => $permissions
                ->reject(fn (array $permission): bool => $permission['status'] === 'granted')
                ->pluck('permission')
                ->values()
                ->all(),
        ];
    }

    protected function pluralizeCount(int $count, string $singular, ?string $plural = null): string
    {
        return $count.' '.($count === 1 ? $singular : ($plural ?: $singular.'s'));
    }

    protected function boundedMetricInt(mixed $value, int $fallback, int $min, int $max): int
    {
        $parsed = (int) filter_var($value, FILTER_VALIDATE_INT);

        if ($parsed <= 0) {
            $parsed = $fallback;
        }

        return max($min, min($max, $parsed));
    }

    /**
     * @param  array<int, array{id: string, name: string, access_token: string}>  $pages
     * @return array<int, array{id: string, name: string, access_token: string}>
     */
    protected function mergeImportedPage(array $pages, string $pageId, string $pageName): array
    {
        return collect($pages)
            ->reject(fn (array $page): bool => trim((string) ($page['id'] ?? '')) === $pageId)
            ->prepend([
                'id' => $pageId,
                'name' => $pageName,
                'access_token' => '',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{id: string, name: string, account_status: string}>  $adAccounts
     * @return array<int, array{id: string, name: string, account_status: string}>
     */
    protected function mergeImportedAdAccount(array $adAccounts, string $accountId): array
    {
        return collect($adAccounts)
            ->reject(fn (array $account): bool => trim((string) ($account['id'] ?? '')) === $accountId)
            ->prepend([
                'id' => $accountId,
                'name' => 'Imported website ad account',
                'account_status' => 'imported',
            ])
            ->values()
            ->all();
    }

    protected function normalizeAdAccountId(string $value): string
    {
        $clean = trim($value);

        if ($clean === '') {
            return '';
        }

        if (str_starts_with(strtolower($clean), 'act_')) {
            return 'act_'.preg_replace('/[^0-9]/', '', substr($clean, 4));
        }

        return preg_replace('/[^0-9]/', '', $clean) ?: '';
    }
}
