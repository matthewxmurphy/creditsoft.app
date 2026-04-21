<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ChartJS, type ChartInstance } from '@/lib/chartjs-line';

const props = withDefaults(
    defineProps<{
        labels: string[];
        series: Array<{
            label: string;
            values: Array<number | null>;
            color: string;
            type?: 'line' | 'bar';
        }>;
        height?: number;
        tension?: number;
        pointRadius?: number;
    }>(),
    {
        height: 220,
        tension: 0.35,
        pointRadius: 3,
    },
);

const withAlpha = (color: string, alphaHex: string) => {
    if (color.startsWith('#') && color.length === 7) {
        return `${color}${alphaHex}`;
    }

    return color;
};

const canvas = ref<HTMLCanvasElement | null>(null);
let chart: ChartInstance<'line' | 'bar', Array<number | null>, string> | null = null;

const renderChart = () => {
    if (!canvas.value) return;

    chart?.destroy();

    chart = new ChartJS(canvas.value, {
        type: 'line',
        data: {
            labels: props.labels,
            datasets: props.series.map((dataset) => ({
                type: dataset.type ?? 'line',
                label: dataset.label,
                data: dataset.values,
                borderColor: dataset.color,
                backgroundColor: dataset.type === 'bar' ? withAlpha(dataset.color, '2e') : dataset.color,
                borderWidth: dataset.type === 'bar' ? 1 : 2,
                tension: dataset.type === 'bar' ? 0 : props.tension,
                pointRadius: dataset.type === 'bar' ? 0 : props.pointRadius,
                pointHoverRadius: dataset.type === 'bar' ? 0 : props.pointRadius + 1,
                pointBackgroundColor: dataset.color,
                pointBorderColor: '#ffffff',
                pointBorderWidth: dataset.type === 'bar' ? 0 : 1,
                borderRadius: dataset.type === 'bar' ? 6 : 0,
                maxBarThickness: dataset.type === 'bar' ? 16 : undefined,
                spanGaps: true,
                fill: false,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        color: '#57534e',
                        boxWidth: 12,
                        usePointStyle: true,
                        pointStyle: 'line',
                    },
                },
            },
            scales: {
                x: {
                    ticks: {
                        color: '#78716c',
                        maxRotation: 0,
                    },
                    grid: {
                        display: false,
                    },
                },
                y: {
                    ticks: {
                        color: '#78716c',
                    },
                    grid: {
                        color: 'rgba(120, 113, 108, 0.15)',
                    },
                },
            },
        },
    });
};

onMounted(renderChart);

watch(() => [props.labels, props.series], renderChart, { deep: true });

onBeforeUnmount(() => {
    chart?.destroy();
});
</script>

<template>
    <div :style="{ height: `${height}px` }">
        <canvas ref="canvas" />
    </div>
</template>
