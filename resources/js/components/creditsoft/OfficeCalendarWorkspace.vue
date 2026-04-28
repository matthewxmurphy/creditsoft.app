<script setup lang="ts">
import { computed, ref } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import type { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import {
    faArrowUpRightFromSquare,
    faCalendarDays,
    faClock,
    faEnvelope,
    faListCheck,
    faTableColumns,
    faUsers,
    faVideo,
} from '@fortawesome/free-solid-svg-icons';

type OfficeCalendarView = 'month' | 'week' | 'day' | 'agenda' | 'consults';
type OfficeRailItem = {
    key: OfficeCalendarView;
    label: string;
    icon: IconDefinition;
};
type BookingLink = {
    name: string;
    url: string;
    channel: string;
};
type VisibleUser = {
    id: number | string;
    name: string;
    role_label?: string | null;
};
type OfficeEventSeed = {
    id: string;
    title: string;
    date: string;
    time: string;
    duration: string;
    kind: 'consult' | 'follow-up' | 'review' | 'admin';
    status: 'confirmed' | 'prep' | 'hold' | 'open';
    description: string;
    ownerIndex: number;
};

const props = withDefaults(defineProps<{
    portalBookingName?: string | null;
    calendarEmail?: string | null;
    bookingLinks?: BookingLink[];
    scopeLabel?: string;
    scopeSummary?: string;
    canViewEveryone?: boolean;
    visibleUsers?: VisibleUser[];
}>(), {
    portalBookingName: 'Detailed Credit Analysis Consultation',
    calendarEmail: null,
    bookingLinks: () => [],
    scopeLabel: 'Personal view',
    scopeSummary: 'Staff-level calendar access stays on the signed-in user.',
    canViewEveryone: false,
    visibleUsers: () => [],
});

const toIsoDate = (value: Date) => {
    const year = value.getFullYear();
    const month = `${value.getMonth() + 1}`.padStart(2, '0');
    const day = `${value.getDate()}`.padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const TODAY = toIsoDate(new Date());

const officeViews: OfficeRailItem[] = [
    { key: 'month', label: 'Month', icon: faCalendarDays },
    { key: 'week', label: 'Week', icon: faTableColumns },
    { key: 'day', label: 'Day', icon: faClock },
    { key: 'agenda', label: 'Agenda', icon: faListCheck },
    { key: 'consults', label: 'Consults', icon: faVideo },
];

const fallbackUsers: VisibleUser[] = [
    { id: 'owner', name: 'Office owner', role_label: 'Owner admin' },
    { id: 'manager', name: 'Team manager', role_label: 'Manager' },
    { id: 'staff', name: 'Case staff', role_label: 'Staff' },
];

const eventSeeds: OfficeEventSeed[] = [
    {
        id: 'credit-analysis',
        title: 'Detailed credit analysis consultation',
        date: '2026-04-15',
        time: '9:30 AM',
        duration: '45 min',
        kind: 'consult',
        status: 'confirmed',
        description: 'New lead walkthrough with report access, portal orientation, and next-step plan.',
        ownerIndex: 0,
    },
    {
        id: 'no-show-recovery',
        title: 'No-show recovery block',
        date: '2026-04-15',
        time: '12:15 PM',
        duration: '20 min',
        kind: 'follow-up',
        status: 'prep',
        description: 'Call and message clients who missed a booked consultation window.',
        ownerIndex: 1,
    },
    {
        id: 'strategy-review',
        title: 'Dispute strategy review',
        date: '2026-04-16',
        time: '2:00 PM',
        duration: '50 min',
        kind: 'review',
        status: 'confirmed',
        description: 'Review imported report items, owner notes, and letter readiness before next round.',
        ownerIndex: 2,
    },
    {
        id: 'business-credit',
        title: 'Business credit consultation',
        date: '2026-04-17',
        time: '10:00 AM',
        duration: '40 min',
        kind: 'consult',
        status: 'hold',
        description: 'Open consult slot reserved for the business-credit booking lane.',
        ownerIndex: 0,
    },
    {
        id: 'client-follow-up',
        title: '30-day client follow-up',
        date: '2026-04-20',
        time: '1:30 PM',
        duration: '30 min',
        kind: 'follow-up',
        status: 'confirmed',
        description: 'Check status, collect missing uploads, and schedule the next report review.',
        ownerIndex: 1,
    },
    {
        id: 'affiliate-intro',
        title: 'Affiliate intro call',
        date: '2026-04-21',
        time: '11:15 AM',
        duration: '25 min',
        kind: 'admin',
        status: 'open',
        description: 'Referral partner handoff and owner assignment review.',
        ownerIndex: 2,
    },
    {
        id: 'onboarding-review',
        title: 'Portal onboarding review',
        date: '2026-04-22',
        time: '3:30 PM',
        duration: '35 min',
        kind: 'review',
        status: 'prep',
        description: 'Make sure the client can use the portal, upload documents, and print DIY letters.',
        ownerIndex: 0,
    },
    {
        id: 'weekly-calendar-cleanup',
        title: 'Weekly calendar cleanup',
        date: '2026-04-24',
        time: '4:00 PM',
        duration: '30 min',
        kind: 'admin',
        status: 'confirmed',
        description: 'Move stale holds, confirm owner calendars, and fill next week’s open slots.',
        ownerIndex: 1,
    },
];

const activeView = ref<OfficeCalendarView>('month');
const selectedDate = ref(TODAY);
const weekdayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

const parseIsoDate = (value: string) => new Date(`${value}T12:00:00`);
const ownerPool = computed(() => props.visibleUsers.length > 0 ? props.visibleUsers : fallbackUsers);
const visibleUserCount = computed(() => props.visibleUsers.length || fallbackUsers.length);

const officeEvents = computed(() =>
    eventSeeds.map((event) => {
        const owner = ownerPool.value[event.ownerIndex % ownerPool.value.length] ?? fallbackUsers[0];

        return {
            ...event,
            ownerName: owner.name,
            ownerRole: owner.role_label || 'Staff',
        };
    }),
);

const currentMonthLabel = computed(() =>
    parseIsoDate(selectedDate.value).toLocaleDateString('en-US', {
        month: 'long',
        year: 'numeric',
    }),
);

const selectedDateLabel = computed(() =>
    parseIsoDate(selectedDate.value).toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
    }),
);

const activeViewMeta = computed(() =>
    officeViews.find((view) => view.key === activeView.value) ?? officeViews[0],
);

const monthGrid = computed(() => {
    const focus = parseIsoDate(selectedDate.value);
    const monthStart = new Date(focus.getFullYear(), focus.getMonth(), 1);
    const gridStart = new Date(monthStart);
    gridStart.setDate(monthStart.getDate() - monthStart.getDay());

    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(gridStart);
        date.setDate(gridStart.getDate() + index);

        const iso = toIsoDate(date);
        const events = officeEvents.value.filter((event) => event.date === iso);

        return {
            iso,
            day: date.getDate(),
            inMonth: date.getMonth() === focus.getMonth(),
            isToday: iso === TODAY,
            isSelected: iso === selectedDate.value,
            events,
        };
    });
});

const weekDays = computed(() => {
    const focus = parseIsoDate(selectedDate.value);
    const weekStart = new Date(focus);
    weekStart.setDate(focus.getDate() - focus.getDay());

    return Array.from({ length: 7 }, (_, index) => {
        const date = new Date(weekStart);
        date.setDate(weekStart.getDate() + index);
        const iso = toIsoDate(date);

        return {
            iso,
            label: date.toLocaleDateString('en-US', {
                weekday: 'short',
                month: 'short',
                day: 'numeric',
            }),
            events: officeEvents.value.filter((event) => event.date === iso),
        };
    });
});

const dayEvents = computed(() =>
    officeEvents.value.filter((event) => event.date === selectedDate.value),
);

const agendaEvents = computed(() =>
    [...officeEvents.value].sort((left, right) => `${left.date} ${left.time}`.localeCompare(`${right.date} ${right.time}`)),
);

const consultEvents = computed(() =>
    agendaEvents.value.filter((event) => event.kind === 'consult' || event.kind === 'review'),
);

const openBookingSlots = computed(() => {
    const links = props.bookingLinks
        .filter((link) => (link.name || link.url || link.channel).trim() !== '')
        .slice(0, 4)
        .map((link, index) => ({
            id: `booking-${index}`,
            label: link.name || 'Booking link',
            channel: link.channel || 'general',
            href: link.url,
        }));

    if (links.length > 0) {
        return links;
    }

    return [
        { id: 'default-consult', label: props.portalBookingName || 'Detailed Credit Analysis Consultation', channel: 'consultation', href: '' },
        { id: 'default-followup', label: 'Follow-up review call', channel: 'follow-up', href: '' },
    ];
});

const statusClass = (status: OfficeEventSeed['status']) => {
    switch (status) {
        case 'confirmed':
            return 'border-emerald-200 bg-emerald-50 text-emerald-800';
        case 'prep':
            return 'border-amber-200 bg-amber-50 text-amber-800';
        case 'hold':
            return 'border-sky-200 bg-sky-50 text-sky-800';
        default:
            return 'border-stone-300 bg-stone-100 text-stone-700';
    }
};

const kindClass = (kind: OfficeEventSeed['kind']) => {
    switch (kind) {
        case 'consult':
            return 'border-stone-900/10 bg-stone-950 text-white';
        case 'follow-up':
            return 'border-emerald-200 bg-emerald-50 text-emerald-800';
        case 'review':
            return 'border-sky-200 bg-sky-50 text-sky-800';
        default:
            return 'border-stone-300 bg-stone-100 text-stone-700';
    }
};

const jumpRange = (direction: number) => {
    const next = parseIsoDate(selectedDate.value);

    if (activeView.value === 'month') {
        next.setMonth(next.getMonth() + direction);
    } else if (activeView.value === 'week' || activeView.value === 'consults') {
        next.setDate(next.getDate() + (7 * direction));
    } else {
        next.setDate(next.getDate() + direction);
    }

    selectedDate.value = toIsoDate(next);
};

const goToToday = () => {
    selectedDate.value = TODAY;
};
</script>

<template>
    <section class="overflow-hidden rounded-[32px] border border-stone-300/70 bg-white/95 shadow-[0_24px_60px_rgba(15,23,42,0.08)]">
        <div class="border-b border-stone-200/80 px-6 py-5 lg:px-8">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="space-y-2">
                    <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">Office calendar</p>
                    <h1 class="text-2xl font-semibold tracking-tight text-stone-950 sm:text-[2rem]">
                        Scheduled consults, follow-ups, and owner visibility.
                    </h1>
                    <p class="max-w-4xl text-sm leading-7 text-stone-600">
                        This is the regular calendar lane for consulting, demos, reviews, and follow-up blocks. Owner and admin roles see the whole office calendar; managers see their team; staff stay scoped to their own lane.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a href="/calendar/social" class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-stone-50 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-500 hover:text-stone-950">
                        <FontAwesomeIcon :icon="faCalendarDays" />
                        Social calendar
                    </a>
                    <a href="/settings/growth#appointments" class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-stone-50 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-500 hover:text-stone-950">
                        <FontAwesomeIcon :icon="faArrowUpRightFromSquare" />
                        Booking settings
                    </a>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
                <span class="inline-flex rounded-full border border-stone-300 bg-white px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-700">
                    {{ props.scopeLabel }}
                </span>
                <span class="inline-flex rounded-full border border-stone-300 bg-white px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-700">
                    {{ visibleUserCount }} visible {{ visibleUserCount === 1 ? 'calendar' : 'calendars' }}
                </span>
                <span class="inline-flex rounded-full border border-stone-300 bg-white px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-700">
                    {{ props.calendarEmail || 'No calendar email saved' }}
                </span>
                <span class="inline-flex rounded-full border border-stone-300 bg-white px-3 py-1.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-700">
                    {{ props.portalBookingName || 'Booking lane staged' }}
                </span>
            </div>
        </div>

        <div class="pl-0 pr-6 py-6 xl:pr-8 xl:py-8">
            <div class="grid gap-2 lg:grid-cols-[34px_minmax(0,1fr)] lg:items-start">
                <aside class="lg:sticky lg:top-6">
                    <div class="flex gap-2 overflow-x-auto lg:flex-col lg:items-start lg:overflow-visible">
                        <button
                            v-for="view in officeViews"
                            :key="view.key"
                            type="button"
                            class="relative inline-flex size-8 shrink-0 items-center justify-center text-[17px] transition"
                            :class="activeView === view.key ? 'text-stone-950' : 'text-stone-400 hover:text-stone-900'"
                            :aria-label="view.label"
                            :title="view.label"
                            @click="activeView = view.key"
                        >
                            <span
                                v-if="activeView === view.key"
                                class="absolute left-0 top-1/2 hidden h-5 w-[2px] -translate-y-1/2 rounded-full bg-stone-950 lg:block"
                            />
                            <FontAwesomeIcon :icon="view.icon" class="lg:translate-x-[5px]" />
                        </button>
                    </div>
                </aside>

                <div class="space-y-5">
                    <div class="flex flex-col gap-4 rounded-[28px] border border-stone-200 bg-stone-50/70 p-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button" class="inline-flex rounded-full border border-stone-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-500 hover:text-stone-950" @click="jumpRange(-1)">
                                Prev
                            </button>
                            <button type="button" class="inline-flex rounded-full border border-stone-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-500 hover:text-stone-950" @click="goToToday">
                                Today
                            </button>
                            <button type="button" class="inline-flex rounded-full border border-stone-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-500 hover:text-stone-950" @click="jumpRange(1)">
                                Next
                            </button>
                        </div>

                        <div class="space-y-1 text-left lg:text-right">
                            <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">
                                {{ activeView === 'month' ? currentMonthLabel : selectedDateLabel }}
                            </p>
                            <p class="text-sm font-medium text-stone-900">
                                {{ activeViewMeta.label }} · {{ props.scopeSummary }}
                            </p>
                        </div>
                    </div>

                    <div v-if="activeView === 'month'" class="overflow-hidden rounded-[30px] border border-stone-200 bg-white">
                        <div class="grid grid-cols-7 border-b border-stone-200 bg-stone-50">
                            <div v-for="label in weekdayLabels" :key="label" class="px-3 py-3 text-center text-[11px] font-semibold uppercase tracking-[0.22em] text-stone-500">
                                {{ label }}
                            </div>
                        </div>

                        <div class="grid grid-cols-7">
                            <button
                                v-for="day in monthGrid"
                                :key="day.iso"
                                type="button"
                                class="min-h-[122px] border-b border-r border-stone-200 px-2 py-2 text-left align-top transition last:border-r-0 hover:bg-stone-50"
                                :class="[
                                    day.inMonth ? 'bg-white' : 'bg-stone-50/60 text-stone-400',
                                    day.isSelected ? 'ring-2 ring-inset ring-stone-900' : '',
                                ]"
                                @click="selectedDate = day.iso"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="inline-flex size-7 items-center justify-center rounded-full text-xs font-semibold" :class="day.isToday ? 'bg-stone-950 text-white' : ''">
                                        {{ day.day }}
                                    </span>
                                    <span v-if="day.events.length" class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-400">
                                        {{ day.events.length }} items
                                    </span>
                                </div>

                                <div class="mt-3 space-y-2">
                                    <div
                                        v-for="event in day.events.slice(0, 2)"
                                        :key="event.id"
                                        class="rounded-2xl border border-stone-200 bg-stone-50 px-2 py-2"
                                    >
                                        <p class="truncate text-[10px] font-semibold uppercase tracking-[0.15em] text-stone-500">
                                            {{ event.time }} · {{ event.ownerName }}
                                        </p>
                                        <p class="mt-1 line-clamp-2 text-xs font-medium text-stone-900">
                                            {{ event.title }}
                                        </p>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>

                    <div v-else-if="activeView === 'week'" class="grid gap-4 xl:grid-cols-7">
                        <article v-for="day in weekDays" :key="day.iso" class="rounded-[26px] border border-stone-200 bg-white p-4">
                            <div class="border-b border-stone-200 pb-3">
                                <p class="text-[11px] font-medium uppercase tracking-[0.22em] text-stone-500">{{ day.label }}</p>
                            </div>
                            <div class="mt-4 space-y-3">
                                <button
                                    v-for="event in day.events"
                                    :key="event.id"
                                    type="button"
                                    class="block w-full rounded-[22px] border border-stone-200 bg-stone-50 px-3 py-3 text-left"
                                    @click="selectedDate = event.date"
                                >
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">{{ event.time }} · {{ event.duration }}</p>
                                    <p class="mt-1 text-sm font-medium text-stone-900">{{ event.title }}</p>
                                    <p class="mt-1 text-xs leading-5 text-stone-500">{{ event.ownerName }} · {{ event.ownerRole }}</p>
                                </button>
                                <p v-if="day.events.length === 0" class="text-sm leading-6 text-stone-400">Open slot.</p>
                            </div>
                        </article>
                    </div>

                    <div v-else-if="activeView === 'day'" class="rounded-[30px] border border-stone-200 bg-white p-5">
                        <div class="border-b border-stone-200 pb-4">
                            <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Day view</p>
                            <h3 class="mt-2 text-lg font-semibold text-stone-950">{{ selectedDateLabel }}</h3>
                        </div>

                        <div class="mt-5 space-y-3">
                            <article
                                v-for="event in dayEvents"
                                :key="event.id"
                                class="rounded-[24px] border border-stone-200 bg-stone-50 px-4 py-4"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-500">{{ event.time }} · {{ event.duration }}</p>
                                        <h4 class="mt-2 text-base font-semibold text-stone-950">{{ event.title }}</h4>
                                    </div>
                                    <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" :class="statusClass(event.status)">
                                        {{ event.status }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-stone-700">{{ event.ownerName }} · {{ event.ownerRole }}</p>
                                <p class="mt-3 text-sm leading-6 text-stone-600">{{ event.description }}</p>
                            </article>
                            <p v-if="dayEvents.length === 0" class="text-sm leading-6 text-stone-500">Nothing is scheduled on this day yet.</p>
                        </div>
                    </div>

                    <div v-else-if="activeView === 'agenda'" class="space-y-3">
                        <article
                            v-for="event in agendaEvents"
                            :key="event.id"
                            class="rounded-[26px] border border-stone-200 bg-white p-4"
                        >
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" :class="kindClass(event.kind)">
                                            {{ event.kind }}
                                        </span>
                                        <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" :class="statusClass(event.status)">
                                            {{ event.status }}
                                        </span>
                                    </div>
                                    <h4 class="text-base font-semibold text-stone-950">{{ event.title }}</h4>
                                    <p class="text-sm leading-6 text-stone-600">{{ event.description }}</p>
                                    <p class="text-xs uppercase tracking-[0.16em] text-stone-500">{{ event.ownerName }} · {{ event.ownerRole }}</p>
                                </div>

                                <div class="space-y-1 text-sm text-stone-500 lg:text-right">
                                    <p>{{ parseIsoDate(event.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}</p>
                                    <p>{{ event.time }}</p>
                                    <p>{{ event.duration }}</p>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div v-else class="grid gap-4 lg:grid-cols-3">
                        <article class="rounded-[28px] border border-stone-200 bg-white p-4">
                            <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Booked consults</p>
                            <div class="mt-4 space-y-3">
                                <div v-for="event in consultEvents.filter((event) => event.status === 'confirmed')" :key="event.id" class="rounded-[22px] border border-emerald-200 bg-emerald-50 px-3 py-3">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-emerald-800">{{ event.time }} · {{ parseIsoDate(event.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}</p>
                                    <p class="mt-1 text-sm font-medium text-stone-900">{{ event.title }}</p>
                                    <p class="mt-1 text-xs text-stone-600">{{ event.ownerName }}</p>
                                </div>
                            </div>
                        </article>

                        <article class="rounded-[28px] border border-stone-200 bg-white p-4">
                            <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Prep and holds</p>
                            <div class="mt-4 space-y-3">
                                <div v-for="event in agendaEvents.filter((event) => event.status === 'prep' || event.status === 'hold')" :key="event.id" class="rounded-[22px] border border-amber-200 bg-amber-50 px-3 py-3">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-amber-800">{{ event.time }} · {{ parseIsoDate(event.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}</p>
                                    <p class="mt-1 text-sm font-medium text-stone-900">{{ event.title }}</p>
                                    <p class="mt-1 text-xs text-stone-600">{{ event.ownerName }}</p>
                                </div>
                            </div>
                        </article>

                        <article class="rounded-[28px] border border-stone-200 bg-white p-4">
                            <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Booking links</p>
                            <div class="mt-4 space-y-3">
                                <a
                                    v-for="slot in openBookingSlots"
                                    :key="slot.id"
                                    :href="slot.href || '/settings/growth#appointments'"
                                    class="block rounded-[22px] border border-stone-200 bg-stone-50 px-3 py-3 transition hover:border-stone-400"
                                >
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">{{ slot.channel }}</p>
                                    <p class="mt-1 text-sm font-medium text-stone-900">{{ slot.label }}</p>
                                </a>
                            </div>
                        </article>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-3">
                        <article class="rounded-[30px] border border-stone-200 bg-stone-50/80 p-5">
                            <div class="flex items-center gap-3">
                                <FontAwesomeIcon :icon="faUsers" class="text-lg text-stone-700" />
                                <div>
                                    <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Calendar scope</p>
                                    <h3 class="mt-1 text-lg font-semibold text-stone-950">{{ props.scopeLabel }}</h3>
                                </div>
                            </div>
                            <p class="mt-3 text-sm leading-6 text-stone-600">{{ props.scopeSummary }}</p>

                            <div class="mt-4 grid gap-2">
                                <div v-for="user in ownerPool.slice(0, 8)" :key="user.id" class="flex items-center justify-between gap-3 rounded-2xl border border-stone-200 bg-white px-3 py-2">
                                    <span class="text-sm font-medium text-stone-900">{{ user.name }}</span>
                                    <span class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">{{ user.role_label || 'Staff' }}</span>
                                </div>
                            </div>
                        </article>

                        <article class="rounded-[30px] border border-stone-200 bg-white p-5">
                            <div class="flex items-center gap-3">
                                <FontAwesomeIcon :icon="faEnvelope" class="text-lg text-stone-700" />
                                <div>
                                    <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Booking handoff</p>
                                    <h3 class="mt-1 text-lg font-semibold text-stone-950">{{ props.portalBookingName }}</h3>
                                </div>
                            </div>
                            <p class="mt-4 text-sm leading-6 text-stone-600">
                                These booking names and links come from Growth settings, so consultation and follow-up paths stay separate from the Social / Meta content calendar.
                            </p>
                        </article>

                        <article class="rounded-[30px] border border-stone-200 bg-white p-5">
                            <div class="flex items-center gap-3">
                                <FontAwesomeIcon :icon="faClock" class="text-lg text-stone-700" />
                                <div>
                                    <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Owner visibility</p>
                                    <h3 class="mt-1 text-lg font-semibold text-stone-950">
                                        {{ props.canViewEveryone ? 'Whole-office view active' : 'Scoped calendar view' }}
                                    </h3>
                                </div>
                            </div>
                            <p class="mt-4 text-sm leading-6 text-stone-600">
                                {{
                                    props.canViewEveryone
                                        ? 'Owner and admin accounts can review every staff calendar from this page.'
                                        : 'Managers and staff see the calendar lanes allowed by the current ACL tree.'
                                }}
                            </p>
                        </article>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
