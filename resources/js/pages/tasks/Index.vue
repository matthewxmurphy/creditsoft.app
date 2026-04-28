<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import DashboardWorkspaceNav from '@/components/creditsoft/DashboardWorkspaceNav.vue';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/creditsoft';

const props = defineProps<{
    tasks: Array<{
        id: number | string;
        title: string;
        details?: string | null;
        status: string;
        priority: string;
        due_at?: string | null;
        client?: {
            display_name?: string;
            first_name?: string;
            last_name?: string;
        } | null;
        system_item?: boolean;
        action_href?: string | null;
        action_label?: string | null;
    }>;
    clients: Array<{
        id: number;
        first_name: string;
        last_name: string;
    }>;
}>();

const form = useForm({
    client_id: '',
    title: '',
    details: '',
    priority: 'normal',
    due_at: '',
});

const taskActionBadgeClass = 'inline-flex min-h-[30px] items-center justify-center rounded-full border border-stone-300 bg-white px-[0.825rem] py-[0.4125rem] text-[12px] font-semibold uppercase tracking-[0.18em] text-stone-700 transition hover:border-stone-500 hover:text-stone-950';
const taskStateBadgeClass = 'inline-flex min-h-[30px] items-center justify-center rounded-full border border-amber-400 bg-amber-400 px-[0.825rem] py-[0.4125rem] text-[12px] font-bold uppercase tracking-[0.18em] text-stone-950';
const taskSystemBadgeClass = 'inline-flex min-h-[30px] items-center justify-center rounded-full border border-stone-300 bg-stone-100 px-[0.825rem] py-[0.4125rem] text-[12px] font-semibold uppercase tracking-[0.18em] text-stone-700';

const submit = () => {
    form.post('/tasks', {
        preserveScroll: true,
        onSuccess: () => form.reset('title', 'details', 'due_at'),
    });
};

const updateStatus = (id: number, status: string) => {
    router.patch(`/tasks/${id}`, { status }, { preserveScroll: true });
};

const clientLabel = (client?: {
    display_name?: string;
    first_name?: string;
    last_name?: string;
} | null) => {
    if (!client) {
        return 'Operations reminder';
    }

    const fallbackName = `${client.first_name ?? ''} ${client.last_name ?? ''}`.trim();

    if (client.display_name) {
        return client.display_name;
    }

    return fallbackName || 'Global task';
};
</script>

<template>
    <Head title="Tasks" />

    <h1 class="sr-only">Tasks</h1>

    <div class="space-y-8">
        <section class="flex items-start justify-between gap-4 border-b border-stone-300/70 pb-4">
            <div>
                <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Operations queue</p>
                <h2 class="text-2xl font-semibold tracking-tight text-stone-950">Tasks</h2>
                <p class="text-sm text-stone-600">Create, assign, and clear office work from one operational lane.</p>
            </div>
            <DashboardWorkspaceNav />
        </section>

        <div class="grid gap-8 xl:grid-cols-[0.85fr_1.15fr]">
            <form class="space-y-3" @submit.prevent="submit">
                <div class="border-b border-stone-300/70 pb-3">
                    <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Create task</p>
                    <p class="text-sm text-stone-600">Assign operational work that should surface in the inbox and audit log.</p>
                </div>
                <select v-model="form.client_id" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm">
                    <option value="">No client</option>
                    <option v-for="client in clients" :key="client.id" :value="client.id.toString()">
                        {{ `${client.first_name} ${client.last_name}` }}
                    </option>
                </select>
                <Input v-model="form.title" placeholder="Task title" />
                <select v-model="form.priority" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm">
                    <option value="low">Low</option>
                    <option value="normal">Normal</option>
                    <option value="high">High</option>
                </select>
                <Input v-model="form.due_at" type="date" />
                <Textarea v-model="form.details" placeholder="Extra task detail or SOP context" />
                <button type="submit" class="rounded-full bg-stone-950 px-4 py-2 text-xs font-medium uppercase tracking-[0.22em] text-stone-50 transition hover:bg-stone-800">
                    Create task
                </button>
            </form>

            <section class="space-y-3">
                <div class="border-b border-stone-300/70 pb-3">
                    <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Task board</p>
                    <p class="text-sm text-stone-600">Update status as work moves from review to completion.</p>
                </div>

                <div class="space-y-3">
                    <div v-for="task in tasks" :key="task.id" class="rounded-[24px] border border-stone-300/70 bg-stone-50/70 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-medium text-stone-950">{{ task.title }}</p>
                                <p class="text-sm text-stone-500">
                                    {{ clientLabel(task.client) }}
                                </p>
                            </div>
                            <div class="text-right text-[11px] uppercase tracking-[0.22em] text-stone-500">
                                <p>{{ task.priority }}</p>
                                <p>{{ task.due_at ? formatDate(task.due_at) : 'No due date' }}</p>
                            </div>
                        </div>
                        <p v-if="task.details" class="mt-3 text-sm text-stone-700">{{ task.details }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <template v-if="task.system_item && task.action_href">
                                <Link
                                    :href="task.action_href"
                                    :class="taskActionBadgeClass"
                                >
                                    {{ task.action_label ?? 'Open' }}
                                </Link>
                                <span :class="taskSystemBadgeClass">
                                    system
                                </span>
                            </template>
                            <template v-else>
                                <button type="button" :class="taskActionBadgeClass" @click="updateStatus(Number(task.id), 'in_progress')">
                                    Start
                                </button>
                                <button type="button" :class="taskActionBadgeClass" @click="updateStatus(Number(task.id), 'done')">
                                    Complete
                                </button>
                                <span :class="taskStateBadgeClass">
                                    {{ task.status }}
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
