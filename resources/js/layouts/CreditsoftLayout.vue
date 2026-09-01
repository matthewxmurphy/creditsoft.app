<script setup lang="ts">
import {
    faAddressCard,
    faAnglesLeft,
    faAnglesRight,
    faArrowsRotate,
    faArrowUpRightFromSquare,
    faCalendarDays,
    faChartColumn,
    faChevronDown,
    faCircleQuestion,
    faCreditCard,
    faDatabase,
    faDownload,
    faFire,
    faGear,
    faHandshake,
    faInbox,
    faInfo,
    faListCheck,
    faMoneyCheckDollar,
    faPersonCircleCheck,
    faPuzzlePiece,
    faRightFromBracket,
    faServer,
    faShieldHalved,
    faSliders,
    faTriangleExclamation,
    faUsers,
    faUsersGear,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import AiAssistantDrawer from '@/components/creditsoft/AiAssistantDrawer.vue';
import ConnectivityBrandMark from '@/components/creditsoft/ConnectivityBrandMark.vue';
import SocialPlatformMark from '@/components/creditsoft/SocialPlatformMark.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Toaster } from '@/components/ui/sonner';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { badgeTone } from '@/lib/creditsoft';
import {
    chooseLocalBackupDirectory,
    getLocalBackupDirectoryState,
    writeBlobToLocalBackupDirectory,
} from '@/lib/localBackupDirectory';
import { usePwaInstall } from '@/lib/pwa';

type SharedProps = {
    auth: {
        user?: {
            id: number;
            name: string;
            email: string;
            gravatar_url: string;
            last_seen_at?: string | null;
            last_login_at?: string | null;
        };
        role?: string | null;
        role_label?: string | null;
        roles?: string[];
        can_manage_users: boolean;
        can_view_user_directory: boolean;
        can_edit_users: boolean;
    };
    creditsoft: {
        badges: Record<string, number>;
        config: {
            files: Array<{
                file: string;
                updated_at: string;
            }>;
        };
        license: {
            access_state: string;
            warning_active: boolean;
            plan_label?: string | null;
            countdown_label?: string | null;
            rail_message?: string | null;
            grace_ends_label?: string | null;
            expires_label?: string | null;
            features?: Record<string, boolean>;
            feature_trials?: Record<
                string,
                {
                    active: boolean;
                    expired: boolean;
                    status: string;
                    message?: string | null;
                    upgrade_message?: string | null;
                }
            >;
        };
        updates: {
            headline?: string | null;
            summary?: string | null;
            source?: string | null;
            checked_at?: string | null;
            current_version?: string | null;
            current_build?: string | null;
            latest_version?: string | null;
            latest_build?: string | null;
            published_latest_version?: string | null;
            local_build_ahead?: boolean;
            update_available?: boolean;
            update_required?: boolean;
            download_url?: string | null;
            package_path?: string | null;
            renewal_url?: string | null;
            browser_companion?: {
                label?: string | null;
                latest_version?: string | null;
                download_url?: string | null;
                renewal_url?: string | null;
            };
            browser_companion_url?: string | null;
        };
        ai: {
            defaultProvider: string;
            catalog: {
                providers: Array<{
                    name: string;
                    label: string;
                    purpose?: string | null;
                    configured: boolean;
                    chat_model?: string | null;
                    validation?: {
                        state: string;
                        message: string;
                        checked_at?: string | null;
                    };
                }>;
            };
        };
        connectivity: {
            local: {
                enabled: boolean;
            };
            tailscale: {
                enabled: boolean;
            };
            ngrok: {
                enabled: boolean;
                api_only: boolean;
            };
            wasabi: {
                enabled: boolean;
            };
            dropbox: {
                enabled: boolean;
            };
            google_drive: {
                enabled: boolean;
            };
        };
        storage: {
            database: {
                driver: string;
                driver_label: string;
                path?: string | null;
                size_label: string;
                last_backup_label?: string | null;
                backup_count: number;
            };
            documents: {
                stored_in_database: boolean;
                storage_mode: string;
                path: string;
                count: number;
            };
            cluster: {
                enabled: boolean;
                peer_count: number;
            };
        };
        crm: {
            enabled: boolean;
            configured: boolean;
            base_url?: string | null;
            mode?: string | null;
        };
    };
    client?: {
        id: number;
        display_name?: string;
        first_name?: string;
        last_name?: string;
    };
    clientNavigator?: {
        current_view?: string | null;
    } | null;
};

const page = usePage<SharedProps>();

const currentPath = computed(() => page.url.split('?')[0]);
const currentSearchParams = computed(() => {
    const queryIndex = page.url.indexOf('?');

    return new URLSearchParams(
        queryIndex >= 0 ? page.url.slice(queryIndex) : '',
    );
});
const client = computed(() => page.props.client);
const clientName = computed(() => {
    if (!client.value) {
        return null;
    }

    return (
        client.value.display_name ??
        `${client.value.first_name ?? ''} ${client.value.last_name ?? ''}`.trim()
    );
});

const headerEyebrow = computed(() =>
    client.value ? 'Customer Information' : 'Creditsoft Intranet',
);
type ClientStage =
    | 'clients'
    | 'leads'
    | 'terminated'
    | 'fired'
    | 'canceled'
    | 'graduated'
    | 'all';
const clientStageOptions: Array<{ label: string; value: ClientStage }> = [
    { label: 'Clients', value: 'clients' },
    { label: 'Leads', value: 'leads' },
    { label: 'Terminated', value: 'terminated' },
    { label: 'Fired', value: 'fired' },
    { label: 'Canceled', value: 'canceled' },
    { label: 'Graduated', value: 'graduated' },
    { label: 'All', value: 'all' },
];
type RailChildItem = {
    label: string;
    href: string;
    icon?: unknown;
    description?: string;
    section?: string | null;
};
type RailItem = {
    label: string;
    href: string;
    icon?: unknown;
    badge?: number;
    badgeDescription?: string;
    children?: RailChildItem[];
    external?: boolean;
    socialBrand?: 'meta';
};
const coerceClientStage = (value?: string | null): ClientStage =>
    value === 'leads' ||
    value === 'terminated' ||
    value === 'fired' ||
    value === 'canceled' ||
    value === 'graduated' ||
    value === 'all'
        ? value
        : 'clients';
const currentClientStage = computed(() =>
    coerceClientStage(
        currentSearchParams.value.get('view') ??
            page.props.clientNavigator?.current_view ??
            null,
    ),
);
const currentClientStageLabel = computed(
    () =>
        clientStageOptions.find(
            (option) => option.value === currentClientStage.value,
        )?.label ?? 'Clients',
);
const clientStageHref = (stage: ClientStage) => {
    const params = new URLSearchParams(currentSearchParams.value);

    if (stage === 'clients') {
        params.set('view', 'clients');
    } else {
        params.set('view', stage);
    }

    const query = params.toString();

    return `${currentPath.value}${query ? `?${query}` : ''}`;
};
const license = computed(() => page.props.creditsoft.license);
const updates = computed(() => page.props.creditsoft.updates);
const browserCompanionEnabled = computed(
    () => page.props.creditsoft.license.features?.browser_companion !== false,
);
const browserCompanionTrial = computed(
    () =>
        page.props.creditsoft.license.feature_trials?.browser_companion ?? null,
);
const browserCompanionRailVisible = computed(
    () => browserCompanionEnabled.value || Boolean(browserCompanionTrial.value),
);
const browserCompanionRailLabel = computed(() =>
    browserCompanionEnabled.value
        ? 'Download browser companion'
        : 'Upgrade browser companion',
);
const browserCompanionRailTitle = computed(
    () =>
        browserCompanionTrial.value?.message ??
        (browserCompanionEnabled.value
            ? 'Download browser companion'
            : 'Upgrade to use the browser companion'),
);
const browserCompanionDownloadHref = computed(
    () => '/browser-companion/download',
);
const updateGuidance = computed(() => {
    if (!updates.value?.update_available) {
        return null;
    }

    return browserCompanionEnabled.value
        ? 'Update only when nobody is working, the browser companion is closed, and API writes are paused.'
        : 'Update only when nobody is working and API writes are paused.';
});
const versionLabel = computed(() => {
    const version = updates.value?.current_version?.trim();

    return version ? `v ${version}` : null;
});
const updateStatusLabel = computed(() => {
    if (updates.value?.update_available) {
        return `${updates.value.latest_version ?? 'Latest'} available`;
    }

    return 'Latest';
});
const updateBannerVisible = computed(
    () =>
        Boolean(currentUser.value) && Boolean(updates.value?.update_available),
);
const updateBannerTitle = computed(() =>
    updates.value?.update_required
        ? 'Required CreditSoft update is ready'
        : 'CreditSoft update is ready',
);
const updateBannerDetail = computed(() => {
    const summary = String(updates.value?.summary || '').trim();
    const latest = String(updates.value?.latest_version || '').trim();
    const current = String(updates.value?.current_version || '').trim();
    const versionDetail =
        latest && current
            ? `Latest ${latest}; this intranet is running ${current}.`
            : latest
              ? `Latest ${latest}.`
              : '';
    const companionDetail = browserCompanionEnabled.value
        ? 'Close the browser companion and pause staff imports before applying.'
        : 'Pause staff imports before applying.';

    return [summary, versionDetail, companionDetail].filter(Boolean).join(' ');
});
const licenseContextTone = computed(() =>
    license.value?.access_state === 'locked'
        ? 'bg-rose-100 text-rose-700'
        : 'bg-amber-100 text-amber-700',
);
const licenseRailLabel = computed(() => {
    if (!license.value?.warning_active || !license.value?.rail_message) {
        return null;
    }

    return license.value.countdown_label
        ? `${license.value.rail_message} · ${license.value.countdown_label}`
        : license.value.rail_message;
});

const railExpanded = ref(false);
const railWidth = ref(214);
const railMinWidth = 176;
const railMaxWidth = 272;

const leftRail = computed<RailItem[]>(() => [
    { label: 'Overview', href: '/dashboard', icon: faSliders, badge: 0 },
    {
        label: 'Clients & Intake',
        href: '/clients',
        icon: faUsers,
        badge: page.props.creditsoft.badges.clients ?? 0,
        badgeDescription: 'intake items needing review',
        children: [
            {
                label: 'Clients',
                href: '/clients',
                icon: faUsers,
                section: null,
                description: 'Active customer dossiers',
            },
            {
                label: 'Leads',
                href: '/clients?view=leads',
                icon: faAddressCard,
                section: null,
                description: 'Imported leads and intake follow-up',
            },
            {
                label: 'Terminated',
                href: '/clients?view=terminated',
                icon: faCircleQuestion,
                section: 'Lifecycle',
                description: 'Recoverable inactive client records',
            },
            {
                label: 'Canceled',
                href: '/clients?view=canceled',
                icon: faCircleQuestion,
                section: 'Lifecycle',
                description: 'Stopped service records',
            },
            {
                label: 'Graduated',
                href: '/clients?view=graduated',
                icon: faPersonCircleCheck,
                section: 'Lifecycle',
                description: 'Finished client relationships',
            },
            {
                label: 'Fired',
                href: '/clients?view=fired',
                icon: faFire,
                section: 'Lifecycle',
                description: 'Archived problem accounts',
            },
            {
                label: 'All Records',
                href: '/clients?view=all',
                icon: faListCheck,
                section: 'Records',
                description: 'Clients, leads, affiliates, and archive lanes',
            },
            {
                label: 'Import',
                href: '/clients/import',
                icon: faDownload,
                section: 'Tools',
                description: 'Migration files and assignment cleanup',
            },
            {
                label: 'Billing',
                href: '/billing',
                icon: faCreditCard,
                section: 'Tools',
                description: 'Revenue and payment health',
            },
        ],
    },
    ...(crmIntegration.value.enabled &&
    crmIntegration.value.configured &&
    crmLaunchUrl.value
        ? [
              {
                  label: 'CRM',
                  href: '/crm',
                  icon: faHandshake,
                  badge: 0,
              },
          ]
        : []),
    {
        label: 'Inbox',
        href: '/inbox',
        icon: faInbox,
        badge: page.props.creditsoft.badges.inbox ?? 0,
        badgeDescription: 'inbox items',
    },
    {
        label: 'Tasks',
        href: '/tasks',
        icon: faListCheck,
        badge: page.props.creditsoft.badges.tasks ?? 0,
        badgeDescription: 'open tasks',
    },
    {
        label: 'Calendar',
        href: '/calendar',
        icon: faCalendarDays,
        badge: 0,
    },
    {
        label: 'Social',
        href: '/calendar/social',
        badge: 0,
        socialBrand: 'meta',
    },
    {
        label: 'Compliance',
        href: '/violations',
        icon: faTriangleExclamation,
        badge: page.props.creditsoft.badges.violations ?? 0,
        badgeDescription: 'open compliance reviews',
    },
    {
        label: 'CFO',
        href: '/cfo',
        icon: faChartColumn,
        badge: 0,
    },
    {
        label: 'CTO',
        href: '/cto',
        icon: faServer,
        badge: 0,
    },
    {
        label: 'HR',
        href: '/hr',
        icon: faPersonCircleCheck,
        badge: 0,
    },
    {
        label: 'Payroll',
        href: '/payroll',
        icon: faMoneyCheckDollar,
        badge: 0,
    },
]);

const headerContext = computed(() => {
    return licenseRailLabel.value
        ? [{ label: licenseRailLabel.value, tone: licenseContextTone.value }]
        : [];
});

const isSettingsPage = computed(() =>
    currentPath.value.startsWith('/settings'),
);
const isCrmPage = computed(() => currentPath.value === '/crm');
const isClientWorkspace = computed(() => {
    if (!client.value?.id) {
        return false;
    }

    return currentPath.value.startsWith(`/clients/${client.value.id}`);
});
const clientWorkspaceLinks = computed(() => {
    if (!client.value?.id) {
        return [];
    }

    const params = new URLSearchParams();
    params.set('view', currentClientStage.value);
    const query = params.toString();
    const withStage = (path: string) => ({
        path,
        href: `${path}${query ? `?${query}` : ''}`,
    });

    return [
        { label: 'Overview', ...withStage(`/clients/${client.value.id}`) },
        {
            label: 'Compare',
            ...withStage(`/clients/${client.value.id}/compare`),
        },
        {
            label: 'Compliance',
            ...withStage(`/clients/${client.value.id}/violations`),
        },
        { label: 'Notes', ...withStage(`/clients/${client.value.id}/notes`) },
        {
            label: 'Letters',
            ...withStage(`/clients/${client.value.id}/letters`),
        },
        { label: 'Briefs', ...withStage(`/clients/${client.value.id}/briefs`) },
        { label: 'Audit', ...withStage(`/clients/${client.value.id}/audit`) },
    ];
});
const { pwaState, install, installLabel } = usePwaInstall();
const currentUser = computed(() => page.props.auth.user);
const connectivity = computed(() => page.props.creditsoft.connectivity);
const storageHealth = computed(() => page.props.creditsoft.storage);
const crmIntegration = computed(() => page.props.creditsoft.crm);
const crmLaunchUrl = computed(() => {
    const baseUrl = crmIntegration.value.base_url ?? '';

    if (!baseUrl) {
        return '';
    }

    try {
        const url = new URL(baseUrl);

        if (!url.pathname || url.pathname === '/') {
            url.pathname = '/welcome';
        }

        if (currentUser.value?.email) {
            url.searchParams.set('email', currentUser.value.email);
        }

        return url.toString();
    } catch {
        return baseUrl;
    }
});
type FooterConnectorBrand =
    | 'tailscale'
    | 'ngrok'
    | 'wasabi'
    | 'syncthing'
    | 'dropbox'
    | 'google_drive';
const footerConnectorBrands: FooterConnectorBrand[] = [
    'tailscale',
    'ngrok',
    'wasabi',
    'syncthing',
    'dropbox',
    'google_drive',
];
const currentRole = computed(() => page.props.auth.role ?? null);
const canViewUserDirectory = computed(
    () =>
        page.props.auth.can_view_user_directory ||
        ['owner_admin', 'admin', 'demo_admin', 'manager'].includes(
            currentRole.value ?? '',
        ),
);
const canManageUsers = computed(
    () =>
        page.props.auth.can_manage_users ||
        ['owner_admin', 'admin', 'demo_admin'].includes(
            currentRole.value ?? '',
        ),
);
const workspaceRailLabels = new Set([
    'Overview',
    'Clients & Intake',
    'Inbox',
    'Tasks',
    'Calendar',
    'CRM',
    'Compliance',
]);
const operationsRailLabels = new Set(['Social', 'CFO', 'CTO', 'HR', 'Payroll']);
const leftRailGroups = computed(() =>
    [
        {
            key: 'workspace',
            label: 'Workspace',
            items: leftRail.value.filter((item) =>
                workspaceRailLabels.has(item.label),
            ),
        },
        {
            key: 'operations',
            label: 'Operations',
            items: leftRail.value.filter((item) =>
                operationsRailLabels.has(item.label),
            ),
        },
    ].filter((group) => group.items.length > 0),
);
const settingsRailMenuItems = computed(() => [
    {
        label: 'All settings',
        href: '/settings',
        icon: faGear,
    },
    {
        label: 'Profile',
        href: '/settings/profile',
        icon: faAddressCard,
    },
    {
        label: 'Social / Meta',
        href: '/settings/social',
        socialBrand: 'meta' as const,
    },
    {
        label: 'Security',
        href: '/settings/security',
        icon: faShieldHalved,
    },
    {
        label: 'License',
        href: '/settings/license',
        icon: faCreditCard,
    },
    ...(canViewUserDirectory.value
        ? [
              {
                  label: 'Accounts Manager',
                  href: '/settings/users',
                  icon: faUsersGear,
              },
          ]
        : []),
]);
const roleLabel = computed(() => page.props.auth.role_label ?? 'Staff');
const railIconStyle = {
    width: '18px',
    height: '18px',
};
const railExternalIconStyle = {
    width: '11px',
    height: '11px',
};
const clampRailWidth = (value: number) =>
    Math.min(railMaxWidth, Math.max(railMinWidth, Math.round(value)));
const railStyle = computed(() =>
    railExpanded.value ? { width: `${railWidth.value}px` } : undefined,
);
const mobileRailIconStyle = {
    width: '16px',
    height: '16px',
};
const collapsedRailBadgeClass =
    '!absolute top-[8px] right-0 !z-30 translate-x-[62%] min-h-[20px] min-w-[34px] px-[6px] text-[11px]';
const expandedRailBadgeClass =
    'relative z-20 ml-auto min-h-[19px] min-w-[24px] px-[6px] text-[11px]';
const inactiveRailButtonClass =
    'text-stone-300 hover:bg-stone-900/45 hover:text-stone-50';
const activeRailButtonClass = () => [
    'text-stone-50',
    railExpanded.value
        ? "bg-stone-900/85 before:pointer-events-none before:absolute before:top-2 before:bottom-2 before:left-1 before:w-1 before:rounded-full before:bg-amber-400 before:content-[''] [&>*]:relative [&>*]:z-10"
        : "bg-transparent before:pointer-events-none before:absolute before:inset-[8px] before:rounded-[8px] before:bg-amber-400 before:content-[''] [&>*]:relative [&>*]:z-10",
];
const railItemClass = (active = false) => [
    'relative flex items-center overflow-visible rounded-xl transition-colors duration-75 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300',
    railExpanded.value
        ? 'min-h-9 w-full justify-start gap-3 px-3 py-2'
        : 'h-[42px] w-11 justify-center',
    active ? activeRailButtonClass() : inactiveRailButtonClass,
];
const toggleRailExpanded = () => {
    railExpanded.value = !railExpanded.value;

    if (typeof window !== 'undefined') {
        window.localStorage.setItem(
            'creditsoft.leftRailExpanded',
            railExpanded.value ? '1' : '0',
        );
    }
};
const startRailResize = (event: PointerEvent) => {
    if (!railExpanded.value || typeof window === 'undefined') {
        return;
    }

    const startX = event.clientX;
    const startWidth = railWidth.value;

    const resize = (moveEvent: PointerEvent) => {
        railWidth.value = clampRailWidth(
            startWidth + moveEvent.clientX - startX,
        );
    };

    const stop = () => {
        window.localStorage.setItem(
            'creditsoft.leftRailWidth',
            String(railWidth.value),
        );
        window.removeEventListener('pointermove', resize);
        window.removeEventListener('pointerup', stop);
        window.removeEventListener('pointercancel', stop);
    };

    window.addEventListener('pointermove', resize);
    window.addEventListener('pointerup', stop);
    window.addEventListener('pointercancel', stop);
};
const railBadgeText = (badge = 0) => {
    if (!Number.isFinite(Number(badge)) || Number(badge) <= 0) {
        return null;
    }

    return Number(badge) > 999 ? '999+' : String(badge);
};
const reloadConfig = () => {
    router.post('/internal/config/reload', {}, { preserveScroll: true });
};

const backupRunningTarget = ref<
    'local' | 'wasabi' | 'dropbox' | 'google_drive' | null
>(null);
const localBackupDirectoryName = ref<string | null>(null);

const syncLocalBackupDirectory = async () => {
    const state = await getLocalBackupDirectoryState();
    localBackupDirectoryName.value = state.name;
};

let stopHrActivitySampler: (() => void) | null = null;

const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

const hrActivitySessionUuid = () => {
    const key = 'creditsoft.hrActivitySessionUuid';
    const existing = window.sessionStorage.getItem(key);

    if (existing) {
        return existing;
    }

    const value =
        window.crypto?.randomUUID?.() ??
        `hr-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    window.sessionStorage.setItem(key, value);

    return value;
};

const isSensitiveActivityTarget = (target: EventTarget | null) => {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    const field = target.closest<HTMLInputElement | HTMLTextAreaElement>(
        'input, textarea',
    );

    if (!field) {
        return false;
    }

    const text = [
        field.getAttribute('type'),
        field.getAttribute('name'),
        field.getAttribute('id'),
        field.getAttribute('autocomplete'),
        field.getAttribute('aria-label'),
        field.getAttribute('placeholder'),
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

    return [
        'password',
        'passcode',
        'secret',
        'token',
        'api key',
        'apikey',
        'private key',
        'credential',
        'security answer',
        'ssn',
        'social security',
    ].some((needle) => text.includes(needle));
};

const startHrActivitySampler = () => {
    if (typeof window === 'undefined' || !page.props.auth.user) {
        return () => {};
    }

    const sessionUuid = hrActivitySessionUuid();
    const counters = {
        active_ms: 0,
        keypress_count: 0,
        click_count: 0,
        mouse_move_count: 0,
        scroll_count: 0,
        focus_count: 0,
        form_submit_count: 0,
    };
    let lastActivityAt: number | null = null;
    let lastMouseMoveAt = 0;

    const markActivity = (
        key: keyof typeof counters,
        event?: Event,
        increment = 1,
    ) => {
        if (
            event &&
            (!event.isTrusted || isSensitiveActivityTarget(event.target))
        ) {
            return;
        }

        const nowMs = Date.now();
        counters[key] += increment;
        counters.active_ms += lastActivityAt
            ? Math.min(nowMs - lastActivityAt, 5000)
            : 1000;
        lastActivityAt = nowMs;
    };

    const resetCounters = () => {
        counters.active_ms = 0;
        counters.keypress_count = 0;
        counters.click_count = 0;
        counters.mouse_move_count = 0;
        counters.scroll_count = 0;
        counters.focus_count = 0;
        counters.form_submit_count = 0;
        lastActivityAt = null;
    };

    const hasActivity = () =>
        counters.active_ms > 0 ||
        counters.keypress_count > 0 ||
        counters.click_count > 0 ||
        counters.mouse_move_count > 0 ||
        counters.scroll_count > 0 ||
        counters.focus_count > 0 ||
        counters.form_submit_count > 0;

    const flush = async (keepalive = false) => {
        if (!hasActivity()) {
            return;
        }

        const payload = {
            captured_at: new Date().toISOString(),
            route_path: window.location.pathname,
            page_title: document.title,
            session_uuid: sessionUuid,
            ...counters,
            metadata: {
                visibility: document.visibilityState,
            },
        };

        resetCounters();

        try {
            await fetch('/hr/activity-captures', {
                method: 'POST',
                credentials: 'same-origin',
                keepalive,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });
        } catch {
            // Activity sampling must never interrupt office work.
        }
    };

    const onKeydown = (event: KeyboardEvent) =>
        markActivity('keypress_count', event);
    const onClick = (event: MouseEvent) => markActivity('click_count', event);
    const onMouseMove = (event: MouseEvent) => {
        const nowMs = Date.now();

        if (nowMs - lastMouseMoveAt < 1000) {
            return;
        }

        lastMouseMoveAt = nowMs;
        markActivity('mouse_move_count', event);
    };
    const onScroll = (event: Event) => markActivity('scroll_count', event);
    const onFocus = (event: FocusEvent) => markActivity('focus_count', event);
    const onSubmit = (event: SubmitEvent) =>
        markActivity('form_submit_count', event);
    const onVisibilityChange = () => {
        if (document.visibilityState === 'hidden') {
            void flush(true);
        }
    };
    const onPageHide = () => void flush(true);
    const flushTimer = window.setInterval(() => void flush(), 60000);

    document.addEventListener('keydown', onKeydown, true);
    document.addEventListener('click', onClick, true);
    document.addEventListener('mousemove', onMouseMove, true);
    document.addEventListener('scroll', onScroll, true);
    document.addEventListener('focusin', onFocus, true);
    document.addEventListener('submit', onSubmit, true);
    document.addEventListener('visibilitychange', onVisibilityChange);
    window.addEventListener('pagehide', onPageHide);

    return () => {
        window.clearInterval(flushTimer);
        document.removeEventListener('keydown', onKeydown, true);
        document.removeEventListener('click', onClick, true);
        document.removeEventListener('mousemove', onMouseMove, true);
        document.removeEventListener('scroll', onScroll, true);
        document.removeEventListener('focusin', onFocus, true);
        document.removeEventListener('submit', onSubmit, true);
        document.removeEventListener('visibilitychange', onVisibilityChange);
        window.removeEventListener('pagehide', onPageHide);
        void flush(true);
    };
};

onMounted(() => {
    void syncLocalBackupDirectory();

    railExpanded.value =
        window.localStorage.getItem('creditsoft.leftRailExpanded') === '1';
    const storedRailWidth = Number(
        window.localStorage.getItem('creditsoft.leftRailWidth'),
    );

    if (Number.isFinite(storedRailWidth)) {
        railWidth.value = clampRailWidth(storedRailWidth);
    }

    stopHrActivitySampler = startHrActivitySampler();
});

onBeforeUnmount(() => {
    stopHrActivitySampler?.();
    stopHrActivitySampler = null;
});

const triggerBrowserDownload = (url: string, filename: string) => {
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.rel = 'noopener';
    document.body.append(link);
    link.click();
    link.remove();
};

const saveLocalBackupIntoPreferredFolder = async (
    downloadUrl: string,
    filename: string,
) => {
    let state = await getLocalBackupDirectoryState();

    if (!state.handle) {
        state = await chooseLocalBackupDirectory();
    }

    if (!state.handle) {
        triggerBrowserDownload(downloadUrl, filename);

        return 'browser download';
    }

    const response = await fetch(downloadUrl, {
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(
            'CreditSoft could not download the fresh backup archive for local export.',
        );
    }

    const blob = await response.blob();
    const savedPath = await writeBlobToLocalBackupDirectory(filename, blob);

    await syncLocalBackupDirectory();

    return savedPath;
};

const runDatabaseBackup = async (
    target: 'local' | 'wasabi' | 'dropbox' | 'google_drive',
) => {
    if (backupRunningTarget.value) {
        return;
    }

    backupRunningTarget.value = target;

    try {
        const csrfToken =
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.content ?? '';
        const response = await fetch('/internal/backups/run', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ target }),
        });
        const payload = await response.json();

        if (!response.ok || payload?.ok === false) {
            throw new Error(
                payload?.message ?? 'CreditSoft could not run the backup lane.',
            );
        }

        const messages = Array.isArray(payload?.messages)
            ? [...payload.messages]
            : ['CreditSoft finished the database backup.'];

        if (target === 'local' && payload?.download_url && payload?.filename) {
            try {
                const savedPath = await saveLocalBackupIntoPreferredFolder(
                    payload.download_url,
                    payload.filename,
                );

                messages.push(
                    savedPath === 'browser download'
                        ? 'The browser saved a downloadable copy because no local export folder was selected.'
                        : `CreditSoft copied the backup into ${savedPath}.`,
                );
            } catch (error) {
                messages.push(
                    error instanceof Error
                        ? error.message
                        : 'CreditSoft kept the archive locally, but could not write the external copy.',
                );
            }
        }

        router.reload({
            only: ['creditsoft'],
        });

        toast.success(messages.join(' '));
    } catch (error) {
        toast.error(
            error instanceof Error
                ? error.message
                : 'CreditSoft could not run the backup lane.',
        );
    } finally {
        backupRunningTarget.value = null;
    }
};

const logout = () => {
    router.post('/logout');
};

const railPathMatches = (href: string) => {
    const path = href.split(/[?#]/)[0] || '/';

    return (
        currentPath.value === path || currentPath.value.startsWith(`${path}/`)
    );
};

const isRailItemActive = (item: { href: string }) => {
    if (item.href === '/clients') {
        return railPathMatches('/clients');
    }

    if (item.href === '/calendar') {
        return currentPath.value === '/calendar';
    }

    if (item.href === '/calendar/social') {
        return (
            currentPath.value.startsWith('/calendar/social') ||
            currentPath.value.startsWith('/settings/social')
        );
    }

    return railPathMatches(item.href);
};

const railChildSections = (children: RailChildItem[]) => {
    const sectionOrder = [null, 'Lifecycle', 'Records', 'Tools'];

    return sectionOrder
        .map((section) => ({
            label: section,
            items: children.filter(
                (child) => (child.section ?? null) === section,
            ),
        }))
        .filter((section) => section.items.length > 0);
};

const initials = (name?: string | null) =>
    (name ?? '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');

const formatDateTime = (value?: string | null) =>
    value
        ? new Intl.DateTimeFormat('en-US', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
          }).format(new Date(value))
        : 'Never';

const footerConnectorMeta = (brand: FooterConnectorBrand) => {
    const enabled =
        brand === 'syncthing'
            ? Boolean(storageHealth.value.cluster.enabled)
            : connectivity.value[brand].enabled;

    switch (brand) {
        case 'tailscale':
            return {
                label: 'Tailscale',
                enabled,
                detail: enabled
                    ? 'Private staff access is live over the office tailnet.'
                    : 'Private tailnet access is not connected right now.',
            };
        case 'ngrok':
            return {
                label: 'ngrok',
                enabled,
                detail: enabled
                    ? connectivity.value.ngrok.api_only
                        ? 'Public relay is live for API traffic only. The office workspace stays private.'
                        : 'Public relay is live for external traffic and office services.'
                    : 'No public relay is running right now.',
            };
        case 'wasabi':
            return {
                label: 'Wasabi',
                enabled,
                detail: enabled
                    ? 'Archive backups can move off this machine into the Wasabi lane.'
                    : 'Wasabi archive backup is not configured yet.',
            };
        case 'syncthing':
            return {
                label: 'Syncthing',
                enabled,
                detail: enabled
                    ? `${storageHealth.value.cluster.peer_count} peer office ${storageHealth.value.cluster.peer_count === 1 ? 'is' : 'are'} ready for local sync or replica handoff.`
                    : 'Peer-to-peer folder sync is not configured yet. Syncthing can mirror selected backup folders without making a cloud service the middleman.',
            };
        case 'dropbox':
            return {
                label: 'Dropbox',
                enabled,
                detail: enabled
                    ? 'Dropbox customer-upload handoff and backup staging are ready.'
                    : 'Dropbox customer-upload handoff is not configured yet.',
            };
        case 'google_drive':
            return {
                label: 'Google Drive',
                enabled,
                detail: enabled
                    ? 'Google Drive customer-upload handoff and backup staging are ready.'
                    : 'Google Drive customer-upload handoff is not configured yet.',
            };
    }
};

const footerConnectorDotClass = (enabled: boolean) =>
    enabled
        ? 'bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.82)]'
        : 'bg-rose-500';

const footerTooltipAlign = (index: number) => {
    if (index <= 1) {
        return 'start';
    }

    if (index >= footerConnectorBrands.length - 2) {
        return 'end';
    }

    return 'center';
};
</script>

<template>
    <div
        class="fixed inset-0 h-dvh w-screen overflow-hidden bg-[radial-gradient(circle_at_top_left,_rgba(245,158,11,0.12),_transparent_28%),linear-gradient(180deg,_rgba(255,251,235,0.96),_rgba(247,244,236,1))] text-stone-900"
    >
        <div class="flex h-full flex-col lg:flex-row">
            <aside
                class="hidden lg:sticky lg:top-0 lg:z-[950] lg:flex lg:h-screen lg:shrink-0 lg:flex-col lg:justify-between lg:self-start lg:overflow-visible lg:border-r lg:border-amber-400/55 lg:bg-stone-950 lg:px-2 lg:py-3 lg:transition-[width] lg:duration-100"
                :class="railExpanded ? 'lg:w-[214px]' : 'lg:w-[64px]'"
                :style="railStyle"
            >
                <button
                    v-if="railExpanded"
                    type="button"
                    class="group absolute top-0 -right-[5px] hidden h-full w-2 cursor-ew-resize items-center justify-center lg:flex"
                    aria-label="Resize icon rail"
                    title="Drag to resize icon rail"
                    @pointerdown.prevent="startRailResize"
                >
                    <span
                        class="h-12 w-px rounded-full bg-stone-700/70 transition group-hover:bg-amber-300"
                    />
                </button>

                <nav
                    class="flex flex-col gap-0 overflow-visible"
                    :class="railExpanded ? 'items-stretch' : 'items-center'"
                    aria-label="Primary"
                >
                    <button
                        type="button"
                        class="relative flex h-10 items-center rounded-xl text-stone-400 transition-colors duration-75 hover:bg-stone-900/45 hover:text-stone-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300"
                        :class="
                            railExpanded
                                ? 'w-full justify-start gap-3 px-3'
                                : 'w-11 justify-center'
                        "
                        :aria-label="
                            railExpanded ? 'Collapse rail' : 'Show rail labels'
                        "
                        :title="
                            railExpanded ? 'Collapse rail' : 'Show rail labels'
                        "
                        @click="toggleRailExpanded"
                    >
                        <FontAwesomeIcon
                            :icon="railExpanded ? faAnglesLeft : faAnglesRight"
                            :style="railIconStyle"
                        />
                        <span
                            v-if="railExpanded"
                            class="text-xs font-semibold tracking-[0.18em] uppercase"
                        >
                            Collapse
                        </span>
                    </button>

                    <template
                        v-for="(group, groupIndex) in leftRailGroups"
                        :key="group.key"
                    >
                        <div
                            v-if="groupIndex > 0"
                            class="my-[2px] h-px bg-amber-400/70"
                            :class="railExpanded ? 'mx-1' : 'w-8'"
                        />
                        <p
                            v-if="railExpanded"
                            class="px-3 pt-1 text-[10px] font-semibold tracking-[0.22em] text-stone-500 uppercase"
                        >
                            {{ group.label }}
                        </p>

                        <template v-for="item in group.items" :key="item.href">
                            <div
                                v-if="item.children"
                                class="group/rail-menu relative overflow-visible"
                                :class="railExpanded ? 'w-full' : 'w-11'"
                            >
                                <Link
                                    :href="item.href"
                                    :class="
                                        railItemClass(isRailItemActive(item))
                                    "
                                    :aria-label="
                                        item.badge
                                            ? `${item.label}: ${item.badge} ${item.badgeDescription ?? 'pending'}`
                                            : item.label
                                    "
                                    :title="
                                        item.badge
                                            ? `${item.label}: ${item.badge} ${item.badgeDescription ?? 'pending'}`
                                            : item.label
                                    "
                                    aria-haspopup="menu"
                                >
                                    <FontAwesomeIcon
                                        v-if="item.icon"
                                        :icon="item.icon"
                                        :style="railIconStyle"
                                    />
                                    <span
                                        v-if="railExpanded"
                                        class="min-w-0 text-left text-sm leading-tight font-medium"
                                    >
                                        {{ item.label }}
                                    </span>
                                    <span
                                        v-if="railBadgeText(item.badge)"
                                        class="pointer-events-none inline-flex items-center justify-center rounded-full leading-none font-bold shadow-sm"
                                        :class="[
                                            badgeTone(item.badge),
                                            railExpanded
                                                ? expandedRailBadgeClass
                                                : collapsedRailBadgeClass,
                                        ]"
                                    >
                                        {{ railBadgeText(item.badge) }}
                                    </span>
                                </Link>

                                <div
                                    class="invisible absolute top-0 left-full z-[1000] w-[23rem] pl-3 opacity-0 transition duration-100 group-focus-within/rail-menu:visible group-focus-within/rail-menu:opacity-100 group-hover/rail-menu:visible group-hover/rail-menu:opacity-100"
                                    role="menu"
                                    :aria-label="`${item.label} shortcuts`"
                                >
                                    <div
                                        class="rounded-2xl border border-stone-200 bg-white p-2 text-stone-900 shadow-xl"
                                    >
                                        <div
                                            class="rounded-xl px-3 py-3 font-normal"
                                        >
                                            <div
                                                class="text-[11px] tracking-[0.22em] text-stone-400 uppercase"
                                            >
                                                Clients & Intake
                                            </div>
                                            <div
                                                class="mt-2 text-xs tracking-normal text-stone-600 normal-case"
                                            >
                                                Client records, intake lanes,
                                                lifecycle, and office tools.
                                            </div>
                                        </div>

                                        <div class="my-1 h-px bg-stone-100" />

                                        <div
                                            v-for="section in railChildSections(
                                                item.children,
                                            )"
                                            :key="section.label ?? 'intake'"
                                            class="space-y-1"
                                        >
                                            <div
                                                v-if="section.label"
                                                class="px-3 pt-2 text-[10px] font-semibold tracking-[0.22em] text-stone-400 uppercase"
                                            >
                                                {{ section.label }}
                                            </div>

                                            <Link
                                                v-for="child in section.items"
                                                :key="child.href"
                                                :href="child.href"
                                                class="flex w-full items-center gap-3 rounded-xl px-3 py-[7px] transition hover:bg-stone-100 focus:bg-stone-100 focus:outline-none"
                                                role="menuitem"
                                            >
                                                <FontAwesomeIcon
                                                    v-if="child.icon"
                                                    :icon="child.icon"
                                                    class="w-4 text-stone-500"
                                                />
                                                <span class="block min-w-0">
                                                    <span
                                                        class="block truncate font-medium text-stone-900"
                                                        >{{ child.label }}</span
                                                    >
                                                    <span
                                                        class="block text-xs text-stone-500"
                                                        >{{
                                                            child.description
                                                        }}</span
                                                    >
                                                </span>
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <a
                                v-else-if="item.external"
                                :href="item.href"
                                target="_blank"
                                rel="noopener"
                                :class="railItemClass(false)"
                                :aria-label="item.label"
                                :title="item.label"
                            >
                                <FontAwesomeIcon
                                    v-if="item.icon"
                                    :icon="item.icon"
                                    :style="railIconStyle"
                                />
                                <span
                                    v-if="item.external && !railExpanded"
                                    class="absolute top-[6px] right-[5px] inline-flex items-center justify-center text-stone-950"
                                    aria-hidden="true"
                                >
                                    <FontAwesomeIcon
                                        :icon="faArrowUpRightFromSquare"
                                        :style="railExternalIconStyle"
                                    />
                                </span>
                                <span
                                    v-if="railExpanded"
                                    class="flex min-w-0 items-center text-left text-sm leading-tight font-medium"
                                >
                                    <span class="min-w-0">{{
                                        item.label
                                    }}</span>
                                    <span
                                        v-if="item.external"
                                        aria-hidden="true"
                                        class="relative -top-1.5 ml-1 inline-flex shrink-0 items-center justify-center text-stone-50"
                                    >
                                        <FontAwesomeIcon
                                            :icon="faArrowUpRightFromSquare"
                                            :style="railExternalIconStyle"
                                        />
                                    </span>
                                </span>
                            </a>

                            <Link
                                v-else
                                :href="item.href"
                                :class="railItemClass(isRailItemActive(item))"
                                :aria-label="
                                    item.badge
                                        ? `${item.label}: ${item.badge} ${item.badgeDescription ?? 'pending'}`
                                        : item.label
                                "
                                :title="
                                    item.badge
                                        ? `${item.label}: ${item.badge} ${item.badgeDescription ?? 'pending'}`
                                        : item.label
                                "
                            >
                                <SocialPlatformMark
                                    v-if="item.socialBrand === 'meta'"
                                    brand="meta"
                                    monochrome
                                    rail
                                />
                                <FontAwesomeIcon
                                    v-else-if="item.icon"
                                    :icon="item.icon"
                                    :style="railIconStyle"
                                />
                                <span
                                    v-if="railExpanded"
                                    class="min-w-0 text-left text-sm leading-tight font-medium"
                                >
                                    {{ item.label }}
                                </span>
                                <span
                                    v-if="railBadgeText(item.badge)"
                                    class="inline-flex items-center justify-center rounded-full leading-none font-bold shadow-sm"
                                    :class="[
                                        badgeTone(item.badge),
                                        railExpanded
                                            ? expandedRailBadgeClass
                                            : collapsedRailBadgeClass,
                                    ]"
                                >
                                    {{ railBadgeText(item.badge) }}
                                </span>
                            </Link>
                        </template>
                    </template>
                </nav>

                <div
                    class="flex flex-col gap-0"
                    :class="railExpanded ? 'items-stretch' : 'items-center'"
                >
                    <div
                        class="mb-[2px] h-px bg-amber-400/70"
                        :class="railExpanded ? 'mx-1 w-auto' : 'w-8'"
                        aria-hidden="true"
                    />
                    <TooltipProvider
                        v-if="browserCompanionRailVisible"
                        :delay-duration="0"
                    >
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Link
                                    href="/settings/profile"
                                    :class="railItemClass(false)"
                                    aria-label="CreditSoft intranet information"
                                >
                                    <FontAwesomeIcon
                                        :icon="faInfo"
                                        :style="railIconStyle"
                                    />
                                    <span
                                        v-if="railExpanded"
                                        class="min-w-0 text-left text-sm leading-tight font-medium"
                                        >Info</span
                                    >
                                </Link>
                            </TooltipTrigger>

                            <TooltipContent
                                variant="card"
                                side="right"
                                align="end"
                                :side-offset="12"
                                class="max-w-xs px-0 py-0"
                            >
                                <div class="space-y-0">
                                    <div
                                        class="border-b border-stone-700/80 px-4 py-3"
                                    >
                                        <div
                                            class="text-[11px] tracking-[0.22em] text-amber-200 uppercase"
                                        >
                                            Intranet info
                                        </div>
                                        <div
                                            class="mt-1 text-sm font-semibold text-stone-50"
                                        >
                                            Browser companion and user API key
                                        </div>
                                    </div>
                                    <div
                                        class="space-y-2 px-4 py-3 text-xs leading-5 tracking-normal text-stone-200 normal-case"
                                    >
                                        <p>
                                            Profile holds the personal API key
                                            the browser companion uses to
                                            connect this local intranet to
                                            approved CreditSoft workflows.
                                        </p>
                                        <p class="font-semibold text-amber-100">
                                            Click the info icon to open Profile.
                                        </p>
                                    </div>
                                </div>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                    <a
                        v-if="browserCompanionEnabled"
                        :href="browserCompanionDownloadHref"
                        :class="railItemClass(false)"
                        :aria-label="browserCompanionRailLabel"
                        :title="browserCompanionRailTitle"
                    >
                        <FontAwesomeIcon
                            :icon="faPuzzlePiece"
                            :style="railIconStyle"
                        />
                        <span
                            v-if="railExpanded"
                            class="min-w-0 text-left text-sm leading-tight font-medium"
                            >Companion</span
                        >
                    </a>
                    <a
                        v-else-if="browserCompanionRailVisible"
                        :href="browserCompanionDownloadHref"
                        :class="[railItemClass(false), '!text-amber-200']"
                        :aria-label="browserCompanionRailLabel"
                        :title="browserCompanionRailTitle"
                    >
                        <FontAwesomeIcon
                            :icon="faPuzzlePiece"
                            :style="railIconStyle"
                        />
                        <span
                            v-if="railExpanded"
                            class="min-w-0 text-left text-sm leading-tight font-medium"
                            >Companion</span
                        >
                    </a>
                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <button
                                type="button"
                                :class="railItemClass(isSettingsPage)"
                                aria-label="Settings"
                            >
                                <FontAwesomeIcon
                                    :icon="faGear"
                                    :style="railIconStyle"
                                />
                                <span
                                    v-if="railExpanded"
                                    class="min-w-0 text-left text-sm leading-tight font-medium"
                                    >Settings</span
                                >
                            </button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent
                            side="right"
                            align="end"
                            :side-offset="12"
                            class="w-80 rounded-2xl border border-stone-200 bg-white p-2 shadow-xl"
                        >
                            <DropdownMenuLabel
                                class="rounded-xl px-3 py-3 font-normal"
                            >
                                <div
                                    class="text-[11px] tracking-[0.22em] text-stone-400 uppercase"
                                >
                                    Settings
                                </div>
                                <div
                                    class="mt-2 text-xs tracking-normal text-stone-600 normal-case"
                                >
                                    Office controls, licensing, staff access,
                                    and the Social / Meta lane live here.
                                </div>
                            </DropdownMenuLabel>

                            <DropdownMenuSeparator />

                            <DropdownMenuItem
                                v-for="item in settingsRailMenuItems"
                                :key="item.href"
                                :as-child="true"
                                class="rounded-xl px-3 py-3"
                            >
                                <Link
                                    :href="item.href"
                                    class="flex w-full items-center gap-3"
                                >
                                    <SocialPlatformMark
                                        v-if="item.socialBrand === 'meta'"
                                        brand="meta"
                                        compact
                                        monochrome
                                        class="text-[#0866ff]"
                                    />
                                    <FontAwesomeIcon
                                        v-else-if="item.icon"
                                        :icon="item.icon"
                                        class="text-stone-500"
                                    />
                                    <span class="font-medium text-stone-900">{{
                                        item.label
                                    }}</span>
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </aside>

            <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                <div
                    class="flex h-[52px] shrink-0 items-center justify-between border-b border-stone-300/70 bg-stone-50/85 px-4 backdrop-blur"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-4">
                        <div class="h-7 w-1 rounded-full bg-amber-400" />
                        <div class="min-w-0">
                            <p
                                class="truncate text-[11px] font-medium tracking-[0.36em] text-stone-500 uppercase"
                            >
                                {{ headerEyebrow }}
                            </p>
                            <div class="flex min-w-0 items-center gap-2">
                                <p
                                    class="truncate text-sm font-semibold text-stone-900"
                                >
                                    {{
                                        clientName ??
                                        'Local-first credit operations'
                                    }}
                                </p>

                                <DropdownMenu v-if="isClientWorkspace">
                                    <DropdownMenuTrigger :as-child="true">
                                        <button
                                            type="button"
                                            class="inline-flex shrink-0 items-center gap-1 rounded-full border border-stone-300 bg-white/85 px-2.5 py-1 text-[10px] font-semibold tracking-[0.18em] text-stone-600 uppercase transition hover:border-amber-400 hover:text-stone-950 data-[state=open]:border-amber-400 data-[state=open]:text-stone-950"
                                            aria-label="Change client roster stage"
                                        >
                                            {{ currentClientStageLabel }}
                                            <FontAwesomeIcon
                                                :icon="faChevronDown"
                                                class="text-[0.6rem]"
                                            />
                                        </button>
                                    </DropdownMenuTrigger>

                                    <DropdownMenuContent
                                        align="start"
                                        class="w-52 rounded-2xl border border-stone-200 bg-white p-2 text-stone-900 shadow-xl"
                                    >
                                        <DropdownMenuLabel
                                            class="px-3 py-2 text-[10px] font-semibold tracking-[0.22em] text-stone-400 uppercase"
                                        >
                                            Roster stage
                                        </DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            v-for="stage in clientStageOptions"
                                            :key="stage.value"
                                            :as-child="true"
                                            class="rounded-xl px-3 py-2"
                                        >
                                            <Link
                                                :href="
                                                    clientStageHref(stage.value)
                                                "
                                                class="flex w-full items-center justify-between gap-3 text-sm"
                                            >
                                                <span>{{ stage.label }}</span>
                                                <span
                                                    v-if="
                                                        currentClientStage ===
                                                        stage.value
                                                    "
                                                    class="size-2 rounded-full bg-amber-400"
                                                    aria-hidden="true"
                                                />
                                            </Link>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>

                        <div
                            v-if="isClientWorkspace"
                            class="hidden min-w-0 flex-1 items-center gap-2 overflow-x-auto pl-2 md:flex"
                            aria-label="Client workspace navigation"
                        >
                            <Link
                                v-for="link in clientWorkspaceLinks"
                                :key="link.href"
                                :href="link.href"
                                class="rounded-full border px-3 py-1.5 text-[11px] font-medium tracking-[0.22em] uppercase transition"
                                :class="
                                    currentPath === link.path
                                        ? 'border-stone-950 bg-stone-950 text-stone-50'
                                        : 'border-stone-300 bg-white/80 text-stone-600 hover:border-stone-500 hover:text-stone-900'
                                "
                            >
                                {{ link.label }}
                            </Link>

                            <span
                                v-if="licenseRailLabel"
                                class="rounded-full px-3 py-1.5 text-[11px] font-medium tracking-[0.24em] uppercase"
                                :class="licenseContextTone"
                            >
                                {{ licenseRailLabel }}
                            </span>
                        </div>

                        <div
                            v-else
                            class="hidden items-center gap-2 overflow-x-auto md:flex"
                            aria-label="Context"
                        >
                            <span
                                v-for="item in headerContext"
                                :key="item.label"
                                class="rounded-full px-3 py-1.5 text-[11px] font-medium tracking-[0.24em] uppercase"
                                :class="item.tone"
                            >
                                {{ item.label }}
                            </span>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-3">
                        <DropdownMenu v-if="currentUser">
                            <DropdownMenuTrigger :as-child="true">
                                <button
                                    type="button"
                                    class="flex items-center gap-3 text-stone-700 transition hover:text-stone-950"
                                >
                                    <Avatar
                                        class="size-9 overflow-hidden rounded-full border border-stone-200/70 bg-white"
                                    >
                                        <AvatarImage
                                            :src="currentUser.gravatar_url"
                                            :alt="currentUser.name"
                                        />
                                        <AvatarFallback
                                            class="bg-stone-100 text-xs font-semibold text-stone-900"
                                        >
                                            {{ initials(currentUser.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div
                                        class="hidden min-w-0 text-left md:block"
                                    >
                                        <p
                                            class="truncate text-sm font-semibold text-stone-900"
                                        >
                                            {{ currentUser.name }}
                                        </p>
                                        <p
                                            class="truncate text-[11px] tracking-[0.2em] text-stone-500 uppercase"
                                        >
                                            {{ roleLabel }}
                                        </p>
                                    </div>
                                    <FontAwesomeIcon
                                        :icon="faChevronDown"
                                        class="hidden text-xs text-stone-500 md:block"
                                    />
                                </button>
                            </DropdownMenuTrigger>

                            <DropdownMenuContent
                                align="end"
                                class="w-80 rounded-2xl border border-stone-200 bg-white p-2 shadow-xl"
                            >
                                <DropdownMenuLabel
                                    class="rounded-xl px-3 py-3 font-normal"
                                >
                                    <div class="flex items-start gap-3">
                                        <Avatar
                                            class="size-12 overflow-hidden rounded-full border border-stone-200 bg-white"
                                        >
                                            <AvatarImage
                                                :src="currentUser.gravatar_url"
                                                :alt="currentUser.name"
                                            />
                                            <AvatarFallback
                                                class="bg-stone-100 text-sm font-semibold text-stone-900"
                                            >
                                                {{ initials(currentUser.name) }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div class="min-w-0">
                                            <p
                                                class="truncate font-semibold text-stone-950"
                                            >
                                                {{ currentUser.name }}
                                            </p>
                                            <p
                                                class="truncate text-sm text-stone-500"
                                            >
                                                {{ currentUser.email }}
                                            </p>
                                            <p
                                                class="mt-1 text-[11px] tracking-[0.2em] text-stone-500 uppercase"
                                            >
                                                {{ roleLabel }}
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        class="mt-3 grid gap-2 rounded-xl border border-stone-200 bg-stone-50 px-3 py-3 text-xs text-stone-600"
                                    >
                                        <p>
                                            <span
                                                class="font-medium text-stone-800"
                                                >Last login:</span
                                            >
                                            {{
                                                formatDateTime(
                                                    currentUser.last_login_at,
                                                )
                                            }}
                                        </p>
                                        <p>
                                            <span
                                                class="font-medium text-stone-800"
                                                >Last seen:</span
                                            >
                                            {{
                                                formatDateTime(
                                                    currentUser.last_seen_at,
                                                )
                                            }}
                                        </p>
                                    </div>
                                </DropdownMenuLabel>

                                <DropdownMenuSeparator />

                                <DropdownMenuItem
                                    :as-child="true"
                                    class="rounded-xl px-3 py-3"
                                >
                                    <Link
                                        href="/settings/profile"
                                        class="flex w-full items-center justify-between gap-3"
                                    >
                                        <span class="font-medium text-stone-900"
                                            >Account settings</span
                                        >
                                        <FontAwesomeIcon
                                            :icon="faGear"
                                            class="text-stone-500"
                                        />
                                    </Link>
                                </DropdownMenuItem>

                                <DropdownMenuItem
                                    v-if="canManageUsers"
                                    :as-child="true"
                                    class="rounded-xl px-3 py-3"
                                >
                                    <Link
                                        href="/billing"
                                        class="flex w-full items-center justify-between gap-3"
                                    >
                                        <span class="font-medium text-stone-900"
                                            >Billing and revenue</span
                                        >
                                        <FontAwesomeIcon
                                            :icon="faCreditCard"
                                            class="text-stone-500"
                                        />
                                    </Link>
                                </DropdownMenuItem>

                                <DropdownMenuItem
                                    v-if="canManageUsers"
                                    :as-child="true"
                                    class="rounded-xl px-3 py-3"
                                >
                                    <Link
                                        href="/settings/users"
                                        class="flex w-full items-center justify-between gap-3"
                                    >
                                        <span class="font-medium text-stone-900"
                                            >Accounts Manager</span
                                        >
                                        <FontAwesomeIcon
                                            :icon="faUsers"
                                            class="text-stone-500"
                                        />
                                    </Link>
                                </DropdownMenuItem>

                                <DropdownMenuSeparator />

                                <DropdownMenuItem
                                    class="rounded-xl px-3 py-3"
                                    @click="logout"
                                >
                                    <div
                                        class="flex w-full items-center justify-between gap-3"
                                    >
                                        <span class="font-medium text-stone-900"
                                            >Log out</span
                                        >
                                        <FontAwesomeIcon
                                            :icon="faRightFromBracket"
                                            class="text-stone-500"
                                        />
                                    </div>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                <div
                    v-if="updateBannerVisible"
                    class="shrink-0 border-b border-red-950 bg-red-700 px-4 py-3 text-white shadow-[0_10px_22px_rgba(127,29,29,0.22)] md:px-6"
                >
                    <div
                        class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
                    >
                        <div class="flex min-w-0 items-start gap-3">
                            <span
                                class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-white/14 text-white ring-1 ring-white/30"
                                aria-hidden="true"
                            >
                                <FontAwesomeIcon
                                    :icon="faTriangleExclamation"
                                />
                            </span>
                            <div class="min-w-0">
                                <p
                                    class="text-sm font-semibold tracking-[0.22em] uppercase"
                                >
                                    {{ updateBannerTitle }}
                                </p>
                                <p
                                    class="mt-1 max-w-5xl text-sm leading-5 text-red-50"
                                >
                                    {{ updateBannerDetail }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex shrink-0 flex-wrap items-center gap-2 pl-11 md:pl-0"
                        >
                            <Link
                                href="/settings/license"
                                class="inline-flex h-9 items-center gap-2 rounded-md bg-white px-3 text-xs font-semibold tracking-[0.18em] text-red-800 uppercase transition hover:bg-red-50"
                            >
                                <FontAwesomeIcon :icon="faDownload" />
                                Updates
                            </Link>
                            <a
                                v-if="browserCompanionEnabled"
                                :href="browserCompanionDownloadHref"
                                class="inline-flex h-9 items-center gap-2 rounded-md border border-white/35 px-3 text-xs font-semibold tracking-[0.18em] text-white uppercase transition hover:bg-white/10"
                            >
                                <FontAwesomeIcon :icon="faPuzzlePiece" />
                                Companion
                            </a>
                        </div>
                    </div>
                </div>

                <div
                    v-if="license.warning_active && license.rail_message"
                    class="shrink-0 border-b px-4 py-2 text-sm md:px-6"
                    :class="
                        license.access_state === 'locked'
                            ? 'border-rose-200 bg-rose-50 text-rose-800'
                            : 'border-amber-200 bg-amber-50 text-amber-900'
                    "
                >
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="font-semibold">{{
                            license.rail_message
                        }}</span>
                        <span
                            v-if="license.countdown_label"
                            class="rounded-full border px-2.5 py-1 text-[11px] font-medium tracking-[0.18em] uppercase"
                            :class="
                                license.access_state === 'locked'
                                    ? 'border-rose-300 bg-white text-rose-700'
                                    : 'border-amber-300 bg-white text-amber-800'
                            "
                        >
                            {{ license.countdown_label }}
                        </span>
                        <span
                            v-if="license.grace_ends_label"
                            class="text-xs tracking-[0.18em] uppercase opacity-75"
                        >
                            Grace ends {{ license.grace_ends_label }}
                        </span>
                    </div>
                </div>

                <main
                    class="min-h-0 flex-1 overscroll-contain"
                    :class="
                        isCrmPage
                            ? 'overflow-hidden p-0'
                            : isSettingsPage
                              ? 'overflow-y-auto px-4 pt-2 pb-5 md:px-6 md:pt-3 md:pb-6'
                              : 'overflow-y-auto px-4 py-5 md:px-6 md:py-6'
                    "
                >
                    <slot />
                </main>

                <div
                    class="relative z-30 flex h-[46px] shrink-0 items-center justify-between gap-3 overflow-hidden border-t border-stone-300/80 bg-stone-200/90 px-3 py-1.5 pr-20 text-stone-800 shadow-[0_-10px_24px_rgba(28,25,23,0.10)] backdrop-blur md:pr-24"
                >
                    <div
                        class="flex min-w-0 flex-1 items-center gap-2 overflow-hidden text-[10px] text-stone-600"
                    >
                        <TooltipProvider :delay-duration="0">
                            <Tooltip>
                                <DropdownMenu>
                                    <TooltipTrigger as-child>
                                        <DropdownMenuTrigger :as-child="true">
                                            <button
                                                type="button"
                                                class="flex size-8 shrink-0 items-center justify-center rounded-full text-stone-500 transition hover:bg-stone-100 hover:text-stone-950"
                                            >
                                                <FontAwesomeIcon
                                                    :icon="faCircleQuestion"
                                                />
                                            </button>
                                        </DropdownMenuTrigger>
                                    </TooltipTrigger>

                                    <TooltipContent
                                        variant="light-card"
                                        side="left"
                                        align="start"
                                        :side-offset="12"
                                        class="max-w-sm px-0 py-0"
                                    >
                                        <div class="space-y-0">
                                            <div
                                                class="border-b border-stone-200/80 px-4 py-3"
                                            >
                                                <div
                                                    class="text-[11px] tracking-[0.24em] text-stone-500 uppercase"
                                                >
                                                    Storage snapshot
                                                </div>
                                                <div
                                                    class="mt-1 flex items-baseline gap-2"
                                                >
                                                    <span
                                                        class="text-lg font-semibold text-stone-950"
                                                        >{{
                                                            storageHealth
                                                                .database
                                                                .driver_label
                                                        }}</span
                                                    >
                                                    <span
                                                        class="text-sm font-medium text-amber-700"
                                                        >{{
                                                            storageHealth
                                                                .database
                                                                .size_label
                                                        }}</span
                                                    >
                                                </div>
                                            </div>
                                            <div
                                                class="grid gap-3 px-4 py-3 text-xs tracking-normal text-stone-700 normal-case"
                                            >
                                                <div
                                                    class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1"
                                                >
                                                    <span
                                                        class="font-semibold tracking-[0.18em] text-stone-500 uppercase"
                                                        >Docs</span
                                                    >
                                                    <span
                                                        >{{
                                                            storageHealth
                                                                .documents.count
                                                        }}
                                                        on disk</span
                                                    >
                                                    <span
                                                        class="font-semibold tracking-[0.18em] text-stone-500 uppercase"
                                                        >Backup</span
                                                    >
                                                    <span>{{
                                                        backupRunningTarget
                                                            ? 'Running now'
                                                            : (storageHealth
                                                                  .database
                                                                  .last_backup_label ??
                                                              'No backup yet')
                                                    }}</span>
                                                    <span
                                                        class="font-semibold tracking-[0.18em] text-stone-500 uppercase"
                                                        >Storage mode</span
                                                    >
                                                    <span>{{
                                                        storageHealth.documents
                                                            .stored_in_database
                                                            ? 'Database'
                                                            : 'Filesystem'
                                                    }}</span>
                                                    <span
                                                        class="font-semibold tracking-[0.18em] text-stone-500 uppercase"
                                                        >Cluster</span
                                                    >
                                                    <span>{{
                                                        storageHealth.cluster
                                                            .enabled
                                                            ? `${storageHealth.cluster.peer_count} peers ready`
                                                            : 'No peers configured'
                                                    }}</span>
                                                </div>
                                                <div
                                                    class="rounded-xl border border-stone-200 bg-white/90 px-3 py-2 text-[11px] leading-5 text-stone-600 shadow-sm"
                                                >
                                                    Customer uploads live on
                                                    disk, not inside the
                                                    application database. Click
                                                    for backup and handoff
                                                    actions, or open CTO for the
                                                    full hardware view.
                                                </div>
                                            </div>
                                        </div>
                                    </TooltipContent>

                                    <DropdownMenuContent
                                        align="start"
                                        class="w-80 rounded-2xl border border-stone-200 bg-white p-2 shadow-xl"
                                    >
                                        <DropdownMenuLabel
                                            class="rounded-xl px-3 py-3 font-normal"
                                        >
                                            <div
                                                class="text-[11px] tracking-[0.22em] text-stone-400 uppercase"
                                            >
                                                Storage and backup
                                            </div>
                                            <div
                                                class="mt-2 grid gap-2 text-xs tracking-normal text-stone-600 normal-case"
                                            >
                                                <div>
                                                    <div
                                                        class="font-semibold text-stone-900"
                                                    >
                                                        {{
                                                            storageHealth
                                                                .database
                                                                .driver_label
                                                        }}
                                                        {{
                                                            storageHealth
                                                                .database
                                                                .size_label
                                                        }}
                                                    </div>
                                                    <div>
                                                        Database engine and
                                                        current file size.
                                                    </div>
                                                </div>
                                                <div>
                                                    <div
                                                        class="font-semibold text-stone-900"
                                                    >
                                                        {{
                                                            storageHealth
                                                                .documents.count
                                                        }}
                                                        docs on disk
                                                    </div>
                                                    <div>
                                                        Client uploads live in
                                                        the filesystem, not
                                                        inside the application
                                                        database.
                                                    </div>
                                                </div>
                                                <div>
                                                    <div
                                                        class="font-semibold text-stone-900"
                                                    >
                                                        {{
                                                            backupRunningTarget
                                                                ? 'Backup running now'
                                                                : storageHealth
                                                                        .database
                                                                        .last_backup_label
                                                                  ? `Last backup ${storageHealth.database.last_backup_label}`
                                                                  : 'No backup yet'
                                                        }}
                                                    </div>
                                                    <div
                                                        v-if="
                                                            storageHealth
                                                                .cluster.enabled
                                                        "
                                                    >
                                                        Cluster peers ready:
                                                        {{
                                                            storageHealth
                                                                .cluster
                                                                .peer_count
                                                        }}
                                                    </div>
                                                    <div v-else>
                                                        No cluster peers
                                                        configured yet.
                                                    </div>
                                                </div>
                                            </div>
                                        </DropdownMenuLabel>

                                        <DropdownMenuSeparator />

                                        <DropdownMenuItem
                                            class="rounded-xl px-3 py-3"
                                            @select.prevent="
                                                runDatabaseBackup('local')
                                            "
                                        >
                                            <ConnectivityBrandMark
                                                brand="local"
                                                compact
                                            />
                                            <div
                                                class="ml-2 flex flex-col gap-1 text-left"
                                            >
                                                <span
                                                    class="text-[11px] font-semibold tracking-[0.22em] text-stone-700 uppercase"
                                                    >Local backup</span
                                                >
                                                <span
                                                    class="text-xs tracking-normal text-stone-500 normal-case"
                                                >
                                                    {{
                                                        localBackupDirectoryName
                                                            ? `Save a fresh database copy into ${localBackupDirectoryName}.`
                                                            : 'Create a fresh local database backup zip.'
                                                    }}
                                                </span>
                                            </div>
                                        </DropdownMenuItem>

                                        <DropdownMenuItem
                                            class="rounded-xl px-3 py-3"
                                            @select.prevent="
                                                runDatabaseBackup('wasabi')
                                            "
                                        >
                                            <ConnectivityBrandMark
                                                brand="wasabi"
                                                compact
                                            />
                                            <div
                                                class="ml-2 flex flex-col gap-1 text-left"
                                            >
                                                <span
                                                    class="text-[11px] font-semibold tracking-[0.22em] text-stone-700 uppercase"
                                                    >Wasabi archive</span
                                                >
                                                <span
                                                    class="text-xs tracking-normal text-stone-500 normal-case"
                                                >
                                                    {{
                                                        connectivity.wasabi
                                                            .enabled
                                                            ? 'Push the backup into the Wasabi archive lane.'
                                                            : 'Create the backup now and stage it until Wasabi is configured.'
                                                    }}
                                                </span>
                                            </div>
                                        </DropdownMenuItem>

                                        <DropdownMenuItem
                                            class="rounded-xl px-3 py-3"
                                            @select.prevent="
                                                runDatabaseBackup('dropbox')
                                            "
                                        >
                                            <ConnectivityBrandMark
                                                brand="dropbox"
                                                compact
                                            />
                                            <div
                                                class="ml-2 flex flex-col gap-1 text-left"
                                            >
                                                <span
                                                    class="text-[11px] font-semibold tracking-[0.22em] text-stone-700 uppercase"
                                                    >Dropbox handoff</span
                                                >
                                                <span
                                                    class="text-xs tracking-normal text-stone-500 normal-case"
                                                >
                                                    {{
                                                        connectivity.dropbox
                                                            .enabled
                                                            ? 'Stage a backup and customer-upload handoff for Dropbox.'
                                                            : 'Create the backup now and stage it until Dropbox is connected.'
                                                    }}
                                                </span>
                                            </div>
                                        </DropdownMenuItem>

                                        <DropdownMenuItem
                                            class="rounded-xl px-3 py-3"
                                            @select.prevent="
                                                runDatabaseBackup(
                                                    'google_drive',
                                                )
                                            "
                                        >
                                            <ConnectivityBrandMark
                                                brand="google_drive"
                                                compact
                                            />
                                            <div
                                                class="ml-2 flex flex-col gap-1 text-left"
                                            >
                                                <span
                                                    class="text-[11px] font-semibold tracking-[0.22em] text-stone-700 uppercase"
                                                    >Google Drive handoff</span
                                                >
                                                <span
                                                    class="text-xs tracking-normal text-stone-500 normal-case"
                                                >
                                                    {{
                                                        connectivity
                                                            .google_drive
                                                            .enabled
                                                            ? 'Stage a backup and customer-upload handoff for Google Drive.'
                                                            : 'Create the backup now and stage it until Google Drive is connected.'
                                                    }}
                                                </span>
                                            </div>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </Tooltip>
                        </TooltipProvider>
                        <Link
                            href="/cto"
                            class="flex h-8 shrink-0 items-center gap-1.5 rounded-full border border-stone-300 bg-stone-100/80 px-2.5 font-semibold tracking-[0.12em] text-stone-700 uppercase transition hover:border-stone-400 hover:bg-white"
                        >
                            <FontAwesomeIcon
                                :icon="faDatabase"
                                class="text-stone-500"
                            />
                            <span>DB Size</span>
                            <span class="text-stone-950">{{
                                storageHealth.database.size_label
                            }}</span>
                        </Link>
                        <TooltipProvider :delay-duration="0">
                            <Tooltip
                                v-for="(brand, index) in footerConnectorBrands"
                                :key="brand"
                            >
                                <TooltipTrigger as-child>
                                    <button
                                        type="button"
                                        class="flex h-8 shrink-0 items-center gap-1.5 px-1 text-stone-700 transition hover:text-stone-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300"
                                    >
                                        <ConnectivityBrandMark
                                            :brand="brand"
                                            compact
                                        />
                                        <span
                                            class="inline-block h-2.5 w-2.5 rounded-full"
                                            :class="
                                                footerConnectorDotClass(
                                                    footerConnectorMeta(brand)
                                                        .enabled,
                                                )
                                            "
                                        />
                                    </button>
                                </TooltipTrigger>

                                <TooltipContent
                                    variant="light-card"
                                    side="left"
                                    :align="footerTooltipAlign(index)"
                                    :side-offset="12"
                                    class="max-w-xs px-0 py-0"
                                >
                                    <div class="space-y-0">
                                        <div
                                            class="border-b border-stone-200/80 px-4 py-3"
                                        >
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <ConnectivityBrandMark
                                                    :brand="brand"
                                                    compact
                                                />
                                                <span
                                                    class="text-sm font-semibold text-stone-950"
                                                >
                                                    {{
                                                        footerConnectorMeta(
                                                            brand,
                                                        ).label
                                                    }}
                                                </span>
                                                <span
                                                    class="ml-auto inline-block h-2.5 w-2.5 rounded-full"
                                                    :class="
                                                        footerConnectorDotClass(
                                                            footerConnectorMeta(
                                                                brand,
                                                            ).enabled,
                                                        )
                                                    "
                                                />
                                            </div>
                                        </div>
                                        <div
                                            class="px-4 py-3 text-xs tracking-normal text-stone-700 normal-case"
                                        >
                                            <div
                                                class="mb-2 text-[11px] font-semibold tracking-[0.2em] uppercase"
                                                :class="
                                                    footerConnectorMeta(brand)
                                                        .enabled
                                                        ? 'text-emerald-700'
                                                        : 'text-rose-700'
                                                "
                                            >
                                                {{
                                                    footerConnectorMeta(brand)
                                                        .enabled
                                                        ? 'Enabled'
                                                        : 'Disabled'
                                                }}
                                            </div>
                                            <div
                                                class="leading-5 text-stone-600"
                                            >
                                                {{
                                                    footerConnectorMeta(brand)
                                                        .detail
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                        <span
                            v-if="license.warning_active && licenseRailLabel"
                            class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-medium"
                            :class="
                                license.access_state === 'locked'
                                    ? 'border-rose-300 bg-rose-100 text-rose-700'
                                    : 'border-amber-300 bg-amber-100 text-amber-800'
                            "
                        >
                            {{ licenseRailLabel }}
                        </span>
                        <span
                            v-if="updateGuidance"
                            class="hidden max-w-[24rem] truncate text-[10px] tracking-normal text-stone-600 normal-case 2xl:inline"
                        >
                            {{ updateGuidance }}
                        </span>
                    </div>

                    <div
                        class="flex shrink-0 items-center gap-1.5 overflow-x-auto"
                    >
                        <Link
                            href="/settings/license"
                            class="group flex h-8 shrink-0 items-center gap-1 rounded-full border border-stone-300 bg-stone-100/80 px-2 transition hover:border-stone-500 hover:bg-white"
                            title="Open office updates"
                        >
                            <span
                                v-if="versionLabel"
                                class="text-[10px] font-semibold tracking-[0.12em] text-stone-700 uppercase"
                            >
                                {{ versionLabel }}
                            </span>
                            <span v-if="versionLabel" class="text-stone-300">
                                ·
                            </span>
                            <FontAwesomeIcon
                                v-if="updates.update_available"
                                :icon="faDownload"
                                class="text-emerald-600"
                                aria-hidden="true"
                            />
                            <span
                                class="text-[10px] font-semibold tracking-[0.12em] text-stone-700 uppercase transition group-hover:text-stone-950"
                            >
                                {{ updateStatusLabel }}
                            </span>
                        </Link>
                        <button
                            v-if="
                                pwaState.localInstallHost && pwaState.canInstall
                            "
                            type="button"
                            class="flex h-8 shrink-0 items-center gap-1.5 rounded-full border border-stone-300 bg-stone-100/70 px-2.5 text-[10px] font-medium tracking-[0.12em] text-stone-600 uppercase transition hover:border-stone-500 hover:bg-white hover:text-stone-950 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="pwaState.prompting"
                            @click="install"
                        >
                            <FontAwesomeIcon :icon="faDownload" />
                            {{ installLabel }}
                        </button>
                        <button
                            type="button"
                            class="flex h-8 shrink-0 items-center gap-1.5 rounded-full border border-stone-300 bg-stone-100/70 px-2.5 text-[10px] font-medium tracking-[0.12em] text-stone-600 uppercase transition hover:border-stone-500 hover:bg-white hover:text-stone-950"
                            @click="reloadConfig"
                        >
                            <FontAwesomeIcon :icon="faArrowsRotate" />
                            Reload
                        </button>
                        <Link
                            href="/violations"
                            class="flex h-8 shrink-0 items-center gap-1.5 rounded-full border border-stone-300 bg-stone-100/70 px-2.5 text-[10px] font-medium tracking-[0.12em] text-stone-600 uppercase transition hover:border-stone-500 hover:bg-white hover:text-stone-950"
                        >
                            <FontAwesomeIcon :icon="faTriangleExclamation" />
                            Compliance
                        </Link>
                    </div>
                </div>

                <nav
                    class="relative z-30 grid shrink-0 grid-cols-7 border-t border-stone-300/70 bg-stone-950 px-2 py-2 lg:hidden"
                    aria-label="Mobile primary"
                >
                    <Link
                        v-for="item in leftRail.slice(0, 7)"
                        :key="item.href"
                        :href="item.href"
                        class="relative flex flex-col items-center gap-1 rounded-xl py-2 text-[10px] tracking-[0.18em] text-stone-300 uppercase"
                        :class="
                            isRailItemActive(item)
                                ? 'bg-stone-800 text-stone-50'
                                : ''
                        "
                    >
                        <SocialPlatformMark
                            v-if="item.socialBrand === 'meta'"
                            brand="meta"
                            compact
                            monochrome
                        />
                        <FontAwesomeIcon
                            v-else-if="item.icon"
                            :icon="item.icon"
                            :style="mobileRailIconStyle"
                        />
                        <span>{{ item.label }}</span>
                        <span
                            v-if="railBadgeText(item.badge)"
                            class="absolute top-1 right-2 inline-flex min-h-[18px] min-w-[26px] items-center justify-center rounded-full px-[5px] text-[10px] leading-none font-bold shadow-sm"
                            :class="badgeTone(item.badge)"
                        >
                            {{ railBadgeText(item.badge) }}
                        </span>
                    </Link>
                </nav>
            </div>
        </div>

        <Toaster />
        <AiAssistantDrawer />
    </div>
</template>
