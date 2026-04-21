<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DashboardWorkspaceNav from '@/components/creditsoft/DashboardWorkspaceNav.vue';
import LineTrendChart from '@/components/creditsoft/LineTrendChart.vue';
import MetricTile from '@/components/creditsoft/MetricTile.vue';
import { formatCurrency, formatNumber } from '@/lib/creditsoft';

defineProps<{
    headline: {
        mrr: number;
        active_clients: number;
        avg_lifespan_months: number;
        new_client_velocity: number;
        case_throughput: number;
        staff_throughput: number;
        churn_signals: number;
        open_violations: number;
    };
    mrrSeries: Array<{
        bucket_date: string;
        value: number;
    }>;
    statusBreakdown: Array<{
        status: string;
        aggregate: number;
    }>;
}>();

const mrrCardTooltip = 'Monthly recurring revenue. This is the expected subscription revenue the office brings in each month from active paying clients.';
</script>

<template>
    <Head title="CFO" />

    <h1 class="sr-only">CFO view</h1>

    <div class="space-y-8">
        <section class="flex items-start justify-between gap-4 border-b border-stone-300/70 pb-4">
            <div>
                <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Office finance</p>
                <h2 class="text-2xl font-semibold tracking-tight text-stone-950">CFO view</h2>
                <p class="text-sm text-stone-600">Revenue, churn risk, throughput, and violation exposure without leaving the office workspace.</p>
            </div>
            <DashboardWorkspaceNav />
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <MetricTile label="MRR" :value="formatCurrency(headline.mrr)" :tooltip="mrrCardTooltip" />
            <MetricTile label="Active clients" :value="formatNumber(headline.active_clients)" />
            <MetricTile label="Customer lifespan" :value="`${headline.avg_lifespan_months} mo`" />
            <MetricTile label="New client velocity" :value="formatNumber(headline.new_client_velocity)" />
            <MetricTile label="Case throughput" :value="formatNumber(headline.case_throughput)" />
            <MetricTile label="Staff throughput" :value="formatNumber(headline.staff_throughput)" />
            <MetricTile label="Churn signals" :value="formatNumber(headline.churn_signals)" />
            <MetricTile label="Open violations" :value="formatNumber(headline.open_violations)" hint="Open the violation review queue" href="/violations" />
        </section>

        <section class="grid gap-8 xl:grid-cols-[1.25fr_0.75fr]">
            <div class="space-y-4">
                <div class="border-b border-stone-300/70 pb-3">
                    <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Revenue line</p>
                    <p class="text-sm text-stone-600">Manual or imported recurring revenue snapshots over time.</p>
                </div>
                <LineTrendChart
                    :labels="mrrSeries.map((point) => point.bucket_date)"
                    :values="mrrSeries.map((point) => Number(point.value))"
                    value-format="currency"
                />
            </div>

            <div class="space-y-3">
                <div class="border-b border-stone-300/70 pb-3">
                    <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Client status mix</p>
                    <p class="text-sm text-stone-600">Where the book of business is sitting today.</p>
                </div>
                <div class="space-y-3">
                    <div v-for="bucket in statusBreakdown" :key="bucket.status" class="flex items-center justify-between rounded-2xl border border-stone-300/70 bg-stone-50/70 px-4 py-3">
                        <p class="font-medium text-stone-900">{{ bucket.status.replaceAll('_', ' ') }}</p>
                        <p class="text-sm text-stone-500">{{ formatNumber(bucket.aggregate) }}</p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
