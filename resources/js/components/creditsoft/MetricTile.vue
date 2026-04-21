<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    label: string;
    value: string | number;
    hint?: string;
    tooltip?: string;
    href?: string;
}>();
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="flex min-h-28 flex-col justify-between border-b border-stone-300/70 pb-4"
        :class="href ? 'group transition hover:border-stone-950' : ''"
    >
        <span
            class="text-[11px] font-medium uppercase tracking-[0.28em] text-stone-500"
            :class="href ? 'transition group-hover:text-stone-900' : ''"
            :title="tooltip"
            :aria-label="tooltip || label"
        >
            {{ label }}
        </span>
        <div class="space-y-1">
            <p class="text-3xl font-semibold tracking-tight text-stone-950" :class="href ? 'transition group-hover:translate-x-1' : ''">
                {{ value }}
            </p>
            <p v-if="hint" class="text-sm text-stone-500">
                {{ hint }}
            </p>
        </div>
    </component>
</template>
