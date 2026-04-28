<script setup lang="ts">
import { computed, ref } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import type { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import {
    faCalendarDays,
    faClock,
    faListCheck,
    faSliders,
    faTableColumns,
    faVideo,
    faWandSparkles,
} from '@fortawesome/free-solid-svg-icons';
import SocialPlatformMark from '@/components/creditsoft/SocialPlatformMark.vue';

type CalendarView = 'month' | 'week' | 'day' | 'agenda' | 'meetings';
type CalendarRailItem = {
    key: CalendarView;
    label: string;
    icon: IconDefinition;
};

type PlannerEvent = {
    id: string;
    title: string;
    date: string;
    time: string;
    duration: string;
    platform: 'Meta' | 'Instagram' | 'Threads' | 'WhatsApp' | 'Meetings';
    lane: 'content' | 'meeting';
    format: string;
    status: 'ai-ready' | 'approved' | 'scheduled' | 'recording';
    description: string;
};

const props = withDefaults(defineProps<{
    pageName?: string | null;
    adAccountName?: string | null;
    defaultCta?: string | null;
    defaultDestination?: string | null;
    autoPublishReleases?: boolean;
    autoPublishFeatures?: boolean;
    autoPublishReviews?: boolean;
    facebookPublishingReady?: boolean;
    instagramBusinessId?: string | null;
    instagramUsername?: string | null;
    instagramPublishingReady?: boolean;
    threadsUsername?: string | null;
    whatsappReady?: boolean;
    eyebrow?: string;
    title?: string;
    description?: string;
    settingsHref?: string;
}>(), {
    pageName: null,
    adAccountName: null,
    defaultCta: 'learn_more',
    defaultDestination: 'website',
    autoPublishReleases: false,
    autoPublishFeatures: false,
    autoPublishReviews: false,
    facebookPublishingReady: false,
    instagramBusinessId: null,
    instagramUsername: null,
    instagramPublishingReady: false,
    threadsUsername: null,
    whatsappReady: false,
    eyebrow: 'Content calendar',
    title: 'Plan Meta content like a real calendar, not a stack of notes.',
    description: 'The reference you shared uses a true month, week, and day structure, so this lane does too. AI planning keeps the content mix moving while regular meeting views handle consults, reminders, and booking flow.',
    settingsHref: '/settings/social',
});

const toIsoDate = (value: Date) => {
    const year = value.getFullYear();
    const month = `${value.getMonth() + 1}`.padStart(2, '0');
    const day = `${value.getDate()}`.padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const TODAY = toIsoDate(new Date());

const plannerEvents: PlannerEvent[] = [
    {
        id: 'bureau-myth-reel',
        title: 'AI hook refresh for bureau myth reel',
        date: '2026-04-14',
        time: '9:00 AM',
        duration: '45 min',
        platform: 'Meta',
        lane: 'content',
        format: 'Short video',
        status: 'ai-ready',
        description: 'Turn a support FAQ into a tighter opener, caption, and CTA before it goes out.',
    },
    {
        id: 'demo-reminders',
        title: 'Demo reminders and reschedules',
        date: '2026-04-14',
        time: '1:30 PM',
        duration: '20 min',
        platform: 'WhatsApp',
        lane: 'meeting',
        format: 'Reminder lane',
        status: 'scheduled',
        description: 'Push same-day reminders and reschedule links to booked consults.',
    },
    {
        id: 'client-win-carousel',
        title: 'Client win carousel',
        date: '2026-04-15',
        time: '11:00 AM',
        duration: '1 hr',
        platform: 'Instagram',
        lane: 'content',
        format: 'Carousel',
        status: 'approved',
        description: 'AI turns approved reviews into slides with a cleaner before-and-after story arc.',
    },
    {
        id: 'metro2-live',
        title: 'Metro2 FAQ live clip',
        date: '2026-04-16',
        time: '3:00 PM',
        duration: '30 min',
        platform: 'Meta',
        lane: 'content',
        format: 'Talking-head live',
        status: 'scheduled',
        description: 'Schedule the explainer slot and reuse the talking points for Stories later.',
    },
    {
        id: 'consult-window',
        title: 'Booked consult block',
        date: '2026-04-17',
        time: '2:00 PM',
        duration: '60 min',
        platform: 'Meetings',
        lane: 'meeting',
        format: 'Booked calls',
        status: 'scheduled',
        description: 'Reserved scheduling lane for demos, onboarding walkthroughs, and follow-ups.',
    },
    {
        id: 'cta-reel',
        title: 'Book-now CTA reel',
        date: '2026-04-19',
        time: '10:30 AM',
        duration: '35 min',
        platform: 'Meta',
        lane: 'content',
        format: 'CTA reel',
        status: 'recording',
        description: 'A lighter reel that points traffic into the consultation and WhatsApp handoff lanes.',
    },
    {
        id: 'follow-up-scripts',
        title: 'AI follow-up scripts for no-shows',
        date: '2026-04-20',
        time: '8:45 AM',
        duration: '25 min',
        platform: 'WhatsApp',
        lane: 'meeting',
        format: 'Follow-up',
        status: 'ai-ready',
        description: 'Write two quick variations for no-show recovery and soft reschedule outreach.',
    },
    {
        id: 'feature-post',
        title: 'Feature spotlight and proof post',
        date: '2026-04-21',
        time: '12:15 PM',
        duration: '40 min',
        platform: 'Instagram',
        lane: 'content',
        format: 'Feature post',
        status: 'scheduled',
        description: 'Package one real workflow improvement into a visual social proof post.',
    },
    {
        id: 'threads-build-note',
        title: 'Build-in-public Threads note',
        date: '2026-04-22',
        time: '9:20 AM',
        duration: '15 min',
        platform: 'Threads',
        lane: 'content',
        format: 'Short text post',
        status: 'ai-ready',
        description: 'Turn the week\'s product progress into a compact manual-ready Threads post.',
    },
    {
        id: 'calendar-cleanup',
        title: 'Calendar cleanup and next-week planning',
        date: '2026-04-23',
        time: '4:00 PM',
        duration: '35 min',
        platform: 'Meetings',
        lane: 'meeting',
        format: 'Planning block',
        status: 'approved',
        description: 'Audit open consult slots, move unconfirmed calls, and refill next week.',
    },
];

const calendarViews: CalendarRailItem[] = [
    { key: 'month', label: 'Month', icon: faCalendarDays },
    { key: 'week', label: 'Week', icon: faTableColumns },
    { key: 'day', label: 'Day', icon: faClock },
    { key: 'agenda', label: 'Agenda', icon: faListCheck },
    { key: 'meetings', label: 'Meetings', icon: faVideo },
];

const weekdayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const activeView = ref<CalendarView>('month');
const selectedDate = ref(TODAY);

const parseIsoDate = (value: string) => new Date(`${value}T12:00:00`);

const statusClass = (status: PlannerEvent['status']) => {
    switch (status) {
        case 'approved':
            return 'border-emerald-200 bg-emerald-50 text-emerald-800';
        case 'scheduled':
            return 'border-sky-200 bg-sky-50 text-sky-800';
        case 'recording':
            return 'border-violet-200 bg-violet-50 text-violet-800';
        default:
            return 'border-amber-200 bg-amber-50 text-amber-800';
    }
};

const platformClass = (platform: PlannerEvent['platform']) => {
    switch (platform) {
        case 'Instagram':
            return 'border-fuchsia-200 bg-fuchsia-50 text-fuchsia-800';
        case 'Threads':
            return 'border-stone-300 bg-stone-950 text-white';
        case 'WhatsApp':
            return 'border-emerald-200 bg-emerald-50 text-emerald-800';
        case 'Meetings':
            return 'border-stone-300 bg-stone-100 text-stone-700';
        default:
            return 'border-[#0866ff]/15 bg-[#0866ff]/10 text-[#0866ff]';
    }
};

const selectedDateLabel = computed(() =>
    parseIsoDate(selectedDate.value).toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
    }),
);

const currentMonthLabel = computed(() =>
    parseIsoDate(selectedDate.value).toLocaleDateString('en-US', {
        month: 'long',
        year: 'numeric',
    }),
);

const activeViewMeta = computed(() =>
    calendarViews.find((view) => view.key === activeView.value) ?? calendarViews[0],
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
        const events = plannerEvents.filter((event) => event.date === iso);

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
            events: plannerEvents.filter((event) => event.date === iso),
        };
    });
});

const dayEvents = computed(() =>
    plannerEvents.filter((event) => event.date === selectedDate.value),
);

const agendaEvents = computed(() =>
    [...plannerEvents].sort((left, right) => {
        const leftKey = `${left.date} ${left.time}`;
        const rightKey = `${right.date} ${right.time}`;

        return leftKey.localeCompare(rightKey);
    }),
);

const meetingEvents = computed(() =>
    agendaEvents.value.filter((event) => event.lane === 'meeting'),
);

const openSlots = computed(() => [
    {
        label: 'Thursday open consult',
        time: 'Apr 16 · 1:00 PM',
    },
    {
        label: 'Friday overflow booking',
        time: 'Apr 17 · 4:30 PM',
    },
    {
        label: 'Monday catch-up window',
        time: 'Apr 20 · 2:15 PM',
    },
]);

const destinationLabel = computed(() => {
    switch (props.defaultDestination) {
        case 'native_lead_form':
            return 'native lead forms';
        case 'messenger':
            return 'Messenger';
        case 'whatsapp':
            return 'WhatsApp';
        default:
            return 'the website';
    }
});

const cleanHandle = (value?: string | null) => (value ?? '').trim().replace(/^@+/, '');
const instagramHandle = computed(() => cleanHandle(props.instagramUsername));
const threadsHandle = computed(() => cleanHandle(props.threadsUsername));

const platformReadiness = computed(() => [
    {
        brand: 'meta' as const,
        label: 'Facebook Page',
        status: props.facebookPublishingReady ? 'API publish ready' : 'Drafts until approval',
        copy: props.facebookPublishingReady
            ? 'Page publishing is enabled. The next step is the guarded composer and audit trail.'
            : 'Keep approved copy queued here while pages_manage_posts and app review finish.',
        href: '/settings/social/publishing',
        tone: props.facebookPublishingReady ? 'ready' : 'blocked',
    },
    {
        brand: 'instagram' as const,
        label: 'Instagram',
        status: props.instagramPublishingReady ? 'IG review lane ready' : (props.instagramBusinessId ? 'IG account identified' : 'Profile/draft mode'),
        copy: props.instagramBusinessId
            ? 'Use the saved IG account for captions, media checks, and future media_publish jobs.'
            : (instagramHandle.value
                ? `Drafts can target @${instagramHandle.value}; save the IG professional account when Meta returns it.`
                : 'Save the IG professional account or handle, then keep captions and media URLs ready.'),
        href: '/settings/social/instagram',
        tone: props.instagramPublishingReady ? 'ready' : (props.instagramBusinessId ? 'staged' : 'blocked'),
    },
    {
        brand: 'threads' as const,
        label: 'Threads',
        status: threadsHandle.value ? 'Manual profile ready' : 'Handle needed',
        copy: threadsHandle.value
            ? `Drafts can target @${threadsHandle.value}; API posting waits for the separate Threads token lane.`
            : 'Add the Threads handle so this calendar can prepare manual-ready text posts.',
        href: '/settings/social/threads',
        tone: threadsHandle.value ? 'staged' : 'blocked',
    },
]);

const platformReadinessClass = (tone: string) => {
    if (tone === 'ready') {
        return 'border-emerald-200 bg-emerald-50/80';
    }

    if (tone === 'staged') {
        return 'border-sky-200 bg-sky-50/80';
    }

    return 'border-amber-200 bg-amber-50/80';
};

const aiPlanCards = computed(() => [
    {
        title: 'AI posting pulse',
        copy: props.autoPublishFeatures
            ? 'Feature updates are allowed to become social drafts, so the planner keeps a mid-week spotlight ready.'
            : 'Turn on feature auto-posting and the planner will keep a mid-week feature spotlight slot warmed up.',
    },
    {
        title: 'Proof and review loop',
        copy: props.autoPublishReviews
            ? 'Approved praise can be reshaped into carousel copy, quote tiles, and a lighter Friday proof post.'
            : 'Review auto-posting is still off, so AI is holding proof-content slots as manual drafts only.',
    },
    {
        title: 'CTA and booking handoff',
        copy: `The current CTA leans toward ${String(props.defaultCta ?? 'learn_more').replace(/_/g, ' ')}, with traffic pointed at ${destinationLabel.value}.`,
    },
    {
        title: 'Meeting follow-up lane',
        copy: props.whatsappReady
            ? 'WhatsApp is ready, so booked consults can flow into reminder and reschedule templates instead of plain email.'
            : 'WhatsApp is not fully ready yet, so the planner keeps reminder slots but treats chat handoff as pending.',
    },
]);

const jumpRange = (direction: number) => {
    const next = parseIsoDate(selectedDate.value);

    if (activeView.value === 'month') {
        next.setMonth(next.getMonth() + direction);
    } else if (activeView.value === 'week' || activeView.value === 'meetings') {
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
    <section id="social-calendar" class="overflow-hidden rounded-[32px] border border-stone-300/70 bg-white/95 shadow-[0_24px_60px_rgba(15,23,42,0.08)]">
        <div class="border-b border-stone-200/80 px-6 py-5 lg:px-8">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div class="space-y-2">
                    <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500">{{ props.eyebrow }}</p>
                    <h2 class="text-xl font-semibold tracking-tight text-stone-950 sm:text-[1.8rem]">
                        {{ props.title }}
                    </h2>
                    <p class="max-w-3xl text-sm leading-7 text-stone-600">
                        {{ props.description }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-stone-50 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-700">
                        <FontAwesomeIcon :icon="activeViewMeta.icon" />
                        {{ activeViewMeta.label }}
                    </span>
                    <a :href="props.settingsHref" class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-stone-50 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-500 hover:text-stone-950">
                        <FontAwesomeIcon :icon="faSliders" />
                        Settings
                    </a>
                </div>
            </div>
        </div>

        <div class="pl-0 pr-6 py-6 xl:pr-8 xl:py-8">
            <div class="grid gap-2 lg:grid-cols-[34px_minmax(0,1fr)] lg:items-start">
                <aside class="lg:sticky lg:top-6">
                    <div class="flex gap-2 overflow-x-auto lg:flex-col lg:items-start lg:overflow-visible">
                        <button
                            v-for="view in calendarViews"
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
                        <span class="hidden h-6 w-px bg-stone-200 lg:block" />
                        <a
                            :href="props.settingsHref"
                            class="inline-flex size-8 shrink-0 items-center justify-center text-[17px] text-stone-400 transition hover:text-stone-900"
                            aria-label="Social settings"
                            title="Social settings"
                        >
                            <FontAwesomeIcon :icon="faSliders" class="lg:translate-x-[5px]" />
                        </a>
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
                            {{ pageName || 'No page selected yet' }}<span v-if="adAccountName"> · {{ adAccountName }}</span>
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 xl:grid-cols-3">
                    <article
                        v-for="item in platformReadiness"
                        :key="item.label"
                        class="rounded-[26px] border px-4 py-4"
                        :class="platformReadinessClass(item.tone)"
                    >
                        <div class="flex items-start gap-3">
                            <SocialPlatformMark
                                :brand="item.brand"
                                compact
                                monochrome
                                :class="[
                                    item.brand === 'meta' ? 'text-[#0866ff]' : '',
                                    item.brand === 'instagram' ? 'text-[#e4405f]' : '',
                                    item.brand === 'threads' ? 'text-stone-950' : '',
                                ]"
                            />
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-stone-950">{{ item.label }}</p>
                                <p class="mt-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-stone-500">{{ item.status }}</p>
                            </div>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-stone-700">{{ item.copy }}</p>
                        <a
                            :href="item.href"
                            class="mt-4 inline-flex text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:text-stone-950"
                        >
                            Open setup
                        </a>
                    </article>
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
                                    class="rounded-2xl border px-2 py-2"
                                    :class="event.lane === 'meeting' ? 'border-emerald-200 bg-emerald-50/70' : 'border-[#0866ff]/15 bg-[#0866ff]/8'"
                                >
                                    <p class="truncate text-[10px] font-semibold uppercase tracking-[0.15em]" :class="event.lane === 'meeting' ? 'text-emerald-800' : 'text-[#0866ff]'">
                                        {{ event.time }}
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
                                class="block w-full rounded-[22px] border px-3 py-3 text-left"
                                :class="event.lane === 'meeting' ? 'border-emerald-200 bg-emerald-50/80' : 'border-stone-200 bg-stone-50'"
                                @click="selectedDate = event.date"
                            >
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">{{ event.time }} · {{ event.duration }}</p>
                                <p class="mt-1 text-sm font-medium text-stone-900">{{ event.title }}</p>
                                <p class="mt-1 text-sm leading-6 text-stone-600">{{ event.format }}</p>
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
                            class="rounded-[24px] border px-4 py-4"
                            :class="event.lane === 'meeting' ? 'border-emerald-200 bg-emerald-50/70' : 'border-stone-200 bg-stone-50'"
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
                            <p class="mt-3 text-sm leading-6 text-stone-600">{{ event.description }}</p>
                        </article>
                        <p v-if="dayEvents.length === 0" class="text-sm leading-6 text-stone-500">Nothing is planned on this day yet.</p>
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
                                    <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" :class="platformClass(event.platform)">
                                        {{ event.platform }}
                                    </span>
                                    <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" :class="statusClass(event.status)">
                                        {{ event.status }}
                                    </span>
                                </div>
                                <h4 class="text-base font-semibold text-stone-950">{{ event.title }}</h4>
                                <p class="text-sm leading-6 text-stone-600">{{ event.description }}</p>
                            </div>

                            <div class="space-y-1 text-sm text-stone-500 lg:text-right">
                                <p>{{ parseIsoDate(event.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}</p>
                                <p>{{ event.time }}</p>
                                <p>{{ event.format }}</p>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="grid gap-4 lg:grid-cols-3">
                    <article class="rounded-[28px] border border-stone-200 bg-white p-4">
                        <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Booked</p>
                        <div class="mt-4 space-y-3">
                            <div v-for="event in meetingEvents.filter((event) => event.status === 'scheduled' || event.status === 'approved')" :key="event.id" class="rounded-[22px] border border-emerald-200 bg-emerald-50 px-3 py-3">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-emerald-800">{{ event.time }} · {{ parseIsoDate(event.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}</p>
                                <p class="mt-1 text-sm font-medium text-stone-900">{{ event.title }}</p>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-[28px] border border-stone-200 bg-white p-4">
                        <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Prep and follow-up</p>
                        <div class="mt-4 space-y-3">
                            <div v-for="event in meetingEvents.filter((event) => event.status === 'ai-ready' || event.status === 'recording')" :key="event.id" class="rounded-[22px] border border-amber-200 bg-amber-50 px-3 py-3">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-amber-800">{{ event.time }} · {{ parseIsoDate(event.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}</p>
                                <p class="mt-1 text-sm font-medium text-stone-900">{{ event.title }}</p>
                            </div>
                        </div>
                    </article>

                    <article class="rounded-[28px] border border-stone-200 bg-white p-4">
                        <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Open slots</p>
                        <div class="mt-4 space-y-3">
                            <div v-for="slot in openSlots" :key="slot.label" class="rounded-[22px] border border-stone-200 bg-stone-50 px-3 py-3">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-stone-500">{{ slot.time }}</p>
                                <p class="mt-1 text-sm font-medium text-stone-900">{{ slot.label }}</p>
                            </div>
                        </div>
                    </article>
                </div>
                <div class="grid gap-4 xl:grid-cols-3">
                    <article class="rounded-[30px] border border-stone-200 bg-stone-50/80 p-5">
                    <div class="flex items-center gap-3">
                        <FontAwesomeIcon :icon="faWandSparkles" class="text-lg text-stone-700" />
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">AI planner</p>
                            <h3 class="mt-1 text-lg font-semibold text-stone-950">Weekly content plan</h3>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        <article v-for="card in aiPlanCards" :key="card.title" class="rounded-[24px] border border-stone-200 bg-white px-4 py-4">
                            <p class="text-sm font-medium text-stone-900">{{ card.title }}</p>
                            <p class="mt-2 text-sm leading-6 text-stone-600">{{ card.copy }}</p>
                        </article>
                    </div>
                    </article>

                    <article class="rounded-[30px] border border-stone-200 bg-white p-5">
                    <div class="flex items-center gap-3">
                        <FontAwesomeIcon :icon="faTableColumns" class="text-lg text-stone-700" />
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Selected day</p>
                            <h3 class="mt-1 text-lg font-semibold text-stone-950">{{ selectedDateLabel }}</h3>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        <article v-for="event in dayEvents" :key="event.id" class="rounded-[22px] border border-stone-200 bg-stone-50 px-4 py-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" :class="platformClass(event.platform)">
                                    {{ event.platform }}
                                </span>
                                <span class="inline-flex rounded-full border px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" :class="statusClass(event.status)">
                                    {{ event.status }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm font-medium text-stone-900">{{ event.title }}</p>
                            <p class="mt-1 text-sm leading-6 text-stone-600">{{ event.time }} · {{ event.duration }}</p>
                        </article>
                        <p v-if="dayEvents.length === 0" class="text-sm leading-6 text-stone-500">Nothing is planned on this date yet.</p>
                    </div>
                    </article>

                    <article class="rounded-[30px] border border-stone-200 bg-white p-5">
                    <div class="flex items-center gap-3">
                        <FontAwesomeIcon :icon="faClock" class="text-lg text-stone-700" />
                        <div>
                            <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">Regular views</p>
                            <h3 class="mt-1 text-lg font-semibold text-stone-950">Scheduling lane</h3>
                        </div>
                    </div>
                    <p class="mt-4 text-sm leading-6 text-stone-600">
                        Use the meetings view for consultations, reminders, prep, and no-show recovery. The content calendar stays social-first, but the regular booking flow still lives right beside it.
                    </p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-stone-50 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-500 hover:text-stone-950" @click="activeView = 'agenda'">
                            <FontAwesomeIcon :icon="faListCheck" />
                            Agenda
                        </button>
                        <button type="button" class="inline-flex items-center gap-2 rounded-full border border-stone-300 bg-stone-50 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-500 hover:text-stone-950" @click="activeView = 'meetings'">
                            <SocialPlatformMark brand="whatsapp" compact monochrome class="text-[#25d366]" />
                            Meetings
                        </button>
                    </div>
                    </article>
                </div>
                </div>
            </div>
        </div>
    </section>
</template>
