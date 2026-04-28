<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    label: string;
    valueDisplay: string | number | null;
    grade?: string | null;
    detailUrl?: string | null;
    scale?: 'score' | 'grade';
    numeric?: number | null;
}>();

const segmentPalette = ['#c94e4b', '#d96c48', '#e18b4b', '#e0b95a', '#c6c965', '#9abf73', '#6fb184', '#4d948f'];
const gradeOrder = ['F', 'D', 'C', 'B', 'A'];
const segmentCount = 18;
const startAngle = 135;
const endAngle = 405;
const stepAngle = (endAngle - startAngle) / (segmentCount - 1);

const normalizedScale = computed(() => props.scale ?? 'score');
const normalizedGrade = computed(() => props.grade?.replace(/^Grade:\s*/i, '').trim().toUpperCase() ?? null);

const ratio = computed(() => {
    if (normalizedScale.value === 'grade') {
        const index = normalizedGrade.value ? gradeOrder.indexOf(normalizedGrade.value) : -1;

        return index >= 0 ? index / (gradeOrder.length - 1) : 0;
    }

    const value = typeof props.numeric === 'number' ? props.numeric : Number.parseInt(String(props.valueDisplay ?? ''), 10);

    if (!Number.isFinite(value)) {
        return 0;
    }

    return Math.min(1, Math.max(0, (value - 360) / (800 - 360)));
});

const activeSegments = computed(() => {
    const count = Math.round(ratio.value * segmentCount);

    return Math.max(1, count);
});

const segments = computed(() =>
    Array.from({ length: segmentCount }, (_, index) => {
        const angle = startAngle + (stepAngle * index);
        const paletteIndex = Math.min(segmentPalette.length - 1, Math.floor((index / Math.max(1, segmentCount - 1)) * segmentPalette.length));
        const color = segmentPalette[paletteIndex];

        return {
            angle,
            active: index < activeSegments.value,
            color,
        };
    }),
);

const scaleHint = computed(() => normalizedScale.value === 'grade' ? 'F to A scale' : '360 to 800 scale');
const detailHost = computed(() => {
    if (!props.detailUrl) {
        return null;
    }

    try {
        return new URL(props.detailUrl).pathname.replace('/member/scores/', '').replace('.htm', '').replaceAll('-', ' ');
    } catch {
        return null;
    }
});

const polarPoint = (radius: number, angle: number) => {
    const radians = (angle - 90) * (Math.PI / 180);

    return {
        x: 66 + (radius * Math.cos(radians)),
        y: 66 + (radius * Math.sin(radians)),
    };
};
</script>

<template>
    <article class="rounded-[24px] border border-stone-300/70 bg-white/90 p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-[11px] font-medium uppercase tracking-[0.24em] text-stone-500">{{ label }}</p>
                <p class="mt-1 text-sm text-stone-600">{{ grade ?? scaleHint }}</p>
            </div>
            <a
                v-if="detailUrl"
                :href="detailUrl"
                target="_blank"
                rel="noreferrer"
                class="text-[11px] font-medium uppercase tracking-[0.2em] text-stone-500 transition hover:text-stone-900"
            >
                {{ detailHost ?? 'details' }}
            </a>
        </div>

        <div class="mt-4 flex items-center justify-center">
            <svg viewBox="0 0 132 132" class="h-36 w-36 overflow-visible">
                <g v-for="segment in segments" :key="segment.angle">
                    <line
                        :x1="polarPoint(40, segment.angle).x"
                        :y1="polarPoint(40, segment.angle).y"
                        :x2="polarPoint(56, segment.angle).x"
                        :y2="polarPoint(56, segment.angle).y"
                        :stroke="segment.active ? segment.color : '#e7e5e4'"
                        stroke-width="7"
                        stroke-linecap="round"
                    />
                </g>
                <circle cx="66" cy="66" r="30" fill="#fafaf9" stroke="#e7e5e4" stroke-width="1.5" />
                <text x="66" y="61" text-anchor="middle" class="fill-stone-900 text-[22px] font-semibold">
                    {{ valueDisplay ?? 'N/A' }}
                </text>
                <text x="66" y="79" text-anchor="middle" class="fill-stone-500 text-[9px] uppercase tracking-[0.2em]">
                    {{ normalizedScale === 'grade' ? 'risk band' : 'score' }}
                </text>
            </svg>
        </div>

        <div class="mt-3 flex items-center justify-between text-[10px] font-medium uppercase tracking-[0.2em] text-stone-500">
            <template v-if="normalizedScale === 'grade'">
                <span>F</span>
                <span>D</span>
                <span>C</span>
                <span>B</span>
                <span>A</span>
            </template>
            <template v-else>
                <span>360</span>
                <span>510</span>
                <span>650</span>
                <span>800</span>
            </template>
        </div>
    </article>
</template>
