<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import DashboardWorkspaceNav from '@/components/creditsoft/DashboardWorkspaceNav.vue';
import { formatDate } from '@/lib/creditsoft';

defineProps<{
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
</script>

<template>
    <Head title="Inbox" />

    <h1 class="sr-only">Inbox</h1>

    <div class="space-y-8">
        <section class="flex items-start justify-between gap-4 border-b border-stone-300/70 pb-4">
            <div>
                <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Office inbox</p>
                <h2 class="text-2xl font-semibold tracking-tight text-stone-950">Inbox</h2>
                <p class="text-sm text-stone-600">Due work, unresolved reviews, and missing-owner items that need attention.</p>
            </div>
            <DashboardWorkspaceNav />
        </section>

        <div class="grid gap-8 xl:grid-cols-2">
            <section class="space-y-3">
                <div class="border-b border-stone-300/70 pb-3">
                    <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Due soon</p>
                    <p class="text-sm text-stone-600">Tasks close to deadline or still missing owners.</p>
                </div>
                <div class="space-y-3">
                    <div v-for="task in tasks" :key="task.id" class="rounded-[24px] border border-stone-300/70 bg-stone-50/70 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-medium text-stone-950">{{ task.title }}</p>
                                <Link v-if="task.client" :href="`/clients/${task.client.id}`" class="text-sm text-stone-500">
                                    {{ task.client.display_name ?? `${task.client.first_name ?? ''} ${task.client.last_name ?? ''}`.trim() }}
                                </Link>
                                <p v-else class="text-sm text-stone-500">Operations reminder</p>
                                <p v-if="task.details" class="mt-1 text-xs leading-5 text-stone-500">{{ task.details }}</p>
                            </div>
                            <div class="text-right text-[11px] uppercase tracking-[0.22em] text-stone-500">
                                <p>{{ task.priority }}</p>
                                <p>{{ task.due_at ? formatDate(task.due_at) : 'No due date' }}</p>
                                <Link
                                    v-if="task.system_item && task.action_href"
                                    :href="task.action_href"
                                    class="mt-2 inline-flex text-[10px] font-medium uppercase tracking-[0.18em] text-stone-700 underline underline-offset-4"
                                >
                                    {{ task.action_label ?? 'Open' }}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="space-y-3">
                <div class="border-b border-stone-300/70 pb-3">
                    <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">High-severity review</p>
                    <p class="text-sm text-stone-600">Violations that should move to confirmed or resolved next.</p>
                </div>
                <div class="space-y-3">
                    <div v-for="violation in violationsNeedingReview" :key="violation.id" class="rounded-[24px] border border-stone-300/70 bg-stone-50/70 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="font-medium text-stone-950">{{ violation.title }}</p>
                                <Link v-if="violation.client" :href="`/clients/${violation.client.id}/violations`" class="text-sm text-stone-500">
                                    {{ violation.client.display_name ?? `${violation.client.first_name ?? ''} ${violation.client.last_name ?? ''}`.trim() }}
                                </Link>
                            </div>
                            <span class="rounded-full border border-amber-300 bg-amber-100 px-2 py-1 text-[11px] uppercase tracking-[0.18em] text-amber-900">
                                {{ violation.severity }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
