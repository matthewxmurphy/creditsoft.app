<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
    faArrowUpRightFromSquare,
    faClipboardCheck,
    faClock,
    faCircleExclamation,
    faMoneyBillTransfer,
    faMoneyCheckDollar,
    faPaperPlane,
    faReceipt,
    faUsers,
} from '@fortawesome/free-solid-svg-icons';
import MetricTile from '@/components/creditsoft/MetricTile.vue';
import { formatCurrency, formatDate, formatDateTime, formatNumber } from '@/lib/creditsoft';

type StaffPayMethod = {
    id: number;
    name: string;
    email: string;
    role_label?: string | null;
    pay_method?: string | null;
    pay_destination?: string | null;
    pay_currency?: string | null;
    payroll_notes?: string | null;
};

type PayrollRecord = {
    id: number;
    employee_name?: string | null;
    period_start?: string | null;
    period_end?: string | null;
    amount: number;
    currency: string;
    pay_method?: string | null;
    pay_destination?: string | null;
    reference?: string | null;
    status: string;
    paid_at?: string | null;
    notes?: string | null;
    created_at?: string | null;
};

const props = defineProps<{
    summary: {
        staff: number;
        ready_methods: number;
        scheduled: number;
        sent_30_days: number;
    };
    sendwave: {
        code: string;
        credit: string;
        url: string;
        copy: string;
    };
    staff: StaffPayMethod[];
    records: PayrollRecord[];
}>();

const firstStaff = computed(() => props.staff[0]);
const selectedStaff = computed(() => props.staff.find((staff) => staff.id.toString() === payrollForm.user_id));
const readyStaff = computed(() => props.staff.filter((member) => Boolean(member.pay_method && member.pay_destination)));
const needsSetup = computed(() => props.staff.filter((member) => !member.pay_method || !member.pay_destination));
const scheduledRecords = computed(() => props.records.filter((record) => record.status === 'scheduled'));
const sentRecords = computed(() => props.records.filter((record) => ['sent', 'confirmed'].includes(record.status)));
const recentRecords = computed(() => props.records.slice(0, 8));
const ledgerTotal = computed(() => props.records.reduce((sum, record) => sum + Number(record.amount || 0), 0));
const methodCoverage = computed(() => props.summary.staff > 0 ? Math.round((readyStaff.value.length / props.summary.staff) * 100) : 0);

const payrollForm = useForm({
    user_id: firstStaff.value?.id?.toString() ?? '',
    period_start: '',
    period_end: '',
    amount: '',
    currency: firstStaff.value?.pay_currency ?? 'USD',
    pay_method: firstStaff.value?.pay_method ?? '',
    pay_destination: firstStaff.value?.pay_destination ?? '',
    reference: '',
    status: 'draft',
    paid_at: '',
    notes: '',
});

const payrollLanes = computed(() => [
    {
        label: 'Ready to pay',
        value: formatNumber(readyStaff.value.length),
        detail: `${methodCoverage.value}% of employees have saved pay details`,
        icon: faClipboardCheck,
        tone: 'border-emerald-200 bg-emerald-50/80 text-emerald-800',
    },
    {
        label: 'Needs setup',
        value: formatNumber(needsSetup.value.length),
        detail: 'Missing method, destination, or both',
        icon: faCircleExclamation,
        tone: needsSetup.value.length > 0 ? 'border-amber-200 bg-amber-50/90 text-amber-800' : 'border-stone-200 bg-stone-50 text-stone-700',
    },
    {
        label: 'Scheduled',
        value: formatCurrency(props.summary.scheduled),
        detail: `${formatNumber(scheduledRecords.value.length)} transfer records waiting`,
        icon: faClock,
        tone: 'border-blue-200 bg-blue-50/80 text-blue-800',
    },
    {
        label: 'Recent sent',
        value: formatCurrency(props.summary.sent_30_days),
        detail: `${formatNumber(sentRecords.value.length)} sent or confirmed in this ledger`,
        icon: faPaperPlane,
        tone: 'border-stone-300 bg-white/88 text-stone-800',
    },
]);

const syncSelectedStaff = () => {
    payrollForm.currency = selectedStaff.value?.pay_currency ?? payrollForm.currency ?? 'USD';
    payrollForm.pay_method = selectedStaff.value?.pay_method ?? '';
    payrollForm.pay_destination = selectedStaff.value?.pay_destination ?? '';
};

const submitPayroll = () => payrollForm.post('/payroll/records', {
    preserveScroll: true,
    onSuccess: () => payrollForm.reset('amount', 'reference', 'paid_at', 'notes'),
});

const statusTone = (status: string) => {
    if (status === 'confirmed') return 'border-emerald-200 bg-emerald-100 text-emerald-800';
    if (status === 'sent') return 'border-blue-200 bg-blue-100 text-blue-800';
    if (status === 'scheduled') return 'border-amber-200 bg-amber-100 text-amber-800';

    return 'border-stone-200 bg-stone-200 text-stone-700';
};

const periodLabel = (record: PayrollRecord) => {
    if (record.period_start && record.period_end) return `${formatDate(record.period_start)} - ${formatDate(record.period_end)}`;
    if (record.period_start) return `Starting ${formatDate(record.period_start)}`;
    if (record.period_end) return `Ending ${formatDate(record.period_end)}`;

    return 'No period saved';
};

const methodLabel = (member: StaffPayMethod) => {
    if (member.pay_method && member.pay_destination) return `${member.pay_method} / ${member.pay_destination}`;
    if (member.pay_method) return `${member.pay_method} / destination needed`;
    if (member.pay_destination) return `Method needed / ${member.pay_destination}`;

    return 'Pay setup needed';
};
</script>

<template>
    <Head title="Payroll" />

    <h1 class="sr-only">Payroll</h1>

    <div class="space-y-7">
        <section class="grid gap-4 border-b border-stone-300/70 pb-5 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.32em] text-stone-500">Payroll</p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight text-stone-950">Small-team payroll control, transfer tracking, and staff pay readiness.</h2>
                <p class="mt-2 max-w-4xl text-sm leading-6 text-stone-600">
                    AI should keep the team small; this screen keeps the few humans cleanly onboarded, paid, documented, and ready for overseas transfer exceptions.
                </p>
            </div>
            <div class="grid gap-2 rounded-[24px] border border-stone-300/70 bg-white/82 p-4 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-stone-500">Method coverage</span>
                    <strong class="text-stone-950">{{ methodCoverage }}%</strong>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-stone-200">
                    <div class="h-full rounded-full bg-emerald-600" :style="{ width: `${methodCoverage}%` }" />
                </div>
                <a href="/hr" class="mt-1 inline-flex items-center gap-2 text-xs font-semibold text-stone-700 transition hover:text-stone-950">
                    Open HR setup
                    <FontAwesomeIcon :icon="faArrowUpRightFromSquare" />
                </a>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <MetricTile label="Staff" :value="formatNumber(summary.staff)" />
            <MetricTile label="Pay methods ready" :value="formatNumber(summary.ready_methods)" />
            <MetricTile label="Scheduled" :value="formatCurrency(summary.scheduled)" />
            <MetricTile label="Sent in 30 days" :value="formatCurrency(summary.sent_30_days)" />
        </section>

        <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <article v-for="lane in payrollLanes" :key="lane.label" class="rounded-[24px] border p-4" :class="lane.tone">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] opacity-75">{{ lane.label }}</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight">{{ lane.value }}</p>
                    </div>
                    <FontAwesomeIcon :icon="lane.icon" class="mt-1 text-lg opacity-75" />
                </div>
                <p class="mt-3 text-xs leading-5 opacity-80">{{ lane.detail }}</p>
            </article>
        </section>

        <section class="space-y-5">
            <div class="border-b border-stone-300/70 pb-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.32em] text-stone-500">Payroll command center</p>
                <p class="text-sm text-stone-600">Record new payroll activity, see who needs setup, and keep transfer support close.</p>
            </div>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)]">
                <form class="space-y-4 rounded-[28px] border border-stone-300/70 bg-white/82 p-5" @submit.prevent="submitPayroll">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-stone-950">Record payroll</p>
                            <p class="mt-1 text-xs leading-5 text-stone-500">Save drafts, scheduled payments, sent transfers, or confirmed payroll records.</p>
                        </div>
                        <FontAwesomeIcon :icon="faMoneyCheckDollar" class="text-xl text-emerald-700" />
                    </div>

                    <label class="space-y-1">
                        <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-stone-500">Employee</span>
                        <select v-model="payrollForm.user_id" class="h-11 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm" @change="syncSelectedStaff">
                            <option v-for="member in staff" :key="member.id" :value="member.id.toString()">{{ member.name }} / {{ member.email }}</option>
                        </select>
                    </label>

                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="space-y-1">
                            <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500">Period start</span>
                            <input v-model="payrollForm.period_start" type="date" class="h-11 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm" />
                        </label>
                        <label class="space-y-1">
                            <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500">Period end</span>
                            <input v-model="payrollForm.period_end" type="date" class="h-11 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm" />
                        </label>
                        <label class="space-y-1">
                            <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500">Amount</span>
                            <input v-model="payrollForm.amount" type="number" min="0.01" step="0.01" class="h-11 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="0.00" />
                        </label>
                        <label class="space-y-1">
                            <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500">Currency</span>
                            <input v-model="payrollForm.currency" type="text" maxlength="3" class="h-11 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm uppercase" placeholder="USD" />
                        </label>
                        <label class="space-y-1">
                            <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500">Method</span>
                            <input v-model="payrollForm.pay_method" type="text" class="h-11 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="Zelle, Cash App, Sendwave, ACH" />
                        </label>
                        <label class="space-y-1">
                            <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500">Destination</span>
                            <input v-model="payrollForm.pay_destination" type="text" class="h-11 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="Email, phone, username, bank note" />
                        </label>
                        <label class="space-y-1">
                            <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500">Reference</span>
                            <input v-model="payrollForm.reference" type="text" class="h-11 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="Confirmation number" />
                        </label>
                        <label class="space-y-1">
                            <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500">Status</span>
                            <select v-model="payrollForm.status" class="h-11 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm">
                                <option value="draft">Draft</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="sent">Sent</option>
                                <option value="confirmed">Confirmed</option>
                            </select>
                        </label>
                    </div>

                    <label class="space-y-1">
                        <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500">Paid at</span>
                        <input v-model="payrollForm.paid_at" type="datetime-local" class="h-11 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm" />
                    </label>
                    <label class="space-y-1">
                        <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500">Internal note</span>
                        <textarea v-model="payrollForm.notes" rows="4" class="w-full rounded-2xl border border-stone-300 bg-white px-3 py-2 text-sm" placeholder="Sender, transfer restriction, split payment, confirmation follow-up, or HR note." />
                    </label>
                    <button type="submit" class="inline-flex h-11 items-center gap-2 rounded-full bg-stone-950 px-5 text-sm font-semibold text-white transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="payrollForm.processing">
                        <FontAwesomeIcon :icon="faReceipt" />
                        Save payroll record
                    </button>
                </form>

                <div class="space-y-4">
                    <article class="rounded-[28px] border border-stone-300/70 bg-white/82 p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-stone-500">Setup gaps</p>
                                <h3 class="mt-2 text-lg font-semibold text-stone-950">{{ formatNumber(needsSetup.length) }} employees need payroll details</h3>
                            </div>
                            <FontAwesomeIcon :icon="faUsers" class="text-lg text-stone-500" />
                        </div>
                        <div class="mt-4 space-y-2">
                            <article v-for="member in needsSetup.slice(0, 5)" :key="member.id" class="rounded-[18px] border border-amber-200 bg-amber-50/70 p-3 text-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-stone-950">{{ member.name }}</p>
                                        <p class="mt-1 truncate text-xs text-stone-600">{{ methodLabel(member) }}</p>
                                    </div>
                                    <span class="shrink-0 text-[10px] font-semibold uppercase tracking-[0.16em] text-amber-800">{{ member.pay_currency ?? 'USD' }}</span>
                                </div>
                            </article>
                            <p v-if="needsSetup.length === 0" class="rounded-[18px] border border-emerald-200 bg-emerald-50/70 p-3 text-sm text-emerald-800">
                                Every employee has a saved method and destination.
                            </p>
                        </div>
                    </article>

                    <article class="rounded-[28px] border border-yellow-300/80 bg-[linear-gradient(135deg,_rgba(255,236,0,0.34),_rgba(255,251,235,0.92)_48%,_rgba(255,255,255,0.9))] p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.3em] text-yellow-800">Overseas payroll</p>
                                <h3 class="mt-2 text-lg font-semibold text-stone-950">Sendwave code {{ sendwave.code }} gives {{ sendwave.credit }} credit.</h3>
                            </div>
                            <img src="/assets/vendor-logos/sendwave.svg" alt="Sendwave" class="h-12 w-12 shrink-0 object-contain" />
                        </div>
                        <p class="mt-3 text-sm leading-6 text-stone-700">{{ sendwave.copy }}</p>
                        <a :href="sendwave.url" target="_blank" rel="noopener" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-yellow-900 transition hover:text-stone-950">
                            Download Sendwave
                            <FontAwesomeIcon :icon="faArrowUpRightFromSquare" />
                        </a>
                    </article>

                    <article class="rounded-[28px] border border-stone-300/70 bg-white/82 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.28em] text-stone-500">Transfer queue</p>
                                <h3 class="mt-2 text-lg font-semibold text-stone-950">{{ formatNumber(scheduledRecords.length) }} scheduled records</h3>
                            </div>
                            <FontAwesomeIcon :icon="faMoneyBillTransfer" class="text-lg text-blue-700" />
                        </div>
                        <div class="mt-4 space-y-2">
                            <article v-for="record in scheduledRecords.slice(0, 4)" :key="record.id" class="rounded-[18px] border border-blue-200 bg-blue-50/70 p-3 text-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-stone-950">{{ record.employee_name }}</p>
                                        <p class="mt-1 text-xs text-stone-600">{{ periodLabel(record) }}</p>
                                    </div>
                                    <p class="shrink-0 font-semibold text-stone-950">{{ formatCurrency(record.amount) }}</p>
                                </div>
                            </article>
                            <p v-if="scheduledRecords.length === 0" class="rounded-[18px] border border-stone-200 bg-stone-50 p-3 text-sm text-stone-600">
                                No scheduled transfers are waiting.
                            </p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div class="border-b border-stone-300/70 pb-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.32em] text-stone-500">Saved pay methods</p>
                <p class="text-sm text-stone-600">Compact payroll setup by employee. This should stay small unless the operation truly needs another human.</p>
            </div>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="member in staff" :key="member.id" class="rounded-[24px] border border-stone-300/70 bg-white/82 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-stone-950">{{ member.name }}</p>
                            <p class="mt-1 truncate text-xs text-stone-500">{{ member.role_label ?? 'Staff' }} / {{ member.email }}</p>
                        </div>
                        <span class="shrink-0 rounded-full border border-stone-200 bg-stone-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-600">{{ member.pay_currency ?? 'USD' }}</span>
                    </div>
                    <dl class="mt-3 grid gap-2 text-xs text-stone-600">
                        <div class="flex justify-between gap-4">
                            <dt>Method</dt>
                            <dd class="min-w-0 truncate text-right font-medium text-stone-900">{{ member.pay_method || 'Not saved' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt>Destination</dt>
                            <dd class="min-w-0 truncate text-right font-medium text-stone-900">{{ member.pay_destination || 'Not saved' }}</dd>
                        </div>
                    </dl>
                    <p v-if="member.payroll_notes" class="mt-3 line-clamp-2 text-xs leading-5 text-stone-500">{{ member.payroll_notes }}</p>
                </article>
            </div>
        </section>

        <section class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-stone-300/70 pb-3">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.32em] text-stone-500">Payroll ledger</p>
                    <p class="text-sm text-stone-600">Latest records, references, transfer status, and confirmation notes.</p>
                </div>
                <p class="text-sm font-semibold text-stone-950">{{ formatCurrency(ledgerTotal) }} visible ledger total</p>
            </div>
            <div class="grid gap-3">
                <article v-for="record in recentRecords" :key="record.id" class="grid gap-3 rounded-[24px] border border-stone-300/70 bg-white/82 p-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(160px,0.45fr)_minmax(0,0.85fr)_140px]">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-stone-950">{{ record.employee_name }}</p>
                        <p class="mt-1 text-xs text-stone-500">{{ periodLabel(record) }}</p>
                        <p v-if="record.notes" class="mt-2 line-clamp-2 text-sm leading-6 text-stone-600">{{ record.notes }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-400">Amount</p>
                        <p class="mt-1 font-semibold text-stone-950">{{ formatCurrency(record.amount) }} {{ record.currency }}</p>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-400">Method / reference</p>
                        <p class="mt-1 truncate text-sm text-stone-900">{{ record.pay_method || 'Not saved' }}</p>
                        <p class="mt-1 truncate text-xs text-stone-500">{{ record.reference || record.pay_destination || 'No reference saved' }}</p>
                    </div>
                    <div class="lg:text-right">
                        <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" :class="statusTone(record.status)">{{ record.status }}</span>
                        <p v-if="record.paid_at" class="mt-2 text-xs text-stone-500">{{ formatDateTime(record.paid_at) }}</p>
                    </div>
                </article>
                <p v-if="records.length === 0" class="rounded-[24px] border border-dashed border-stone-300 bg-stone-50/70 p-5 text-sm text-stone-500">
                    No payroll records yet.
                </p>
            </div>
        </section>
    </div>
</template>
