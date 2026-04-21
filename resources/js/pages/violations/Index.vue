<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import DashboardWorkspaceNav from '@/components/creditsoft/DashboardWorkspaceNav.vue';
import { compactLabel, formatDate, formatNumber } from '@/lib/creditsoft';

defineProps<{
    stats: {
        total: number;
        open: number;
        confirmed: number;
        high: number;
    };
    violations: Array<{
        id: number;
        title: string;
        severity: string;
        priority_score?: number | null;
        status: string;
        rule_key: string;
        bureau?: string | null;
        next_action?: string | null;
        created_at?: string | null;
        client?: {
            id: number;
            display_name?: string;
            first_name?: string;
            last_name?: string;
        } | null;
        reporting_cycle?: {
            id: number;
            cycle_label?: string | null;
        } | null;
    }>;
}>();

const clientLabel = (client?: {
    display_name?: string;
    first_name?: string;
    last_name?: string;
} | null) => {
    if (!client) {
        return 'No client attached';
    }

    const fullName = `${client.first_name ?? ''} ${client.last_name ?? ''}`.trim();

    return client.display_name || fullName || 'Client file';
};

const severityClass = (severity: string) => {
    if (severity === 'high') {
        return 'border-rose-300 bg-rose-50 text-rose-800';
    }

    if (severity === 'medium') {
        return 'border-amber-300 bg-amber-50 text-amber-900';
    }

    return 'border-stone-300 bg-white text-stone-700';
};

const statusClass = (status: string) => status === 'confirmed'
    ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
    : 'border-amber-300 bg-amber-50 text-amber-900';
</script>

<template>
    <Head title="Violations" />

    <h1 class="sr-only">Violations</h1>

    <div class="space-y-8">
        <section class="grid gap-6 border-b border-stone-300/70 pb-6 xl:grid-cols-[1fr_auto] xl:items-start">
            <div>
                <p class="text-[11px] font-medium uppercase tracking-[0.34em] text-stone-500">Violation reviews</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-[-0.04em] text-stone-950 md:text-5xl">
                    One queue for every open Metro 2 review.
                </h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-stone-600">
                    Review open and confirmed violations across every client without opening files one by one.
                </p>
            </div>

            <div class="space-y-4 xl:min-w-[32rem]">
                <DashboardWorkspaceNav />

                <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm md:grid-cols-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-stone-500">Total</p>
                        <p class="mt-1 text-2xl font-semibold text-stone-950">{{ formatNumber(stats.total) }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-stone-500">Open</p>
                        <p class="mt-1 text-2xl font-semibold text-stone-950">{{ formatNumber(stats.open) }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-stone-500">Confirmed</p>
                        <p class="mt-1 text-2xl font-semibold text-stone-950">{{ formatNumber(stats.confirmed) }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-stone-500">High</p>
                        <p class="mt-1 text-2xl font-semibold text-stone-950">{{ formatNumber(stats.high) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <div v-if="violations.length === 0" class="rounded-[28px] border border-stone-300/70 bg-stone-50/70 p-6">
                <p class="text-lg font-semibold text-stone-950">No open violations need review.</p>
                <p class="mt-2 text-sm leading-6 text-stone-600">Client-specific violation pages will still show resolved history inside each client file.</p>
            </div>

            <div
                v-for="violation in violations"
                :key="violation.id"
                class="rounded-[28px] border border-stone-300/70 bg-stone-50/80 p-4 md:p-5"
            >
                <div class="grid gap-4 xl:grid-cols-[1fr_auto]">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em]" :class="severityClass(violation.severity)">
                                {{ violation.severity }}
                            </span>
                            <span class="inline-flex rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em]" :class="statusClass(violation.status)">
                                {{ violation.status }}
                            </span>
                            <span v-if="violation.priority_score" class="text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500">
                                Priority {{ violation.priority_score }}
                            </span>
                        </div>

                        <h3 class="mt-3 text-lg font-semibold text-stone-950">{{ violation.title }}</h3>
                        <p class="mt-1 text-xs uppercase tracking-[0.22em] text-stone-500">
                            {{ compactLabel(violation.rule_key) }} · {{ violation.bureau ?? 'All bureaus' }}
                        </p>
                        <p class="mt-3 max-w-3xl text-sm leading-6 text-stone-600">
                            {{ violation.next_action || 'No next action has been added yet.' }}
                        </p>
                    </div>

                    <div class="grid content-start gap-3 text-sm xl:min-w-[17rem]">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-stone-500">Client</p>
                            <Link
                                v-if="violation.client"
                                :href="`/clients/${violation.client.id}`"
                                class="mt-1 inline-flex font-semibold text-stone-950 underline underline-offset-4"
                            >
                                {{ clientLabel(violation.client) }}
                            </Link>
                            <p v-else class="mt-1 font-semibold text-stone-950">{{ clientLabel(violation.client) }}</p>
                        </div>
                        <div v-if="violation.reporting_cycle">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-stone-500">Cycle</p>
                            <p class="mt-1 text-stone-700">{{ violation.reporting_cycle.cycle_label ?? 'Review cycle' }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-stone-500">Created</p>
                            <p class="mt-1 text-stone-700">{{ violation.created_at ? formatDate(violation.created_at) : 'Not dated' }}</p>
                        </div>
                        <Link
                            v-if="violation.client"
                            :href="`/clients/${violation.client.id}/violations`"
                            class="inline-flex items-center justify-center rounded-full bg-stone-950 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-white transition hover:bg-stone-800"
                        >
                            Open client review
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
