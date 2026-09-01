<script setup lang="ts">
import { faTrashCan } from '@fortawesome/free-regular-svg-icons';
import { faCheck } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DashboardWorkspaceNav from '@/components/creditsoft/DashboardWorkspaceNav.vue';
import { formatDate } from '@/lib/creditsoft';

const props = defineProps<{
    counts: {
        leads: number;
        tasks: number;
        reviews: number;
        reminders: number;
        total: number;
    };
    leadInbox: Array<{
        id: number;
        display_name: string;
        status?: string | null;
        source_label: string;
        assigned_user?: string | null;
        email?: string | null;
        avatar_url?: string | null;
        contact_label: string;
        profile_url?: string | null;
        updated_at?: string | null;
        updated_label?: string | null;
        provider_account_count: number;
        document_file_count: number;
        payment_count: number;
        billing_status?: string | null;
        needs_provider_credentials: boolean;
        has_files: boolean;
        has_payment: boolean;
        href: string;
        initials: string;
    }>;
    leadPagination: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from?: number | null;
        to?: number | null;
        has_more_pages: boolean;
    };
    tasks: Array<{
        id: number | string;
        title: string;
        details?: string | null;
        status: string;
        priority: string;
        due_at?: string | null;
        client?: {
            id: number;
            display_name?: string;
            first_name?: string;
            last_name?: string;
        } | null;
        system_item?: boolean;
        action_href?: string | null;
        action_label?: string | null;
    }>;
    violationsNeedingReview: Array<{
        id: number;
        title: string;
        severity: string;
        client?: {
            id: number;
            display_name?: string;
            first_name?: string;
            last_name?: string;
        } | null;
    }>;
}>();

const totalOpen = computed(() => props.counts.total ?? 0);
const avatarFailures = ref<Set<number>>(new Set());
const armedLeadDeleteId = ref<number | null>(null);
const deletingLeadId = ref<number | null>(null);
const reminderItems = computed(() =>
    props.tasks.filter((task) => Boolean(task.system_item)),
);
const taskItems = computed(() =>
    props.tasks.filter((task) => !Boolean(task.system_item)),
);
const leadPageNumbers = computed(() => {
    const current = props.leadPagination.current_page;
    const last = props.leadPagination.last_page;

    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    return [...new Set([1, current - 1, current, current + 1, last])]
        .filter((page) => page >= 1 && page <= last)
        .sort((a, b) => a - b);
});

const clientName = (
    client?: {
        display_name?: string;
        first_name?: string;
        last_name?: string;
    } | null,
) =>
    (client?.display_name ??
        `${client?.first_name ?? ''} ${client?.last_name ?? ''}`.trim()) ||
    'Client file';

const canPatchTask = (task: { id: number | string; system_item?: boolean }) =>
    !task.system_item && Number.isFinite(Number(task.id));

const hasLeadAvatar = (lead: (typeof props.leadInbox)[number]) =>
    Boolean(lead.avatar_url) && !avatarFailures.value.has(lead.id);

const markAvatarFailed = (lead: (typeof props.leadInbox)[number]) => {
    avatarFailures.value = new Set([...avatarFailures.value, lead.id]);
};

const leadPageHref = (page: number) => `/inbox?lead_page=${page}#lead-intake`;

const updateTask = (
    task: (typeof props.tasks)[number],
    status: 'in_progress' | 'done',
) => {
    if (!canPatchTask(task)) {
        return;
    }

    router.patch(
        `/tasks/${task.id}`,
        { status },
        {
            preserveScroll: true,
            preserveState: false,
        },
    );
};

const promoteLead = (lead: (typeof props.leadInbox)[number]) => {
    router.post(
        `/clients/${lead.id}/promote`,
        { return_to: 'inbox' },
        {
            preserveScroll: true,
            preserveState: false,
        },
    );
};

const reviewLead = (lead: (typeof props.leadInbox)[number]) => {
    router.post(
        `/clients/${lead.id}/review-lead`,
        { return_to: 'inbox' },
        {
            preserveScroll: true,
            preserveState: false,
        },
    );
};

const deleteLead = (lead: (typeof props.leadInbox)[number]) => {
    if (armedLeadDeleteId.value !== lead.id) {
        armedLeadDeleteId.value = lead.id;

        return;
    }

    deletingLeadId.value = lead.id;
    router.delete(`/clients/${lead.id}`, {
        data: { return_to: 'inbox' },
        preserveScroll: true,
        preserveState: false,
        onFinish: () => {
            deletingLeadId.value = null;
            armedLeadDeleteId.value = null;
        },
    });
};
</script>

<template>
    <Head title="Inbox" />

    <h1 class="sr-only">Inbox</h1>

    <div class="space-y-8">
        <section
            class="flex flex-col gap-5 border-b border-stone-300/70 pb-5 xl:flex-row xl:items-end xl:justify-between"
        >
            <div>
                <p
                    class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                >
                    Office inbox
                </p>
                <h2
                    class="text-3xl font-semibold tracking-tight text-stone-950"
                >
                    Inbox
                </h2>
                <p class="mt-1 text-sm text-stone-600">
                    {{ totalOpen }} open
                    {{ totalOpen === 1 ? 'item' : 'items' }}
                </p>
            </div>
            <DashboardWorkspaceNav />
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <a
                href="#lead-intake"
                aria-label="Jump to lead intake"
                class="rounded-lg border border-stone-300/70 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-stone-400 hover:shadow-md focus:ring-2 focus:ring-stone-950 focus:ring-offset-2 focus:outline-none"
            >
                <p
                    class="text-[11px] font-medium tracking-[0.26em] text-stone-500 uppercase"
                >
                    Leads
                </p>
                <p
                    class="mt-3 text-3xl font-semibold tracking-tight text-stone-950"
                >
                    {{ counts.leads }}
                </p>
            </a>
            <a
                href="#tasks"
                aria-label="Jump to tasks"
                class="rounded-lg border border-stone-300/70 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-stone-400 hover:shadow-md focus:ring-2 focus:ring-stone-950 focus:ring-offset-2 focus:outline-none"
            >
                <p
                    class="text-[11px] font-medium tracking-[0.26em] text-stone-500 uppercase"
                >
                    Tasks
                </p>
                <p
                    class="mt-3 text-3xl font-semibold tracking-tight text-stone-950"
                >
                    {{ counts.tasks }}
                </p>
            </a>
            <a
                href="#reviews"
                aria-label="Jump to reviews"
                class="rounded-lg border border-stone-300/70 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-stone-400 hover:shadow-md focus:ring-2 focus:ring-stone-950 focus:ring-offset-2 focus:outline-none"
            >
                <p
                    class="text-[11px] font-medium tracking-[0.26em] text-stone-500 uppercase"
                >
                    Reviews
                </p>
                <p
                    class="mt-3 text-3xl font-semibold tracking-tight text-stone-950"
                >
                    {{ counts.reviews }}
                </p>
            </a>
            <a
                href="#reminders"
                aria-label="Jump to reminders"
                class="rounded-lg border border-stone-300/70 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-stone-400 hover:shadow-md focus:ring-2 focus:ring-stone-950 focus:ring-offset-2 focus:outline-none"
            >
                <p
                    class="text-[11px] font-medium tracking-[0.26em] text-stone-500 uppercase"
                >
                    Reminders
                </p>
                <p
                    class="mt-3 text-3xl font-semibold tracking-tight text-stone-950"
                >
                    {{ counts.reminders }}
                </p>
            </a>
        </section>

        <section id="lead-intake" class="scroll-mt-28 space-y-3">
            <div
                class="flex flex-col gap-3 border-b border-stone-300/70 pb-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        Lead intake
                    </p>
                    <p class="text-sm text-stone-600">
                        {{ counts.leads }} waiting to move, review, or delete.
                        <span
                            v-if="leadPagination.total"
                            class="text-stone-500"
                        >
                            Showing {{ leadPagination.from }}-{{
                                leadPagination.to
                            }}.
                        </span>
                    </p>
                </div>
                <Link
                    href="/clients?view=leads"
                    class="text-sm font-medium text-stone-800 underline underline-offset-4"
                >
                    Open leads roster
                </Link>
            </div>

            <div v-if="leadInbox.length" class="grid gap-3 2xl:grid-cols-2">
                <article
                    v-for="lead in leadInbox"
                    :key="lead.id"
                    class="rounded-lg border border-stone-300/70 bg-white p-4 shadow-sm"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex items-start gap-3">
                                <img
                                    v-if="hasLeadAvatar(lead)"
                                    :src="lead.avatar_url ?? undefined"
                                    :alt="`${lead.display_name} Gravatar`"
                                    class="h-10 w-10 shrink-0 rounded-lg object-cover"
                                    loading="lazy"
                                    referrerpolicy="no-referrer"
                                    @error="markAvatarFailed(lead)"
                                />
                                <div
                                    v-else
                                    class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-stone-950 text-sm font-semibold text-white"
                                >
                                    {{ lead.initials || 'L' }}
                                </div>
                                <div class="min-w-0">
                                    <Link
                                        :href="lead.href"
                                        class="text-lg font-semibold tracking-tight text-stone-950 hover:underline"
                                    >
                                        {{ lead.display_name }}
                                    </Link>
                                    <p class="text-sm text-stone-600">
                                        {{ lead.contact_label }}
                                    </p>
                                    <p class="text-xs leading-5 text-stone-500">
                                        {{ lead.source_label }} ·
                                        {{
                                            lead.updated_label ??
                                            'Recently updated'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <dl
                                class="mt-4 grid gap-2 text-xs text-stone-600 sm:grid-cols-2 lg:grid-cols-4"
                            >
                                <div>
                                    <dt class="font-medium text-stone-950">
                                        Provider
                                    </dt>
                                    <dd>
                                        {{
                                            lead.provider_account_count
                                                ? `${lead.provider_account_count} saved`
                                                : 'Missing'
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-stone-950">
                                        Files
                                    </dt>
                                    <dd>
                                        {{
                                            lead.document_file_count
                                                ? `${lead.document_file_count} attached`
                                                : 'None yet'
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-stone-950">
                                        Payment
                                    </dt>
                                    <dd>
                                        {{
                                            lead.payment_count
                                                ? `${lead.payment_count} record`
                                                : 'No record'
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-stone-950">
                                        Owner
                                    </dt>
                                    <dd>
                                        {{ lead.assigned_user ?? 'Unassigned' }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div
                            class="flex shrink-0 flex-wrap gap-2 lg:justify-end"
                        >
                            <Link
                                :href="lead.href"
                                class="inline-flex h-9 items-center rounded-md border border-stone-300 bg-white px-3 text-sm font-medium text-stone-800 shadow-sm transition hover:bg-stone-50"
                            >
                                Open
                            </Link>
                            <button
                                type="button"
                                class="inline-flex h-9 items-center rounded-md bg-stone-950 px-3 text-sm font-medium text-white shadow-sm transition hover:bg-stone-800"
                                @click="promoteLead(lead)"
                            >
                                Move to clients
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-9 items-center gap-2 rounded-md border border-stone-300 bg-white px-3 text-sm font-medium text-stone-800 shadow-sm transition hover:bg-stone-50"
                                @click="reviewLead(lead)"
                            >
                                <FontAwesomeIcon
                                    :icon="faCheck"
                                    class="h-4 w-4 text-emerald-700"
                                />
                                Reviewed
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center text-red-600 transition hover:text-red-800 disabled:cursor-not-allowed disabled:opacity-50"
                                :aria-label="
                                    armedLeadDeleteId === lead.id
                                        ? `Confirm delete ${lead.display_name}`
                                        : `Delete ${lead.display_name}`
                                "
                                :disabled="deletingLeadId === lead.id"
                                :title="
                                    armedLeadDeleteId === lead.id
                                        ? 'Confirm delete'
                                        : 'Delete lead'
                                "
                                @click="deleteLead(lead)"
                            >
                                <FontAwesomeIcon
                                    :icon="faTrashCan"
                                    class="h-5 w-5"
                                    :class="
                                        armedLeadDeleteId === lead.id
                                            ? 'text-red-950'
                                            : ''
                                    "
                                />
                            </button>
                        </div>
                    </div>
                </article>
            </div>

            <div
                v-else
                class="rounded-lg border border-dashed border-stone-300 bg-stone-50 p-6 text-sm text-stone-600"
            >
                No leads are waiting in the inbox.
            </div>

            <nav
                v-if="leadPagination.last_page > 1"
                class="flex flex-col gap-3 border-t border-stone-300/70 pt-4 sm:flex-row sm:items-center sm:justify-between"
                aria-label="Lead inbox pages"
            >
                <p class="text-xs text-stone-500">
                    Page {{ leadPagination.current_page }} of
                    {{ leadPagination.last_page }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="leadPagination.current_page > 1"
                        :href="leadPageHref(leadPagination.current_page - 1)"
                        class="inline-flex h-9 items-center rounded-md border border-stone-300 bg-white px-3 text-sm font-medium text-stone-800 shadow-sm transition hover:bg-stone-50"
                    >
                        Previous
                    </Link>
                    <template
                        v-for="(page, index) in leadPageNumbers"
                        :key="page"
                    >
                        <span
                            v-if="
                                index > 0 &&
                                page - leadPageNumbers[index - 1] > 1
                            "
                            class="inline-flex h-9 items-center px-1 text-sm text-stone-400"
                        >
                            ...
                        </span>
                        <Link
                            :href="leadPageHref(page)"
                            class="inline-flex h-9 min-w-9 items-center justify-center rounded-md border px-3 text-sm font-medium shadow-sm transition"
                            :class="
                                page === leadPagination.current_page
                                    ? 'border-stone-950 bg-stone-950 text-white'
                                    : 'border-stone-300 bg-white text-stone-800 hover:bg-stone-50'
                            "
                        >
                            {{ page }}
                        </Link>
                    </template>
                    <Link
                        v-if="leadPagination.has_more_pages"
                        :href="leadPageHref(leadPagination.current_page + 1)"
                        class="inline-flex h-9 items-center rounded-md border border-stone-300 bg-white px-3 text-sm font-medium text-stone-800 shadow-sm transition hover:bg-stone-50"
                    >
                        Next
                    </Link>
                </div>
            </nav>
        </section>

        <div class="grid gap-8 xl:grid-cols-2">
            <section id="tasks" class="scroll-mt-28 space-y-3">
                <div class="border-b border-stone-300/70 pb-3">
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        Tasks
                    </p>
                    <p class="text-sm text-stone-600">
                        Open work that can be started or marked done.
                    </p>
                </div>
                <div class="space-y-3">
                    <article
                        v-for="task in taskItems"
                        :key="task.id"
                        class="rounded-lg border border-stone-300/70 bg-white p-4 shadow-sm"
                    >
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <p class="font-medium text-stone-950">
                                    {{ task.title }}
                                </p>
                                <Link
                                    v-if="task.client"
                                    :href="`/clients/${task.client.id}`"
                                    class="text-sm text-stone-500 hover:underline"
                                >
                                    {{ clientName(task.client) }}
                                </Link>
                                <p v-else class="text-sm text-stone-500">
                                    Operations reminder
                                </p>
                                <p
                                    v-if="task.details"
                                    class="mt-1 text-xs leading-5 text-stone-500"
                                >
                                    {{ task.details }}
                                </p>
                                <Link
                                    v-if="task.system_item && task.action_href"
                                    :href="task.action_href"
                                    class="mt-2 inline-flex text-xs font-medium text-stone-800 underline underline-offset-4"
                                >
                                    {{ task.action_label ?? 'Open' }}
                                </Link>
                            </div>
                            <div
                                class="flex shrink-0 flex-wrap gap-2 sm:justify-end"
                            >
                                <span
                                    class="text-xs tracking-[0.18em] text-stone-500 uppercase"
                                >
                                    {{ task.priority }} ·
                                    {{
                                        task.due_at
                                            ? formatDate(task.due_at)
                                            : 'No due date'
                                    }}
                                </span>
                                <button
                                    v-if="
                                        canPatchTask(task) &&
                                        task.status !== 'in_progress'
                                    "
                                    type="button"
                                    class="inline-flex h-8 items-center rounded-md border border-stone-300 bg-white px-3 text-xs font-medium text-stone-800 shadow-sm transition hover:bg-stone-50"
                                    @click="updateTask(task, 'in_progress')"
                                >
                                    Start
                                </button>
                                <button
                                    v-if="canPatchTask(task)"
                                    type="button"
                                    class="inline-flex h-8 items-center rounded-md bg-stone-950 px-3 text-xs font-medium text-white shadow-sm transition hover:bg-stone-800"
                                    @click="updateTask(task, 'done')"
                                >
                                    Done
                                </button>
                            </div>
                        </div>
                    </article>
                    <div
                        v-if="!taskItems.length"
                        class="rounded-lg border border-dashed border-stone-300 bg-stone-50 p-6 text-sm text-stone-600"
                    >
                        No open tasks.
                    </div>
                </div>
            </section>

            <section id="reviews" class="scroll-mt-28 space-y-3">
                <div class="border-b border-stone-300/70 pb-3">
                    <p
                        class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                    >
                        High-severity review
                    </p>
                    <p class="text-sm text-stone-600">
                        Open compliance items that need a decision.
                    </p>
                </div>
                <div class="space-y-3">
                    <article
                        v-for="violation in violationsNeedingReview"
                        :key="violation.id"
                        class="rounded-lg border border-stone-300/70 bg-white p-4 shadow-sm"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p class="font-medium text-stone-950">
                                    {{ violation.title }}
                                </p>
                                <Link
                                    v-if="violation.client"
                                    :href="`/clients/${violation.client.id}/violations`"
                                    class="text-sm text-stone-500 hover:underline"
                                >
                                    {{ clientName(violation.client) }}
                                </Link>
                            </div>
                            <div class="flex items-center gap-3">
                                <span
                                    class="text-xs tracking-[0.18em] text-amber-800 uppercase"
                                    >{{ violation.severity }}</span
                                >
                                <Link
                                    v-if="violation.client"
                                    :href="`/clients/${violation.client.id}/violations`"
                                    class="inline-flex h-8 items-center rounded-md bg-stone-950 px-3 text-xs font-medium text-white shadow-sm transition hover:bg-stone-800"
                                >
                                    Review
                                </Link>
                            </div>
                        </div>
                    </article>
                    <div
                        v-if="!violationsNeedingReview.length"
                        class="rounded-lg border border-dashed border-stone-300 bg-stone-50 p-6 text-sm text-stone-600"
                    >
                        No high-severity reviews.
                    </div>
                </div>
            </section>
        </div>

        <section id="reminders" class="scroll-mt-28 space-y-3">
            <div class="border-b border-stone-300/70 pb-3">
                <p
                    class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                >
                    Reminders
                </p>
                <p class="text-sm text-stone-600">
                    System notices that need an office-level fix.
                </p>
            </div>
            <div class="grid gap-3 xl:grid-cols-2">
                <article
                    v-for="task in reminderItems"
                    :key="task.id"
                    class="rounded-lg border border-stone-300/70 bg-white p-4 shadow-sm"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <p class="font-medium text-stone-950">
                                {{ task.title }}
                            </p>
                            <p class="text-sm text-stone-500">
                                Operations reminder
                            </p>
                            <p
                                v-if="task.details"
                                class="mt-1 text-xs leading-5 text-stone-500"
                            >
                                {{ task.details }}
                            </p>
                        </div>
                        <Link
                            v-if="task.action_href"
                            :href="task.action_href"
                            class="inline-flex h-8 shrink-0 items-center rounded-md bg-stone-950 px-3 text-xs font-medium text-white shadow-sm transition hover:bg-stone-800"
                        >
                            {{ task.action_label ?? 'Open' }}
                        </Link>
                    </div>
                </article>
                <div
                    v-if="!reminderItems.length"
                    class="rounded-lg border border-dashed border-stone-300 bg-stone-50 p-6 text-sm text-stone-600"
                >
                    No active reminders.
                </div>
            </div>
        </section>
    </div>
</template>
