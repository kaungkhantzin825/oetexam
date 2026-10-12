<!--
  Pages/Admin/Results.vue
  ---------------------------------------------------------------------------
  Every completed attempt, filterable by exam, student and sub-test, with a
  summary of the filtered set and server-side pagination.
-->
<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { BarChart3, Search } from 'lucide-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { AppPagination, BandBadge, EmptyState, SectionBadge, StatCard } from '@/components/ui';
import { useTranslation } from '@/lib/i18n';
import { formatDate, formatDuration } from '@/lib/format';

const props = defineProps({
    results: { type: Object, default: () => ({ data: [], current_page: 1, last_page: 1 }) },
    summary: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    exams: { type: Array, default: () => [] },
});

const { t } = useTranslation();

const SECTIONS = ['Listening', 'Reading', 'Writing', 'Speaking', 'Full'];

const fExam = ref(props.filters.exam_id ? String(props.filters.exam_id) : '');
const fSection = ref(props.filters.section || '');
const fStudent = ref(props.filters.student_id ? String(props.filters.student_id) : '');

function applyFilters() {
    router.get(
        '/admin/results',
        {
            exam_id: fExam.value || undefined,
            section: fSection.value || undefined,
            student_id: fStudent.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function clearFilters() {
    fExam.value = '';
    fSection.value = '';
    fStudent.value = '';
    router.get('/admin/results', {}, { preserveState: true, preserveScroll: true, replace: true });
}

const rows = computed(() => props.results.data || []);
const hasFilters = computed(() => Boolean(fExam.value || fSection.value || fStudent.value));
</script>

<template>
    <Head :title="t('admin.results')" />

    <AdminLayout>
        <header>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ t('admin.results') }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ t('admin.totalAttempts') }}</p>
        </header>

        <!-- ---- Summary ---- -->
        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <StatCard :icon="BarChart3" :label="t('admin.totalAttempts')" :value="summary.n ?? 0" tone="blue" />
            <StatCard
                :icon="BarChart3"
                :label="t('admin.avgBand')"
                :value="Number(summary.avg_band ?? 0).toFixed(1)"
                tone="purple"
            />
            <StatCard
                :icon="BarChart3"
                :label="t('dashboard.bestBand')"
                :value="Number(summary.best_band ?? 0).toFixed(1)"
                tone="teal"
            />
        </div>

        <!-- ---- Filters ---- -->
        <div class="card mt-5 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="label" for="r-exam">{{ t('nav.practice') }}</label>
                <select id="r-exam" v-model="fExam" class="input">
                    <option value="">{{ t('common.all') }}</option>
                    <option v-for="e in exams" :key="e.id" :value="String(e.id)">{{ e.title }}</option>
                </select>
            </div>

            <div>
                <label class="label" for="r-section">{{ t('tutorials.category') }}</label>
                <select id="r-section" v-model="fSection" class="input">
                    <option value="">{{ t('common.all') }}</option>
                    <option v-for="s in SECTIONS" :key="s" :value="s">{{ t(`sections.${s}`) }}</option>
                </select>
            </div>

            <div>
                <label class="label" for="r-student">{{ t('admin.totalStudents') }}</label>
                <div class="relative">
                    <Search :size="15" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input
                        id="r-student"
                        v-model="fStudent"
                        type="number"
                        class="input pl-9"
                        placeholder="ID"
                        @keyup.enter="applyFilters"
                    />
                </div>
            </div>

            <div class="flex items-end gap-2">
                <button type="button" class="btn-secondary flex-1" @click="applyFilters">{{ t('common.filter') }}</button>
                <button v-if="hasFilters" type="button" class="btn-ghost" @click="clearFilters">{{ t('common.all') }}</button>
            </div>
        </div>

        <!-- ---- Table ---- -->
        <section class="card mt-5 overflow-hidden">
            <div v-if="rows.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-semibold">{{ t('admin.totalStudents') }}</th>
                            <th class="px-5 py-3 text-left font-semibold">{{ t('nav.practice') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ t('common.score') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ t('common.band') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ t('result.timeTaken') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in rows" :key="row.id" class="transition hover:bg-slate-50/70">
                            <td class="px-5 py-3.5">
                                <p class="font-medium text-slate-800">{{ row.student_name }}</p>
                                <p class="text-xs text-slate-500">{{ row.student_email }}</p>
                            </td>
                            <td class="max-w-xs px-5 py-3.5">
                                <p class="truncate text-slate-700">{{ row.exam_title }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                    <SectionBadge :section="row.section" />
                                    <span class="text-[11px] text-slate-400">{{ formatDate(row.finished_at, true) }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-right tabular-nums text-slate-600">
                                {{ Number(row.total_score || 0).toFixed(0) }} / {{ Number(row.max_score || 0).toFixed(0) }}
                            </td>
                            <td class="px-5 py-3.5 text-right"><BandBadge :band="row.band_score" size="sm" /></td>
                            <td class="px-5 py-3.5 text-right text-xs text-slate-500">
                                {{ formatDuration(row.time_spent_seconds, t) }}
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <a :href="`/attempts/${row.id}/result`" class="btn-secondary btn-sm">
                                    {{ t('result.reviewAnswers') }}
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="p-5"><EmptyState :icon="BarChart3" :title="t('common.noData')" /></div>

            <AppPagination
                v-if="results.last_page > 1"
                :page="results.current_page"
                :pages="results.last_page"
                @change="(p) => router.get('/admin/results', { ...filters, page: p }, { preserveScroll: true })"
            />
        </section>
    </AdminLayout>
</template>
