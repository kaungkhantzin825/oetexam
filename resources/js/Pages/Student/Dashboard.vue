<!--
  Pages/Student/Dashboard.vue
  ---------------------------------------------------------------------------
  The student's home: headline stats, a quick-start row for the four sub-tests,
  a band trend chart, per-sub-test performance and recent attempts.
-->
<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Clock, Flame, Target, TrendingUp, Trophy } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import SectionIcon from '@/components/SectionIcon.vue';
import PerformanceChart from '@/components/PerformanceChart.vue';
import { BandBadge, EmptyState, ProgressBar, SectionBadge, StatCard } from '@/components/ui';
import { useTranslation } from '@/lib/i18n';
import { useAuth } from '@/lib/auth';
import { formatDate, formatDuration } from '@/lib/format';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    bySection: { type: Array, default: () => [] },
    recentAttempts: { type: Array, default: () => [] },
    trend: { type: Array, default: () => [] },
});

const { t } = useTranslation();
const { user, firstName } = useAuth();

const SECTION_ORDER = ['Listening', 'Reading', 'Writing', 'Speaking', 'Full'];

const quickStart = computed(() =>
    SECTION_ORDER.map((section) => {
        const row = props.bySection.find((s) => s.section === section);
        return {
            section,
            attempts: Number(row?.attempts ?? 0),
            bestBand: row?.best_band != null ? Number(row.best_band) : null,
        };
    })
);

const studyTime = computed(() => formatDuration(props.stats.total_seconds, t));
const hasTrend = computed(() => props.trend.length >= 2);
</script>

<template>
    <Head :title="t('nav.dashboard')" />

    <AppLayout>
        <div class="container-page py-8">
            <!-- ---- Header ---- -->
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                        {{ t('dashboard.greeting', { name: firstName || user?.name }) }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-600">{{ t('dashboard.subtitle') }}</p>
                </div>

                <div class="flex gap-2">
                    <Link href="/practice" class="btn-primary btn-sm">
                        {{ t('nav.practice') }} <ArrowRight :size="15" />
                    </Link>
                    <Link href="/tutorials" class="btn-secondary btn-sm">{{ t('nav.tutorials') }}</Link>
                </div>
            </header>

            <!-- ---- Stats ---- -->
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard :icon="Flame" :label="t('dashboard.testsTaken')" :value="stats.tests_taken ?? 0" tone="blue" />
                <StatCard
                    :icon="TrendingUp"
                    :label="t('dashboard.averageBand')"
                    :value="Number(stats.avg_band ?? 0).toFixed(1)"
                    tone="purple"
                />
                <StatCard
                    :icon="Trophy"
                    :label="t('dashboard.bestBand')"
                    :value="Number(stats.best_band ?? 0).toFixed(1)"
                    tone="teal"
                />
                <StatCard :icon="Clock" :label="t('dashboard.studyTime')" :value="studyTime" tone="amber" />
            </div>

            <!-- ---- Quick start ---- -->
            <section class="mt-8">
                <h2 class="text-base font-semibold text-slate-900">{{ t('dashboard.quickStart') }}</h2>

                <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="item in quickStart"
                        :key="item.section"
                        :href="`/practice?section=${item.section}`"
                        class="card-hover card-pad group"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 text-primary-600">
                                <SectionIcon :section="item.section" :size="19" />
                            </span>
                            <BandBadge v-if="item.bestBand != null" :band="item.bestBand" size="sm" />
                        </div>

                        <h3 class="mt-3 text-sm font-semibold text-slate-900">{{ t(`sections.${item.section}`) }}</h3>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ item.attempts }} {{ t('dashboard.testsTaken') }}
                        </p>

                        <span class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-primary-600 transition-all group-hover:gap-2">
                            {{ t('exam.start') }} <ArrowRight :size="13" />
                        </span>
                    </Link>
                </div>
            </section>

            <!-- ---- Trend + by-section ---- -->
            <div class="mt-8 grid gap-6 lg:grid-cols-5">
                <section class="card card-pad lg:col-span-3">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-slate-900">{{ t('dashboard.performance') }}</h2>
                        <span class="badge-slate">{{ props.trend.length }} {{ t('common.questions') }}</span>
                    </div>

                    <div v-if="hasTrend" class="mt-5">
                        <PerformanceChart :points="trend" :height="230" />
                    </div>
                    <div v-else class="mt-4">
                        <EmptyState :icon="TrendingUp" :message="t('dashboard.noAttempts')" />
                    </div>
                </section>

                <section class="card card-pad lg:col-span-2">
                    <h2 class="text-base font-semibold text-slate-900">{{ t('dashboard.bySection') }}</h2>

                    <div v-if="bySection.length" class="mt-5 space-y-4">
                        <div v-for="row in bySection" :key="row.section">
                            <div class="flex items-center justify-between gap-3">
                                <SectionBadge :section="row.section" />
                                <span class="text-xs text-slate-500">
                                    {{ row.attempts }} × · {{ t('admin.avgBand') }}
                                    <strong class="font-semibold text-slate-700">{{ Number(row.avg_band ?? 0).toFixed(1) }}</strong>
                                </span>
                            </div>
                            <ProgressBar class="mt-2" :value="Number(row.avg_band ?? 0)" :max="6" tone="primary" />
                        </div>
                    </div>

                    <div v-else class="mt-4">
                        <EmptyState :icon="Target" :message="t('dashboard.noAttempts')" />
                    </div>
                </section>
            </div>

            <!-- ---- Recent attempts ---- -->
            <section class="card mt-8 overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">{{ t('dashboard.recentAttempts') }}</h2>
                    <Link href="/history" class="link text-sm">{{ t('dashboard.viewAll') }}</Link>
                </div>

                <div v-if="recentAttempts.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold">{{ t('nav.practice') }}</th>
                                <th class="px-5 py-3 text-left font-semibold">{{ t('common.status') }}</th>
                                <th class="px-5 py-3 text-right font-semibold">{{ t('common.score') }}</th>
                                <th class="px-5 py-3 text-right font-semibold">{{ t('common.band') }}</th>
                                <th class="px-5 py-3 text-right font-semibold">{{ t('result.timeTaken') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="a in recentAttempts" :key="a.id" class="transition hover:bg-slate-50/70">
                                <td class="px-5 py-3.5">
                                    <Link
                                        v-if="a.status === 'completed'"
                                        :href="`/attempts/${a.id}/result`"
                                        class="font-medium text-slate-800 hover:text-primary-600"
                                    >
                                        {{ a.title }}
                                    </Link>
                                    <span v-else class="font-medium text-slate-800">{{ a.title }}</span>
                                    <div class="mt-1"><SectionBadge :section="a.section" /></div>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span :class="a.status === 'completed' ? 'badge-green' : 'badge-amber'">
                                        {{ a.status === 'completed' ? t('admin.published') : t('exam.autosaving') }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right tabular-nums text-slate-600">
                                    {{ Number(a.total_score ?? 0).toFixed(0) }} / {{ Number(a.max_score ?? 0).toFixed(0) }}
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <BandBadge v-if="a.band_score != null" :band="a.band_score" size="sm" />
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="px-5 py-3.5 text-right text-xs text-slate-500">
                                    {{ a.status === 'completed' ? formatDate(a.finished_at, true) : '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="p-5">
                    <EmptyState :icon="Target" :message="t('dashboard.noAttempts')">
                        <template #action>
                            <Link href="/practice" class="btn-primary btn-sm">{{ t('exam.start') }}</Link>
                        </template>
                    </EmptyState>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
