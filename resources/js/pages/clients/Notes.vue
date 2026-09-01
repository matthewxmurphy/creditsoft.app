<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import ClientHealthSummary from '@/components/creditsoft/ClientHealthSummary.vue';
import ClientWorkspaceNav from '@/components/creditsoft/ClientWorkspaceNav.vue';
import { Textarea } from '@/components/ui/textarea';
import type { ClientHealthSignal } from '@/lib/client-health';
import { formatDate } from '@/lib/creditsoft';

const props = defineProps<{
    client: {
        id: number;
        cuid?: string | null;
        display_name: string;
        status?: string | null;
    };
    clientHealth?: ClientHealthSignal | string | null;
    cycles: Array<{
        id: number;
        cycle_label: string;
    }>;
    notes: Array<{
        id: number;
        visibility: string;
        note: string;
        created_at: string;
        reporting_cycle?: {
            cycle_label: string;
        } | null;
    }>;
}>();

const form = useForm({
    reporting_cycle_id: props.cycles[0]?.id?.toString() ?? '',
    visibility: 'working_note',
    note: '',
});

const clientRouteKey = computed(() => String(props.client.id));

const submit = () => {
    form.post(`/clients/${clientRouteKey.value}/notes`, {
        preserveScroll: true,
        onSuccess: () => form.reset('note'),
    });
};
</script>

<template>
    <Head :title="`Notes - ${client.display_name}`" />

    <h1 class="sr-only">{{ client.display_name }} notes</h1>

    <div class="space-y-8">
        <ClientWorkspaceNav :client-id="client.id" :health-signal="clientHealth" />

        <ClientHealthSummary :client="client" :health-signal="clientHealth" />

        <section class="grid gap-8 xl:grid-cols-[0.85fr_1.15fr]">
            <form class="space-y-3" @submit.prevent="submit">
                <div class="border-b border-stone-300/70 pb-3">
                    <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">New note</p>
                    <p class="text-sm text-stone-600">Private and working notes stay local. Only shareable briefs can sync outward.</p>
                </div>
                <select v-model="form.reporting_cycle_id" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm">
                    <option value="">No cycle</option>
                    <option v-for="cycle in cycles" :key="cycle.id" :value="cycle.id.toString()">
                        {{ cycle.cycle_label }}
                    </option>
                </select>
                <select v-model="form.visibility" class="border-input h-9 rounded-md border bg-transparent px-3 text-sm">
                    <option value="private_note">Private note</option>
                    <option value="working_note">Working note</option>
                    <option value="shareable_case_brief">Shareable brief seed</option>
                </select>
                <Textarea v-model="form.note" placeholder="Write the internal note here" />
                <button type="submit" class="rounded-full bg-stone-950 px-4 py-2 text-xs font-medium uppercase tracking-[0.22em] text-stone-50 transition hover:bg-stone-800">
                    Save note
                </button>
            </form>

            <section class="space-y-3">
                <div class="border-b border-stone-300/70 pb-3">
                    <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Timeline of notes</p>
                    <p class="text-sm text-stone-600">Local-only note history for this client.</p>
                </div>

                <div class="space-y-3">
                    <div v-for="note in notes" :key="note.id" class="rounded-[24px] border border-stone-300/70 bg-stone-50/70 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-[11px] uppercase tracking-[0.22em] text-stone-500">
                                {{ note.visibility.replaceAll('_', ' ') }}
                            </p>
                            <p class="text-[11px] uppercase tracking-[0.22em] text-stone-500">
                                {{ formatDate(note.created_at) }}
                            </p>
                        </div>
                        <p class="mt-3 text-sm text-stone-800">{{ note.note }}</p>
                        <p v-if="note.reporting_cycle" class="mt-2 text-xs text-stone-500">
                            {{ note.reporting_cycle.cycle_label }}
                        </p>
                    </div>
                </div>
            </section>
        </section>
    </div>
</template>
