<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ExternalLink } from 'lucide-vue-next';
import CreditsoftWordmark from '@/components/CreditsoftWordmark.vue';
import { Toaster } from '@/components/ui/sonner';

const props = defineProps<{
    installer: {
        state: {
            company_name?: string | null;
            admin_email?: string | null;
            tailscale_required?: boolean;
            tailscale_hostname?: string | null;
            tailscale_tailnet?: string | null;
            tailscale_api_key_expires_at?: string | null;
            backup_destination?: string | null;
            portal_sync_enabled?: boolean;
            report_feedback_enabled?: boolean;
            api_enabled?: boolean;
            ai_default_provider?: string | null;
            ngrok_enabled?: boolean;
            ngrok_api_only?: boolean;
            branding?: {
                logo_name?: string | null;
                logo_url?: string | null;
                uploaded_at?: string | null;
            };
            license: {
                valid: boolean;
                status: string;
                mode: string;
                plan?: string | null;
                plan_key?: string | null;
                plan_label?: string | null;
                features?: Record<string, boolean>;
                message?: string | null;
                checked_at?: string | null;
                masked_key?: string | null;
                expires_at?: string | null;
                grace_days?: number;
                grace_ends_at?: string | null;
                grace_ends_label?: string | null;
                expires_label?: string | null;
                access_state?: string | null;
                countdown_label?: string | null;
                rail_message?: string | null;
            };
        };
        bootstrap: {
            ai: {
                default_provider: string;
                providers: Array<{
                    key: string;
                    label: string;
                    configured: boolean;
                    masked_key?: string | null;
                    validation?: {
                        state: string;
                        message: string;
                    } | null;
                }>;
            };
            api: {
                enabled: boolean;
                token_saved: boolean;
                masked_token?: string | null;
            };
            tailscale: {
                required: boolean;
                hostname: string;
                tailnet?: string | null;
                api_key_saved: boolean;
                masked_api_key?: string | null;
                api_key_expires_at?: string | null;
            };
            ngrok: {
                enabled: boolean;
                api_only: boolean;
                authtoken_saved: boolean;
                api_key_saved: boolean;
                masked_authtoken?: string | null;
                masked_api_key?: string | null;
                runtime?: {
                    running?: boolean;
                    active_public_url?: string | null;
                    message?: string | null;
                };
            };
        };
        steps: Array<{
            key: string;
            title: string;
            description: string;
            status: string;
        }>;
        advertisements: {
            title: string;
            subtitle?: string | null;
            updated_at?: string | null;
            source?: string | null;
            feed_url?: string | null;
            items: Array<{
                id: string;
                eyebrow: string;
                title: string;
                summary?: string | null;
                copy: string;
                image_url?: string | null;
                logo_url?: string | null;
                cta_label?: string | null;
                cta_url?: string | null;
                duration_ms?: number | null;
                disclaimer?: string | null;
            }>;
        };
        licenseMode: string;
        licenseCheckConfigured: boolean;
        licenseSources: Array<{
            label: string;
            url: string;
        }>;
        portalUrl: string;
        browserCompanion: {
            name: string;
            version: string;
            description: string;
            enabled: boolean;
            download_url: string | null;
        };
        intranetClientInstaller: {
            name: string;
            version: string;
            download_name: string;
            download_url: string;
            description: string;
            platforms: string[];
            router_url: string;
            office_name?: string | null;
            candidate_api_bases: string[];
            api_token_included: boolean;
            contains_cluster_ssh: boolean;
            sensitive: boolean;
        };
        intranetNodeInstaller: {
            name: string;
            version: string;
            download_name: string;
            download_url: string;
            description: string;
            platforms: string[];
            includes_package: boolean;
            package_name?: string | null;
            package_source: string;
            latest_version?: string | null;
            feed_url?: string | null;
            api_token_included: boolean;
            masked_api_token?: string | null;
            office_name?: string | null;
            sensitive: boolean;
        };
    };
}>();

const form = useForm({
    company_name: props.installer.state.company_name ?? '',
    admin_email: props.installer.state.admin_email ?? '',
    tailscale_hostname:
        props.installer.state.tailscale_hostname ?? 'creditsoft-intranet',
    backup_destination: props.installer.state.backup_destination ?? 'wasabi',
    portal_sync_enabled: props.installer.state.portal_sync_enabled ?? false,
    report_feedback_enabled:
        props.installer.state.report_feedback_enabled ?? false,
    license_key: '',
});

const configForm = useForm({
    ai_default_provider:
        props.installer.bootstrap.ai.default_provider ??
        'openrouter_creditsoft',
    opencode_api_key: '',
    openrouter_api_key: '',
    ollama_cloud_api_key: '',
    api_enabled: props.installer.bootstrap.api.enabled ?? true,
    website_api_token: '',
    tailscale_required: props.installer.bootstrap.tailscale.required ?? true,
    tailscale_tailnet: props.installer.bootstrap.tailscale.tailnet ?? '',
    tailscale_api_key: '',
    tailscale_api_key_expires_at:
        props.installer.bootstrap.tailscale.api_key_expires_at ?? '',
    ngrok_enabled: props.installer.bootstrap.ngrok.enabled ?? false,
    ngrok_api_only: props.installer.bootstrap.ngrok.api_only ?? true,
    ngrok_authtoken: '',
    ngrok_api_key: '',
});

const logoForm = useForm<{
    logo: File | null;
}>({
    logo: null,
});

const statusTone = (status: string) => {
    if (status === 'complete')
        return 'border-emerald-300 bg-emerald-100 text-emerald-900';
    if (status === 'in_progress')
        return 'border-amber-300 bg-amber-100 text-amber-900';

    return 'border-stone-300 bg-stone-100 text-stone-700';
};

const licenseTone = (valid: boolean) => {
    return valid
        ? 'border-emerald-300 bg-emerald-100 text-emerald-950'
        : 'border-rose-300 bg-rose-100 text-rose-950';
};

const feedTone = (source?: string | null) => {
    if (source === 'remote')
        return 'border-emerald-300 bg-emerald-100 text-emerald-950';
    if (source === 'local-fallback')
        return 'border-amber-300 bg-amber-100 text-amber-950';

    return 'border-stone-300 bg-stone-100 text-stone-700';
};

const installerLogoUrl = computed(() => {
    const url = props.installer.state.branding?.logo_url;
    const uploadedAt = props.installer.state.branding?.uploaded_at;

    if (!url) {
        return null;
    }

    return uploadedAt ? `${url}?v=${encodeURIComponent(uploadedAt)}` : url;
});

const feedStatusLabel = computed(() => {
    const source = props.installer.advertisements.source;

    if (source === 'remote') {
        return 'Live portal feed';
    }

    if (source === 'local-fallback') {
        return 'Local fallback';
    }

    return 'Local preview';
});

const advertisements = computed(
    () => props.installer.advertisements.items ?? [],
);

const activeAdIndex = ref(0);
const activeAdvertisement = computed(
    () => advertisements.value[activeAdIndex.value] ?? null,
);
const activeAdDurationMs = computed(
    () => activeAdvertisement.value?.duration_ms ?? 20000,
);
const activeAdProgressKey = computed(
    () => `${activeAdvertisement.value?.id ?? 'empty'}-${activeAdIndex.value}`,
);

let adTimer: ReturnType<typeof window.setInterval> | null = null;

const stopAdTimer = () => {
    if (!adTimer) {
        return;
    }

    window.clearInterval(adTimer);
    adTimer = null;
};

const nextAd = () => {
    if (advertisements.value.length <= 1) {
        return;
    }

    activeAdIndex.value =
        (activeAdIndex.value + 1) % advertisements.value.length;
};

const startAdTimer = () => {
    stopAdTimer();

    if (advertisements.value.length <= 1) {
        return;
    }

    adTimer = window.setInterval(nextAd, activeAdDurationMs.value);
};

const selectAd = (index: number) => {
    activeAdIndex.value = index;
    startAdTimer();
};

onMounted(startAdTimer);
onBeforeUnmount(stopAdTimer);

watch(
    () => advertisements.value.length,
    (length) => {
        if (activeAdIndex.value >= length) {
            activeAdIndex.value = 0;
        }

        startAdTimer();
    },
);

watch(activeAdDurationMs, startAdTimer);

const activeBrandLabel = computed(() => {
    if (props.installer.state.branding?.logo_name) {
        return props.installer.state.branding.logo_name;
    }

    return props.installer.state.company_name
        ? `${props.installer.state.company_name} branding pending`
        : 'CreditSoft default wordmark';
});

const submit = () => {
    form.post('/install/license-check', {
        preserveScroll: true,
        onSuccess: () => form.reset('license_key'),
    });
};

const saveConfig = () => {
    configForm.post('/install/config', {
        preserveScroll: true,
        onSuccess: () =>
            configForm.reset(
                'opencode_api_key',
                'openrouter_api_key',
                'ollama_cloud_api_key',
                'website_api_token',
                'tailscale_api_key',
                'ngrok_authtoken',
                'ngrok_api_key',
            ),
    });
};

const uploadLogo = (event: Event) => {
    const input = event.target as HTMLInputElement;
    logoForm.logo = input.files?.[0] ?? null;

    if (!logoForm.logo) {
        return;
    }

    logoForm.post('/install/logo', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            logoForm.reset();
            input.value = '';
        },
    });
};

const credentialStatus = (saved: boolean, masked?: string | null) => {
    return saved && masked
        ? `Saved on this install: ${masked}`
        : 'No saved key yet.';
};

const operatorRunbook = [
    {
        label: 'Download zip',
        detail: 'Use the server node installer on the server computer.',
    },
    {
        label: 'Run full office',
        detail: 'macOS/Linux: bash install.sh --office',
    },
    {
        label: 'Windows command',
        detail: 'PowerShell: .\\install.ps1 -Office',
    },
    {
        label: 'Open router',
        detail: 'Use the local router link after Docker reports the stack is up.',
    },
];

const clientRunbook = [
    {
        label: 'Install client',
        detail: 'macOS/Linux: bash install.sh',
    },
    {
        label: 'Windows command',
        detail: 'PowerShell: .\\install.ps1',
    },
    {
        label: 'Add API key',
        detail: 'Use the staff key from Profile or pass CREDITSOFT_API_TOKEN.',
    },
    {
        label: 'Open PWA',
        detail: 'Installer prints the bookmark URL after checking port 80, then 8877+.',
    },
];
</script>

<template>
    <Head title="Installer Setup" />

    <Toaster rich-colors position="top-right" />

    <div
        class="min-h-screen bg-[radial-gradient(circle_at_top_left,_rgba(245,158,11,0.16),_transparent_21%),radial-gradient(circle_at_bottom_right,_rgba(41,37,36,0.08),_transparent_20%),linear-gradient(180deg,_#fbf8f1,_#f1e8d8)] text-stone-900"
    >
        <div class="w-full px-4 py-4 md:px-6 md:py-6">
            <div
                class="mr-auto grid max-w-[1740px] gap-5 xl:grid-cols-[minmax(820px,1.28fr)_minmax(390px,0.72fr)] xl:items-stretch"
            >
                <section
                    class="relative overflow-hidden rounded-[40px] border border-stone-300/70 bg-white/84 shadow-[0_28px_90px_rgba(120,113,108,0.14)] backdrop-blur"
                >
                    <div
                        class="absolute inset-y-0 left-0 hidden w-[350px] bg-stone-950 xl:block"
                    />

                    <div
                        class="relative grid gap-0 xl:grid-cols-[350px_minmax(0,1fr)]"
                    >
                        <aside
                            class="space-y-5 bg-stone-950 p-6 text-stone-50 md:p-7"
                        >
                            <div class="space-y-4">
                                <div
                                    class="rounded-[28px] border border-stone-800 bg-white px-5 py-6 text-stone-950 shadow-[0_18px_44px_rgba(0,0,0,0.18)]"
                                >
                                    <div
                                        class="flex min-h-[120px] items-center justify-center"
                                    >
                                        <img
                                            v-if="installerLogoUrl"
                                            :src="installerLogoUrl"
                                            :alt="
                                                installer.state.branding
                                                    ?.logo_name ?? 'Office logo'
                                            "
                                            class="max-h-24 w-auto object-contain"
                                        />
                                        <CreditsoftWordmark
                                            v-else
                                            class-name="h-12 w-auto md:h-14"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <p
                                        class="text-[11px] font-medium tracking-[0.34em] text-stone-400 uppercase"
                                    >
                                        Installer workspace
                                    </p>
                                    <h1
                                        class="mt-3 text-3xl font-semibold tracking-tight text-white md:text-[2.45rem]"
                                    >
                                        AnyDesk-ready browser installer.
                                    </h1>
                                    <p
                                        class="mt-3 text-sm leading-6 text-stone-300"
                                    >
                                        Work the setup from this left pane while
                                        the right rail keeps partner offers one
                                        click away in a JSON-controlled lane.
                                    </p>
                                </div>
                            </div>

                            <div
                                class="rounded-[26px] border border-stone-800 bg-stone-900/85 p-4"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-3"
                                >
                                    <div>
                                        <p
                                            class="text-[11px] tracking-[0.28em] text-stone-500 uppercase"
                                        >
                                            Active brand
                                        </p>
                                        <p
                                            class="mt-2 text-sm font-medium text-stone-50"
                                        >
                                            {{ activeBrandLabel }}
                                        </p>
                                    </div>
                                    <div
                                        class="rounded-full border px-3 py-2 text-[11px] font-medium tracking-[0.18em] uppercase"
                                        :class="
                                            installerLogoUrl
                                                ? 'border-emerald-300 bg-emerald-100 text-emerald-950'
                                                : 'border-stone-700 bg-stone-900 text-stone-200'
                                        "
                                    >
                                        {{
                                            installerLogoUrl
                                                ? 'Custom logo live'
                                                : 'CreditSoft default'
                                        }}
                                    </div>
                                </div>
                                <p
                                    class="mt-3 text-sm leading-6 text-stone-400"
                                >
                                    The uploaded mark is saved into the
                                    installer state, copied into generated node
                                    zips, and used on the login screen.
                                </p>
                            </div>

                            <div class="space-y-3">
                                <article
                                    v-for="step in installer.steps"
                                    :key="step.key"
                                    class="rounded-[22px] border border-stone-800 bg-stone-900/85 p-4"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-sm font-semibold text-stone-50"
                                            >
                                                {{ step.title }}
                                            </p>
                                            <p
                                                class="mt-2 text-sm leading-6 text-stone-400"
                                            >
                                                {{ step.description }}
                                            </p>
                                        </div>
                                        <span
                                            class="rounded-full border px-2 py-1 text-[11px] font-medium tracking-[0.18em] uppercase"
                                            :class="statusTone(step.status)"
                                        >
                                            {{ step.status.replace('_', ' ') }}
                                        </span>
                                    </div>
                                </article>
                            </div>

                            <div
                                class="grid gap-3 rounded-[24px] border border-stone-800 bg-stone-900/80 p-4 md:grid-cols-2"
                            >
                                <div>
                                    <p
                                        class="text-[11px] tracking-[0.28em] text-stone-500 uppercase"
                                    >
                                        Portal reference
                                    </p>
                                    <p
                                        class="mt-2 text-sm font-medium text-stone-50"
                                    >
                                        {{ installer.portalUrl }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-[11px] tracking-[0.28em] text-stone-500 uppercase"
                                    >
                                        License mode
                                    </p>
                                    <p
                                        class="mt-2 text-sm font-medium text-stone-50"
                                    >
                                        {{ installer.licenseMode }}
                                        <span
                                            v-if="
                                                installer.licenseMode ===
                                                    'remote' &&
                                                !installer.licenseCheckConfigured
                                            "
                                            class="text-stone-400"
                                        >
                                            (remote endpoint not configured)
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </aside>

                        <div class="space-y-6 p-5 md:p-7 xl:p-8">
                            <div
                                class="grid gap-4 lg:grid-cols-[minmax(0,1.15fr)_minmax(260px,0.85fr)]"
                            >
                                <div
                                    class="rounded-[30px] border border-stone-300/70 bg-[linear-gradient(135deg,_rgba(255,255,255,0.96),_rgba(251,245,231,0.92))] p-6"
                                >
                                    <p
                                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                                    >
                                        Install flow
                                    </p>
                                    <h2
                                        class="mt-3 text-3xl font-semibold tracking-tight text-stone-950"
                                    >
                                        Run the office setup without terminal
                                        guessing.
                                    </h2>
                                    <p
                                        class="mt-3 max-w-2xl text-sm leading-7 text-stone-600"
                                    >
                                        Set the office identity, Tailscale name,
                                        backup target, API keys, browser
                                        companion, and node zip from one browser
                                        surface.
                                    </p>
                                </div>

                                <div
                                    class="rounded-[30px] border border-amber-200/80 bg-[radial-gradient(circle_at_top_left,_rgba(251,191,36,0.18),_transparent_56%),linear-gradient(180deg,_rgba(255,251,235,0.98),_rgba(255,247,220,0.94))] p-5"
                                >
                                    <p
                                        class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                                    >
                                        Identity preview
                                    </p>
                                    <div
                                        class="mt-4 flex min-h-[124px] items-center justify-center rounded-[24px] border border-white/80 bg-white/90 px-5 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.6)]"
                                    >
                                        <img
                                            v-if="installerLogoUrl"
                                            :src="installerLogoUrl"
                                            :alt="
                                                installer.state.branding
                                                    ?.logo_name ?? 'Office logo'
                                            "
                                            class="max-h-20 w-auto object-contain"
                                        />
                                        <CreditsoftWordmark
                                            v-else
                                            class-name="h-11 w-auto"
                                        />
                                    </div>
                                    <p
                                        class="mt-4 text-sm leading-6 text-stone-600"
                                    >
                                        {{
                                            installerLogoUrl
                                                ? 'Custom branding is now live for the installer and login screen.'
                                                : 'Upload an office logo to replace the default CreditSoft mark on login.'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="rounded-[30px] border border-stone-300/70 bg-stone-50/84 p-5 md:p-6"
                            >
                                <div
                                    class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                                >
                                    <div>
                                        <p
                                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                                        >
                                            Bundled browser companion
                                        </p>
                                        <p
                                            class="mt-2 text-sm leading-6 text-stone-600"
                                        >
                                            {{
                                                installer.browserCompanion
                                                    .description
                                            }}
                                        </p>
                                    </div>

                                    <a
                                        v-if="
                                            installer.browserCompanion
                                                .download_url
                                        "
                                        :href="
                                            installer.browserCompanion
                                                .download_url
                                        "
                                        class="inline-flex rounded-full border border-stone-300 bg-stone-950 px-5 py-3 text-xs font-medium tracking-[0.22em] text-white uppercase transition hover:bg-stone-800"
                                    >
                                        Download companion pack
                                    </a>
                                    <div
                                        v-else
                                        class="rounded-full border border-stone-300 bg-white px-4 py-3 text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                                    >
                                        Included with Enterprise Pro
                                    </div>
                                </div>

                                <p
                                    class="mt-3 text-xs leading-5 text-stone-500"
                                >
                                    Version
                                    {{ installer.browserCompanion.version }}
                                    extracts as a load-unpacked Chromium helper
                                    and includes Safari webarchive instructions
                                    in the same package.
                                </p>
                            </div>

                            <div
                                class="rounded-[30px] border border-stone-300/70 bg-[linear-gradient(135deg,_rgba(255,255,255,0.96),_rgba(244,240,232,0.88))] p-5 md:p-6"
                            >
                                <div
                                    class="flex flex-col gap-4 border-b border-stone-300/70 pb-4 lg:flex-row lg:items-start lg:justify-between"
                                >
                                    <div>
                                        <p
                                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                                        >
                                            Employee PWA router
                                        </p>
                                        <h3
                                            class="mt-2 text-2xl font-semibold tracking-tight text-stone-950"
                                        >
                                            Install the staff workstation
                                            client.
                                        </h3>
                                        <p
                                            class="mt-2 max-w-3xl text-sm leading-7 text-stone-600"
                                        >
                                            {{
                                                installer
                                                    .intranetClientInstaller
                                                    .description
                                            }}
                                        </p>
                                    </div>

                                    <a
                                        :href="
                                            installer.intranetClientInstaller
                                                .download_url
                                        "
                                        class="inline-flex shrink-0 rounded-full border border-stone-300 bg-stone-950 px-5 py-3 text-xs font-medium tracking-[0.22em] text-white uppercase transition hover:bg-stone-800"
                                    >
                                        Download client router
                                    </a>
                                </div>

                                <div
                                    class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(260px,0.62fr)]"
                                >
                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <div
                                            v-for="platform in installer
                                                .intranetClientInstaller
                                                .platforms"
                                            :key="platform"
                                            class="rounded-[22px] border border-stone-300/70 bg-white/90 px-4 py-4"
                                        >
                                            <p
                                                class="text-[11px] tracking-[0.2em] text-stone-500 uppercase"
                                            >
                                                Supported
                                            </p>
                                            <p
                                                class="mt-2 text-sm font-semibold text-stone-950"
                                            >
                                                {{ platform }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-[24px] border border-stone-300/70 bg-white/90 p-4 text-sm text-stone-700"
                                    >
                                        <p
                                            class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                        >
                                            Local router
                                        </p>
                                        <p
                                            class="mt-2 font-mono text-xs leading-5 break-all text-stone-950"
                                        >
                                            {{
                                                installer
                                                    .intranetClientInstaller
                                                    .router_url
                                            }}
                                        </p>
                                        <p
                                            class="mt-2 text-xs leading-5 text-stone-500"
                                        >
                                            Staff machines probe port 80 first,
                                            fall back to 8877+, then route to the healthiest
                                            approved server node.
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-3 lg:grid-cols-4">
                                    <div
                                        v-for="item in clientRunbook"
                                        :key="item.label"
                                        class="rounded-[22px] border border-stone-300/70 bg-white/85 px-4 py-4"
                                    >
                                        <p
                                            class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                                        >
                                            {{ item.label }}
                                        </p>
                                        <p
                                            class="mt-2 font-mono text-xs leading-5 text-stone-800"
                                        >
                                            {{ item.detail }}
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-3 md:grid-cols-2">
                                    <div
                                        class="rounded-[22px] border border-stone-300/70 bg-white/80 px-4 py-4 text-sm leading-6 text-stone-700"
                                    >
                                        <p
                                            class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                                        >
                                            Workstation only
                                        </p>
                                        <p class="mt-2">
                                            This client package does not install
                                            Docker, PostgreSQL, queues,
                                            schedulers, backups, or server node
                                            services.
                                        </p>
                                    </div>

                                    <div
                                        class="rounded-[22px] border border-stone-300/70 bg-white/80 px-4 py-4 text-sm leading-6 text-stone-700"
                                    >
                                        <p
                                            class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                                        >
                                            No cluster SSH
                                        </p>
                                        <p class="mt-2">
                                            Server-to-server SSH keys are
                                            generated only by the node installer.
                                            Employee clients use personal API
                                            keys and the local router lane.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-[30px] border border-stone-300/70 bg-[linear-gradient(135deg,_rgba(255,255,255,0.96),_rgba(244,240,232,0.88))] p-5 md:p-6"
                            >
                                <div
                                    class="flex flex-col gap-4 border-b border-stone-300/70 pb-4 lg:flex-row lg:items-start lg:justify-between"
                                >
                                    <div>
                                        <p
                                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                                        >
                                            Generated server installer
                                        </p>
                                        <h3
                                            class="mt-2 text-2xl font-semibold tracking-tight text-stone-950"
                                        >
                                            Install the intranet node without
                                            hand tuning this machine.
                                        </h3>
                                        <p
                                            class="mt-2 max-w-3xl text-sm leading-7 text-stone-600"
                                        >
                                            {{
                                                installer.intranetNodeInstaller
                                                    .description
                                            }}
                                        </p>
                                    </div>

                                    <a
                                        :href="
                                            installer.intranetNodeInstaller
                                                .download_url
                                        "
                                        class="inline-flex shrink-0 rounded-full border border-stone-300 bg-stone-950 px-5 py-3 text-xs font-medium tracking-[0.22em] text-white uppercase transition hover:bg-stone-800"
                                    >
                                        Download node installer
                                    </a>
                                </div>

                                <div
                                    class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(280px,0.65fr)]"
                                >
                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <div
                                            v-for="platform in installer
                                                .intranetNodeInstaller
                                                .platforms"
                                            :key="platform"
                                            class="rounded-[22px] border border-stone-300/70 bg-white/90 px-4 py-4"
                                        >
                                            <p
                                                class="text-[11px] tracking-[0.2em] text-stone-500 uppercase"
                                            >
                                                Supported
                                            </p>
                                            <p
                                                class="mt-2 text-sm font-semibold text-stone-950"
                                            >
                                                {{ platform }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-[24px] border border-stone-300/70 bg-white/90 p-4 text-sm text-stone-700"
                                    >
                                        <p
                                            class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                        >
                                            Installer payload
                                        </p>
                                        <p
                                            class="mt-2 font-semibold text-stone-950"
                                        >
                                            {{
                                                installer.intranetNodeInstaller
                                                    .includes_package
                                                    ? 'Office package bundled'
                                                    : 'Fetches from update feed'
                                            }}
                                        </p>
                                        <p
                                            class="mt-2 text-xs leading-5 break-all text-stone-500"
                                        >
                                            {{
                                                installer.intranetNodeInstaller
                                                    .package_name ??
                                                installer.intranetNodeInstaller
                                                    .feed_url
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-3 lg:grid-cols-4">
                                    <div
                                        v-for="item in operatorRunbook"
                                        :key="item.label"
                                        class="rounded-[22px] border border-stone-300/70 bg-white/85 px-4 py-4"
                                    >
                                        <p
                                            class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                                        >
                                            {{ item.label }}
                                        </p>
                                        <p
                                            class="mt-2 font-mono text-xs leading-5 text-stone-800"
                                        >
                                            {{ item.detail }}
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-3 md:grid-cols-2">
                                    <div
                                        class="rounded-[22px] border border-stone-300/70 bg-white/80 px-4 py-4 text-sm text-stone-700"
                                    >
                                        <p
                                            class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                                        >
                                            Office configuration
                                        </p>
                                        <p class="mt-2 leading-6">
                                            The zip writes a generated
                                            <span class="font-mono text-xs"
                                                >.env.docker</span
                                            >, creates a stable app key, starts
                                            the intranet, queue, and scheduler,
                                            then can optionally start the local
                                            router and CRM sidecar. It can also
                                            switch the intranet database to
                                            PostgreSQL so the office server uses
                                            the same database engine as the CRM
                                            without mixing their schemas.
                                        </p>
                                    </div>

                                    <div
                                        class="rounded-[22px] border border-stone-300/70 bg-white/80 px-4 py-4 text-sm leading-6 text-stone-700"
                                    >
                                        <p
                                            class="text-[11px] tracking-[0.22em] uppercase"
                                        >
                                            Clean package
                                        </p>
                                        <p class="mt-2">
                                            The zip ships without owner API, AI,
                                            tunnel, backup, or CRM keys. The
                                            first-run installer generates local
                                            office credentials and asks the
                                            operator to paste provider tokens
                                            when that office needs them.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-[30px] border border-stone-300/70 bg-stone-50/84 p-5 md:p-6"
                            >
                                <div
                                    class="flex flex-col gap-3 border-b border-stone-300/70 pb-4 md:flex-row md:items-end md:justify-between"
                                >
                                    <div>
                                        <p
                                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                                        >
                                            Brand the installer
                                        </p>
                                        <p class="mt-2 text-sm text-stone-600">
                                            Add the office logo here and the
                                            installer, generated node package,
                                            and login screen use that brand
                                            instead of the default CreditSoft
                                            wordmark.
                                        </p>
                                    </div>

                                    <div
                                        class="rounded-full border px-3 py-2 text-[11px] font-medium tracking-[0.18em] uppercase"
                                        :class="
                                            installerLogoUrl
                                                ? 'border-emerald-300 bg-emerald-100 text-emerald-950'
                                                : 'border-stone-300 bg-white text-stone-700'
                                        "
                                    >
                                        {{
                                            installerLogoUrl
                                                ? 'Custom logo active'
                                                : 'Using CreditSoft mark'
                                        }}
                                    </div>
                                </div>

                                <div
                                    class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(250px,0.8fr)]"
                                >
                                    <label
                                        class="group flex min-h-[205px] cursor-pointer flex-col items-center justify-center rounded-[26px] border border-dashed border-stone-300 bg-white/90 px-6 py-6 text-center transition hover:border-stone-500 hover:bg-white"
                                    >
                                        <input
                                            type="file"
                                            accept=".png,.svg,.jpg,.jpeg"
                                            class="hidden"
                                            @change="uploadLogo"
                                        />
                                        <div class="space-y-2">
                                            <p
                                                class="text-sm font-semibold text-stone-950"
                                            >
                                                {{
                                                    logoForm.processing
                                                        ? 'Uploading logo...'
                                                        : 'Upload office logo'
                                                }}
                                            </p>
                                            <p
                                                class="text-sm leading-6 text-stone-600"
                                            >
                                                PNG or SVG works best. This
                                                replaces the default wordmark as
                                                soon as the upload finishes.
                                            </p>
                                        </div>
                                    </label>

                                    <div
                                        class="rounded-[26px] border border-stone-300/70 bg-white/90 p-5 text-sm text-stone-700"
                                    >
                                        <p
                                            class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                        >
                                            Current branding
                                        </p>
                                        <p
                                            class="mt-3 font-medium text-stone-950"
                                        >
                                            {{ activeBrandLabel }}
                                        </p>
                                        <p class="mt-2 text-sm text-stone-600">
                                            {{
                                                installer.state.branding
                                                    ?.uploaded_at ??
                                                'No office logo uploaded yet.'
                                            }}
                                        </p>
                                        <p
                                            v-if="logoForm.errors.logo"
                                            class="mt-3 text-xs text-rose-700"
                                        >
                                            {{ logoForm.errors.logo }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-[30px] border border-stone-300/70 bg-stone-50/84 p-5 md:p-6"
                            >
                                <div
                                    class="flex flex-col gap-4 border-b border-stone-300/70 pb-4 md:flex-row md:items-end md:justify-between"
                                >
                                    <div>
                                        <p
                                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                                        >
                                            Office bootstrap
                                        </p>
                                        <p class="mt-2 text-sm text-stone-600">
                                            Ask the core setup questions here
                                            once: AI lanes, website API access,
                                            Tailscale, and ngrok. Secret fields
                                            stay blank after save, but the
                                            installer shows what is already
                                            configured on this office.
                                        </p>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            class="rounded-full border px-3 py-2 text-[11px] font-medium tracking-[0.18em] uppercase"
                                            :class="
                                                props.installer.bootstrap.api
                                                    .token_saved
                                                    ? 'border-emerald-300 bg-emerald-100 text-emerald-950'
                                                    : 'border-stone-300 bg-white text-stone-700'
                                            "
                                        >
                                            {{
                                                props.installer.bootstrap.api
                                                    .token_saved
                                                    ? 'API ready'
                                                    : 'API pending'
                                            }}
                                        </span>
                                        <span
                                            class="rounded-full border px-3 py-2 text-[11px] font-medium tracking-[0.18em] uppercase"
                                            :class="
                                                props.installer.bootstrap.ngrok
                                                    .enabled
                                                    ? 'border-amber-300 bg-amber-100 text-amber-950'
                                                    : 'border-stone-300 bg-white text-stone-700'
                                            "
                                        >
                                            {{
                                                props.installer.bootstrap.ngrok
                                                    .enabled
                                                    ? 'ngrok enabled'
                                                    : 'ngrok optional'
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <form
                                    class="mt-5 space-y-6"
                                    @submit.prevent="saveConfig"
                                >
                                    <div class="grid gap-6 xl:grid-cols-2">
                                        <div
                                            class="space-y-4 rounded-[26px] border border-stone-300/70 bg-white/90 p-5"
                                        >
                                            <div>
                                                <p
                                                    class="text-[11px] font-medium tracking-[0.26em] text-stone-500 uppercase"
                                                >
                                                    AI lanes
                                                </p>
                                                <p
                                                    class="mt-2 text-sm leading-6 text-stone-600"
                                                >
                                                    Pick the default drafting
                                                    lane, then save whichever
                                                    provider keys this office
                                                    will use.
                                                </p>
                                            </div>

                                            <label
                                                class="space-y-2 text-sm font-medium text-stone-700"
                                            >
                                                <span>Default AI provider</span>
                                                <select
                                                    v-model="
                                                        configForm.ai_default_provider
                                                    "
                                                    class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900 shadow-sm transition outline-none focus:border-amber-500"
                                                >
                                                    <option
                                                        value="opencode_zen"
                                                    >
                                                        OpenCode Zen
                                                    </option>
                                                    <option
                                                        value="openrouter_creditsoft"
                                                    >
                                                        OpenRouter
                                                    </option>
                                                    <option
                                                        value="ollama_cloud"
                                                    >
                                                        Ollama Cloud
                                                    </option>
                                                </select>
                                            </label>

                                            <div class="grid gap-4">
                                                <label
                                                    class="space-y-2 text-sm font-medium text-stone-700"
                                                >
                                                    <span
                                                        >OpenCode API key</span
                                                    >
                                                    <input
                                                        v-model="
                                                            configForm.opencode_api_key
                                                        "
                                                        type="password"
                                                        class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900 shadow-sm transition outline-none focus:border-amber-500"
                                                        placeholder="Paste OpenCode Zen key"
                                                    />
                                                    <p
                                                        class="text-xs text-stone-500"
                                                    >
                                                        {{
                                                            credentialStatus(
                                                                props.installer
                                                                    .bootstrap
                                                                    .ai
                                                                    .providers[0]
                                                                    ?.configured ??
                                                                    false,
                                                                props.installer
                                                                    .bootstrap
                                                                    .ai
                                                                    .providers[0]
                                                                    ?.masked_key,
                                                            )
                                                        }}
                                                    </p>
                                                </label>

                                                <label
                                                    class="space-y-2 text-sm font-medium text-stone-700"
                                                >
                                                    <span
                                                        >OpenRouter API
                                                        key</span
                                                    >
                                                    <input
                                                        v-model="
                                                            configForm.openrouter_api_key
                                                        "
                                                        type="password"
                                                        class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900 shadow-sm transition outline-none focus:border-amber-500"
                                                        placeholder="Paste OpenRouter key"
                                                    />
                                                    <p
                                                        class="text-xs text-stone-500"
                                                    >
                                                        {{
                                                            credentialStatus(
                                                                props.installer
                                                                    .bootstrap
                                                                    .ai
                                                                    .providers[1]
                                                                    ?.configured ??
                                                                    false,
                                                                props.installer
                                                                    .bootstrap
                                                                    .ai
                                                                    .providers[1]
                                                                    ?.masked_key,
                                                            )
                                                        }}
                                                    </p>
                                                </label>

                                                <label
                                                    class="space-y-2 text-sm font-medium text-stone-700"
                                                >
                                                    <span
                                                        >Ollama Cloud API
                                                        key</span
                                                    >
                                                    <input
                                                        v-model="
                                                            configForm.ollama_cloud_api_key
                                                        "
                                                        type="password"
                                                        class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900 shadow-sm transition outline-none focus:border-amber-500"
                                                        placeholder="Paste Ollama Cloud key"
                                                    />
                                                    <p
                                                        class="text-xs text-stone-500"
                                                    >
                                                        {{
                                                            credentialStatus(
                                                                props.installer
                                                                    .bootstrap
                                                                    .ai
                                                                    .providers[2]
                                                                    ?.configured ??
                                                                    false,
                                                                props.installer
                                                                    .bootstrap
                                                                    .ai
                                                                    .providers[2]
                                                                    ?.masked_key,
                                                            )
                                                        }}
                                                    </p>
                                                </label>
                                            </div>
                                        </div>

                                        <div
                                            class="space-y-4 rounded-[26px] border border-stone-300/70 bg-white/90 p-5"
                                        >
                                            <div>
                                                <p
                                                    class="text-[11px] font-medium tracking-[0.26em] text-stone-500 uppercase"
                                                >
                                                    Website and browser API
                                                </p>
                                                <p
                                                    class="mt-2 text-sm leading-6 text-stone-600"
                                                >
                                                    This key powers the website
                                                    portal lane. Staff can still
                                                    create their own personal
                                                    keys later for the browser
                                                    companion and automation
                                                    tools.
                                                </p>
                                            </div>

                                            <label
                                                class="flex items-start gap-3 rounded-[22px] border border-stone-300/70 bg-stone-50/80 px-4 py-4 text-sm text-stone-700"
                                            >
                                                <input
                                                    v-model="
                                                        configForm.api_enabled
                                                    "
                                                    type="checkbox"
                                                    class="mt-1 size-4 rounded border-stone-300 text-amber-500 focus:ring-amber-500"
                                                />
                                                <span class="leading-6">
                                                    <span
                                                        class="block font-medium text-stone-900"
                                                        >Enable website and
                                                        portal API</span
                                                    >
                                                    <span class="mt-1 block">
                                                        Leave the key field
                                                        blank if you want
                                                        CreditSoft to preserve
                                                        the current key or
                                                        auto-generate one the
                                                        first time.
                                                    </span>
                                                </span>
                                            </label>

                                            <label
                                                class="space-y-2 text-sm font-medium text-stone-700"
                                            >
                                                <span
                                                    >Website and portal API
                                                    key</span
                                                >
                                                <input
                                                    v-model="
                                                        configForm.website_api_token
                                                    "
                                                    type="password"
                                                    class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900 shadow-sm transition outline-none focus:border-amber-500"
                                                    placeholder="Leave blank to keep or auto-generate"
                                                />
                                                <p
                                                    class="text-xs text-stone-500"
                                                >
                                                    {{
                                                        credentialStatus(
                                                            props.installer
                                                                .bootstrap.api
                                                                .token_saved,
                                                            props.installer
                                                                .bootstrap.api
                                                                .masked_token,
                                                        )
                                                    }}
                                                </p>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="grid gap-6 xl:grid-cols-2">
                                        <div
                                            class="space-y-4 rounded-[26px] border border-stone-300/70 bg-white/90 p-5"
                                        >
                                            <div>
                                                <p
                                                    class="text-[11px] font-medium tracking-[0.26em] text-stone-500 uppercase"
                                                >
                                                    Tailscale lane
                                                </p>
                                                <p
                                                    class="mt-2 text-sm leading-6 text-stone-600"
                                                >
                                                    This is the private office
                                                    path for staff devices.
                                                    Hosting the app on a desktop
                                                    or Pi with Tailscale lets
                                                    the office work from
                                                    anywhere on the same
                                                    tailnet.
                                                </p>
                                            </div>

                                            <label
                                                class="flex items-start gap-3 rounded-[22px] border border-stone-300/70 bg-stone-50/80 px-4 py-4 text-sm text-stone-700"
                                            >
                                                <input
                                                    v-model="
                                                        configForm.tailscale_required
                                                    "
                                                    type="checkbox"
                                                    class="mt-1 size-4 rounded border-stone-300 text-amber-500 focus:ring-amber-500"
                                                />
                                                <span class="leading-6">
                                                    <span
                                                        class="block font-medium text-stone-900"
                                                        >Require Tailscale for
                                                        staff access</span
                                                    >
                                                    <span class="mt-1 block">
                                                        Keep the operator
                                                        workspace private by
                                                        default, even if ngrok
                                                        is later enabled for
                                                        callbacks or demos.
                                                    </span>
                                                </span>
                                            </label>

                                            <div
                                                class="grid gap-4 md:grid-cols-2"
                                            >
                                                <label
                                                    class="space-y-2 text-sm font-medium text-stone-700"
                                                >
                                                    <span>Tailnet</span>
                                                    <input
                                                        v-model="
                                                            configForm.tailscale_tailnet
                                                        "
                                                        type="text"
                                                        class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900 shadow-sm transition outline-none focus:border-amber-500"
                                                        placeholder="tail86e562.ts.net"
                                                    />
                                                </label>

                                                <label
                                                    class="space-y-2 text-sm font-medium text-stone-700"
                                                >
                                                    <span
                                                        >Admin API key
                                                        expires</span
                                                    >
                                                    <input
                                                        v-model="
                                                            configForm.tailscale_api_key_expires_at
                                                        "
                                                        type="date"
                                                        class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900 shadow-sm transition outline-none focus:border-amber-500"
                                                    />
                                                </label>
                                            </div>

                                            <label
                                                class="space-y-2 text-sm font-medium text-stone-700"
                                            >
                                                <span
                                                    >Tailscale admin API
                                                    key</span
                                                >
                                                <input
                                                    v-model="
                                                        configForm.tailscale_api_key
                                                    "
                                                    type="password"
                                                    class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900 shadow-sm transition outline-none focus:border-amber-500"
                                                    placeholder="Paste Tailscale admin key"
                                                />
                                                <p
                                                    class="text-xs text-stone-500"
                                                >
                                                    {{
                                                        credentialStatus(
                                                            props.installer
                                                                .bootstrap
                                                                .tailscale
                                                                .api_key_saved,
                                                            props.installer
                                                                .bootstrap
                                                                .tailscale
                                                                .masked_api_key,
                                                        )
                                                    }}
                                                </p>
                                            </label>
                                        </div>

                                        <div
                                            class="space-y-4 rounded-[26px] border border-stone-300/70 bg-white/90 p-5"
                                        >
                                            <div>
                                                <p
                                                    class="text-[11px] font-medium tracking-[0.26em] text-stone-500 uppercase"
                                                >
                                                    ngrok callback lane
                                                </p>
                                                <p
                                                    class="mt-2 text-sm leading-6 text-stone-600"
                                                >
                                                    Use ngrok when webhooks,
                                                    forms, Meta callbacks, or a
                                                    public demo need to reach
                                                    the local installation. With
                                                    ngrok off, CreditSoft stays
                                                    in offline / local-only mode
                                                    and can be reached only from
                                                    this machine or Tailscale.
                                                </p>
                                            </div>

                                            <label
                                                class="flex items-start gap-3 rounded-[22px] border border-stone-300/70 bg-stone-50/80 px-4 py-4 text-sm text-stone-700"
                                            >
                                                <input
                                                    v-model="
                                                        configForm.ngrok_enabled
                                                    "
                                                    type="checkbox"
                                                    class="mt-1 size-4 rounded border-stone-300 text-amber-500 focus:ring-amber-500"
                                                />
                                                <span class="leading-6">
                                                    <span
                                                        class="block font-medium text-stone-900"
                                                        >Enable ngrok callback
                                                        lane</span
                                                    >
                                                    <span class="mt-1 block">
                                                        Turn this on only when
                                                        you need a public
                                                        callback or outside demo
                                                        lane. Leave it off when
                                                        you want the
                                                        installation staying
                                                        local-only.
                                                    </span>
                                                </span>
                                            </label>

                                            <label
                                                class="flex items-start gap-3 rounded-[22px] border border-stone-300/70 bg-stone-50/80 px-4 py-4 text-sm text-stone-700"
                                            >
                                                <input
                                                    v-model="
                                                        configForm.ngrok_api_only
                                                    "
                                                    type="checkbox"
                                                    class="mt-1 size-4 rounded border-stone-300 text-amber-500 focus:ring-amber-500"
                                                />
                                                <span class="leading-6">
                                                    <span
                                                        class="block font-medium text-stone-900"
                                                        >Keep ngrok in API-only
                                                        mode</span
                                                    >
                                                    <span class="mt-1 block">
                                                        Limit the public tunnel
                                                        to the partner API until
                                                        you intentionally want
                                                        the portal itself
                                                        reachable from outside.
                                                    </span>
                                                </span>
                                            </label>

                                            <label
                                                class="space-y-2 text-sm font-medium text-stone-700"
                                            >
                                                <span>ngrok authtoken</span>
                                                <input
                                                    v-model="
                                                        configForm.ngrok_authtoken
                                                    "
                                                    type="password"
                                                    class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900 shadow-sm transition outline-none focus:border-amber-500"
                                                    placeholder="Paste ngrok authtoken"
                                                />
                                                <p
                                                    class="text-xs text-stone-500"
                                                >
                                                    {{
                                                        credentialStatus(
                                                            props.installer
                                                                .bootstrap.ngrok
                                                                .authtoken_saved,
                                                            props.installer
                                                                .bootstrap.ngrok
                                                                .masked_authtoken,
                                                        )
                                                    }}
                                                </p>
                                            </label>

                                            <label
                                                class="space-y-2 text-sm font-medium text-stone-700"
                                            >
                                                <span>ngrok API key</span>
                                                <input
                                                    v-model="
                                                        configForm.ngrok_api_key
                                                    "
                                                    type="password"
                                                    class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900 shadow-sm transition outline-none focus:border-amber-500"
                                                    placeholder="Paste ngrok API key"
                                                />
                                                <p
                                                    class="text-xs text-stone-500"
                                                >
                                                    {{
                                                        credentialStatus(
                                                            props.installer
                                                                .bootstrap.ngrok
                                                                .api_key_saved,
                                                            props.installer
                                                                .bootstrap.ngrok
                                                                .masked_api_key,
                                                        )
                                                    }}
                                                </p>
                                            </label>

                                            <p
                                                v-if="
                                                    props.installer.bootstrap
                                                        .ngrok.runtime
                                                        ?.active_public_url
                                                "
                                                class="rounded-[18px] border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
                                            >
                                                ngrok is already live at
                                                {{
                                                    props.installer.bootstrap
                                                        .ngrok.runtime
                                                        ?.active_public_url
                                                }}.
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="flex flex-wrap items-center gap-3"
                                    >
                                        <button
                                            type="submit"
                                            class="rounded-full bg-stone-950 px-5 py-3 text-xs font-medium tracking-[0.24em] text-stone-50 uppercase transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-60"
                                            :disabled="configForm.processing"
                                        >
                                            {{
                                                configForm.processing
                                                    ? 'Saving bootstrap...'
                                                    : 'Save bootstrap settings'
                                            }}
                                        </button>
                                        <p
                                            class="text-xs tracking-[0.18em] text-stone-500 uppercase"
                                        >
                                            Secrets stay blank after save.
                                            Current saved values are only shown
                                            as masked hints.
                                        </p>
                                    </div>
                                </form>
                            </div>

                            <div
                                class="rounded-[30px] border border-stone-300/70 bg-stone-50/84 p-5 md:p-6"
                            >
                                <div
                                    class="flex flex-col gap-4 border-b border-stone-300/70 pb-4 md:flex-row md:items-end md:justify-between"
                                >
                                    <div>
                                        <p
                                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                                        >
                                            Installation profile
                                        </p>
                                        <p class="mt-2 text-sm text-stone-600">
                                            Save the office details, choose the
                                            backup destination, and run the
                                            license check before the production
                                            install pass begins.
                                        </p>
                                    </div>

                                    <div
                                        class="rounded-full border px-3 py-2 text-[11px] font-medium tracking-[0.18em] uppercase"
                                        :class="
                                            installer.state.license
                                                .access_state === 'grace'
                                                ? 'border-amber-300 bg-amber-100 text-amber-950'
                                                : licenseTone(
                                                      installer.state.license
                                                          .valid,
                                                  )
                                        "
                                    >
                                        {{
                                            installer.state.license
                                                .access_state === 'grace'
                                                ? 'Grace period active'
                                                : installer.state.license.valid
                                                  ? 'License verified'
                                                  : 'License pending'
                                        }}
                                    </div>
                                </div>

                                <form
                                    class="mt-5 space-y-5"
                                    @submit.prevent="submit"
                                >
                                    <div class="grid gap-4 md:grid-cols-2">
                                        <label
                                            class="space-y-2 text-sm font-medium text-stone-700"
                                        >
                                            <span>Office name</span>
                                            <input
                                                v-model="form.company_name"
                                                type="text"
                                                class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900 shadow-sm transition outline-none focus:border-amber-500"
                                                placeholder="Creditsoft Dallas HQ"
                                            />
                                            <span
                                                v-if="form.errors.company_name"
                                                class="text-xs text-rose-700"
                                                >{{
                                                    form.errors.company_name
                                                }}</span
                                            >
                                        </label>

                                        <label
                                            class="space-y-2 text-sm font-medium text-stone-700"
                                        >
                                            <span>Admin email</span>
                                            <input
                                                v-model="form.admin_email"
                                                type="email"
                                                class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900 shadow-sm transition outline-none focus:border-amber-500"
                                                placeholder="ops@creditsoft.local"
                                            />
                                            <span
                                                v-if="form.errors.admin_email"
                                                class="text-xs text-rose-700"
                                                >{{
                                                    form.errors.admin_email
                                                }}</span
                                            >
                                        </label>

                                        <label
                                            class="space-y-2 text-sm font-medium text-stone-700"
                                        >
                                            <span>Tailscale hostname</span>
                                            <input
                                                v-model="
                                                    form.tailscale_hostname
                                                "
                                                type="text"
                                                class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900 shadow-sm transition outline-none focus:border-amber-500"
                                                placeholder="creditsoft-office-01"
                                            />
                                            <span
                                                v-if="
                                                    form.errors
                                                        .tailscale_hostname
                                                "
                                                class="text-xs text-rose-700"
                                                >{{
                                                    form.errors
                                                        .tailscale_hostname
                                                }}</span
                                            >
                                        </label>

                                        <label
                                            class="space-y-2 text-sm font-medium text-stone-700"
                                        >
                                            <span>Backup destination</span>
                                            <select
                                                v-model="
                                                    form.backup_destination
                                                "
                                                class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 text-sm text-stone-900 shadow-sm transition outline-none focus:border-amber-500"
                                            >
                                                <option value="wasabi">
                                                    Wasabi
                                                </option>
                                                <option value="google_drive">
                                                    Google Drive
                                                </option>
                                                <option value="dropbox">
                                                    Dropbox
                                                </option>
                                                <option value="local_only">
                                                    Local only
                                                </option>
                                            </select>
                                            <span
                                                v-if="
                                                    form.errors
                                                        .backup_destination
                                                "
                                                class="text-xs text-rose-700"
                                                >{{
                                                    form.errors
                                                        .backup_destination
                                                }}</span
                                            >
                                        </label>
                                    </div>

                                    <label
                                        class="flex items-start gap-3 rounded-[22px] border border-stone-300/70 bg-white/80 px-4 py-4 text-sm text-stone-700"
                                    >
                                        <input
                                            v-model="form.portal_sync_enabled"
                                            type="checkbox"
                                            class="mt-1 size-4 rounded border-stone-300 text-amber-500 focus:ring-amber-500"
                                        />
                                        <span class="leading-6">
                                            <span
                                                class="block font-medium text-stone-900"
                                                >Enable portal sync for approved
                                                briefs</span
                                            >
                                            <span class="mt-1 block">
                                                Approved briefs and sanitized
                                                case status can flow to the
                                                portal. Raw reports, notes,
                                                attachments, and private drafts
                                                stay local.
                                            </span>
                                        </span>
                                    </label>

                                    <label
                                        class="flex items-start gap-3 rounded-[22px] border border-stone-300/70 bg-white/80 px-4 py-4 text-sm text-stone-700"
                                    >
                                        <input
                                            v-model="
                                                form.report_feedback_enabled
                                            "
                                            type="checkbox"
                                            class="mt-1 size-4 rounded border-stone-300 text-amber-500 focus:ring-amber-500"
                                        />
                                        <span class="leading-6">
                                            <span
                                                class="block font-medium text-stone-900"
                                                >Help improve CreditSoft with
                                                privacy-safe report data</span
                                            >
                                            <span class="mt-1 block">
                                                Share John-Doe style report
                                                structure, score movement, lead
                                                timing, and customer age under
                                                an org-scoped ID. No names,
                                                emails, phone numbers,
                                                addresses, notes, or attachments
                                                leave the office.
                                            </span>
                                        </span>
                                    </label>

                                    <div
                                        class="grid gap-4 md:grid-cols-[minmax(0,1fr)_240px]"
                                    >
                                        <label
                                            class="space-y-2 text-sm font-medium text-stone-700"
                                        >
                                            <span>License key</span>
                                            <input
                                                v-model="form.license_key"
                                                type="text"
                                                class="w-full rounded-2xl border border-stone-300 bg-white px-4 py-3 font-mono text-sm tracking-[0.2em] text-stone-900 uppercase shadow-sm transition outline-none focus:border-amber-500"
                                                placeholder="CSFT-AB12-CD34-EF56"
                                            />
                                            <span
                                                v-if="form.errors.license_key"
                                                class="text-xs text-rose-700"
                                                >{{
                                                    form.errors.license_key
                                                }}</span
                                            >
                                        </label>

                                        <div
                                            class="rounded-[22px] border border-stone-300/70 bg-white/80 px-4 py-4 text-sm text-stone-700"
                                        >
                                            <p
                                                class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                            >
                                                Last check
                                            </p>
                                            <p
                                                class="mt-2 font-medium text-stone-950"
                                            >
                                                {{
                                                    installer.state.license
                                                        .checked_at ??
                                                    'Not checked yet'
                                                }}
                                            </p>
                                            <p
                                                class="mt-2 text-xs tracking-[0.18em] text-stone-500 uppercase"
                                            >
                                                {{
                                                    installer.state.license
                                                        .masked_key ??
                                                    'No stored key'
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-[22px] border px-4 py-4 text-sm leading-6"
                                        :class="
                                            installer.state.license
                                                .access_state === 'grace'
                                                ? 'border-amber-300 bg-amber-100 text-amber-950'
                                                : installer.state.license
                                                        .access_state ===
                                                    'locked'
                                                  ? 'border-rose-300 bg-rose-100 text-rose-950'
                                                  : licenseTone(
                                                        installer.state.license
                                                            .valid,
                                                    )
                                        "
                                    >
                                        <p class="font-semibold">
                                            License status
                                        </p>
                                        <p class="mt-2">
                                            {{
                                                installer.state.license
                                                    .message ??
                                                'License has not been checked yet.'
                                            }}
                                        </p>
                                        <div
                                            class="mt-3 flex flex-wrap gap-2 text-xs tracking-[0.18em] text-current/80 uppercase"
                                        >
                                            <span
                                                v-if="
                                                    installer.state.license
                                                        .access_state ===
                                                    'grace'
                                                "
                                                class="rounded-full border border-current/20 bg-white/60 px-2.5 py-1"
                                            >
                                                {{
                                                    installer.state.license
                                                        .countdown_label ??
                                                    `${installer.state.license.grace_days ?? 7} day grace`
                                                }}
                                            </span>
                                            <span
                                                v-if="
                                                    installer.state.license
                                                        .grace_ends_label
                                                "
                                                class="rounded-full border border-current/20 bg-white/60 px-2.5 py-1"
                                            >
                                                Grace ends
                                                {{
                                                    installer.state.license
                                                        .grace_ends_label
                                                }}
                                            </span>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-[22px] border border-stone-300/70 bg-white/80 px-4 py-4 text-sm text-stone-700"
                                    >
                                        <p
                                            class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                                        >
                                            Validation sources
                                        </p>
                                        <p
                                            class="mt-2 leading-6 text-stone-600"
                                        >
                                            CreditSoft checks the API lane
                                            first, then the website JSON feed.
                                            If both are unavailable, the
                                            installer falls back to the local
                                            key-format check.
                                        </p>
                                        <div class="mt-3 grid gap-2">
                                            <div
                                                v-for="source in installer.licenseSources"
                                                :key="source.url"
                                                class="rounded-[18px] border border-stone-300/70 bg-stone-50 px-3 py-2"
                                            >
                                                <p
                                                    class="text-[11px] tracking-[0.18em] text-stone-500 uppercase"
                                                >
                                                    {{ source.label }}
                                                </p>
                                                <p
                                                    class="mt-1 text-sm break-all text-stone-800"
                                                >
                                                    {{ source.url }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="flex flex-wrap items-center gap-3"
                                    >
                                        <button
                                            type="submit"
                                            class="rounded-full bg-stone-950 px-5 py-3 text-xs font-medium tracking-[0.24em] text-stone-50 uppercase transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-60"
                                            :disabled="form.processing"
                                        >
                                            {{
                                                form.processing
                                                    ? 'Checking license...'
                                                    : 'Save profile and check license'
                                            }}
                                        </button>
                                        <p
                                            class="text-xs tracking-[0.18em] text-stone-500 uppercase"
                                        >
                                            License mode:
                                            {{ installer.licenseMode }}
                                        </p>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </section>

                <aside
                    class="rounded-[40px] border border-stone-300/70 bg-stone-950 p-6 text-stone-50 shadow-[0_28px_90px_rgba(28,25,23,0.22)] xl:sticky xl:top-4 xl:min-h-[calc(100vh-2rem)]"
                >
                    <div class="flex h-full flex-col">
                        <div class="border-b border-stone-800 pb-5">
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div>
                                    <p
                                        class="text-[11px] font-medium tracking-[0.32em] text-stone-400 uppercase"
                                    >
                                        JSON external ad rail
                                    </p>
                                    <h2
                                        class="mt-3 text-2xl font-semibold text-white"
                                    >
                                        {{ installer.advertisements.title }}
                                    </h2>
                                </div>
                                <div
                                    class="rounded-full border px-3 py-2 text-[11px] font-medium tracking-[0.18em] uppercase"
                                    :class="
                                        feedTone(
                                            installer.advertisements.source,
                                        )
                                    "
                                >
                                    {{ feedStatusLabel }}
                                </div>
                            </div>

                            <p class="mt-3 text-sm leading-6 text-stone-300">
                                {{ installer.advertisements.subtitle }}
                            </p>
                            <div
                                class="mt-4 grid gap-3 rounded-[24px] border border-stone-800 bg-stone-900/72 p-4 text-sm text-stone-300"
                            >
                                <div>
                                    <p
                                        class="text-[11px] tracking-[0.2em] text-stone-500 uppercase"
                                    >
                                        Updated
                                    </p>
                                    <p class="mt-2">
                                        {{
                                            installer.advertisements
                                                .updated_at ?? 'locally'
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-[11px] tracking-[0.2em] text-stone-500 uppercase"
                                    >
                                        Feed source
                                    </p>
                                    <p class="mt-2 break-all">
                                        {{
                                            installer.advertisements.feed_url ??
                                            'Local bundled feed'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 flex-1">
                            <Transition name="installer-ad" mode="out-in">
                                <article
                                    v-if="activeAdvertisement"
                                    :key="activeAdvertisement.id"
                                    class="overflow-hidden rounded-[28px] border border-stone-800 bg-[linear-gradient(180deg,_rgba(28,25,23,0.78),_rgba(12,10,9,0.96))] shadow-[inset_0_1px_0_rgba(255,255,255,0.04)]"
                                >
                                    <div
                                        class="relative aspect-[1.91/1] w-full overflow-hidden border-b border-stone-800 bg-stone-900"
                                    >
                                        <img
                                            v-if="activeAdvertisement.image_url"
                                            :src="activeAdvertisement.image_url"
                                            :alt="activeAdvertisement.title"
                                            class="h-full w-full object-cover"
                                        />
                                        <div
                                            class="absolute inset-x-4 bottom-4 flex items-center justify-between gap-3"
                                        >
                                            <div
                                                class="flex min-h-16 max-w-[66%] min-w-0 items-center rounded-2xl border border-white/70 bg-white/94 px-4 py-3 shadow-[0_14px_34px_rgba(0,0,0,0.28)]"
                                            >
                                                <img
                                                    v-if="
                                                        activeAdvertisement.logo_url
                                                    "
                                                    :src="
                                                        activeAdvertisement.logo_url
                                                    "
                                                    :alt="`${activeAdvertisement.title} logo`"
                                                    class="max-h-10 max-w-full object-contain"
                                                />
                                                <span
                                                    v-else
                                                    class="truncate text-base font-semibold text-stone-950"
                                                >
                                                    {{
                                                        activeAdvertisement.title
                                                    }}
                                                </span>
                                            </div>
                                            <span
                                                class="rounded-full border border-stone-700 bg-stone-950/88 px-3 py-2 text-[10px] tracking-[0.18em] text-stone-300 uppercase"
                                            >
                                                {{ activeAdIndex + 1 }}/{{
                                                    advertisements.length
                                                }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="p-5">
                                        <div
                                            class="flex items-start justify-between gap-3"
                                        >
                                            <p
                                                class="text-[11px] font-medium tracking-[0.28em] text-amber-300 uppercase"
                                            >
                                                {{
                                                    activeAdvertisement.eyebrow
                                                }}
                                            </p>
                                            <span
                                                class="rounded-full border border-stone-700 px-3 py-1 text-[10px] tracking-[0.18em] text-stone-400 uppercase"
                                            >
                                                20 sec
                                            </span>
                                        </div>
                                        <h3
                                            class="mt-3 text-2xl font-semibold text-stone-50"
                                        >
                                            {{ activeAdvertisement.title }}
                                        </h3>
                                        <p
                                            v-if="activeAdvertisement.summary"
                                            class="mt-3 text-sm leading-6 font-medium text-amber-100"
                                        >
                                            {{ activeAdvertisement.summary }}
                                        </p>
                                        <div
                                            class="mt-4 max-h-[380px] overflow-y-auto pr-1 text-sm leading-6 text-stone-300"
                                        >
                                            {{ activeAdvertisement.copy }}
                                        </div>
                                        <p
                                            v-if="
                                                activeAdvertisement.disclaimer
                                            "
                                            class="mt-4 text-xs leading-5 text-stone-500"
                                        >
                                            {{ activeAdvertisement.disclaimer }}
                                        </p>
                                        <a
                                            v-if="
                                                activeAdvertisement.cta_label &&
                                                activeAdvertisement.cta_url
                                            "
                                            :href="activeAdvertisement.cta_url"
                                            target="_blank"
                                            rel="noreferrer"
                                            class="mt-5 inline-flex items-center gap-2 rounded-full border border-stone-700 px-4 py-2 text-[11px] font-medium tracking-[0.2em] text-stone-200 uppercase transition hover:border-amber-300 hover:text-amber-200"
                                        >
                                            {{ activeAdvertisement.cta_label }}
                                            <ExternalLink
                                                class="size-3.5"
                                                aria-hidden="true"
                                            />
                                        </a>
                                    </div>

                                    <div
                                        class="h-1 overflow-hidden bg-stone-800"
                                    >
                                        <div
                                            :key="activeAdProgressKey"
                                            class="installer-ad-progress h-full bg-amber-300"
                                            :style="{
                                                animationDuration: `${activeAdDurationMs}ms`,
                                            }"
                                        />
                                    </div>
                                </article>
                            </Transition>

                            <div
                                v-if="advertisements.length > 1"
                                class="mt-4 grid grid-cols-4 gap-2"
                            >
                                <button
                                    v-for="(item, index) in advertisements"
                                    :key="item.id"
                                    type="button"
                                    class="min-h-11 rounded-2xl border px-2 text-[10px] font-medium tracking-[0.12em] uppercase transition"
                                    :class="
                                        index === activeAdIndex
                                            ? 'border-amber-300 bg-amber-300 text-stone-950'
                                            : 'border-stone-800 bg-stone-900 text-stone-400 hover:border-stone-600 hover:text-stone-200'
                                    "
                                    @click="selectAd(index)"
                                >
                                    {{ item.title }}
                                </button>
                            </div>

                            <p
                                v-if="!advertisements.length"
                                class="text-sm text-stone-400"
                            >
                                No installer campaigns are available yet. Add a
                                local JSON feed or configure a remote feed URL.
                            </p>
                        </div>

                        <div
                            class="mt-5 rounded-[26px] border border-stone-800 bg-stone-900/80 p-4 text-sm text-stone-300"
                        >
                            <p
                                class="text-[11px] tracking-[0.24em] text-stone-500 uppercase"
                            >
                                Why this rail exists
                            </p>
                            <p class="mt-3 leading-6">
                                Keep the intranet private and operational. Sell
                                hosting, lead-generation websites, and the
                                public customer-acquisition stack from the right
                                rail without mixing them into client casework.
                            </p>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</template>

<style scoped>
.installer-ad-enter-active,
.installer-ad-leave-active {
    transition:
        opacity 220ms ease,
        transform 220ms ease;
}

.installer-ad-enter-from,
.installer-ad-leave-to {
    opacity: 0;
    transform: translateY(10px);
}

.installer-ad-progress {
    animation-name: installer-ad-progress;
    animation-timing-function: linear;
    animation-fill-mode: forwards;
    transform-origin: left center;
}

@keyframes installer-ad-progress {
    from {
        transform: scaleX(0);
    }

    to {
        transform: scaleX(1);
    }
}
</style>
