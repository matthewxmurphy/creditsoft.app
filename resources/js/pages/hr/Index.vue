<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import {
    faBolt,
    faBrain,
    faChartLine,
    faClock,
    faClipboardCheck,
    faComputerMouse,
    faFileSignature,
    faGripVertical,
    faKeyboard,
    faPersonCircleCheck,
    faTableList,
    faTriangleExclamation,
    faUsers,
    faWandMagicSparkles,
} from '@fortawesome/free-solid-svg-icons';
import MetricTile from '@/components/creditsoft/MetricTile.vue';
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
        active_minutes: number;
        keypresses: number;
        clicks: number;
        mouse_moves: number;
        scrolls: number;
        focuses: number;
        form_submits: number;
        score: number;
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

type EmployeeWeeklyReport = {
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
    weekly_reports: EmployeeWeeklyReport[];
    current_week: {
        period_start: string;
        period_end: string;
    };
    reviews: EmployeeReview[];
    employee_options: Array<{
        id: number;
        name: string;
        email: string;
    }>;
}>();

const staffById = computed(
    () => new Map(props.staff.map((staff) => [staff.id, staff])),
);
const firstEmployeeId = computed(
    () => props.employee_options[0]?.id?.toString() ?? '',
);

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
    review_type: 'review',
    title: '',
    body: '',
    rating: '',
    status: 'open',
    occurred_on: new Date().toISOString().slice(0, 10),
    due_on: '',
});

const weeklyReportForm = useForm({
    user_id: firstEmployeeId.value,
    period_start: props.current_week.period_start,
});

const toDateInput = (value?: string | null) =>
    value ? String(value).slice(0, 10) : '';

const syncProfileForm = () => {
    const selected = staffById.value.get(Number(profileForm.user_id));
    const profile = selected?.profile ?? {};

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
    profileForm.onboarding_started_at = toDateInput(
        profile.onboarding_started_at,
    );
    profileForm.onboarding_completed_at = toDateInput(
        profile.onboarding_completed_at,
    );
    profileForm.pay_method = profile.pay_method ?? '';
    profileForm.pay_destination = profile.pay_destination ?? '';
    profileForm.pay_currency = profile.pay_currency ?? 'USD';
    profileForm.payroll_notes = profile.payroll_notes ?? '';
};

watch(() => profileForm.user_id, syncProfileForm, { immediate: true });

const submitProfile = () =>
    profileForm.post('/hr/profiles', {
        preserveScroll: true,
    });

const submitReview = () =>
    reviewForm.post('/hr/reviews', {
        preserveScroll: true,
        onSuccess: () => reviewForm.reset('title', 'body', 'rating', 'due_on'),
    });

const submitWeeklyReport = () =>
    weeklyReportForm.post('/hr/weekly-reports/generate', {
        preserveScroll: true,
    });

const statusLabel = (value?: string | null) =>
    (value || 'not_started').replaceAll('_', ' ');
const empty = (value?: string | null) => value?.trim() || 'Not saved';
const formatPeriod = (start?: string | null, end?: string | null) => {
    if (!start || !end) return 'No period';

    return `${formatDate(start)} - ${formatDate(end)}`;
};
const reportProviderLabel = (report: EmployeeWeeklyReport) =>
    report.status === 'local_fallback'
        ? 'Local draft'
        : (report.ai_provider ?? 'AI');
const reportProviderClass = (report: EmployeeWeeklyReport) =>
    report.status === 'local_fallback'
        ? 'bg-stone-100 text-stone-700'
        : 'bg-amber-100 text-amber-800';

const hrSectionKeys = [
    'reports',
    'activity',
    'activity-windows',
    'performance',
    'employee-records',
    'review-history',
] as const;
type HrSectionKey = (typeof hrSectionKeys)[number];

const hrSectionOrderStorageKey = 'creditsoft.hr.sections.order';
const hrSectionOrder = ref<HrSectionKey[]>([]);
const hrSectionDragKey = ref<HrSectionKey | null>(null);
const hrSectionDragOverKey = ref<HrSectionKey | null>(null);

const isHrSectionKey = (value: unknown): value is HrSectionKey =>
    typeof value === 'string' &&
    (hrSectionKeys as readonly string[]).includes(value);

const mergeHrSectionOrder = (source: HrSectionKey[] = []) => [
    ...source.filter((key) => hrSectionKeys.includes(key)),
    ...hrSectionKeys.filter((key) => !source.includes(key)),
];

const readHrSectionOrder = () => {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const stored = window.localStorage.getItem(hrSectionOrderStorageKey);
        const parsed = stored ? JSON.parse(stored) : [];

        return Array.isArray(parsed) ? parsed.filter(isHrSectionKey) : [];
    } catch {
        return [];
    }
};

const saveHrSectionOrder = () => {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(
        hrSectionOrderStorageKey,
        JSON.stringify(hrSectionOrder.value),
    );
};

onMounted(() => {
    hrSectionOrder.value = mergeHrSectionOrder(readHrSectionOrder());
});

const orderedHrSectionKeys = computed(() =>
    mergeHrSectionOrder(hrSectionOrder.value),
);

const hrSectionOrderIndex = (key: HrSectionKey) =>
    10 + orderedHrSectionKeys.value.indexOf(key);

const orderedPerformanceStaff = computed(() => {
    return props.staff.slice(0, 8);
});

const moveHrSection = (
    draggedKey: HrSectionKey | null,
    targetKey: HrSectionKey,
) => {
    if (!draggedKey || draggedKey === targetKey) {
        return;
    }

    const nextOrder = mergeHrSectionOrder(hrSectionOrder.value);
    const fromIndex = nextOrder.indexOf(draggedKey);
    const toIndex = nextOrder.indexOf(targetKey);

    if (fromIndex === -1 || toIndex === -1) {
        return;
    }

    const [moved] = nextOrder.splice(fromIndex, 1);
    nextOrder.splice(toIndex, 0, moved);
    hrSectionOrder.value = nextOrder;
    saveHrSectionOrder();
};

const onHrSectionDragStart = (event: DragEvent, key: HrSectionKey) => {
    hrSectionDragKey.value = key;
    event.dataTransfer?.setData('text/plain', key);

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
};

const onHrSectionDrop = (event: DragEvent, targetKey: HrSectionKey) => {
    const dataKey = event.dataTransfer?.getData('text/plain');
    const draggedKey = isHrSectionKey(dataKey)
        ? dataKey
        : hrSectionDragKey.value;

    moveHrSection(draggedKey, targetKey);
    hrSectionDragKey.value = null;
    hrSectionDragOverKey.value = null;
};

const hrSectionDragClass = (key: HrSectionKey) => [
    'transition-[opacity,transform,box-shadow] duration-150',
    hrSectionDragKey.value === key ? 'opacity-55' : '',
    hrSectionDragOverKey.value === key && hrSectionDragKey.value !== key
        ? 'rounded-[28px] ring-2 ring-amber-400/70 ring-offset-4 ring-offset-stone-50'
        : '',
];

const weekDayLabels = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
const isoDate = (date: Date) => date.toISOString().slice(0, 10);
const addDays = (date: Date, days: number) => {
    const next = new Date(date);
    next.setDate(next.getDate() + days);

    return next;
};
const startOfWeek = (date: Date) => {
    const start = new Date(date);
    const day = start.getDay();
    const diff = day === 0 ? -6 : 1 - day;
    start.setDate(start.getDate() + diff);
    start.setHours(0, 0, 0, 0);

    return start;
};
const latestActivityDate = computed(() => {
    const lastKey = props.activity_charts.daily.date_keys?.at(-1);
    const parsed = lastKey ? new Date(`${lastKey}T00:00:00`) : new Date();

    return Number.isNaN(parsed.getTime()) ? new Date() : parsed;
});
const maxDailyActivity = computed(() =>
    Math.max(
        1,
        ...props.staff.flatMap((member) =>
            (member.activity_daily ?? []).map((day) => Number(day.total) || 0),
        ),
    ),
);
const weekActivityFor = (member: StaffMember) => {
    const byDate = new Map(
        (member.activity_daily ?? []).map((day) => [
            day.date,
            Number(day.total) || 0,
        ]),
    );
    const currentMonday = startOfWeek(latestActivityDate.value);

    return weekDayLabels.map((label, index) => {
        const current = byDate.get(isoDate(addDays(currentMonday, index))) ?? 0;
        const previous =
            byDate.get(isoDate(addDays(currentMonday, index - 7))) ?? 0;

        return {
            label,
            current,
            previous,
            currentWidth: activityStripWidth(current),
            previousWidth: activityStripWidth(previous),
        };
    });
};
const activityStripWidth = (value: number) => {
    if (value <= 0) {
        return '4px';
    }

    return `${Math.max(8, Math.round((value / maxDailyActivity.value) * 24))}px`;
};
const activeWeekDayCount = (
    member: StaffMember,
    week: 'current' | 'previous',
) => weekActivityFor(member).filter((day) => day[week] > 0).length;
</script>

<template>
    <Head title="HR Department" />

    <h1 class="sr-only">HR Department</h1>

    <div class="flex flex-col gap-7">
        <section
            class="flex flex-wrap items-end justify-between gap-4 border-b border-stone-300/70 pb-4"
        >
            <div class="max-w-3xl">
                <p
                    class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                >
                    HR Department
                </p>
                <h2
                    class="mt-1 text-2xl font-semibold tracking-tight text-stone-950"
                >
                    People, performance, onboarding, and payroll readiness.
                </h2>
                <p class="mt-2 text-sm leading-6 text-stone-600">
                    Track who is doing the work, whether it came through
                    API/AI/manual effort, and keep staff contact, onboarding,
                    write-up, and pay-method notes in one lane.
                </p>
            </div>
            <div class="flex items-center gap-3 text-sm text-stone-600">
                <FontAwesomeIcon
                    :icon="faPersonCircleCheck"
                    class="text-xl text-amber-600"
                />
                <span
                    >{{ formatNumber(summary.staff) }} staff visible to
                    you</span
                >
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <MetricTile label="Staff" :value="formatNumber(summary.staff)" />
            <MetricTile
                label="Open reviews"
                :value="formatNumber(summary.open_reviews)"
            />
            <MetricTile
                label="Write-ups"
                :value="formatNumber(summary.write_ups)"
            />
            <MetricTile
                label="Onboarding active"
                :value="formatNumber(summary.onboarding_active)"
            />
            <MetricTile
                label="Active minutes"
                :value="formatNumber(summary.active_minutes)"
            />
            <MetricTile
                label="Key entries"
                :value="formatNumber(summary.keypresses)"
            />
            <MetricTile
                label="Mouse clicks"
                :value="formatNumber(summary.clicks)"
            />
        </section>

        <section
            class="grid gap-5 xl:grid-cols-[0.8fr_1.2fr]"
            :class="hrSectionDragClass('reports')"
            :style="{ order: hrSectionOrderIndex('reports') }"
            @dragover.prevent="hrSectionDragOverKey = 'reports'"
            @dragleave="hrSectionDragOverKey = null"
            @drop.prevent="onHrSectionDrop($event, 'reports')"
        >
            <div class="space-y-4">
                <div
                    class="flex items-end justify-between gap-3 border-b border-stone-300/70 pb-3"
                >
                    <div>
                        <p
                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                        >
                            AI Weekly Reports
                        </p>
                        <p class="text-sm text-stone-600">
                            OpenRouter drafts manager-ready summaries from task,
                            audit, review, and aggregate input-signal data.
                        </p>
                    </div>
                    <button
                        type="button"
                        draggable="true"
                        class="inline-flex h-9 shrink-0 cursor-grab items-center gap-2 rounded-full border border-stone-300 bg-white/80 px-3 text-[11px] font-semibold tracking-[0.18em] text-stone-500 uppercase transition hover:border-amber-400 hover:text-stone-900 active:cursor-grabbing"
                        title="Move HR table"
                        @dragstart="onHrSectionDragStart($event, 'reports')"
                        @dragend="
                            hrSectionDragKey = null;
                            hrSectionDragOverKey = null;
                        "
                    >
                        <FontAwesomeIcon :icon="faGripVertical" />
                        Move
                    </button>
                </div>
                <form
                    class="space-y-3 rounded-[28px] border border-stone-300/70 bg-white/76 p-4"
                    @submit.prevent="submitWeeklyReport"
                >
                    <label class="space-y-1">
                        <span
                            class="text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                            >Employee</span
                        >
                        <select
                            v-model="weeklyReportForm.user_id"
                            class="h-10 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm"
                        >
                            <option
                                v-for="employee in employee_options"
                                :key="employee.id"
                                :value="employee.id.toString()"
                            >
                                {{ employee.name }}
                            </option>
                        </select>
                    </label>
                    <label class="space-y-1">
                        <span
                            class="text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                            >Week starting</span
                        >
                        <input
                            v-model="weeklyReportForm.period_start"
                            type="date"
                            class="h-10 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm"
                        />
                    </label>
                    <button
                        type="submit"
                        class="inline-flex h-10 items-center gap-2 rounded-full bg-stone-950 px-5 text-sm font-medium text-white transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="weeklyReportForm.processing"
                    >
                        <FontAwesomeIcon :icon="faWandMagicSparkles" />
                        Generate weekly report
                    </button>
                    <p class="text-xs leading-5 text-stone-500">
                        Counts only. CreditSoft does not store typed text, field
                        values, passwords, screenshots, or clipboard contents.
                    </p>
                </form>
            </div>

            <div class="space-y-4">
                <div class="border-b border-stone-300/70 pb-3">
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        Report Queue
                    </p>
                    <p class="text-sm text-stone-600">
                        Latest generated weekly summaries for the visible team.
                    </p>
                </div>
                <div class="grid gap-3">
                    <article
                        v-for="report in weekly_reports"
                        :key="report.id"
                        class="rounded-[24px] border border-stone-300/70 bg-white/76 p-4"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <p class="font-semibold text-stone-950">
                                    {{ report.title }}
                                </p>
                                <p
                                    class="mt-1 text-xs tracking-[0.18em] text-stone-500 uppercase"
                                >
                                    {{ report.employee_name }} ·
                                    {{
                                        formatPeriod(
                                            report.period_start,
                                            report.period_end,
                                        )
                                    }}
                                </p>
                            </div>
                            <span
                                class="rounded-full px-2.5 py-1 text-[10px] font-semibold tracking-[0.16em] uppercase"
                                :class="reportProviderClass(report)"
                                >{{ reportProviderLabel(report) }}</span
                            >
                        </div>
                        <p
                            v-if="report.summary"
                            class="text-stone-650 mt-3 text-sm leading-6"
                        >
                            {{ report.summary }}
                        </p>
                        <div class="mt-4 grid gap-3 md:grid-cols-3">
                            <div v-if="report.strengths.length">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.22em] text-emerald-700 uppercase"
                                >
                                    Strengths
                                </p>
                                <ul
                                    class="mt-2 space-y-1 text-xs leading-5 text-stone-600"
                                >
                                    <li
                                        v-for="item in report.strengths"
                                        :key="item"
                                    >
                                        {{ item }}
                                    </li>
                                </ul>
                            </div>
                            <div v-if="report.risks.length">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.22em] text-rose-700 uppercase"
                                >
                                    Risks
                                </p>
                                <ul
                                    class="mt-2 space-y-1 text-xs leading-5 text-stone-600"
                                >
                                    <li
                                        v-for="item in report.risks"
                                        :key="item"
                                    >
                                        {{ item }}
                                    </li>
                                </ul>
                            </div>
                            <div v-if="report.next_week_focus.length">
                                <p
                                    class="text-[11px] font-semibold tracking-[0.22em] text-stone-700 uppercase"
                                >
                                    Next focus
                                </p>
                                <ul
                                    class="mt-2 space-y-1 text-xs leading-5 text-stone-600"
                                >
                                    <li
                                        v-for="item in report.next_week_focus"
                                        :key="item"
                                    >
                                        {{ item }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <p
                            v-if="report.coaching_notes"
                            class="mt-4 rounded-2xl bg-stone-100/80 px-3 py-2 text-xs leading-5 text-stone-700"
                        >
                            {{ report.coaching_notes }}
                        </p>
                    </article>
                    <p
                        v-if="weekly_reports.length === 0"
                        class="rounded-[24px] border border-dashed border-stone-300 bg-stone-50/70 p-5 text-sm text-stone-500"
                    >
                        No weekly reports generated yet.
                    </p>
                </div>
            </div>
        </section>

        <section
            class="grid gap-5 xl:grid-cols-[1.15fr_0.85fr]"
            :class="hrSectionDragClass('activity')"
            :style="{ order: hrSectionOrderIndex('activity') }"
            @dragover.prevent="hrSectionDragOverKey = 'activity'"
            @dragleave="hrSectionDragOverKey = null"
            @drop.prevent="onHrSectionDrop($event, 'activity')"
        >
            <div class="space-y-4">
                <div
                    class="flex items-end justify-between border-b border-stone-300/70 pb-3"
                >
                    <div>
                        <p
                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                        >
                            Activity timeline
                        </p>
                        <p class="text-sm text-stone-600">
                            Daily employee activity over the last 14 days. Each
                            color is one staff member.
                        </p>
                    </div>
                    <FontAwesomeIcon
                        :icon="faChartLine"
                        class="hidden text-xl text-amber-600 md:block"
                    />
                    <button
                        type="button"
                        draggable="true"
                        class="inline-flex h-9 shrink-0 cursor-grab items-center gap-2 rounded-full border border-stone-300 bg-white/80 px-3 text-[11px] font-semibold tracking-[0.18em] text-stone-500 uppercase transition hover:border-amber-400 hover:text-stone-900 active:cursor-grabbing"
                        title="Move HR table"
                        @dragstart="onHrSectionDragStart($event, 'activity')"
                        @dragend="
                            hrSectionDragKey = null;
                            hrSectionDragOverKey = null;
                        "
                    >
                        <FontAwesomeIcon :icon="faGripVertical" />
                        Move
                    </button>
                </div>
                <div
                    class="rounded-[28px] border border-stone-300/70 bg-white/78 p-4"
                >
                    <MultiLineTrendChart
                        :labels="activity_charts.daily.labels"
                        :series="activity_charts.daily.series"
                        :height="280"
                        :point-radius="2"
                    />
                </div>
            </div>

            <div class="space-y-4">
                <div
                    class="flex items-end justify-between border-b border-stone-300/70 pb-3"
                >
                    <div>
                        <p
                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                        >
                            Work hours
                        </p>
                        <p class="text-sm text-stone-600">
                            Hourly pattern from the last 30 days in
                            {{ activity_charts.timezone }}.
                        </p>
                    </div>
                    <FontAwesomeIcon
                        :icon="faClock"
                        class="hidden text-xl text-stone-500 md:block"
                    />
                </div>
                <div
                    class="rounded-[28px] border border-stone-300/70 bg-white/78 p-4"
                >
                    <MultiLineTrendChart
                        :labels="activity_charts.hourly.labels"
                        :series="activity_charts.hourly.series"
                        :height="240"
                        :point-radius="1"
                    />
                </div>
            </div>
        </section>

        <section
            class="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
            :class="hrSectionDragClass('activity-windows')"
            :style="{ order: hrSectionOrderIndex('activity-windows') }"
            @dragover.prevent="hrSectionDragOverKey = 'activity-windows'"
            @dragleave="hrSectionDragOverKey = null"
            @drop.prevent="onHrSectionDrop($event, 'activity-windows')"
        >
            <div
                class="flex items-end justify-between gap-3 border-b border-stone-300/70 pb-3 md:col-span-2 xl:col-span-4"
            >
                <div>
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        Activity Windows
                    </p>
                    <p class="text-sm text-stone-600">
                        Usual working span, peak hour, active days, and latest
                        event for each visible staff member.
                    </p>
                </div>
                <button
                    type="button"
                    draggable="true"
                    class="inline-flex h-9 shrink-0 cursor-grab items-center gap-2 rounded-full border border-stone-300 bg-white/80 px-3 text-[11px] font-semibold tracking-[0.18em] text-stone-500 uppercase transition hover:border-amber-400 hover:text-stone-900 active:cursor-grabbing"
                    title="Move HR table"
                    @dragstart="
                        onHrSectionDragStart($event, 'activity-windows')
                    "
                    @dragend="
                        hrSectionDragKey = null;
                        hrSectionDragOverKey = null;
                    "
                >
                    <FontAwesomeIcon :icon="faGripVertical" />
                    Move
                </button>
            </div>
            <article
                v-for="window in activity_charts.windows"
                :key="window.user_id"
                class="rounded-[24px] border border-stone-300/70 bg-stone-50/76 p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-stone-950">
                            {{ window.name }}
                        </p>
                        <p
                            class="mt-1 text-xs tracking-[0.18em] text-stone-500 uppercase"
                        >
                            Activity window
                        </p>
                    </div>
                    <span
                        class="mt-1 size-3 rounded-full"
                        :style="{ backgroundColor: window.color }"
                    />
                </div>
                <dl class="mt-4 space-y-2 text-xs text-stone-600">
                    <div class="flex items-center justify-between gap-4">
                        <dt>Usual span</dt>
                        <dd class="font-medium text-stone-950">
                            {{ window.work_window }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt>Peak hour</dt>
                        <dd class="font-medium text-stone-950">
                            {{ window.peak_hour }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt>Active days</dt>
                        <dd class="font-medium text-stone-950">
                            {{ formatNumber(window.active_days) }}
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt>Last event</dt>
                        <dd class="text-right font-medium text-stone-950">
                            {{
                                window.last_activity_at
                                    ? formatDateTime(window.last_activity_at)
                                    : 'No activity yet'
                            }}
                        </dd>
                    </div>
                </dl>
            </article>
        </section>

        <section
            class="space-y-4"
            :class="hrSectionDragClass('performance')"
            :style="{ order: hrSectionOrderIndex('performance') }"
            @dragover.prevent="hrSectionDragOverKey = 'performance'"
            @dragleave="hrSectionDragOverKey = null"
            @drop.prevent="onHrSectionDrop($event, 'performance')"
        >
            <div
                class="flex flex-wrap items-end justify-between gap-4 border-b border-stone-300/70 pb-3"
            >
                <div>
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        Performance
                    </p>
                    <p class="text-sm text-stone-600">
                        Last 30 days, scored across API, AI, manual work,
                        completed tasks, and assigned clients.
                    </p>
                </div>
                <div
                    class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-stone-500"
                >
                    <span
                        ><FontAwesomeIcon
                            :icon="faBolt"
                            class="mr-1 text-stone-700"
                        />
                        API {{ formatNumber(summary.api_actions) }}</span
                    >
                    <span
                        ><FontAwesomeIcon
                            :icon="faBrain"
                            class="mr-1 text-stone-700"
                        />
                        AI {{ formatNumber(summary.ai_actions) }}</span
                    >
                    <span
                        ><FontAwesomeIcon
                            :icon="faKeyboard"
                            class="mr-1 text-stone-700"
                        />
                        Manual {{ formatNumber(summary.manual_actions) }}</span
                    >
                    <span
                        ><FontAwesomeIcon
                            :icon="faComputerMouse"
                            class="mr-1 text-stone-700"
                        />
                        Clicks {{ formatNumber(summary.clicks) }}</span
                    >
                    <span class="inline-flex items-center gap-1 text-stone-600"
                        ><FontAwesomeIcon :icon="faTableList" /> Drag table
                        blocks</span
                    >
                    <button
                        type="button"
                        draggable="true"
                        class="inline-flex h-9 shrink-0 cursor-grab items-center gap-2 rounded-full border border-stone-300 bg-white/80 px-3 text-[11px] font-semibold tracking-[0.18em] text-stone-500 uppercase transition hover:border-amber-400 hover:text-stone-900 active:cursor-grabbing"
                        title="Move HR table"
                        @dragstart="onHrSectionDragStart($event, 'performance')"
                        @dragend="
                            hrSectionDragKey = null;
                            hrSectionDragOverKey = null;
                        "
                    >
                        <FontAwesomeIcon :icon="faGripVertical" />
                        Move
                    </button>
                </div>
            </div>

            <div
                class="overflow-hidden rounded-[24px] border border-stone-300/70 bg-stone-50/80"
            >
                <div class="overflow-x-auto">
                    <table
                        class="min-w-[1120px] divide-y divide-stone-300/70 text-sm"
                    >
                        <thead
                            class="bg-stone-100/80 text-left text-[11px] tracking-[0.22em] text-stone-500 uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3">Employee</th>
                                <th class="px-4 py-3">Score</th>
                                <th class="px-4 py-3">API / AI / Manual</th>
                                <th class="px-4 py-3">Input signals</th>
                                <th class="px-4 py-3">Workload</th>
                                <th class="px-4 py-3">Week signal</th>
                                <th class="px-4 py-3">Seen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-200/70 bg-white/70">
                            <tr
                                v-for="member in orderedPerformanceStaff"
                                :key="member.id"
                                class="transition hover:bg-amber-50/50"
                            >
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <img
                                            :src="member.gravatar_url"
                                            :alt="member.name"
                                            class="size-9 rounded-full border border-stone-200 bg-white"
                                        />
                                        <div>
                                            <p
                                                class="font-medium text-stone-950"
                                            >
                                                {{ member.name }}
                                            </p>
                                            <p class="text-xs text-stone-500">
                                                {{ member.role_label ?? 'Staff'
                                                }}<span
                                                    v-if="member.manager_name"
                                                >
                                                    · reports to
                                                    {{
                                                        member.manager_name
                                                    }}</span
                                                >
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 text-lg font-semibold text-stone-950"
                                >
                                    {{ formatNumber(member.activity.score) }}
                                </td>
                                <td class="px-4 py-3 text-stone-600">
                                    {{ formatNumber(member.activity.api) }} /
                                    {{ formatNumber(member.activity.ai) }} /
                                    {{ formatNumber(member.activity.manual) }}
                                </td>
                                <td class="px-4 py-3 text-stone-600">
                                    {{
                                        formatNumber(
                                            member.activity.active_minutes,
                                        )
                                    }}m ·
                                    {{
                                        formatNumber(member.activity.keypresses)
                                    }}
                                    keys ·
                                    {{ formatNumber(member.activity.clicks) }}
                                    clicks
                                </td>
                                <td class="px-4 py-3 text-stone-600">
                                    {{ formatNumber(member.assigned_clients) }}
                                    clients ·
                                    {{ formatNumber(member.open_tasks) }} open ·
                                    {{ formatNumber(member.done_tasks) }} done
                                </td>
                                <td class="px-4 py-3">
                                    <div
                                        class="inline-flex items-center gap-1 rounded-xl border border-stone-200 bg-white/80 px-2 py-1.5"
                                    >
                                        <span
                                            v-for="(
                                                day, index
                                            ) in weekActivityFor(member)"
                                            :key="`${member.id}-${day.label}-${index}`"
                                            class="relative inline-flex h-8 w-6 items-center justify-center rounded-md bg-stone-50 text-[10px] font-semibold text-stone-600"
                                        >
                                            <span
                                                class="absolute top-1 h-1 rounded-full bg-amber-400"
                                                :class="
                                                    day.current > 0
                                                        ? 'opacity-100'
                                                        : 'opacity-20'
                                                "
                                                :style="{
                                                    width: day.currentWidth,
                                                }"
                                            />
                                            <span
                                                class="absolute bottom-1 h-1 rounded-full bg-stone-400"
                                                :class="
                                                    day.previous > 0
                                                        ? 'opacity-90'
                                                        : 'opacity-20'
                                                "
                                                :style="{
                                                    width: day.previousWidth,
                                                }"
                                            />
                                            <span class="relative z-10">{{
                                                day.label
                                            }}</span>
                                        </span>
                                    </div>
                                    <p
                                        class="mt-1 text-[11px] tracking-[0.12em] text-stone-500 uppercase"
                                    >
                                        This
                                        {{
                                            activeWeekDayCount(
                                                member,
                                                'current',
                                            )
                                        }}/7 · Last
                                        {{
                                            activeWeekDayCount(
                                                member,
                                                'previous',
                                            )
                                        }}/7
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-xs text-stone-500">
                                    <p>
                                        <span class="text-stone-400"
                                            >First</span
                                        >
                                        {{
                                            member.first_seen_at
                                                ? formatDate(
                                                      member.first_seen_at,
                                                  )
                                                : 'No activity yet'
                                        }}
                                    </p>
                                    <p class="mt-1">
                                        <span class="text-stone-400">Last</span>
                                        {{
                                            member.last_seen_at
                                                ? formatDateTime(
                                                      member.last_seen_at,
                                                  )
                                                : 'Never'
                                        }}
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section
            class="grid gap-5 xl:grid-cols-[0.95fr_1.05fr]"
            :class="hrSectionDragClass('employee-records')"
            :style="{ order: hrSectionOrderIndex('employee-records') }"
            @dragover.prevent="hrSectionDragOverKey = 'employee-records'"
            @dragleave="hrSectionDragOverKey = null"
            @drop.prevent="onHrSectionDrop($event, 'employee-records')"
        >
            <div class="space-y-4">
                <div
                    class="flex items-end justify-between gap-3 border-b border-stone-300/70 pb-3"
                >
                    <div>
                        <p
                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                        >
                            Employee File
                        </p>
                        <p class="text-sm text-stone-600">
                            Contact, onboarding, job title, and pay-method
                            details.
                        </p>
                    </div>
                    <button
                        type="button"
                        draggable="true"
                        class="inline-flex h-9 shrink-0 cursor-grab items-center gap-2 rounded-full border border-stone-300 bg-white/80 px-3 text-[11px] font-semibold tracking-[0.18em] text-stone-500 uppercase transition hover:border-amber-400 hover:text-stone-900 active:cursor-grabbing"
                        title="Move HR table"
                        @dragstart="
                            onHrSectionDragStart($event, 'employee-records')
                        "
                        @dragend="
                            hrSectionDragKey = null;
                            hrSectionDragOverKey = null;
                        "
                    >
                        <FontAwesomeIcon :icon="faGripVertical" />
                        Move
                    </button>
                </div>

                <form
                    v-if="can_manage_hr"
                    class="space-y-3 rounded-[28px] border border-stone-300/70 bg-white/76 p-4"
                    @submit.prevent="submitProfile"
                >
                    <select
                        v-model="profileForm.user_id"
                        class="h-10 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm"
                    >
                        <option
                            v-for="employee in employee_options"
                            :key="employee.id"
                            :value="employee.id.toString()"
                        >
                            {{ employee.name }} · {{ employee.email }}
                        </option>
                    </select>
                    <div class="grid gap-3 md:grid-cols-2">
                        <input
                            v-model="profileForm.legal_name"
                            type="text"
                            class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm"
                            placeholder="Legal name"
                        />
                        <input
                            v-model="profileForm.preferred_name"
                            type="text"
                            class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm"
                            placeholder="Preferred name"
                        />
                        <input
                            v-model="profileForm.phone"
                            type="text"
                            class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm"
                            placeholder="Phone"
                        />
                        <input
                            v-model="profileForm.timezone"
                            type="text"
                            class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm"
                            placeholder="Timezone"
                        />
                        <input
                            v-model="profileForm.department"
                            type="text"
                            class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm"
                            placeholder="Department"
                        />
                        <input
                            v-model="profileForm.title"
                            type="text"
                            class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm"
                            placeholder="Title"
                        />
                        <input
                            v-model="profileForm.employment_type"
                            type="text"
                            class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm"
                            placeholder="Employment type"
                        />
                        <select
                            v-model="profileForm.onboarding_status"
                            class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm"
                        >
                            <option value="not_started">
                                Onboarding not started
                            </option>
                            <option value="invited">Invited</option>
                            <option value="active">Active onboarding</option>
                            <option value="complete">Complete</option>
                            <option value="paused">Paused</option>
                        </select>
                        <input
                            v-model="profileForm.emergency_contact_name"
                            type="text"
                            class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm"
                            placeholder="Emergency contact"
                        />
                        <input
                            v-model="profileForm.emergency_contact_phone"
                            type="text"
                            class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm"
                            placeholder="Emergency phone"
                        />
                        <input
                            v-model="profileForm.pay_method"
                            type="text"
                            class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm"
                            placeholder="Pay method"
                        />
                        <input
                            v-model="profileForm.pay_destination"
                            type="text"
                            class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm"
                            placeholder="Pay destination"
                        />
                    </div>
                    <textarea
                        v-model="profileForm.payroll_notes"
                        rows="3"
                        class="w-full rounded-2xl border border-stone-300 bg-white px-3 py-2 text-sm"
                        placeholder="Payroll notes, tax paperwork reminders, or payment constraints."
                    />
                    <button
                        type="submit"
                        class="inline-flex h-10 items-center rounded-full bg-stone-950 px-5 text-sm font-medium text-white transition hover:bg-stone-800"
                        :disabled="profileForm.processing"
                    >
                        Save employee file
                    </button>
                </form>
                <p
                    v-else
                    class="rounded-[24px] border border-stone-300/70 bg-stone-50/70 p-4 text-sm text-stone-600"
                >
                    Managers can view their lane and add HR notes. Owner/admin
                    access is required to edit private contact or pay-method
                    fields.
                </p>

                <div class="border-t border-stone-300/70 pt-4">
                    <div class="mb-3">
                        <p
                            class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                        >
                            HR Actions
                        </p>
                        <p class="text-sm text-stone-600">
                            Create reviews, coaching notes, onboarding notes, or
                            write-ups.
                        </p>
                    </div>

                    <form
                        class="space-y-3 rounded-[24px] border border-stone-300/70 bg-white/76 p-4"
                        @submit.prevent="submitReview"
                    >
                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="space-y-1">
                                <span
                                    class="text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                                    >Employee</span
                                >
                                <select
                                    v-model="reviewForm.user_id"
                                    class="h-10 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm"
                                >
                                    <option
                                        v-for="employee in employee_options"
                                        :key="employee.id"
                                        :value="employee.id.toString()"
                                    >
                                        {{ employee.name }}
                                    </option>
                                </select>
                            </label>
                            <label class="space-y-1">
                                <span
                                    class="text-[11px] font-medium tracking-[0.2em] text-stone-500 uppercase"
                                    >Type</span
                                >
                                <select
                                    v-model="reviewForm.review_type"
                                    class="h-10 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm"
                                >
                                    <option value="review">Review</option>
                                    <option value="coaching">Coaching</option>
                                    <option value="write_up">Write-up</option>
                                    <option value="onboarding">
                                        Onboarding
                                    </option>
                                </select>
                            </label>
                        </div>
                        <input
                            v-model="reviewForm.title"
                            type="text"
                            class="h-10 w-full rounded-xl border border-stone-300 bg-white px-3 text-sm"
                            placeholder="Title"
                        />
                        <textarea
                            v-model="reviewForm.body"
                            rows="4"
                            class="w-full rounded-2xl border border-stone-300 bg-white px-3 py-2 text-sm"
                            placeholder="What happened, what changed, or what needs follow-up?"
                        />
                        <div class="grid gap-3 md:grid-cols-3">
                            <input
                                v-model="reviewForm.rating"
                                type="number"
                                min="1"
                                max="5"
                                class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm"
                                placeholder="Rating 1-5"
                            />
                            <input
                                v-model="reviewForm.occurred_on"
                                type="date"
                                class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm"
                            />
                            <select
                                v-model="reviewForm.status"
                                class="h-10 rounded-xl border border-stone-300 bg-white px-3 text-sm"
                            >
                                <option value="open">Open</option>
                                <option value="acknowledged">
                                    Acknowledged
                                </option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <button
                            type="submit"
                            class="inline-flex h-10 items-center rounded-full bg-stone-950 px-5 text-sm font-medium text-white transition hover:bg-stone-800"
                            :disabled="reviewForm.processing"
                        >
                            Save HR note
                        </button>
                    </form>
                </div>
            </div>

            <div class="space-y-4">
                <div class="border-b border-stone-300/70 pb-3">
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        Roster Snapshot
                    </p>
                    <p class="text-sm text-stone-600">
                        Quick contact and onboarding state for the visible team.
                    </p>
                </div>
                <div class="grid gap-3 md:grid-cols-2">
                    <article
                        v-for="member in staff"
                        :key="`profile-${member.id}`"
                        class="rounded-[24px] border border-stone-300/70 bg-stone-50/74 p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-stone-950">
                                    {{ member.name }}
                                </p>
                                <p class="text-xs text-stone-500">
                                    {{
                                        member.profile?.title ??
                                        member.role_label ??
                                        'Staff'
                                    }}
                                </p>
                            </div>
                            <span
                                class="rounded-full px-2.5 py-1 text-[10px] font-semibold tracking-[0.16em] uppercase"
                                :class="
                                    member.needs_setup
                                        ? 'border border-amber-300 bg-amber-100 text-amber-900'
                                        : 'bg-stone-900 text-white'
                                "
                                >{{
                                    member.needs_setup
                                        ? 'Setup needed'
                                        : statusLabel(
                                              member.profile?.onboarding_status,
                                          )
                                }}</span
                            >
                        </div>
                        <dl class="mt-3 space-y-2 text-xs text-stone-600">
                            <div class="flex justify-between gap-4">
                                <dt>Email</dt>
                                <dd class="text-right text-stone-900">
                                    {{ member.email }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt>Phone</dt>
                                <dd class="text-right text-stone-900">
                                    {{ empty(member.profile?.phone) }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt>Pay</dt>
                                <dd class="text-right text-stone-900">
                                    {{ empty(member.profile?.pay_method) }}
                                </dd>
                            </div>
                            <div
                                v-if="member.needs_setup"
                                class="rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-amber-900"
                            >
                                {{ member.setup_note }}
                            </div>
                        </dl>
                    </article>
                </div>
            </div>
        </section>

        <section
            class="space-y-4"
            :class="hrSectionDragClass('review-history')"
            :style="{ order: hrSectionOrderIndex('review-history') }"
            @dragover.prevent="hrSectionDragOverKey = 'review-history'"
            @dragleave="hrSectionDragOverKey = null"
            @drop.prevent="onHrSectionDrop($event, 'review-history')"
        >
            <div
                class="flex items-end justify-between gap-3 border-b border-stone-300/70 pb-3"
            >
                <div>
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        Review History
                    </p>
                    <p class="text-sm text-stone-600">
                        Latest reviews, onboarding notes, coaching notes, and
                        write-ups.
                    </p>
                </div>
                <button
                    type="button"
                    draggable="true"
                    class="inline-flex h-9 shrink-0 cursor-grab items-center gap-2 rounded-full border border-stone-300 bg-white/80 px-3 text-[11px] font-semibold tracking-[0.18em] text-stone-500 uppercase transition hover:border-amber-400 hover:text-stone-900 active:cursor-grabbing"
                    title="Move HR table"
                    @dragstart="onHrSectionDragStart($event, 'review-history')"
                    @dragend="
                        hrSectionDragKey = null;
                        hrSectionDragOverKey = null;
                    "
                >
                    <FontAwesomeIcon :icon="faGripVertical" />
                    Move
                </button>
            </div>
            <div class="grid gap-3">
                <article
                    v-for="review in reviews"
                    :key="review.id"
                    class="grid gap-3 rounded-[24px] border border-stone-300/70 bg-white/76 p-4 md:grid-cols-[170px_1fr_160px]"
                >
                    <div class="text-sm">
                        <p class="font-semibold text-stone-950">
                            {{ review.employee_name }}
                        </p>
                        <p
                            class="text-xs tracking-[0.18em] text-stone-500 uppercase"
                        >
                            {{ review.review_type.replaceAll('_', ' ') }}
                        </p>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <FontAwesomeIcon
                                :icon="
                                    review.review_type === 'write_up'
                                        ? faTriangleExclamation
                                        : review.review_type === 'onboarding'
                                          ? faClipboardCheck
                                          : faFileSignature
                                "
                                class="text-stone-500"
                            />
                            <p class="font-medium text-stone-950">
                                {{ review.title }}
                            </p>
                        </div>
                        <p
                            v-if="review.body"
                            class="mt-1 text-sm leading-6 text-stone-600"
                        >
                            {{ review.body }}
                        </p>
                    </div>
                    <div class="text-xs text-stone-500 md:text-right">
                        <p>{{ review.status }}</p>
                        <p v-if="review.occurred_on">
                            {{ formatDate(review.occurred_on) }}
                        </p>
                        <p v-if="review.reviewer_name">
                            By {{ review.reviewer_name }}
                        </p>
                    </div>
                </article>
                <p
                    v-if="reviews.length === 0"
                    class="rounded-[24px] border border-dashed border-stone-300 bg-stone-50/70 p-5 text-sm text-stone-500"
                >
                    No HR notes yet.
                </p>
            </div>
        </section>
    </div>
</template>
