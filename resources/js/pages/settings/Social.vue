<script setup lang="ts">
import {
    faArrowUpRightFromSquare,
    faBullseye,
    faCalendarDays,
    faCircleCheck,
    faCloudArrowUp,
    faCopy,
    faLink,
    faLinkSlash,
    faListOl,
    faMessage,
    faRectangleAd,
    faRotate,
    faShareNodes,
    faTriangleExclamation,
    faTrophy,
    faWandMagicSparkles,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import SocialPlatformMark from '@/components/creditsoft/SocialPlatformMark.vue';
import SocialSettingsMenu from '@/components/creditsoft/SocialSettingsMenu.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Social / Meta',
                href: '/settings/social',
            },
        ],
    },
});

type MetaPage = {
    id: string;
    name: string;
    access_token: string;
};

type MetaAdAccount = {
    id: string;
    name: string;
    account_status: string;
};

type MetaApiTestResult = {
    key: string;
    label: string;
    permission: string;
    endpoint: string;
    status: 'passed' | 'failed' | 'manual_required' | 'skipped' | string;
    http_status: string;
    message: string;
    tested_at: string;
};

type MetaApiTest = {
    status: 'not_run' | 'passed' | 'partial' | 'failed' | string;
    last_tested_at: string;
    last_error: string;
    results: MetaApiTestResult[];
};

type WhatsAppBusinessAccount = {
    id: string;
    name: string;
    review_status: string;
};

type WhatsAppPhoneNumber = {
    id: string;
    display_phone_number: string;
    verified_name: string;
    business_account_id: string;
    business_account_name: string;
    quality_rating: string;
    code_verification_status: string;
    name_status: string;
    status: string;
    is_test: boolean;
};

type WhatsAppSettings = {
    enabled: boolean;
    lead_handoff_enabled: boolean;
    appointment_reminders_enabled: boolean;
    number_strategy: 'new_number' | 'business_app_coexistence' | 'migrate_existing_app';
    display_number: string;
    phone_number_id: string;
    business_account_id: string;
    default_template_name: string;
    verify_token: string;
    fallback_agent_number: string;
    connection_status: string;
    available_business_accounts: WhatsAppBusinessAccount[];
    available_phone_numbers: WhatsAppPhoneNumber[];
    last_synced_at: string;
    last_error: string;
};

type ThreadsSettings = {
    enabled: boolean;
    app_id: string;
    app_secret: string;
    user_id: string;
    username: string;
    access_token: string;
    last_container_id: string;
    verification_blocker: 'none' | 'meta_verification' | 'instagram_login' | 'app_review' | string;
    manual_workflow_enabled: boolean;
    manual_draft: string;
    manual_media_url: string;
    manual_published_url: string;
    manual_notes: string;
    connection_status: string;
    last_error: string;
    api_test: MetaApiTest;
};

type CreatorChallengeSettings = {
    enabled: boolean;
    track_weekly_challenge: boolean;
    require_goal_completion: boolean;
    ai_guidance_enabled: boolean;
    challenge_name: string;
    challenge_window: string;
    leaderboard_depth: string;
    first_place_label: string;
    second_place_label: string;
    third_place_label: string;
    placement_tier_label: string;
    comment_points: string;
    public_share_points: string;
    published_post_points: string;
    comment_like_bonus_points: string;
    comment_like_bonus_step: string;
    goal_posts: string;
    goal_comments: string;
    goal_public_shares: string;
    goal_reply_windows: string;
    goal_leads: string;
    tie_breaker: string;
    live_sync: {
        status: string;
        last_synced_at: string;
        last_error: string;
        window_days: string;
        page: {
            id: string;
            name: string;
            fan_count: string;
            followers_count: string;
        };
        totals: {
            posts: string;
            comments: string;
            public_shares: string;
            reply_windows: string;
            leads: string;
            comment_likes: string;
            reactions: string;
        };
        top_posts: Array<{
            id: string;
            message: string;
            created_time: string;
            permalink_url: string;
            comments: string;
            public_shares: string;
            reactions: string;
            comment_likes: string;
            replied_threads: string;
        }>;
    };
};

type WebsiteSignals = {
    source_path: string;
    imported_at: string;
    meta_pixel_id: string;
    meta_app_id: string;
    meta_business_id: string;
    meta_management_token: string;
    meta_webhook_verify_token: string;
    facebook_page_id: string;
    instagram_business_id: string;
    instagram_username: string;
    threads_username: string;
    x_username: string;
    meta_ad_account_id: string;
    lead_form_name: string;
    campaign_objective: string;
    meta_capi_token: string;
    meta_capi_test_event_code: string;
    weekly_budget: string;
    daily_cap: string;
    monthly_cap: string;
    whatsapp_enabled: boolean;
    whatsapp_display_number: string;
    whatsapp_phone_number_id: string;
    whatsapp_business_account_id: string;
    whatsapp_verify_token: string;
    whatsapp_default_message: string;
};

type MetaSetupLink = {
    label: string;
    href: string;
    description: string;
    primary?: boolean;
};

type MetaReadinessTone = 'ready' | 'staged' | 'blocked' | 'manual';

type MetaReadinessItem = {
    key: string;
    label: string;
    status: string;
    detail: string;
    tone: MetaReadinessTone;
    href?: string;
    action?: string;
};

type MetaNextMove = {
    key: string;
    label: string;
    detail: string;
    href: string;
    tone: MetaReadinessTone;
};

type ThreadsForwardMove = {
    key: string;
    label: string;
    detail: string;
    tone: MetaReadinessTone;
};

type SocialSettingsSection =
    | 'overview'
    | 'readiness'
    | 'facebook'
    | 'instagram'
    | 'threads'
    | 'creator-challenge'
    | 'whatsapp'
    | 'publishing'
    | 'ads';

const props = defineProps<{
    section: SocialSettingsSection;
    settings: {
        meta: {
            enabled: boolean;
            app_id: string;
            app_secret: string;
            business_login_config_id: string;
            business_id: string;
            system_user_id: string;
            user_access_token: string;
            page_id: string;
            page_name: string;
            page_access_token: string;
            instagram_business_id: string;
            default_ad_account_id: string;
            available_pages: MetaPage[];
            available_ad_accounts: MetaAdAccount[];
            connection_status: string;
            last_connected_at: string;
            api_test: MetaApiTest;
        };
        publishing: {
            enabled: boolean;
            facebook_page_posts: boolean;
            instagram_posts: boolean;
            approval_required: boolean;
            auto_publish_releases: boolean;
            auto_publish_features: boolean;
            auto_publish_reviews: boolean;
            default_cta: string;
            cadence: string;
        };
        ads: {
            enabled: boolean;
            lead_ads_enabled: boolean;
            default_objective: string;
            monthly_cap: string;
            daily_cap: string;
            default_campaign_name: string;
            default_destination: string;
            default_form_name: string;
        };
        whatsapp: WhatsAppSettings;
        threads: ThreadsSettings;
        customer_uploads: {
            dropbox_request_enabled: boolean;
            dropbox_request_link: string;
            google_drive_upload_enabled: boolean;
            google_drive_folder_link: string;
            intake_copy: string;
        };
        website_signals: WebsiteSignals;
        creator_challenge: CreatorChallengeSettings;
    };
    meta: {
        connect_ready: boolean;
        connect_url: string | null;
        callback_url: string;
        api_callback_url: string;
        deauthorize_url: string;
        data_deletion_url: string;
        allowed_domains: string[];
        callback_mode: 'local' | 'public' | 'api_domain';
        configured_callback_status: {
            state: 'none' | 'verified' | 'unreachable' | 'unchecked';
            normalized_base_url?: string | null;
            callback_url?: string | null;
            http_status?: number | null;
            message: string;
        };
        callback_notice?: {
            type: 'success' | 'error';
            message: string;
        } | null;
        scopes: string[];
    };
    threads_auth: {
        connect_ready: boolean;
        connect_url: string | null;
        callback_url: string;
        scopes: string[];
    };
}>();

const socialSectionUrl = (section: SocialSettingsSection) =>
    section === 'overview' ? '/settings/social' : `/settings/social/${section}`;

const activeSection = computed<SocialSettingsSection>(
    () => props.section ?? 'overview',
);

const isSocialSection = (section: SocialSettingsSection) =>
    activeSection.value === section;

const socialSectionLabel = computed(() => {
    if (activeSection.value === 'readiness') {
        return 'Meta readiness';
    }

    if (activeSection.value === 'facebook') {
        return 'Facebook settings';
    }

    if (activeSection.value === 'instagram') {
        return 'Instagram settings';
    }

    if (activeSection.value === 'threads') {
        return 'Threads settings';
    }

    if (activeSection.value === 'creator-challenge') {
        return 'Creator Challenge';
    }

    if (activeSection.value === 'whatsapp') {
        return 'WhatsApp settings';
    }

    if (activeSection.value === 'publishing') {
        return 'Publishing settings';
    }

    if (activeSection.value === 'ads') {
        return 'Ads & Leads';
    }

    return 'Social / Meta overview';
});

const socialSectionDescription = computed(() => {
    if (activeSection.value === 'readiness') {
        return 'A command board for the callback, app config, Page token, creator challenge, Instagram, Threads, ads, and WhatsApp blockers.';
    }

    if (activeSection.value === 'facebook') {
        return 'App ID, Business Login, public callbacks, Page selection, and Meta developer setup.';
    }

    if (activeSection.value === 'instagram') {
        return 'Professional account linking, media-hosting requirements, draft mode, and the publishing roadblock list.';
    }

    if (activeSection.value === 'threads') {
        return 'Threads profile, manual posting flow, and the separate Threads API lane to wire after token approval.';
    }

    if (activeSection.value === 'creator-challenge') {
        return 'Weekly challenge scoring, live Meta sync, winner placement, and AI next moves.';
    }

    if (activeSection.value === 'whatsapp') {
        return 'WhatsApp Business assets, production-number readiness, webhooks, and lead handoff.';
    }

    if (activeSection.value === 'publishing') {
        return 'Page posting rules, cadence, approval controls, Instagram options, and default CTA.';
    }

    if (activeSection.value === 'ads') {
        return 'Ad account selection, lead campaign defaults, budgets, and destination rules.';
    }

    return 'Pick one lane below so this settings area stays lighter and faster to work through.';
});

const socialSectionNav: Array<{
    key: SocialSettingsSection;
    label: string;
    description: string;
}> = [
    {
        key: 'overview',
        label: 'Overview',
        description: 'Status snapshot',
    },
    {
        key: 'readiness',
        label: 'Readiness',
        description: 'What is blocked',
    },
    {
        key: 'facebook',
        label: 'Facebook',
        description: 'Business login',
    },
    {
        key: 'instagram',
        label: 'Instagram',
        description: 'Professional account',
    },
    {
        key: 'threads',
        label: 'Threads',
        description: 'Profile and API lane',
    },
    {
        key: 'creator-challenge',
        label: 'Creator Challenge',
        description: 'Weekly scoring',
    },
    {
        key: 'whatsapp',
        label: 'WhatsApp',
        description: 'Message lane',
    },
    {
        key: 'publishing',
        label: 'Publishing',
        description: 'Posting rules',
    },
    {
        key: 'ads',
        label: 'Ads & Leads',
        description: 'Paid traffic',
    },
];

const clone = <T,>(value: T): T => JSON.parse(JSON.stringify(value));

const form = useForm({
    meta: clone(props.settings.meta),
    publishing: clone(props.settings.publishing),
    ads: clone(props.settings.ads),
    whatsapp: clone(props.settings.whatsapp),
    threads: clone(props.settings.threads),
    customer_uploads: clone(props.settings.customer_uploads),
    website_signals: clone(props.settings.website_signals),
    creator_challenge: clone(props.settings.creator_challenge),
});

const importForm = useForm({});
const challengeSyncForm = useForm({});
const whatsappSyncForm = useForm({});
const apiTestForm = useForm({});
const threadsApiTestForm = useForm({});
const copiedSetupValue = ref('');
const autoSaveState = ref<'idle' | 'saving' | 'saved'>('idle');
let fieldExitSaveTimer: ReturnType<typeof window.setTimeout> | null = null;

const copiedCallback = computed(() => copiedSetupValue.value === 'callback');
const copiedThreadsAuth = computed(
    () => copiedSetupValue.value === 'threads-auth',
);

const selectedPage = computed(
    () =>
        form.meta.available_pages.find(
            (page) => page.id === form.meta.page_id,
        ) ?? null,
);

const selectedAdAccount = computed(
    () =>
        form.meta.available_ad_accounts.find(
            (account) => account.id === form.meta.default_ad_account_id,
        ) ?? null,
);

const callbackModeLabel = computed(() => {
    if (props.meta.callback_mode === 'api_domain') {
        return 'Stable API callback active';
    }

    return props.meta.callback_mode === 'public'
        ? 'Ngrok callback active'
        : 'Local callback only';
});

const metaHasPageToken = computed(
    () => form.meta.page_access_token.trim() !== '',
);

const metaConnected = computed(
    () =>
        form.meta.connection_status === 'connected' && metaHasPageToken.value,
);

const metaBusinessLoginConfigReady = computed(
    () => form.meta.business_login_config_id.trim() !== '',
);

const metaImported = computed(() => form.meta.connection_status === 'imported');

const metaConnectionIcon = computed(() =>
    metaConnected.value ? faLink : faLinkSlash,
);

const metaStatusIcon = computed(() =>
    metaConnected.value ? faCircleCheck : faLinkSlash,
);

const metaStatusChipClass = computed(() => {
    if (metaConnected.value) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800';
    }

    if (metaImported.value) {
        return 'border-sky-200 bg-sky-50 text-sky-800';
    }

    return 'border-amber-200 bg-amber-50 text-amber-800';
});

const metaStatusLabel = computed(() => {
    if (metaConnected.value) {
        return 'Meta connected';
    }

    if (metaImported.value) {
        return 'Website admin imported';
    }

    return 'Meta not connected';
});

const metaConnectActionLabel = computed(() =>
    metaConnected.value ? 'Reconnect Meta' : 'Connect Meta',
);
const metaConnectRoute = '/settings/social/meta/connect';
const cleanHandle = (value: string) => value.trim().replace(/^@+/, '');

const instagramBusinessId = computed(() =>
    form.meta.instagram_business_id.trim() !== ''
        ? form.meta.instagram_business_id.trim()
        : form.website_signals.instagram_business_id.trim(),
);

const instagramUsername = computed(() =>
    cleanHandle(form.website_signals.instagram_username),
);

const instagramProfileUrl = computed(() =>
    instagramUsername.value !== ''
        ? `https://www.instagram.com/${encodeURIComponent(instagramUsername.value)}/`
        : 'https://www.instagram.com/',
);

const instagramSetupReady = computed(
    () => instagramBusinessId.value !== '' && metaConnected.value,
);

const instagramStatusLabel = computed(() => {
    if (form.publishing.instagram_posts && instagramSetupReady.value) {
        return 'Ready to request IG publishing review';
    }

    if (instagramBusinessId.value !== '') {
        return 'IG account identified';
    }

    if (instagramUsername.value !== '') {
        return 'Profile link only';
    }

    return 'Instagram not linked yet';
});

const instagramStatusChipClass = computed(() => {
    if (form.publishing.instagram_posts && instagramSetupReady.value) {
        return 'border-sky-200 bg-sky-50 text-sky-800';
    }

    if (instagramBusinessId.value !== '') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800';
    }

    return 'border-amber-200 bg-amber-50 text-amber-900';
});

const threadsUsername = computed(() =>
    cleanHandle(
        form.threads.username.trim() !== ''
            ? form.threads.username
            : form.website_signals.threads_username,
    ),
);

const threadsProfileUrl = computed(() =>
    threadsUsername.value !== ''
        ? `https://www.threads.com/@${encodeURIComponent(threadsUsername.value)}`
        : 'https://www.threads.com/',
);

const threadsBlockerLabels: Record<string, string> = {
    none: 'No blocker',
    meta_verification: 'Blocked by Meta verification',
    instagram_login: 'Blocked by Instagram login',
    app_review: 'Waiting on Threads app review',
};

const threadsBlockerLabel = computed(
    () =>
        threadsBlockerLabels[form.threads.verification_blocker] ??
        threadsBlockerLabels.meta_verification,
);

const threadsBlockedByMeta = computed(
    () =>
        form.threads.access_token.trim() === '' &&
        form.threads.verification_blocker !== 'none',
);

const threadsStatusLabel = computed(() =>
    form.threads.access_token.trim() !== ''
        ? 'Threads token saved'
        : threadsBlockedByMeta.value
        ? threadsBlockerLabel.value
        : threadsUsername.value !== ''
        ? 'Manual Threads profile ready'
        : 'Threads profile not saved yet',
);

const threadsStatusChipClass = computed(() =>
    form.threads.access_token.trim() !== ''
        ? 'border-sky-200 bg-sky-50 text-sky-800'
        : threadsBlockedByMeta.value
        ? 'border-amber-200 bg-amber-50 text-amber-900'
        : threadsUsername.value !== ''
        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
        : 'border-amber-200 bg-amber-50 text-amber-900',
);

const threadsManualWorkflowReady = computed(
    () =>
        form.threads.manual_workflow_enabled &&
        (form.threads.manual_draft.trim() !== '' ||
            form.threads.manual_published_url.trim() !== '' ||
            threadsUsername.value !== ''),
);

const threadsForwardMoves = computed<ThreadsForwardMove[]>(() => [
    {
        key: 'facebook',
        label: 'Facebook can keep moving',
        detail: metaConnected.value
            ? 'Use the connected Page lane for posts, challenge sync, and ad account testing while Threads waits.'
            : 'Finish the Page connection first; this is the lane that can still feed creator challenge scoring.',
        tone: metaConnected.value ? 'ready' : 'manual',
    },
    {
        key: 'instagram',
        label: 'Instagram stays staged',
        detail: instagramSetupReady.value
            ? 'Instagram has an asset ID saved, so drafts and publish settings can stay organized.'
            : 'Save the IG Business ID when Meta exposes it; keep captions and media staged here for now.',
        tone: instagramSetupReady.value ? 'staged' : 'manual',
    },
    {
        key: 'whatsapp',
        label: 'WhatsApp waits on verification',
        detail: whatsappReady.value
            ? 'A WhatsApp number is selected; production sending still depends on Meta verification.'
            : 'Keep the follow-up copy and number strategy ready while Meta reviews the business.',
        tone: whatsappReady.value ? 'staged' : 'manual',
    },
    {
        key: 'threads',
        label: 'Threads has a manual fallback',
        detail: threadsManualWorkflowReady.value
            ? 'Draft, media, and publish URL tracking are active even without the Threads token.'
            : 'Save a handle and draft so the team can still publish manually from the approved account.',
        tone: threadsManualWorkflowReady.value ? 'ready' : 'manual',
    },
]);

const scopeLanes = computed(() => [
    {
        key: 'base',
        label: 'Page read',
        description: 'Required first. Lets CreditSoft list Pages, receive the Page token, and read Page engagement.',
        enabled: true,
        scopes: ['pages_show_list', 'pages_read_engagement'],
        href: socialSectionUrl('facebook'),
        tone: 'ready',
    },
    {
        key: 'creator',
        label: 'Creator challenge',
        description: 'Uses the connected Page token to sync posts, comments, shares, and replies.',
        enabled: form.creator_challenge.enabled,
        scopes: ['Uses Page feed after connection'],
        href: socialSectionUrl('creator-challenge'),
        tone: 'standard',
    },
    {
        key: 'publishing',
        label: 'Facebook publishing',
        description: 'Adds post publishing once the Page-read connection works.',
        enabled: form.publishing.enabled && form.publishing.facebook_page_posts,
        scopes: ['pages_manage_posts'],
        href: socialSectionUrl('publishing'),
        tone: 'advanced',
    },
    {
        key: 'instagram',
        label: 'Instagram',
        description: 'Adds Instagram publishing after the Meta Page/asset link is ready.',
        enabled: form.publishing.instagram_posts,
        scopes: ['instagram_basic', 'instagram_content_publish'],
        href: socialSectionUrl('instagram'),
        tone: 'advanced',
    },
    {
        key: 'threads',
        label: 'Threads',
        description: 'Separate Threads API lane. Drafts and profile links can work before API approval.',
        enabled: threadsUsername.value !== '',
        scopes: ['threads_basic', 'threads_content_publish'],
        href: socialSectionUrl('threads'),
        tone: 'advanced',
    },
    {
        key: 'ads',
        label: 'Ads and leads',
        description: 'Adds ad-account and lead-form access only when the ads lane is on.',
        enabled: form.ads.enabled,
        scopes: form.ads.lead_ads_enabled
            ? ['ads_read', 'ads_management', 'business_management', 'leads_retrieval']
            : ['ads_read'],
        href: socialSectionUrl('ads'),
        tone: 'advanced',
    },
    {
        key: 'whatsapp',
        label: 'WhatsApp',
        description: 'Adds WhatsApp Business access after the message lane is configured.',
        enabled: form.whatsapp.enabled,
        scopes: ['whatsapp_business_management', 'whatsapp_business_messaging'],
        href: socialSectionUrl('whatsapp'),
        tone: 'advanced',
    },
]);

const enabledScopeLanes = computed(() =>
    scopeLanes.value.filter((lane) => lane.key !== 'base' && lane.enabled),
);

const scopeModeLabel = computed(() =>
    enabledScopeLanes.value.length === 0
        ? 'Page-read login mode'
        : `${enabledScopeLanes.value.length} extra lane${enabledScopeLanes.value.length === 1 ? '' : 's'} on`,
);

const apiTestResults = computed(() => form.meta.api_test?.results ?? []);

const apiTestCounts = computed(() => ({
    passed: apiTestResults.value.filter((result) => result.status === 'passed')
        .length,
    failed: apiTestResults.value.filter((result) => result.status === 'failed')
        .length,
    manual: apiTestResults.value.filter(
        (result) => result.status === 'manual_required',
    ).length,
    skipped: apiTestResults.value.filter((result) => result.status === 'skipped')
        .length,
}));

const apiTestTone = computed<MetaReadinessTone>(() => {
    if (form.meta.api_test?.status === 'passed') {
        return 'ready';
    }

    if (form.meta.api_test?.status === 'partial') {
        return 'staged';
    }

    return 'blocked';
});

const apiTestStatusLabel = computed(() => {
    if (form.meta.api_test?.status === 'passed') {
        return 'Read tests passed';
    }

    if (form.meta.api_test?.status === 'partial') {
        return 'Read tests ran';
    }

    if (form.meta.api_test?.status === 'failed') {
        return 'API test blocked';
    }

    return 'Not tested yet';
});

const apiTestSummary = computed(() => {
    if (form.meta.api_test?.status === 'failed') {
        return (
            form.meta.api_test.last_error ||
            'At least one Graph call failed. Open the result list below to see which permission or asset is blocking.'
        );
    }

    if (apiTestResults.value.length > 0) {
        return `${apiTestCounts.value.passed} read call${apiTestCounts.value.passed === 1 ? '' : 's'} passed. ${apiTestCounts.value.manual} write/send smoke test${apiTestCounts.value.manual === 1 ? '' : 's'} still need explicit approval.`;
    }

    return 'Run this once after connecting Meta so the Meta Testing page sees real Graph API activity from CreditSoft.';
});

const apiTestResultLabel = (status: string) => {
    if (status === 'passed') {
        return 'Passed';
    }

    if (status === 'failed') {
        return 'Failed';
    }

    if (status === 'manual_required') {
        return 'Manual smoke test';
    }

    if (status === 'skipped') {
        return 'Skipped';
    }

    return status || 'Unknown';
};

const apiTestResultClass = (status: string) => {
    if (status === 'passed') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800';
    }

    if (status === 'failed') {
        return 'border-rose-200 bg-rose-50 text-rose-800';
    }

    if (status === 'manual_required') {
        return 'border-sky-200 bg-sky-50 text-sky-800';
    }

    return 'border-stone-200 bg-stone-50 text-stone-600';
};

const threadsApiTestResults = computed(
    () => form.threads.api_test?.results ?? [],
);

const threadsApiTestCounts = computed(() => ({
    passed: threadsApiTestResults.value.filter(
        (result) => result.status === 'passed',
    ).length,
    failed: threadsApiTestResults.value.filter(
        (result) => result.status === 'failed',
    ).length,
    manual: threadsApiTestResults.value.filter(
        (result) => result.status === 'manual_required',
    ).length,
    skipped: threadsApiTestResults.value.filter(
        (result) => result.status === 'skipped',
    ).length,
}));

const threadsApiTestTone = computed<MetaReadinessTone>(() => {
    if (form.threads.api_test?.status === 'passed') {
        return 'ready';
    }

    if (form.threads.api_test?.status === 'partial') {
        return 'staged';
    }

    return 'blocked';
});

const threadsApiTestStatusLabel = computed(() => {
    if (form.threads.api_test?.status === 'passed') {
        return 'Threads tests passed';
    }

    if (form.threads.api_test?.status === 'partial') {
        return 'Threads tests ran';
    }

    if (form.threads.api_test?.status === 'failed') {
        return 'Threads test blocked';
    }

    return 'Threads not tested';
});

const threadsApiTestSummary = computed(() => {
    if (form.threads.access_token.trim() === '') {
        if (threadsBlockedByMeta.value) {
            return `${threadsBlockerLabel.value}. Keep drafts manual-ready until Meta allows the Threads OAuth token.`;
        }

        return 'Paste a Threads user access token first. Meta is checking graph.threads.net calls, not Facebook Page calls.';
    }

    if (form.threads.api_test?.status === 'failed') {
        return (
            form.threads.api_test.last_error ||
            'At least one Threads Graph call failed. Review the result list before checking Meta again.'
        );
    }

    if (threadsApiTestResults.value.length > 0) {
        return `${threadsApiTestCounts.value.passed} Threads call${threadsApiTestCounts.value.passed === 1 ? '' : 's'} passed. ${threadsApiTestCounts.value.manual} visible or destructive smoke test${threadsApiTestCounts.value.manual === 1 ? '' : 's'} still need explicit approval.`;
    }

    return 'Run this after saving a Threads token so Meta can see the required Threads API calls.';
});

const whatsappReady = computed(
    () =>
        form.whatsapp.enabled &&
        (form.whatsapp.phone_number_id.trim() !== '' ||
            form.whatsapp.display_number.trim() !== ''),
);

const selectedWhatsappPhone = computed(
    () =>
        form.whatsapp.available_phone_numbers.find(
            (phone) => phone.id === form.whatsapp.phone_number_id,
        ) ?? null,
);

const whatsappLooksLikeTestNumber = computed(() => {
    const phone = selectedWhatsappPhone.value;
    const displayNumber = form.whatsapp.display_number.toLowerCase();

    return Boolean(phone?.is_test) ||
        displayNumber.includes('+1 555') ||
        displayNumber.includes('1555') ||
        displayNumber.includes('555-');
});

const whatsappProductionReady = computed(
    () =>
        form.whatsapp.phone_number_id.trim() !== '' &&
        form.whatsapp.business_account_id.trim() !== '' &&
        !whatsappLooksLikeTestNumber.value,
);

const whatsappStatusChipClass = computed(() => {
    if (whatsappProductionReady.value) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800';
    }

    if (
        form.whatsapp.connection_status === 'test_detected' ||
        whatsappLooksLikeTestNumber.value
    ) {
        return 'border-amber-200 bg-amber-50 text-amber-900';
    }

    if (form.whatsapp.connection_status === 'permissions_missing') {
        return 'border-rose-200 bg-rose-50 text-rose-900';
    }

    return 'border-stone-200 bg-stone-50 text-stone-700';
});

const whatsappStatusLabel = computed(() => {
    if (whatsappProductionReady.value) {
        return 'Production number detected';
    }

    if (
        form.whatsapp.connection_status === 'test_detected' ||
        whatsappLooksLikeTestNumber.value
    ) {
        return 'Meta test number detected';
    }

    if (form.whatsapp.connection_status === 'account_detected') {
        return 'WABA found, no phone yet';
    }

    if (form.whatsapp.connection_status === 'permissions_missing') {
        return 'WhatsApp permission missing';
    }

    if (form.whatsapp.connection_status === 'not_found') {
        return 'No WhatsApp asset returned';
    }

    return 'WhatsApp not synced';
});

const whatsappNumberStrategyLabel = computed(() => {
    if (form.whatsapp.number_strategy === 'business_app_coexistence') {
        return 'Keep the phone app if Meta offers coexistence.';
    }

    if (form.whatsapp.number_strategy === 'migrate_existing_app') {
        return 'Move an existing Business app number into Cloud API.';
    }

    return 'Use a clean new production number.';
});

const websiteSignalsReady = computed(() =>
    [
        form.website_signals.meta_pixel_id,
        form.website_signals.facebook_page_id,
        form.website_signals.meta_management_token,
        form.website_signals.meta_capi_token,
        form.website_signals.meta_app_id,
        form.website_signals.meta_business_id,
        form.website_signals.meta_ad_account_id,
    ].some((value) => value.trim() !== ''),
);

const whatsappSummary = computed(() => {
    if (!form.whatsapp.enabled) {
        return 'WhatsApp lane is staged but not enabled yet.';
    }

    if (whatsappLooksLikeTestNumber.value) {
        return 'A Meta test number is detected. Keep it for smoke tests only; production follow-up needs a real or coexistence WhatsApp number.';
    }

    if (form.whatsapp.number_strategy === 'business_app_coexistence') {
        return 'CreditSoft should connect through the Business App coexistence flow so the existing phone app can keep working if Meta allows it.';
    }

    if (form.whatsapp.number_strategy === 'migrate_existing_app') {
        return 'CreditSoft should migrate the existing Business app number only if the office accepts that the phone-app workflow may change or stop.';
    }

    if (whatsappReady.value) {
        return form.whatsapp.display_number.trim() !== ''
            ? `WhatsApp replies route through ${form.whatsapp.display_number}.`
            : 'WhatsApp lane is enabled and ready for message routing.';
    }

    return 'WhatsApp is enabled, but the business number details still need to be filled in.';
});

const metaBusinessSummary = computed(() => {
    if (metaConnected.value) {
        return 'Connected and ready for Page sync.';
    }

    if (metaImported.value) {
        return 'Imported from website admin. Connect Meta when you want live Page and ad-account sync.';
    }

    return 'Waiting for office business login.';
});

const cleanMetaAppId = computed(() =>
    form.meta.app_id.trim() !== ''
        ? form.meta.app_id.trim()
        : form.website_signals.meta_app_id.trim(),
);

const cleanMetaBusinessId = computed(() =>
    form.meta.business_id.trim() !== ''
        ? form.meta.business_id.trim()
        : form.website_signals.meta_business_id.trim(),
);

const metaBusinessToolsUrl = 'https://www.facebook.com/settings/?tab=business_tools';

const metaSetupLinks = computed<MetaSetupLink[]>(() => {
    const appId = cleanMetaAppId.value;

    if (appId === '') {
        return [];
    }

    const encodedAppId = encodeURIComponent(appId);
    const businessQuery =
        cleanMetaBusinessId.value !== ''
            ? `?business_id=${encodeURIComponent(cleanMetaBusinessId.value)}`
            : '';
    const appBase = `https://developers.facebook.com/apps/${encodedAppId}`;

    return [
        {
            label: 'Business Login settings',
            href: `${appBase}/business-login/settings/${businessQuery}`,
            description:
                'Valid OAuth redirect URIs and Redirect URI to check live here.',
            primary: true,
        },
        {
            label: 'Basic app settings',
            href: `${appBase}/settings/basic/${businessQuery}`,
            description:
                'Confirm app domain, contact email, privacy URL, and app identity.',
        },
        {
            label: 'App roles',
            href: `${appBase}/roles/roles/${businessQuery}`,
            description:
                'Add admins, developers, testers, or test users while the app is not live.',
        },
        {
            label: 'App review permissions',
            href: `${appBase}/app-review/permissions/${businessQuery}`,
            description:
                'Request Page, ads, Instagram, and WhatsApp permissions by lane.',
        },
        {
            label: 'Facebook Business Tools',
            href: metaBusinessToolsUrl,
            description:
                'Open while switched to the personal Facebook profile that authorized CreditSoft, then use View and edit to repair the Page grant.',
        },
        {
            label: 'All Meta apps',
            href: 'https://developers.facebook.com/apps/',
            description:
                'Search by App Name or App ID, click the app card, or Create App in the upper-right.',
        },
    ];
});

const metaSetupSourceLabel = computed(() => {
    if (form.meta.app_id.trim() !== '' || form.meta.business_id.trim() !== '') {
        return 'Using the saved office Meta IDs.';
    }

    if (
        form.website_signals.meta_app_id.trim() !== '' ||
        form.website_signals.meta_business_id.trim() !== ''
    ) {
        return 'Using imported website admin Meta IDs.';
    }

    return 'Open Meta Apps, search by App Name or App ID, click the app card, or use Create App in the upper-right corner. Then save the App ID here.';
});

const metaBusinessLoginWizardSteps = [
    {
        step: 'Create configuration',
        value: 'Use Create configuration, not template',
        help: 'This creates the config_id CreditSoft needs before it should send another Meta reconnect.',
    },
    {
        step: 'Login variation',
        value: 'General',
        help: 'This is the clean Facebook Login for Business path for a normal Page admin connection.',
    },
    {
        step: 'Access token',
        value: 'User access token',
        help: 'Use the personal Facebook profile that has full control of the Page. Avoid system-user access until the basic Page login works.',
    },
    {
        step: 'Assets',
        value: 'Select the CreditSoft.app Page',
        help: 'Add Instagram, ad account, and WhatsApp later only if those assets appear and the lane is ready.',
    },
    {
        step: 'Permissions',
        value: 'pages_show_list and pages_read_engagement',
        help: 'Start with the two Page-read permissions. Publishing, ads, Instagram, and WhatsApp can be added after Page sync succeeds.',
    },
    {
        step: 'Finish',
        value: 'Save, copy Configuration ID',
        help: 'Paste that ID into Business Login Config ID in CreditSoft, save social settings, then reconnect Meta.',
    },
];

const callbackUrlVariants = computed(() => {
    const variants = new Set<string>([props.meta.callback_url]);

    try {
        const url = new URL(props.meta.callback_url);

        if (url.hostname.startsWith('www.')) {
            const bare = new URL(url.toString());
            bare.hostname = bare.hostname.replace(/^www\./, '');
            variants.add(bare.toString());
        } else {
            const www = new URL(url.toString());
            www.hostname = `www.${www.hostname}`;
            variants.add(www.toString());
        }
    } catch {
        return Array.from(variants);
    }

    return Array.from(variants).map((value) => value.replace(/\/$/, ''));
});

const metaDashboardFields = computed(() => [
    {
        key: 'oauth_redirects',
        label: 'Valid OAuth Redirect URIs',
        values: callbackUrlVariants.value,
        help: 'Paste these into the saved redirect list, press Enter if Meta creates a chip, then click Save changes.',
    },
    {
        key: 'sdk_domains',
        label: 'Allowed Domains for the JavaScript SDK',
        values: props.meta.allowed_domains,
        help: 'Add these domains so Meta accepts browser-based login and SDK activity from the public bridge host.',
    },
    {
        key: 'deauthorize',
        label: 'Deauthorize callback URL',
        values: [props.meta.deauthorize_url],
        help: 'Paste this into Deauthorize callback URL so Meta can notify CreditSoft when a user removes the app.',
    },
    {
        key: 'data_deletion',
        label: 'Data Deletion Request URL',
        values: [props.meta.data_deletion_url],
        help: 'Paste this into Data Deletion Request URL so Meta can send deletion requests and receive a confirmation code.',
    },
]);

const positiveInt = (value: string | number, fallback: number) => {
    const parsed = Number.parseInt(String(value ?? ''), 10);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
};

const creatorChallengeConfig = computed(() => ({
    commentPoints: positiveInt(form.creator_challenge.comment_points, 6),
    publicSharePoints: positiveInt(
        form.creator_challenge.public_share_points,
        8,
    ),
    publishedPostPoints: positiveInt(
        form.creator_challenge.published_post_points,
        3,
    ),
    commentLikeBonusPoints: positiveInt(
        form.creator_challenge.comment_like_bonus_points,
        1,
    ),
    commentLikeBonusStep: positiveInt(
        form.creator_challenge.comment_like_bonus_step,
        5,
    ),
    leaderboardDepth: Math.max(
        4,
        Math.min(10, positiveInt(form.creator_challenge.leaderboard_depth, 10)),
    ),
    goalPosts: positiveInt(form.creator_challenge.goal_posts, 4),
    goalComments: positiveInt(form.creator_challenge.goal_comments, 20),
    goalPublicShares: positiveInt(
        form.creator_challenge.goal_public_shares,
        10,
    ),
    goalReplyWindows: positiveInt(
        form.creator_challenge.goal_reply_windows,
        12,
    ),
    goalLeads: positiveInt(form.creator_challenge.goal_leads, 3),
}));

const metricInt = (value: string | number | null | undefined) => {
    const parsed = Number.parseInt(String(value ?? ''), 10);

    return Number.isFinite(parsed) && parsed >= 0 ? parsed : 0;
};

const hasLiveCreatorData = computed(() =>
    ['live', 'live_limited'].includes(form.creator_challenge.live_sync.status),
);
const creatorChallengeLimited = computed(
    () => form.creator_challenge.live_sync.status === 'live_limited',
);
const creatorChallengeSyncFailed = computed(
    () => form.creator_challenge.live_sync.status === 'error',
);
const creatorChallengeWindowDays = computed(() =>
    positiveInt(form.creator_challenge.live_sync.window_days, 7),
);

const creatorChallengeTotals = computed(() => ({
    posts: hasLiveCreatorData.value
        ? metricInt(form.creator_challenge.live_sync.totals.posts)
        : 0,
    comments: hasLiveCreatorData.value
        ? metricInt(form.creator_challenge.live_sync.totals.comments)
        : 0,
    publicShares: hasLiveCreatorData.value
        ? metricInt(form.creator_challenge.live_sync.totals.public_shares)
        : 0,
    replyWindows: hasLiveCreatorData.value
        ? metricInt(form.creator_challenge.live_sync.totals.reply_windows)
        : 0,
    leads: hasLiveCreatorData.value
        ? metricInt(form.creator_challenge.live_sync.totals.leads)
        : 0,
}));

const creatorChallengeGoals = computed(() =>
    [
        {
            key: 'posts',
            label: 'Published posts',
            current: creatorChallengeTotals.value.posts,
            target: creatorChallengeConfig.value.goalPosts,
        },
        {
            key: 'comments',
            label: 'Public comments',
            current: creatorChallengeTotals.value.comments,
            target: creatorChallengeConfig.value.goalComments,
        },
        {
            key: 'publicShares',
            label: 'Public shares',
            current: creatorChallengeTotals.value.publicShares,
            target: creatorChallengeConfig.value.goalPublicShares,
        },
        {
            key: 'replyWindows',
            label: hasLiveCreatorData.value
                ? 'Replied threads'
                : 'Reply windows',
            current: creatorChallengeTotals.value.replyWindows,
            target: creatorChallengeConfig.value.goalReplyWindows,
        },
        {
            key: 'leads',
            label: 'Leads routed back',
            current: creatorChallengeTotals.value.leads,
            target: creatorChallengeConfig.value.goalLeads,
        },
    ].map((goal) => ({
        ...goal,
        met: goal.current >= goal.target,
        progress: Math.min(100, Math.round((goal.current / goal.target) * 100)),
    })),
);

const creatorChallengeGoalsMet = computed(() =>
    creatorChallengeGoals.value.every((goal) => goal.met),
);

const creatorChallengeBlockers = computed(() => {
    const blockers: string[] = [];

    if (!form.creator_challenge.enabled) {
        blockers.push(
            'Creator challenge scoring is off. Enable the lane before expecting rankings.',
        );
    }

    if (!metaConnected.value) {
        blockers.push('Meta is not connected with a live business login yet.');
    }

    if (props.meta.callback_mode === 'local') {
        blockers.push(
            'The callback is still local-only. Use the website bridge or ngrok before connecting Meta.',
        );
    }

    if (form.meta.page_id.trim() === '') {
        blockers.push('No Facebook Page is selected.');
    }

    if (
        form.meta.page_access_token.trim() === '' &&
        form.meta.user_access_token.trim() === ''
    ) {
        blockers.push('No Page-readable token is saved.');
    }

    if (creatorChallengeSyncFailed.value) {
        blockers.push(
            form.creator_challenge.live_sync.last_error ||
                'Live sync failed. Reconnect Meta with Page read access and try again.',
        );
    }

    return Array.from(new Set(blockers));
});

const creatorChallengeSummary = computed(() => {
    if (!form.creator_challenge.enabled) {
        return 'Creator challenge scoring is off. Nothing is being ranked.';
    }

    if (creatorChallengeSyncFailed.value) {
        return `Live sync is blocked. ${form.creator_challenge.live_sync.last_error || 'Reconnect Meta with Page read access and try again.'}`;
    }

    if (!hasLiveCreatorData.value) {
        return 'No live Meta challenge data has been detected yet. Sync live Meta data after the Page, callback, and permissions are ready.';
    }

    const metCount = creatorChallengeGoals.value.filter(
        (goal) => goal.met,
    ).length;

    if (creatorChallengeLimited.value) {
        return `Limited live Meta data. Posts and public shares are synced for the last ${creatorChallengeWindowDays.value} days, but comments, reactions, and reply windows are waiting on Meta engagement access.`;
    }

    return creatorChallengeGoalsMet.value
        ? `Live Meta data. The last ${creatorChallengeWindowDays.value} days clear the current creator goals.`
        : `Live Meta data. ${metCount} of ${creatorChallengeGoals.value.length} creator goals are on pace over the last ${creatorChallengeWindowDays.value} days.`;
});

const readinessToneClass = (tone: MetaReadinessTone) => {
    if (tone === 'ready') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-900';
    }

    if (tone === 'staged') {
        return 'border-sky-200 bg-sky-50 text-sky-950';
    }

    if (tone === 'blocked') {
        return 'border-amber-200 bg-amber-50 text-amber-950';
    }

    return 'border-stone-200 bg-stone-50 text-stone-700';
};

const readinessDotClass = (tone: MetaReadinessTone) => {
    if (tone === 'ready') {
        return 'bg-emerald-500';
    }

    if (tone === 'staged') {
        return 'bg-sky-500';
    }

    if (tone === 'blocked') {
        return 'bg-amber-500';
    }

    return 'bg-stone-400';
};

const appCredentialsReady = computed(
    () => form.meta.app_id.trim() !== '' && form.meta.app_secret.trim() !== '',
);

const callbackReadinessTone = computed<MetaReadinessTone>(() => {
    if (props.meta.configured_callback_status.state === 'unreachable') {
        return 'blocked';
    }

    if (props.meta.callback_mode === 'api_domain') {
        return props.meta.configured_callback_status.state === 'verified'
            ? 'ready'
            : 'staged';
    }

    if (props.meta.callback_mode === 'public') {
        return 'staged';
    }

    return 'blocked';
});

const callbackReadinessStatus = computed(() => {
    if (props.meta.configured_callback_status.state === 'unreachable') {
        return 'Bridge unreachable';
    }

    if (props.meta.callback_mode === 'api_domain') {
        return props.meta.configured_callback_status.state === 'verified'
            ? 'Stable bridge verified'
            : 'Stable bridge selected';
    }

    if (props.meta.callback_mode === 'public') {
        return 'Ngrok bridge active';
    }

    return 'Local-only callback';
});

const pageTokenReady = computed(
    () =>
        metaConnected.value &&
        form.meta.page_id.trim() !== '' &&
        form.meta.page_access_token.trim() !== '',
);

const selectedPageLabel = computed(() => {
    if (selectedPage.value) {
        return selectedPage.value.name || selectedPage.value.id;
    }

    if (form.meta.page_name.trim() !== '') {
        return form.meta.page_name.trim();
    }

    return form.meta.page_id.trim();
});

const metaReadinessItems = computed<MetaReadinessItem[]>(() => [
    {
        key: 'app',
        label: 'Meta app credentials',
        status: appCredentialsReady.value ? 'Ready' : 'Missing',
        detail: appCredentialsReady.value
            ? `App ${cleanMetaAppId.value} is saved with an App Secret.`
            : 'Save the App ID and App Secret before trying another connect flow.',
        tone: appCredentialsReady.value ? 'ready' : 'blocked',
        href: '/settings/social/facebook',
        action: appCredentialsReady.value ? 'Review app setup' : 'Add credentials',
    },
    {
        key: 'callback',
        label: 'Public callback',
        status: callbackReadinessStatus.value,
        detail:
            callbackReadinessTone.value === 'blocked'
                ? `${props.meta.configured_callback_status.message} Meta cannot use 127.0.0.1, so fix the website bridge or ngrok before reconnecting.`
                : `Meta should use ${props.meta.callback_url}. Keep this exact URL in the Business Login redirect list.`,
        tone: callbackReadinessTone.value,
        href: '/settings/api',
        action: 'Open API bridge',
    },
    {
        key: 'business-login',
        label: 'Business Login configuration',
        status: metaBusinessLoginConfigReady.value ? 'Config ID saved' : 'Config ID needed',
        detail: metaBusinessLoginConfigReady.value
            ? `Configuration ${form.meta.business_login_config_id.trim()} is saved and will be sent as config_id.`
            : 'Create the Facebook Login for Business configuration, save it, then reconnect Meta.',
        tone: metaBusinessLoginConfigReady.value ? 'ready' : 'blocked',
        href: '/settings/social/facebook',
        action: 'Open Facebook setup',
    },
    {
        key: 'page-token',
        label: 'Facebook Page token',
        status: pageTokenReady.value
            ? 'Page token ready'
            : form.meta.available_pages.length > 0
              ? 'Pages returned, token missing'
              : metaConnected.value
                ? 'Connected, no Page returned'
                : 'Not connected',
        detail: pageTokenReady.value
            ? `${selectedPageLabel.value || 'Selected Page'} can be used for creator challenge sync and future Page publishing.`
            : 'Reconnect with the same Facebook profile that has full control of the Page, then select the Page in the Meta grant screen.',
        tone: pageTokenReady.value ? 'ready' : 'blocked',
        href: '/settings/social/facebook',
        action: pageTokenReady.value ? 'Review Page' : 'Repair Page access',
    },
    {
        key: 'api-test',
        label: 'Meta API test',
        status: apiTestStatusLabel.value,
        detail: apiTestSummary.value,
        tone: apiTestTone.value,
        href: '/settings/social/readiness',
        action:
            apiTestResults.value.length > 0
                ? 'Review test results'
                : 'Run API test',
    },
    {
        key: 'creator-challenge',
        label: 'Creator challenge',
        status: hasLiveCreatorData.value
            ? creatorChallengeLimited.value
                ? 'Live limited'
                : 'Live'
            : pageTokenReady.value
              ? 'Ready to sync'
              : 'Waiting on Page token',
        detail: creatorChallengeSummary.value,
        tone: hasLiveCreatorData.value
            ? 'ready'
            : pageTokenReady.value
              ? 'staged'
              : 'blocked',
        href: '/settings/social/creator-challenge',
        action: hasLiveCreatorData.value ? 'Review winners' : 'Open challenge',
    },
    {
        key: 'instagram',
        label: 'Instagram',
        status: instagramStatusLabel.value,
        detail: instagramBusinessId.value
            ? `Instagram business ID ${instagramBusinessId.value} is saved. Publishing still depends on Meta approval.`
            : instagramUsername.value
              ? `Profile @${instagramUsername.value} is saved for drafts and calendar targeting.`
              : 'Save the public Instagram profile now; connect the business asset when Meta returns it.',
        tone:
            form.publishing.instagram_posts && instagramSetupReady.value
                ? 'staged'
                : instagramBusinessId.value || instagramUsername.value
                  ? 'manual'
                  : 'blocked',
        href: '/settings/social/instagram',
        action: 'Open Instagram',
    },
    {
        key: 'threads',
        label: 'Threads',
        status: threadsApiTestStatusLabel.value,
        detail:
            form.threads.access_token.trim() !== ''
                ? threadsApiTestSummary.value
                : threadsUsername.value
                  ? `Drafts can target @${threadsUsername.value}. Add the Threads token to run the API testing checklist.`
                  : 'Save the Threads handle and token so CreditSoft can prep drafts and test graph.threads.net.',
        tone:
            form.threads.access_token.trim() !== ''
                ? threadsApiTestTone.value
                : threadsUsername.value
                  ? 'manual'
                  : 'blocked',
        href: '/settings/social/threads',
        action: 'Open Threads',
    },
    {
        key: 'ads',
        label: 'Ads and lead forms',
        status: selectedAdAccount.value ? 'Ad account selected' : 'Ad account not selected',
        detail: selectedAdAccount.value
            ? `${selectedAdAccount.value.name || selectedAdAccount.value.id} is selected. Lead ads still depend on the ads permissions and business verification.`
            : 'Save the ad account ID or reconnect Meta after ads access is approved.',
        tone: selectedAdAccount.value ? 'staged' : 'manual',
        href: '/settings/social/ads',
        action: 'Open ads',
    },
    {
        key: 'whatsapp',
        label: 'WhatsApp',
        status: whatsappStatusLabel.value,
        detail: whatsappSummary.value,
        tone: whatsappProductionReady.value
            ? 'ready'
            : whatsappLooksLikeTestNumber.value || form.whatsapp.enabled
              ? 'staged'
              : 'manual',
        href: '/settings/social/whatsapp',
        action: 'Open WhatsApp',
    },
]);

const metaReadinessCounts = computed(() => ({
    ready: metaReadinessItems.value.filter((item) => item.tone === 'ready').length,
    staged: metaReadinessItems.value.filter((item) => item.tone === 'staged').length,
    blocked: metaReadinessItems.value.filter((item) => item.tone === 'blocked').length,
    manual: metaReadinessItems.value.filter((item) => item.tone === 'manual').length,
}));

const readinessBoardSummary = computed(() => {
    if (metaReadinessCounts.value.blocked > 0) {
        return `${metaReadinessCounts.value.blocked} blocker${metaReadinessCounts.value.blocked === 1 ? '' : 's'} still need cleanup before the whole Meta stack can act like production.`;
    }

    if (metaReadinessCounts.value.staged > 0) {
        return `${metaReadinessCounts.value.staged} lane${metaReadinessCounts.value.staged === 1 ? '' : 's'} are staged and waiting on Meta review, verification, or a production asset.`;
    }

    return 'The core Meta setup is clean. Keep content and challenge work moving while advanced permissions finish.';
});

const metaNextMoves = computed<MetaNextMove[]>(() => {
    const moves: MetaNextMove[] = [];

    if (!appCredentialsReady.value) {
        moves.push({
            key: 'credentials',
            label: 'Save the Meta App ID and App Secret',
            detail: 'Without these, CreditSoft cannot build a valid OAuth URL or exchange the callback code.',
            href: '/settings/social/facebook',
            tone: 'blocked',
        });
    }

    if (callbackReadinessTone.value === 'blocked') {
        moves.push({
            key: 'callback',
            label: 'Fix the public callback bridge',
            detail: 'Use the website bridge or ngrok so Meta never receives a 127.0.0.1 callback.',
            href: '/settings/api',
            tone: 'blocked',
        });
    }

    if (!metaBusinessLoginConfigReady.value) {
        moves.push({
            key: 'business-login',
            label: 'Create and save the Business Login Configuration ID',
            detail: 'This keeps Meta on the business-login flow instead of raw scopes that caused the zero-Page loop.',
            href: '/settings/social/facebook',
            tone: 'blocked',
        });
    }

    if (!pageTokenReady.value) {
        moves.push({
            key: 'page-token',
            label: 'Reconnect and confirm the Page asset',
            detail: 'Use the same personal profile with full Page control, then select the CreditSoft Page in the Meta grant screen.',
            href: '/settings/social/facebook',
            tone: 'blocked',
        });
    }

    if (
        pageTokenReady.value &&
        form.meta.api_test?.status !== 'partial' &&
        form.meta.api_test?.status !== 'passed'
    ) {
        moves.push({
            key: 'api-test',
            label: 'Run the Meta API test',
            detail: 'This sends safe read-only Graph calls so Meta can see CreditSoft using the connected app and tokens.',
            href: '/settings/social/readiness',
            tone: form.meta.api_test?.status === 'failed' ? 'blocked' : 'staged',
        });
    }

    if (pageTokenReady.value && !hasLiveCreatorData.value) {
        moves.push({
            key: 'sync-challenge',
            label: 'Sync this week’s creator challenge',
            detail: 'The Page token is ready enough to try live posts, shares, comments, and reply-window scoring.',
            href: '/settings/social/creator-challenge',
            tone: 'staged',
        });
    }

    if (instagramUsername.value === '') {
        moves.push({
            key: 'instagram-handle',
            label: 'Save the Instagram profile handle',
            detail: 'Even before publishing approval, this lets the calendar prep caption drafts and profile-targeted work.',
            href: '/settings/social/instagram',
            tone: 'manual',
        });
    }

    if (threadsUsername.value === '') {
        moves.push({
            key: 'threads-handle',
            label: 'Save the Threads profile handle',
            detail: 'Threads can stay manual-ready while the separate API token lane waits.',
            href: '/settings/social/threads',
            tone: 'manual',
        });
    } else if (
        form.threads.access_token.trim() !== '' &&
        form.threads.api_test?.status !== 'partial' &&
        form.threads.api_test?.status !== 'passed'
    ) {
        moves.push({
            key: 'threads-api-test',
            label: 'Run the Threads API test',
            detail: 'Meta is checking Threads permissions separately, so CreditSoft needs graph.threads.net calls to move that row.',
            href: '/settings/social/threads',
            tone:
                form.threads.api_test?.status === 'failed'
                    ? 'blocked'
                    : 'staged',
        });
    }

    if (form.whatsapp.enabled && !whatsappProductionReady.value) {
        moves.push({
            key: 'whatsapp-production',
            label: 'Keep WhatsApp staged until production assets are approved',
            detail: 'The current lane can smoke test a Meta test number, but real follow-up needs a production or coexistence number.',
            href: '/settings/social/whatsapp',
            tone: 'staged',
        });
    }

    if (moves.length === 0) {
        moves.push({
            key: 'calendar',
            label: 'Use the Social / Meta calendar as the workbench',
            detail: 'The setup board is clean enough to move content planning, challenge scoring, and manual-ready posts forward.',
            href: '/calendar/social',
            tone: 'ready',
        });
    }

    return moves.slice(0, 5);
});

const creatorChallengeLeaderboard = computed(() => {
    const config = creatorChallengeConfig.value;

    if (!hasLiveCreatorData.value) {
        return [];
    }

    return form.creator_challenge.live_sync.top_posts
        .map((post, index) => {
            const comments = metricInt(post.comments);
            const publicShares = metricInt(post.public_shares);
            const commentLikes = metricInt(post.comment_likes);
            const score =
                config.publishedPostPoints +
                comments * config.commentPoints +
                publicShares * config.publicSharePoints +
                Math.floor(commentLikes / config.commentLikeBonusStep) *
                    config.commentLikeBonusPoints;
            const createdLabel = post.created_time
                ? new Date(post.created_time).toLocaleString()
                : 'Recent post';
            const body =
                post.message.trim() !== ''
                    ? post.message.trim()
                    : 'Published post without body copy available.';

            return {
                id: post.id || `live-post-${index + 1}`,
                rank: index + 1,
                name: body.length > 72 ? `${body.slice(0, 72).trim()}…` : body,
                role: createdLabel,
                posts: 1,
                comments,
                publicShares,
                commentLikes,
                replyWindows: metricInt(post.replied_threads),
                leads: 0,
                likeBonusHits: Math.floor(
                    commentLikes / config.commentLikeBonusStep,
                ),
                lastMove:
                    post.permalink_url.trim() !== ''
                        ? `Live post link: ${post.permalink_url}`
                        : body,
                score,
                eligible:
                    !form.creator_challenge.require_goal_completion ||
                    creatorChallengeGoalsMet.value,
                placementLabel:
                    index === 0
                        ? form.creator_challenge.first_place_label
                        : index === 1
                          ? form.creator_challenge.second_place_label
                          : index === 2
                            ? form.creator_challenge.third_place_label
                            : `${form.creator_challenge.placement_tier_label} ${index + 1}`,
            };
        })
        .slice(0, creatorChallengeConfig.value.leaderboardDepth);
});

const creatorChallengeAiMoves = computed(() => {
    if (!form.creator_challenge.enabled) {
        return [
            'Enable the creator challenge lane first, then sync live Meta data so CreditSoft can score real posts, comments, shares, and replied threads.',
        ];
    }

    if (creatorChallengeSyncFailed.value) {
        return [
            `Live sync is blocked: ${form.creator_challenge.live_sync.last_error || 'Meta permissions are not ready yet.'}`,
            'Reconnect Meta with the Page-read login mode first, then sync again so CreditSoft can use a real Page token.',
            'Keep the contest on visible signals only: published posts, public comments, public shares, and replied threads that the Graph-accessible Page feed can actually verify.',
        ];
    }

    if (!hasLiveCreatorData.value) {
        return creatorChallengeBlockers.value.length > 0
            ? creatorChallengeBlockers.value
            : [
                  'No live Page feed has been synced yet. Click Sync live Meta data after the Page token and callback are ready.',
                  'Do not score private shares or any hidden dashboard-only signal. Keep the contest on visible post, comment, share, and reply metrics.',
              ];
    }

    if (creatorChallengeLimited.value) {
        return [
            'CreditSoft can score published posts and public shares right now. Treat comments and reactions as locked until Meta allows the engagement fields for this Page token.',
            'Use this week to validate the challenge flow, winner placement, and recap copy while the richer comment/reaction data waits on Meta access.',
            'Keep the contest language honest: visible shares count now; comments count once Meta unlocks the engagement edge.',
        ];
    }

    const unmetGoals = creatorChallengeGoals.value
        .filter((goal) => !goal.met)
        .sort(
            (left, right) =>
                right.target - right.current - (left.target - left.current),
        );

    if (!form.creator_challenge.ai_guidance_enabled) {
        return [
            'AI guidance is off, so the office is only using the scoring model and manual winner review right now.',
        ];
    }

    if (unmetGoals.length === 0) {
        return [
            'Challenge goals are met. Let AI package the winning posts, comments, and follow-up screenshots into a weekly recap.',
            'Queue a winner-announcement post and push qualified people back into the office booking lane before the next challenge resets.',
            'Use the current leaderboard as the seed list for the next weekly challenge so the office knows who needs another push.',
        ];
    }

    return unmetGoals.slice(0, 3).map((goal) => {
        if (goal.key === 'publicShares') {
            return 'Public shares are behind target. Push a proof-heavy post and prompt the team to reshare the public version instead of relying on private shares you cannot verify.';
        }

        if (goal.key === 'comments') {
            return 'Comments are behind target. Have AI draft two reply openers and one comment-driving post so the page challenge builds visible engagement.';
        }

        if (goal.key === 'replyWindows') {
            return 'Reply windows need help. Move the next calendar block toward comment follow-up so the challenge does not stall after publishing.';
        }

        if (goal.key === 'leads') {
            return 'Lead handoff is light. Tighten the CTA, point more traffic toward the website or WhatsApp, and make sure warmer conversations land back in the office lane.';
        }

        return 'Publishing cadence is behind. Let AI queue one lighter explainer and one proof post so the Page hits the weekly challenge floor faster.';
    });
});

const submit = () => {
    autoSaveState.value = 'idle';
    form.put('/settings/social', {
        preserveScroll: true,
    });
};

const stagePageReadConnection = () => {
    form.creator_challenge.enabled = false;
    form.publishing.enabled = false;
    form.publishing.instagram_posts = false;
    form.ads.enabled = false;
    form.ads.lead_ads_enabled = false;
    form.whatsapp.enabled = false;
    submit();
};

const saveOnFieldExit = () => {
    if (!form.isDirty || form.processing) {
        return;
    }

    if (fieldExitSaveTimer) {
        window.clearTimeout(fieldExitSaveTimer);
    }

    fieldExitSaveTimer = window.setTimeout(() => {
        if (!form.isDirty || form.processing) {
            return;
        }

        autoSaveState.value = 'saving';

        form.put('/settings/social', {
            preserveScroll: true,
            onSuccess: () => {
                autoSaveState.value = 'saved';
                window.setTimeout(() => {
                    if (autoSaveState.value === 'saved') {
                        autoSaveState.value = 'idle';
                    }
                }, 1800);
            },
            onError: () => {
                autoSaveState.value = 'idle';
            },
        });
    }, 450);
};

const importWebsiteTracking = () => {
    importForm.post('/settings/social/import-website-tracking', {
        preserveScroll: true,
    });
};

const syncCreatorChallenge = () => {
    challengeSyncForm.post('/settings/social/creator-challenge/sync', {
        preserveScroll: true,
    });
};

const syncWhatsAppAssets = () => {
    whatsappSyncForm.post('/settings/social/whatsapp/sync', {
        preserveScroll: true,
    });
};

const runMetaApiTest = () => {
    apiTestForm.post('/settings/social/api-test', {
        preserveScroll: true,
    });
};

const runThreadsApiTest = () => {
    threadsApiTestForm.post('/settings/social/threads/api-test', {
        preserveScroll: true,
    });
};

const selectWhatsAppPhone = (phoneId: string) => {
    const phone = form.whatsapp.available_phone_numbers.find(
        (candidate) => candidate.id === phoneId,
    );

    if (!phone) {
        return;
    }

    form.whatsapp.phone_number_id = phone.id;
    form.whatsapp.business_account_id = phone.business_account_id;
    form.whatsapp.display_number = phone.display_phone_number;
};

const selectWhatsAppPhoneFromEvent = (event: Event) => {
    const target = event.target as HTMLSelectElement | null;

    selectWhatsAppPhone(target?.value ?? '');
};

const copySetupValue = async (value: string, key: string) => {
    try {
        await navigator.clipboard.writeText(value);
        copiedSetupValue.value = key;
        window.setTimeout(() => {
            if (copiedSetupValue.value === key) {
                copiedSetupValue.value = '';
            }
        }, 1800);
    } catch {
        copiedSetupValue.value = '';
    }
};

const copyCallbackUrl = () =>
    copySetupValue(props.meta.callback_url, 'callback');

const copyThreadsAuthUrl = () => {
    if (!props.threads_auth.connect_url) {
        return;
    }

    copySetupValue(props.threads_auth.connect_url, 'threads-auth');
};

onMounted(() => {
    const url = new URL(window.location.href);

    if (!url.searchParams.has('meta_oauth') && !url.searchParams.has('meta_message')) {
        return;
    }

    url.searchParams.delete('meta_oauth');
    url.searchParams.delete('meta_message');
    window.history.replaceState(
        window.history.state,
        document.title,
        `${url.pathname}${url.search}${url.hash}`,
    );
});

const maskSecret = (value: string, visible = 4) => {
    const clean = value.trim();

    if (clean === '') {
        return 'Not imported';
    }

    if (clean.length <= visible * 2) {
        return `${clean.slice(0, 2)}…${clean.slice(-2)}`;
    }

    return `${clean.slice(0, visible)}…${clean.slice(-visible)}`;
};
</script>

<template>
    <Head :title="socialSectionLabel" />

    <div class="space-y-5">
        <SocialSettingsMenu />

        <section
            class="rounded-[22px] border border-stone-200 bg-white/95 px-4 py-4 shadow-[0_12px_28px_rgba(15,23,42,0.05)]"
        >
            <div
                class="grid gap-4 xl:grid-cols-[minmax(280px,0.72fr)_minmax(0,1.28fr)] xl:items-end"
            >
                <div>
                    <p
                        class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                    >
                        Social / Meta settings
                    </p>
                    <h1
                        class="mt-1.5 text-xl font-semibold tracking-tight text-stone-950"
                    >
                        {{ socialSectionLabel }}
                    </h1>
                    <p class="mt-1.5 text-sm leading-6 text-stone-600">
                        {{ socialSectionDescription }}
                    </p>
                </div>

                <nav
                    class="flex flex-wrap gap-2 xl:justify-end"
                    aria-label="Social settings sections"
                >
                    <a
                        v-for="item in socialSectionNav"
                        :key="item.key"
                        :href="socialSectionUrl(item.key)"
                        class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-semibold transition hover:border-[#0866ff]/35 hover:bg-[#0866ff]/5"
                        :class="
                            isSocialSection(item.key)
                                ? 'border-[#0866ff]/35 bg-[#0866ff]/10 text-stone-950 shadow-[0_10px_24px_rgba(8,102,255,0.08)]'
                                : 'border-stone-200 bg-stone-50 text-stone-700'
                        "
                    >
                        <span
                            class="size-1.5 rounded-full"
                            :class="
                                isSocialSection(item.key)
                                    ? 'bg-[#0866ff]'
                                    : 'bg-stone-300'
                            "
                        ></span>
                        <span>
                            {{ item.label }}
                        </span>
                        <span class="hidden text-stone-400 xl:inline">
                            /
                        </span>
                        <span class="hidden font-medium text-stone-500 xl:inline">
                            {{ item.description }}
                        </span>
                    </a>
                </nav>
            </div>
        </section>

        <form
            class="space-y-5"
            @focusout="saveOnFieldExit"
            @submit.prevent="submit"
        >
            <div
                class="sticky top-2 z-40 rounded-[18px] border border-stone-200/80 bg-white/95 px-3 py-2 shadow-[0_14px_34px_rgba(15,23,42,0.11)] backdrop-blur"
            >
                <div
                    class="grid gap-2 xl:grid-cols-[minmax(0,1fr)_auto]"
                >
                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                        <span
                            class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold tracking-[0.15em] uppercase"
                            :class="metaStatusChipClass"
                        >
                            {{ metaStatusLabel }}
                        </span>
                        <span
                            class="inline-flex rounded-full border border-stone-300 bg-white px-2.5 py-1 text-[10px] font-semibold tracking-[0.15em] text-stone-700 uppercase"
                        >
                            {{ scopeModeLabel }}
                        </span>
                        <span class="hidden text-sm text-stone-600 sm:inline">
                            Autosaves when you leave a field.
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 xl:justify-end">
                        <button
                            v-if="enabledScopeLanes.length > 0"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-sm font-medium text-sky-950 transition hover:border-sky-400 disabled:cursor-not-allowed disabled:opacity-70"
                            :disabled="form.processing"
                            @click="stagePageReadConnection"
                        >
                            <FontAwesomeIcon :icon="faCircleCheck" />
                            Use Page-read login
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-stone-950 px-3 py-2 text-sm font-medium text-white transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-70"
                            :disabled="form.processing"
                        >
                            <FontAwesomeIcon :icon="faCloudArrowUp" />
                            {{
                                form.processing || autoSaveState === 'saving'
                                    ? 'Saving…'
                                    : autoSaveState === 'saved'
                                      ? 'Saved'
                                      : 'Save now'
                            }}
                        </button>
                        <a
                            v-if="
                                props.meta.connect_ready &&
                                metaBusinessLoginConfigReady
                            "
                            :href="metaConnectRoute"
                            class="inline-flex items-center gap-2 rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-800 transition hover:border-stone-500 hover:text-stone-950"
                        >
                            <FontAwesomeIcon :icon="metaConnectionIcon" />
                            {{ metaConnectActionLabel }}
                        </a>
                        <span
                            v-else-if="
                                props.meta.connect_ready &&
                                !metaBusinessLoginConfigReady
                            "
                            class="inline-flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-900"
                        >
                            <FontAwesomeIcon :icon="faLinkSlash" />
                            Save Business Login Config ID first
                        </span>
                    </div>
                </div>
            </div>

            <div
                v-if="props.meta.callback_notice"
                class="flex items-start gap-3 rounded-[24px] border px-4 py-3 text-sm leading-6"
                :class="
                    props.meta.callback_notice.type === 'success'
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-950'
                        : 'border-rose-200 bg-rose-50 text-rose-950'
                "
            >
                <FontAwesomeIcon
                    :icon="
                        props.meta.callback_notice.type === 'success'
                            ? faCircleCheck
                            : faLinkSlash
                    "
                    class="mt-1"
                />
                <span>{{ props.meta.callback_notice.message }}</span>
            </div>

            <div
                v-if="props.meta.connect_ready && !metaBusinessLoginConfigReady"
                class="flex items-start gap-3 rounded-[24px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950"
            >
                <FontAwesomeIcon :icon="faLinkSlash" class="mt-1" />
                <div>
                    <p class="font-semibold">
                        Business Login Config ID is required before reconnecting Meta.
                    </p>
                    <p>
                        CreditSoft removed the stale app grant, but Meta is still
                        returning zero Pages because the OAuth URL is falling back
                        to raw scopes. Paste the Configuration ID from Meta's
                        Business Login settings, save, then reconnect.
                    </p>
                </div>
            </div>

            <section
                v-if="isSocialSection('overview')"
                class="overflow-hidden rounded-[32px] border border-stone-300/70 bg-[radial-gradient(circle_at_top_left,_rgba(8,102,255,0.17),_transparent_28%),radial-gradient(circle_at_top_right,_rgba(37,211,102,0.16),_transparent_24%),linear-gradient(135deg,_rgba(255,255,255,0.98),_rgba(247,250,252,0.95))]"
            >
                <div
                    class="grid gap-6 px-6 py-6 lg:grid-cols-[1.15fr_0.85fr] lg:px-8 lg:py-8"
                >
                    <div class="space-y-5">
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="flex items-center gap-3">
                                <SocialPlatformMark
                                    brand="meta"
                                    large
                                    monochrome
                                    class="text-[#0866ff] drop-shadow-[0_14px_24px_rgba(8,102,255,0.16)]"
                                />
                                <SocialPlatformMark
                                    brand="whatsapp"
                                    large
                                    monochrome
                                    class="text-[#25d366] drop-shadow-[0_14px_24px_rgba(37,211,102,0.18)]"
                                />
                                <SocialPlatformMark
                                    brand="instagram"
                                    large
                                    monochrome
                                    class="text-[#e4405f] drop-shadow-[0_14px_24px_rgba(228,64,95,0.14)]"
                                />
                                <SocialPlatformMark
                                    brand="threads"
                                    large
                                    monochrome
                                    class="text-stone-950 drop-shadow-[0_14px_24px_rgba(15,23,42,0.12)]"
                                />
                            </div>

                            <div class="space-y-2">
                                <p
                                    class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                                >
                                    Social / Meta control
                                </p>
                                <h1
                                    class="max-w-3xl text-2xl font-semibold tracking-tight text-stone-950 sm:text-[2rem]"
                                >
                                    Meta, Instagram, Threads, and WhatsApp in
                                    one office lane.
                                </h1>
                            </div>
                        </div>

                        <p class="max-w-3xl text-sm leading-7 text-stone-700">
                            Connect the Meta business once, keep Page and
                            Instagram work organized, draft Threads posts while
                            API approval waits, and hand off warmer
                            conversations into WhatsApp when the office wants a
                            faster reply lane.
                        </p>

                        <div class="flex flex-wrap gap-2">
                            <span
                                class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-[11px] font-semibold tracking-[0.18em] uppercase"
                                :class="metaStatusChipClass"
                            >
                                <FontAwesomeIcon :icon="metaStatusIcon" />
                                {{ metaStatusLabel }}
                            </span>
                            <span
                                class="inline-flex rounded-full border border-stone-300 bg-white/85 px-3 py-1.5 text-[11px] font-semibold tracking-[0.18em] text-stone-700 uppercase"
                            >
                                {{ callbackModeLabel }}
                            </span>
                            <span
                                class="inline-flex rounded-full border border-stone-300 bg-white/85 px-3 py-1.5 text-[11px] font-semibold tracking-[0.18em] text-stone-700 uppercase"
                            >
                                {{
                                    selectedPage
                                        ? selectedPage.name
                                        : 'No Page selected'
                                }}
                            </span>
                            <span
                                class="inline-flex rounded-full border border-stone-300 bg-white/85 px-3 py-1.5 text-[11px] font-semibold tracking-[0.18em] text-stone-700 uppercase"
                            >
                                {{
                                    whatsappReady
                                        ? 'WhatsApp ready'
                                        : 'WhatsApp staged'
                                }}
                            </span>
                            <span
                                class="inline-flex rounded-full border border-stone-300 bg-white/85 px-3 py-1.5 text-[11px] font-semibold tracking-[0.18em] text-stone-700 uppercase"
                            >
                                {{
                                    form.creator_challenge.enabled
                                        ? 'Creator challenge on'
                                        : 'Creator challenge staged'
                                }}
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <a
                                v-if="
                                    props.meta.connect_ready &&
                                    metaBusinessLoginConfigReady
                                "
                                :href="metaConnectRoute"
                                class="inline-flex items-center gap-2 rounded-2xl bg-stone-950 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-stone-800"
                            >
                                <FontAwesomeIcon :icon="metaConnectionIcon" />
                                {{ metaConnectActionLabel }}
                            </a>
                            <span
                                v-else-if="
                                    props.meta.connect_ready &&
                                    !metaBusinessLoginConfigReady
                                "
                                class="inline-flex items-center gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm font-medium text-amber-900"
                            >
                                <FontAwesomeIcon :icon="faLinkSlash" />
                                Save Config ID first
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm font-medium text-amber-900"
                            >
                                <FontAwesomeIcon :icon="faLinkSlash" />
                                Add App ID and Secret first
                            </span>

                            <button
                                type="submit"
                                class="inline-flex items-center gap-2 rounded-2xl border border-stone-300 bg-white/90 px-4 py-2.5 text-sm font-medium text-stone-900 transition hover:border-stone-500"
                            >
                                <FontAwesomeIcon :icon="faCloudArrowUp" />
                                Save social settings
                            </button>

                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-2.5 text-sm font-medium text-sky-950 transition hover:border-sky-400 disabled:cursor-not-allowed disabled:opacity-70"
                                :disabled="importForm.processing"
                                @click="importWebsiteTracking"
                            >
                                <FontAwesomeIcon :icon="faLink" />
                                {{
                                    importForm.processing
                                        ? 'Importing website admin Meta…'
                                        : 'Import website admin Meta'
                                }}
                            </button>

                            <span
                                v-if="form.meta.last_connected_at"
                                class="text-sm text-stone-600"
                            >
                                Last connected
                                {{
                                    new Date(
                                        form.meta.last_connected_at,
                                    ).toLocaleString()
                                }}
                            </span>
                        </div>

                        <div
                            class="rounded-[26px] border border-stone-200 bg-white/86 p-4 shadow-[0_16px_34px_rgba(15,23,42,0.06)]"
                        >
                            <div
                                class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
                            >
                                <div>
                                    <p
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >
                                        Meta login plan
                                    </p>
                                    <h2
                                        class="mt-1 text-base font-semibold text-stone-950"
                                    >
                                        Start with Page-read. Add other lanes only
                                        after that token works.
                                    </h2>
                                    <p
                                        class="mt-2 max-w-3xl text-sm leading-6 text-stone-600"
                                    >
                                        Creator Challenge, Publishing, Ads,
                                        Instagram, and WhatsApp all change what
                                        Meta may ask for. This keeps the scope
                                        decision in one place instead of making
                                        you hunt across the whole page.
                                    </p>
                                </div>
                                <button
                                    v-if="enabledScopeLanes.length > 0"
                                    type="button"
                                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-2.5 text-sm font-medium text-sky-950 transition hover:border-sky-400 disabled:cursor-not-allowed disabled:opacity-70"
                                    :disabled="form.processing"
                                    @click="stagePageReadConnection"
                                >
                                    <FontAwesomeIcon :icon="faCircleCheck" />
                                    Stage clean login
                                </button>
                            </div>

                            <div class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                                <a
                                    v-for="lane in scopeLanes"
                                    :key="lane.key"
                                    :href="lane.href"
                                    class="group rounded-[18px] border px-3 py-3 text-left transition hover:-translate-y-0.5 hover:shadow-[0_12px_28px_rgba(15,23,42,0.08)]"
                                    :class="
                                        lane.enabled
                                            ? lane.key === 'base'
                                                ? 'border-emerald-200 bg-emerald-50/80'
                                                : 'border-amber-200 bg-amber-50/80'
                                            : 'border-stone-200 bg-stone-50'
                                    "
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <p
                                            class="text-sm font-semibold text-stone-950"
                                        >
                                            {{ lane.label }}
                                        </p>
                                        <span
                                            class="rounded-full border px-2 py-0.5 text-[10px] font-semibold tracking-[0.14em] uppercase"
                                            :class="
                                                lane.enabled
                                                    ? lane.key === 'base'
                                                        ? 'border-emerald-200 bg-white text-emerald-800'
                                                        : 'border-amber-200 bg-white text-amber-900'
                                                    : 'border-stone-200 bg-white text-stone-500'
                                            "
                                        >
                                            {{
                                                lane.enabled
                                                    ? lane.key === 'base'
                                                        ? 'Required'
                                                        : 'On'
                                                    : 'Off'
                                            }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-xs leading-5 text-stone-600">
                                        {{ lane.description }}
                                    </p>
                                    <p
                                        class="mt-2 truncate text-[10px] font-semibold tracking-[0.12em] text-stone-500 uppercase group-hover:text-stone-900"
                                    >
                                        {{ lane.scopes.join(' · ') }}
                                    </p>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div
                        class="rounded-[28px] border border-stone-200/80 bg-white/82 p-4 shadow-[0_18px_48px_rgba(15,23,42,0.08)] backdrop-blur"
                    >
                        <p
                            class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                        >
                            Lane snapshot
                        </p>
                        <div class="mt-3 grid gap-2">
                            <div
                                class="rounded-[20px] border border-stone-200 bg-stone-50 px-4 py-3"
                            >
                                <div class="flex items-center gap-3">
                                    <SocialPlatformMark
                                        brand="meta"
                                        large
                                        monochrome
                                        class="shrink-0 text-[#0866ff]"
                                    />
                                    <div class="min-w-0">
                                        <p
                                            class="text-sm font-medium text-stone-900"
                                        >
                                            Meta business
                                        </p>
                                        <p class="text-sm text-stone-600">
                                            {{ metaBusinessSummary }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-[20px] border border-stone-200 bg-stone-50 px-4 py-3"
                            >
                                <div class="flex items-center gap-3">
                                    <FontAwesomeIcon
                                        :icon="faRectangleAd"
                                        class="shrink-0 text-[30px] text-stone-700"
                                    />
                                    <div class="min-w-0">
                                        <p
                                            class="text-sm font-medium text-stone-900"
                                        >
                                            Ads baseline
                                        </p>
                                        <p class="text-sm text-stone-600">
                                            {{
                                                selectedAdAccount
                                                    ? `${selectedAdAccount.name || selectedAdAccount.id} selected.`
                                                    : 'No default ad account selected yet.'
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-[20px] border border-stone-200 bg-stone-50 px-4 py-3"
                            >
                                <div class="flex items-center gap-3">
                                    <SocialPlatformMark
                                        brand="whatsapp"
                                        large
                                        monochrome
                                        class="shrink-0 text-[#25d366]"
                                    />
                                    <div class="min-w-0">
                                        <p
                                            class="text-sm font-medium text-stone-900"
                                        >
                                            WhatsApp follow-up
                                        </p>
                                        <p class="text-sm text-stone-600">
                                            {{ whatsappSummary }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-[20px] border border-stone-200 bg-stone-50 px-4 py-3"
                            >
                                <div class="flex items-center gap-3">
                                    <FontAwesomeIcon
                                        :icon="faTrophy"
                                        class="shrink-0 text-[30px] text-stone-700"
                                    />
                                    <div class="min-w-0">
                                        <p
                                            class="text-sm font-medium text-stone-900"
                                        >
                                            Creator challenge
                                        </p>
                                        <p class="text-sm text-stone-600">
                                            {{ creatorChallengeSummary }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-4 rounded-[22px] border border-stone-200 bg-white px-4 py-4"
                        >
                            <p
                                class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                            >
                                Current callback
                            </p>
                            <p
                                class="mt-2 text-sm leading-6 break-all text-stone-900"
                            >
                                {{ props.meta.callback_url }}
                            </p>
                            <p class="mt-2 text-xs leading-5 text-stone-500">
                                {{
                                    props.meta.callback_mode === 'api_domain'
                                        ? 'Register this stable API callback inside Meta so the office domain stays valid even if ngrok rotates.'
                                        : props.meta.callback_mode === 'public'
                                          ? 'Register this exact ngrok callback inside Meta only until the website bridge domain is live.'
                                          : 'Ngrok is off or unavailable, so the lane is still local-only.'
                                }}
                            </p>
                            <div
                                v-if="
                                    props.meta.configured_callback_status
                                        .state === 'unreachable' &&
                                    props.meta.configured_callback_status
                                        .callback_url
                                "
                                class="mt-3 rounded-[18px] border border-amber-200 bg-amber-50 px-3 py-3 text-amber-950"
                            >
                                <p
                                    class="text-[11px] font-medium tracking-[0.22em] text-amber-800 uppercase"
                                >
                                    Saved website bridge not live yet
                                </p>
                                <p
                                    class="mt-2 text-sm font-medium break-all text-stone-900"
                                >
                                    {{
                                        props.meta.configured_callback_status
                                            .callback_url
                                    }}
                                </p>
                                <p
                                    class="mt-2 text-xs leading-5 text-amber-900"
                                >
                                    {{
                                        props.meta.configured_callback_status
                                            .message
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-if="isSocialSection('overview')"
                class="overflow-hidden rounded-[24px] border border-stone-200 bg-white/95"
            >
                <div
                    class="grid gap-4 px-5 py-4 lg:grid-cols-[0.78fr_1.22fr] lg:items-center"
                >
                    <div class="space-y-2">
                        <p
                            class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >
                            What can I do?
                        </p>
                        <h2 class="text-lg font-semibold text-stone-950">
                            Use the overview as the action hub.
                        </h2>
                        <p class="text-sm leading-6 text-stone-600">
                            The child pages are for setup. This header page is
                            for choosing the next move: plan content, check the
                            challenge, connect Facebook, or see what is still
                            blocked.
                        </p>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <a
                            href="/calendar/social"
                            class="group rounded-[20px] border border-stone-200 bg-stone-50 px-4 py-3 transition hover:border-stone-400 hover:bg-white"
                        >
                            <div class="flex items-center gap-3">
                                <SocialPlatformMark
                                    brand="meta"
                                    large
                                    monochrome
                                    class="shrink-0 text-[#0866ff]"
                                />
                                <div class="min-w-0">
                                    <p
                                        class="text-sm font-semibold text-stone-950"
                                    >
                                        Open Social / Meta workspace
                                    </p>
                                    <p
                                        class="mt-1 text-sm leading-5 text-stone-600"
                                    >
                                        Content calendar, challenge work, and
                                        Meta action lanes.
                                    </p>
                                </div>
                            </div>
                        </a>

                        <a
                            href="/settings/social/creator-challenge"
                            class="group rounded-[20px] border border-stone-200 bg-stone-50 px-4 py-3 transition hover:border-stone-400 hover:bg-white"
                        >
                            <div class="flex items-center gap-3">
                                <FontAwesomeIcon
                                    :icon="faTrophy"
                                    class="shrink-0 text-[28px] text-stone-800"
                                />
                                <div class="min-w-0">
                                    <p
                                        class="text-sm font-semibold text-stone-950"
                                    >
                                        Review this week&rsquo;s challenge
                                    </p>
                                    <p
                                        class="mt-1 text-sm leading-5 text-stone-600"
                                    >
                                        See live/limited Meta data, scoring,
                                        goals, and winner placement.
                                    </p>
                                </div>
                            </div>
                        </a>

                        <a
                            href="/settings/social/facebook"
                            class="group rounded-[20px] border border-stone-200 bg-stone-50 px-4 py-3 transition hover:border-stone-400 hover:bg-white"
                        >
                            <div class="flex items-center gap-3">
                                <SocialPlatformMark
                                    brand="meta"
                                    large
                                    monochrome
                                    class="shrink-0 text-[#0866ff]"
                                />
                                <div class="min-w-0">
                                    <p
                                        class="text-sm font-semibold text-stone-950"
                                    >
                                        Tune Facebook connection
                                    </p>
                                    <p
                                        class="mt-1 text-sm leading-5 text-stone-600"
                                    >
                                        App ID, callback, Business Login,
                                        Page, and ad account setup.
                                    </p>
                                </div>
                            </div>
                        </a>

                        <a
                            href="/settings/social/whatsapp"
                            class="group rounded-[20px] border border-stone-200 bg-stone-50 px-4 py-3 transition hover:border-stone-400 hover:bg-white"
                        >
                            <div class="flex items-center gap-3">
                                <SocialPlatformMark
                                    brand="whatsapp"
                                    large
                                    monochrome
                                    class="shrink-0 text-[#25d366]"
                                />
                                <div class="min-w-0">
                                    <p
                                        class="text-sm font-semibold text-stone-950"
                                    >
                                        Check WhatsApp readiness
                                    </p>
                                    <p
                                        class="mt-1 text-sm leading-5 text-stone-600"
                                    >
                                        See whether Meta returned a test number,
                                        production number, or verification block.
                                    </p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </section>

            <section
                v-if="isSocialSection('readiness')"
                id="social-meta-readiness"
                class="overflow-hidden rounded-[28px] border border-stone-200 bg-white/95 shadow-[0_14px_36px_rgba(15,23,42,0.05)]"
            >
                <div
                    class="grid gap-5 border-b border-stone-200/80 px-5 py-5 xl:grid-cols-[0.95fr_1.05fr]"
                >
                    <div class="space-y-4">
                        <div class="flex items-center gap-4">
                            <SocialPlatformMark
                                brand="meta"
                                large
                                monochrome
                                class="text-[#0866ff]"
                            />
                            <div>
                                <p
                                    class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                                >
                                    Meta readiness board
                                </p>
                                <h2
                                    class="mt-1 text-xl font-semibold tracking-tight text-stone-950"
                                >
                                    Know what can run now and what Meta is still
                                    holding.
                                </h2>
                            </div>
                        </div>

                        <p class="max-w-3xl text-sm leading-6 text-stone-600">
                            This board stays honest while verification is
                            pending: core Page access, creator challenge sync,
                            Instagram drafts, Threads manual posting, ads, and
                            WhatsApp all show their actual lane state instead
                            of blending setup and production into one noisy
                            page.
                        </p>

                        <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                            <div
                                class="rounded-[18px] border border-emerald-200 bg-emerald-50 px-3 py-3 text-emerald-950"
                            >
                                <p
                                    class="text-[10px] font-semibold tracking-[0.18em] uppercase"
                                >
                                    Ready
                                </p>
                                <p class="mt-1 text-2xl font-semibold">
                                    {{ metaReadinessCounts.ready }}
                                </p>
                            </div>
                            <div
                                class="rounded-[18px] border border-sky-200 bg-sky-50 px-3 py-3 text-sky-950"
                            >
                                <p
                                    class="text-[10px] font-semibold tracking-[0.18em] uppercase"
                                >
                                    Staged
                                </p>
                                <p class="mt-1 text-2xl font-semibold">
                                    {{ metaReadinessCounts.staged }}
                                </p>
                            </div>
                            <div
                                class="rounded-[18px] border border-amber-200 bg-amber-50 px-3 py-3 text-amber-950"
                            >
                                <p
                                    class="text-[10px] font-semibold tracking-[0.18em] uppercase"
                                >
                                    Blocked
                                </p>
                                <p class="mt-1 text-2xl font-semibold">
                                    {{ metaReadinessCounts.blocked }}
                                </p>
                            </div>
                            <div
                                class="rounded-[18px] border border-stone-200 bg-stone-50 px-3 py-3 text-stone-700"
                            >
                                <p
                                    class="text-[10px] font-semibold tracking-[0.18em] uppercase"
                                >
                                    Manual
                                </p>
                                <p class="mt-1 text-2xl font-semibold">
                                    {{ metaReadinessCounts.manual }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="rounded-[20px] border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <p
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                            >
                                Current read
                            </p>
                            <p class="mt-2 text-sm leading-6 text-stone-700">
                                {{ readinessBoardSummary }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="rounded-[24px] border border-stone-200 bg-stone-50/80 px-4 py-4"
                    >
                        <div
                            class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
                        >
                            <div>
                                <p
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >
                                    Next moves
                                </p>
                                <h3
                                    class="mt-1 text-base font-semibold text-stone-950"
                                >
                                    Work this list top to bottom.
                                </h3>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-2xl bg-stone-950 px-3 py-2 text-sm font-semibold text-white transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-70"
                                    :disabled="apiTestForm.processing"
                                    @click="runMetaApiTest"
                                >
                                    <FontAwesomeIcon :icon="faCloudArrowUp" />
                                    {{
                                        apiTestForm.processing
                                            ? 'Testing...'
                                            : 'Run Meta API test'
                                    }}
                                </button>
                                <a
                                    href="/calendar/social"
                                    class="inline-flex items-center gap-2 rounded-2xl border border-stone-300 bg-white px-3 py-2 text-sm font-medium text-stone-900 transition hover:border-stone-500"
                                >
                                    <FontAwesomeIcon :icon="faCalendarDays" />
                                    Calendar
                                </a>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-2">
                            <a
                                v-for="move in metaNextMoves"
                                :key="move.key"
                                :href="move.href"
                                class="group rounded-[18px] border px-3 py-3 transition hover:-translate-y-0.5 hover:shadow-[0_12px_26px_rgba(15,23,42,0.08)]"
                                :class="readinessToneClass(move.tone)"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold">
                                            {{ move.label }}
                                        </p>
                                        <p class="mt-1 text-xs leading-5">
                                            {{ move.detail }}
                                        </p>
                                    </div>
                                    <FontAwesomeIcon
                                        :icon="faArrowUpRightFromSquare"
                                        class="mt-1 shrink-0 text-xs opacity-60 transition group-hover:opacity-100"
                                    />
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 px-5 py-5 xl:grid-cols-[1.12fr_0.88fr]">
                    <div class="space-y-4">
                        <div class="grid gap-3 md:grid-cols-2">
                            <a
                                v-for="item in metaReadinessItems"
                                :key="item.key"
                                :href="item.href || '/settings/social/readiness'"
                                class="group rounded-[20px] border border-stone-200 bg-white px-4 py-4 transition hover:-translate-y-0.5 hover:border-stone-300 hover:shadow-[0_14px_28px_rgba(15,23,42,0.06)]"
                            >
                                <div class="flex items-start gap-3">
                                    <span
                                        class="mt-1 size-2.5 shrink-0 rounded-full"
                                        :class="readinessDotClass(item.tone)"
                                    ></span>
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <p
                                                class="text-sm font-semibold text-stone-950"
                                            >
                                                {{ item.label }}
                                            </p>
                                            <span
                                                class="rounded-full border px-2 py-0.5 text-[10px] font-semibold tracking-[0.14em] uppercase"
                                                :class="
                                                    readinessToneClass(item.tone)
                                                "
                                            >
                                                {{ item.status }}
                                            </span>
                                        </div>
                                        <p
                                            class="mt-2 text-sm leading-6 text-stone-600"
                                        >
                                            {{ item.detail }}
                                        </p>
                                        <p
                                            class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-stone-500 transition group-hover:text-stone-950"
                                        >
                                            {{ item.action || 'Open lane' }}
                                            <FontAwesomeIcon
                                                :icon="faArrowUpRightFromSquare"
                                                class="text-[10px]"
                                            />
                                        </p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div
                            class="rounded-[22px] border border-stone-200 bg-white px-4 py-4"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div>
                                    <p
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >
                                        Meta API testing
                                    </p>
                                    <div
                                        class="mt-2 flex flex-wrap items-center gap-2"
                                    >
                                        <h3
                                            class="text-base font-semibold text-stone-950"
                                        >
                                            Prove Graph calls from CreditSoft
                                        </h3>
                                        <span
                                            class="rounded-full border px-2.5 py-1 text-[10px] font-semibold tracking-[0.14em] uppercase"
                                            :class="
                                                readinessToneClass(apiTestTone)
                                            "
                                        >
                                            {{ apiTestStatusLabel }}
                                        </span>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-2xl border border-stone-300 bg-stone-950 px-3 py-2 text-sm font-semibold text-white transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-70"
                                    :disabled="apiTestForm.processing"
                                    @click="runMetaApiTest"
                                >
                                    <FontAwesomeIcon :icon="faCloudArrowUp" />
                                    {{
                                        apiTestForm.processing
                                            ? 'Running...'
                                            : 'Run test'
                                    }}
                                </button>
                            </div>

                            <p class="mt-3 text-sm leading-6 text-stone-600">
                                {{ apiTestSummary }}
                            </p>
                            <p
                                v-if="form.meta.api_test?.last_tested_at"
                                class="mt-2 text-xs leading-5 text-stone-500"
                            >
                                Last tested
                                {{
                                    new Date(
                                        form.meta.api_test.last_tested_at,
                                    ).toLocaleString()
                                }}.
                            </p>

                            <div class="mt-4 grid grid-cols-4 gap-2">
                                <div
                                    class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-900"
                                >
                                    <p
                                        class="text-[10px] font-semibold tracking-[0.14em] uppercase"
                                    >
                                        Passed
                                    </p>
                                    <p class="mt-1 text-lg font-semibold">
                                        {{ apiTestCounts.passed }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-rose-900"
                                >
                                    <p
                                        class="text-[10px] font-semibold tracking-[0.14em] uppercase"
                                    >
                                        Failed
                                    </p>
                                    <p class="mt-1 text-lg font-semibold">
                                        {{ apiTestCounts.failed }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-2xl border border-sky-200 bg-sky-50 px-3 py-2 text-sky-900"
                                >
                                    <p
                                        class="text-[10px] font-semibold tracking-[0.14em] uppercase"
                                    >
                                        Manual
                                    </p>
                                    <p class="mt-1 text-lg font-semibold">
                                        {{ apiTestCounts.manual }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-2xl border border-stone-200 bg-stone-50 px-3 py-2 text-stone-700"
                                >
                                    <p
                                        class="text-[10px] font-semibold tracking-[0.14em] uppercase"
                                    >
                                        Skipped
                                    </p>
                                    <p class="mt-1 text-lg font-semibold">
                                        {{ apiTestCounts.skipped }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="apiTestResults.length > 0"
                                class="mt-4 space-y-2"
                            >
                                <article
                                    v-for="result in apiTestResults"
                                    :key="result.key"
                                    class="rounded-[18px] border border-stone-200 bg-stone-50 px-3 py-3"
                                >
                                    <div
                                        class="flex flex-wrap items-start justify-between gap-2"
                                    >
                                        <div class="min-w-0">
                                            <p
                                                class="text-sm font-semibold text-stone-950"
                                            >
                                                {{ result.label }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs leading-5 break-all text-stone-500"
                                            >
                                                {{ result.permission }} -
                                                {{ result.endpoint }}
                                                <span
                                                    v-if="result.http_status"
                                                >
                                                    - HTTP
                                                    {{ result.http_status }}
                                                </span>
                                            </p>
                                        </div>
                                        <span
                                            class="rounded-full border px-2.5 py-1 text-[10px] font-semibold tracking-[0.12em] uppercase"
                                            :class="
                                                apiTestResultClass(
                                                    result.status,
                                                )
                                            "
                                        >
                                            {{
                                                apiTestResultLabel(
                                                    result.status,
                                                )
                                            }}
                                        </span>
                                    </div>
                                    <p
                                        class="mt-2 text-xs leading-5 text-stone-600"
                                    >
                                        {{ result.message }}
                                    </p>
                                </article>
                            </div>
                            <p
                                v-else
                                class="mt-4 rounded-[18px] border border-amber-200 bg-amber-50 px-3 py-3 text-sm leading-6 text-amber-900"
                            >
                                No API calls are recorded yet. Click Run test
                                after Meta is connected.
                            </p>
                        </div>

                        <div
                            class="rounded-[22px] border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <div
                                class="flex items-start justify-between gap-3"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >
                                        Meta redirect packet
                                    </p>
                                    <p
                                        class="mt-2 text-sm leading-6 break-all text-stone-900"
                                    >
                                        {{ props.meta.callback_url }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-stone-300 bg-white px-3 py-2 text-xs font-semibold text-stone-800 transition hover:border-stone-500"
                                    @click="
                                        copySetupValue(
                                            props.meta.callback_url,
                                            'readiness-callback',
                                        )
                                    "
                                >
                                    <FontAwesomeIcon :icon="faCopy" />
                                    {{
                                        copiedSetupValue ===
                                        'readiness-callback'
                                            ? 'Copied'
                                            : 'Copy'
                                    }}
                                </button>
                            </div>
                            <p class="mt-3 text-xs leading-5 text-stone-500">
                                Use this exact callback in Business Login. If
                                the bridge changes, update it here first so
                                CreditSoft keeps generating one stable value.
                            </p>
                        </div>

                        <div
                            class="rounded-[22px] border border-stone-200 bg-white px-4 py-4"
                        >
                            <p
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                            >
                                Dashboard paste list
                            </p>
                            <div class="mt-4 space-y-3">
                                <div
                                    v-for="field in metaDashboardFields"
                                    :key="field.key"
                                    class="rounded-[18px] border border-stone-200 bg-stone-50 px-3 py-3"
                                >
                                    <p
                                        class="text-sm font-semibold text-stone-950"
                                    >
                                        {{ field.label }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs leading-5 text-stone-500"
                                    >
                                        {{ field.help }}
                                    </p>
                                    <div class="mt-2 space-y-1">
                                        <button
                                            v-for="value in field.values"
                                            :key="`${field.key}-${value}`"
                                            type="button"
                                            class="block w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-left text-xs break-all text-stone-800 transition hover:border-stone-400"
                                            @click="
                                                copySetupValue(
                                                    value,
                                                    `${field.key}-${value}`,
                                                )
                                            "
                                        >
                                            {{
                                                copiedSetupValue ===
                                                `${field.key}-${value}`
                                                    ? 'Copied: '
                                                    : ''
                                            }}{{ value }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-[22px] border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <p
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                            >
                                Scope ladder
                            </p>
                            <div class="mt-3 grid gap-2">
                                <a
                                    v-for="lane in scopeLanes"
                                    :key="`readiness-${lane.key}`"
                                    :href="lane.href"
                                    class="rounded-[16px] border border-stone-200 bg-white px-3 py-3 transition hover:border-stone-400"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-sm font-semibold text-stone-950"
                                            >
                                                {{ lane.label }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs leading-5 text-stone-600"
                                            >
                                                {{ lane.description }}
                                            </p>
                                        </div>
                                        <span
                                            class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-semibold tracking-[0.14em] uppercase"
                                            :class="
                                                lane.enabled
                                                    ? 'border-amber-200 bg-amber-50 text-amber-900'
                                                    : 'border-stone-200 bg-stone-50 text-stone-500'
                                            "
                                        >
                                            {{
                                                lane.enabled
                                                    ? lane.key === 'base'
                                                        ? 'Required'
                                                        : 'On'
                                                    : 'Off'
                                            }}
                                        </span>
                                    </div>
                                    <p
                                        class="mt-2 text-[10px] font-semibold tracking-[0.12em] text-stone-500 uppercase"
                                    >
                                        {{ lane.scopes.join(' · ') }}
                                    </p>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-if="isSocialSection('creator-challenge')"
                id="social-creator-challenge"
                class="overflow-hidden rounded-[24px] border border-stone-200 bg-white/95 shadow-[0_12px_30px_rgba(15,23,42,0.04)]"
            >
                <div class="border-b border-stone-200/80 px-5 py-4">
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between"
                    >
                        <div class="max-w-3xl">
                            <div class="flex items-center gap-3">
                                <FontAwesomeIcon
                                    :icon="faTrophy"
                                    class="text-[26px] text-stone-700"
                                />
                                <div>
                                    <p
                                        class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                                    >
                                        Creator challenge
                                    </p>
                                    <h2
                                        class="mt-2 text-lg font-semibold text-stone-950"
                                    >
                                        Score the week, help the page hit goals,
                                        and crown winners cleanly.
                                    </h2>
                                </div>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-stone-600">
                                Keep the scoring model on signals the office can
                                actually verify: public comments, public shares,
                                publishing cadence, liked replies, and warmer
                                leads handed back into the office. CreditSoft
                                can guide the week without pretending it can see
                                private-share data Meta does not expose, and
                                without using contest mechanics that rely on
                                personal Timeline shares or forced friend
                                tagging.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <span
                                class="inline-flex rounded-full border px-3 py-1.5 text-[11px] font-semibold tracking-[0.18em] uppercase"
                                :class="
                                    form.creator_challenge.enabled
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                        : 'border-amber-200 bg-amber-50 text-amber-800'
                                "
                            >
                                {{
                                    form.creator_challenge.enabled
                                        ? 'Scoring enabled'
                                        : 'Scoring staged'
                                }}
                            </span>
                            <span
                                class="inline-flex rounded-full border border-stone-300 bg-white px-3 py-1.5 text-[11px] font-semibold tracking-[0.18em] text-stone-700 uppercase"
                            >
                                {{
                                    form.creator_challenge
                                        .track_weekly_challenge
                                        ? 'Weekly challenge tracked'
                                        : 'Manual contest mode'
                                }}
                            </span>
                            <span
                                class="inline-flex rounded-full border px-3 py-1.5 text-[11px] font-semibold tracking-[0.18em] uppercase"
                                :class="
                                    hasLiveCreatorData
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                        : creatorChallengeSyncFailed
                                          ? 'border-rose-200 bg-rose-50 text-rose-800'
                                          : 'border-amber-200 bg-amber-50 text-amber-800'
                                "
                            >
                                {{
                                    hasLiveCreatorData
                                        ? 'Live Meta data'
                                        : creatorChallengeSyncFailed
                                          ? 'Live sync blocked'
                                          : 'Not synced'
                                }}
                            </span>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-2xl border border-stone-300 bg-white px-4 py-2.5 text-sm font-medium text-stone-900 transition hover:border-stone-500 disabled:cursor-not-allowed disabled:opacity-70"
                                :disabled="challengeSyncForm.processing"
                                @click="syncCreatorChallenge"
                            >
                                <FontAwesomeIcon :icon="faCloudArrowUp" />
                                {{
                                    challengeSyncForm.processing
                                        ? 'Syncing live Meta data…'
                                        : 'Sync live Meta data'
                                }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 px-5 py-5 xl:grid-cols-[1.05fr_0.95fr]">
                    <div class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <label
                                class="flex items-start gap-3 rounded-[18px] border border-stone-200 bg-stone-50 px-4 py-4"
                            >
                                <input
                                    v-model="form.creator_challenge.enabled"
                                    type="checkbox"
                                    class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                                />
                                <span class="space-y-1 text-sm text-stone-700">
                                    <span
                                        class="block font-medium text-stone-900"
                                        >Enable creator challenge lane</span
                                    >
                                    <span class="block leading-6"
                                        >Let CreditSoft track points, show
                                        placements, and prep a winner
                                        recap.</span
                                    >
                                </span>
                            </label>
                            <label
                                class="flex items-start gap-3 rounded-[18px] border border-stone-200 bg-stone-50 px-4 py-4"
                            >
                                <input
                                    v-model="
                                        form.creator_challenge
                                            .track_weekly_challenge
                                    "
                                    type="checkbox"
                                    class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                                />
                                <span class="space-y-1 text-sm text-stone-700">
                                    <span
                                        class="block font-medium text-stone-900"
                                        >Track the Page&rsquo;s weekly
                                        challenge</span
                                    >
                                    <span class="block leading-6"
                                        >Keep the office focused on the current
                                        creator targets instead of posting
                                        blind.</span
                                    >
                                </span>
                            </label>
                            <label
                                class="flex items-start gap-3 rounded-[18px] border border-stone-200 bg-stone-50 px-4 py-4"
                            >
                                <input
                                    v-model="
                                        form.creator_challenge
                                            .require_goal_completion
                                    "
                                    type="checkbox"
                                    class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                                />
                                <span class="space-y-1 text-sm text-stone-700">
                                    <span
                                        class="block font-medium text-stone-900"
                                        >Require goals before winner lock</span
                                    >
                                    <span class="block leading-6"
                                        >Do not finalize first place until the
                                        weekly goal board is satisfied.</span
                                    >
                                </span>
                            </label>
                            <label
                                class="flex items-start gap-3 rounded-[18px] border border-stone-200 bg-stone-50 px-4 py-4"
                            >
                                <input
                                    v-model="
                                        form.creator_challenge
                                            .ai_guidance_enabled
                                    "
                                    type="checkbox"
                                    class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                                />
                                <span class="space-y-1 text-sm text-stone-700">
                                    <span
                                        class="block font-medium text-stone-900"
                                        >AI guidance enabled</span
                                    >
                                    <span class="block leading-6"
                                        >Let CreditSoft suggest the next move
                                        when the Page falls behind on comments,
                                        shares, or replies.</span
                                    >
                                </span>
                            </label>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="space-y-2 md:col-span-2">
                                <span
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >Challenge name</span
                                >
                                <input
                                    v-model="
                                        form.creator_challenge.challenge_name
                                    "
                                    type="text"
                                    class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                    placeholder="Weekly creator challenge"
                                />
                            </label>
                            <label class="space-y-2">
                                <span
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >Challenge window</span
                                >
                                <select
                                    v-model="
                                        form.creator_challenge.challenge_window
                                    "
                                    class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                >
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </label>
                            <label class="space-y-2">
                                <span
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >Leaderboard depth</span
                                >
                                <input
                                    v-model="
                                        form.creator_challenge.leaderboard_depth
                                    "
                                    type="text"
                                    class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                    placeholder="10"
                                />
                            </label>
                            <label class="space-y-2">
                                <span
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >Tie breaker</span
                                >
                                <select
                                    v-model="form.creator_challenge.tie_breaker"
                                    class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                >
                                    <option value="shares_then_comments">
                                        Public shares, then comments
                                    </option>
                                    <option value="comments_then_posts">
                                        Comments, then posts
                                    </option>
                                    <option value="leads_then_shares">
                                        Leads, then shares
                                    </option>
                                </select>
                            </label>
                        </div>

                        <div
                            class="rounded-[20px] border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <div class="flex items-center gap-3">
                                <FontAwesomeIcon
                                    :icon="faListOl"
                                    class="text-lg text-stone-700"
                                />
                                <div>
                                    <p
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >
                                        Point weights
                                    </p>
                                    <h3
                                        class="mt-1 text-base font-semibold text-stone-950"
                                    >
                                        Use your own office scoring algorithm.
                                    </h3>
                                </div>
                            </div>

                            <div
                                class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                            >
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                        >Public comment points</span
                                    >
                                    <input
                                        v-model="
                                            form.creator_challenge
                                                .comment_points
                                        "
                                        type="text"
                                        class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                        >Public share points</span
                                    >
                                    <input
                                        v-model="
                                            form.creator_challenge
                                                .public_share_points
                                        "
                                        type="text"
                                        class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                        >Published post points</span
                                    >
                                    <input
                                        v-model="
                                            form.creator_challenge
                                                .published_post_points
                                        "
                                        type="text"
                                        class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                        >Comment-like bonus</span
                                    >
                                    <input
                                        v-model="
                                            form.creator_challenge
                                                .comment_like_bonus_points
                                        "
                                        type="text"
                                        class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                        >Bonus step</span
                                    >
                                    <input
                                        v-model="
                                            form.creator_challenge
                                                .comment_like_bonus_step
                                        "
                                        type="text"
                                        class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    />
                                </label>
                            </div>
                        </div>

                        <div
                            class="rounded-[28px] border border-stone-200 bg-stone-50 px-5 py-5"
                        >
                            <div class="flex items-center gap-3">
                                <FontAwesomeIcon
                                    :icon="faBullseye"
                                    class="text-lg text-stone-700"
                                />
                                <div>
                                    <p
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >
                                        Weekly goals
                                    </p>
                                    <h3
                                        class="mt-1 text-base font-semibold text-stone-950"
                                    >
                                        Help the page reach the challenge
                                        targets.
                                    </h3>
                                </div>
                            </div>

                            <div
                                class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                            >
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                        >Posts</span
                                    >
                                    <input
                                        v-model="
                                            form.creator_challenge.goal_posts
                                        "
                                        type="text"
                                        class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                        >Comments</span
                                    >
                                    <input
                                        v-model="
                                            form.creator_challenge.goal_comments
                                        "
                                        type="text"
                                        class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                        >Public shares</span
                                    >
                                    <input
                                        v-model="
                                            form.creator_challenge
                                                .goal_public_shares
                                        "
                                        type="text"
                                        class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                        >Reply windows</span
                                    >
                                    <input
                                        v-model="
                                            form.creator_challenge
                                                .goal_reply_windows
                                        "
                                        type="text"
                                        class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                        >Leads back to office</span
                                    >
                                    <input
                                        v-model="
                                            form.creator_challenge.goal_leads
                                        "
                                        type="text"
                                        class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    />
                                </label>
                            </div>
                        </div>

                        <div
                            class="rounded-[28px] border border-stone-200 bg-stone-50 px-5 py-5"
                        >
                            <div class="flex items-center gap-3">
                                <FontAwesomeIcon
                                    :icon="faTrophy"
                                    class="text-lg text-stone-700"
                                />
                                <div>
                                    <p
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >
                                        Placement labels
                                    </p>
                                    <h3
                                        class="mt-1 text-base font-semibold text-stone-950"
                                    >
                                        Name first, second, third, and the rest
                                        of the board.
                                    </h3>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                        >First place</span
                                    >
                                    <input
                                        v-model="
                                            form.creator_challenge
                                                .first_place_label
                                        "
                                        type="text"
                                        class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                        >Second place</span
                                    >
                                    <input
                                        v-model="
                                            form.creator_challenge
                                                .second_place_label
                                        "
                                        type="text"
                                        class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                        >Third place</span
                                    >
                                    <input
                                        v-model="
                                            form.creator_challenge
                                                .third_place_label
                                        "
                                        type="text"
                                        class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                        >Placement tier label</span
                                    >
                                    <input
                                        v-model="
                                            form.creator_challenge
                                                .placement_tier_label
                                        "
                                        type="text"
                                        class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    />
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div
                            class="rounded-[28px] border border-stone-200 bg-[linear-gradient(135deg,_rgba(8,102,255,0.12),_rgba(37,99,235,0.04)_55%,_rgba(255,255,255,0.94))] p-5"
                        >
                            <p
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                            >
                                {{
                                    hasLiveCreatorData
                                        ? 'Live snapshot'
                                        : 'No live snapshot'
                                }}
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <h3
                                    class="text-lg font-semibold text-stone-950"
                                >
                                    {{ form.creator_challenge.challenge_name }}
                                </h3>
                                <span
                                    class="inline-flex rounded-full border px-3 py-1 text-[10px] font-semibold tracking-[0.16em] uppercase"
                                    :class="
                                        hasLiveCreatorData
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                            : creatorChallengeSyncFailed
                                              ? 'border-rose-200 bg-rose-50 text-rose-800'
                                              : 'border-amber-200 bg-amber-50 text-amber-800'
                                    "
                                >
                                    {{
                                        hasLiveCreatorData
                                            ? creatorChallengeLimited
                                                ? 'Limited live data'
                                                : 'Live Meta data'
                                            : creatorChallengeSyncFailed
                                              ? 'Sync failed'
                                              : 'Not synced'
                                    }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-stone-600">
                                {{ creatorChallengeSummary }}
                            </p>
                            <p
                                v-if="hasLiveCreatorData"
                                class="mt-2 text-xs leading-5 text-stone-500"
                            >
                                Synced from
                                {{
                                    form.creator_challenge.live_sync.page
                                        .name || 'the connected Page'
                                }}
                                over the last
                                {{ creatorChallengeWindowDays }} days.
                                <span v-if="creatorChallengeLimited">
                                    Comments, reactions, and reply windows are
                                    waiting on Meta engagement access.
                                </span>
                                <span
                                    v-if="
                                        form.creator_challenge.live_sync
                                            .last_synced_at
                                    "
                                >
                                    Last sync
                                    {{
                                        new Date(
                                            form.creator_challenge.live_sync
                                                .last_synced_at,
                                        ).toLocaleString()
                                    }}.
                                </span>
                            </p>
                            <p
                                v-else-if="creatorChallengeSyncFailed"
                                class="mt-2 text-xs leading-5 text-rose-700"
                            >
                                CreditSoft could not read the Page feed with the
                                current token. Use Page-read login mode, connect
                                Meta, then sync again.
                            </p>
                            <p
                                v-else
                                class="mt-2 text-xs leading-5 text-stone-500"
                            >
                                No creator challenge metrics are displayed until
                                CreditSoft can sync a real Page feed from Meta.
                            </p>

                            <div
                                v-if="creatorChallengeBlockers.length > 0"
                                class="mt-4 rounded-[22px] border border-amber-200 bg-amber-50 px-4 py-4"
                            >
                                <p class="text-sm font-semibold text-amber-950">
                                    Why CreditSoft cannot detect this yet
                                </p>
                                <ul class="mt-3 space-y-2">
                                    <li
                                        v-for="blocker in creatorChallengeBlockers"
                                        :key="blocker"
                                        class="flex gap-2 text-sm leading-6 text-amber-900"
                                    >
                                        <span
                                            class="mt-2 size-1.5 shrink-0 rounded-full bg-amber-500"
                                        />
                                        <span>{{ blocker }}</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="mt-4 space-y-3">
                                <div
                                    v-for="goal in creatorChallengeGoals"
                                    :key="goal.key"
                                    class="rounded-[22px] border border-stone-200 bg-white px-4 py-4"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-sm font-medium text-stone-900"
                                            >
                                                {{ goal.label }}
                                            </p>
                                            <p
                                                class="mt-1 text-sm text-stone-600"
                                            >
                                                {{
                                                    hasLiveCreatorData
                                                        ? 'Current'
                                                        : 'Detected'
                                                }}
                                                {{ goal.current }} / Goal
                                                {{ goal.target }}
                                            </p>
                                        </div>
                                        <span
                                            class="inline-flex rounded-full border px-3 py-1 text-[10px] font-semibold tracking-[0.16em] uppercase"
                                            :class="
                                                goal.met
                                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                                    : 'border-amber-200 bg-amber-50 text-amber-800'
                                            "
                                        >
                                            {{
                                                goal.met ? 'Met' : 'Needs push'
                                            }}
                                        </span>
                                    </div>
                                    <div
                                        class="mt-3 h-2.5 overflow-hidden rounded-full bg-stone-200"
                                    >
                                        <div
                                            class="h-full rounded-full bg-stone-950 transition-all"
                                            :style="{
                                                width: `${goal.progress}%`,
                                            }"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-[28px] border border-stone-200 bg-white p-5"
                        >
                            <div class="flex items-center gap-3">
                                <FontAwesomeIcon
                                    :icon="faWandMagicSparkles"
                                    class="text-lg text-stone-700"
                                />
                                <div>
                                    <p
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >
                                        AI next moves
                                    </p>
                                    <h3
                                        class="mt-1 text-lg font-semibold text-stone-950"
                                    >
                                        What the office should do next
                                    </h3>
                                </div>
                            </div>

                            <div class="mt-4 space-y-3">
                                <article
                                    v-for="move in creatorChallengeAiMoves"
                                    :key="move"
                                    class="rounded-[22px] border border-stone-200 bg-stone-50 px-4 py-4"
                                >
                                    <p class="text-sm leading-6 text-stone-700">
                                        {{ move }}
                                    </p>
                                </article>
                            </div>
                        </div>

                        <div
                            class="rounded-[28px] border border-stone-200 bg-white p-5"
                        >
                            <div class="flex items-center gap-3">
                                <FontAwesomeIcon
                                    :icon="faListOl"
                                    class="text-lg text-stone-700"
                                />
                                <div>
                                    <p
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >
                                        {{
                                            hasLiveCreatorData
                                                ? 'Live ranking'
                                                : 'No ranking yet'
                                        }}
                                    </p>
                                    <h3
                                        class="mt-1 text-lg font-semibold text-stone-950"
                                    >
                                        {{
                                            hasLiveCreatorData
                                                ? 'Top posts and placements'
                                                : 'Waiting for live Meta data'
                                        }}
                                    </h3>
                                </div>
                            </div>

                            <p class="mt-3 text-xs leading-5 text-stone-500">
                                {{
                                    hasLiveCreatorData
                                        ? 'These placements are based on live post performance inside the sync window, not private dashboard-only signals.'
                                        : 'No winner board is shown until live Meta post data syncs successfully.'
                                }}
                            </p>

                            <div class="mt-4 space-y-3">
                                <div
                                    v-if="
                                        creatorChallengeLeaderboard.length === 0
                                    "
                                    class="rounded-[22px] border border-amber-200 bg-amber-50 px-4 py-4"
                                >
                                    <p
                                        class="text-sm font-semibold text-amber-950"
                                    >
                                        No live ranking yet.
                                    </p>
                                    <p
                                        class="mt-2 text-sm leading-6 text-amber-900"
                                    >
                                        CreditSoft will not show first place,
                                        second place, or a winner board until it
                                        can read real Page posts, comments, and
                                        public share counts from Meta. Fix the
                                        blockers above, then sync live data.
                                    </p>
                                </div>
                                <article
                                    v-for="entrant in creatorChallengeLeaderboard"
                                    :key="entrant.id"
                                    class="rounded-[22px] border border-stone-200 bg-stone-50 px-4 py-4"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div>
                                            <div
                                                class="flex flex-wrap items-center gap-2"
                                            >
                                                <span
                                                    class="inline-flex size-8 items-center justify-center rounded-full bg-stone-950 text-xs font-semibold text-white"
                                                    >{{ entrant.rank }}</span
                                                >
                                                <p
                                                    class="text-sm font-medium text-stone-900"
                                                >
                                                    {{ entrant.name }}
                                                </p>
                                                <span
                                                    class="text-xs tracking-[0.16em] text-stone-500 uppercase"
                                                    >{{ entrant.role }}</span
                                                >
                                            </div>
                                            <p
                                                class="mt-2 text-sm text-stone-700"
                                            >
                                                {{ entrant.placementLabel }}
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p
                                                class="text-lg font-semibold text-stone-950"
                                            >
                                                {{ entrant.score }}
                                            </p>
                                            <p
                                                class="text-[11px] tracking-[0.18em] text-stone-500 uppercase"
                                            >
                                                points
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="mt-4 grid gap-2 text-[11px] font-semibold tracking-[0.14em] text-stone-600 uppercase sm:grid-cols-4"
                                    >
                                        <div
                                            class="rounded-2xl border border-stone-200 bg-white px-3 py-2"
                                        >
                                            Comments {{ entrant.comments }}
                                        </div>
                                        <div
                                            class="rounded-2xl border border-stone-200 bg-white px-3 py-2"
                                        >
                                            Shares {{ entrant.publicShares }}
                                        </div>
                                        <div
                                            class="rounded-2xl border border-stone-200 bg-white px-3 py-2"
                                        >
                                            Posts {{ entrant.posts }}
                                        </div>
                                        <div
                                            class="rounded-2xl border border-stone-200 bg-white px-3 py-2"
                                        >
                                            Like bonus
                                            {{ entrant.likeBonusHits }}
                                        </div>
                                    </div>

                                    <p
                                        class="mt-3 text-sm leading-6 text-stone-600"
                                    >
                                        {{ entrant.lastMove }}
                                    </p>
                                </article>
                            </div>
                        </div>

                        <div
                            class="rounded-[28px] border border-sky-200 bg-sky-50 px-5 py-5 text-sm leading-6 text-sky-950"
                        >
                            <p class="font-medium">Policy-safe scoring model</p>
                            <p class="mt-2">
                                This lane stays on what the office can actually
                                verify: public comments, public shares,
                                publishing cadence, liked replies, and leads
                                routed back to the office. It avoids
                                private-share guessing and hidden engagement
                                claims Meta does not expose.
                            </p>
                            <p class="mt-2">
                                Contest automation should stay off prohibited
                                promotion mechanics like “share on your Timeline
                                to enter”, “share on a friend&rsquo;s Timeline
                                to enter”, or “tag your friends to enter”. Keep
                                it on official rules, required disclosures,
                                visible engagement, and clean winner logic that
                                CreditSoft can actually audit.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <div
                v-if="
                    isSocialSection('facebook') ||
                    isSocialSection('whatsapp')
                "
                id="social-settings"
                class="grid gap-4"
            >
                <section
                    v-if="isSocialSection('facebook')"
                    id="social-meta-business"
                    class="overflow-hidden rounded-[24px] border border-stone-200 bg-white/95 shadow-[0_12px_30px_rgba(15,23,42,0.04)]"
                >
                    <div class="border-b border-stone-200/80 px-5 py-4">
                        <p
                            class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >
                            Meta business identity
                        </p>
                        <h2 class="mt-2 text-lg font-semibold text-stone-950">
                            Connect the right app, business, and callback
                        </h2>
                        <p
                            class="mt-2 max-w-3xl text-sm leading-6 text-stone-600"
                        >
                            Keep the business login credentials and callback
                            lane clean so Page sync, ads, and WhatsApp all point
                            at the same office setup.
                        </p>
                    </div>

                    <div class="space-y-4 px-5 py-5">
                        <div
                            class="rounded-[20px] border border-sky-200 bg-[linear-gradient(135deg,_rgba(14,165,233,0.12),_rgba(255,255,255,0.94))] px-4 py-4"
                        >
                            <div
                                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                            >
                                <div class="max-w-3xl">
                                    <p
                                        class="text-[11px] font-medium tracking-[0.24em] text-sky-800 uppercase"
                                    >
                                        Website admin import
                                    </p>
                                    <h3
                                        class="mt-2 text-base font-semibold text-stone-950"
                                    >
                                        Pull the saved Meta footprint from
                                        admin.creditsoft.app.
                                    </h3>
                                    <p
                                        class="mt-2 text-sm leading-6 text-stone-700"
                                    >
                                        Import the website-side Meta signals
                                        that are already saved in admin: Pixel
                                        ID, Page ID, management or CAPI token,
                                        webhook verify token, ad defaults, and
                                        WhatsApp numbers. Blank website fields
                                        do not wipe out office values.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-2xl border border-sky-300 bg-white px-4 py-2.5 text-sm font-medium text-sky-950 transition hover:border-sky-500 disabled:cursor-not-allowed disabled:opacity-70"
                                    :disabled="importForm.processing"
                                    @click="importWebsiteTracking"
                                >
                                    <FontAwesomeIcon :icon="faLink" />
                                    {{
                                        importForm.processing
                                            ? 'Importing…'
                                            : 'Import from website admin'
                                    }}
                                </button>
                            </div>

                            <div
                                class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-4"
                            >
                                <div
                                    class="rounded-[16px] border border-white/80 bg-white/90 px-3 py-3"
                                >
                                    <p
                                        class="text-[11px] font-medium tracking-[0.18em] text-stone-500 uppercase"
                                    >
                                        Meta Pixel
                                    </p>
                                    <p
                                        class="mt-2 text-sm font-medium text-stone-950"
                                    >
                                        {{
                                            form.website_signals
                                                .meta_pixel_id || 'Not imported'
                                        }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs leading-5 text-stone-500"
                                    >
                                        Browser and CAPI event anchor from the
                                        website lane.
                                    </p>
                                </div>
                                <div
                                    class="rounded-[16px] border border-white/80 bg-white/90 px-3 py-3"
                                >
                                    <p
                                        class="text-[11px] font-medium tracking-[0.18em] text-stone-500 uppercase"
                                    >
                                        Facebook Page
                                    </p>
                                    <p
                                        class="mt-2 text-sm font-medium text-stone-950"
                                    >
                                        {{
                                            form.website_signals
                                                .facebook_page_id ||
                                            'Not imported'
                                        }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs leading-5 text-stone-500"
                                    >
                                        Feeds the default Page and imported Page
                                        picker entry.
                                    </p>
                                </div>
                                <div
                                    class="rounded-[16px] border border-white/80 bg-white/90 px-3 py-3"
                                >
                                    <p
                                        class="text-[11px] font-medium tracking-[0.18em] text-stone-500 uppercase"
                                    >
                                        Token lane
                                    </p>
                                    <p
                                        class="mt-2 text-sm font-medium text-stone-950"
                                    >
                                        {{
                                            form.website_signals
                                                .meta_management_token
                                                ? `Mgmt ${maskSecret(form.website_signals.meta_management_token)}`
                                                : form.website_signals
                                                        .meta_capi_token
                                                  ? `CAPI ${maskSecret(form.website_signals.meta_capi_token)}`
                                                  : 'Not imported'
                                        }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs leading-5 text-stone-500"
                                    >
                                        Management token wins. CAPI token
                                        becomes the fallback import.
                                    </p>
                                </div>
                                <div
                                    class="rounded-[16px] border border-white/80 bg-white/90 px-3 py-3"
                                >
                                    <p
                                        class="text-[11px] font-medium tracking-[0.18em] text-stone-500 uppercase"
                                    >
                                        Last imported
                                    </p>
                                    <p
                                        class="mt-2 text-sm font-medium text-stone-950"
                                    >
                                        {{
                                            form.website_signals.imported_at
                                                ? new Date(
                                                      form.website_signals
                                                          .imported_at,
                                                  ).toLocaleString()
                                                : 'Not imported yet'
                                        }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs leading-5 text-stone-500"
                                    >
                                        {{
                                            form.website_signals.source_path ||
                                            'Waiting for website admin tracking source.'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="mt-2 grid gap-2 md:grid-cols-2 xl:grid-cols-4"
                            >
                                <div
                                    class="rounded-[16px] border border-stone-200 bg-white px-3 py-3"
                                >
                                    <p
                                        class="text-[11px] font-medium tracking-[0.18em] text-stone-500 uppercase"
                                    >
                                        Webhook verify token
                                    </p>
                                    <p
                                        class="mt-2 text-sm font-medium text-stone-950"
                                    >
                                        {{
                                            maskSecret(
                                                form.website_signals
                                                    .meta_webhook_verify_token,
                                                3,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-[16px] border border-stone-200 bg-white px-3 py-3"
                                >
                                    <p
                                        class="text-[11px] font-medium tracking-[0.18em] text-stone-500 uppercase"
                                    >
                                        Ad account default
                                    </p>
                                    <p
                                        class="mt-2 text-sm font-medium text-stone-950"
                                    >
                                        {{
                                            form.website_signals
                                                .meta_ad_account_id ||
                                            'Not imported'
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-[16px] border border-stone-200 bg-white px-3 py-3"
                                >
                                    <p
                                        class="text-[11px] font-medium tracking-[0.18em] text-stone-500 uppercase"
                                    >
                                        Campaign objective
                                    </p>
                                    <p
                                        class="mt-2 text-sm font-medium text-stone-950"
                                    >
                                        {{
                                            form.website_signals
                                                .campaign_objective ||
                                            'OUTCOME_LEADS'
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-[16px] border border-stone-200 bg-white px-3 py-3"
                                >
                                    <p
                                        class="text-[11px] font-medium tracking-[0.18em] text-stone-500 uppercase"
                                    >
                                        Import state
                                    </p>
                                    <p
                                        class="mt-2 text-sm font-medium text-stone-950"
                                    >
                                        {{
                                            websiteSignalsReady
                                                ? 'Website admin snapshot loaded'
                                                : 'No imported website signals yet'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="space-y-2">
                                <span
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >Meta App ID</span
                                >
                                <input
                                    v-model="form.meta.app_id"
                                    type="text"
                                    class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                    placeholder="123456789012345"
                                />
                            </label>
                            <label class="space-y-2">
                                <span
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >Meta App Secret</span
                                >
                                <input
                                    v-model="form.meta.app_secret"
                                    type="password"
                                    class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                    placeholder="Meta app secret"
                                />
                            </label>
                            <label class="space-y-2">
                                <span
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >Business Login Config ID</span
                                >
                                <input
                                    v-model="form.meta.business_login_config_id"
                                    type="text"
                                    class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                    placeholder="Required for Facebook Login for Business"
                                />
                                <span class="block text-xs leading-5 text-stone-500">
                                    Add the Configuration ID from Meta's Business Login settings. CreditSoft will use config_id instead of raw scopes, which is the flow Meta expects when Page assets are granted through Business Login.
                                </span>
                            </label>
                            <label class="space-y-2">
                                <span
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >Business Manager ID</span
                                >
                                <input
                                    v-model="form.meta.business_id"
                                    type="text"
                                    class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                    placeholder="Meta business ID"
                                />
                            </label>
                            <label class="space-y-2">
                                <span
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >System User ID</span
                                >
                                <input
                                    v-model="form.meta.system_user_id"
                                    type="text"
                                    class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                    placeholder="Optional Meta system user"
                                />
                            </label>
                        </div>

                        <div
                            class="overflow-hidden rounded-[26px] border border-stone-200 bg-stone-950 text-white"
                        >
                            <div
                                class="grid gap-4 px-5 py-5 lg:grid-cols-[0.95fr_1.05fr] lg:items-start"
                            >
                                <div>
                                    <p
                                        class="text-[11px] font-medium tracking-[0.24em] text-sky-200 uppercase"
                                    >
                                        Meta setup launcher
                                    </p>
                                    <h3 class="mt-2 text-lg font-semibold">
                                        Open the exact Meta app pages.
                                    </h3>
                                    <p
                                        class="mt-2 text-sm leading-6 text-stone-300"
                                    >
                                        {{ metaSetupSourceLabel }}
                                    </p>
                                    <p
                                        class="mt-3 rounded-2xl border border-white/10 bg-white/8 px-4 py-3 text-xs leading-5 break-all text-stone-200"
                                    >
                                        https://developers.facebook.com/apps/{{
                                            cleanMetaAppId || 'APP_ID'
                                        }}/business-login/settings/{{
                                            cleanMetaBusinessId
                                                ? `?business_id=${cleanMetaBusinessId}`
                                                : '?business_id=BUSINESS_ID'
                                        }}
                                    </p>
                                </div>

                                <div class="space-y-3">
                                    <div
                                        class="rounded-[18px] border border-emerald-300/30 bg-emerald-300/10 px-3 py-3"
                                    >
                                        <p
                                            class="text-[11px] font-medium tracking-[0.22em] text-emerald-100 uppercase"
                                        >
                                            Business Login configuration wizard
                                        </p>
                                        <p
                                            class="mt-2 text-xs leading-5 text-emerald-50"
                                        >
                                            Your screenshot shows there are no
                                            configurations yet. Create one with
                                            these choices, then paste the
                                            Configuration ID back into CreditSoft.
                                        </p>
                                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                            <div
                                                v-for="item in metaBusinessLoginWizardSteps"
                                                :key="item.step"
                                                class="rounded-[14px] border border-white/10 bg-black/20 px-3 py-2.5"
                                            >
                                                <div
                                                    class="flex flex-col gap-1"
                                                >
                                                    <p
                                                        class="text-xs font-medium tracking-[0.16em] text-emerald-100 uppercase"
                                                    >
                                                        {{ item.step }}
                                                    </p>
                                                    <p
                                                        class="text-sm font-semibold leading-5 text-white"
                                                    >
                                                        {{ item.value }}
                                                    </p>
                                                </div>
                                                <p
                                                    class="mt-1 text-xs leading-5 text-stone-300"
                                                >
                                                    {{ item.help }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-[22px] border border-white/10 bg-white/8 px-4 py-4"
                                    >
                                        <div
                                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                                        >
                                            <div class="min-w-0">
                                                <p
                                                    class="text-[11px] font-medium tracking-[0.22em] text-stone-400 uppercase"
                                                >
                                                    Redirect URI to check
                                                </p>
                                                <p
                                                    class="mt-2 text-sm leading-6 break-all text-white"
                                                >
                                                    {{
                                                        props.meta.callback_url
                                                    }}
                                                </p>
                                                <p
                                                    v-if="
                                                        props.meta
                                                            .api_callback_url !==
                                                        props.meta.callback_url
                                                    "
                                                    class="mt-2 text-xs leading-5 text-stone-300"
                                                >
                                                    Public OAuth alias forwards
                                                    to
                                                    <span
                                                        class="break-all text-stone-100"
                                                        >{{
                                                            props.meta
                                                                .api_callback_url
                                                        }}</span
                                                    >.
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-2xl border border-white/15 bg-white px-4 py-2.5 text-sm font-medium text-stone-950 transition hover:bg-sky-50"
                                                @click="copyCallbackUrl"
                                            >
                                                <FontAwesomeIcon
                                                    :icon="
                                                        copiedCallback
                                                            ? faCircleCheck
                                                            : faCopy
                                                    "
                                                />
                                                {{
                                                    copiedCallback
                                                        ? 'Copied'
                                                        : 'Copy'
                                                }}
                                            </button>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-[22px] border border-white/10 bg-white/8 px-4 py-4"
                                    >
                                        <p
                                            class="text-[11px] font-medium tracking-[0.22em] text-stone-400 uppercase"
                                        >
                                            If Meta still says invalid
                                        </p>
                                        <div class="mt-3 space-y-3">
                                            <div>
                                                <p
                                                    class="text-sm font-medium text-white"
                                                >
                                                    Basic app settings
                                                </p>
                                                <p
                                                    class="mt-1 text-xs leading-5 text-stone-300"
                                                >
                                                    Add both App Domains:
                                                    <span
                                                        class="font-medium text-white"
                                                        >creditsoft.app</span
                                                    >
                                                    and
                                                    <span
                                                        class="font-medium text-white"
                                                        >www.creditsoft.app</span
                                                    >.
                                                </p>
                                            </div>
                                            <div>
                                                <p
                                                    class="text-sm font-medium text-white"
                                                >
                                                    Business Login settings
                                                </p>
                                                <p
                                                    class="mt-1 text-xs leading-5 text-stone-300"
                                                >
                                                    The top Redirect URI to
                                                    check box is only a tester.
                                                    Scroll down to Valid OAuth
                                                    Redirect URIs, paste the
                                                    exact callback into that
                                                    saved list, press Enter if
                                                    Meta creates a tag, then
                                                    save changes. Add both
                                                    variants while testing so
                                                    www vs non-www cannot block
                                                    the app.
                                                </p>
                                                <div class="mt-2 space-y-2">
                                                    <p
                                                        v-for="variant in callbackUrlVariants"
                                                        :key="variant"
                                                        class="rounded-2xl border border-white/10 bg-black/20 px-3 py-2 text-xs leading-5 break-all text-stone-100"
                                                    >
                                                        {{ variant }}
                                                    </p>
                                                </div>
                                            </div>
                                            <p
                                                class="text-xs leading-5 text-stone-300"
                                            >
                                                Then save the Meta settings,
                                                reload that Meta page, and run
                                                Redirect URI to check again.
                                                CreditSoft has already verified
                                                the bridge endpoint is live.
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-[22px] border border-white/10 bg-white/8 px-4 py-4"
                                    >
                                        <p
                                            class="text-[11px] font-medium tracking-[0.22em] text-stone-400 uppercase"
                                        >
                                            Fields on this Meta screen
                                        </p>
                                        <div class="mt-3 space-y-3">
                                            <div
                                                v-for="field in metaDashboardFields"
                                                :key="field.key"
                                                class="rounded-[18px] border border-white/10 bg-black/15 px-3 py-3"
                                            >
                                                <div
                                                    class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                                                >
                                                    <div class="min-w-0">
                                                        <p
                                                            class="text-sm font-medium text-white"
                                                        >
                                                            {{ field.label }}
                                                        </p>
                                                        <p
                                                            class="mt-1 text-xs leading-5 text-stone-300"
                                                        >
                                                            {{ field.help }}
                                                        </p>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="inline-flex shrink-0 items-center justify-center gap-2 rounded-2xl border border-white/15 bg-white px-3 py-2 text-xs font-medium text-stone-950 transition hover:bg-sky-50"
                                                        @click="
                                                            copySetupValue(
                                                                field.values.join(
                                                                    '\n',
                                                                ),
                                                                field.key,
                                                            )
                                                        "
                                                    >
                                                        <FontAwesomeIcon
                                                            :icon="
                                                                copiedSetupValue ===
                                                                field.key
                                                                    ? faCircleCheck
                                                                    : faCopy
                                                            "
                                                        />
                                                        {{
                                                            copiedSetupValue ===
                                                            field.key
                                                                ? 'Copied'
                                                                : 'Copy'
                                                        }}
                                                    </button>
                                                </div>
                                                <div class="mt-2 space-y-2">
                                                    <p
                                                        v-for="value in field.values"
                                                        :key="value"
                                                        class="rounded-2xl border border-white/10 bg-black/25 px-3 py-2 text-xs leading-5 break-all text-stone-100"
                                                    >
                                                        {{ value }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-if="metaSetupLinks.length > 0"
                                        class="grid gap-2 sm:grid-cols-2"
                                    >
                                        <a
                                            v-for="link in metaSetupLinks"
                                            :key="link.href"
                                            :href="link.href"
                                            target="_blank"
                                            rel="noreferrer"
                                            class="group rounded-[20px] border px-4 py-4 transition"
                                            :class="
                                                link.primary
                                                    ? 'border-sky-300 bg-sky-300 text-stone-950 hover:bg-sky-200'
                                                    : 'border-white/10 bg-white/8 text-white hover:border-white/25 hover:bg-white/12'
                                            "
                                        >
                                            <span
                                                class="flex items-start justify-between gap-3"
                                            >
                                                <span
                                                    class="text-sm font-semibold"
                                                    >{{ link.label }}</span
                                                >
                                                <FontAwesomeIcon
                                                    :icon="
                                                        faArrowUpRightFromSquare
                                                    "
                                                    class="mt-0.5 shrink-0 text-xs opacity-70 transition group-hover:opacity-100"
                                                />
                                            </span>
                                            <span
                                                class="mt-2 block text-xs leading-5"
                                                :class="
                                                    link.primary
                                                        ? 'text-stone-800'
                                                        : 'text-stone-300'
                                                "
                                            >
                                                {{ link.description }}
                                            </span>
                                        </a>
                                    </div>

                                    <div
                                        v-else
                                        class="rounded-[20px] border border-amber-300/30 bg-amber-300/10 px-4 py-4 text-sm leading-6 text-amber-50"
                                    >
                                        <p>
                                            Start at Meta Apps, then click the
                                            app card that shows the App ID, or
                                            use Create App in the upper-right
                                            corner. Once CreditSoft has that App
                                            ID, this launcher opens the exact
                                            Business Login settings page instead
                                            of making someone search through
                                            Meta.
                                        </p>
                                        <a
                                            href="https://developers.facebook.com/apps/"
                                            target="_blank"
                                            rel="noreferrer"
                                            class="mt-3 inline-flex items-center gap-2 rounded-2xl border border-amber-100/30 bg-white px-4 py-2.5 text-sm font-medium text-stone-950 transition hover:bg-amber-50"
                                        >
                                            <FontAwesomeIcon
                                                :icon="faArrowUpRightFromSquare"
                                            />
                                            Open Meta Apps
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-[1.05fr_0.95fr]">
                            <div
                                class="rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-3"
                                >
                                    <p
                                        class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >
                                        Callback mode
                                    </p>
                                    <span
                                        class="inline-flex rounded-full border px-3 py-1 text-[11px] font-semibold tracking-[0.18em] uppercase"
                                        :class="
                                            props.meta.callback_mode === 'local'
                                                ? 'border-amber-200 bg-amber-50 text-amber-800'
                                                : 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                        "
                                    >
                                        {{
                                            props.meta.callback_mode ===
                                            'api_domain'
                                                ? 'Stable API callback'
                                                : props.meta.callback_mode ===
                                                    'public'
                                                  ? 'Public ngrok callback'
                                                  : 'Local callback'
                                        }}
                                    </span>
                                </div>
                                <p
                                    class="mt-3 text-sm leading-6 text-stone-900"
                                >
                                    {{
                                        props.meta.callback_mode ===
                                        'api_domain'
                                            ? 'Meta returns through the saved website bridge first, so the callback does not depend on a rotating ngrok hostname.'
                                            : props.meta
                                                    .configured_callback_status
                                                    .state === 'unreachable'
                                              ? 'A saved website bridge exists, but CreditSoft could not reach the public bridge there yet. Meta is staying on the fallback lane until that host actually forwards into this office.'
                                              : props.meta.callback_mode ===
                                                  'public'
                                                ? 'Meta can return to the live ngrok callback lane.'
                                                : 'Meta will still return to localhost until the public callback lane is live.'
                                    }}
                                </p>
                                <a
                                    v-if="props.meta.callback_mode === 'local'"
                                    href="/settings/connectivity"
                                    class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-stone-900 underline decoration-stone-300 underline-offset-4 transition hover:decoration-stone-900"
                                >
                                    Open Connectivity
                                </a>
                            </div>

                            <div
                                class="rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4"
                            >
                                <p
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >
                                    Requested scopes
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span
                                        v-for="scope in props.meta.scopes"
                                        :key="scope"
                                        class="inline-flex rounded-xl border border-stone-300 bg-white px-3 py-2 text-[11px] font-semibold tracking-[0.12em] text-stone-700 uppercase"
                                    >
                                        {{ scope }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-[24px] border border-amber-200 bg-amber-50 px-5 py-5"
                        >
                            <div
                                class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
                            >
                                <div class="max-w-3xl">
                                    <p
                                        class="text-[11px] font-medium tracking-[0.24em] text-amber-800 uppercase"
                                    >
                                        Meta review staging
                                    </p>
                                    <h3
                                        class="mt-2 text-base font-semibold text-stone-950"
                                    >
                                        Keep first login clean, then enable the
                                        heavier lanes after Page sync works.
                                    </h3>
                                    <p
                                        class="mt-2 text-sm leading-6 text-amber-950"
                                    >
                                        Basic connection should only ask for Page
                                        list, metadata, and Page engagement.
                                        Publishing, ads, Instagram, and WhatsApp
                                        are staged by their own toggles so Meta
                                        does not reject the login before the Page
                                        token is proven.
                                    </p>
                                </div>
                                <span
                                    class="inline-flex shrink-0 rounded-full border px-3 py-1.5 text-[11px] font-semibold tracking-[0.18em] uppercase"
                                    :class="
                                        enabledScopeLanes.length > 0
                                            ? 'border-amber-300 bg-white text-amber-900'
                                            : 'border-emerald-200 bg-white text-emerald-800'
                                    "
                                >
                                    {{
                                        enabledScopeLanes.length > 0
                                            ? 'Extra scopes staged'
                                            : 'Clean login staged'
                                    }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-amber-950">
                                The creator challenge now uses the connected
                                Page token and live sync result. If Meta blocks
                                the feed or comments, the board stays blocked
                                and explains the exact Graph error instead of
                                showing estimated winners.
                            </p>
                        </div>

                        <details
                            class="group rounded-[20px] border border-sky-200 bg-sky-50 px-4 py-4"
                        >
                            <summary
                                class="flex cursor-pointer list-none flex-col gap-3 lg:flex-row lg:items-start lg:justify-between [&::-webkit-details-marker]:hidden"
                            >
                                <div>
                                    <p
                                        class="text-[11px] font-medium tracking-[0.24em] text-sky-800 uppercase"
                                    >
                                        Meta setup checklist
                                    </p>
                                    <h3
                                        class="mt-2 text-base font-semibold text-stone-950"
                                    >
                                        Use the website bridge URL in Meta, not
                                        localhost.
                                    </h3>
                                    <p
                                        class="mt-2 max-w-3xl text-sm leading-6 text-sky-950"
                                    >
                                        If this still shows
                                        <span class="font-medium text-stone-950"
                                            >127.0.0.1</span
                                        >, the website bridge is not live or
                                        CreditSoft cannot verify it yet. Meta
                                        should get a stable OAuth callback like
                                        <span class="font-medium text-stone-950"
                                            >https://yourdomain.com/oauth.php</span
                                        >. That public file relays to the
                                        current ngrok or office API target, so a
                                        rotated ngrok hostname does not break
                                        the Meta app setting.
                                    </p>
                                </div>
                                <span
                                    class="inline-flex shrink-0 items-center justify-center rounded-2xl border border-sky-300 bg-white px-4 py-2.5 text-sm font-medium text-sky-950 transition group-open:border-sky-500"
                                >
                                    <span class="group-open:hidden"
                                        >Open checklist</span
                                    >
                                    <span class="hidden group-open:inline"
                                        >Hide checklist</span
                                    >
                                </span>
                            </summary>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <a
                                    href="/settings/api"
                                    class="inline-flex items-center justify-center rounded-xl border border-sky-300 bg-white px-3 py-2 text-sm font-medium text-sky-950 transition hover:border-sky-500"
                                >
                                    Open API bridge setup
                                </a>
                            </div>

                            <div class="mt-4 grid gap-2 lg:grid-cols-3">
                                <div
                                    class="rounded-[16px] border border-white/80 bg-white/90 px-3 py-3"
                                >
                                    <p
                                        class="text-sm font-medium text-stone-950"
                                    >
                                        1. In Meta, add Facebook Login or
                                        Business Login.
                                    </p>
                                    <p
                                        class="mt-2 text-sm leading-6 text-stone-600"
                                    >
                                        This is the product inside your Meta app
                                        that owns the OAuth redirect/callback
                                        setting.
                                    </p>
                                </div>
                                <div
                                    class="rounded-[16px] border border-white/80 bg-white/90 px-3 py-3"
                                >
                                    <p
                                        class="text-sm font-medium text-stone-950"
                                    >
                                        2. Paste the exact website callback.
                                    </p>
                                    <p
                                        class="mt-2 text-sm leading-6 text-stone-600"
                                    >
                                        Use the customer domain callback in
                                        Meta's Valid OAuth redirect URIs. If
                                        this value is still localhost, do not
                                        paste it into Meta yet.
                                    </p>
                                    <p
                                        class="mt-2 text-sm leading-6 font-medium break-all text-stone-950"
                                    >
                                        {{ props.meta.callback_url }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-[16px] border border-white/80 bg-white/90 px-3 py-3"
                                >
                                    <p
                                        class="text-sm font-medium text-stone-950"
                                    >
                                        3. Confirm the bridge target.
                                    </p>
                                    <p
                                        class="mt-2 text-sm leading-6 text-stone-600"
                                    >
                                        The website bridge should forward
                                        /api/v1 traffic to the current office
                                        API base, usually the live ngrok /api/v1
                                        URL saved on API settings.
                                    </p>
                                </div>
                                <div
                                    class="rounded-[16px] border border-white/80 bg-white/90 px-3 py-3"
                                >
                                    <p
                                        class="text-sm font-medium text-stone-950"
                                    >
                                        4. Connect with a real Page admin.
                                    </p>
                                    <p
                                        class="mt-2 text-sm leading-6 text-stone-600"
                                    >
                                        Click Connect while switched to the
                                        personal Facebook profile that has full
                                        Page control and access to the Business
                                        portfolio that owns the assets.
                                    </p>
                                </div>
                                <div
                                    class="rounded-[16px] border border-white/80 bg-white/90 px-3 py-3"
                                >
                                    <p
                                        class="text-sm font-medium text-stone-950"
                                    >
                                        5. If the app is in development mode,
                                        add the user as an app role.
                                    </p>
                                    <p
                                        class="mt-2 text-sm leading-6 text-stone-600"
                                    >
                                        Until the app is live and reviewed, Meta
                                        usually limits testing to admins,
                                        developers, testers, and test users
                                        attached to that app.
                                    </p>
                                </div>
                                <div
                                    class="rounded-[16px] border border-white/80 bg-white/90 px-3 py-3"
                                >
                                    <p
                                        class="text-sm font-medium text-stone-950"
                                    >
                                        6. If Meta returns zero Pages, repair the grant.
                                    </p>
                                    <p
                                        class="mt-2 text-sm leading-6 text-stone-600"
                                    >
                                        First switch Facebook back to the same
                                        personal profile that clicked Connect
                                        Meta, not the CreditSoft.app Page
                                        profile. If Facebook already removed
                                        CreditSoft but Page list is still zero,
                                        save the Business Login Configuration ID
                                        above first. Then reconnect, select the
                                        CreditSoft.app Page, click Save, and
                                        finish the Meta flow.
                                    </p>
                                    <a
                                        :href="metaBusinessToolsUrl"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-stone-950 underline decoration-stone-300 underline-offset-4 transition hover:decoration-stone-950"
                                    >
                                        <FontAwesomeIcon
                                            :icon="faArrowUpRightFromSquare"
                                        />
                                        Open Facebook Business Tools
                                    </a>
                                </div>
                                <div
                                    class="rounded-[16px] border border-white/80 bg-white/90 px-3 py-3"
                                >
                                    <p
                                        class="text-sm font-medium text-stone-950"
                                    >
                                        7. Request only the staged scopes shown
                                        here.
                                    </p>
                                    <p
                                        class="mt-2 text-sm leading-6 text-stone-600"
                                    >
                                        Without a Business Login Configuration
                                        ID, CreditSoft falls back to raw Page
                                        scopes. With a Configuration ID saved,
                                        CreditSoft sends config_id instead of
                                        raw scopes so Meta can attach the Page
                                        asset to the login.
                                    </p>
                                </div>
                                <div
                                    class="rounded-[16px] border border-white/80 bg-white/90 px-3 py-3"
                                >
                                    <p
                                        class="text-sm font-medium text-stone-950"
                                    >
                                        8. Prepare App Review evidence by lane.
                                    </p>
                                    <p
                                        class="mt-2 text-sm leading-6 text-stone-600"
                                    >
                                        Show Meta the exact screens: Page
                                        selection, post/comment sync, publishing
                                        queue, ad account read, lead routing,
                                        Instagram publish, or WhatsApp
                                        follow-up.
                                    </p>
                                </div>
                                <div
                                    class="rounded-[16px] border border-white/80 bg-white/90 px-3 py-3"
                                >
                                    <p
                                        class="text-sm font-medium text-stone-950"
                                    >
                                        9. Reconnect after approval or scope
                                        changes.
                                    </p>
                                    <p
                                        class="mt-2 text-sm leading-6 text-stone-600"
                                    >
                                        When new permissions are approved,
                                        reconnect Meta from this screen so
                                        CreditSoft stores a token that actually
                                        includes those new grants.
                                    </p>
                                </div>
                            </div>
                        </details>
                    </div>
                </section>

                <section
                    v-if="isSocialSection('whatsapp')"
                    id="social-whatsapp"
                    class="overflow-hidden rounded-[24px] border border-stone-200 bg-white/95 shadow-[0_12px_30px_rgba(15,23,42,0.04)]"
                >
                    <div class="border-b border-stone-200/80 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <SocialPlatformMark
                                brand="whatsapp"
                                large
                                monochrome
                                class="shrink-0 text-[#25d366]"
                            />
                            <div>
                                <p
                                    class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                                >
                                    WhatsApp support
                                </p>
                                <h2
                                    class="mt-2 text-lg font-semibold text-stone-950"
                                >
                                    Add a direct message lane for warmer leads
                                </h2>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 px-5 py-5">
                        <div
                            class="grid gap-4 lg:grid-cols-[minmax(0,1.35fr)_minmax(260px,0.65fr)]"
                        >
                            <div
                                class="rounded-[18px] border border-stone-200 bg-stone-50 px-4 py-4"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p
                                            class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                        >
                                            WhatsApp connection
                                        </p>
                                        <h3
                                            class="mt-2 text-base font-semibold text-stone-950"
                                        >
                                            {{ whatsappStatusLabel }}
                                        </h3>
                                    </div>
                                    <span
                                        class="inline-flex rounded-full border px-3 py-1.5 text-[11px] font-semibold tracking-[0.14em] uppercase"
                                        :class="whatsappStatusChipClass"
                                    >
                                        {{ whatsappStatusLabel }}
                                    </span>
                                </div>
                                <p
                                    class="mt-3 text-sm leading-6 text-stone-600"
                                >
                                    {{ whatsappSummary }}
                                </p>
                                <p
                                    class="mt-2 text-sm leading-6 text-stone-500"
                                >
                                    {{ whatsappNumberStrategyLabel }}
                                </p>
                            </div>

                            <div
                                class="rounded-[18px] border border-stone-200 bg-white px-4 py-4"
                            >
                                <button
                                    type="button"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-stone-950 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-70"
                                    :disabled="
                                        whatsappSyncForm.processing ||
                                        !metaConnected
                                    "
                                    @click="syncWhatsAppAssets"
                                >
                                    <FontAwesomeIcon :icon="faRotate" />
                                    {{
                                        whatsappSyncForm.processing
                                            ? 'Syncing…'
                                            : 'Sync WhatsApp assets'
                                    }}
                                </button>
                                <p
                                    class="mt-3 text-sm leading-6 text-stone-600"
                                >
                                    Uses the saved Meta business login token.
                                    CreditSoft never shows the access token on
                                    this screen.
                                </p>
                                <p
                                    v-if="form.whatsapp.last_synced_at"
                                    class="mt-2 text-xs font-medium text-stone-500"
                                >
                                    Last synced:
                                    {{
                                        new Date(
                                            form.whatsapp.last_synced_at,
                                        ).toLocaleString()
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="form.whatsapp.last_error"
                            class="flex items-start gap-3 rounded-[18px] border border-amber-200 bg-amber-50 px-4 py-4 text-sm leading-6 text-amber-950"
                        >
                            <FontAwesomeIcon
                                :icon="faTriangleExclamation"
                                class="mt-1"
                            />
                            <span>{{ form.whatsapp.last_error }}</span>
                        </div>

                        <div
                            v-if="whatsappLooksLikeTestNumber"
                            class="rounded-[18px] border border-amber-200 bg-amber-50 px-4 py-4 text-sm leading-6 text-amber-950"
                        >
                            <p class="font-semibold">
                                This is still the Meta test-number lane.
                            </p>
                            <p>
                                The detected +1 555 / Test Number asset is good
                                for a smoke test curl, but it is not the
                                CreditSoft production WhatsApp identity. Do not
                                enable customer follow-up until a real
                                WhatsApp Business Platform number is selected.
                            </p>
                        </div>

                        <div
                            class="grid gap-4 md:grid-cols-3"
                        >
                            <label
                                class="rounded-[18px] border border-stone-200 bg-white px-4 py-4"
                            >
                                <span
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >Number plan</span
                                >
                                <select
                                    v-model="form.whatsapp.number_strategy"
                                    class="mt-3 w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                >
                                    <option value="new_number">
                                        New CreditSoft production number
                                    </option>
                                    <option value="business_app_coexistence">
                                        Existing Business app number with
                                        coexistence
                                    </option>
                                    <option value="migrate_existing_app">
                                        Migrate existing Business app number
                                    </option>
                                </select>
                            </label>

                            <div
                                class="rounded-[18px] border border-stone-200 bg-white px-4 py-4"
                            >
                                <p
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >
                                    WABA options
                                </p>
                                <p
                                    class="mt-3 text-2xl font-semibold text-stone-950"
                                >
                                    {{
                                        form.whatsapp
                                            .available_business_accounts.length
                                    }}
                                </p>
                                <p class="mt-1 text-sm text-stone-600">
                                    WhatsApp Business Accounts returned by
                                    Meta.
                                </p>
                            </div>

                            <div
                                class="rounded-[18px] border border-stone-200 bg-white px-4 py-4"
                            >
                                <p
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >
                                    Phone options
                                </p>
                                <p
                                    class="mt-3 text-2xl font-semibold text-stone-950"
                                >
                                    {{
                                        form.whatsapp
                                            .available_phone_numbers.length
                                    }}
                                </p>
                                <p class="mt-1 text-sm text-stone-600">
                                    Cloud API phone numbers returned by Meta.
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="form.whatsapp.number_strategy !== 'new_number'"
                            class="rounded-[18px] border border-sky-200 bg-sky-50 px-4 py-4 text-sm leading-6 text-sky-950"
                        >
                            <p class="font-semibold">
                                Repurposing an existing WhatsApp Business app
                                number is possible only after Meta offers or
                                accepts the official number move.
                            </p>
                            <p>
                                Coexistence is the safer target because the
                                WhatsApp Business app can keep working if Meta
                                allows that flow. Full migration is the backup
                                path, but it can change or remove normal
                                phone-app use for that number.
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label
                                class="flex items-start gap-3 rounded-[18px] border border-stone-200 bg-stone-50 px-4 py-4"
                            >
                                <input
                                    v-model="form.whatsapp.enabled"
                                    type="checkbox"
                                    class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                                />
                                <span class="space-y-1 text-sm text-stone-700">
                                    <span
                                        class="block font-medium text-stone-900"
                                        >Enable WhatsApp lane</span
                                    >
                                    <span class="block leading-6"
                                        >Show WhatsApp as a real support and
                                        sales handoff inside CreditSoft.</span
                                    >
                                </span>
                            </label>
                            <label
                                class="flex items-start gap-3 rounded-[18px] border border-stone-200 bg-stone-50 px-4 py-4"
                            >
                                <input
                                    v-model="form.whatsapp.lead_handoff_enabled"
                                    type="checkbox"
                                    class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                                />
                                <span class="space-y-1 text-sm text-stone-700">
                                    <span
                                        class="block font-medium text-stone-900"
                                        >Lead handoff enabled</span
                                    >
                                    <span class="block leading-6"
                                        >Push higher-intent leads toward a
                                        faster human reply path.</span
                                    >
                                </span>
                            </label>
                            <label
                                class="flex items-start gap-3 rounded-[18px] border border-stone-200 bg-stone-50 px-4 py-4 md:col-span-2"
                            >
                                <input
                                    v-model="
                                        form.whatsapp
                                            .appointment_reminders_enabled
                                    "
                                    type="checkbox"
                                    class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                                />
                                <span class="space-y-1 text-sm text-stone-700">
                                    <span
                                        class="block font-medium text-stone-900"
                                        >Appointment and reminder
                                        templates</span
                                    >
                                    <span class="block leading-6"
                                        >Keep WhatsApp ready for reminder flows,
                                        re-engagement, and confirmed follow-up
                                        templates.</span
                                    >
                                </span>
                            </label>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label
                                v-if="
                                    form.whatsapp.available_phone_numbers
                                        .length > 0
                                "
                                class="space-y-2 md:col-span-2"
                            >
                                <span
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >Detected Meta phone number</span
                                >
                                <select
                                    :value="form.whatsapp.phone_number_id"
                                    class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    @change="selectWhatsAppPhoneFromEvent"
                                >
                                    <option value="">
                                        Pick a WhatsApp phone returned by Meta
                                    </option>
                                    <option
                                        v-for="phone in form.whatsapp
                                            .available_phone_numbers"
                                        :key="phone.id"
                                        :value="phone.id"
                                    >
                                        {{
                                            phone.display_phone_number ||
                                            phone.verified_name ||
                                            phone.id
                                        }}
                                        {{
                                            phone.is_test
                                                ? ' - test number'
                                                : ''
                                        }}
                                    </option>
                                </select>
                            </label>

                            <label class="space-y-2">
                                <span
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >Display number</span
                                >
                                <input
                                    v-model="form.whatsapp.display_number"
                                    type="text"
                                    class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                    placeholder="+1 (555) 123-4567"
                                />
                            </label>
                            <label class="space-y-2">
                                <span
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >Phone number ID</span
                                >
                                <input
                                    v-model="form.whatsapp.phone_number_id"
                                    type="text"
                                    class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                    placeholder="WhatsApp phone number ID"
                                />
                            </label>
                            <label class="space-y-2">
                                <span
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >WhatsApp business account ID</span
                                >
                                <input
                                    v-model="form.whatsapp.business_account_id"
                                    type="text"
                                    class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                    placeholder="Meta WhatsApp business account ID"
                                />
                            </label>
                            <label class="space-y-2">
                                <span
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >Default template name</span
                                >
                                <input
                                    v-model="
                                        form.whatsapp.default_template_name
                                    "
                                    type="text"
                                    class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                    placeholder="creditsoft_follow_up"
                                />
                            </label>
                            <label class="space-y-2">
                                <span
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >Webhook verify token</span
                                >
                                <input
                                    v-model="form.whatsapp.verify_token"
                                    type="password"
                                    class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                    placeholder="Optional verify token"
                                />
                            </label>
                            <label class="space-y-2">
                                <span
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >Fallback agent number</span
                                >
                                <input
                                    v-model="
                                        form.whatsapp.fallback_agent_number
                                    "
                                    type="text"
                                    class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                    placeholder="+1 (555) 555-0199"
                                />
                            </label>
                        </div>
                    </div>
                </section>
            </div>

            <section
                v-if="isSocialSection('instagram')"
                id="social-instagram"
                class="overflow-hidden rounded-[24px] border border-stone-200 bg-white/95 shadow-[0_12px_30px_rgba(15,23,42,0.04)]"
            >
                <div class="border-b border-stone-200/80 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <SocialPlatformMark
                            brand="instagram"
                            large
                            monochrome
                            class="text-[#e4405f]"
                        />
                        <div>
                            <p
                                class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                            >
                                Instagram lane
                            </p>
                            <h2
                                class="mt-2 text-lg font-semibold text-stone-950"
                            >
                                Build the Instagram setup while Meta review waits
                            </h2>
                            <p
                                class="mt-2 max-w-3xl text-sm leading-6 text-stone-600"
                            >
                                Keep Instagram useful now as a draft, caption,
                                media, and profile lane. API publishing stays
                                gated until the professional account, app
                                review, and content publishing permission are
                                actually ready.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 px-5 py-5">
                    <div class="grid gap-4 lg:grid-cols-3">
                        <article
                            class="rounded-[20px] border px-4 py-4"
                            :class="instagramStatusChipClass"
                        >
                            <p
                                class="text-[11px] font-semibold tracking-[0.2em] uppercase"
                            >
                                Setup state
                            </p>
                            <p class="mt-2 text-sm font-semibold">
                                {{ instagramStatusLabel }}
                            </p>
                            <p class="mt-2 text-xs leading-5">
                                {{
                                    instagramBusinessId
                                        ? `IG business ID ${instagramBusinessId}`
                                        : 'No Instagram business ID is saved yet.'
                                }}
                            </p>
                        </article>
                        <article
                            class="rounded-[20px] border border-stone-200 bg-stone-50 px-4 py-4 text-sm text-stone-700"
                        >
                            <p
                                class="text-[11px] font-semibold tracking-[0.2em] text-stone-500 uppercase"
                            >
                                Public profile
                            </p>
                            <p class="mt-2 font-semibold text-stone-950">
                                {{
                                    instagramUsername
                                        ? `@${instagramUsername}`
                                        : 'No handle saved'
                                }}
                            </p>
                            <a
                                :href="instagramProfileUrl"
                                target="_blank"
                                rel="noopener"
                                class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-stone-700 hover:text-stone-950"
                            >
                                <FontAwesomeIcon :icon="faArrowUpRightFromSquare" />
                                Open Instagram
                            </a>
                        </article>
                        <article
                            class="rounded-[20px] border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950"
                        >
                            <p
                                class="text-[11px] font-semibold tracking-[0.2em] uppercase"
                            >
                                API roadblock
                            </p>
                            <p class="mt-2 font-semibold">
                                Publishing waits on Meta approval.
                            </p>
                            <p class="mt-2 text-xs leading-5">
                                Keep planning, captions, media URLs, and manual
                                copy moving now. Reconnect after the Instagram
                                publishing permissions are granted.
                            </p>
                        </article>
                    </div>

                    <div
                        class="rounded-[22px] border border-amber-200 bg-amber-50/80 px-4 py-4"
                    >
                        <div class="grid gap-4 lg:grid-cols-[0.8fr_1.2fr]">
                            <div>
                                <p
                                    class="text-[11px] font-semibold tracking-[0.22em] text-amber-800 uppercase"
                                >
                                    Current blocker
                                </p>
                                <h3 class="mt-2 text-lg font-semibold text-stone-950">
                                    {{ threadsBlockerLabel }}
                                </h3>
                                <p class="mt-2 text-sm leading-6 text-stone-700">
                                    This keeps the page honest while Meta
                                    verification, app review, or Instagram
                                    browser login is blocking the token. Threads
                                    can still be planned and published manually.
                                </p>
                                <label class="mt-4 block space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.2em] text-stone-600 uppercase"
                                        >Blocker type</span
                                    >
                                    <select
                                        v-model="form.threads.verification_blocker"
                                        class="w-full rounded-2xl border border-amber-200 bg-white px-4 py-3 text-sm text-stone-900"
                                    >
                                        <option value="meta_verification">
                                            Meta verification
                                        </option>
                                        <option value="instagram_login">
                                            Instagram browser login
                                        </option>
                                        <option value="app_review">
                                            Threads app review
                                        </option>
                                        <option value="none">
                                            No blocker
                                        </option>
                                    </select>
                                </label>
                            </div>
                            <div class="grid gap-2">
                                <article
                                    v-for="move in threadsForwardMoves"
                                    :key="move.key"
                                    class="rounded-[18px] border border-white/80 bg-white/80 px-3 py-3"
                                >
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <p
                                            class="text-sm font-semibold text-stone-950"
                                        >
                                            {{ move.label }}
                                        </p>
                                        <span
                                            class="rounded-full border px-2 py-1 text-[10px] font-semibold tracking-[0.12em] uppercase"
                                            :class="readinessToneClass(move.tone)"
                                        >
                                            {{ move.tone }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs leading-5 text-stone-600">
                                        {{ move.detail }}
                                    </p>
                                </article>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >Instagram business ID</span
                            >
                            <input
                                v-model="form.meta.instagram_business_id"
                                type="text"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                placeholder="IG professional account ID"
                            />
                            <span class="block text-xs leading-5 text-stone-500">
                                Use the linked Instagram professional account ID
                                once Meta returns it from the connected Page.
                            </span>
                        </label>
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >Instagram username</span
                            >
                            <input
                                v-model="form.website_signals.instagram_username"
                                type="text"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                placeholder="@creditsoftapp"
                            />
                            <span class="block text-xs leading-5 text-stone-500">
                                This keeps public links and screenshot-ready
                                planning visible before the API is live.
                            </span>
                        </label>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                        <label
                            class="flex items-start gap-3 rounded-[18px] border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <input
                                v-model="form.publishing.instagram_posts"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                            />
                            <span class="space-y-1 text-sm text-stone-700">
                                <span class="block font-medium text-stone-900"
                                    >Prepare Instagram publishing scopes</span
                                >
                                <span class="block leading-6"
                                    >When enabled, the next Meta reconnect asks
                                    for Instagram publishing. Leave it off while
                                    verification is blocked.</span
                                >
                            </span>
                        </label>
                        <div
                            class="rounded-[18px] border border-sky-200 bg-sky-50 px-4 py-4 text-sm leading-6 text-sky-950"
                        >
                            <p class="font-semibold">What can move now</p>
                            <p class="mt-2">
                                Build caption drafts, carousel outlines, media
                                checklists, public media URLs, and approval
                                notes. Those pieces become the API payload later
                                instead of getting rebuilt from scratch.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-if="isSocialSection('threads')"
                id="social-threads"
                class="overflow-hidden rounded-[24px] border border-stone-200 bg-white/95 shadow-[0_12px_30px_rgba(15,23,42,0.04)]"
            >
                <div class="border-b border-stone-200/80 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <SocialPlatformMark
                            brand="threads"
                            large
                            monochrome
                            class="text-stone-950"
                        />
                        <div>
                            <p
                                class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                            >
                                Threads lane
                            </p>
                            <h2
                                class="mt-2 text-lg font-semibold text-stone-950"
                            >
                                Keep Threads moving as a manual-ready channel
                            </h2>
                            <p
                                class="mt-2 max-w-3xl text-sm leading-6 text-stone-600"
                            >
                                Threads is not the same OAuth lane as Facebook
                                Page posting. Save the profile and draft rules
                                now, then wire the separate Threads API token
                                flow after the Meta app can carry it.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 px-5 py-5">
                    <div class="grid gap-4 lg:grid-cols-3">
                        <article
                            class="rounded-[20px] border px-4 py-4"
                            :class="threadsStatusChipClass"
                        >
                            <p
                                class="text-[11px] font-semibold tracking-[0.2em] uppercase"
                            >
                                Setup state
                            </p>
                            <p class="mt-2 text-sm font-semibold">
                                {{ threadsStatusLabel }}
                            </p>
                            <p class="mt-2 text-xs leading-5">
                                {{
                                    threadsUsername
                                        ? `@${threadsUsername}`
                                        : 'Add the Threads handle to start.'
                                }}
                            </p>
                        </article>
                        <article
                            class="rounded-[20px] border border-stone-200 bg-stone-50 px-4 py-4 text-sm text-stone-700"
                        >
                            <p
                                class="text-[11px] font-semibold tracking-[0.2em] text-stone-500 uppercase"
                            >
                                Public profile
                            </p>
                            <p class="mt-2 font-semibold text-stone-950">
                                {{
                                    threadsUsername
                                        ? `@${threadsUsername}`
                                        : 'No handle saved'
                                }}
                            </p>
                            <a
                                :href="threadsProfileUrl"
                                target="_blank"
                                rel="noopener"
                                class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-stone-700 hover:text-stone-950"
                            >
                                <FontAwesomeIcon :icon="faArrowUpRightFromSquare" />
                                Open Threads
                            </a>
                        </article>
                        <article
                            class="rounded-[20px] border px-4 py-4 text-sm"
                            :class="readinessToneClass(threadsApiTestTone)"
                        >
                            <p
                                class="text-[11px] font-semibold tracking-[0.2em] uppercase"
                            >
                                API lane
                            </p>
                            <p class="mt-2 font-semibold">
                                {{ threadsApiTestStatusLabel }}
                            </p>
                            <p class="mt-2 text-xs leading-5">
                                {{ threadsApiTestSummary }}
                            </p>
                            <button
                                type="button"
                                class="mt-3 inline-flex items-center gap-2 rounded-2xl bg-stone-950 px-3 py-2 text-xs font-semibold text-white transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-70"
                                :disabled="threadsApiTestForm.processing"
                                @click="runThreadsApiTest"
                            >
                                <FontAwesomeIcon :icon="faCloudArrowUp" />
                                {{
                                    threadsApiTestForm.processing
                                        ? 'Testing...'
                                        : 'Run Threads API test'
                                }}
                            </button>
                        </article>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >Threads username</span
                            >
                            <input
                                v-model="form.threads.username"
                                type="text"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                placeholder="@creditsoftapp"
                            />
                            <span class="block text-xs leading-5 text-stone-500">
                                This powers public links and manual publishing
                                prep until the API token lane is built.
                            </span>
                        </label>
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >Threads user ID</span
                            >
                            <input
                                v-model="form.threads.user_id"
                                type="text"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                placeholder="Auto-filled after test"
                            />
                            <span class="block text-xs leading-5 text-stone-500">
                                CreditSoft can auto-fill this from
                                <span class="font-mono">GET /me</span>
                                once a Threads token works.
                            </span>
                        </label>
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >Threads app ID</span
                            >
                            <input
                                v-model="form.threads.app_id"
                                type="text"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                placeholder="Threads app ID"
                            />
                        </label>
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >Threads app secret</span
                            >
                            <input
                                v-model="form.threads.app_secret"
                                type="password"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                placeholder="Threads app secret"
                            />
                        </label>
                        <label class="space-y-2 lg:col-span-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >Threads user access token</span
                            >
                            <textarea
                                v-model="form.threads.access_token"
                                rows="3"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                placeholder="Paste the Threads user token from Graph API Explorer or the Threads auth flow"
                            ></textarea>
                            <span class="block text-xs leading-5 text-stone-500">
                                Use Meta Graph API Explorer with domain
                                <span class="font-mono">THREADS</span>, click
                                <span class="font-semibold"
                                    >Generate Threads Access Token</span
                                >, then click
                                <span class="font-semibold">Submit</span>
                                on
                                <span class="font-mono">GET /me?fields=id,name</span>
                                before copying the token here. Meta&rsquo;s Testing
                                page is checking
                                <span class="font-mono">graph.threads.net</span>
                                activity, not the Facebook Page token lane.
                            </span>
                        </label>
                        <div
                            class="rounded-[18px] border border-sky-200 bg-sky-50 px-4 py-4 text-sm leading-6 text-sky-950 lg:col-span-2"
                        >
                            <p class="font-semibold">How the test behaves</p>
                            <p class="mt-2">
                                CreditSoft first runs the same
                                <span class="font-mono">/me</span>
                                Threads check from Graph API Explorer. If Meta
                                rejects that token, the test stops there so the
                                page shows one real fix instead of eight noisy
                                follow-on failures. Once
                                <span class="font-mono">/me</span>
                                passes, CreditSoft can safely run post-list,
                                keyword, mention, location lookup, insights,
                                and unpublished container calls. Delete,
                                manage-reply, and share-to-Instagram stay
                                manual because those touch visible or
                                destructive actions.
                            </p>
                        </div>
                        <div
                            class="rounded-[18px] border border-stone-200 bg-white px-4 py-4 text-sm leading-6 text-stone-700 lg:col-span-2"
                        >
                            <div
                                class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
                            >
                                <div>
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.2em] text-stone-500 uppercase"
                                    >
                                        Phone login handoff
                                    </p>
                                    <h3
                                        class="mt-1 text-base font-semibold text-stone-950"
                                    >
                                        Use the phone if desktop Instagram is
                                        blocking the auth window
                                    </h3>
                                    <p class="mt-2 max-w-3xl">
                                        This uses the real Threads OAuth flow,
                                        not the Graph Explorer token box. Open
                                        the link on the phone where
                                        Instagram/Threads is already logged in,
                                        approve CreditSoft, then return here and
                                        refresh. The callback stays on
                                        <span class="font-mono">
                                            {{ props.threads_auth.callback_url }}
                                        </span>
                                        so Meta never sees
                                        <span class="font-mono">127.0.0.1</span>.
                                    </p>
                                </div>
                                <div
                                    class="flex shrink-0 flex-wrap gap-2 md:justify-end"
                                >
                                    <a
                                        v-if="props.threads_auth.connect_url"
                                        :href="props.threads_auth.connect_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex items-center gap-2 rounded-2xl bg-stone-950 px-3 py-2 text-xs font-semibold text-white transition hover:bg-stone-800"
                                    >
                                        <FontAwesomeIcon
                                            :icon="faArrowUpRightFromSquare"
                                        />
                                        Open Threads auth
                                    </a>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-2xl border border-stone-300 bg-white px-3 py-2 text-xs font-semibold text-stone-800 transition hover:border-stone-500"
                                        :disabled="!props.threads_auth.connect_url"
                                        @click="copyThreadsAuthUrl"
                                    >
                                        <FontAwesomeIcon :icon="faCopy" />
                                        {{
                                            copiedThreadsAuth
                                                ? 'Copied'
                                                : 'Copy phone link'
                                        }}
                                    </button>
                                </div>
                            </div>
                            <div
                                class="mt-4 grid gap-2 text-xs leading-5 text-stone-600 md:grid-cols-2"
                            >
                                <p
                                    class="rounded-2xl border border-stone-200 bg-stone-50 px-3 py-3"
                                >
                                    If the phone ends on a dead local page, that
                                    is okay: the bridge has already handed the
                                    code to the intranet. Come back to the
                                    desktop page and run the Threads API test.
                                </p>
                                <p
                                    class="rounded-2xl border border-stone-200 bg-stone-50 px-3 py-3"
                                >
                                    Register this same callback in the Threads
                                    OAuth settings:
                                    <span class="font-mono break-all">
                                        {{ props.threads_auth.callback_url }}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <div
                            class="rounded-[18px] border border-emerald-200 bg-emerald-50 px-4 py-4 text-sm leading-6 text-emerald-950 lg:col-span-2"
                        >
                            <div
                                class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
                            >
                                <div>
                                    <p
                                        class="text-[11px] font-semibold tracking-[0.2em] text-emerald-800 uppercase"
                                    >
                                        Manual publish workflow
                                    </p>
                                    <h3
                                        class="mt-1 text-base font-semibold text-stone-950"
                                    >
                                        Keep the content moving without the API
                                    </h3>
                                    <p class="mt-2 max-w-3xl">
                                        Draft the caption, keep the media URL,
                                        publish from the logged-in Threads
                                        account, then paste the final post URL
                                        back here so the office has a record.
                                    </p>
                                </div>
                                <label
                                    class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-white/80 px-3 py-2 text-xs font-semibold text-emerald-900"
                                >
                                    <input
                                        v-model="form.threads.manual_workflow_enabled"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-emerald-400 text-emerald-700"
                                    />
                                    Manual workflow active
                                </label>
                            </div>
                            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                                <label class="space-y-2 lg:col-span-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.2em] text-emerald-800 uppercase"
                                        >Draft caption</span
                                    >
                                    <textarea
                                        v-model="form.threads.manual_draft"
                                        rows="4"
                                        class="w-full rounded-2xl border border-emerald-200 bg-white px-4 py-3 text-sm text-stone-900"
                                        placeholder="Write the Threads caption here before publishing manually."
                                    ></textarea>
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.2em] text-emerald-800 uppercase"
                                        >Media or source URL</span
                                    >
                                    <input
                                        v-model="form.threads.manual_media_url"
                                        type="url"
                                        class="w-full rounded-2xl border border-emerald-200 bg-white px-4 py-3 text-sm text-stone-900"
                                        placeholder="https://..."
                                    />
                                </label>
                                <label class="space-y-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.2em] text-emerald-800 uppercase"
                                        >Published Threads URL</span
                                    >
                                    <input
                                        v-model="form.threads.manual_published_url"
                                        type="url"
                                        class="w-full rounded-2xl border border-emerald-200 bg-white px-4 py-3 text-sm text-stone-900"
                                        placeholder="Paste final post URL after manual publish"
                                    />
                                </label>
                                <label class="space-y-2 lg:col-span-2">
                                    <span
                                        class="text-[11px] font-medium tracking-[0.2em] text-emerald-800 uppercase"
                                        >Notes for follow-up</span
                                    >
                                    <textarea
                                        v-model="form.threads.manual_notes"
                                        rows="3"
                                        class="w-full rounded-2xl border border-emerald-200 bg-white px-4 py-3 text-sm text-stone-900"
                                        placeholder="Track who published, which account was used, and what should be checked later."
                                    ></textarea>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div
                        class="rounded-[22px] border border-stone-200 bg-stone-50 px-4 py-4"
                    >
                        <div
                            class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
                        >
                            <div>
                                <p
                                    class="text-[11px] font-semibold tracking-[0.2em] text-stone-500 uppercase"
                                >
                                    Threads testing checklist
                                </p>
                                <h3
                                    class="mt-1 text-base font-semibold text-stone-950"
                                >
                                    {{ threadsApiTestStatusLabel }}
                                </h3>
                                <p
                                    v-if="form.threads.api_test?.last_tested_at"
                                    class="mt-1 text-xs leading-5 text-stone-500"
                                >
                                    Last tested
                                    {{
                                        new Date(
                                            form.threads.api_test.last_tested_at,
                                        ).toLocaleString()
                                    }}.
                                </p>
                            </div>
                            <div class="grid grid-cols-4 gap-2 text-center">
                                <div
                                    class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-900"
                                >
                                    <p class="text-[10px] font-semibold uppercase">
                                        Pass
                                    </p>
                                    <p class="text-lg font-semibold">
                                        {{ threadsApiTestCounts.passed }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-rose-900"
                                >
                                    <p class="text-[10px] font-semibold uppercase">
                                        Fail
                                    </p>
                                    <p class="text-lg font-semibold">
                                        {{ threadsApiTestCounts.failed }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-2xl border border-sky-200 bg-sky-50 px-3 py-2 text-sky-900"
                                >
                                    <p class="text-[10px] font-semibold uppercase">
                                        Manual
                                    </p>
                                    <p class="text-lg font-semibold">
                                        {{ threadsApiTestCounts.manual }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-2xl border border-stone-200 bg-white px-3 py-2 text-stone-700"
                                >
                                    <p class="text-[10px] font-semibold uppercase">
                                        Skip
                                    </p>
                                    <p class="text-lg font-semibold">
                                        {{ threadsApiTestCounts.skipped }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="threadsApiTestResults.length > 0"
                            class="mt-4 grid gap-2"
                        >
                            <article
                                v-for="result in threadsApiTestResults"
                                :key="result.key"
                                class="rounded-[18px] border border-stone-200 bg-white px-3 py-3"
                            >
                                <div
                                    class="flex flex-wrap items-start justify-between gap-2"
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="text-sm font-semibold text-stone-950"
                                        >
                                            {{ result.label }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs leading-5 break-all text-stone-500"
                                        >
                                            {{ result.permission }} -
                                            {{ result.endpoint }}
                                            <span v-if="result.http_status">
                                                - HTTP {{ result.http_status }}
                                            </span>
                                        </p>
                                    </div>
                                    <span
                                        class="rounded-full border px-2.5 py-1 text-[10px] font-semibold tracking-[0.12em] uppercase"
                                        :class="
                                            apiTestResultClass(result.status)
                                        "
                                    >
                                        {{ apiTestResultLabel(result.status) }}
                                    </span>
                                </div>
                                <p
                                    class="mt-2 text-xs leading-5 text-stone-600"
                                >
                                    {{ result.message }}
                                </p>
                            </article>
                        </div>
                        <p
                            v-else
                            class="mt-4 rounded-[18px] border border-amber-200 bg-amber-50 px-3 py-3 text-sm leading-6 text-amber-900"
                        >
                            No Threads API calls are recorded yet. Save the
                            token, then run the test.
                        </p>
                    </div>
                </div>
            </section>

            <section
                v-if="isSocialSection('publishing')"
                id="social-publishing"
                class="overflow-hidden rounded-[24px] border border-stone-200 bg-white/95 shadow-[0_12px_30px_rgba(15,23,42,0.04)]"
            >
                <div class="border-b border-stone-200/80 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <FontAwesomeIcon
                            :icon="faShareNodes"
                            class="text-[26px] text-stone-700"
                        />
                        <div>
                            <p
                                class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                            >
                                Publishing lanes
                            </p>
                            <h2
                                class="mt-2 text-lg font-semibold text-stone-950"
                            >
                                Keep Page posting intentional and easy to scan
                            </h2>
                            <p
                                class="mt-2 max-w-3xl text-sm leading-6 text-stone-600"
                            >
                                Pick the Page, decide what is allowed to
                                auto-post, and keep approval in the loop when
                                you do not want public changes shipping blindly.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 px-5 py-5">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <label
                            class="flex items-start gap-3 rounded-[18px] border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <input
                                v-model="form.publishing.enabled"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                            />
                            <span class="space-y-1 text-sm text-stone-700">
                                <span class="block font-medium text-stone-900"
                                    >Publishing lane enabled</span
                                >
                                <span class="block leading-6"
                                    >Let CreditSoft prepare and ship approved
                                    posts to the connected Page.</span
                                >
                            </span>
                        </label>
                        <label
                            class="flex items-start gap-3 rounded-[18px] border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <input
                                v-model="form.publishing.approval_required"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                            />
                            <span class="space-y-1 text-sm text-stone-700">
                                <span class="block font-medium text-stone-900"
                                    >Approval required</span
                                >
                                <span class="block leading-6"
                                    >Keep a human in the loop before anything
                                    posts to the public Page.</span
                                >
                            </span>
                        </label>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >Facebook Page</span
                            >
                            <select
                                v-model="form.meta.page_id"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                            >
                                <option value="">Choose a page</option>
                                <option
                                    v-for="page in form.meta.available_pages"
                                    :key="page.id"
                                    :value="page.id"
                                >
                                    {{ page.name }} · {{ page.id }}
                                </option>
                            </select>
                        </label>
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >Instagram business ID</span
                            >
                            <input
                                v-model="form.meta.instagram_business_id"
                                type="text"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                placeholder="Optional Instagram business ID"
                            />
                        </label>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-3">
                        <label
                            class="flex items-start gap-3 rounded-[18px] border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <input
                                v-model="form.publishing.facebook_page_posts"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                            />
                            <span class="space-y-1 text-sm text-stone-700">
                                <span class="block font-medium text-stone-900"
                                    >Facebook Page posts</span
                                >
                                <span class="block leading-6"
                                    >Primary publishing lane for release posts
                                    and office updates.</span
                                >
                            </span>
                        </label>
                        <label
                            class="flex items-start gap-3 rounded-[18px] border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <input
                                v-model="form.publishing.instagram_posts"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                            />
                            <span class="space-y-1 text-sm text-stone-700">
                                <span class="block font-medium text-stone-900"
                                    >Instagram posting lane</span
                                >
                                <span class="block leading-6"
                                    >Keep this off until the business IG account
                                    is truly ready.</span
                                >
                            </span>
                        </label>
                        <label
                            class="flex items-start gap-3 rounded-[18px] border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <input
                                v-model="form.publishing.auto_publish_releases"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                            />
                            <span class="space-y-1 text-sm text-stone-700">
                                <span class="block font-medium text-stone-900"
                                    >Auto-post releases</span
                                >
                                <span class="block leading-6"
                                    >Use product updates and shipping notes as
                                    ready-made social posts.</span
                                >
                            </span>
                        </label>
                        <label
                            class="flex items-start gap-3 rounded-[18px] border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <input
                                v-model="form.publishing.auto_publish_features"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                            />
                            <span class="space-y-1 text-sm text-stone-700">
                                <span class="block font-medium text-stone-900"
                                    >Auto-post features</span
                                >
                                <span class="block leading-6"
                                    >Ship polished feature highlights from the
                                    product lane to the public Page.</span
                                >
                            </span>
                        </label>
                        <label
                            class="flex items-start gap-3 rounded-[18px] border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <input
                                v-model="form.publishing.auto_publish_reviews"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                            />
                            <span class="space-y-1 text-sm text-stone-700">
                                <span class="block font-medium text-stone-900"
                                    >Auto-post reviews</span
                                >
                                <span class="block leading-6"
                                    >Turn approved praise into social proof
                                    without retyping it every time.</span
                                >
                            </span>
                        </label>
                        <div class="grid gap-4">
                            <label class="space-y-2">
                                <span
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >Cadence</span
                                >
                                <select
                                    v-model="form.publishing.cadence"
                                    class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                >
                                    <option value="manual">
                                        Manual publish
                                    </option>
                                    <option value="daily">At most daily</option>
                                    <option value="weekly">
                                        At most weekly
                                    </option>
                                </select>
                            </label>
                            <label class="space-y-2">
                                <span
                                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                    >Default CTA</span
                                >
                                <select
                                    v-model="form.publishing.default_cta"
                                    class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                >
                                    <option value="learn_more">
                                        Learn more
                                    </option>
                                    <option value="sign_up">Sign up</option>
                                    <option value="contact_us">
                                        Contact us
                                    </option>
                                    <option value="book_now">Book now</option>
                                </select>
                            </label>
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-if="isSocialSection('ads')"
                id="social-ads"
                class="overflow-hidden rounded-[24px] border border-stone-200 bg-white/95 shadow-[0_12px_30px_rgba(15,23,42,0.04)]"
            >
                <div class="border-b border-stone-200/80 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <FontAwesomeIcon
                            :icon="faRectangleAd"
                            class="text-[26px] text-stone-700"
                        />
                        <div>
                            <p
                                class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                            >
                                Ads and lead capture
                            </p>
                            <h2
                                class="mt-2 text-lg font-semibold text-stone-950"
                            >
                                Keep the ad account lane sane before spending
                            </h2>
                            <p
                                class="mt-2 max-w-3xl text-sm leading-6 text-stone-600"
                            >
                                Set the default ad account, objective, budget
                                guardrails, and lead destination so the office
                                can launch or hand off campaigns from a known
                                baseline.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 px-5 py-5">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <label
                            class="flex items-start gap-3 rounded-[18px] border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <input
                                v-model="form.ads.enabled"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                            />
                            <span class="space-y-1 text-sm text-stone-700">
                                <span class="block font-medium text-stone-900"
                                    >Ad account lane enabled</span
                                >
                                <span class="block leading-6"
                                    >Let the office store and reuse ad account
                                    defaults here.</span
                                >
                            </span>
                        </label>
                        <label
                            class="flex items-start gap-3 rounded-[18px] border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <input
                                v-model="form.ads.lead_ads_enabled"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                            />
                            <span class="space-y-1 text-sm text-stone-700">
                                <span class="block font-medium text-stone-900"
                                    >Lead ads enabled</span
                                >
                                <span class="block leading-6"
                                    >Turn this on if the system should aim for
                                    native lead capture, not only website
                                    clicks.</span
                                >
                            </span>
                        </label>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >Default ad account</span
                            >
                            <select
                                v-model="form.meta.default_ad_account_id"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                            >
                                <option value="">Choose an ad account</option>
                                <option
                                    v-for="account in form.meta
                                        .available_ad_accounts"
                                    :key="account.id"
                                    :value="account.id"
                                >
                                    {{ account.name || account.id }} ·
                                    {{ account.id }}
                                </option>
                            </select>
                        </label>
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >Default objective</span
                            >
                            <select
                                v-model="form.ads.default_objective"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                            >
                                <option value="OUTCOME_LEADS">Leads</option>
                                <option value="OUTCOME_SALES">Sales</option>
                                <option value="OUTCOME_TRAFFIC">Traffic</option>
                                <option value="OUTCOME_ENGAGEMENT">
                                    Engagement
                                </option>
                            </select>
                        </label>
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >Monthly cap</span
                            >
                            <input
                                v-model="form.ads.monthly_cap"
                                type="text"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                placeholder="1500"
                            />
                        </label>
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >Daily cap</span
                            >
                            <input
                                v-model="form.ads.daily_cap"
                                type="text"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                placeholder="50"
                            />
                        </label>
                        <label class="space-y-2 md:col-span-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >Default campaign naming</span
                            >
                            <input
                                v-model="form.ads.default_campaign_name"
                                type="text"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                            />
                        </label>
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >Lead destination</span
                            >
                            <select
                                v-model="form.ads.default_destination"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                            >
                                <option value="website">Website</option>
                                <option value="native_lead_form">
                                    Native lead form
                                </option>
                                <option value="messenger">Messenger</option>
                                <option value="whatsapp">WhatsApp</option>
                            </select>
                        </label>
                        <label class="space-y-2">
                            <span
                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                >Lead form name</span
                            >
                            <input
                                v-model="form.ads.default_form_name"
                                type="text"
                                class="w-full rounded-2xl border border-stone-300 px-4 py-3 text-sm text-stone-900"
                                placeholder="CreditSoft demo lead form"
                            />
                        </label>
                    </div>
                </div>
            </section>

            <section
                v-if="!isSocialSection('overview')"
                class="flex flex-wrap items-center gap-3"
            >
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 rounded-2xl bg-stone-950 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-stone-800"
                >
                    <FontAwesomeIcon :icon="faCloudArrowUp" />
                    Save social settings
                </button>
                <a
                    v-if="
                        props.meta.connect_ready &&
                        metaBusinessLoginConfigReady
                    "
                    :href="metaConnectRoute"
                    class="inline-flex items-center gap-2 rounded-2xl border border-stone-300 bg-white px-4 py-2.5 text-sm font-medium text-stone-800 transition hover:border-stone-500 hover:text-stone-950"
                >
                    <FontAwesomeIcon :icon="metaConnectionIcon" />
                    {{ metaConnectActionLabel }}
                </a>
                <span
                    v-else-if="
                        props.meta.connect_ready &&
                        !metaBusinessLoginConfigReady
                    "
                    class="inline-flex items-center gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm font-medium text-amber-900"
                >
                    <FontAwesomeIcon :icon="faLinkSlash" />
                    Save Business Login Config ID first
                </span>
                <a
                    href="/settings/connectivity"
                    class="inline-flex items-center gap-2 rounded-2xl border border-stone-300 bg-white px-4 py-2.5 text-sm font-medium text-stone-800 transition hover:border-stone-500 hover:text-stone-950"
                >
                    <FontAwesomeIcon :icon="faMessage" />
                    Open connectivity
                </a>
            </section>
        </form>
    </div>
</template>
