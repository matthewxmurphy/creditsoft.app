<script setup lang="ts">
import type { TooltipContentProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faCircleQuestion } from '@fortawesome/free-solid-svg-icons';
import { cn } from '@/lib/utils';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

const props = withDefaults(defineProps<{
    label?: string;
    eyebrow?: string | null;
    title?: string | null;
    body?: string | null;
    footer?: string | null;
    side?: TooltipContentProps['side'];
    align?: TooltipContentProps['align'];
    sideOffset?: number;
    triggerClass?: HTMLAttributes['class'];
    contentClass?: HTMLAttributes['class'];
}>(), {
    label: 'More info',
    eyebrow: null,
    title: null,
    body: null,
    footer: null,
    side: 'top',
    align: 'center',
    sideOffset: 10,
    triggerClass: '',
    contentClass: '',
});
</script>

<template>
    <TooltipProvider :delay-duration="0">
        <Tooltip>
            <TooltipTrigger as-child>
                <slot name="trigger">
                    <button
                        type="button"
                        :aria-label="props.label"
                        :title="props.label"
                        :class="cn(
                            'inline-flex cursor-help items-center justify-center p-0 text-stone-400 transition hover:text-stone-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-400',
                            props.triggerClass,
                        )"
                    >
                        <FontAwesomeIcon :icon="faCircleQuestion" class="text-[15px]" />
                        <span class="sr-only">{{ props.label }}</span>
                    </button>
                </slot>
            </TooltipTrigger>

            <TooltipContent
                variant="card"
                :side="props.side"
                :align="props.align"
                :side-offset="props.sideOffset"
                :class="cn('max-w-sm px-0 py-0', props.contentClass)"
            >
                <div class="overflow-hidden rounded-[28px]">
                    <div v-if="props.eyebrow || props.title || $slots['header-extra']" class="border-b border-white/10 px-4 py-3">
                        <div v-if="props.eyebrow" class="text-[11px] font-semibold uppercase tracking-[0.24em] text-stone-400">
                            {{ props.eyebrow }}
                        </div>
                        <div class="mt-1 flex items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <div v-if="props.title" class="text-base font-semibold text-white">
                                    {{ props.title }}
                                </div>
                            </div>
                            <slot name="header-extra" />
                        </div>
                    </div>

                    <div v-if="$slots.default || props.body" class="grid gap-3 px-4 py-3 text-sm leading-6 text-stone-100">
                        <slot>
                            <p>{{ props.body }}</p>
                        </slot>
                    </div>

                    <div v-if="$slots.footer || props.footer" class="border-t border-white/10 px-4 py-3 text-xs leading-5 text-stone-300">
                        <slot name="footer">
                            {{ props.footer }}
                        </slot>
                    </div>
                </div>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
