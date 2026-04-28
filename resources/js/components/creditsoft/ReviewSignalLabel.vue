<script setup lang="ts">
import { computed } from 'vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import type { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import {
    faArrowUpWideShort,
    faChartPie,
    faCheckDouble,
    faCircleHalfStroke,
    faCircleCheck,
    faCircleExclamation,
    faClock,
    faCodeCompare,
    faLinkSlash,
    faMagnifyingGlass,
    faMinus,
    faSackDollar,
    faTriangleExclamation,
} from '@fortawesome/free-solid-svg-icons';

type ReviewSignalKind =
    | 'missing'
    | 'mismatch'
    | 'negative'
    | 'lawsuit'
    | 'priority'
    | 'queued'
    | 'review'
    | 'severity-high'
    | 'severity-low'
    | 'severity-medium'
    | 'single'
    | 'status-confirmed'
    | 'status-open'
    | 'status-resolved'
    | 'utilization';

const props = withDefaults(defineProps<{
    kind: ReviewSignalKind;
    label: string;
    title?: string | null;
    styleKey?: string | number | null;
}>(), {
    title: null,
    styleKey: '10',
});

const toneClass = computed(() => `tone-${props.kind}`);
const styleClass = computed(() => `style-${String(props.styleKey ?? '10')}`);
const tooltip = computed(() => props.title || props.label);
const showBar = computed(() => ['1', '10'].includes(String(props.styleKey ?? '10')));

const icon = computed<IconDefinition>(() => {
    switch (props.kind) {
        case 'negative':
            return faTriangleExclamation;
        case 'priority':
            return faArrowUpWideShort;
        case 'lawsuit':
            return faSackDollar;
        case 'queued':
        case 'status-open':
            return faClock;
        case 'status-confirmed':
            return faCircleCheck;
        case 'status-resolved':
            return faCheckDouble;
        case 'severity-high':
            return faTriangleExclamation;
        case 'severity-medium':
            return faCircleExclamation;
        case 'severity-low':
            return faMinus;
        case 'mismatch':
            return faCodeCompare;
        case 'missing':
            return faLinkSlash;
        case 'single':
            return faCircleHalfStroke;
        case 'utilization':
            return faChartPie;
        case 'review':
        default:
            return faMagnifyingGlass;
    }
});
</script>

<template>
    <span
        class="review-signal"
        :class="[styleClass, toneClass]"
        :title="tooltip"
        :aria-label="tooltip"
    >
        <span v-if="showBar" class="signal-bar" aria-hidden="true" />
        <FontAwesomeIcon :icon="icon" class="signal-icon" />
        <span class="signal-text">{{ label }}</span>
    </span>
</template>

<style scoped>
.review-signal {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 0.38rem;
    max-width: 100%;
    color: var(--signal-color);
    font-size: 0.56rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    line-height: 1;
    text-transform: uppercase;
    white-space: nowrap;
}

.signal-icon {
    font-size: 0.68rem;
    flex: 0 0 auto;
}

.signal-text {
    display: inline-block;
}

.signal-bar {
    display: inline-block;
    width: 0.16rem;
    height: 0.92rem;
    background: currentColor;
    border-radius: 999px;
    flex: 0 0 auto;
}

.tone-negative {
    --signal-color: #9d4537;
}

.tone-lawsuit {
    --signal-color: #7a5a08;
}

.tone-priority {
    --signal-color: #5a513b;
}

.tone-queued,
.tone-status-open {
    --signal-color: #7a6517;
}

.tone-status-confirmed,
.tone-status-resolved {
    --signal-color: #2f7a55;
}

.tone-severity-high {
    --signal-color: #9d4537;
}

.tone-severity-medium {
    --signal-color: #73560a;
}

.tone-severity-low {
    --signal-color: #5f5751;
}

.tone-mismatch {
    --signal-color: #73560a;
}

.tone-missing {
    --signal-color: #8d6300;
}

.tone-single {
    --signal-color: #4a6786;
}

.tone-utilization {
    --signal-color: #4e6841;
}

.tone-review {
    --signal-color: #5f5751;
}

.style-1,
.style-10 {
    padding: 0.33rem 0.5rem 0.33rem 0.42rem;
    background: rgba(255, 255, 255, 0.95);
    box-shadow: inset 0 0 0 1px rgba(31, 26, 23, 0.1);
}

.style-2 {
    padding: 0.32rem 0.46rem;
    border: 1px solid currentColor;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.86), rgba(255, 255, 255, 0.74));
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.55);
}

.style-3 {
    padding: 0.28rem 0.46rem;
    border-left: 2px solid currentColor;
    border-right: 2px solid currentColor;
    background: rgba(255, 255, 255, 0.82);
}

.style-4 {
    padding: 0.3rem 0.48rem;
    background: rgba(255, 255, 255, 0.96);
    box-shadow: inset 0 0 0 1px rgba(31, 26, 23, 0.08);
}

.style-4::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 0;
    height: 0;
    border-left: 8px solid transparent;
    border-bottom: 8px solid transparent;
    border-top: 8px solid currentColor;
}

.style-5 {
    padding: 0.3rem 0.72rem 0.3rem 0.48rem;
    border: 1px dashed currentColor;
    background: rgba(255, 255, 255, 0.94);
    clip-path: polygon(0 0, calc(100% - 7px) 0, 100% 50%, calc(100% - 7px) 100%, 0 100%);
}

.style-6 {
    padding: 0.28rem 0.46rem;
    border-top: 2px solid currentColor;
    border-bottom: 2px solid currentColor;
    background:
        repeating-linear-gradient(
            -45deg,
            rgba(255, 255, 255, 0.94),
            rgba(255, 255, 255, 0.94) 8px,
            rgba(31, 26, 23, 0.04) 8px,
            rgba(31, 26, 23, 0.04) 14px
        );
}

.style-7 {
    padding: 0.32rem 0.5rem 0.32rem 0.58rem;
    border-left: 3px solid currentColor;
    background: rgba(255, 255, 255, 0.95);
    box-shadow: inset 0 0 0 1px rgba(31, 26, 23, 0.08);
}

.style-8 {
    padding: 0 0 0.18rem;
    border-bottom: 2px solid currentColor;
    background: transparent;
}

.style-9 {
    padding: 0.28rem 0.44rem;
    border: 1px solid rgba(31, 26, 23, 0.12);
    background: rgba(255, 255, 255, 0.95);
    font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
    letter-spacing: 0.08em;
}

.style-11 {
    padding: 0.32rem 0.5rem 0.26rem;
    background: rgba(255, 255, 255, 0.94);
    box-shadow:
        inset 0 0 0 1px rgba(31, 26, 23, 0.08),
        inset 0 -2px 0 rgba(0, 0, 0, 0.05);
}

.style-12 {
    padding: 0.28rem 0.48rem;
    border-left: 3px solid currentColor;
    border-bottom: 2px solid rgba(31, 26, 23, 0.08);
    background: rgba(255, 255, 255, 0.96);
}
</style>
