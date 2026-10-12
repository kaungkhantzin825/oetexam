<!--
  Pages/Admin/Dashboard.vue
  ---------------------------------------------------------------------------
  Admin overview: platform totals, 14-day activity, per-sub-test performance,
  the leaderboard and the most recent attempts.
-->
<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Bot, FileText, GraduationCap, ListChecks, Trophy, Users } from 'lucide-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';
import ActivityChart from '@/components/ActivityChart.vue';
import { BandBadge, EmptyState, ProgressBar, SectionBadge, StatCard } from '@/components/ui';
import { useTranslation } from '@/lib/i18n';
import { formatDate } from '@/lib/format';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    activity: { type: Array, default: () => [] },
    bySection: { type: Array, default: () => [] },
    recentAttempts: { type: Array, default: () => [] },
    leaderboard: { type: Array, default: () => [] },
});

const { t } = useTranslation();

const cards = computed(() => [
    {
        icon: Users,
        label: t('admin.totalStudents'),
        value: props.stats.users?.students ?? 0,
        hint: `${props.stats.users?.active ?? 0} ${t('admin.active').toLowerCase()}`,
        tone: 'blue',
    },
    { icon: FileText, label: t('admin.totalExams'), value: props.stats.exams?.total ?? 0, hint: `${props.stats.exams?.published ?? 0} ${t('admin.published').toLowerCase()}`, tone: 'purple' },
    { icon: ListChecks, label: t('admin.totalQuestions'), value: props.stats.questions?.total ?? 0, tone: 'teal' },
    {
        icon: GraduationCap,
        label: t('admin.totalAttempts'),
        value: props.stats.attempts?.total ?? 0,
        hint: `${t('admin.avgBand')} ${Number(props.stats.attempts?.avg_band ?? 0).toFixed(1)}`,
        tone: 'amber',
    },
]);
</script>

<template>
    <Head :title="t('admin.dashboard')" />

    <AdminLayout>
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ t('admin.dashboard') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ t('app.fullName') }}</p>
            </div>

            <div class="flex gap-2">
                <Link href="/admin/ai-logs" class="btn-secondary btn-sm gap-1.5"><Bot :size="14" /> {{ t('admin.aiLogs') }}</Link>
                <Link href="/admin/exams" class="btn-primary btn-sm gap-1.5"><FileText :size="14" /> {{ t('admin.exams') }}</Link>
            </div>
        </header>

        <!-- ---- Totals ---- -->
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard v-for="c in cards" :key="c.label" :icon="c.icon" :label="c.label" :value="c.value" :hint="c.hint" :tone="c.tone" />
        </div>

        <!-- ---- Activity + by section ---- -->
        <div class="mt-6 grid gap-5 lg:grid-cols-5">
            <section class="card card-pad lg:col-span-3">
                <h2 class="text-base font-semibold text-slate-900">{{ t('dashboard.performance') }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ t('admin.totalAttempts') }} · 14d</p>

                <div v-if="activity.length" class="mt-4"><ActivityChart :rows="activity" :height="250" /></div>
                <div v-else class="mt-4"><EmptyState :icon="GraduationCap" :message="t('common.noData')" /></div>
            </section>

            <section class="card card-pad lg:col-span-2">
                <h2 class="text-base font-semibold text-slate-900">{{ t('dashboard.bySection') }}</h2>

                <div v-if="bySection.length" class="mt-5 space-y-4">
                    <div v-for="row in bySection" :key="row.section">
                        <div class="flex items-center justify-between gap-3">
                            <SectionBadge :section="row.section" />
                            <span class="text-xs text-slate-500">
                                {{ row.attempts }} × · {{ Number(row.avg_band ?? 0).toFixed(1) }}
                            </span>
                        </div>
                        <ProgressBar class="mt-2" :value="Number(row.avg_band ?? 0)" :max="6" tone="primary" />
                    </div>
                </div>

                <div v-else class="mt-4"><EmptyState :icon="ListChecks" :message="t('common.noData')" /></div>
            </section>
        </div>

        <!-- ---- Leaderboard + recent ---- -->
        <div class="mt-6 grid gap-5 lg:grid-cols-2">
            <section class="card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="inline-flex items-center gap-2 text-base font-semibold text-slate-900">
                        <Trophy :size="17" class="text-amber-500" /> {{ t('admin.leaderboard') }}
                    </h2>
                </div>

                <div v-if="leaderboard.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold">#</th>
                                <th class="px-5 py-3 text-left font-semibold">{{ t('admin.totalStudents') }}</th>
                                <th class="px-5 py-3 text-right font-semibold">{{ t('admin.avgBand') }}</th>
                                <th class="px-5 py-3 text-right font-semibold">{{ t('dashboard.bestBand') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(row, i) in leaderboard" :key="row.user_id ?? i">
                                <td class="px-5 py-3 font-bold tabular-nums text-slate-400">{{ i + 1 }}</td>
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800">{{ row.name }}</p>
                                    <p class="text-[11px] text-slate-400">{{ row.attempts }} {{ t('admin.totalAttempts') }}</p>
                                </td>
                                <td class="px-5 py-3 text-right tabular-nums text-slate-600">
                                    {{ Number(row.avg_band ?? 0).toFixed(1) }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <BandBadge v-if="row.best_band != null" :band="row.best_band" size="sm" />
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="p-5"><EmptyState :icon="Trophy" :message="t('common.noData')" /></div>
            </section>

            <section class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">{{ t('dashboard.recentAttempts') }}</h2>
                    <Link href="/admin/results" class="link text-sm">{{ t('dashboard.viewAll') }}</Link>
                </div>

                <div v-if="recentAttempts.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold">{{ t('admin.totalStudents') }}</th>
                                <th class="px-5 py-3 text-left font-semibold">{{ t('nav.practice') }}</th>
                                <th class="px-5 py-3 text-right font-semibold">{{ t('common.band') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="a in recentAttempts" :key="a.id">
                                <td class="px-5 py-3">
                                    <p class="font-medium text-slate-800">{{ a.student_name }}</p>
                                    <p class="text-[11px] text-slate-400">{{ formatDate(a.started_at, true) }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <p class="truncate text-slate-700">{{ a.exam_title }}</p>
                                    <div class="mt-1"><SectionBadge :section="a.section" /></div>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <BandBadge v-if="a.band_score != null" :band="a.band_score" size="sm" />
                                    <span v-else class="badge-amber">{{ t('exam.autosaving') }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="p-5"><EmptyState :icon="GraduationCap" :message="t('common.noData')" /></div>
            </section>
        </div>
    </AdminLayout>
</template>
