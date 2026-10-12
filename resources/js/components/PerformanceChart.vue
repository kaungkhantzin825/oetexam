<!--
  components/PerformanceChart.vue
  ---------------------------------------------------------------------------
  Band-score trend line, drawn with chart.js + vue-chartjs (the Vue replacement
  for recharts). Reads the chronological series produced by
  DashboardController@index.
-->
<script setup>
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import {
    CategoryScale,
    Chart as ChartJS,
    Filler,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip);

const props = defineProps({
    /** [{ label, band, section, date }] — oldest first. */
    points: { type: Array, default: () => [] },
    height: { type: Number, default: 220 },
});

const chartData = computed(() => ({
    labels: props.points.map((p) => p.label),
    datasets: [
        {
            data: props.points.map((p) => p.band),
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.12)',
            pointBackgroundColor: '#2563eb',
            pointRadius: 4,
            pointHoverRadius: 6,
            borderWidth: 2,
            tension: 0.35,
            fill: true,
        },
    ],
}));

const options = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx) => `Band ${Number(ctx.parsed.y).toFixed(1)}`,
            },
        },
    },
    scales: {
        y: {
            min: 0,
            max: 6,
            ticks: { stepSize: 1, color: '#64748b', font: { size: 11 } },
            grid: { color: '#e2e8f0' },
        },
        x: {
            ticks: { color: '#64748b', font: { size: 11 } },
            grid: { display: false },
        },
    },
};
</script>

<template>
    <div :style="{ height: height + 'px' }">
        <Line :data="chartData" :options="options" />
    </div>
</template>
