<!--
  components/ActivityChart.vue
  ---------------------------------------------------------------------------
  Daily attempt volume for the admin dashboard (chart.js Bar via vue-chartjs).
-->
<script setup>
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';
import { useTranslation } from '@/lib/i18n';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);

const props = defineProps({
    /** [{ day, attempts, students }] — oldest first. */
    rows: { type: Array, default: () => [] },
    height: { type: Number, default: 240 },
});

const { t } = useTranslation();

const chartData = computed(() => ({
    labels: props.rows.map((r) => String(r.day).slice(5)), // MM-DD reads better on the axis
    datasets: [
        {
            label: t('admin.totalAttempts'),
            data: props.rows.map((r) => Number(r.attempts) || 0),
            backgroundColor: '#3b82f6',
            borderRadius: 4,
            maxBarThickness: 28,
        },
        {
            label: t('admin.totalStudents'),
            data: props.rows.map((r) => Number(r.students) || 0),
            backgroundColor: '#14b8a6',
            borderRadius: 4,
            maxBarThickness: 28,
        },
    ],
}));

const options = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
            labels: { boxWidth: 12, boxHeight: 12, color: '#64748b', font: { size: 11 } },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { precision: 0, color: '#64748b', font: { size: 11 } },
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
        <Bar :data="chartData" :options="options" />
    </div>
</template>
