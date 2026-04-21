<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import ConnectivityBrandMark from '@/components/creditsoft/ConnectivityBrandMark.vue';
import DashboardWorkspaceNav from '@/components/creditsoft/DashboardWorkspaceNav.vue';
import {
    faArrowUpRightFromSquare,
    faCircleCheck,
    faPlugCircleBolt,
    faKey,
    faTriangleExclamation,
} from '@fortawesome/free-solid-svg-icons';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Connectivity settings',
                href: '/settings/connectivity',
            },
        ],
    },
});

const props = defineProps<{
    tunnels: {
        tailscale: {
            required: boolean;
            hostname: string;
            tailnet: string;
        };
        ngrok: {
            enabled: boolean;
            api_only: boolean;
            has_authtoken: boolean;
            has_api_key: boolean;
            public_base_url?: string | null;
        };
    };
    runtime: {
        tailscale: {
            installed: boolean;
            running: boolean;
            version?: string | null;
            hostname?: string | null;
            dns_name?: string | null;
            ipv4?: string | null;
            ipv6?: string | null;
            tailnet?: string | null;
            tailnet_name?: string | null;
            reason?: string | null;
            credentials: {
                has_api_key: boolean;
                masked_api_key?: string | null;
                expires_at?: string | null;
                expires_label?: string | null;
                warning_days: number;
                reminder_starts_at?: string | null;
                reminder_starts_label?: string | null;
                reminder_active: boolean;
                days_until_expiry?: number | null;
                status_summary: string;
                rotation_url: string;
            };
        };
        ngrok: {
            installed: boolean;
            config_path: string;
            config_exists: boolean;
            host_authtoken_saved: boolean;
            host_api_key_saved: boolean;
            validated: boolean;
            masked_authtoken?: string | null;
            masked_api_key?: string | null;
            running: boolean;
            active_public_url?: string | null;
            message: string;
        };
    };
    apiAccess: {
        enabled: boolean;
        legacy_masked_token?: string | null;
        generated_legacy_token?: string | null;
        local_base_url: string;
        tailnet_base_url?: string | null;
        public_base_url?: string | null;
        preferred_base_url: string;
        docs_url: string;
        spec_url: string;
    };
    portalAccess: {
        local_base_url: string;
        public_base_url?: string | null;
        tailnet_base_url?: string | null;
        preferred_base_url: string;
        login_url: string;
        dashboard_url: string;
        installer_url: string;
        home_url: string;
    };
    feedback: {
        portal_sync_enabled: boolean;
        report_feedback_enabled: boolean;
    };
}>();

const form = useForm({
    tailscale_required: props.tunnels.tailscale.required,
    tailscale_hostname: props.tunnels.tailscale.hostname,
    tailscale_tailnet: props.tunnels.tailscale.tailnet,
    tailscale_api_key: '',
    tailscale_api_key_expires_at:
        props.runtime.tailscale.credentials.expires_at ?? '',
    ngrok_enabled: props.tunnels.ngrok.enabled,
    ngrok_api_only: props.tunnels.ngrok.api_only,
    ngrok_authtoken: '',
    ngrok_api_key: '',
    api_enabled: props.apiAccess.enabled,
    rotate_api_token: false,
    report_feedback_enabled: props.feedback.report_feedback_enabled,
});

const websiteKeyForm = useForm({});

const tailscaleCredential = computed(() => props.runtime.tailscale.credentials);
const ngrokWebhookBase = computed(
    () => props.apiAccess.public_base_url ?? null,
);
const portalBase = computed(
    () =>
        props.portalAccess.public_base_url ??
        props.portalAccess.tailnet_base_url ??
        props.portalAccess.local_base_url,
);
const portalIsPublic = computed(() =>
    Boolean(props.portalAccess.public_base_url),
);
const portalBlockedByApiOnly = computed(
    () => props.tunnels.ngrok.enabled && props.tunnels.ngrok.api_only,
);
const ngrokEnabledButStopped = computed(
    () =>
        form.ngrok_enabled &&
        props.runtime.ngrok.host_authtoken_saved &&
        !props.runtime.ngrok.running,
);
const localOnlyMode = computed(
    () => !form.ngrok_enabled && !props.runtime.ngrok.running,
);
const ngrokStateTone = computed(() => {
    if (props.runtime.ngrok.running) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-900';
    }

    if (ngrokEnabledButStopped.value) {
        return 'border-amber-200 bg-amber-50 text-amber-900';
    }

    return 'border-stone-200 bg-stone-50 text-stone-900';
});
const ngrokStateTitle = computed(() => {
    if (props.runtime.ngrok.running) {
        return portalBlockedByApiOnly.value
            ? 'Public callback lane is live in API-only mode.'
            : 'Public callback lane is live.';
    }

    if (ngrokEnabledButStopped.value) {
        return 'Ngrok is enabled, but the public tunnel is not live.';
    }

    return 'Offline / local-only mode is active.';
});
const ngrokStateBody = computed(() => {
    if (props.runtime.ngrok.running) {
        return portalBlockedByApiOnly.value
            ? 'Webhooks and callbacks can reach the API, but the portal itself stays private until API-only mode is turned off.'
            : 'External callbacks, public demos, and portal links can reach this installation right now.';
    }

    if (ngrokEnabledButStopped.value) {
        return 'CreditSoft is still reachable locally and over Tailscale, but outside callbacks will fail until ngrok comes back up. Opening this page should retry the tunnel automatically.';
    }

    return props.runtime.tailscale.running
        ? 'This installation is private to this machine and approved Tailscale devices. Meta callbacks, webhooks, and public demos stay off until ngrok is enabled and live.'
        : 'This installation is private to this machine right now. Meta callbacks, webhooks, and public demos stay off until ngrok is enabled and live.';
});
const demoFlowStepOne = computed(() => {
    if (portalBlockedByApiOnly.value) {
        return 'Turn off API-only mode before sending the login link.';
    }

    if (!props.runtime.ngrok.running) {
        return props.runtime.tailscale.running
            ? 'Use localhost or the Tailscale address for internal demos. Public login links will not work until ngrok is live.'
            : 'Use localhost on this machine for demos. Public login links will not work until ngrok is live.';
    }

    return 'Send the login link.';
});
const demoFlowFootnote = computed(() => {
    if (portalBlockedByApiOnly.value) {
        return 'Public ngrok access is restricted to the API right now.';
    }

    if (portalIsPublic.value) {
        return 'This share path is live through ngrok.';
    }

    return props.runtime.tailscale.running
        ? 'Offline / local-only mode is active. Use localhost or Tailscale until you want a public demo lane.'
        : 'Offline / local-only mode is active. Use localhost on this machine until you want a public demo lane.';
});

const createWebsiteApiKey = () => {
    websiteKeyForm.post('/settings/connectivity/website-key', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Connectivity Settings" />

    <div class="space-y-6">
        <DashboardWorkspaceNav />

        <section class="space-y-2">
            <h1 class="text-xl font-semibold text-stone-950">
                Connectivity and partner API
            </h1>
            <p class="max-w-3xl text-sm leading-6 text-stone-600">
                Private staff access, portal demo links, and API credentials for
                the website, portal, and browser plugin.
            </p>
        </section>

        <form
            class="space-y-6"
            @submit.prevent="
                form.put('/settings/connectivity', { preserveScroll: true })
            "
        >
            <section
                class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95"
            >
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between lg:gap-6"
                    >
                        <div>
                            <p
                                class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                            >
                                Privacy-safe feedback
                            </p>
                            <h2
                                class="mt-2 text-lg font-semibold text-stone-950"
                            >
                                Improve the algorithm without sending customer
                                identity
                            </h2>
                        </div>
                        <p
                            class="text-sm leading-6 text-stone-600 lg:flex-1 lg:text-[13px] lg:leading-5"
                        >
                            Report data stays pseudonymous and office-scoped.
                            Identity, contact details, notes, attachments, and
                            raw page dumps stay local.
                        </p>
                    </div>
                </div>

                <div class="grid gap-6 px-6 py-6 lg:grid-cols-[1.15fr_0.85fr]">
                    <div class="space-y-4">
                        <label
                            class="flex items-start gap-3 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <input
                                v-model="form.report_feedback_enabled"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                            />
                            <span class="space-y-1">
                                <span
                                    class="block text-sm font-medium text-stone-900"
                                    >Share privacy-safe report feedback</span
                                >
                                <span
                                    class="block text-sm leading-6 text-stone-600"
                                >
                                    Send Metro 2/report patterns, score
                                    movement, lead timing, and customer lifespan
                                    under an org-scoped ID so CreditSoft can
                                    improve rules and return better marketing
                                    reports.
                                </span>
                            </span>
                        </label>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div
                                class="rounded-[24px] border border-stone-200 bg-white px-4 py-4"
                            >
                                <p
                                    class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                                >
                                    Shared
                                </p>
                                <p
                                    class="mt-2 text-sm leading-6 text-stone-900"
                                >
                                    Report structure, score changes,
                                    negative/open/closed counts, customer age,
                                    and lead-to-conversion timing.
                                </p>
                            </div>
                            <div
                                class="rounded-[24px] border border-stone-200 bg-white px-4 py-4"
                            >
                                <p
                                    class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                                >
                                    Never shared
                                </p>
                                <p
                                    class="mt-2 text-sm leading-6 text-stone-900"
                                >
                                    Names, emails, phone numbers, addresses,
                                    notes, attachments, raw reports, and
                                    provider passwords.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="rounded-[24px] border border-stone-200 bg-stone-50 px-5 py-5"
                    >
                        <p
                            class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >
                            Current mode
                        </p>
                        <p class="mt-3 text-sm font-medium text-stone-900">
                            {{
                                form.report_feedback_enabled
                                    ? 'Privacy-safe feedback is on.'
                                    : 'This office is keeping analytics feedback local only.'
                            }}
                        </p>
                        <p class="mt-3 text-sm leading-6 text-stone-600">
                            {{
                                form.report_feedback_enabled
                                    ? 'Each synced payload uses an org-scoped person ID instead of customer identity.'
                                    : 'Turn this on only if you want CreditSoft learning and marketing benchmarks from this installation.'
                            }}
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95"
            >
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between lg:gap-6"
                    >
                        <ConnectivityBrandMark brand="tailscale" large />
                        <p
                            class="text-sm leading-6 text-stone-600 lg:flex-1 lg:text-[13px] lg:leading-5"
                        >
                            Private staff access for devices on your tailnet.
                        </p>
                    </div>
                </div>

                <div class="grid gap-6 px-6 py-6 lg:grid-cols-[1.2fr_0.8fr]">
                    <div class="space-y-5">
                        <label
                            class="flex items-start gap-3 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <input
                                v-model="form.tailscale_required"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                            />
                            <span class="space-y-1">
                                <span
                                    class="block text-sm font-medium text-stone-900"
                                    >Require Tailscale access for staff
                                    devices</span
                                >
                                <span
                                    class="block text-sm leading-6 text-stone-600"
                                >
                                    Keep the operator workspace private.
                                    External systems should use the
                                    token-protected API lane instead.
                                </span>
                            </span>
                        </label>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="space-y-2">
                                <span
                                    class="text-xs font-medium tracking-[0.22em] text-stone-500 uppercase"
                                    >Device name</span
                                >
                                <input
                                    v-model="form.tailscale_hostname"
                                    type="text"
                                    class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    :placeholder="
                                        props.runtime.tailscale.hostname ??
                                        'creditsoft-intranet'
                                    "
                                />
                                <span
                                    v-if="form.errors.tailscale_hostname"
                                    class="text-xs text-rose-700"
                                    >{{ form.errors.tailscale_hostname }}</span
                                >
                            </label>

                            <label class="space-y-2">
                                <span
                                    class="text-xs font-medium tracking-[0.22em] text-stone-500 uppercase"
                                    >Tailnet</span
                                >
                                <input
                                    v-model="form.tailscale_tailnet"
                                    type="text"
                                    class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    :placeholder="
                                        props.runtime.tailscale.tailnet ??
                                        'office-name.ts.net'
                                    "
                                />
                                <span class="text-xs text-stone-500"
                                    >Auto-filled from the local tailnet when
                                    available.</span
                                >
                            </label>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="space-y-2 md:col-span-2">
                                <span
                                    class="text-xs font-medium tracking-[0.22em] text-stone-500 uppercase"
                                    >Tailscale admin API key</span
                                >
                                <input
                                    v-model="form.tailscale_api_key"
                                    type="password"
                                    class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    :placeholder="
                                        tailscaleCredential.masked_api_key ??
                                        'Paste Tailscale admin API key'
                                    "
                                />
                                <span class="text-xs text-stone-500">
                                    Store the office Tailscale admin key here so
                                    CreditSoft can automate staff offboarding
                                    later.
                                </span>
                                <span
                                    v-if="tailscaleCredential.masked_api_key"
                                    class="text-xs font-medium text-stone-700"
                                >
                                    Saved API key on file:
                                    {{ tailscaleCredential.masked_api_key }}
                                </span>
                                <span
                                    v-if="form.errors.tailscale_api_key"
                                    class="text-xs text-rose-700"
                                    >{{ form.errors.tailscale_api_key }}</span
                                >
                            </label>

                            <label class="space-y-2">
                                <span
                                    class="text-xs font-medium tracking-[0.22em] text-stone-500 uppercase"
                                    >Key expires on</span
                                >
                                <input
                                    v-model="form.tailscale_api_key_expires_at"
                                    type="date"
                                    class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                />
                                <span class="text-xs text-stone-500">
                                    CreditSoft starts the daily reminder
                                    {{
                                        tailscaleCredential.reminder_starts_label ??
                                        `${tailscaleCredential.warning_days} days before expiry`
                                    }}.
                                </span>
                                <span
                                    v-if="
                                        form.errors.tailscale_api_key_expires_at
                                    "
                                    class="text-xs text-rose-700"
                                    >{{
                                        form.errors.tailscale_api_key_expires_at
                                    }}</span
                                >
                            </label>
                        </div>
                    </div>

                    <div
                        class="rounded-[24px] border border-stone-200 bg-stone-50 px-5 py-5"
                    >
                        <p
                            class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >
                            Detected on this machine
                        </p>

                        <template v-if="props.runtime.tailscale.installed">
                            <p class="mt-3 text-sm font-medium text-stone-900">
                                {{
                                    props.runtime.tailscale.running
                                        ? 'Connected now'
                                        : 'Installed, but not connected'
                                }}
                            </p>

                            <dl class="mt-4 space-y-3 text-sm">
                                <div class="space-y-1">
                                    <dt
                                        class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                                    >
                                        Device
                                    </dt>
                                    <dd class="break-all text-stone-900">
                                        {{
                                            props.runtime.tailscale.hostname ??
                                            'Unknown device'
                                        }}
                                    </dd>
                                </div>
                                <div class="space-y-1">
                                    <dt
                                        class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                                    >
                                        IPv4
                                    </dt>
                                    <dd class="break-all text-stone-900">
                                        {{
                                            props.runtime.tailscale.ipv4 ??
                                            'No Tailscale IPv4 yet'
                                        }}
                                    </dd>
                                </div>
                                <div class="space-y-1">
                                    <dt
                                        class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                                    >
                                        MagicDNS
                                    </dt>
                                    <dd class="break-all text-stone-900">
                                        {{
                                            props.runtime.tailscale.dns_name ??
                                            'Not available'
                                        }}
                                    </dd>
                                </div>
                                <div class="space-y-1">
                                    <dt
                                        class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                                    >
                                        Tailnet
                                    </dt>
                                    <dd class="break-all text-stone-900">
                                        {{
                                            props.runtime.tailscale
                                                .tailnet_name ??
                                            props.runtime.tailscale.tailnet ??
                                            'Not available'
                                        }}
                                    </dd>
                                </div>
                            </dl>

                            <p class="mt-4 text-xs leading-5 text-stone-500">
                                Approved devices on this tailnet can reach the
                                office box once the app is deployed on a web
                                server that listens beyond localhost.
                            </p>

                            <div
                                class="mt-4 rounded-[20px] border border-stone-200 bg-white px-4 py-4"
                            >
                                <p
                                    class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                                >
                                    Admin API key
                                </p>
                                <p
                                    class="mt-2 text-sm font-medium text-stone-900"
                                >
                                    {{ tailscaleCredential.status_summary }}
                                </p>
                                <div
                                    class="mt-3 flex flex-wrap gap-3 text-xs text-stone-600"
                                >
                                    <span
                                        v-if="tailscaleCredential.expires_label"
                                        >Expires
                                        {{
                                            tailscaleCredential.expires_label
                                        }}</span
                                    >
                                    <span
                                        v-if="
                                            tailscaleCredential.reminder_starts_label
                                        "
                                        >Daily reminder starts
                                        {{
                                            tailscaleCredential.reminder_starts_label
                                        }}</span
                                    >
                                </div>
                                <a
                                    :href="tailscaleCredential.rotation_url"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="mt-3 inline-flex items-center gap-2 text-xs font-medium tracking-[0.18em] text-stone-500 uppercase underline underline-offset-4"
                                >
                                    Open Tailscale keys
                                    <FontAwesomeIcon
                                        :icon="faArrowUpRightFromSquare"
                                        class="text-[10px]"
                                    />
                                </a>
                            </div>
                        </template>

                        <template v-else>
                            <p class="mt-3 text-sm leading-6 text-stone-600">
                                {{
                                    props.runtime.tailscale.reason ??
                                    'Tailscale is not installed on this machine yet.'
                                }}
                            </p>
                        </template>
                    </div>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95"
            >
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between lg:gap-6"
                    >
                        <ConnectivityBrandMark brand="ngrok" large />
                        <p
                            class="text-sm leading-6 text-stone-600 lg:flex-1 lg:text-[13px] lg:leading-5"
                        >
                            Public callbacks for webhooks, forms, and the
                            portal.
                        </p>
                    </div>
                </div>

                <div class="grid gap-6 px-6 py-6 lg:grid-cols-[1.2fr_0.8fr]">
                    <div class="space-y-5">
                        <label
                            class="flex items-start gap-3 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <input
                                v-model="form.ngrok_enabled"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                            />
                            <span class="space-y-1">
                                <span
                                    class="block text-sm font-medium text-stone-900"
                                    >Enable ngrok callback lane</span
                                >
                                <span
                                    class="block text-sm leading-6 text-stone-600"
                                >
                                    Turn this on when you want trusted webhooks
                                    or the public portal to reach the local
                                    partner API.
                                </span>
                            </span>
                        </label>

                        <label
                            class="flex items-start gap-3 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <input
                                v-model="form.ngrok_api_only"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                            />
                            <span class="space-y-1">
                                <span
                                    class="block text-sm font-medium text-stone-900"
                                    >ngrok API-only mode</span
                                >
                                <span
                                    class="block text-sm leading-6 text-stone-600"
                                >
                                    Keep the public tunnel limited to `/api/*`.
                                    The portal stays private unless you switch
                                    this off for a demo.
                                </span>
                            </span>
                        </label>

                        <div class="space-y-4">
                            <label class="space-y-2">
                                <span
                                    class="text-xs font-medium tracking-[0.22em] text-stone-500 uppercase"
                                    >Ngrok authtoken</span
                                >
                                <input
                                    v-model="form.ngrok_authtoken"
                                    type="password"
                                    class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    :placeholder="
                                        props.runtime.ngrok.masked_authtoken ??
                                        (props.tunnels.ngrok.has_authtoken
                                            ? 'Saved token on file'
                                            : 'Paste ngrok authtoken')
                                    "
                                />
                                <span class="text-xs text-stone-500">
                                    Runs `ngrok config add-authtoken` on save.
                                    This is the local tunnel credential.
                                </span>
                            </label>

                            <label class="space-y-2">
                                <span
                                    class="text-xs font-medium tracking-[0.22em] text-stone-500 uppercase"
                                    >Ngrok API key</span
                                >
                                <input
                                    v-model="form.ngrok_api_key"
                                    type="password"
                                    class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900"
                                    :placeholder="
                                        props.runtime.ngrok.masked_api_key ??
                                        (props.tunnels.ngrok.has_api_key
                                            ? 'Saved API key on file'
                                            : 'Paste ngrok API key')
                                    "
                                />
                                <span class="text-xs text-stone-500">
                                    Runs `ngrok config add-api-key` on save.
                                    This is for ngrok API access.
                                </span>
                            </label>
                        </div>

                        <div
                            class="rounded-[20px] border px-4 py-3 text-sm"
                            :class="ngrokStateTone"
                        >
                            <p class="font-medium">{{ ngrokStateTitle }}</p>
                            <p class="mt-2 leading-6">{{ ngrokStateBody }}</p>
                        </div>
                    </div>

                    <div
                        class="rounded-[24px] border border-stone-200 bg-stone-50 px-5 py-5"
                    >
                        <div class="flex items-center gap-2">
                            <FontAwesomeIcon
                                :icon="
                                    props.runtime.ngrok.running
                                        ? faCircleCheck
                                        : props.runtime.ngrok
                                                .host_authtoken_saved
                                          ? faCircleCheck
                                          : faTriangleExclamation
                                "
                                :class="
                                    props.runtime.ngrok.running
                                        ? 'text-emerald-600'
                                        : props.runtime.ngrok
                                                .host_authtoken_saved
                                          ? 'text-stone-700'
                                          : 'text-amber-600'
                                "
                            />
                            <p class="text-sm font-medium text-stone-900">
                                {{
                                    props.runtime.ngrok.running
                                        ? 'ngrok live'
                                        : props.runtime.ngrok
                                                .host_authtoken_saved
                                          ? 'ngrok ready'
                                          : 'ngrok needs setup'
                                }}
                            </p>
                        </div>

                        <p class="mt-3 text-sm leading-6 text-stone-600">
                            {{
                                props.runtime.ngrok.running
                                    ? 'Public callbacks are live for this backend right now.'
                                    : ngrokEnabledButStopped
                                      ? 'The tunnel should auto-restart from this screen, but outside callers still cannot reach the backend yet.'
                                      : props.runtime.ngrok.host_authtoken_saved
                                        ? 'Credentials are saved. This installation is still local-only until ngrok is live.'
                                        : 'Add the ngrok authtoken to enable a live public callback lane.'
                            }}
                        </p>

                        <div class="mt-4 space-y-1">
                            <p
                                class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                            >
                                Public base
                            </p>
                            <p class="text-sm break-all text-stone-900">
                                {{
                                    ngrokWebhookBase ??
                                    (props.runtime.tailscale.running
                                        ? 'Offline / local-only mode. Use localhost or Tailscale until ngrok is live.'
                                        : 'Offline / local-only mode. Use localhost on this machine until ngrok is live.')
                                }}
                            </p>
                        </div>

                        <details
                            class="mt-4 rounded-[20px] border border-stone-200 bg-white px-4 py-3 text-sm text-stone-700"
                        >
                            <summary
                                class="cursor-pointer text-xs font-medium tracking-[0.18em] text-stone-500 uppercase select-none"
                            >
                                Advanced
                            </summary>

                            <dl class="mt-3 space-y-3">
                                <div class="space-y-1">
                                    <dt
                                        class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                                    >
                                        Tunnel status
                                    </dt>
                                    <dd class="text-stone-900">
                                        {{
                                            props.runtime.ngrok.running
                                                ? 'ngrok is running on this machine.'
                                                : 'No live ngrok tunnel is running yet.'
                                        }}
                                    </dd>
                                </div>
                                <div class="space-y-1">
                                    <dt
                                        class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                                    >
                                        Saved credentials
                                    </dt>
                                    <dd class="text-stone-900">
                                        {{
                                            props.runtime.ngrok
                                                .host_authtoken_saved
                                                ? 'Authtoken saved.'
                                                : 'Authtoken missing.'
                                        }}
                                        {{
                                            props.runtime.ngrok
                                                .host_api_key_saved
                                                ? ' API key saved.'
                                                : ' API key missing.'
                                        }}
                                    </dd>
                                </div>
                                <div class="space-y-1">
                                    <dt
                                        class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                                    >
                                        Validation
                                    </dt>
                                    <dd class="text-stone-900">
                                        {{
                                            props.runtime.ngrok.validated
                                                ? 'The local ngrok config file format is valid.'
                                                : 'Waiting for ngrok config file validation.'
                                        }}
                                    </dd>
                                </div>
                                <div class="space-y-1">
                                    <dt
                                        class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                                    >
                                        Config path
                                    </dt>
                                    <dd class="break-all text-stone-900">
                                        {{ props.runtime.ngrok.config_path }}
                                    </dd>
                                </div>
                            </dl>
                        </details>
                    </div>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95"
            >
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between lg:gap-6"
                    >
                        <div>
                            <p
                                class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                            >
                                Portal demo
                            </p>
                            <h2
                                class="mt-2 text-lg font-semibold text-stone-950"
                            >
                                Show the app itself
                            </h2>
                        </div>
                        <p
                            class="text-sm leading-6 text-stone-600 lg:flex-1 lg:text-[13px] lg:leading-5"
                        >
                            {{
                                portalBlockedByApiOnly
                                    ? 'Turn off API-only mode when you want the portal itself reachable through ngrok.'
                                    : 'Open the login page first, then jump into the dashboard or installer during the demo.'
                            }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-6 px-6 py-6 lg:grid-cols-[1.15fr_0.85fr]">
                    <div class="space-y-4">
                        <div
                            class="rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <p
                                class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                            >
                                Public demo base
                            </p>
                            <p
                                class="mt-2 text-sm font-medium break-all text-stone-900"
                            >
                                {{ portalBase }}
                            </p>
                            <p class="mt-2 text-xs leading-5 text-stone-500">
                                {{
                                    portalBlockedByApiOnly
                                        ? 'The host is live, but the portal is intentionally blocked while API-only mode is on.'
                                        : 'This is the host to share with friends. Use login for the first click.'
                                }}
                            </p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div
                                class="rounded-[24px] border border-stone-200 bg-white px-4 py-4"
                            >
                                <p
                                    class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                                >
                                    Shared
                                </p>
                                <p
                                    class="mt-2 text-sm leading-6 text-stone-900"
                                >
                                    Login, dashboard, installer, approved
                                    briefs, and non-PII portal feedback.
                                </p>
                            </div>
                            <div
                                class="rounded-[24px] border border-stone-200 bg-white px-4 py-4"
                            >
                                <p
                                    class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                                >
                                    Local only
                                </p>
                                <p
                                    class="mt-2 text-sm leading-6 text-stone-900"
                                >
                                    Raw reports, notes, attachments, private
                                    drafts, and office-only workflow details.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <a
                                :href="props.portalAccess.login_url"
                                target="_blank"
                                rel="noreferrer"
                                class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm font-medium text-stone-900 transition hover:border-stone-300 hover:bg-stone-50"
                                :class="{
                                    'pointer-events-none opacity-40':
                                        portalBlockedByApiOnly,
                                }"
                            >
                                Open login
                            </a>
                            <a
                                :href="props.portalAccess.dashboard_url"
                                target="_blank"
                                rel="noreferrer"
                                class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm font-medium text-stone-900 transition hover:border-stone-300 hover:bg-stone-50"
                                :class="{
                                    'pointer-events-none opacity-40':
                                        portalBlockedByApiOnly,
                                }"
                            >
                                Open dashboard
                            </a>
                            <a
                                :href="props.portalAccess.installer_url"
                                target="_blank"
                                rel="noreferrer"
                                class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm font-medium text-stone-900 transition hover:border-stone-300 hover:bg-stone-50"
                                :class="{
                                    'pointer-events-none opacity-40':
                                        portalBlockedByApiOnly,
                                }"
                            >
                                Open installer
                            </a>
                        </div>
                    </div>

                    <div
                        class="rounded-[24px] border border-stone-200 bg-stone-50 px-5 py-5"
                    >
                        <p
                            class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >
                            Demo flow
                        </p>
                        <ol
                            class="mt-3 space-y-3 text-sm leading-6 text-stone-700"
                        >
                            <li>
                                <span class="font-medium text-stone-900"
                                    >1.</span
                                >
                                {{ demoFlowStepOne }}
                            </li>
                            <li>
                                <span class="font-medium text-stone-900"
                                    >2.</span
                                >
                                After sign-in, open the dashboard or clients
                                view.
                            </li>
                            <li>
                                <span class="font-medium text-stone-900"
                                    >3.</span
                                >
                                Use the installer for a fresh setup walkthrough.
                            </li>
                        </ol>
                        <p class="mt-4 text-xs leading-5 text-stone-500">
                            {{ demoFlowFootnote }}
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/95"
            >
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between lg:gap-6"
                    >
                        <ConnectivityBrandMark brand="openapi" large />
                        <p
                            class="text-sm leading-6 text-stone-600 lg:flex-1 lg:text-[13px] lg:leading-5"
                        >
                            Lead intake, client status, dispute letters, and
                            shareable brief access through the local API. Any
                            valid CreditSoft API key works.
                        </p>
                    </div>
                </div>

                <div class="grid gap-6 px-6 py-6 lg:grid-cols-[1.15fr_0.85fr]">
                    <div class="space-y-5">
                        <div
                            class="rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <p
                                class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                            >
                                What shares out
                            </p>
                            <p class="mt-2 text-sm leading-6 text-stone-900">
                                Lead intake, case status, score snapshots,
                                approved briefs, and other non-PII portal data.
                            </p>
                            <p class="mt-2 text-xs leading-5 text-stone-500">
                                Raw reports, notes, attachments, and private
                                drafts stay on the local installation.
                            </p>
                        </div>

                        <label
                            class="flex items-start gap-3 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-4"
                        >
                            <input
                                v-model="form.api_enabled"
                                type="checkbox"
                                class="mt-1 h-4 w-4 rounded border-stone-400 text-stone-950"
                            />
                            <span class="space-y-1">
                                <span
                                    class="block text-sm font-medium text-stone-900"
                                    >Enable token-protected partner API</span
                                >
                                <span
                                    class="block text-sm leading-6 text-stone-600"
                                >
                                    This keeps writes and reads separate from
                                    the operator UI. The website can use the
                                    office key, and staff can use their own keys
                                    for the browser plugin or direct
                                    integrations.
                                </span>
                            </span>
                        </label>

                        <div
                            class="rounded-[24px] border border-stone-200 bg-white px-4 py-4"
                        >
                            <div class="flex items-center gap-2">
                                <FontAwesomeIcon
                                    :icon="faPlugCircleBolt"
                                    class="text-stone-700"
                                />
                                <p class="text-sm font-semibold text-stone-950">
                                    Personal client keys moved to Profile
                                </p>
                            </div>
                            <p class="mt-2 text-xs leading-5 text-stone-500">
                                Generate one personal key per staff device under
                                Profile settings. That keeps user pairing with
                                the user account, while this page stays focused
                                on office-wide Tailscale, ngrok, and the shared
                                website token.
                            </p>
                            <div
                                class="mt-4 rounded-[20px] border border-stone-200 bg-stone-50 px-4 py-4"
                            >
                                <p class="text-sm leading-6 text-stone-900">
                                    `127.0.0.1` only belongs to the host device.
                                    Another approved office device should pair
                                    with a personal key from Profile, then use
                                    Tailscale or the stable public API path
                                    instead of trying to reuse localhost.
                                </p>
                                <Link
                                    href="/settings/profile"
                                    class="mt-4 inline-flex items-center gap-2 rounded-full border border-stone-300 bg-white px-4 py-2 text-[11px] font-medium tracking-[0.18em] text-stone-700 uppercase transition hover:border-stone-500"
                                >
                                    <FontAwesomeIcon
                                        :icon="faArrowUpRightFromSquare"
                                    />
                                    Open profile pairing
                                </Link>
                            </div>
                        </div>

                        <details
                            class="rounded-[24px] border border-stone-200 bg-white px-4 py-4"
                        >
                            <summary
                                class="cursor-pointer text-sm font-medium text-stone-900"
                            >
                                Website and portal API key
                            </summary>
                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <div
                                    class="rounded-[20px] border border-stone-200 bg-stone-50 px-4 py-4"
                                >
                                    <p
                                        class="text-xs font-medium tracking-[0.22em] text-stone-500 uppercase"
                                    >
                                        Website token on file
                                    </p>
                                    <p
                                        class="mt-2 text-sm font-medium text-stone-900"
                                    >
                                        {{
                                            props.apiAccess
                                                .legacy_masked_token ??
                                            'No website token generated yet'
                                        }}
                                    </p>
                                    <p
                                        class="mt-2 text-xs leading-5 text-stone-500"
                                    >
                                        Use this for the website or shared
                                        portal/backend integration. Staff can
                                        still use it too, but personal keys are
                                        easier to revoke.
                                    </p>
                                </div>

                                <div
                                    class="rounded-[20px] border border-stone-200 bg-stone-50 px-4 py-4"
                                >
                                    <p
                                        class="text-sm font-medium text-stone-900"
                                    >
                                        Generate or rotate website key
                                    </p>
                                    <p
                                        class="mt-2 text-xs leading-5 text-stone-500"
                                    >
                                        Create the office-wide key used by the
                                        website, portal backend, or any shared
                                        integration. Any valid user key still
                                        works too.
                                    </p>
                                    <button
                                        type="button"
                                        class="mt-4 inline-flex items-center justify-center gap-2 rounded-full bg-stone-950 px-5 py-3 text-xs font-medium tracking-[0.2em] text-white uppercase transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:bg-stone-300"
                                        :disabled="websiteKeyForm.processing"
                                        @click="createWebsiteApiKey"
                                    >
                                        <FontAwesomeIcon :icon="faKey" />
                                        Generate website key
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="props.apiAccess.generated_legacy_token"
                                class="mt-4 rounded-[20px] border border-emerald-300 bg-emerald-50 px-4 py-4"
                            >
                                <div
                                    class="flex items-center gap-2 text-emerald-800"
                                >
                                    <FontAwesomeIcon :icon="faCircleCheck" />
                                    <p class="text-sm font-semibold">
                                        New website API key
                                    </p>
                                </div>
                                <p
                                    class="mt-3 rounded-2xl bg-white px-4 py-3 font-mono text-sm break-all text-stone-900"
                                >
                                    {{ props.apiAccess.generated_legacy_token }}
                                </p>
                            </div>
                        </details>
                    </div>

                    <div
                        class="space-y-4 rounded-[24px] border border-stone-200 bg-stone-50 px-5 py-5"
                    >
                        <div>
                            <p
                                class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                            >
                                Docs
                            </p>
                            <Link
                                :href="props.apiAccess.docs_url"
                                class="mt-2 inline-flex items-center gap-2 text-sm font-medium text-stone-900 underline underline-offset-4"
                            >
                                Open Swagger explorer
                                <FontAwesomeIcon
                                    :icon="faArrowUpRightFromSquare"
                                    class="text-xs"
                                />
                            </Link>
                            <a
                                :href="props.apiAccess.spec_url"
                                target="_blank"
                                class="mt-3 inline-flex items-center gap-2 text-xs font-medium tracking-[0.18em] text-stone-500 uppercase underline underline-offset-4"
                            >
                                Raw OpenAPI YAML
                                <FontAwesomeIcon
                                    :icon="faArrowUpRightFromSquare"
                                    class="text-[10px]"
                                />
                            </a>
                        </div>

                        <div>
                            <p
                                class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                            >
                                Primary base
                            </p>
                            <p class="mt-2 text-sm break-all text-stone-900">
                                {{ props.apiAccess.preferred_base_url }}
                            </p>
                        </div>

                        <div v-if="props.apiAccess.tailnet_base_url">
                            <p
                                class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                            >
                                Tailnet base
                            </p>
                            <p class="mt-2 text-sm break-all text-stone-900">
                                {{ props.apiAccess.tailnet_base_url }}
                            </p>
                        </div>

                        <div v-if="props.apiAccess.public_base_url">
                            <p
                                class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                            >
                                ngrok base
                            </p>
                            <p class="mt-2 text-sm break-all text-stone-900">
                                {{ props.apiAccess.public_base_url }}
                            </p>
                        </div>

                        <div>
                            <p
                                class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                            >
                                Local dev base
                            </p>
                            <p class="mt-2 text-sm break-all text-stone-900">
                                {{ props.apiAccess.local_base_url }}
                            </p>
                        </div>

                        <div
                            class="rounded-[20px] border border-stone-200 bg-white px-4 py-4"
                        >
                            <p
                                class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                            >
                                Included endpoints
                            </p>
                            <ul
                                class="mt-3 space-y-2 text-sm leading-6 text-stone-700"
                            >
                                <li>
                                    <span class="font-medium text-stone-900"
                                        >POST</span
                                    >
                                    `/clients` for lead intake
                                </li>
                                <li>
                                    <span class="font-medium text-stone-900"
                                        >GET</span
                                    >
                                    `/clients/{cuid}/status` for score and case
                                    status
                                </li>
                                <li>
                                    <span class="font-medium text-stone-900"
                                        >GET</span
                                    >
                                    `/clients/{cuid}/letters` for dispute
                                    letters
                                </li>
                                <li>
                                    <span class="font-medium text-stone-900"
                                        >GET</span
                                    >
                                    `/clients/{cuid}/briefs` for shareable case
                                    briefs
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 rounded-full bg-stone-950 px-5 py-3 text-xs font-medium tracking-[0.2em] text-white uppercase transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:bg-stone-300"
                    :disabled="form.processing"
                >
                    <FontAwesomeIcon :icon="faKey" />
                    Save connectivity settings
                </button>
            </div>
        </form>
    </div>
</template>
