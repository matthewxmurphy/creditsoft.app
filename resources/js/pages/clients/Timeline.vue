<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ClientHealthSummary from '@/components/creditsoft/ClientHealthSummary.vue';
import ClientWorkspaceNav from '@/components/creditsoft/ClientWorkspaceNav.vue';
import type { ClientHealthSignal } from '@/lib/client-health';

defineProps<{
    client: {
        id: number;
        display_name: string;
        status?: string | null;
    };
    clientHealth?: ClientHealthSignal | string | null;
    retentionDays: number;
    entries: Array<{
        id: number;
        event: string;
        summary: string;
        created_at: string;
        user?: {
            name: string;
        } | null;
    }>;
}>();

const formatDateTime = (value?: string | null) =>
    value
        ? new Intl.DateTimeFormat('en-US', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
              second: '2-digit',
              timeZoneName: 'short',
          }).format(new Date(value))
        : 'Unknown';
</script>

<template>
    <Head :title="`Audit - ${client.display_name}`" />

    <h1 class="sr-only">{{ client.display_name }} audit trail</h1>

    <div class="space-y-8">
        <ClientWorkspaceNav :client-id="client.id" :health-signal="clientHealth" />

        <ClientHealthSummary :client="client" :health-signal="clientHealth" />

        <section class="space-y-3">
            <div class="border-b border-stone-300/70 pb-3">
                <p class="text-[11px] font-medium uppercase tracking-[0.32em] text-stone-500">Audit trail</p>
                <p class="text-sm text-stone-600">Every import, edit, approval, and workflow event tied to this client from the last {{ retentionDays }} days.</p>
            </div>

            <div class="space-y-3">
                <div v-for="entry in entries" :key="entry.id" class="rounded-[24px] border border-stone-300/70 bg-stone-50/70 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-medium text-stone-950">{{ entry.summary }}</p>
                            <p class="text-xs uppercase tracking-[0.22em] text-stone-500">{{ entry.event.replaceAll('.', ' ') }}</p>
                        </div>
                        <div class="text-right text-[11px] uppercase tracking-[0.22em] text-stone-500">
                            <p>{{ entry.user?.name ?? 'System' }}</p>
                            <p>{{ formatDateTime(entry.created_at) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
