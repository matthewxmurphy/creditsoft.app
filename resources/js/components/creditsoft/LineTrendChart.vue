<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ChartJS, type ChartInstance } from '@/lib/chartjs-line';

const currencyFormatter = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0,
});

const numberFormatter = new Intl.NumberFormat('en-US');

const props = withDefaults(
    defineProps<{
        labels: string[];
        values: number[];
        color?: string;
        height?: number;
        valueFormat?: 'number' | 'currency';
    }>(),
    {
        color: '#d97706',
        height: 220,
        valueFormat: 'number',
    },
);

const canvas = ref<HTMLCanvasElement | null>(null);
let chart: ChartInstance<'line'> | null = null;

const formatValue = (value: number) => (
    props.valueFormat === 'currency'
        ? currencyFormatter.format(value)
        : numberFormatter.format(value)
);

const renderChart = () => {
    if (!canvas.value) return;

    chart?.destroy();

    chart = new ChartJS(canvas.value, {
        type: 'line',
        data: {
            labels: props.labels,
            datasets: [
                {
                    data: props.values,
                    borderColor: props.color,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: props.color,
                    pointHoverBorderColor: '#fafaf9',
                    pointHoverBorderWidth: 2,
                    backgroundColor: `${props.color}1F`,
                },
            ],
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
                    display: false,
                },
                tooltip: {
                    displayColors: false,
                    backgroundColor: '#1c1917',
                    titleColor: '#fafaf9',
                    bodyColor: '#fafaf9',
                    padding: 12,
                    caretPadding: 10,
                    callbacks: {
                        title: (items) => String(items[0]?.label ?? 'Checkpoint'),
                        label: (context) => formatValue(Number(context.parsed.y ?? context.raw ?? 0)),
                    },
                },
            },
            scales: {
                x: {
                    ticks: {
                        color: '#78716c',
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

watch(() => [props.labels, props.values], renderChart, { deep: true });

onBeforeUnmount(() => {
    chart?.destroy();
});
</script>

<template>
    <div :style="{ height: `${height}px` }">
        <canvas ref="canvas" />
    </div>
</template>
