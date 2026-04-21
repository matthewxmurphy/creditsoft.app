<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
    faArrowUpRightFromSquare,
    faMoneyCheckDollar,
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
    if (status === 'confirmed') return 'bg-emerald-100 text-emerald-800';
    if (status === 'sent') return 'bg-blue-100 text-blue-800';
    if (status === 'scheduled') return 'bg-amber-100 text-amber-800';

    return 'bg-stone-200 text-stone-700';
};
</script>

<template>
    <Head title="Payroll" />

    <h1 class="sr-only">Payroll</h1>

    <div class="space-y-7">
        <section class="flex flex-wrap items-end justify-between gap-4 border-b border-stone-300/70 pb-4">
            <div class="max-w-3xl">
                <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Payroll</p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight text-stone-950">Pay methods, overseas transfers, and payroll history.</h2>
                <p class="mt-2 text-sm leading-6 text-stone-600">
                    Keep the office payroll trail clean: who gets paid, how they get paid, transfer references, notes, and confirmation state.
                </p>
            </div>
            <div class="flex items-center gap-3 text-sm text-stone-600">
                <FontAwesomeIcon :icon="faMoneyCheckDollar" class="text-xl text-emerald-700" />
                <span>{{ formatNumber(summary.ready_methods) }} of {{ formatNumber(summary.staff) }} have pay methods</span>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <MetricTile label="Staff" :value="formatNumber(summary.staff)" />
            <MetricTile label="Pay methods ready" :value="formatNumber(summary.ready_methods)" />
            <MetricTile label="Scheduled" :value="formatCurrency(summary.scheduled)" />
            <MetricTile label="Sent in 30 days" :value="formatCurrency(summary.sent_30_days)" />
        </section>

        <section class="grid gap-5 xl:grid-cols-[0.9fr_1.1fr]">
            <div class="space-y-4">
                <div class="border-b border-stone-300/70 pb-3">
                    <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Overseas payroll</p>
                    <p class="text-sm text-stone-600">Referral info for staff who can use Sendwave in supported countries.</p>
                </div>
                <article class="rounded-[32px] border border-yellow-300/80 bg-[linear-gradient(135deg,_rgba(255,236,0,0.34),_rgba(255,251,235,0.92)_48%,_rgba(255,255,255,0.9))] p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-[0.3em] text-yellow-800">Sendwave</p>
                            <h3 class="mt-2 text-xl font-semibold text-stone-950">Use code {{ sendwave.code }} for {{ sendwave.credit }} credit.</h3>
                        </div>
                        <img src="/assets/vendor-logos/sendwave.svg" alt="Sendwave" class="h-12 w-12 shrink-0 object-contain" />
                    </div>
                    <p class="mt-3 text-sm leading-6 text-stone-700">{{ sendwave.copy }}</p>
                    <a :href="sendwave.url" target="_blank" rel="noopener" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-yellow-900 transition hover:text-stone-950">
                        Download Sendwave
                        <FontAwesomeIcon :icon="faArrowUpRightFromSquare" />
                    </a>
                </article>

                <div class="space-y-3">
                    <div class="flex items-center gap-2 border-b border-stone-300/70 pb-3">
                        <FontAwesomeIcon :icon="faUsers" class="text-stone-500" />
                        <p class="text-sm font-semibold text-stone-950">Saved pay methods</p>
                    </div>
                    <article v-for="member in staff" :key="member.id" class="rounded-[24px] border border-stone-300/70 bg-stone-50/76 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-semibold text-stone-950">{{ member.name }}</p>
                                <p class="text-xs text-stone-500">{{ member.role_label ?? 'Staff' }} · {{ member.email }}</p>
                            </div>
                            <span class="text-xs font-semibold uppercase tracking-[0.16em] text-stone-500">{{ member.pay_currency ?? 'USD' }}</span>
                        </div>
                        <dl class="mt-3 space-y-2 text-xs text-stone-600">
                            <div class="flex justify-between gap-4"><dt>Method</dt><dd class="text-right text-stone-900">{{ member.pay_method || 'Not saved' }}</dd></div>
                            <div class="flex justify-between gap-4"><dt>Destination</dt><dd class="text-right text-stone-900">{{ member.pay_destination || 'Not saved' }}</dd></div>
                        </dl>
                        <p v-if="member.payroll_notes" class="mt-3 text-xs leading-5 text-stone-500">{{ member.payroll_notes }}</p>
                    </article>
                </div>
            </div>

            <div class="space-y-4">
                <div class="border-b border-stone-300/70 pb-3">
                    <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Record payroll</p>
                    <p class="text-sm text-stone-600">Log a draft, scheduled transfer, sent payment, or confirmed payroll record.</p>
                </div>

                <form class="space-y-3 rounded-[28px] border border-stone-300/70 bg-white/76 p-4" @submit.prevent="submitPayroll">
                    <label class="space-y-1">
                        <span class="text-[11px] font-medium uppercase tracking-[0.2em] text-stone-500">Employee</span>
                        <select v-model="payrollForm.user_id" class="h-10 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm" @change="syncSelectedStaff">
                            <option v-for="member in staff" :key="member.id" :value="member.id.toString()">{{ member.name }} · {{ member.email }}</option>
                        </select>
                    </label>
                    <div class="grid gap-3 md:grid-cols-2">
                        <input v-model="payrollForm.period_start" type="date" class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm" />
                        <input v-model="payrollForm.period_end" type="date" class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm" />
                        <input v-model="payrollForm.amount" type="number" min="0.01" step="0.01" class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="Amount" />
                        <input v-model="payrollForm.currency" type="text" maxlength="3" class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm uppercase" placeholder="USD" />
                        <input v-model="payrollForm.pay_method" type="text" class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="Zelle, Cash App, Sendwave, ACH" />
                        <input v-model="payrollForm.pay_destination" type="text" class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="Email, phone, username, bank note" />
                        <input v-model="payrollForm.reference" type="text" class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="Transaction or confirmation number" />
                        <select v-model="payrollForm.status" class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm">
                            <option value="draft">Draft</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="sent">Sent</option>
                            <option value="confirmed">Confirmed</option>
                        </select>
                    </div>
                    <input v-model="payrollForm.paid_at" type="datetime-local" class="h-10 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm" />
                    <textarea v-model="payrollForm.notes" rows="3" class="w-full rounded-2xl border border-stone-300 bg-white px-3 py-2 text-sm" placeholder="Internal payroll note, sender, transfer restrictions, or follow-up." />
                    <button type="submit" class="inline-flex h-10 items-center rounded-full bg-stone-950 px-5 text-sm font-medium text-white transition hover:bg-stone-800" :disabled="payrollForm.processing">
                        Save payroll record
                    </button>
                </form>

                <div class="space-y-3">
                    <div class="border-b border-stone-300/70 pb-3">
                        <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Payroll ledger</p>
                    </div>
                    <article v-for="record in records" :key="record.id" class="rounded-[24px] border border-stone-300/70 bg-stone-50/76 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-stone-950">{{ record.employee_name }}</p>
                                <p class="text-xs text-stone-500">
                                    {{ record.period_start ? formatDate(record.period_start) : 'No period start' }}
                                    <span v-if="record.period_end"> - {{ formatDate(record.period_end) }}</span>
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-stone-950">{{ formatCurrency(record.amount) }} {{ record.currency }}</p>
                                <span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" :class="statusTone(record.status)">{{ record.status }}</span>
                            </div>
                        </div>
                        <dl class="mt-3 grid gap-2 text-xs text-stone-600 md:grid-cols-2">
                            <div><dt class="uppercase tracking-[0.16em] text-stone-400">Method</dt><dd class="mt-1 text-stone-900">{{ record.pay_method || 'Not saved' }}</dd></div>
                            <div><dt class="uppercase tracking-[0.16em] text-stone-400">Reference</dt><dd class="mt-1 text-stone-900">{{ record.reference || 'Not saved' }}</dd></div>
                        </dl>
                        <p v-if="record.paid_at" class="mt-3 text-xs text-stone-500">Paid {{ formatDateTime(record.paid_at) }}</p>
                        <p v-if="record.notes" class="mt-2 text-sm leading-6 text-stone-600">{{ record.notes }}</p>
                    </article>
                    <p v-if="records.length === 0" class="rounded-[24px] border border-dashed border-stone-300 bg-stone-50/70 p-5 text-sm text-stone-500">
                        No payroll records yet.
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
