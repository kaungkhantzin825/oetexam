<!--
  Pages/Student/History.vue
  ---------------------------------------------------------------------------
  The student's full attempt list, newest first, with a status filter and a
  link straight into the review page for any completed attempt.
-->
<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ClipboardList, Eye, PlayCircle, RotateCcw } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { BandBadge, EmptyState, SectionBadge } from '@/components/ui';
import { useTranslation } from '@/lib/i18n';
import { formatDate, formatDuration } from '@/lib/format';

const props = defineProps({
    attempts: { type: Array, default: () => [] },
});

const { t } = useTranslation();

const filter = ref('all'); // all | completed | in_progress

const filtered = computed(() => {
    if (filter.value === 'all') return props.attempts;
    return props.attempts.filter((a) => a.status === filter.value);
});

const counts = computed(() => ({
    all: props.attempts.length,
    completed: props.attempts.filter((a) => a.status === 'completed').length,
    in_progress: props.attempts.filter((a) => a.status === 'in_progress').length,
}));
</script>

<template>
    <Head :title="t('nav.history')" />

    <AppLayout>
        <div class="container-page py-8">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ t('nav.history') }}</h1>
                    <p class="mt-1 text-sm text-slate-600">{{ t('dashboard.subtitle') }}</p>
                </div>

                <Link href="/practice" class="btn-primary btn-sm">{{ t('exam.start') }}</Link>
            </header>

            <!-- ---- Filter ---- -->
            <div class="mt-6 inline-flex rounded-lg border border-slate-300 bg-white p-1">
                <button
                    v-for="opt in [
                        { key: 'all', label: t('common.all'), n: counts.all },
                        { key: 'completed', label: t('admin.published'), n: counts.completed },
                        { key: 'in_progress', label: t('exam.autosaving'), n: counts.in_progress },
                    ]"
                    :key="opt.key"
                    type="button"
                    :class="[
                        'rounded-md px-3.5 py-1.5 text-xs font-semibold transition',
                        filter === opt.key ? 'bg-primary-600 text-white' : 'text-slate-600 hover:bg-slate-100',
                    ]"
                    @click="filter = opt.key"
                >
                    {{ opt.label }} <span class="ml-1 tabular-nums opacity-70">{{ opt.n }}</span>
                </button>
            </div>

            <!-- ---- Table ---- -->
            <section v-if="filtered.length" class="card mt-5 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold">{{ t('nav.practice') }}</th>
                                <th class="px-5 py-3 text-left font-semibold">{{ t('common.status') }}</th>
                                <th class="px-5 py-3 text-right font-semibold">{{ t('common.score') }}</th>
                                <th class="px-5 py-3 text-right font-semibold">{{ t('common.band') }}</th>
                                <th class="px-5 py-3 text-right font-semibold">{{ t('result.timeTaken') }}</th>
                                <th class="px-5 py-3 text-right font-semibold">{{ t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="a in filtered" :key="a.id" class="transition hover:bg-slate-50/70">
                                <td class="px-5 py-3.5">
                                    <p class="font-medium text-slate-800">{{ a.title }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                        <SectionBadge :section="a.section" />
                                        <span class="text-[11px] text-slate-400">{{ formatDate(a.started_at, true) }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span :class="a.status === 'completed' ? 'badge-green' : 'badge-amber'">
                                        {{ a.status === 'completed' ? t('admin.published') : t('exam.autosaving') }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5 text-right tabular-nums text-slate-600">
                                    {{ Number(a.total_score || 0).toFixed(0) }} / {{ Number(a.max_score || 0).toFixed(0) }}
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <BandBadge v-if="a.band_score != null" :band="a.band_score" size="sm" />
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="px-5 py-3.5 text-right text-xs text-slate-500">
                                    {{ a.status === 'completed' ? formatDuration(a.time_spent_seconds, t) : '—' }}
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <Link
                                        v-if="a.status === 'completed'"
                                        :href="`/attempts/${a.id}/result`"
                                        class="btn-secondary btn-sm gap-1.5"
                                    >
                                        <Eye :size="13" /> {{ t('result.reviewAnswers') }}
                                    </Link>
                                    <Link v-else :href="`/practice/${a.exam_id}`" class="btn-primary btn-sm gap-1.5">
                                        <PlayCircle :size="13" /> {{ t('exam.resume') }}
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div v-else class="mt-5">
                <EmptyState :icon="ClipboardList" :message="t('dashboard.noAttempts')">
                    <template #action>
                        <Link href="/practice" class="btn-primary btn-sm">
                            <RotateCcw :size="15" /> {{ t('exam.start') }}
                        </Link>
                    </template>
                </EmptyState>
            </div>
        </div>
    </AppLayout>
</template>
