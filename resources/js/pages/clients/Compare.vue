<script setup lang="ts">
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import BureauWordmark from '@/components/creditsoft/BureauWordmark.vue';
import ClientHealthSummary from '@/components/creditsoft/ClientHealthSummary.vue';
import ClientWorkspaceNav from '@/components/creditsoft/ClientWorkspaceNav.vue';
import MetricTile from '@/components/creditsoft/MetricTile.vue';
import ReviewSignalLabel from '@/components/creditsoft/ReviewSignalLabel.vue';
import type { ClientHealthSignal } from '@/lib/client-health';
import { compactLabel, formatNumber } from '@/lib/creditsoft';

type BureauKey = 'experian' | 'transunion' | 'equifax';
type BureauEntry = {
    id: number | null;
    creditor_name: string;
    account_type?: string | null;
    is_revolving?: boolean | null;
    balance?: number | null;
    credit_limit?: number | null;
    utilization_percent?: number | null;
    account_status?: string | null;
    payment_status?: string | null;
    is_open?: boolean | null;
    date_last_payment?: string | null;
    remarks?: string | null;
    coverage_state?: string | null;
    coverage_label?: string | null;
} | null;

const props = defineProps<{
    client: {
        id: number;
        display_name: string;
        status?: string | null;
    };
    clientHealth?: ClientHealthSignal | string | null;
    cycle: {
        id: number;
        cycle_label: string;
    };
    cycles: Array<{
        id: number;
        cycle_label: string;
    }>;
    summary: {
        total_accounts: number;
        open_accounts: number;
        negative_accounts: number;
        over_thirty_percent: number;
    };
    suggestions: Array<{
        fingerprint: string;
        rule_key: string;
        title: string;
        severity: string;
        priority_score: number;
        bureau?: string | null;
        category?: string | null;
        description?: string | null;
        reference?: string | null;
        next_action?: string | null;
        account_label?: string | null;
        evidence: Array<{
            detail: string;
            bureau?: string | null;
            field?: string | null;
            tradeline_id?: number | null;
            value?: string | number | boolean | null;
            missing?: boolean | null;
        }>;
        legal_frameworks: Array<{
            code: string;
            label: string;
            kind: string;
            title: string;
        }>;
        already_logged: boolean;
    }>;
    rows: Array<{
        key: string;
        label: string;
        duplicates: string[];
        mismatches: string[];
        severity: string;
        coverage_label?: string | null;
        bureaus: Record<string, BureauEntry>;
    }>;
}>();

const page = usePage<{
    creditsoft?: {
        ui?: {
            review_label_style?: string | null;
        } | null;
    } | null;
}>();
const bureauOrder: BureauKey[] = ['experian', 'transunion', 'equifax'];
const reviewLabelStyle = computed(() => String(page.props.creditsoft?.ui?.review_label_style ?? '10'));

const changeCycle = (event: Event) => {
    const target = event.target as HTMLSelectElement;

    router.get(`/clients/${props.client.id}/compare`, { cycle: target.value }, { preserveState: true });
};

const queueSuggestions = () => {
    router.post(`/clients/${props.client.id}/violations/scan`, {
        reporting_cycle_id: props.cycle.id,
    }, {
        preserveScroll: true,
    });
};

const needsReviewRows = computed(() => props.rows.filter((row) => row.mismatches.length > 0));
const alignedRows = computed(() => props.rows.filter((row) => row.mismatches.length === 0));
const importedRowCount = computed(() => props.rows.length);

const bureauSummaries = computed(() =>
    bureauOrder.map((bureau) => ({
        key: bureau,
        presentCount: props.rows.filter((row) => row.bureaus[bureau] !== null).length,
        missingCount: props.rows.filter((row) => row.bureaus[bureau] === null).length,
        flaggedCount: needsReviewRows.value.filter((row) => row.bureaus[bureau] !== null || row.mismatches.includes('missing_bureau_entry')).length,
        alignedCount: alignedRows.value.filter((row) => row.bureaus[bureau] !== null).length,
    })),
);

const severityTone = (severity: string) => {
    if (severity === 'high') return 'border-rose-300 bg-rose-100 text-rose-900';
    if (severity === 'medium') return 'border-amber-300 bg-amber-100 text-amber-900';

    return 'border-sky-300 bg-sky-100 text-sky-900';
};

const severityLabelKind = (severity: string) => {
    if (severity === 'high') return 'severity-high' as const;
    if (severity === 'medium') return 'severity-medium' as const;

    return 'severity-low' as const;
};

const bureauLabel = (bureau?: string | null) => {
    if (bureau === 'transunion') return 'TransUnion';
    if (bureau === 'equifax') return 'Equifax';
    if (bureau === 'experian') return 'Experian';

    return bureau ?? 'All bureaus';
};

const hasBureauWordmark = (bureau?: string | null): bureau is BureauKey =>
    bureau === 'experian' || bureau === 'transunion' || bureau === 'equifax';

const evidenceDetailLabel = (detail?: string | null, bureau?: string | null) => {
    const value = String(detail ?? '').trim();
    const bureauName = bureau ? bureauLabel(bureau) : '';

    if (!bureauName || !value) {
        return value;
    }

    const escaped = bureauName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    return value.replace(new RegExp(`^${escaped}\\s+`, 'i'), '').trim();
};

const signalForMismatch = (mismatch: string) => {
    if (mismatch === 'missing_bureau_entry') {
        return { key: `missing-${mismatch}`, kind: 'missing' as const, label: 'Missing bureau', title: 'One or more bureaus are missing this tradeline.' };
    }

    if (mismatch === 'single_bureau_reporting') {
        return { key: `single-${mismatch}`, kind: 'single' as const, label: 'Single-bureau', title: 'Only one bureau is clearly carrying this tradeline.' };
    }

    if (mismatch === 'negative_reporting') {
        return { key: `negative-${mismatch}`, kind: 'negative' as const, label: 'Negative reporting', title: 'This tradeline is derogatory and should be reviewed first.' };
    }

    return {
        key: `mismatch-${mismatch}`,
        kind: 'mismatch' as const,
        label: compactLabel(mismatch),
        title: compactLabel(mismatch),
    };
};

const rowSignals = (row: (typeof props.rows)[number]) => row.mismatches.map(signalForMismatch);

const bureauTone = (entry: BureauEntry) => {
    if (entry === null) return 'border-stone-300 bg-stone-100/80 text-stone-500';

    const state = entry?.coverage_state ?? 'unknown';

    if (state === 'missing') return 'border-stone-300 bg-stone-100/80 text-stone-500';
    if (state === 'only') return 'border-sky-300 bg-sky-50 text-sky-900';
    if (state === 'pair') return 'border-amber-300 bg-amber-50 text-amber-900';
    if (state === 'diff') return 'border-rose-300 bg-rose-50 text-rose-900';

    return 'border-emerald-300 bg-emerald-50 text-emerald-900';
};

const bureauStateLabel = (entry: BureauEntry) => {
    if (entry === null) return 'Missing';

    const state = entry?.coverage_state ?? 'unknown';

    return {
        match: 'Aligned',
        pair: '2-match',
        diff: 'Mismatch',
        only: 'Only here',
        missing: 'Missing',
        unknown: 'Imported',
    }[state] ?? 'Imported';
};

const bureauPrimaryText = (entry: BureauEntry) => {
    if (!entry) {
        return 'Missing from this bureau';
    }

    return entry.account_status || entry.coverage_label || 'Imported from provider data';
};

const bureauDetailLines = (entry: BureauEntry) => {
    if (!entry) {
        return [];
    }

    const details = [
        entry.balance !== null && entry.balance !== undefined ? `Balance ${formatNumber(entry.balance)}` : null,
        entry.credit_limit !== null && entry.credit_limit !== undefined ? `Limit ${formatNumber(entry.credit_limit)}` : null,
        entry.utilization_percent !== null && entry.utilization_percent !== undefined ? `Utilization ${entry.utilization_percent}%` : null,
        entry.date_last_payment ? `Last payment ${entry.date_last_payment}` : null,
    ];

    return details.filter((detail): detail is string => Boolean(detail)).slice(0, 3);
};

const sectionCopy = (mode: 'needs-review' | 'aligned') => {
    if (mode === 'needs-review') {
        return 'Missing, single-bureau, or inconsistent reporting grouped for Metro 2 review and dispute prep.';
    }

    return 'Tradelines that currently look aligned enough to stay out of the dispute pile.';
};
</script>

<template>
    <Head :title="`Compare - ${client.display_name}`" />

    <h1 class="sr-only">{{ client.display_name }} comparison</h1>

    <div class="space-y-8">
        <ClientWorkspaceNav :client-id="client.id" :health-signal="clientHealth" />

        <ClientHealthSummary :client="client" :health-signal="clientHealth" />

        <section class="rounded-[30px] border border-stone-300/70 bg-stone-50/70 p-6">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-3">
                    <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Three-bureau compare</p>
                    <div class="space-y-2">
                        <h2 class="text-3xl font-semibold tracking-[-0.03em] text-stone-950">SmartCredit review board</h2>
                        <p class="max-w-3xl text-sm text-stone-600">
                            Group what is missing or inconsistent first, then keep the aligned reporting in its own lane.
                            Missing and mismatch findings from this board now flow into the violation queue automatically.
                        </p>
                    </div>
                </div>
                <div class="flex flex-col gap-3 lg:min-w-[260px] lg:items-end">
                    <span class="rounded-full border border-stone-300 bg-white/90 px-3 py-1 text-[11px] uppercase tracking-[0.2em] text-stone-600">
                        {{ cycle.cycle_label }}
                    </span>
                    <select :value="cycle.id.toString()" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm" @change="changeCycle">
                        <option v-for="option in cycles" :key="option.id" :value="option.id.toString()">
                            {{ option.cycle_label }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="mt-6 grid gap-3 md:grid-cols-3">
                <div class="rounded-[22px] border border-stone-200/80 bg-white/90 px-4 py-4">
                    <p class="text-[11px] uppercase tracking-[0.22em] text-stone-500">Imported tradelines</p>
                    <p class="mt-2 text-2xl font-semibold text-stone-950">{{ importedRowCount }}</p>
                </div>
                <div class="rounded-[22px] border border-amber-200 bg-amber-50/80 px-4 py-4">
                    <p class="text-[11px] uppercase tracking-[0.22em] text-amber-800">Needs review</p>
                    <p class="mt-2 text-2xl font-semibold text-stone-950">{{ needsReviewRows.length }}</p>
                </div>
                <div class="rounded-[22px] border border-emerald-200 bg-emerald-50/80 px-4 py-4">
                    <p class="text-[11px] uppercase tracking-[0.22em] text-emerald-800">Aligned</p>
                    <p class="mt-2 text-2xl font-semibold text-stone-950">{{ alignedRows.length }}</p>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-4">
            <MetricTile label="Accounts" :value="formatNumber(summary.total_accounts)" />
            <MetricTile label="Open accounts" :value="formatNumber(summary.open_accounts)" />
            <MetricTile label="Negative accounts" :value="formatNumber(summary.negative_accounts)" />
            <MetricTile label="Over 30%" :value="formatNumber(summary.over_thirty_percent)" />
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            <article
                v-for="bureau in bureauSummaries"
                :key="bureau.key"
                class="rounded-[24px] border border-stone-300/70 bg-stone-50/70 p-4"
            >
                <div class="flex items-center justify-between gap-3">
                    <BureauWordmark :bureau="bureau.key" class="h-5 w-auto" />
                    <span class="rounded-full border border-stone-300 bg-white/90 px-2 py-1 text-[11px] uppercase tracking-[0.18em] text-stone-600">
                        {{ bureau.presentCount }} present
                    </span>
                </div>
                <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-2xl border border-stone-200 bg-white/85 px-3 py-3">
                        <p class="text-[10px] uppercase tracking-[0.18em] text-stone-500">Flagged</p>
                        <p class="mt-1 text-lg font-semibold text-stone-950">{{ bureau.flaggedCount }}</p>
                    </div>
                    <div class="rounded-2xl border border-stone-200 bg-white/85 px-3 py-3">
                        <p class="text-[10px] uppercase tracking-[0.18em] text-stone-500">Aligned</p>
                        <p class="mt-1 text-lg font-semibold text-stone-950">{{ bureau.alignedCount }}</p>
                    </div>
                    <div class="rounded-2xl border border-stone-200 bg-white/85 px-3 py-3">
                        <p class="text-[10px] uppercase tracking-[0.18em] text-stone-500">Missing</p>
                        <p class="mt-1 text-lg font-semibold text-stone-950">{{ bureau.missingCount }}</p>
                    </div>
                </div>
            </article>
        </section>

        <section class="space-y-4 rounded-[28px] border border-stone-300/70 bg-stone-50/70 p-5">
            <div class="flex flex-col gap-3 border-b border-stone-300/70 pb-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Metro2 scan</p>
                    <p class="text-sm text-stone-600">
                        Suggested compliance and strategy findings from the current reporting cycle.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-full bg-stone-950 px-4 py-2 text-xs font-medium uppercase tracking-[0.22em] text-stone-50 transition hover:bg-stone-800"
                        @click="queueSuggestions"
                    >
                        Queue Suggested Findings
                    </button>
                    <button
                        type="button"
                        class="rounded-full border border-stone-300 px-4 py-2 text-xs font-medium uppercase tracking-[0.22em] text-stone-700 transition hover:border-stone-500"
                        @click="router.visit(`/clients/${client.id}/violations?cycle=${cycle.id}`)"
                    >
                        Open Violation Queue
                    </button>
                </div>
            </div>

            <div v-if="suggestions.length" class="grid gap-3 xl:grid-cols-2">
                <article
                    v-for="suggestion in suggestions"
                    :key="suggestion.fingerprint"
                    class="rounded-[24px] border border-stone-300/70 bg-white/80 p-4"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="font-medium text-stone-950">{{ suggestion.title }}</p>
                            <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                {{ compactLabel(suggestion.rule_key) }}<span v-if="suggestion.bureau"> • {{ suggestion.bureau }}</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <ReviewSignalLabel
                                kind="priority"
                                :label="`Priority ${suggestion.priority_score}`"
                                :title="`Priority ${suggestion.priority_score}`"
                                :style-key="reviewLabelStyle"
                            />
                            <ReviewSignalLabel
                                v-for="framework in suggestion.legal_frameworks"
                                :key="`${suggestion.fingerprint}-${framework.code}`"
                                kind="lawsuit"
                                :label="framework.label"
                                :title="framework.title"
                                :style-key="reviewLabelStyle"
                            />
                            <ReviewSignalLabel
                                :kind="severityLabelKind(suggestion.severity)"
                                :label="suggestion.severity"
                                :title="`${suggestion.severity} severity`"
                                :style-key="reviewLabelStyle"
                            />
                            <ReviewSignalLabel
                                v-if="suggestion.already_logged"
                                kind="queued"
                                label="Already queued"
                                title="Already queued in the current violation workflow."
                                :style-key="reviewLabelStyle"
                            />
                        </div>
                    </div>
                    <p v-if="suggestion.description" class="mt-3 text-sm text-stone-600">{{ suggestion.description }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-stone-700">
                        <li
                            v-for="evidenceItem in suggestion.evidence"
                            :key="`${suggestion.fingerprint}-${evidenceItem.detail}-${evidenceItem.bureau ?? 'all'}`"
                            class="rounded-2xl border border-stone-200/70 bg-stone-50/80 px-3 py-2"
                        >
                            <div
                                v-if="hasBureauWordmark(evidenceItem.bureau)"
                                class="flex items-center gap-3"
                            >
                                <BureauWordmark :bureau="evidenceItem.bureau" class="h-5 w-auto shrink-0" />
                                <div class="min-w-0">
                                    <p>{{ evidenceDetailLabel(evidenceItem.detail, evidenceItem.bureau) }}</p>
                                    <p
                                        v-if="evidenceItem.field"
                                        class="mt-1 text-[11px] uppercase tracking-[0.18em] text-stone-500"
                                    >
                                        {{ compactLabel(evidenceItem.field) }}
                                    </p>
                                </div>
                            </div>
                            <template v-else>
                                <p>{{ evidenceDetailLabel(evidenceItem.detail, evidenceItem.bureau) }}</p>
                                <p
                                    v-if="evidenceItem.bureau || evidenceItem.field"
                                    class="mt-1 text-[11px] uppercase tracking-[0.18em] text-stone-500"
                                >
                                    <span v-if="evidenceItem.bureau">{{ bureauLabel(evidenceItem.bureau) }}</span>
                                    <span v-if="evidenceItem.bureau && evidenceItem.field"> • </span>
                                    <span v-if="evidenceItem.field">{{ compactLabel(evidenceItem.field) }}</span>
                                </p>
                            </template>
                        </li>
                    </ul>
                </article>
            </div>

            <p v-else class="text-sm text-stone-500">
                No automated findings were generated for this cycle yet.
            </p>
        </section>

        <section class="space-y-4 rounded-[28px] border border-stone-300/70 bg-stone-50/70 p-5">
            <div class="border-b border-stone-300/70 pb-4">
                <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Needs review</p>
                <p class="text-sm text-stone-600">
                    {{ sectionCopy('needs-review') }}
                </p>
            </div>

            <div v-if="needsReviewRows.length" class="space-y-4">
                <article
                    v-for="row in needsReviewRows"
                    :key="row.key"
                    class="rounded-[24px] border border-stone-300/70 bg-white/85 p-4"
                >
                    <div class="flex flex-col gap-3 border-b border-stone-200/70 pb-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-1">
                            <p class="text-base font-semibold text-stone-950">{{ row.label }}</p>
                            <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                {{ row.coverage_label || `${row.severity} severity` }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <ReviewSignalLabel
                                v-for="signal in rowSignals(row)"
                                :key="signal.key"
                                :kind="signal.kind"
                                :label="signal.label"
                                :title="signal.title"
                                :style-key="reviewLabelStyle"
                            />
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 lg:grid-cols-3">
                        <article
                            v-for="bureau in bureauOrder"
                            :key="`${row.key}-${bureau}`"
                            class="rounded-[22px] border p-4"
                            :class="bureauTone(row.bureaus[bureau])"
                        >
                            <div class="flex items-start gap-3">
                                <BureauWordmark :bureau="bureau" class="mt-1 h-5 w-auto shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="text-sm font-medium">{{ bureauPrimaryText(row.bureaus[bureau]) }}</p>
                                        <span class="text-[10px] font-medium uppercase tracking-[0.18em]">
                                            {{ bureauStateLabel(row.bureaus[bureau]) }}
                                        </span>
                                    </div>
                                <ul v-if="bureauDetailLines(row.bureaus[bureau]).length" class="mt-2 space-y-1 text-sm opacity-80">
                                    <li v-for="detail in bureauDetailLines(row.bureaus[bureau])" :key="detail">{{ detail }}</li>
                                </ul>
                                </div>
                            </div>
                        </article>
                    </div>
                </article>
            </div>

            <p v-else class="text-sm text-stone-500">
                No missing or inconsistent rows were detected for this cycle.
            </p>
        </section>

        <section class="space-y-4 rounded-[28px] border border-stone-300/70 bg-stone-50/70 p-5">
            <div class="border-b border-stone-300/70 pb-4">
                <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Aligned reporting</p>
                <p class="text-sm text-stone-600">
                    {{ sectionCopy('aligned') }}
                </p>
            </div>

            <div v-if="alignedRows.length" class="grid gap-3 xl:grid-cols-2">
                <article
                    v-for="row in alignedRows"
                    :key="row.key"
                    class="rounded-[22px] border border-emerald-200 bg-white/85 p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-medium text-stone-950">{{ row.label }}</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.18em] text-stone-500">
                                {{ row.coverage_label || 'Aligned across the available bureaus' }}
                            </p>
                        </div>
                        <ReviewSignalLabel
                            kind="review"
                            label="Aligned"
                            title="This row looks clean enough to stay out of the dispute lane."
                            :style-key="reviewLabelStyle"
                        />
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <div
                            v-for="bureau in bureauOrder.filter((bureauKey) => row.bureaus[bureauKey] !== null)"
                            :key="`${row.key}-${bureau}-aligned`"
                            class="inline-flex items-center rounded-full border border-stone-300 bg-stone-100 px-3 py-2"
                        >
                            <BureauWordmark :bureau="bureau" class="h-4 w-auto" />
                        </div>
                    </div>
                </article>
            </div>

            <p v-else class="text-sm text-stone-500">
                Nothing is fully aligned yet. Everything in this cycle still needs review.
            </p>
        </section>
    </div>
</template>
