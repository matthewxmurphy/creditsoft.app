<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import DashboardWorkspaceNav from '@/components/creditsoft/DashboardWorkspaceNav.vue';
import LineTrendChart from '@/components/creditsoft/LineTrendChart.vue';
import MetricTile from '@/components/creditsoft/MetricTile.vue';
import { formatCurrency, formatDate, formatNumber } from '@/lib/creditsoft';

const props = defineProps<{
    kpis: {
        clients: number;
        paying_members: number;
        open_tasks: number;
        open_violations: number;
        mrr: number;
    };
    latestCycleSummary: {
        total_accounts: number;
        open_accounts: number;
        closed_accounts: number;
        negative_accounts: number;
        positive_accounts: number;
        revolving_accounts: number;
        over_thirty_percent: number;
        priority_disputes: number;
        changed_since_last_cycle: number;
    } | null;
    hotQueue: Array<{
        id: number | string;
        title: string;
        details?: string | null;
        status: string;
        priority: string;
        due_at?: string | null;
        client?: {
            id: number;
            display_name?: string;
            first_name?: string;
            last_name?: string;
        } | null;
        system_item?: boolean;
        action_href?: string | null;
        action_label?: string | null;
    }>;
    recentAudit: Array<{
        id: number;
        summary: string;
        created_at: string;
        user?: {
            name: string;
        };
    }>;
    trendLine: Array<{
        bucket_date: string;
        value: number;
    }>;
    impact: {
        clients_served: number;
        active_clients: number;
        debt_removed: number;
        negative_items_removed: number;
        average_score_lift: number;
        minimum_score_lift: number;
        maximum_score_lift: number;
        clients_with_score_gain: number;
        average_client_lifespan_months: number;
        longest_client_lifespan_months: number;
        graduated_clients: number;
        ended_for_nonpayment: number;
        ended_other: number;
        unknown_outcome_clients: number;
    };
    billing: {
        settings: {
            gateway_provider?: string | null;
            gateway_status: string;
            gateway_account_label?: string | null;
            gateway_environment?: string | null;
            webhook_status?: string | null;
            gateway_connected_at?: string | null;
            payment_portal_url?: string | null;
            notes?: string | null;
        };
        stats: {
            recorded_revenue: number;
            last_paid_at?: string | null;
        };
        clients: Array<{
            id: number;
            display_name: string;
            email?: string | null;
        }>;
        profiles: Array<{
            id: number;
            client_id: number;
            client_name?: string | null;
            status: string;
            amount: number;
            currency: string;
            billing_interval: string;
            monthly_amount: number;
            started_at?: string | null;
            last_paid_at?: string | null;
            next_due_at?: string | null;
            gateway_name?: string | null;
            gateway_customer_id?: string | null;
            gateway_subscription_id?: string | null;
            notes?: string | null;
        }>;
        recent_payments: Array<{
            id: number;
            client_id: number;
            client_name?: string | null;
            amount: number;
            currency: string;
            status: string;
            paid_at?: string | null;
            gateway_name?: string | null;
            gateway_transaction_id?: string | null;
            reference?: string | null;
            notes?: string | null;
        }>;
        can_manage_billing: boolean;
    };
    updates: {
        current_version?: string | null;
        latest_version?: string | null;
        latest_build?: string | null;
        headline?: string | null;
        summary?: string | null;
        notes?: string[];
        published_at?: string | null;
        update_available: boolean;
        update_required?: boolean;
        download_url?: string | null;
        renewal_url?: string | null;
        support_url?: string | null;
    };
}>();

const page = usePage();
const billingPanelRequested = computed(() => page.url.includes('panel=billing'));

const gatewayForm = useForm({
    gateway_provider: props.billing.settings.gateway_provider ?? '',
    gateway_status: props.billing.settings.gateway_status ?? 'manual',
    gateway_account_label: props.billing.settings.gateway_account_label ?? '',
    gateway_environment: props.billing.settings.gateway_environment ?? '',
    webhook_status: props.billing.settings.webhook_status ?? '',
    gateway_connected_at: props.billing.settings.gateway_connected_at?.slice(0, 16) ?? '',
    payment_portal_url: props.billing.settings.payment_portal_url ?? '',
    notes: props.billing.settings.notes ?? '',
});

const profileForm = useForm({
    client_id: props.billing.clients[0]?.id?.toString() ?? '',
    status: 'active',
    amount: '',
    currency: 'USD',
    billing_interval: 'monthly',
    started_at: '',
    last_paid_at: '',
    next_due_at: '',
    gateway_name: '',
    gateway_customer_id: '',
    gateway_subscription_id: '',
    notes: '',
});

const paymentForm = useForm({
    client_id: props.billing.clients[0]?.id?.toString() ?? '',
    amount: '',
    currency: 'USD',
    status: 'paid',
    paid_at: '',
    gateway_name: '',
    gateway_transaction_id: '',
    reference: '',
    notes: '',
});

const gatewayProviderSuggestions = [
    'Authorize.net',
    'PaymentCloud + Authorize.net',
    'PaymentCloud + NMI',
    'PaymentCloud + ACH',
    'GOAT Payments',
    'GOAT Payments + Authorize.net',
    'GOAT Payments + NMI',
    'NMI',
    'USAePay',
    'Valor PayTech',
    'Biller Genie',
    'eCheck / ACH',
    'Zelle',
    'Cash App',
    'Apple Pay',
    'Google Pay',
    'Manual / offline',
    'Other merchant account',
];

const submitGatewaySettings = () => gatewayForm.transform((data) => ({
    ...data,
    gateway_connected_at: data.gateway_connected_at || null,
    payment_portal_url: data.payment_portal_url || null,
})).put('/dashboard/billing-settings');

const submitBillingProfile = () => profileForm.post('/dashboard/billing-profiles', {
    preserveScroll: true,
    onSuccess: () => profileForm.reset('amount', 'started_at', 'last_paid_at', 'next_due_at', 'gateway_customer_id', 'gateway_subscription_id', 'notes'),
});

const submitPayment = () => paymentForm.post('/dashboard/payments', {
    preserveScroll: true,
    onSuccess: () => paymentForm.reset('amount', 'paid_at', 'gateway_transaction_id', 'reference', 'notes'),
});

const clientLabel = (client?: {
    display_name?: string;
    first_name?: string;
    last_name?: string;
} | null) => {
    if (!client) {
        return 'Operations reminder';
    }

    const fallbackName = `${client.first_name ?? ''} ${client.last_name ?? ''}`.trim();

    if (client.display_name) {
        return client.display_name;
    }

    return fallbackName || 'Global queue item';
};

const formatDateTime = (value?: string | null) => value ? new Date(value).toLocaleString() : 'Not recorded';

const trendLabels = computed(() => props.trendLine.map((point) => formatDate(point.bucket_date)));
const trendValues = computed(() => props.trendLine.map((point) => Number(point.value)));
const trendStartValue = computed(() => trendValues.value[0] ?? 0);
const trendEndValue = computed(() => trendValues.value[trendValues.value.length - 1] ?? 0);
const trendDirectionLabel = computed(() => {
    if (trendEndValue.value > trendStartValue.value) {
        return 'Up from the first checkpoint';
    }

    if (trendEndValue.value < trendStartValue.value) {
        return 'Down from the first checkpoint';
    }

    return 'Flat against the first checkpoint';
});

const mrrCardTooltip = 'Monthly recurring revenue. This is the expected subscription revenue the office brings in each month from active paying clients.';
const mrrTrendTooltip = 'Monthly recurring revenue. This trend compares the first visible checkpoint to the latest one so you can quickly see whether recurring revenue is moving up, down, or flat.';
const debtRemovedTooltip = 'Estimated balance tied to negative tradelines that showed in the first imported file and no longer appear as negative in the latest imported file.';
const negativeItemsTooltip = 'Negative tradelines that appeared in the first imported file and no longer appear as negative in the latest imported file.';
const avgScoreLiftTooltip = 'Average credit score increase across clients with imported score history that shows a positive lift.';
const minScoreLiftTooltip = 'Smallest positive score gain among clients who improved.';
const maxScoreLiftTooltip = 'Largest score gain among clients with imported score history.';
const lifespanTooltip = 'Average time clients stay in CreditSoft, using their start date and either now or the best known ending date.';
</script>

<template>
    <Head title="Control Panel" />

    <h1 class="sr-only">Creditsoft control panel</h1>

    <div class="space-y-8">
        <section class="flex items-start justify-between gap-4 border-b border-stone-300/70 pb-4">
            <div>
                <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Office overview</p>
                <h2 class="text-2xl font-semibold tracking-tight text-stone-950">Control panel</h2>
                <p class="text-sm text-stone-600">Revenue, queue health, and office operations in one place.</p>
            </div>
            <DashboardWorkspaceNav />
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <MetricTile label="Monthly recurring revenue" :value="formatCurrency(kpis.mrr)" hint="Current headline metric" :tooltip="mrrCardTooltip" />
            <MetricTile label="Paying members" :value="formatNumber(kpis.paying_members)" hint="Clients with active or trial billing" />
            <MetricTile label="Active clients" :value="formatNumber(kpis.clients)" hint="Local dossiers on this installation" />
            <MetricTile label="Open tasks" :value="formatNumber(kpis.open_tasks)" hint="Inbox and SOP work still in motion" />
            <MetricTile label="Open violations" :value="formatNumber(kpis.open_violations)" hint="Candidates waiting on review or action" />
        </section>

        <section class="space-y-4">
            <div class="border-b border-stone-300/70 pb-3">
                <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Brag rights</p>
                <p class="text-sm text-stone-600">Source-backed impact numbers the office can reuse on the website and in sales conversations.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <MetricTile label="Debt removed" :value="formatCurrency(impact.debt_removed)" hint="From first-file negatives no longer present as negative" :tooltip="debtRemovedTooltip" />
                <MetricTile label="Negative items removed" :value="formatNumber(impact.negative_items_removed)" hint="Negative tradelines no longer showing in the latest file" :tooltip="negativeItemsTooltip" />
                <MetricTile label="Average score raise" :value="formatNumber(impact.average_score_lift)" :hint="`${formatNumber(impact.clients_with_score_gain)} clients with positive score movement`" :tooltip="avgScoreLiftTooltip" />
                <MetricTile label="Best score raise" :value="formatNumber(impact.maximum_score_lift)" :hint="impact.minimum_score_lift > 0 ? `Smallest recorded raise ${formatNumber(impact.minimum_score_lift)}` : 'Waiting on more score history'" :tooltip="maxScoreLiftTooltip" />
                <MetricTile label="Average client lifespan" :value="`${impact.average_client_lifespan_months.toFixed(1)} mo`" :hint="`Longest tracked relationship ${impact.longest_client_lifespan_months.toFixed(1)} mo`" :tooltip="lifespanTooltip" />
                <MetricTile label="Graduated clients" :value="formatNumber(impact.graduated_clients)" hint="Marked done or no longer needing help" />
                <MetricTile label="Ended for nonpayment" :value="formatNumber(impact.ended_for_nonpayment)" hint="Only counted when the outcome reason says nonpayment" />
                <MetricTile label="Other ended / unknown" :value="formatNumber(impact.ended_other + impact.unknown_outcome_clients)" hint="Ended relationships still missing a cleaner outcome reason" />
            </div>
        </section>

        <section
            class="rounded-[28px] border px-5 py-5 shadow-sm"
            :class="updates.update_available ? 'border-emerald-300 bg-emerald-50/70' : 'border-stone-300/70 bg-stone-50/60'"
        >
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div class="space-y-2">
                    <p class="text-[11px] font-medium uppercase tracking-[0.32em]" :class="updates.update_available ? 'text-emerald-700' : 'text-stone-500'">
                        Office update lane
                    </p>
                    <h3 class="text-xl font-semibold tracking-tight text-stone-950">
                        {{ updates.headline ?? 'CreditSoft update status' }}
                    </h3>
                    <p class="max-w-3xl text-sm leading-7 text-stone-700">
                        {{ updates.summary ?? 'The office can check the remote update lane to see whether a newer build is ready.' }}
                    </p>
                    <div class="flex flex-wrap gap-3 text-xs uppercase tracking-[0.2em] text-stone-500">
                        <span>Current {{ updates.current_version ?? 'unknown' }}</span>
                        <span v-if="updates.latest_version">Latest {{ updates.latest_version }}</span>
                        <span v-if="updates.latest_build">Build {{ updates.latest_build }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a
                        v-if="updates.download_url"
                        :href="updates.download_url"
                        class="inline-flex items-center rounded-full bg-stone-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-stone-800"
                    >
                        Open update lane
                    </a>
                    <a
                        v-if="updates.renewal_url"
                        :href="updates.renewal_url"
                        class="inline-flex items-center rounded-full border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-900 transition hover:border-stone-500"
                    >
                        Renewal lane
                    </a>
                </div>
            </div>
        </section>

        <section class="grid gap-8 xl:grid-cols-[1.45fr_0.85fr]">
            <div class="space-y-4">
                <div class="flex items-end justify-between border-b border-stone-300/70 pb-3">
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">
                            Selected KPIs
                        </p>
                        <p class="text-sm text-stone-600">
                            Revenue trend and operational changes from the latest reporting cycle.
                        </p>
                    </div>
                    <Link href="/cfo" class="text-xs font-medium uppercase tracking-[0.22em] text-stone-500 transition hover:text-stone-950">
                        Open CFO
                    </Link>
                </div>

                <div class="overflow-hidden rounded-[28px] border border-stone-300/70 bg-white/80">
                    <div class="border-b border-stone-200/80 px-5 py-4">
                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <p
                                    class="text-[11px] font-medium uppercase tracking-[0.22em] text-stone-500"
                                    :title="mrrTrendTooltip"
                                    :aria-label="mrrTrendTooltip"
                                >
                                    MRR trend
                                </p>
                                <p class="mt-1 text-sm text-stone-700">{{ trendDirectionLabel }}</p>
                            </div>
                            <p class="text-xs uppercase tracking-[0.2em] text-stone-500">
                                {{ formatCurrency(trendStartValue) }} to {{ formatCurrency(trendEndValue) }}
                            </p>
                        </div>
                    </div>
                    <div class="px-4 py-4">
                        <LineTrendChart :labels="trendLabels" :values="trendValues" color="#d97706" :height="220" value-format="currency" />
                        <div class="mt-3 grid gap-2 text-xs text-stone-500 md:grid-cols-4">
                            <div v-for="(label, index) in trendLabels" :key="`${label}-${index}`" class="rounded-2xl bg-stone-50 px-3 py-2">
                                <p class="uppercase tracking-[0.18em] text-stone-400">{{ label }}</p>
                                <p class="mt-1 font-medium text-stone-700">{{ formatCurrency(trendValues[index] ?? 0) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="latestCycleSummary" class="grid gap-4 md:grid-cols-3">
                    <MetricTile label="Accounts reviewed" :value="formatNumber(latestCycleSummary.total_accounts)" />
                    <MetricTile label="Priority disputes" :value="formatNumber(latestCycleSummary.priority_disputes)" />
                    <MetricTile label="Utilization targets" :value="formatNumber(latestCycleSummary.over_thirty_percent)" />
                </div>
            </div>

            <div class="space-y-6">
                <section class="space-y-3 border-b border-stone-300/70 pb-5">
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">
                            Hot queue
                        </p>
                        <p class="text-sm text-stone-600">
                            Work due soon across intake, comparison, and follow-up.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="task in hotQueue"
                            :key="task.id"
                            class="flex items-center justify-between rounded-2xl border border-stone-300/70 bg-stone-50/70 px-4 py-3"
                        >
                            <div class="space-y-1">
                                <p class="font-medium text-stone-900">{{ task.title }}</p>
                                <p class="text-sm text-stone-500">
                                    {{ clientLabel(task.client) }}
                                </p>
                                <p v-if="task.details" class="mt-1 text-xs leading-5 text-stone-500">{{ task.details }}</p>
                            </div>
                            <div class="text-right text-xs uppercase tracking-[0.22em] text-stone-500">
                                <p>{{ task.priority }}</p>
                                <p>{{ task.due_at ? formatDate(task.due_at) : 'No due date' }}</p>
                                <Link
                                    v-if="task.system_item && task.action_href"
                                    :href="task.action_href"
                                    class="mt-2 inline-flex text-[10px] font-medium uppercase tracking-[0.18em] text-stone-700 underline underline-offset-4"
                                >
                                    {{ task.action_label ?? 'Open' }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="space-y-3">
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">
                            Audit pulse
                        </p>
                        <p class="text-sm text-stone-600">
                            Most recent casework and review events.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="entry in recentAudit"
                            :key="entry.id"
                            class="rounded-2xl border border-stone-300/70 bg-stone-50/70 px-4 py-3"
                        >
                            <div class="flex items-center justify-between gap-4">
                                <p class="text-sm text-stone-800">{{ entry.summary }}</p>
                                <p class="text-[11px] uppercase tracking-[0.22em] text-stone-500">
                                    {{ entry.user?.name ?? 'System' }}
                                </p>
                            </div>
                            <p class="mt-1 text-xs text-stone-500">{{ formatDate(entry.created_at) }}</p>
                        </div>
                    </div>
                </section>
            </div>
        </section>

    </div>
</template>
