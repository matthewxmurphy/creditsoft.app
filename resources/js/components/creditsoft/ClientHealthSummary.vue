<script setup lang="ts">
import {
    clientHealthBadgeClass,
    clientHealthDetail,
    clientHealthDotClass,
    clientHealthLabel,
    clientHealthPanelClass,
    clientHealthScoreLabel,
} from '@/lib/client-health';
import type { ClientHealthSignal } from '@/lib/client-health';

defineProps<{
    client: {
        display_name: string;
        status?: string | null;
    };
    healthSignal?: ClientHealthSignal | string | null;
}>();
</script>

<template>
    <section
        class="rounded-[28px] border p-5 shadow-sm"
        :class="clientHealthPanelClass(healthSignal)"
    >
        <div class="flex flex-wrap items-start justify-between gap-5">
            <div class="min-w-0">
                <p
                    class="text-[11px] font-medium tracking-[0.32em] text-stone-500 uppercase"
                >
                    Client health
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <h2
                        class="truncate text-2xl font-semibold tracking-tight text-stone-950"
                    >
                        {{ client.display_name }}
                    </h2>
                    <span
                        v-if="clientHealthLabel(healthSignal)"
                        class="inline-flex max-w-full items-center gap-2 rounded-full border px-3 py-1.5 text-[11px] font-semibold tracking-[0.18em] uppercase"
                        :class="clientHealthBadgeClass(healthSignal)"
                    >
                        <span
                            class="size-2 rounded-full"
                            :class="clientHealthDotClass(healthSignal)"
                        />
                        <span>{{ clientHealthLabel(healthSignal) }}</span>
                    </span>
                </div>
                <p class="mt-2 text-sm leading-6 text-stone-700">
                    {{
                        clientHealthDetail(healthSignal) ||
                        'No client health signal has been calculated yet.'
                    }}
                </p>
            </div>

            <div class="text-left sm:text-right">
                <p class="text-4xl font-semibold tracking-tight text-stone-950">
                    {{ clientHealthScoreLabel(healthSignal) ?? '50/100' }}
                </p>
                <p
                    class="mt-1 text-[11px] font-medium tracking-[0.22em] text-stone-500 uppercase"
                >
                    Internal score
                </p>
                <p class="mt-2 text-sm font-medium text-stone-700 capitalize">
                    {{ client.status?.replaceAll('_', ' ') ?? 'Client' }}
                </p>
            </div>
        </div>
    </section>
</template>
