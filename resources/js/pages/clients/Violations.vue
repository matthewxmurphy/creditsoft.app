<script setup lang="ts">
import { computed, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import BureauWordmark from '@/components/creditsoft/BureauWordmark.vue';
import ClientWorkspaceNav from '@/components/creditsoft/ClientWorkspaceNav.vue';
import ReviewSignalLabel from '@/components/creditsoft/ReviewSignalLabel.vue';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { compactLabel, formatCurrency, formatDate } from '@/lib/creditsoft';

type BureauKey = 'experian' | 'transunion' | 'equifax';
type ComparisonFieldKey = 'account_status' | 'balance' | 'credit_limit' | 'payment_status' | 'date_last_payment';
type EvidenceItem = {
    detail: string;
    bureau?: string | null;
    field?: string | null;
    tradeline_id?: number | null;
    value?: string | number | boolean | null;
    missing?: boolean | null;
};

const props = defineProps<{
    client: {
        id: number;
        display_name: string;
    };
    selectedCycle?: {
        id: number;
        cycle_label: string;
    } | null;
    cycles: Array<{
        id: number;
        cycle_label: string;
    }>;
    rules: Array<{
        key: string;
        title: string;
        severity: string;
        category?: string | null;
        description?: string | null;
        next_action?: string | null;
        reference?: string | null;
    }>;
    creditReasonOptions: Array<{
        key: string;
        reason: string;
        group: string;
        bureau: string;
        round: string;
    }>;
    selectedCycleId?: number | null;
    suggestions: Array<{
        fingerprint: string;
        rule_key: string;
        title: string;
        severity: string;
        priority_score: number;
        bureau?: string | null;
        description?: string | null;
        next_action?: string | null;
        reference?: string | null;
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
    violations: Array<{
        id: number;
        title: string;
        severity: string;
        priority_score: number;
        status: string;
        rule_key: string;
        bureau?: string | null;
        next_action?: string | null;
        evidence: EvidenceItem[];
        legal_frameworks: Array<{
            code: string;
            label: string;
            kind: string;
            title: string;
        }>;
    }>;
    comparisonRows: Array<{
        key: string;
        label: string;
        duplicates: string[];
        mismatches: string[];
        severity: string;
        coverage_label?: string | null;
        bureaus: Record<string, {
            id: number;
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
        } | null>;
    }>;
}>();

const page = usePage<{
    creditsoft?: {
        ui?: {
            review_label_style?: string | null;
        } | null;
    } | null;
}>();
const bureauKeys: BureauKey[] = ['experian', 'transunion', 'equifax'];
const reviewLabelStyle = computed(() => String(page.props.creditsoft?.ui?.review_label_style ?? '10'));
const comparisonFields: Array<{ key: ComparisonFieldKey; label: string }> = [
    { key: 'account_status', label: 'Status' },
    { key: 'balance', label: 'Balance' },
    { key: 'credit_limit', label: 'Limit' },
    { key: 'payment_status', label: 'Payment' },
    { key: 'date_last_payment', label: 'Last payment' },
];

const form = useForm({
    reporting_cycle_id: props.selectedCycleId?.toString() ?? props.cycles[0]?.id?.toString() ?? '',
    rule_key: props.rules[0]?.key ?? 'metro2_status_conflict',
    title: props.rules[0]?.title ?? '',
    severity: props.rules[0]?.severity ?? 'medium',
    bureau: 'experian',
    dispute_reason: '',
    next_action: '',
    evidence: '',
});

const selectedRule = computed(() => props.rules.find((entry) => entry.key === form.rule_key) ?? null);
const selectedReasonOptions = computed(() => {
    const bureau = form.bureau || 'all';
    const group = selectedRule.value?.category === 'compliance' ? 'Collections' : 'Account';

    const matching = props.creditReasonOptions.filter((reason) => {
        const bureauMatches = reason.bureau === 'all' || reason.bureau === bureau;
        const groupMatches = reason.group === group || (group === 'Account' && ['Account', 'Collections'].includes(reason.group));

        return bureauMatches && groupMatches;
    });

    return matching.length ? matching : props.creditReasonOptions;
});

if (!form.dispute_reason && selectedReasonOptions.value.length) {
    form.dispute_reason = selectedReasonOptions.value[0].reason;
}

watch(selectedReasonOptions, (options) => {
    if (options.length === 0) {
        form.dispute_reason = '';

        return;
    }

    if (!options.some((reason) => reason.reason === form.dispute_reason)) {
        form.dispute_reason = options[0].reason;
    }
});

const submit = () => {
    form.post(`/clients/${props.client.id}/violations`, {
        preserveScroll: true,
        onSuccess: () => form.reset('next_action', 'evidence'),
    });
};

const setRule = (event: Event) => {
    const nextKey = (event.target as HTMLSelectElement).value;
    const rule = props.rules.find((entry) => entry.key === nextKey);

    form.rule_key = nextKey;
    form.title = rule?.title ?? '';
    form.severity = rule?.severity ?? 'medium';
    form.dispute_reason = selectedReasonOptions.value[0]?.reason ?? form.dispute_reason;
};

const updateStatus = (id: number, status: string) => {
    router.patch(`/clients/${props.client.id}/violations/${id}`, { status }, { preserveScroll: true });
};

const queueSuggestions = () => {
    if (!form.reporting_cycle_id) return;

    router.post(`/clients/${props.client.id}/violations/scan`, {
        reporting_cycle_id: form.reporting_cycle_id,
    }, {
        preserveScroll: true,
    });
};

const changeCycle = (event: Event) => {
    const target = event.target as HTMLSelectElement;

    form.reporting_cycle_id = target.value;

    router.get(`/clients/${props.client.id}/violations`, {
        cycle: target.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

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

const violationStatusKind = (status: string) => {
    if (status === 'resolved') return 'status-resolved' as const;
    if (status === 'confirmed') return 'status-confirmed' as const;

    return 'status-open' as const;
};

const bureauLabel = (bureau?: string | null) => {
    if (bureau === 'transunion') return 'TransUnion';
    if (bureau === 'equifax') return 'Equifax';
    if (bureau === 'experian') return 'Experian';

    return bureau ?? 'All bureaus';
};

const hasBureauWordmark = (bureau?: string | null): bureau is BureauKey =>
    bureau === 'experian' || bureau === 'transunion' || bureau === 'equifax';

const evidenceDetailLabel = (item: EvidenceItem) => {
    const detail = item.detail?.trim() ?? '';
    const bureau = item.bureau ? bureauLabel(item.bureau) : '';

    if (!bureau || !detail) {
        return detail;
    }

    const escaped = bureau.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    return detail.replace(new RegExp(`^${escaped}\\s+`, 'i'), '').trim();
};

const bureauRecord = (row: (typeof props.comparisonRows)[number], bureau: BureauKey) =>
    row.bureaus[bureau] ?? null;

const normalizedFieldValue = (value: unknown) => {
    if (value === null || value === undefined || value === '') return '__missing__';
    if (typeof value === 'boolean') return value ? 'true' : 'false';

    return String(value);
};

const comparisonFieldValue = (
    row: (typeof props.comparisonRows)[number],
    bureau: BureauKey,
    field: ComparisonFieldKey,
) => {
    const record = bureauRecord(row, bureau);

    if (!record) return 'Missing tradeline';

    const value = record[field];

    if (field === 'balance' || field === 'credit_limit') {
        return typeof value === 'number' ? formatCurrency(value) : 'Missing';
    }

    if (field === 'date_last_payment') {
        return typeof value === 'string' && value ? formatDate(value) : 'Missing';
    }

    return value ? String(value) : 'Missing';
};

const comparisonFieldTone = (
    row: (typeof props.comparisonRows)[number],
    field: ComparisonFieldKey,
    bureau: BureauKey,
) => {
    const record = bureauRecord(row, bureau);

    if (!record) {
        return 'border-rose-300 bg-rose-50 text-rose-900';
    }

    const values = bureauKeys
        .map((currentBureau) => {
            const currentRecord = bureauRecord(row, currentBureau);

            return {
                bureau: currentBureau,
                value: currentRecord?.[field] ?? null,
                normalized: normalizedFieldValue(currentRecord?.[field] ?? null),
            };
        })
        .filter((entry) => bureauRecord(row, entry.bureau));

    const current = values.find((entry) => entry.bureau === bureau);
    const nonMissing = values.filter((entry) => entry.normalized !== '__missing__');

    if (!current) {
        return 'border-stone-200 bg-stone-50 text-stone-500';
    }

    if (current.normalized === '__missing__') {
        return nonMissing.length > 0
            ? 'border-rose-300 bg-rose-50 text-rose-900'
            : 'border-stone-200 bg-stone-50 text-stone-500';
    }

    if (nonMissing.length <= 1) {
        return 'border-emerald-300 bg-emerald-50 text-emerald-900';
    }

    const counts = new Map<string, number>();

    for (const entry of nonMissing) {
        counts.set(entry.normalized, (counts.get(entry.normalized) ?? 0) + 1);
    }

    const maxCount = Math.max(...counts.values());
    const currentCount = counts.get(current.normalized) ?? 0;

    if (maxCount > 1 && currentCount === maxCount) {
        return 'border-emerald-300 bg-emerald-50 text-emerald-900';
    }

    return 'border-rose-300 bg-rose-50 text-rose-900';
};

const evidenceTone = (evidence: EvidenceItem[], item: EvidenceItem) => {
    if (item.missing) {
        return 'border-rose-300 bg-rose-50 text-rose-900';
    }

    const comparable = evidence
        .filter((entry) => entry.field === item.field && !entry.missing && entry.value !== null && entry.value !== undefined)
        .map((entry) => normalizedFieldValue(entry.value));

    if (comparable.length <= 1) {
        return 'border-emerald-300 bg-emerald-50 text-emerald-900';
    }

    const counts = comparable.reduce((carry, value) => {
        carry.set(value, (carry.get(value) ?? 0) + 1);

        return carry;
    }, new Map<string, number>());
    const currentValue = normalizedFieldValue(item.value);
    const maxCount = Math.max(...counts.values());
    const currentCount = counts.get(currentValue) ?? 0;

    if (maxCount > 1 && currentCount === maxCount) {
        return 'border-emerald-300 bg-emerald-50 text-emerald-900';
    }

    return 'border-rose-300 bg-rose-50 text-rose-900';
};

const evidenceShowsVisibleConflict = (evidence: EvidenceItem[]) =>
    evidence.some((item) => evidenceTone(evidence, item).includes('rose'));

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

    if (mismatch === 'utilization_over_threshold') {
        return { key: `utilization-${mismatch}`, kind: 'utilization' as const, label: 'Utilization', title: 'Utilization is over the review threshold.' };
    }

    return {
        key: `mismatch-${mismatch}`,
        kind: 'mismatch' as const,
        label: compactLabel(mismatch),
        title: compactLabel(mismatch),
    };
};

const rowSignals = (row: (typeof props.comparisonRows)[number]) => row.mismatches.map(signalForMismatch);
</script>

<template>
    <Head :title="`Violations - ${client.display_name}`" />

    <h1 class="sr-only">{{ client.display_name }} violations</h1>

    <div class="space-y-8">
        <ClientWorkspaceNav :client-id="client.id" />

        <section class="space-y-3 rounded-[28px] border border-stone-300/70 bg-stone-50/70 p-5">
            <div class="flex flex-col gap-3 border-b border-stone-300/70 pb-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Automated findings</p>
                    <p class="text-sm text-stone-600">
                        Review the latest suggested Metro2 and compliance items before promoting them into the queue.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <select
                        :value="form.reporting_cycle_id"
                        class="border-input h-9 rounded-full border bg-white px-3 text-sm"
                        @change="changeCycle"
                    >
                        <option v-for="cycle in cycles" :key="cycle.id" :value="cycle.id.toString()">
                            {{ cycle.cycle_label }}
                        </option>
                    </select>
                    <button
                        type="button"
                        class="rounded-full bg-stone-950 px-4 py-2 text-xs font-medium uppercase tracking-[0.22em] text-stone-50 transition hover:bg-stone-800"
                        @click="queueSuggestions"
                    >
                        Queue Suggested Findings
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
                        <div>
                            <p class="font-medium text-stone-950">{{ suggestion.title }}</p>
                            <p class="mt-1 text-xs uppercase tracking-[0.22em] text-stone-500">
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
                        </div>
                    </div>
                    <p v-if="suggestion.description" class="mt-3 text-sm text-stone-600">{{ suggestion.description }}</p>
                    <ul class="mt-3 space-y-2 text-sm text-stone-700">
                        <li
                            v-for="evidenceItem in suggestion.evidence"
                            :key="`${suggestion.fingerprint}-${evidenceItem.detail}-${evidenceItem.bureau ?? 'all'}`"
                            class="rounded-2xl border px-3 py-2"
                            :class="evidenceTone(suggestion.evidence, evidenceItem)"
                        >
                            <div
                                v-if="hasBureauWordmark(evidenceItem.bureau)"
                                class="flex items-center gap-3"
                            >
                                <BureauWordmark
                                    :bureau="evidenceItem.bureau"
                                    class="h-5 w-auto shrink-0"
                                />
                                <div class="min-w-0">
                                    <p>{{ evidenceDetailLabel(evidenceItem) }}</p>
                                    <p
                                        v-if="evidenceItem.field"
                                        class="mt-1 text-[11px] uppercase tracking-[0.18em] text-stone-500"
                                    >
                                        {{ compactLabel(evidenceItem.field) }}
                                    </p>
                                </div>
                            </div>
                            <template v-else>
                                <p>{{ evidenceDetailLabel(evidenceItem) }}</p>
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
                    <p class="mt-3 text-sm text-stone-600">{{ suggestion.next_action ?? 'Review before queuing.' }}</p>
                    <p v-if="suggestion.already_logged" class="mt-2 text-[11px] uppercase tracking-[0.18em] text-emerald-700">
                        Already in the current violation queue
                    </p>
                </article>
            </div>

            <p v-else class="text-sm text-stone-500">
                No automated findings are available for the selected cycle yet.
            </p>
        </section>

        <section class="space-y-4 rounded-[28px] border border-stone-300/70 bg-stone-50/70 p-5">
            <div class="border-b border-stone-300/70 pb-4">
                <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Three-bureau evidence board</p>
                <p class="text-sm text-stone-600">
                    Review the actual Experian, TransUnion, and Equifax entries side by side for the selected cycle before confirming a violation.
                    <span v-if="selectedCycle"> Currently showing {{ selectedCycle.cycle_label }}.</span>
                </p>
            </div>

            <div v-if="comparisonRows.length" class="overflow-hidden rounded-[24px] border border-stone-300/70 bg-white/80">
                <table class="min-w-full divide-y divide-stone-300/70 text-sm">
                    <thead class="bg-stone-100/80 text-left text-[11px] uppercase tracking-[0.22em] text-stone-500">
                        <tr>
                            <th class="px-4 py-3">Tradeline</th>
                            <th v-for="bureau in bureauKeys" :key="`head-${bureau}`" class="px-4 py-3">
                                <BureauWordmark :bureau="bureau" class="h-4 w-auto" />
                            </th>
                            <th class="px-4 py-3">Flags</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200/70 bg-stone-50/70 align-top">
                        <tr v-for="row in comparisonRows" :key="row.key">
                            <td class="px-4 py-4">
                                <p class="font-medium text-stone-950">{{ row.label }}</p>
                                <p class="mt-2 text-xs uppercase tracking-[0.16em] text-stone-500">
                                    {{ row.severity }} severity
                                </p>
                                <p v-if="row.coverage_label" class="mt-2 text-[11px] uppercase tracking-[0.18em] text-stone-500">
                                    {{ row.coverage_label }}
                                </p>
                            </td>
                            <td v-for="bureau in bureauKeys" :key="bureau" class="px-4 py-4">
                                <div class="space-y-2">
                                    <div class="mb-3 flex justify-end">
                                        <span
                                            class="rounded-full border px-2 py-1 text-[10px] font-medium uppercase tracking-[0.18em]"
                                            :class="bureauRecord(row, bureau) ? 'border-stone-200 bg-stone-100 text-stone-600' : 'border-rose-200 bg-rose-100 text-rose-800'"
                                        >
                                            {{ bureauRecord(row, bureau) ? 'Imported' : 'Missing' }}
                                        </span>
                                    </div>
                                    <div
                                        v-if="!bureauRecord(row, bureau)"
                                        class="rounded-2xl border border-rose-300 bg-rose-50 px-3 py-3 text-sm text-rose-900"
                                    >
                                        <p class="mt-1 font-medium">Missing tradeline</p>
                                    </div>
                                    <div
                                        v-for="field in comparisonFields"
                                        :key="`${row.key}-${bureau}-${field.key}`"
                                        class="rounded-2xl border px-3 py-2"
                                        :class="comparisonFieldTone(row, field.key, bureau)"
                                    >
                                        <p class="text-[10px] uppercase tracking-[0.18em] opacity-70">{{ field.label }}</p>
                                        <p class="mt-1 font-medium">
                                            {{ comparisonFieldValue(row, bureau, field.key) }}
                                        </p>
                                    </div>
                                </div>
                                <p
                                    v-if="bureauRecord(row, bureau)?.coverage_label"
                                    class="mt-2 text-[11px] uppercase tracking-[0.18em] text-stone-500"
                                >
                                    {{ bureauRecord(row, bureau)?.coverage_label }}
                                </p>
                                <p
                                    v-else-if="bureauRecord(row, bureau)?.remarks"
                                    class="mt-2 text-[11px] uppercase tracking-[0.18em] text-stone-500"
                                >
                                    {{ bureauRecord(row, bureau)?.remarks }}
                                </p>
                            </td>
                            <td class="px-4 py-4">
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
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-else class="text-sm text-stone-500">
                No three-bureau mismatch rows are available for this cycle yet. Import or compare bureau snapshots first.
            </p>
        </section>

        <section class="grid gap-8 xl:grid-cols-[0.9fr_1.1fr]">
            <form class="space-y-3" @submit.prevent="submit">
                <div class="border-b border-stone-300/70 pb-3">
                    <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Log violation candidate</p>
                    <p class="text-sm text-stone-600">Confirm Metro 2 issues, completeness gaps, and cross-bureau mismatches.</p>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <select v-model="form.reporting_cycle_id" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm">
                        <option v-for="cycle in cycles" :key="cycle.id" :value="cycle.id.toString()">
                            {{ cycle.cycle_label }}
                        </option>
                    </select>
                    <select :value="form.rule_key" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm" @change="setRule">
                        <option v-for="rule in rules" :key="rule.key" :value="rule.key">
                            {{ rule.title }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <Input v-model="form.title" placeholder="Violation title" />
                    <select v-model="form.bureau" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm">
                        <option value="experian">Experian</option>
                        <option value="transunion">TransUnion</option>
                        <option value="equifax">Equifax</option>
                    </select>
                </div>
                <select v-model="form.severity" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm">
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                </select>
                <select v-model="form.dispute_reason" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm">
                    <option value="">Select office dispute reason</option>
                    <option v-for="reason in selectedReasonOptions" :key="reason.key" :value="reason.reason">
                        {{ reason.reason }}
                    </option>
                </select>
                <Textarea v-model="form.evidence" placeholder="What did the reports show that triggered this candidate?" />
                <Textarea v-model="form.next_action" placeholder="What should happen next?" />
                <button type="submit" class="rounded-full bg-stone-950 px-4 py-2 text-xs font-medium uppercase tracking-[0.22em] text-stone-50 transition hover:bg-stone-800">
                    Add violation
                </button>
            </form>

            <section class="space-y-3">
                <div class="border-b border-stone-300/70 pb-3">
                    <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Current queue</p>
                    <p class="text-sm text-stone-600">Review, confirm, and resolve candidate issues.</p>
                </div>

                <div class="space-y-3">
                    <div v-for="violation in violations" :key="violation.id" class="rounded-[24px] border border-stone-300/70 bg-stone-50/70 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="space-y-1">
                                <p class="font-medium text-stone-950">{{ violation.title }}</p>
                                <p class="text-xs uppercase tracking-[0.22em] text-stone-500">
                                    {{ compactLabel(violation.rule_key) }} • {{ violation.bureau ?? 'All bureaus' }}
                                </p>
                            </div>
                        <div class="flex flex-wrap gap-2">
                            <ReviewSignalLabel
                                kind="priority"
                                :label="`Priority ${violation.priority_score}`"
                                :title="`Priority ${violation.priority_score}`"
                                :style-key="reviewLabelStyle"
                            />
                            <ReviewSignalLabel
                                v-for="framework in violation.legal_frameworks"
                                :key="`${violation.id}-${framework.code}`"
                                kind="lawsuit"
                                :label="framework.label"
                                :title="framework.title"
                                :style-key="reviewLabelStyle"
                            />
                            <ReviewSignalLabel
                                :kind="severityLabelKind(violation.severity)"
                                :label="violation.severity"
                                    :title="`${violation.severity} severity`"
                                    :style-key="reviewLabelStyle"
                                />
                            </div>
                        </div>
                        <p class="mt-3 text-sm text-stone-600">{{ violation.next_action || 'No action added yet.' }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" class="rounded-full border border-stone-300 px-3 py-1.5 text-[11px] uppercase tracking-[0.18em] text-stone-600" @click="updateStatus(violation.id, 'confirmed')">
                                Confirm
                            </button>
                            <button type="button" class="rounded-full border border-stone-300 px-3 py-1.5 text-[11px] uppercase tracking-[0.18em] text-stone-600" @click="updateStatus(violation.id, 'resolved')">
                                Resolve
                            </button>
                            <ReviewSignalLabel
                                :kind="violationStatusKind(violation.status)"
                                :label="violation.status"
                                :title="`Violation status: ${violation.status}`"
                                :style-key="reviewLabelStyle"
                            />
                        </div>
                        <ul v-if="violation.evidence.length" class="mt-4 space-y-2 text-sm text-stone-700">
                            <li
                                v-for="evidenceItem in violation.evidence"
                                :key="`${violation.id}-${evidenceItem.detail}-${evidenceItem.bureau ?? 'all'}`"
                                class="rounded-2xl border px-3 py-2"
                                :class="evidenceTone(violation.evidence, evidenceItem)"
                            >
                                <div
                                    v-if="hasBureauWordmark(evidenceItem.bureau)"
                                    class="flex items-center gap-3"
                                >
                                    <BureauWordmark
                                        :bureau="evidenceItem.bureau"
                                        class="h-5 w-auto shrink-0"
                                    />
                                    <div class="min-w-0">
                                        <p>{{ evidenceDetailLabel(evidenceItem) }}</p>
                                        <p
                                            v-if="evidenceItem.field"
                                            class="mt-1 text-[11px] uppercase tracking-[0.18em] text-stone-500"
                                        >
                                            {{ compactLabel(evidenceItem.field) }}
                                        </p>
                                    </div>
                                </div>
                                <template v-else>
                                    <p>{{ evidenceDetailLabel(evidenceItem) }}</p>
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
                        <p
                            v-if="violation.evidence.length && !evidenceShowsVisibleConflict(violation.evidence)"
                            class="mt-3 rounded-2xl border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                        >
                            Visible bureau values currently align in this import. Re-open Compare before disputing this older queued item.
                        </p>
                    </div>
                </div>
            </section>
        </section>
    </div>
</template>
