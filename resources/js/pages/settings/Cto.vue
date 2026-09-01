<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
    faArrowUpRightFromSquare,
    faBolt,
    faChartLine,
    faCircleCheck,
    faCircleDot,
    faCircleNodes,
    faClock,
    faCloudArrowDown,
    faCloudArrowUp,
    faDatabase,
    faFloppyDisk,
    faGaugeHigh,
    faGlobe,
    faHardDrive,
    faLink,
    faMemory,
    faMicrochip,
    faNetworkWired,
    faPlugCircleBolt,
    faServer,
    faSignal,
    faTowerBroadcast,
    faTriangleExclamation,
    faUsers,
    faWandMagicSparkles,
    faWifi,
} from '@fortawesome/free-solid-svg-icons';
import DashboardWorkspaceNav from '@/components/creditsoft/DashboardWorkspaceNav.vue';
import MultiLineTrendChart from '@/components/creditsoft/MultiLineTrendChart.vue';
import CreditsoftLayout from '@/layouts/CreditsoftLayout.vue';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type PublicSpeedProvider = {
    key: 'fast' | 'speedtest';
    label: string;
    url: string;
    download_mbps?: number | null;
    upload_mbps?: number | null;
    latency_ms?: number | null;
    download_label: string;
    upload_label: string;
    latency_label: string;
    measured_at?: string | null;
    measured_at_label: string;
};

type PublicSpeedSummary = {
    providers: {
        fast: PublicSpeedProvider;
        speedtest: PublicSpeedProvider;
    };
    average: {
        provider_count: number;
        download_mbps?: number | null;
        upload_mbps?: number | null;
        latency_ms?: number | null;
        download_label: string;
        upload_label: string;
        latency_label: string;
    };
};

type PerformanceRecommendationResult = {
    title: string;
    summary: string;
    bottleneck: string;
    recommendations: string[];
    meta?: {
        provider?: string | null;
        model?: string | null;
    };
};

type PerformanceActionResult = {
    ok?: boolean;
    action?: string;
    title?: string;
    message?: string;
    requires_restart?: boolean;
    target_label?: string;
    preferred_label?: string;
    preferred_api_base_url?: string;
    cluster_action_uuid?: string;
    cluster_node_count?: number;
    cluster_delivered?: number;
    cluster_queued?: number;
    cluster_messages?: string[];
    peer_results?: PerformanceActionResult[];
};

defineOptions({
    layout: CreditsoftLayout,
});

const props = defineProps<{
    diagnostics: {
        current: {
            captured_at: string;
            machine: {
                hostname: string;
                office_label: string;
                os_family: string;
                kernel: string;
                architecture: string;
                php_version: string;
                php_sapi: string;
                laravel_version: string;
                app_version: string;
                app_build: string;
                database_driver: string;
                database_version: string;
                opcache_status: string;
                cpu_cores: number;
            };
            load: {
                one: number;
                five: number;
                fifteen: number;
            };
            memory: {
                total_label: string;
                used_label: string;
                free_label: string;
                available_label?: string | null;
                pressure_label?: string | null;
                pressure_level?: string | null;
                pressure_free_percent?: number | null;
            };
            swap: {
                total_label: string;
                used_label: string;
                free_label: string;
            };
            disk: {
                path: string;
                total_label: string;
                used_label: string;
                free_label: string;
                used_percent: number;
            };
            network: {
                rx_label: string;
                tx_label: string;
            };
            storage: {
                database: {
                    driver_label: string;
                    size_bytes: number;
                    size_label: string;
                    path?: string | null;
                    last_backup_label?: string | null;
                    backup_count: number;
                };
                documents: {
                    count: number;
                    record_count?: number;
                    file_backed_count?: number;
                    metadata_only_count?: number;
                    file_size_label?: string;
                    filesystem_file_count?: number;
                    filesystem_size_label?: string;
                    path: string;
                    stored_in_database: boolean;
                };
            };
            client_storage: {
                document_count?: number;
                file_backed_document_count?: number;
                file_backed_client_count?: number;
                metadata_only_document_count?: number;
                document_coverage_percent?: number;
                total_label: string;
                average_label: string;
                file_backed_average_label?: string | null;
                database_average_label: string;
                estimated_client_footprint_bytes?: number | null;
                estimated_client_footprint_label: string;
                estimated_more_clients?: number | null;
                estimate_ready?: boolean;
                estimate_note: string;
                biggest?: {
                    label: string;
                    size_label: string;
                    document_count: number;
                } | null;
                smallest?: {
                    label: string;
                    size_label: string;
                    document_count: number;
                } | null;
            };
            staff_activity: {
                window_label: string;
                staff_count: number;
                total_events: number;
                most_active?: {
                    label: string;
                    role_label: string;
                    event_count: number;
                    last_seen_label: string;
                } | null;
                least_active?: {
                    label: string;
                    role_label: string;
                    event_count: number;
                    last_seen_label: string;
                } | null;
            };
        };
        history: {
            window_label: string;
            labels: string[];
            load: {
                series: Array<{
                    label: string;
                    values: Array<number | null>;
                    color: string;
                    type?: 'line' | 'bar';
                }>;
            };
            memory: {
                series: Array<{
                    label: string;
                    values: Array<number | null>;
                    color: string;
                    type?: 'line' | 'bar';
                }>;
            };
            swap: {
                series: Array<{
                    label: string;
                    values: Array<number | null>;
                    color: string;
                    type?: 'line' | 'bar';
                }>;
            };
            disk: {
                series: Array<{
                    label: string;
                    values: Array<number | null>;
                    color: string;
                    type?: 'line' | 'bar';
                }>;
            };
            network: {
                series: Array<{
                    label: string;
                    values: Array<number | null>;
                    color: string;
                    type?: 'line' | 'bar';
                }>;
            };
        };
        cluster: {
            enabled: boolean;
            office_label: string;
            peer_count: number;
            online_count: number;
            connection: {
                tested_count: number;
                summary_label: string;
                fastest_latency_label: string;
                slowest_latency_label: string;
                best_throughput_label: string;
            };
            totals: {
                memory_total_label: string;
                memory_used_label: string;
                swap_total_label: string;
                swap_used_label: string;
                disk_total_label: string;
                disk_used_label: string;
                disk_free_bytes?: number | null;
                disk_free_label?: string | null;
                network_rx_label: string;
                network_tx_label: string;
            };
            nodes: Array<{
                label: string;
                configured_label?: string | null;
                detail_label?: string | null;
                base_url?: string | null;
                license_key?: string | null;
                source: string;
                online: boolean;
                error?: string | null;
                connection: {
                    tested: boolean;
                    status_label: string;
                    latency_ms?: number | null;
                    latency_label: string;
                    transfer_bytes: number;
                    transfer_label: string;
                    throughput_bps: number;
                    throughput_label: string;
                    measured_at?: string | null;
                    measured_at_label: string;
                };
                summary?: {
                    machine: {
                        hostname: string;
                        cpu_cores: number;
                        os_family?: string | null;
                        architecture?: string | null;
                        diagnostics_source?: string | null;
                    };
                    memory: {
                        total_bytes?: number | null;
                        used_bytes?: number | null;
                        free_bytes?: number | null;
                        available_bytes?: number | null;
                        available_percent?: number | null;
                        pressure_free_percent?: number | null;
                        pressure_level?: string | null;
                        pressure_label?: string | null;
                        used_label: string;
                        total_label: string;
                        free_label?: string | null;
                        available_label?: string | null;
                    };
                    swap?: {
                        total_bytes?: number | null;
                        used_bytes?: number | null;
                        free_bytes?: number | null;
                        used_label?: string;
                        total_label?: string;
                    };
                    disk: {
                        used_label: string;
                        total_label: string;
                        free_label?: string | null;
                        used_percent: number;
                    };
                    network: {
                        rx_label: string;
                        tx_label: string;
                    };
                    load: {
                        one: number;
                        five: number;
                        fifteen: number;
                    };
                } | null;
            }>;
        };
    };
    public_speed: PublicSpeedSummary;
}>();

const speedInput = (value?: number | null) =>
    value === null || value === undefined ? '' : String(value);

const fastSpeedForm = useForm({
    provider: 'fast',
    download_mbps: speedInput(props.public_speed.providers.fast.download_mbps),
    upload_mbps: speedInput(props.public_speed.providers.fast.upload_mbps),
    latency_ms: speedInput(props.public_speed.providers.fast.latency_ms),
});

const speedtestSpeedForm = useForm({
    provider: 'speedtest',
    download_mbps: speedInput(
        props.public_speed.providers.speedtest.download_mbps,
    ),
    upload_mbps: speedInput(props.public_speed.providers.speedtest.upload_mbps),
    latency_ms: speedInput(props.public_speed.providers.speedtest.latency_ms),
});
const performanceRecommendations = ref<PerformanceRecommendationResult | null>(
    null,
);
const performanceRecommendationError = ref('');
const performanceRecommendationsLoading = ref(false);
const performanceActionLoading = ref('');
const performanceActionError = ref('');
const performanceActionResult = ref<PerformanceActionResult | null>(null);

const submitPublicSpeed = (provider: 'fast' | 'speedtest') => {
    const form = provider === 'fast' ? fastSpeedForm : speedtestSpeedForm;

    form.post('/cto/public-speed', {
        preserveScroll: true,
    });
};

const csrfToken = () =>
    document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

const askPerformanceAdvisor = async () => {
    performanceRecommendationsLoading.value = true;
    performanceRecommendationError.value = '';

    try {
        const response = await fetch('/cto/performance-recommendations', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({}),
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(
                payload.message ??
                    'The performance advisor could not answer right now.',
            );
        }

        performanceRecommendations.value =
            payload as PerformanceRecommendationResult;
    } catch (error) {
        performanceRecommendationError.value =
            error instanceof Error
                ? error.message
                : 'The performance advisor could not answer right now.';
    } finally {
        performanceRecommendationsLoading.value = false;
    }
};

const memoryActionTarget = computed(() => {
    const nodes = props.diagnostics.cluster.nodes.filter(
        (node) => node.online && node.summary,
    );

    if (nodes.length === 0) {
        return null;
    }

    const ranked = [...nodes].sort(
        (left, right) => memoryPressureScore(right) - memoryPressureScore(left),
    );
    const top = ranked[0];

    return top && memoryPressureScore(top) > 0 ? top : null;
});

const memoryPressureScore = (
    node: (typeof props.diagnostics.cluster.nodes)[number],
) => {
    const memory = node.summary?.memory;
    const swap = node.summary?.swap?.used_bytes ?? 0;
    const level = (memory?.pressure_level ?? '').toLowerCase();

    if (level === 'healthy' && swap === 0) {
        return 0;
    }

    const availablePercent =
        memory?.pressure_free_percent ?? memory?.available_percent ?? null;
    const memoryTotal = memory?.total_bytes ?? 1;
    const memoryUsed = memory?.used_bytes ?? 0;
    const usedPercent =
        availablePercent === null
            ? (memoryUsed / Math.max(memoryTotal, 1)) * 100
            : 100 - availablePercent;
    const levelScore =
        level === 'critical'
            ? 12
            : level === 'pressured'
              ? 8
              : level === 'watch'
                ? 4
                : 0;

    return levelScore + swap / 1073741824 + Math.max(usedPercent - 85, 0) / 10;
};

const runPerformanceAction = async (
    action: 'memory_saver_profile' | 'prefer_healthy_node' | 'ram_action_note',
) => {
    performanceActionLoading.value = action;
    performanceActionError.value = '';
    performanceActionResult.value = null;

    const target =
        action === 'memory_saver_profile' || action === 'ram_action_note'
            ? memoryActionTarget.value
            : null;

    try {
        const response = await fetch('/cto/performance-action', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                action,
                target_base_url: target?.base_url ?? null,
                target_label: target?.label ?? null,
            }),
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(
                payload.message ?? 'The CTO action could not run right now.',
            );
        }

        performanceActionResult.value = payload as PerformanceActionResult;
    } catch (error) {
        performanceActionError.value =
            error instanceof Error
                ? error.message
                : 'The CTO action could not run right now.';
    } finally {
        performanceActionLoading.value = '';
    }
};

const compactCount = (value?: number | null) => {
    if (value === null || value === undefined) {
        return '—';
    }

    return new Intl.NumberFormat('en-US', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value);
};

const exactCount = (value?: number | null) => {
    if (value === null || value === undefined) {
        return 'No estimate yet';
    }

    return new Intl.NumberFormat('en-US').format(value);
};

const capacityEstimate = computed(() => {
    const footprint =
        props.diagnostics.current.client_storage
            .estimated_client_footprint_bytes ?? 0;
    const clusterFree = props.diagnostics.cluster.totals.disk_free_bytes ?? 0;
    const localEstimate =
        props.diagnostics.current.client_storage.estimated_more_clients ?? null;

    if (footprint > 0 && clusterFree > 0) {
        const reservedBytes = Math.min(clusterFree * 0.15, 20 * 1024 ** 3);

        return {
            ready: true,
            value: Math.floor(Math.max(clusterFree - reservedBytes, 0) / footprint),
            scope:
                props.diagnostics.cluster.online_count > 1
                    ? 'cluster capacity estimate'
                    : 'capacity estimate',
            detail:
                props.diagnostics.cluster.online_count > 1
                    ? `Uses ${props.diagnostics.cluster.totals.disk_free_label ?? 'available disk'} free across online nodes and ${props.diagnostics.current.client_storage.estimated_client_footprint_label} per client. ${props.diagnostics.current.client_storage.estimate_note}`
                    : props.diagnostics.current.client_storage.estimate_note,
        };
    }

    return {
        ready: localEstimate !== null,
        value: localEstimate,
        scope: 'capacity estimate',
        detail: props.diagnostics.current.client_storage.estimate_note,
    };
});

const clusterNodes = computed(() =>
    [...props.diagnostics.cluster.nodes].sort((left, right) => {
        if (left.source === 'local' && right.source !== 'local') {
            return -1;
        }

        if (left.source !== 'local' && right.source === 'local') {
            return 1;
        }

        if (left.online !== right.online) {
            return left.online ? -1 : 1;
        }

        return left.label.localeCompare(right.label);
    }),
);

const clusterHeading = computed(() => {
    const labels = clusterNodes.value
        .map((node) => node.label?.trim())
        .filter((label): label is string => Boolean(label));
    const uniqueLabels = [...new Set(labels)];

    if (uniqueLabels.length === 0) {
        return 'Office server nodes in one view';
    }

    if (uniqueLabels.length === 1) {
        return `${uniqueLabels[0]} in one view`;
    }

    if (uniqueLabels.length === 2) {
        return `${uniqueLabels[0]} and ${uniqueLabels[1]} in one view`;
    }

    return `${uniqueLabels[0]}, ${uniqueLabels[1]} + ${uniqueLabels.length - 2} more in one view`;
});

const nodePanelClass = (
    node: (typeof props.diagnostics.cluster.nodes)[number],
) => {
    if (!node.online) {
        return 'border-rose-200 bg-rose-50/70';
    }

    return node.source === 'local'
        ? 'border-amber-300/80 bg-amber-50/50'
        : 'border-emerald-300/80 bg-emerald-50/50';
};

const nodeStatusClass = (
    node: (typeof props.diagnostics.cluster.nodes)[number],
) => {
    if (!node.online) {
        return 'text-rose-600';
    }

    return node.source === 'local' ? 'text-amber-600' : 'text-emerald-600';
};

const nodeStatusLabel = (
    node: (typeof props.diagnostics.cluster.nodes)[number],
) => {
    if (!node.online) {
        return 'Offline';
    }

    return node.source === 'local' ? 'Local' : 'Online peer';
};

const nodeDetailLabel = (
    node: (typeof props.diagnostics.cluster.nodes)[number],
) => node.detail_label ?? node.base_url ?? 'No endpoint';

const nodeProbeLabel = (
    node: (typeof props.diagnostics.cluster.nodes)[number],
) =>
    node.source === 'local'
        ? 'Local loopback'
        : `Auto probe ${node.connection.measured_at_label}`;
</script>

<template>
    <Head title="CTO" />

    <div class="space-y-6">
        <section
            class="flex items-start justify-between gap-4 border-b border-stone-300/70 pb-5"
        >
            <div class="space-y-1">
                <p
                    class="text-[11px] font-medium tracking-[0.24em] text-stone-500 uppercase"
                >
                    System control
                </p>
                <h1 class="text-2xl font-semibold text-stone-950">CTO</h1>
                <p class="max-w-4xl text-sm leading-6 text-stone-600">
                    Server nodes, routing, update health, public speed, and
                    capacity in one operational view.
                </p>
            </div>
            <DashboardWorkspaceNav />
        </section>

        <section
            class="overflow-hidden rounded-lg border border-stone-300/70 bg-white/95"
        >
            <div
                class="flex flex-col gap-5 border-b border-stone-200/80 px-5 py-5 xl:flex-row xl:items-end xl:justify-between"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-amber-200 bg-amber-50 text-amber-700"
                    >
                        <FontAwesomeIcon :icon="faCircleNodes" />
                    </div>
                    <div>
                        <p
                            class="text-[11px] font-medium tracking-[0.28em] text-stone-500 uppercase"
                        >
                            Server node mesh
                        </p>
                        <h2 class="mt-2 text-xl font-semibold text-stone-950">
                            {{ clusterHeading }}
                        </h2>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-stone-600">
                            Local and approved office nodes with status, load,
                            RAM pressure, disk, tailnet link, and router
                            readiness.
                        </p>
                    </div>
                </div>

                <div
                    class="grid gap-3 text-sm text-stone-600 sm:grid-cols-2 xl:min-w-[520px] xl:grid-cols-4"
                >
                    <div class="border-l border-stone-200 pl-3">
                        <p class="text-[10px] tracking-[0.18em] text-stone-500 uppercase">
                            Online
                        </p>
                        <p class="mt-1 font-semibold text-stone-950">
                            {{ diagnostics.cluster.online_count }} /
                            {{ diagnostics.cluster.peer_count + 1 }}
                        </p>
                    </div>
                    <div class="border-l border-stone-200 pl-3">
                        <p class="text-[10px] tracking-[0.18em] text-stone-500 uppercase">
                            Remote link
                        </p>
                        <p class="mt-1 font-semibold text-stone-950">
                            {{ diagnostics.cluster.connection.best_throughput_label }}
                        </p>
                    </div>
                    <div class="border-l border-stone-200 pl-3">
                        <p class="text-[10px] tracking-[0.18em] text-stone-500 uppercase">
                            Public avg
                        </p>
                        <p class="mt-1 font-semibold text-stone-950">
                            {{ public_speed.average.download_label }}
                        </p>
                    </div>
                    <div class="border-l border-stone-200 pl-3">
                        <p class="text-[10px] tracking-[0.18em] text-stone-500 uppercase">
                            Build
                        </p>
                        <p class="mt-1 font-semibold text-stone-950">
                            {{ diagnostics.current.machine.app_build }}
                        </p>
                    </div>
                </div>
            </div>

            <div
                class="grid items-start gap-4 px-5 py-5 xl:grid-cols-[minmax(0,1fr)_minmax(430px,0.42fr)] 2xl:grid-cols-[minmax(0,1fr)_minmax(460px,0.42fr)]"
            >
                <div class="grid content-start items-start gap-3 lg:grid-cols-2">
                    <article
                        v-for="node in clusterNodes"
                        :key="`${node.source}-${node.label}-${node.base_url ?? 'local'}`"
                        class="h-fit rounded-lg border px-4 py-3 transition"
                        :class="nodePanelClass(node)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <FontAwesomeIcon
                                        :icon="faCircleDot"
                                        :class="nodeStatusClass(node)"
                                    />
                                    <h3
                                        class="truncate text-base font-semibold text-stone-950"
                                    >
                                        {{ node.label }}
                                    </h3>
                                </div>
                                <p
                                    class="mt-1 truncate text-xs text-stone-600"
                                >
                                    {{ nodeDetailLabel(node) }}
                                </p>
                            </div>
                            <div
                                class="shrink-0 text-right text-[10px] font-semibold tracking-[0.16em] uppercase"
                                :class="nodeStatusClass(node)"
                            >
                                {{ nodeStatusLabel(node) }}
                            </div>
                        </div>

                        <div
                            v-if="node.summary"
                            class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2.5 text-sm"
                        >
                            <div>
                                <p class="text-[10px] tracking-[0.16em] text-stone-500 uppercase">
                                    CPU / load
                                </p>
                                <p class="mt-1 font-semibold text-stone-950">
                                    {{ node.summary.machine.cpu_cores }} cores ·
                                    {{ node.summary.load.one }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[10px] tracking-[0.16em] text-stone-500 uppercase">
                                    RAM
                                </p>
                                <p class="mt-1 font-semibold text-stone-950">
                                    {{
                                        node.summary.memory.available_label ??
                                        node.summary.memory.free_label
                                    }}
                                    free
                                </p>
                                <p
                                    v-if="node.summary.memory.pressure_label"
                                    class="text-xs text-stone-600"
                                >
                                    {{ node.summary.memory.pressure_label }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[10px] tracking-[0.16em] text-stone-500 uppercase">
                                    Disk
                                </p>
                                <p class="mt-1 font-semibold text-stone-950">
                                    {{ node.summary.disk.used_percent }}% used
                                </p>
                                <p class="text-xs text-stone-600">
                                    {{ node.summary.disk.free_label }} free
                                </p>
                            </div>
                            <div>
                                <p class="text-[10px] tracking-[0.16em] text-stone-500 uppercase">
                                    Link
                                </p>
                                <p class="mt-1 font-semibold text-stone-950">
                                    {{ node.connection.throughput_label }}
                                </p>
                                <p class="text-xs text-stone-600">
                                    {{ node.connection.latency_label }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-else
                            class="mt-3 border-l-2 border-rose-300 pl-3 text-sm leading-6 text-rose-900"
                        >
                            {{
                                node.error ??
                                'This node is configured but not responding.'
                            }}
                        </div>

                        <div
                            class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-black/10 pt-3 text-xs text-stone-600"
                        >
                            <span>{{ node.connection.status_label }}</span>
                            <span>{{ nodeProbeLabel(node) }}</span>
                            <span v-if="node.summary">
                                {{ node.summary.machine.os_family }}
                            </span>
                        </div>
                    </article>
                </div>

                <aside
                    class="grid content-start gap-4 border-t border-stone-200 pt-4 xl:border-t-0 xl:border-l xl:pt-0 xl:pl-5"
                >
                    <div>
                        <p
                            class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                        >
                            Runtime
                        </p>
                        <p class="mt-2 text-sm font-semibold text-stone-950">
                            PHP {{ diagnostics.current.machine.php_version }} ·
                            {{
                                diagnostics.current.storage.database
                                    .driver_label
                            }}
                            {{ diagnostics.current.machine.database_version }}
                        </p>
                        <p class="mt-1 text-xs leading-5 text-stone-600">
                            CreditSoft v{{ diagnostics.current.machine.app_version }}
                            · Laravel
                            {{ diagnostics.current.machine.laravel_version }} ·
                            OPcache {{ diagnostics.current.machine.opcache_status }}
                        </p>
                    </div>

                    <div class="grid grid-cols-3 gap-3 border-y border-stone-200 py-4">
                        <div>
                            <p class="text-[10px] tracking-[0.16em] text-stone-500 uppercase">
                                1 min
                            </p>
                            <p class="mt-1 text-lg font-semibold text-stone-950">
                                {{ diagnostics.current.load.one }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[10px] tracking-[0.16em] text-stone-500 uppercase">
                                5 min
                            </p>
                            <p class="mt-1 text-lg font-semibold text-stone-950">
                                {{ diagnostics.current.load.five }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[10px] tracking-[0.16em] text-stone-500 uppercase">
                                15 min
                            </p>
                            <p class="mt-1 text-lg font-semibold text-stone-950">
                                {{ diagnostics.current.load.fifteen }}
                            </p>
                        </div>
                    </div>

                    <div>
                        <p class="text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase">
                            Client storage
                        </p>
                        <div class="mt-3 grid gap-3 text-sm text-stone-600">
                            <p>
                                <span class="font-semibold text-stone-950">
                                    {{
                                        diagnostics.current.storage.documents
                                            .file_size_label ??
                                        diagnostics.current.client_storage
                                            .total_label
                                    }}
                                </span>
                                real files stored across dossiers.
                            </p>
                            <p>
                                <span class="font-semibold text-stone-950">
                                    {{
                                        exactCount(
                                            diagnostics.current.storage.documents
                                                .record_count ??
                                                diagnostics.current.storage
                                                    .documents.count,
                                        )
                                    }}
                                </span>
                                document records ·
                                {{
                                    exactCount(
                                        diagnostics.current.storage.documents
                                            .metadata_only_count ??
                                            diagnostics.current.client_storage
                                                .metadata_only_document_count,
                                    )
                                }}
                                metadata-only.
                            </p>
                            <TooltipProvider :delay-duration="0">
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <button
                                            type="button"
                                            class="inline-flex w-fit items-end gap-2 text-left"
                                            :class="
                                                capacityEstimate.ready
                                                    ? ''
                                                    : 'cursor-help'
                                            "
                                        >
                                            <span
                                                class="text-2xl font-semibold text-stone-950"
                                            >
                                                <template
                                                    v-if="
                                                        capacityEstimate.ready
                                                    "
                                                >
                                                    {{
                                                        compactCount(
                                                            capacityEstimate.value,
                                                        )
                                                    }}
                                                </template>
                                                <template v-else>Pending</template>
                                            </span>
                                            <span
                                                class="pb-1 text-[10px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                                            >
                                                {{ capacityEstimate.scope }}
                                            </span>
                                        </button>
                                    </TooltipTrigger>
                                    <TooltipContent
                                        variant="light-card"
                                        side="top"
                                        align="center"
                                        :side-offset="8"
                                        class="max-w-[260px] text-left"
                                    >
                                        <div class="space-y-1.5">
                                            <div
                                                class="text-[10px] font-semibold tracking-[0.2em] text-stone-500 uppercase"
                                            >
                                                Storage estimate
                                            </div>
                                            <div
                                                class="text-sm font-semibold text-stone-950"
                                            >
                                                <template
                                                    v-if="
                                                        capacityEstimate.ready
                                                    "
                                                >
                                                    {{
                                                        exactCount(
                                                            capacityEstimate.value,
                                                        )
                                                    }}
                                                    more clients
                                                </template>
                                                <template v-else>
                                                    Waiting on local files
                                                </template>
                                            </div>
                                            <div
                                                class="text-[11px] leading-5 text-stone-600"
                                            >
                                                {{
                                                    capacityEstimate.detail
                                                }}
                                            </div>
                                        </div>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div
                class="rounded-lg border border-stone-300/70 bg-white/95 px-5 py-5"
            >
                <div class="flex items-center gap-3">
                    <FontAwesomeIcon :icon="faMemory" class="text-stone-700" />
                    <p
                        class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                    >
                        Memory
                    </p>
                </div>
                <p class="mt-4 text-2xl font-semibold text-stone-950">
                    {{ diagnostics.current.memory.used_label }}
                </p>
                <p class="mt-1 text-sm text-stone-600">
                    Used of {{ diagnostics.current.memory.total_label }}
                </p>
                <p
                    v-if="diagnostics.current.memory.available_label"
                    class="mt-1 text-xs text-stone-500"
                >
                    Available {{ diagnostics.current.memory.available_label }}
                    <span v-if="diagnostics.current.memory.pressure_label">
                        · {{ diagnostics.current.memory.pressure_label }}
                    </span>
                </p>
            </div>
            <div
                class="rounded-lg border border-stone-300/70 bg-white/95 px-5 py-5"
            >
                <div class="flex items-center gap-3">
                    <FontAwesomeIcon
                        :icon="faMicrochip"
                        class="text-stone-700"
                    />
                    <p
                        class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                    >
                        Swap
                    </p>
                </div>
                <p class="mt-4 text-2xl font-semibold text-stone-950">
                    {{ diagnostics.current.swap.used_label }}
                </p>
                <p class="mt-1 text-sm text-stone-600">
                    Used of {{ diagnostics.current.swap.total_label }}
                </p>
            </div>
            <div
                class="rounded-lg border border-stone-300/70 bg-white/95 px-5 py-5"
            >
                <div class="flex items-center gap-3">
                    <FontAwesomeIcon
                        :icon="faHardDrive"
                        class="text-stone-700"
                    />
                    <p
                        class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                    >
                        Disk
                    </p>
                </div>
                <p class="mt-4 text-2xl font-semibold text-stone-950">
                    {{ diagnostics.current.disk.used_label }}
                </p>
                <p class="mt-1 text-sm text-stone-600">
                    Used of {{ diagnostics.current.disk.total_label }} ·
                    {{ diagnostics.current.disk.used_percent }}%
                </p>
            </div>
            <div
                class="rounded-lg border border-stone-300/70 bg-white/95 px-5 py-5"
            >
                <div class="flex items-center gap-3">
                    <FontAwesomeIcon
                        :icon="faNetworkWired"
                        class="text-stone-700"
                    />
                    <p
                        class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                    >
                        Network
                    </p>
                </div>
                <p class="mt-4 text-lg font-semibold text-stone-950">
                    RX {{ diagnostics.current.network.rx_label }}
                </p>
                <p class="mt-1 text-sm text-stone-600">
                    TX {{ diagnostics.current.network.tx_label }}
                </p>
            </div>
            <div
                class="rounded-lg border border-stone-300/70 bg-white/95 px-5 py-5 md:col-span-2 xl:col-span-1"
            >
                <div class="flex items-center gap-3">
                    <FontAwesomeIcon :icon="faUsers" class="text-stone-700" />
                    <p
                        class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                    >
                        Staff activity
                    </p>
                </div>
                <p class="mt-4 text-sm font-semibold text-stone-950">
                    {{
                        diagnostics.current.staff_activity.most_active?.label ??
                        'No staff activity yet'
                    }}
                </p>
                <p class="mt-1 text-xs leading-5 text-stone-600">
                    Most active ·
                    {{
                        diagnostics.current.staff_activity.most_active
                            ?.event_count ?? 0
                    }}
                    events ·
                    {{ diagnostics.current.staff_activity.window_label }}
                </p>
                <div class="mt-3 border-t border-stone-200 pt-3">
                    <p class="text-sm font-semibold text-stone-950">
                        {{
                            diagnostics.current.staff_activity.least_active
                                ?.label ?? 'No quiet staff yet'
                        }}
                    </p>
                    <p class="mt-1 text-xs leading-5 text-stone-600">
                        Quietest ·
                        {{
                            diagnostics.current.staff_activity.least_active
                                ?.event_count ?? 0
                        }}
                        events ·
                        {{
                            diagnostics.current.staff_activity.least_active
                                ?.last_seen_label ?? 'Not seen yet'
                        }}
                    </p>
                </div>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-lg border border-stone-300/70 bg-white/95"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-4 border-b border-stone-200/80 px-6 py-5"
            >
                <div class="flex items-center gap-3">
                    <FontAwesomeIcon
                        :icon="faWandMagicSparkles"
                        class="text-[24px] text-stone-700"
                    />
                    <div>
                        <p
                            class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                        >
                            OpenRouter Nemotron
                        </p>
                        <h2 class="mt-2 text-lg font-semibold text-stone-950">
                            Top 3 performance recommendations
                        </h2>
                        <p class="mt-1 max-w-3xl text-sm text-stone-600">
                            CPU, memory, disk, cluster, and public speed data
                            are scored together.
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    :disabled="performanceRecommendationsLoading"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-stone-950 px-4 text-sm font-semibold text-white transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="askPerformanceAdvisor"
                >
                    <FontAwesomeIcon
                        :icon="faBolt"
                        class="text-xs text-amber-300"
                    />
                    {{
                        performanceRecommendationsLoading
                            ? 'Asking...'
                            : 'Ask Nemotron'
                    }}
                </button>
            </div>

            <div class="px-6 py-5">
                <div
                    v-if="performanceRecommendationError"
                    class="border-l-2 border-rose-400 bg-rose-50 px-4 py-3 text-sm leading-6 text-rose-950"
                >
                    {{ performanceRecommendationError }}
                </div>

                <div
                    v-else-if="performanceRecommendations"
                    class="space-y-5"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <p class="text-sm font-semibold text-stone-950">
                                {{ performanceRecommendations.title }}
                            </p>
                            <p class="mt-1 text-sm leading-6 text-stone-600">
                                {{ performanceRecommendations.summary }}
                            </p>
                        </div>
                        <div
                            class="text-right text-[11px] tracking-[0.18em] text-stone-500 uppercase"
                        >
                            <p>{{ performanceRecommendations.bottleneck }}</p>
                            <p
                                v-if="performanceRecommendations.meta?.model"
                                class="mt-1 normal-case tracking-normal text-stone-400"
                            >
                                {{ performanceRecommendations.meta.model }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="grid divide-y divide-stone-200 border-y border-stone-200 md:grid-cols-3 md:divide-x md:divide-y-0"
                    >
                        <div
                            v-for="(
                                recommendation, index
                            ) in performanceRecommendations.recommendations"
                            :key="`${index}-${recommendation}`"
                            class="px-0 py-4 md:px-5"
                        >
                            <p
                                class="text-[10px] tracking-[0.22em] text-stone-500 uppercase"
                            >
                                {{ index + 1 }}
                            </p>
                            <p class="mt-2 text-sm leading-6 text-stone-700">
                                {{ recommendation }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="grid gap-3 border-t border-stone-200 pt-5 lg:grid-cols-3"
                    >
                        <button
                            type="button"
                            :disabled="
                                performanceActionLoading !== '' ||
                                !memoryActionTarget
                            "
                            class="group flex min-h-[132px] flex-col items-start justify-between rounded-lg border border-stone-200 bg-stone-50 px-4 py-4 text-left transition hover:border-amber-300 hover:bg-amber-50 disabled:cursor-not-allowed disabled:opacity-60"
                            @click="runPerformanceAction('memory_saver_profile')"
                        >
                            <FontAwesomeIcon
                                :icon="faMemory"
                                class="text-[22px] text-stone-700 group-hover:text-amber-700"
                            />
                            <span>
                                <span
                                    class="block text-sm font-semibold text-stone-950"
                                >
                                    Apply memory saver
                                </span>
                                <span
                                    class="mt-1 block text-xs leading-5 text-stone-600"
                                >
                                    Stage smaller OPcache and PostgreSQL memory
                                    limits for
                                    {{
                                        memoryActionTarget?.label ??
                                        'the pressured node'
                                    }}.
                                </span>
                            </span>
                        </button>

                        <button
                            type="button"
                            :disabled="performanceActionLoading !== ''"
                            class="group flex min-h-[132px] flex-col items-start justify-between rounded-lg border border-stone-200 bg-stone-50 px-4 py-4 text-left transition hover:border-emerald-300 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-60"
                            @click="runPerformanceAction('prefer_healthy_node')"
                        >
                            <FontAwesomeIcon
                                :icon="faServer"
                                class="text-[22px] text-stone-700 group-hover:text-emerald-700"
                            />
                            <span>
                                <span
                                    class="block text-sm font-semibold text-stone-950"
                                >
                                    Prefer healthiest node
                                </span>
                                <span
                                    class="mt-1 block text-xs leading-5 text-stone-600"
                                >
                                    Save a resource-aware router hint so client
                                    probes lean toward the stronger server.
                                </span>
                            </span>
                        </button>

                        <button
                            type="button"
                            :disabled="
                                performanceActionLoading !== '' ||
                                !memoryActionTarget
                            "
                            class="group flex min-h-[132px] flex-col items-start justify-between rounded-lg border border-stone-200 bg-stone-50 px-4 py-4 text-left transition hover:border-rose-300 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-60"
                            @click="runPerformanceAction('ram_action_note')"
                        >
                            <FontAwesomeIcon
                                :icon="faTriangleExclamation"
                                class="text-[22px] text-stone-700 group-hover:text-rose-700"
                            />
                            <span>
                                <span
                                    class="block text-sm font-semibold text-stone-950"
                                >
                                    Record RAM action
                                </span>
                                <span
                                    class="mt-1 block text-xs leading-5 text-stone-600"
                                >
                                    Track the hardware/admin follow-up without
                                    pretending software can add memory.
                                </span>
                            </span>
                        </button>
                    </div>

                    <div
                        v-if="performanceActionLoading"
                        class="border-l-2 border-amber-400 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950"
                    >
                        Running CTO action...
                    </div>

                    <div
                        v-if="performanceActionError"
                        class="border-l-2 border-rose-400 bg-rose-50 px-4 py-3 text-sm leading-6 text-rose-950"
                    >
                        {{ performanceActionError }}
                    </div>

                    <div
                        v-if="performanceActionResult"
                        class="border-l-2 border-emerald-500 bg-emerald-50 px-4 py-3 text-sm leading-6 text-emerald-950"
                    >
                        <p class="font-semibold">
                            {{
                                performanceActionResult.title ??
                                'CTO action complete'
                            }}
                        </p>
                        <p>
                            {{
                                performanceActionResult.message ??
                                'The requested action was saved.'
                            }}
                        </p>
                        <p
                            v-if="performanceActionResult.requires_restart"
                            class="mt-1 text-xs text-emerald-800"
                        >
                            Restart the office Docker services when the node is
                            ready.
                        </p>
                        <p
                            v-if="performanceActionResult.cluster_node_count"
                            class="mt-2 text-xs font-semibold text-emerald-900"
                        >
                            Cluster nodes checked:
                            {{ performanceActionResult.cluster_node_count }}.
                            Delivered:
                            {{ performanceActionResult.cluster_delivered ?? 0 }}.
                            Queued:
                            {{ performanceActionResult.cluster_queued ?? 0 }}.
                        </p>
                        <ul
                            v-if="performanceActionResult.cluster_messages?.length"
                            class="mt-2 space-y-1 text-xs text-emerald-900"
                        >
                            <li
                                v-for="message in performanceActionResult.cluster_messages"
                                :key="message"
                            >
                                {{ message }}
                            </li>
                        </ul>
                    </div>
                </div>

                <div v-else class="text-sm leading-6 text-stone-600">
                    Ask for a ranked upgrade call using the current CTO
                    diagnostics.
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <div
                class="overflow-hidden rounded-lg border border-stone-300/70 bg-white/95"
            >
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <div class="flex items-center gap-3">
                        <FontAwesomeIcon
                            :icon="faChartLine"
                            class="text-stone-700"
                        />
                        <div>
                            <p
                                class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                            >
                                Load history
                            </p>
                            <h2
                                class="mt-2 text-lg font-semibold text-stone-950"
                            >
                                {{ diagnostics.history.window_label }}
                            </h2>
                            <p class="mt-1 text-sm text-stone-600">
                                1 minute load shows the spikes. 5 and 15 minute
                                lines stay on the chart for trend context.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-6">
                    <MultiLineTrendChart
                        :labels="diagnostics.history.labels"
                        :series="diagnostics.history.load.series"
                        :height="240"
                        :tension="0"
                        :point-radius="2"
                    />
                </div>
            </div>

            <div
                class="overflow-hidden rounded-lg border border-stone-300/70 bg-white/95"
            >
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <div class="flex items-center gap-3">
                        <FontAwesomeIcon
                            :icon="faMemory"
                            class="text-stone-700"
                        />
                        <div>
                            <p
                                class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                            >
                                Memory and swap
                            </p>
                            <h2
                                class="mt-2 text-lg font-semibold text-stone-950"
                            >
                                Working set over time
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="grid gap-6 px-6 py-6">
                    <MultiLineTrendChart
                        :labels="diagnostics.history.labels"
                        :series="diagnostics.history.memory.series"
                        :height="200"
                    />
                    <MultiLineTrendChart
                        :labels="diagnostics.history.labels"
                        :series="diagnostics.history.swap.series"
                        :height="180"
                    />
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <div
                class="overflow-hidden rounded-lg border border-stone-300/70 bg-white/95"
            >
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <div class="flex items-center gap-3">
                        <FontAwesomeIcon
                            :icon="faDatabase"
                            class="text-stone-700"
                        />
                        <div>
                            <p
                                class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                            >
                                Disk + database
                            </p>
                            <h2
                                class="mt-2 text-lg font-semibold text-stone-950"
                            >
                                {{
                                    diagnostics.current.storage.database
                                        .driver_label
                                }}
                                {{
                                    diagnostics.current.storage.database
                                        .size_label
                                }}
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="grid gap-6 px-6 py-6">
                    <div
                        class="rounded-lg border border-stone-200 bg-stone-50/70 px-4 py-4 text-sm text-stone-700"
                    >
                        <p>
                            <span class="font-semibold text-stone-950"
                                >Database location:</span
                            >
                            {{
                                diagnostics.current.storage.database.path ??
                                'Unknown'
                            }}
                        </p>
                        <p class="mt-2">
                            <span class="font-semibold text-stone-950"
                                >Documents:</span
                            >
                            {{
                                exactCount(
                                    diagnostics.current.client_storage
                                        .document_count ??
                                        diagnostics.current.storage.documents.count,
                                )
                            }}
                            records ·
                            {{
                                exactCount(
                                    diagnostics.current.client_storage
                                        .file_backed_document_count,
                                )
                            }}
                            file-backed ·
                            {{
                                diagnostics.current.client_storage.total_label
                            }}
                            on disk at
                            {{ diagnostics.current.storage.documents.path }}
                        </p>
                        <p
                            v-if="
                                (diagnostics.current.client_storage
                                    .metadata_only_document_count ?? 0) > 0
                            "
                            class="mt-2 text-xs leading-5 text-amber-800"
                        >
                            {{
                                exactCount(
                                    diagnostics.current.client_storage
                                        .metadata_only_document_count,
                                )
                            }}
                            imported document records are metadata-only right
                            now. They need file upload from the companion before
                            download links and capacity math become real.
                        </p>
                        <p class="mt-2">
                            <span class="font-semibold text-stone-950"
                                >Last backup:</span
                            >
                            {{
                                diagnostics.current.storage.database
                                    .last_backup_label ?? 'No backup yet'
                            }}
                        </p>
                        <p
                            v-if="
                                diagnostics.current.machine.database_driver ===
                                'pgsql'
                            "
                            class="mt-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs leading-5 text-emerald-900"
                        >
                            This office is running on PostgreSQL, which is the
                            database mode intended for the CRM sidecar stack.
                        </p>
                    </div>
                    <MultiLineTrendChart
                        :labels="diagnostics.history.labels"
                        :series="diagnostics.history.disk.series"
                        :height="220"
                    />
                </div>
            </div>

            <div
                class="overflow-hidden rounded-lg border border-stone-300/70 bg-white/95"
            >
                <div class="border-b border-stone-200/80 px-6 py-5">
                    <div class="flex items-center gap-3">
                        <FontAwesomeIcon
                            :icon="faNetworkWired"
                            class="text-stone-700"
                        />
                        <div>
                            <p
                                class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                            >
                                Network throughput
                            </p>
                            <h2
                                class="mt-2 text-lg font-semibold text-stone-950"
                            >
                                Per-snapshot traffic
                            </h2>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-6">
                    <MultiLineTrendChart
                        :labels="diagnostics.history.labels"
                        :series="diagnostics.history.network.series"
                        :height="240"
                        :tension="0"
                        :point-radius="2"
                    />
                </div>
            </div>
        </section>

        <section class="space-y-5 border-t border-stone-300/70 pt-6">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-amber-200 bg-amber-50 text-amber-700"
                    >
                        <FontAwesomeIcon :icon="faCircleNodes" />
                    </div>
                    <div>
                        <p
                            class="text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                        >
                            Cluster view
                        </p>
                        <h2 class="mt-2 text-lg font-semibold text-stone-950">
                            Server node mesh
                        </h2>
                        <p class="mt-1 max-w-2xl text-sm text-stone-600">
                            Local and approved office nodes with tailnet reach,
                            capacity, and router preference signals.
                        </p>
                    </div>
                </div>
                <div
                    class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-stone-600"
                >
                    <span class="inline-flex items-center gap-2">
                        <FontAwesomeIcon
                            :icon="
                                diagnostics.cluster.enabled
                                    ? faCircleCheck
                                    : faTriangleExclamation
                            "
                            :class="
                                diagnostics.cluster.enabled
                                    ? 'text-emerald-500'
                                    : 'text-amber-500'
                            "
                        />
                        {{
                            diagnostics.cluster.enabled
                                ? 'Cluster enabled'
                                : 'Local only'
                        }}
                    </span>
                    <span>{{ diagnostics.cluster.online_count }} online</span>
                    <span
                        >{{ diagnostics.cluster.peer_count }} remote peers</span
                    >
                    <span>{{
                        diagnostics.cluster.connection.summary_label
                    }}</span>
                </div>
            </div>

            <div
                class="grid gap-y-4 border-y border-stone-200 py-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
            >
                <div class="flex gap-3 border-stone-200 pr-4 sm:border-r">
                    <FontAwesomeIcon
                        :icon="faPlugCircleBolt"
                        class="mt-1 text-amber-600"
                    />
                    <div>
                        <p
                            class="text-[10px] tracking-[0.18em] text-stone-500 uppercase"
                        >
                            Local office
                        </p>
                        <p class="mt-1 font-semibold text-stone-950">
                            {{ diagnostics.cluster.office_label }}
                        </p>
                    </div>
                </div>
                <div
                    class="flex gap-3 border-stone-200 pr-4 sm:border-r lg:pl-4"
                >
                    <FontAwesomeIcon
                        :icon="faServer"
                        class="mt-1 text-stone-700"
                    />
                    <div>
                        <p
                            class="text-[10px] tracking-[0.18em] text-stone-500 uppercase"
                        >
                            Offices online
                        </p>
                        <p class="mt-1 font-semibold text-stone-950">
                            {{ diagnostics.cluster.online_count }}
                        </p>
                    </div>
                </div>
                <div
                    class="flex gap-3 border-stone-200 pr-4 lg:border-r lg:pl-4"
                >
                    <FontAwesomeIcon
                        :icon="faTowerBroadcast"
                        class="mt-1 text-stone-700"
                    />
                    <div>
                        <p
                            class="text-[10px] tracking-[0.18em] text-stone-500 uppercase"
                        >
                            Remote peers
                        </p>
                        <p class="mt-1 font-semibold text-stone-950">
                            {{ diagnostics.cluster.peer_count }}
                        </p>
                    </div>
                </div>
                <div
                    class="flex gap-3 border-stone-200 pr-4 sm:border-r xl:pl-4"
                >
                    <FontAwesomeIcon
                        :icon="faWifi"
                        class="mt-1 text-stone-700"
                    />
                    <div>
                        <p
                            class="text-[10px] tracking-[0.18em] text-stone-500 uppercase"
                        >
                            Fastest peer
                        </p>
                        <p class="mt-1 font-semibold text-stone-950">
                            {{
                                diagnostics.cluster.connection
                                    .fastest_latency_label
                            }}
                        </p>
                    </div>
                </div>
                <div
                    class="flex gap-3 border-stone-200 pr-4 lg:border-r lg:pl-4"
                >
                    <FontAwesomeIcon
                        :icon="faSignal"
                        class="mt-1 text-stone-700"
                    />
                    <div>
                        <p
                            class="text-[10px] tracking-[0.18em] text-stone-500 uppercase"
                        >
                            Best link
                        </p>
                        <p class="mt-1 font-semibold text-stone-950">
                            {{
                                diagnostics.cluster.connection
                                    .best_throughput_label
                            }}
                        </p>
                    </div>
                </div>
                <div class="flex gap-3 xl:pl-4">
                    <FontAwesomeIcon
                        :icon="faGlobe"
                        class="mt-1 text-stone-700"
                    />
                    <div>
                        <p
                            class="text-[10px] tracking-[0.18em] text-stone-500 uppercase"
                        >
                            Public avg
                        </p>
                        <p class="mt-1 font-semibold text-stone-950">
                            {{ public_speed.average.download_label }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid gap-8 xl:grid-cols-[0.9fr_1.6fr]">
                <div class="space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <FontAwesomeIcon
                                :icon="faGaugeHigh"
                                class="mt-1 text-stone-700"
                            />
                            <div>
                                <p
                                    class="text-[11px] tracking-[0.2em] text-stone-500 uppercase"
                                >
                                    Public speed checks
                                </p>
                                <p class="mt-1 text-sm text-stone-600">
                                    Open a test, paste the measured values, and
                                    save a reference for this office.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="grid gap-3 border-y border-stone-200 py-4 sm:grid-cols-3"
                    >
                        <div>
                            <p
                                class="text-[10px] tracking-[0.18em] text-stone-500 uppercase"
                            >
                                Avg down
                            </p>
                            <p class="mt-1 font-semibold text-stone-950">
                                {{ public_speed.average.download_label }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-[10px] tracking-[0.18em] text-stone-500 uppercase"
                            >
                                Avg up
                            </p>
                            <p class="mt-1 font-semibold text-stone-950">
                                {{ public_speed.average.upload_label }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-[10px] tracking-[0.18em] text-stone-500 uppercase"
                            >
                                Avg ping
                            </p>
                            <p class="mt-1 font-semibold text-stone-950">
                                {{ public_speed.average.latency_label }}
                            </p>
                        </div>
                    </div>

                    <form
                        class="space-y-3 border-b border-stone-200 pb-4"
                        @submit.prevent="submitPublicSpeed('fast')"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <a
                                :href="public_speed.providers.fast.url"
                                target="_blank"
                                rel="noreferrer"
                                class="inline-flex items-center gap-2 font-semibold text-stone-950 underline-offset-4 hover:underline"
                            >
                                Fast.com
                                <FontAwesomeIcon
                                    :icon="faArrowUpRightFromSquare"
                                    class="text-[10px] text-stone-500"
                                />
                            </a>
                            <span class="text-xs text-stone-500">
                                {{
                                    public_speed.providers.fast
                                        .measured_at_label
                                }}
                            </span>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-4">
                            <label class="space-y-1">
                                <span
                                    class="text-[10px] tracking-[0.16em] text-stone-500 uppercase"
                                    >Down Mbps</span
                                >
                                <input
                                    v-model="fastSpeedForm.download_mbps"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    class="h-10 w-full rounded-lg border border-stone-300 bg-white px-3 text-sm text-stone-950 transition outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-200"
                                />
                            </label>
                            <label class="space-y-1">
                                <span
                                    class="text-[10px] tracking-[0.16em] text-stone-500 uppercase"
                                    >Up Mbps</span
                                >
                                <input
                                    v-model="fastSpeedForm.upload_mbps"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    class="h-10 w-full rounded-lg border border-stone-300 bg-white px-3 text-sm text-stone-950 transition outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-200"
                                />
                            </label>
                            <label class="space-y-1">
                                <span
                                    class="text-[10px] tracking-[0.16em] text-stone-500 uppercase"
                                    >Ping ms</span
                                >
                                <input
                                    v-model="fastSpeedForm.latency_ms"
                                    type="number"
                                    step="1"
                                    min="0"
                                    class="h-10 w-full rounded-lg border border-stone-300 bg-white px-3 text-sm text-stone-950 transition outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-200"
                                />
                            </label>
                            <button
                                type="submit"
                                :disabled="fastSpeedForm.processing"
                                class="mt-auto inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-stone-950 px-3 text-sm font-semibold text-white transition hover:bg-stone-800 disabled:opacity-60"
                            >
                                <FontAwesomeIcon
                                    :icon="faFloppyDisk"
                                    class="text-xs"
                                />
                                Save
                            </button>
                        </div>
                    </form>

                    <form
                        class="space-y-3"
                        @submit.prevent="submitPublicSpeed('speedtest')"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <a
                                :href="public_speed.providers.speedtest.url"
                                target="_blank"
                                rel="noreferrer"
                                class="inline-flex items-center gap-2 font-semibold text-stone-950 underline-offset-4 hover:underline"
                            >
                                Speedtest.net
                                <FontAwesomeIcon
                                    :icon="faArrowUpRightFromSquare"
                                    class="text-[10px] text-stone-500"
                                />
                            </a>
                            <span class="text-xs text-stone-500">
                                {{
                                    public_speed.providers.speedtest
                                        .measured_at_label
                                }}
                            </span>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-4">
                            <label class="space-y-1">
                                <span
                                    class="text-[10px] tracking-[0.16em] text-stone-500 uppercase"
                                    >Down Mbps</span
                                >
                                <input
                                    v-model="speedtestSpeedForm.download_mbps"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    class="h-10 w-full rounded-lg border border-stone-300 bg-white px-3 text-sm text-stone-950 transition outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-200"
                                />
                            </label>
                            <label class="space-y-1">
                                <span
                                    class="text-[10px] tracking-[0.16em] text-stone-500 uppercase"
                                    >Up Mbps</span
                                >
                                <input
                                    v-model="speedtestSpeedForm.upload_mbps"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    class="h-10 w-full rounded-lg border border-stone-300 bg-white px-3 text-sm text-stone-950 transition outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-200"
                                />
                            </label>
                            <label class="space-y-1">
                                <span
                                    class="text-[10px] tracking-[0.16em] text-stone-500 uppercase"
                                    >Ping ms</span
                                >
                                <input
                                    v-model="speedtestSpeedForm.latency_ms"
                                    type="number"
                                    step="1"
                                    min="0"
                                    class="h-10 w-full rounded-lg border border-stone-300 bg-white px-3 text-sm text-stone-950 transition outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-200"
                                />
                            </label>
                            <button
                                type="submit"
                                :disabled="speedtestSpeedForm.processing"
                                class="mt-auto inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-stone-950 px-3 text-sm font-semibold text-white transition hover:bg-stone-800 disabled:opacity-60"
                            >
                                <FontAwesomeIcon
                                    :icon="faFloppyDisk"
                                    class="text-xs"
                                />
                                Save
                            </button>
                        </div>
                    </form>
                </div>

                <div>
                    <div
                        class="flex items-center justify-between gap-3 border-b border-stone-200 pb-3"
                    >
                        <div class="flex items-center gap-2">
                            <FontAwesomeIcon
                                :icon="faLink"
                                class="text-stone-700"
                            />
                            <p
                                class="text-[11px] tracking-[0.2em] text-stone-500 uppercase"
                            >
                                Server nodes
                            </p>
                        </div>
                        <p class="text-sm text-stone-600">
                            {{ diagnostics.cluster.connection.summary_label }}
                        </p>
                    </div>

                    <div class="divide-y divide-stone-200">
                        <div
                            v-for="node in diagnostics.cluster.nodes"
                            :key="`${node.source}-${node.label}-${node.base_url ?? 'local'}`"
                            class="py-4"
                        >
                            <div
                                class="grid gap-4 xl:grid-cols-[minmax(220px,1.25fr)_repeat(5,minmax(96px,0.7fr))]"
                            >
                                <div>
                                    <div class="flex items-center gap-2">
                                        <FontAwesomeIcon
                                            :icon="faCircleDot"
                                            :class="
                                                node.online
                                                    ? 'text-emerald-500'
                                                    : 'text-rose-500'
                                            "
                                        />
                                        <p class="font-semibold text-stone-950">
                                            {{ node.label }}
                                        </p>
                                        <span
                                            class="text-[10px] tracking-[0.16em] text-stone-400 uppercase"
                                            >{{ node.source }}</span
                                        >
                                    </div>
                                    <p
                                        class="mt-1 text-sm break-all text-stone-600"
                                    >
                                        {{
                                            nodeDetailLabel(node)
                                        }}
                                    </p>
                                </div>

                                <div class="flex gap-2">
                                    <FontAwesomeIcon
                                        :icon="faBolt"
                                        class="mt-1 text-stone-400"
                                    />
                                    <div>
                                        <p
                                            class="text-[10px] tracking-[0.16em] text-stone-500 uppercase"
                                        >
                                            Ping
                                        </p>
                                        <p
                                            class="mt-1 font-semibold text-stone-950"
                                        >
                                            {{ node.connection.latency_label }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex gap-2">
                                    <FontAwesomeIcon
                                        :icon="faSignal"
                                        class="mt-1 text-stone-400"
                                    />
                                    <div>
                                        <p
                                            class="text-[10px] tracking-[0.16em] text-stone-500 uppercase"
                                        >
                                            Link
                                        </p>
                                        <p
                                            class="mt-1 font-semibold text-stone-950"
                                        >
                                            {{
                                                node.connection.throughput_label
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex gap-2">
                                    <FontAwesomeIcon
                                        :icon="
                                            node.online
                                                ? faCircleCheck
                                                : faTriangleExclamation
                                        "
                                        :class="
                                            node.online
                                                ? 'mt-1 text-emerald-500'
                                                : 'mt-1 text-rose-500'
                                        "
                                    />
                                    <div>
                                        <p
                                            class="text-[10px] tracking-[0.16em] text-stone-500 uppercase"
                                        >
                                            State
                                        </p>
                                        <p
                                            class="mt-1 font-semibold text-stone-950"
                                        >
                                            {{ node.connection.status_label }}
                                        </p>
                                    </div>
                                </div>

                                <div v-if="node.summary" class="flex gap-2">
                                    <FontAwesomeIcon
                                        :icon="faHardDrive"
                                        class="mt-1 text-stone-400"
                                    />
                                    <div>
                                        <p
                                            class="text-[10px] tracking-[0.16em] text-stone-500 uppercase"
                                        >
                                            Disk
                                        </p>
                                        <p
                                            class="mt-1 font-semibold text-stone-950"
                                        >
                                            {{ node.summary.disk.used_label }}
                                        </p>
                                        <p class="text-xs text-stone-500">
                                            of
                                            {{ node.summary.disk.total_label }}
                                        </p>
                                    </div>
                                </div>
                                <div v-else class="flex gap-2 text-rose-700">
                                    <FontAwesomeIcon
                                        :icon="faTriangleExclamation"
                                        class="mt-1"
                                    />
                                    <p class="text-sm">
                                        {{
                                            node.error ??
                                            'This node is offline right now.'
                                        }}
                                    </p>
                                </div>

                                <div v-if="node.summary" class="flex gap-2">
                                    <FontAwesomeIcon
                                        :icon="faClock"
                                        class="mt-1 text-stone-400"
                                    />
                                    <div>
                                        <p
                                            class="text-[10px] tracking-[0.16em] text-stone-500 uppercase"
                                        >
                                            Probe
                                        </p>
                                        <p
                                            class="mt-1 font-semibold text-stone-950"
                                        >
                                            {{
                                                nodeProbeLabel(node)
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="node.summary"
                                class="mt-3 grid gap-3 border-l border-amber-300 pl-4 text-sm text-stone-600 md:grid-cols-3"
                            >
                                <div class="flex items-center gap-2">
                                    <FontAwesomeIcon
                                        :icon="faMemory"
                                        class="text-stone-400"
                                    />
                                    <span>
                                        RAM
                                        {{ node.summary.memory.used_label }} /
                                        {{
                                            node.summary.memory.total_label
                                        }}
                                        <template
                                            v-if="
                                                node.summary.memory
                                                    .pressure_label
                                            "
                                        >
                                            ·
                                            {{
                                                node.summary.memory
                                                    .pressure_label
                                            }}
                                        </template>
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <FontAwesomeIcon
                                        :icon="faCloudArrowDown"
                                        class="text-stone-400"
                                    />
                                    <span>
                                        RX
                                        {{ node.summary.network.rx_label }}
                                        · TX
                                        {{
                                            node.summary.network.tx_label
                                        }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <FontAwesomeIcon
                                        :icon="faCloudArrowUp"
                                        class="text-stone-400"
                                    />
                                    <span>
                                        Load {{ node.summary.load.one }} /
                                        {{ node.summary.load.five }} /
                                        {{ node.summary.load.fifteen }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
