<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
    faArrowsRotate,
    faBolt,
    faBrain,
    faChartLine,
    faCircleCheck,
    faCircleExclamation,
    faClipboardCheck,
    faClock,
    faFileSignature,
    faFolderOpen,
    faKeyboard,
    faListCheck,
    faMoneyCheckDollar,
    faPersonCircleCheck,
    faShieldHalved,
    faTriangleExclamation,
    faUsers,
} from '@fortawesome/free-solid-svg-icons';
import MultiLineTrendChart from '@/components/creditsoft/MultiLineTrendChart.vue';
import { formatDate, formatDateTime, formatNumber } from '@/lib/creditsoft';

type EmployeeProfile = {
    legal_name?: string | null;
    preferred_name?: string | null;
    phone?: string | null;
    emergency_contact_name?: string | null;
    emergency_contact_phone?: string | null;
    department?: string | null;
    title?: string | null;
    employment_type?: string | null;
    timezone?: string | null;
    onboarding_status?: string | null;
    onboarding_started_at?: string | null;
    onboarding_completed_at?: string | null;
    pay_method?: string | null;
    pay_destination?: string | null;
    pay_currency?: string | null;
    payroll_notes?: string | null;
};

type StaffMember = {
    id: number;
    name: string;
    email: string;
    gravatar_url: string;
    role_label?: string | null;
    roles: string[];
    manager_name?: string | null;
    first_seen_at?: string | null;
    last_seen_at?: string | null;
    assigned_clients: number;
    open_tasks: number;
    done_tasks: number;
    needs_setup?: boolean;
    setup_note?: string | null;
    source_owner_name?: string | null;
    activity_daily: Array<{
        date: string;
        audit: number;
        input: number;
        total: number;
    }>;
    activity: {
        total: number;
        api: number;
        ai: number;
        manual: number;
        score: number;
        active_minutes?: number;
        keypresses?: number;
        clicks?: number;
        mouse_moves?: number;
        scrolls?: number;
        focuses?: number;
        form_submits?: number;
    };
    profile?: EmployeeProfile | null;
};

type EmployeeReview = {
    id: number;
    employee_name?: string | null;
    reviewer_name?: string | null;
    review_type: string;
    title: string;
    body?: string | null;
    rating?: number | null;
    status: string;
    occurred_on?: string | null;
    due_on?: string | null;
    created_at?: string | null;
};

type WeeklyReport = {
    id: number;
    employee_name?: string | null;
    generated_by_name?: string | null;
    period_start?: string | null;
    period_end?: string | null;
    title: string;
    summary?: string | null;
    strengths: string[];
    risks: string[];
    coaching_notes?: string | null;
    next_week_focus: string[];
    ai_provider?: string | null;
    ai_model?: string | null;
    status: string;
    generated_at?: string | null;
};

type ActivityCharts = {
    timezone: string;
    daily: {
        date_keys?: string[];
        labels: string[];
        series: Array<{
            label: string;
            color: string;
            values: number[];
        }>;
    };
    hourly: {
        labels: string[];
        series: Array<{
            label: string;
            color: string;
            values: number[];
        }>;
    };
    windows: Array<{
        user_id: number;
        name: string;
        color: string;
        events: number;
        active_days: number;
        work_window: string;
        peak_hour: string;
        last_activity_at?: string | null;
    }>;
};

type ChecklistItem = {
    label: string;
    done: boolean;
    detail: string;
};

type ActivityCalendarCell = {
    date: string | null;
    label: string;
    audit: number;
    input: number;
    total: number;
    intensity: number;
    empty: boolean;
};

const props = defineProps<{
    can_manage_hr: boolean;
    summary: {
        staff: number;
        open_reviews: number;
        write_ups: number;
        onboarding_active: number;
        api_actions: number;
        ai_actions: number;
        manual_actions: number;
        active_minutes: number;
        keypresses: number;
        clicks: number;
    };
    staff: StaffMember[];
    activity_charts: ActivityCharts;
    current_week: {
        period_start: string;
        period_end: string;
    };
    weekly_reports: WeeklyReport[];
    reviews: EmployeeReview[];
    employee_options: Array<{
        id: number;
        name: string;
        email: string;
    }>;
}>();

const firstEmployeeId = computed(() => props.employee_options[0]?.id?.toString() ?? '');
const staffById = computed(() => new Map(props.staff.map((staff) => [staff.id, staff])));
const reviewCounts = computed(() => {
    const counts = new Map<string, { open: number; writeUps: number; total: number }>();

    props.reviews.forEach((review) => {
        const key = review.employee_name ?? '';
        const current = counts.get(key) ?? { open: 0, writeUps: 0, total: 0 };

        current.total += 1;
        current.open += review.status === 'open' ? 1 : 0;
        current.writeUps += review.review_type === 'write_up' ? 1 : 0;
        counts.set(key, current);
    });

    return counts;
});

const profileForm = useForm({
    user_id: firstEmployeeId.value,
    legal_name: '',
    preferred_name: '',
    phone: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    department: '',
    title: '',
    employment_type: '',
    timezone: '',
    onboarding_status: 'not_started',
    onboarding_started_at: '',
    onboarding_completed_at: '',
    pay_method: '',
    pay_destination: '',
    pay_currency: 'USD',
    payroll_notes: '',
});

const reviewForm = useForm({
    user_id: firstEmployeeId.value,
    review_type: 'onboarding',
    title: '',
    body: '',
    rating: '',
    status: 'open',
    occurred_on: new Date().toISOString().slice(0, 10),
    due_on: '',
});

const reportForm = useForm({
    user_id: firstEmployeeId.value,
    period_start: props.current_week.period_start,
});

const selectedEmployeeId = ref(firstEmployeeId.value);

const toDateInput = (value?: string | null) => value ? String(value).slice(0, 10) : '';

const syncProfileForm = () => {
    const selected = staffById.value.get(Number(profileForm.user_id));
    const profile = selected?.profile ?? {};

    selectedEmployeeId.value = profileForm.user_id;
    profileForm.legal_name = profile.legal_name ?? selected?.name ?? '';
    profileForm.preferred_name = profile.preferred_name ?? '';
    profileForm.phone = profile.phone ?? '';
    profileForm.emergency_contact_name = profile.emergency_contact_name ?? '';
    profileForm.emergency_contact_phone = profile.emergency_contact_phone ?? '';
    profileForm.department = profile.department ?? '';
    profileForm.title = profile.title ?? '';
    profileForm.employment_type = profile.employment_type ?? '';
    profileForm.timezone = profile.timezone ?? '';
    profileForm.onboarding_status = profile.onboarding_status ?? 'not_started';
    profileForm.onboarding_started_at = toDateInput(profile.onboarding_started_at);
    profileForm.onboarding_completed_at = toDateInput(profile.onboarding_completed_at);
    profileForm.pay_method = profile.pay_method ?? '';
    profileForm.pay_destination = profile.pay_destination ?? '';
    profileForm.pay_currency = profile.pay_currency ?? 'USD';
    profileForm.payroll_notes = profile.payroll_notes ?? '';
};

watch(() => profileForm.user_id, syncProfileForm, { immediate: true });
watch(firstEmployeeId, (value) => {
    if (!profileForm.user_id && value) {
        profileForm.user_id = value;
        reviewForm.user_id = value;
        reportForm.user_id = value;
        selectedEmployeeId.value = value;
    }
});

const selectEmployee = (id: number) => {
    const value = id.toString();

    profileForm.user_id = value;
    reviewForm.user_id = value;
    reportForm.user_id = value;
    selectedEmployeeId.value = value;
};

const selectedStaff = computed(() => staffById.value.get(Number(selectedEmployeeId.value)) ?? props.staff[0] ?? null);

const submitProfile = () => profileForm.post('/hr/profiles', {
    preserveScroll: true,
});

const submitReview = () => reviewForm.post('/hr/reviews', {
    preserveScroll: true,
    onSuccess: () => reviewForm.reset('title', 'body', 'rating', 'due_on'),
});

const submitWeeklyReport = () => reportForm.post('/hr/weekly-reports/generate', {
    preserveScroll: true,
});

const statusLabel = (value?: string | null) => (value || 'not_started').replaceAll('_', ' ');
const empty = (value?: string | null) => value?.trim() || 'Not saved';
const percent = (value: number) => `${Math.round(Math.max(0, Math.min(1, value)) * 100)}%`;

const statusTone = (value?: string | null) => {
    if (value === 'complete') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-800';
    }

    if (value === 'active' || value === 'invited') {
        return 'border-blue-200 bg-blue-50 text-blue-800';
    }

    if (value === 'paused') {
        return 'border-amber-200 bg-amber-50 text-amber-800';
    }

    return 'border-stone-200 bg-stone-100 text-stone-700';
};

const reviewTone = (review: EmployeeReview) => {
    if (review.review_type === 'write_up') {
        return 'border-red-200 bg-red-50 text-red-800';
    }

    if (review.review_type === 'coaching') {
        return 'border-amber-200 bg-amber-50 text-amber-800';
    }

    if (review.review_type === 'onboarding') {
        return 'border-blue-200 bg-blue-50 text-blue-800';
    }

    return 'border-stone-200 bg-stone-100 text-stone-700';
};

const checklistFor = (member: StaffMember): ChecklistItem[] => {
    const profile = member.profile;

    return [
        {
            label: 'Identity and contact',
            done: Boolean(profile?.legal_name && profile.phone),
            detail: profile?.legal_name ? `${profile.legal_name} / ${empty(profile.phone)}` : 'Legal name and phone needed',
        },
        {
            label: 'Role, manager, and lane',
            done: Boolean((profile?.department || member.role_label) && (profile?.title || member.role_label)),
            detail: `${profile?.department ?? 'Department pending'} / ${profile?.title ?? member.role_label ?? 'Title pending'}`,
        },
        {
            label: 'Payroll destination',
            done: Boolean(profile?.pay_method && profile.pay_destination && profile.pay_currency),
            detail: profile?.pay_method ? `${profile.pay_method} / ${profile.pay_currency ?? 'USD'}` : 'Pay method and destination needed',
        },
        {
            label: 'Emergency contact',
            done: Boolean(profile?.emergency_contact_name && profile.emergency_contact_phone),
            detail: profile?.emergency_contact_name ? `${profile.emergency_contact_name} / ${empty(profile.emergency_contact_phone)}` : 'Emergency contact needed',
        },
        {
            label: 'Workspace access',
            done: Boolean(member.last_seen_at || member.activity.total > 0 || (member.activity.active_minutes ?? 0) > 0),
            detail: member.last_seen_at ? `Last seen ${formatDateTime(member.last_seen_at)}` : 'No intranet activity recorded yet',
        },
        {
            label: 'HR sign-off',
            done: profile?.onboarding_status === 'complete',
            detail: profile?.onboarding_completed_at ? `Completed ${formatDate(profile.onboarding_completed_at)}` : 'Mark complete after documents and policy acknowledgments are handled',
        },
    ];
};

const readinessFor = (member: StaffMember) => {
    const checklist = checklistFor(member);
    const done = checklist.filter((item) => item.done).length;

    return {
        done,
        total: checklist.length,
        ratio: done / checklist.length,
    };
};

const nextChecklistItem = (member: StaffMember) =>
    checklistFor(member).find((item) => !item.done) ?? null;

const payrollReady = computed(() => props.staff.filter((member) => checklistFor(member)[2]?.done).length);
const complianceAttention = computed(() =>
    props.staff.filter((member) => {
        const checklist = checklistFor(member);

        return member.needs_setup || !checklist[0]?.done || !checklist[3]?.done || !checklist[5]?.done;
    }).length,
);
const aiAutomationCoverage = computed(() => {
    const automated = props.summary.api_actions + props.summary.ai_actions;
    const total = automated + props.summary.manual_actions;

    if (total <= 0) {
        return 0;
    }

    return Math.round((automated / total) * 100);
});

const onboardingQueue = computed(() =>
    props.staff
        .filter((member) => {
            const status = member.profile?.onboarding_status ?? 'not_started';

            return status !== 'complete' || readinessFor(member).ratio < 1;
        })
        .sort((a, b) => readinessFor(a).ratio - readinessFor(b).ratio)
        .slice(0, 8),
);

const attentionItems = computed(() => [
    {
        label: 'Human seats',
        value: formatNumber(props.summary.staff),
        detail: 'Keep the team small; AI and workflows should carry the repeat work',
        icon: faUsers,
        tone: 'text-stone-700',
    },
    {
        label: 'AI coverage',
        value: `${aiAutomationCoverage.value}%`,
        detail: 'API and AI actions compared with manual intranet work',
        icon: faBrain,
        tone: 'text-blue-700',
    },
    {
        label: 'Compliance attention',
        value: formatNumber(complianceAttention.value),
        detail: 'Missing profile, emergency, or sign-off details',
        icon: faShieldHalved,
        tone: 'text-amber-700',
    },
    {
        label: 'Payroll ready',
        value: formatNumber(payrollReady.value),
        detail: 'Employees with a saved payment route',
        icon: faMoneyCheckDollar,
        tone: 'text-emerald-700',
    },
    {
        label: 'Open HR notes',
        value: formatNumber(props.summary.open_reviews),
        detail: 'Reviews, coaching, or onboarding notes still open',
        icon: faFileSignature,
        tone: 'text-red-700',
    },
]);

const selectedChecklist = computed(() => selectedStaff.value ? checklistFor(selectedStaff.value) : []);
const selectedReadiness = computed(() => selectedStaff.value ? readinessFor(selectedStaff.value) : { done: 0, total: 1, ratio: 0 });
const topStaff = computed(() => props.staff.slice(0, 10));
const latestReports = computed(() => props.weekly_reports.slice(0, 4));
const weekDayLabels = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
const peakWindows = computed(() =>
    [...props.activity_charts.windows]
        .sort((a, b) => b.events - a.events)
        .slice(0, 4),
);
const activityWindowByUser = computed(() =>
    new Map(props.activity_charts.windows.map((window) => [window.user_id, window])),
);
const memberActivityColor = (member: StaffMember) =>
    activityWindowByUser.value.get(member.id)?.color ?? '#d97706';
const withAlphaColor = (hex: string, alpha: number) => {
    if (!hex.startsWith('#') || hex.length !== 7) {
        return hex;
    }

    const red = Number.parseInt(hex.slice(1, 3), 16);
    const green = Number.parseInt(hex.slice(3, 5), 16);
    const blue = Number.parseInt(hex.slice(5, 7), 16);

    return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
};
const dateKey = (date: Date) => date.toISOString().slice(0, 10);
const localDate = (value: string) => new Date(`${value}T12:00:00`);
const activityWeeksFor = (member: StaffMember): ActivityCalendarCell[][] => {
    const days = [...member.activity_daily].sort((a, b) => a.date.localeCompare(b.date));

    if (days.length === 0) {
        return [];
    }

    const byDate = new Map(days.map((day) => [day.date, day]));
    const firstDate = localDate(days[0].date);
    const lastDate = localDate(days[days.length - 1].date);
    const startDate = new Date(firstDate);
    const endDate = new Date(lastDate);
    const maxTotal = Math.max(...days.map((day) => day.total), 1);

    startDate.setDate(startDate.getDate() - startDate.getDay());
    endDate.setDate(endDate.getDate() + (6 - endDate.getDay()));

    const cells: ActivityCalendarCell[] = [];
    const cursor = new Date(startDate);

    while (cursor <= endDate) {
        const key = dateKey(cursor);
        const day = byDate.get(key);

        cells.push({
            date: day?.date ?? null,
            label: key,
            audit: day?.audit ?? 0,
            input: day?.input ?? 0,
            total: day?.total ?? 0,
            intensity: day ? day.total / maxTotal : 0,
            empty: !day,
        });

        cursor.setDate(cursor.getDate() + 1);
    }

    const weeks: ActivityCalendarCell[][] = [];

    for (let index = 0; index < cells.length; index += 7) {
        weeks.push(cells.slice(index, index + 7));
    }

    return weeks;
};
const activityCellStyle = (member: StaffMember, cell: ActivityCalendarCell) => ({
    backgroundColor: cell.empty
        ? 'rgba(231, 229, 228, 0.58)'
        : withAlphaColor(memberActivityColor(member), cell.total > 0 ? 0.18 + cell.intensity * 0.72 : 0.08),
});
const activityCellTitle = (cell: ActivityCalendarCell) =>
    cell.date
        ? `${formatDate(cell.date)}: ${formatNumber(cell.total)} events (${formatNumber(cell.audit)} audit / ${formatNumber(cell.input)} captures)`
        : 'Outside the tracked 30-day window';

const employeeReviewCounts = (member: StaffMember) => reviewCounts.value.get(member.name) ?? { open: 0, writeUps: 0, total: 0 };
</script>

<template>
    <Head title="HR Command Center" />

    <h1 class="sr-only">HR Command Center</h1>

    <div class="space-y-7">
        <section class="space-y-5 border-b border-stone-300/70 pb-5">
            <div class="flex flex-wrap items-end justify-between gap-5">
                <div class="max-w-5xl">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.34em] text-stone-500">Operations / HR</p>
                    <h2 class="mt-2 text-3xl font-semibold tracking-tight text-stone-950 md:text-4xl">
                        Lean HR command center for small teams, AI-heavy operations, compliance, and payroll readiness.
                    </h2>
                    <p class="mt-3 max-w-4xl text-sm leading-6 text-stone-600">
                        CreditSoft should not need a large back office. This lane keeps the few real people organized while AI, companion captures, imports, API checks, and automated workflows do the heavy lifting.
                    </p>
                </div>
                <Link href="/payroll" class="inline-flex h-10 shrink-0 items-center gap-2 rounded-full border border-stone-300 bg-white px-4 text-sm font-medium text-stone-800 transition hover:border-stone-900">
                    <FontAwesomeIcon :icon="faMoneyCheckDollar" />
                    Payroll
                </Link>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <article
                    v-for="item in attentionItems"
                    :key="item.label"
                    class="min-h-24 rounded-[22px] border border-stone-300/70 bg-white/82 p-4"
                >
                    <div class="flex items-start justify-between gap-4">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-stone-500">{{ item.label }}</p>
                        <FontAwesomeIcon :icon="item.icon" class="text-lg" :class="item.tone" />
                    </div>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-stone-950">{{ item.value }}</p>
                    <p class="mt-1 text-xs leading-5 text-stone-500">{{ item.detail }}</p>
                </article>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1.25fr)_minmax(360px,0.75fr)]">
            <div class="space-y-4">
                <div class="flex flex-wrap items-end justify-between gap-3 border-b border-stone-300/70 pb-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.32em] text-stone-500">Staff setup queue</p>
                        <p class="text-sm text-stone-600">Access, payroll, emergency contact, and HR sign-off for the people who actually work in the office.</p>
                    </div>
                    <span class="rounded-full border border-stone-300 bg-white px-3 py-1 text-xs font-medium text-stone-700">
                        {{ onboardingQueue.length }} records need review
                    </span>
                </div>

                <div class="grid max-h-[760px] gap-3 overflow-y-auto pr-1 lg:grid-cols-2">
                    <article
                        v-for="member in onboardingQueue"
                        :key="`onboarding-${member.id}`"
                        class="rounded-[22px] border border-stone-300/70 bg-white/82 p-3 transition hover:border-stone-500"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <button type="button" class="flex min-w-0 items-center gap-3 text-left" @click="selectEmployee(member.id)">
                                <img :src="member.gravatar_url" :alt="member.name" class="size-10 rounded-full border border-stone-200 bg-white" />
                                <span class="min-w-0">
                                    <span class="block font-semibold text-stone-950">{{ member.name }}</span>
                                    <span class="block truncate text-xs text-stone-500">{{ member.profile?.title ?? member.role_label ?? 'Staff' }} / {{ member.email }}</span>
                                </span>
                            </button>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em]" :class="statusTone(member.profile?.onboarding_status)">
                                    {{ statusLabel(member.profile?.onboarding_status) }}
                                </span>
                                <span v-if="member.needs_setup" class="rounded-full border border-red-200 bg-red-50 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-red-800">
                                    Setup needed
                                </span>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="flex items-center justify-between text-xs text-stone-500">
                                <span>{{ readinessFor(member).done }} of {{ readinessFor(member).total }} onboarding controls complete</span>
                                <span class="font-semibold text-stone-900">{{ percent(readinessFor(member).ratio) }}</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-stone-200">
                                <div class="h-full rounded-full bg-stone-950" :style="{ width: percent(readinessFor(member).ratio) }" />
                            </div>
                        </div>

                        <div class="mt-3 rounded-2xl border border-stone-200 bg-stone-50/80 p-3 text-xs leading-5">
                            <p class="font-semibold text-stone-900">
                                {{ nextChecklistItem(member)?.label ?? 'Ready for HR sign-off' }}
                            </p>
                            <p class="text-stone-500">
                                {{ nextChecklistItem(member)?.detail ?? 'All setup controls are complete.' }}
                            </p>
                        </div>
                    </article>

                    <p v-if="onboardingQueue.length === 0" class="rounded-[24px] border border-dashed border-stone-300 bg-stone-50/70 p-5 text-sm text-stone-500">
                        No open onboarding cases. New imported source owners or invited staff will appear here automatically.
                    </p>
                </div>
            </div>

            <div class="space-y-4">
                <div class="border-b border-stone-300/70 pb-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.32em] text-stone-500">Employee 360</p>
                    <p class="text-sm text-stone-600">One employee file for HR, payroll, manager context, activity captures, and weekly reports.</p>
                </div>

                <article v-if="selectedStaff" class="rounded-[28px] border border-stone-300/70 bg-white/84 p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <img :src="selectedStaff.gravatar_url" :alt="selectedStaff.name" class="size-14 rounded-full border border-stone-200 bg-white" />
                            <div>
                                <p class="text-lg font-semibold text-stone-950">{{ selectedStaff.name }}</p>
                                <p class="text-sm text-stone-500">{{ selectedStaff.profile?.title ?? selectedStaff.role_label ?? 'Staff' }}</p>
                                <p class="text-xs text-stone-500">{{ selectedStaff.email }}</p>
                            </div>
                        </div>
                        <div class="min-w-32 text-right">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-stone-500">Readiness</p>
                            <p class="text-3xl font-semibold tracking-tight text-stone-950">{{ percent(selectedReadiness.ratio) }}</p>
                        </div>
                    </div>

                    <div class="mt-5 h-2 overflow-hidden rounded-full bg-stone-200">
                        <div class="h-full rounded-full bg-stone-950" :style="{ width: percent(selectedReadiness.ratio) }" />
                    </div>

                    <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                        <div class="border-b border-stone-200 pb-3">
                            <dt class="text-xs uppercase tracking-[0.18em] text-stone-500">Manager</dt>
                            <dd class="mt-1 font-medium text-stone-950">{{ empty(selectedStaff.manager_name) }}</dd>
                        </div>
                        <div class="border-b border-stone-200 pb-3">
                            <dt class="text-xs uppercase tracking-[0.18em] text-stone-500">Department</dt>
                            <dd class="mt-1 font-medium text-stone-950">{{ empty(selectedStaff.profile?.department) }}</dd>
                        </div>
                        <div class="border-b border-stone-200 pb-3">
                            <dt class="text-xs uppercase tracking-[0.18em] text-stone-500">Payroll</dt>
                            <dd class="mt-1 font-medium text-stone-950">{{ empty(selectedStaff.profile?.pay_method) }} / {{ selectedStaff.profile?.pay_currency ?? 'USD' }}</dd>
                        </div>
                        <div class="border-b border-stone-200 pb-3">
                            <dt class="text-xs uppercase tracking-[0.18em] text-stone-500">Last seen</dt>
                            <dd class="mt-1 font-medium text-stone-950">{{ selectedStaff.last_seen_at ? formatDateTime(selectedStaff.last_seen_at) : 'Never' }}</dd>
                        </div>
                    </dl>

                    <div class="mt-5 grid gap-2">
                        <div v-for="item in selectedChecklist" :key="`selected-${item.label}`" class="flex items-start gap-3 rounded-2xl border border-stone-200 bg-stone-50/70 p-3">
                            <FontAwesomeIcon :icon="item.done ? faCircleCheck : faCircleExclamation" class="mt-0.5" :class="item.done ? 'text-emerald-700' : 'text-amber-700'" />
                            <div>
                                <p class="text-sm font-medium text-stone-950">{{ item.label }}</p>
                                <p class="text-xs leading-5 text-stone-500">{{ item.detail }}</p>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[1.05fr_0.95fr]">
            <div class="space-y-4">
                <div class="border-b border-stone-300/70 pb-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.32em] text-stone-500">Employee file</p>
                    <p class="text-sm text-stone-600">Structured record for onboarding, emergency contact, job lane, pay destination, and HR notes.</p>
                </div>

                <form v-if="can_manage_hr" class="space-y-4 rounded-[28px] border border-stone-300/70 bg-white/82 p-5" @submit.prevent="submitProfile">
                    <label class="block space-y-1">
                        <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-stone-500">Employee</span>
                        <select v-model="profileForm.user_id" class="h-11 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm">
                            <option v-for="employee in employee_options" :key="employee.id" :value="employee.id.toString()">{{ employee.name }} / {{ employee.email }}</option>
                        </select>
                    </label>

                    <div class="grid gap-3 md:grid-cols-2">
                        <input v-model="profileForm.legal_name" type="text" class="h-11 rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="Legal name" />
                        <input v-model="profileForm.preferred_name" type="text" class="h-11 rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="Preferred name" />
                        <input v-model="profileForm.phone" type="text" class="h-11 rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="Phone" />
                        <input v-model="profileForm.timezone" type="text" class="h-11 rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="Timezone" />
                        <input v-model="profileForm.department" type="text" class="h-11 rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="Department" />
                        <input v-model="profileForm.title" type="text" class="h-11 rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="Title" />
                        <input v-model="profileForm.employment_type" type="text" class="h-11 rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="Employment type" />
                        <select v-model="profileForm.onboarding_status" class="h-11 rounded-xl border border-stone-300 bg-white px-3 text-sm">
                            <option value="not_started">Onboarding not started</option>
                            <option value="invited">Invited</option>
                            <option value="active">Active onboarding</option>
                            <option value="complete">Complete</option>
                            <option value="paused">Paused</option>
                        </select>
                        <input v-model="profileForm.onboarding_started_at" type="date" class="h-11 rounded-xl border border-stone-300 bg-white px-3 text-sm" />
                        <input v-model="profileForm.onboarding_completed_at" type="date" class="h-11 rounded-xl border border-stone-300 bg-white px-3 text-sm" />
                        <input v-model="profileForm.emergency_contact_name" type="text" class="h-11 rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="Emergency contact" />
                        <input v-model="profileForm.emergency_contact_phone" type="text" class="h-11 rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="Emergency phone" />
                        <input v-model="profileForm.pay_method" type="text" class="h-11 rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="Pay method" />
                        <input v-model="profileForm.pay_destination" type="text" class="h-11 rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="Pay destination" />
                    </div>

                    <div class="grid gap-3 md:grid-cols-[120px_1fr]">
                        <input v-model="profileForm.pay_currency" type="text" maxlength="3" class="h-11 rounded-xl border border-stone-300 bg-white px-3 text-sm uppercase" placeholder="USD" />
                        <textarea v-model="profileForm.payroll_notes" rows="3" class="w-full rounded-2xl border border-stone-300 bg-white px-3 py-2 text-sm" placeholder="Payroll notes, tax paperwork reminders, policy acknowledgments, or payment constraints." />
                    </div>

                    <button type="submit" class="inline-flex h-11 items-center gap-2 rounded-full bg-stone-950 px-5 text-sm font-medium text-white transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="profileForm.processing">
                        <FontAwesomeIcon :icon="faFolderOpen" />
                        Save employee file
                    </button>
                </form>
                <p v-else class="rounded-[24px] border border-stone-300/70 bg-stone-50/70 p-4 text-sm text-stone-600">
                    Managers can view their lane and add HR notes. Owner/admin access is required to edit private contact or pay-method fields.
                </p>
            </div>

            <div class="space-y-4">
                <div class="border-b border-stone-300/70 pb-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.32em] text-stone-500">HR action desk</p>
                    <p class="text-sm text-stone-600">Create onboarding notes, coaching entries, write-ups, reviews, and AI-assisted weekly reports.</p>
                </div>

                <form class="space-y-3 rounded-[28px] border border-stone-300/70 bg-white/82 p-5" @submit.prevent="submitReview">
                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="space-y-1">
                            <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-stone-500">Employee</span>
                            <select v-model="reviewForm.user_id" class="h-11 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm">
                                <option v-for="employee in employee_options" :key="employee.id" :value="employee.id.toString()">{{ employee.name }}</option>
                            </select>
                        </label>
                        <label class="space-y-1">
                            <span class="text-[11px] font-semibold uppercase tracking-[0.2em] text-stone-500">Entry type</span>
                            <select v-model="reviewForm.review_type" class="h-11 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm">
                                <option value="onboarding">Onboarding</option>
                                <option value="review">Review</option>
                                <option value="coaching">Coaching</option>
                                <option value="write_up">Write-up</option>
                            </select>
                        </label>
                    </div>
                    <input v-model="reviewForm.title" type="text" class="h-11 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="Title" />
                    <textarea v-model="reviewForm.body" rows="5" class="w-full rounded-2xl border border-stone-300 bg-white px-3 py-2 text-sm" placeholder="What changed, what was acknowledged, what needs follow-up, or what the employee needs next?" />
                    <div class="grid gap-3 md:grid-cols-3">
                        <input v-model="reviewForm.rating" type="number" min="1" max="5" class="h-11 rounded-xl border border-stone-300 bg-white px-3 text-sm" placeholder="Rating 1-5" />
                        <input v-model="reviewForm.occurred_on" type="date" class="h-11 rounded-xl border border-stone-300 bg-white px-3 text-sm" />
                        <select v-model="reviewForm.status" class="h-11 rounded-xl border border-stone-300 bg-white px-3 text-sm">
                            <option value="open">Open</option>
                            <option value="acknowledged">Acknowledged</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <button type="submit" class="inline-flex h-11 items-center gap-2 rounded-full bg-stone-950 px-5 text-sm font-medium text-white transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-60" :disabled="reviewForm.processing">
                        <FontAwesomeIcon :icon="faFileSignature" />
                        Save HR entry
                    </button>
                </form>

                <form class="rounded-[28px] border border-stone-300/70 bg-stone-50/78 p-5" @submit.prevent="submitWeeklyReport">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-stone-500">Weekly AI report</p>
                            <p class="mt-1 text-sm leading-6 text-stone-600">Uses task status, audit activity, HR notes, and aggregate activity captures only.</p>
                        </div>
                        <FontAwesomeIcon :icon="faBrain" class="text-xl text-stone-500" />
                    </div>
                    <div class="mt-4 grid gap-3 md:grid-cols-[1fr_160px]">
                        <select v-model="reportForm.user_id" class="h-11 rounded-xl border border-stone-300 bg-white px-3 text-sm">
                            <option v-for="employee in employee_options" :key="employee.id" :value="employee.id.toString()">{{ employee.name }}</option>
                        </select>
                        <input v-model="reportForm.period_start" type="date" class="h-11 rounded-xl border border-stone-300 bg-white px-3 text-sm" />
                    </div>
                    <button type="submit" class="mt-4 inline-flex h-11 items-center gap-2 rounded-full border border-stone-300 bg-white px-5 text-sm font-medium text-stone-900 transition hover:border-stone-950 disabled:cursor-not-allowed disabled:opacity-60" :disabled="reportForm.processing">
                        <FontAwesomeIcon :icon="faArrowsRotate" />
                        Generate report
                    </button>
                </form>
            </div>
        </section>

        <section class="space-y-5">
            <div class="space-y-4">
                <div class="flex items-end justify-between border-b border-stone-300/70 pb-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.32em] text-stone-500">Workforce performance</p>
                        <p class="text-sm text-stone-600">Real intranet activity by employee. Each row shows the last 30 days Sunday through Saturday, week by week.</p>
                    </div>
                    <div class="hidden items-center gap-4 text-xs text-stone-500 md:flex">
                        <span><FontAwesomeIcon :icon="faBolt" class="mr-1 text-stone-700" /> API {{ formatNumber(summary.api_actions) }}</span>
                        <span><FontAwesomeIcon :icon="faBrain" class="mr-1 text-stone-700" /> AI {{ formatNumber(summary.ai_actions) }}</span>
                        <span><FontAwesomeIcon :icon="faKeyboard" class="mr-1 text-stone-700" /> Manual {{ formatNumber(summary.manual_actions) }}</span>
                    </div>
                </div>

                <div class="grid gap-3">
                    <article
                        v-for="member in topStaff"
                        :key="member.id"
                        class="rounded-[24px] border border-stone-300/70 bg-white/82 p-4"
                    >
                        <div class="grid gap-4 xl:grid-cols-[minmax(210px,0.85fr)_120px_minmax(240px,1fr)_minmax(240px,1fr)]">
                            <button type="button" class="flex min-w-0 items-center gap-3 text-left" @click="selectEmployee(member.id)">
                                <img :src="member.gravatar_url" :alt="member.name" class="size-10 rounded-full border border-stone-200 bg-white" />
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-stone-950">{{ member.name }}</span>
                                    <span class="block truncate text-xs text-stone-500">{{ member.profile?.title ?? member.role_label ?? 'Staff' }}</span>
                                </span>
                            </button>

                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-stone-400">Score</p>
                                <p class="mt-1 text-2xl font-semibold text-stone-950">{{ formatNumber(member.activity.score) }}</p>
                            </div>

                            <div class="text-sm text-stone-600">
                                <p class="font-medium text-stone-900">
                                    {{ formatNumber(member.activity.api) }} API / {{ formatNumber(member.activity.ai) }} AI / {{ formatNumber(member.activity.manual) }} manual
                                </p>
                                <p class="mt-1 text-xs text-stone-500">{{ formatNumber(member.activity.active_minutes ?? 0) }} active min / {{ formatNumber(member.activity.clicks ?? 0) }} clicks</p>
                            </div>

                            <div class="text-sm text-stone-600">
                                <p class="font-medium text-stone-900">
                                    {{ formatNumber(member.assigned_clients) }} clients / {{ formatNumber(member.open_tasks) }} open / {{ formatNumber(member.done_tasks) }} done
                                </p>
                                <p class="mt-1 text-xs text-stone-500">
                                    {{ employeeReviewCounts(member).open }} open HR / {{ employeeReviewCounts(member).writeUps }} write-ups · {{ member.last_seen_at ? formatDateTime(member.last_seen_at) : 'Never seen' }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 border-t border-stone-200 pt-3">
                            <div class="mb-2 flex items-center justify-between gap-3 text-[10px] font-semibold uppercase tracking-[0.18em] text-stone-400">
                                <span>30-day activity</span>
                                <span>{{ formatNumber(member.activity_daily.reduce((sum, day) => sum + day.total, 0)) }} events</span>
                            </div>
                            <div class="grid grid-cols-7 gap-1 text-center text-[9px] font-semibold text-stone-400">
                                <span v-for="day in weekDayLabels" :key="`label-${member.id}-${day}`">{{ day }}</span>
                            </div>
                            <div class="mt-1 space-y-1">
                                <div
                                    v-for="(week, weekIndex) in activityWeeksFor(member)"
                                    :key="`${member.id}-week-${weekIndex}`"
                                    class="grid grid-cols-7 gap-1"
                                >
                                    <span
                                        v-for="cell in week"
                                        :key="`${member.id}-${weekIndex}-${cell.label}`"
                                        class="block h-1.5 rounded-full"
                                        :class="cell.total > 0 ? '' : 'opacity-70'"
                                        :title="activityCellTitle(cell)"
                                        :style="activityCellStyle(member, cell)"
                                    />
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

            <div class="space-y-4">
                <div class="border-b border-stone-300/70 pb-3">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.32em] text-stone-500">Latest weekly reports</p>
                    <p class="text-sm text-stone-600">Manager-ready summaries with strengths, risks, and next-week focus.</p>
                </div>
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <article v-for="report in latestReports" :key="report.id" class="rounded-[24px] border border-stone-300/70 bg-white/82 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-stone-950">{{ report.employee_name }}</p>
                                <p class="text-xs uppercase tracking-[0.18em] text-stone-500">{{ report.period_start ? formatDate(report.period_start) : 'Week' }} - {{ report.period_end ? formatDate(report.period_end) : 'current' }}</p>
                            </div>
                            <span class="rounded-full border border-stone-200 bg-stone-100 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-700">{{ report.status }}</span>
                        </div>
                        <p class="mt-3 text-sm font-medium text-stone-950">{{ report.title }}</p>
                        <p v-if="report.summary" class="mt-1 text-sm leading-6 text-stone-600">{{ report.summary }}</p>
                        <ul v-if="report.next_week_focus?.length" class="mt-3 space-y-1 text-xs text-stone-600">
                            <li v-for="focus in report.next_week_focus.slice(0, 2)" :key="focus" class="flex gap-2">
                                <FontAwesomeIcon :icon="faListCheck" class="mt-0.5 text-stone-500" />
                                <span>{{ focus }}</span>
                            </li>
                        </ul>
                    </article>
                    <p v-if="latestReports.length === 0" class="rounded-[24px] border border-dashed border-stone-300 bg-stone-50/70 p-5 text-sm text-stone-500">
                        No weekly reports yet. Generate one from the HR action desk.
                    </p>
                </div>
            </div>
        </section>

        <section class="space-y-5">
            <div class="min-w-0 space-y-4">
                <div class="flex items-end justify-between border-b border-stone-300/70 pb-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.32em] text-stone-500">Activity timeline</p>
                        <p class="text-sm text-stone-600">Daily employee activity over the last 30 days. Each color is one staff member.</p>
                    </div>
                    <FontAwesomeIcon :icon="faChartLine" class="hidden text-xl text-amber-600 md:block" />
                </div>
                <div class="min-w-0 rounded-[28px] border border-stone-300/70 bg-white/82 p-4">
                    <MultiLineTrendChart
                        :labels="activity_charts.daily.labels"
                        :series="activity_charts.daily.series"
                        :height="360"
                        :point-radius="2"
                    />
                </div>
            </div>

            <div class="min-w-0 space-y-4">
                <div class="flex items-end justify-between border-b border-stone-300/70 pb-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.32em] text-stone-500">Work hours</p>
                        <p class="text-sm text-stone-600">Hourly activity pattern from the last 30 days in {{ activity_charts.timezone }}.</p>
                    </div>
                    <FontAwesomeIcon :icon="faClock" class="hidden text-xl text-stone-500 md:block" />
                </div>

                <div class="space-y-3">
                    <div class="min-w-0 rounded-[28px] border border-stone-300/70 bg-white/82 p-4">
                        <MultiLineTrendChart
                            :labels="activity_charts.hourly.labels"
                            :series="activity_charts.hourly.series"
                            :height="300"
                            :point-radius="1"
                        />
                    </div>

                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <article v-for="window in peakWindows" :key="window.user_id" class="rounded-[22px] border border-stone-300/70 bg-white/82 p-4 text-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-stone-950">{{ window.name }}</p>
                                    <p class="mt-1 text-xs text-stone-500">{{ window.work_window }}</p>
                                </div>
                                <span class="shrink-0 rounded-full border border-stone-200 bg-stone-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-700">
                                    peak {{ window.peak_hour }}
                                </span>
                            </div>
                            <p class="mt-3 text-xs leading-5 text-stone-500">
                                {{ formatNumber(window.events) }} events across {{ formatNumber(window.active_days) }} active days
                            </p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div class="border-b border-stone-300/70 pb-3">
                <p class="text-[11px] font-semibold uppercase tracking-[0.32em] text-stone-500">Review history</p>
                <p class="text-sm text-stone-600">Latest reviews, onboarding notes, coaching entries, and write-ups.</p>
            </div>
            <div class="grid gap-3">
                <article v-for="review in reviews" :key="review.id" class="grid gap-3 rounded-[24px] border border-stone-300/70 bg-white/82 p-4 md:grid-cols-[170px_1fr_160px]">
                    <div class="text-sm">
                        <p class="font-semibold text-stone-950">{{ review.employee_name }}</p>
                        <span class="mt-2 inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" :class="reviewTone(review)">
                            {{ review.review_type.replaceAll('_', ' ') }}
                        </span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <FontAwesomeIcon :icon="review.review_type === 'write_up' ? faTriangleExclamation : review.review_type === 'onboarding' ? faClipboardCheck : faFileSignature" class="text-stone-500" />
                            <p class="font-medium text-stone-950">{{ review.title }}</p>
                        </div>
                        <p v-if="review.body" class="mt-1 text-sm leading-6 text-stone-600">{{ review.body }}</p>
                    </div>
                    <div class="text-xs text-stone-500 md:text-right">
                        <p>{{ review.status }}</p>
                        <p v-if="review.occurred_on">{{ formatDate(review.occurred_on) }}</p>
                        <p v-if="review.reviewer_name">By {{ review.reviewer_name }}</p>
                    </div>
                </article>
                <p v-if="reviews.length === 0" class="rounded-[24px] border border-dashed border-stone-300 bg-stone-50/70 p-5 text-sm text-stone-500">
                    No HR entries yet.
                </p>
            </div>
        </section>
    </div>
</template>
