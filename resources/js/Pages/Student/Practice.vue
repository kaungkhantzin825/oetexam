<!--
  Pages/Student/Practice.vue
  ---------------------------------------------------------------------------
  The exam catalogue. A sub-test filter across the top, then one card per
  published exam showing its question count, duration and the student's best
  band for that sub-test.

  Cards link straight to /practice/{exam}, which starts or resumes the attempt
  server-side — so a refresh or a back-button never loses progress.
-->
<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2, ListChecks, PlayCircle, Timer } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import SectionIcon from '@/components/SectionIcon.vue';
import { BandBadge, EmptyState, SectionBadge } from '@/components/ui';
import { useTranslation } from '@/lib/i18n';
import { formatDate } from '@/lib/format';

const props = defineProps({
    exams: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    inProgress: { type: Object, default: () => ({}) },
});

const { t } = useTranslation();

const SECTION_ORDER = ['Listening', 'Reading', 'Writing', 'Speaking', 'Full'];

/** '' means "all". Kept local so filtering feels instant; the URL stays in sync. */
const activeSection = ref(new URLSearchParams(window.location.search).get('section') || '');

const visible = computed(() =>
    activeSection.value ? props.exams.filter((e) => e.section === activeSection.value) : props.exams
);

const counts = computed(() => {
    const map = { '': props.exams.length };
    for (const s of SECTION_ORDER) map[s] = props.exams.filter((e) => e.section === s).length;
    return map;
});

/** Section metadata, so each filter chip can show its own best band. */
function sectionMeta(section) {
    return props.sections.find((s) => s.section === section) || null;
}

function filterBy(section) {
    activeSection.value = section;
    router.get('/practice', section ? { section } : {}, { preserveState: true, preserveScroll: true, replace: true });
}

// Keep the chips in sync when the user navigates with the browser buttons.
watch(
    () => props.sections,
    () => {
        const fromUrl = new URLSearchParams(window.location.search).get('section') || '';
        if (fromUrl !== activeSection.value) activeSection.value = fromUrl;
    }
);

function attemptId(examId) {
    return props.inProgress?.[examId] ?? null;
}
</script>

<template>
    <Head :title="t('nav.practice')" />

    <AppLayout>
        <div class="container-page py-8">
            <header>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ t('nav.practice') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ t('about.subtitle') }}</p>
            </header>

            <!-- ---- Sub-test filter ---- -->
            <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <button
                    type="button"
                    :class="[
                        'flex items-center gap-3 rounded-xl border px-4 py-3 text-left transition',
                        activeSection === ''
                            ? 'border-primary-500 bg-primary-50 ring-1 ring-primary-500'
                            : 'border-slate-200 bg-white hover:border-primary-300 hover:bg-primary-50/40',
                    ]"
                    @click="filterBy('')"
                >
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                        <ListChecks :size="17" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-slate-900">{{ t('common.all') }}</span>
                        <span class="block text-xs text-slate-500">{{ counts[''] }} {{ t('admin.totalExams') }}</span>
                    </span>
                </button>

                <button
                    v-for="section in SECTION_ORDER"
                    :key="section"
                    type="button"
                    :class="[
                        'flex items-center gap-3 rounded-xl border px-4 py-3 text-left transition',
                        activeSection === section
                            ? 'border-primary-500 bg-primary-50 ring-1 ring-primary-500'
                            : 'border-slate-200 bg-white hover:border-primary-300 hover:bg-primary-50/40',
                    ]"
                    @click="filterBy(section)"
                >
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-50 text-primary-600">
                        <SectionIcon :section="section" :size="17" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-slate-900">{{ t(`sections.${section}`) }}</span>
                        <span class="block text-xs text-slate-500">{{ counts[section] || 0 }} {{ t('admin.totalExams') }}</span>
                    </span>
                </button>
            </div>

            <!-- ---- Section summary (only when one is picked) ---- -->
            <div v-if="activeSection && sectionMeta(activeSection)" class="card mt-6 flex flex-wrap items-center gap-x-6 gap-y-3 card-pad">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('dashboard.testsTaken') }}</p>
                    <p class="mt-0.5 text-lg font-bold tabular-nums text-slate-900">{{ sectionMeta(activeSection).attempts }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('dashboard.averageBand') }}</p>
                    <p class="mt-0.5 text-lg font-bold tabular-nums text-slate-900">
                        {{ sectionMeta(activeSection).avg_band != null ? Number(sectionMeta(activeSection).avg_band).toFixed(1) : '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('dashboard.bestBand') }}</p>
                    <p class="mt-0.5">
                        <BandBadge
                            v-if="sectionMeta(activeSection).best_band != null"
                            :band="sectionMeta(activeSection).best_band"
                            size="sm"
                        />
                        <span v-else class="text-lg font-bold text-slate-300">—</span>
                    </p>
                </div>
                <p class="ml-auto max-w-sm text-sm text-slate-600">{{ t(`sectionDesc.${activeSection}`) }}</p>
            </div>

            <!-- ---- Exam cards ---- -->
            <div v-if="visible.length" class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <article v-for="exam in visible" :key="exam.id" class="card-hover flex flex-col card-pad">
                    <div class="flex items-start justify-between gap-3">
                        <SectionBadge :section="exam.section" />
                        <span v-if="attemptId(exam.id)" class="badge-amber">{{ t('exam.resume') }}</span>
                    </div>

                    <h2 class="mt-3 text-base font-semibold leading-snug text-slate-900">{{ exam.title }}</h2>

                    <p v-if="exam.description" class="mt-2 line-clamp-3 flex-1 text-sm leading-relaxed text-slate-600">
                        {{ exam.description }}
                    </p>
                    <div v-else class="flex-1" />

                    <dl class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-slate-500">
                        <div class="inline-flex items-center gap-1.5">
                            <ListChecks :size="14" />
                            <span>{{ exam.question_count }} {{ t('common.questions') }}</span>
                        </div>
                        <div class="inline-flex items-center gap-1.5">
                            <Timer :size="14" />
                            <span>{{ exam.duration_minutes }} {{ t('common.minutes') }}</span>
                        </div>
                        <div v-if="exam.total_marks" class="inline-flex items-center gap-1.5">
                            <CheckCircle2 :size="14" />
                            <span>{{ exam.total_marks }} {{ t('common.marks') }}</span>
                        </div>
                    </dl>

                    <p v-if="exam.created_at" class="mt-2 text-[11px] text-slate-400">{{ formatDate(exam.created_at) }}</p>

                    <Link
                        :href="`/practice/${exam.id}`"
                        :class="['mt-4 w-full', attemptId(exam.id) ? 'btn-secondary' : 'btn-primary']"
                    >
                        <PlayCircle :size="16" />
                        {{ attemptId(exam.id) ? t('exam.resume') : t('exam.start') }}
                        <ArrowRight :size="15" />
                    </Link>
                </article>
            </div>

            <div v-else class="mt-6">
                <EmptyState :icon="ListChecks" :title="t('common.noData')" :message="t('dashboard.noAttempts')" />
            </div>
        </div>
    </AppLayout>
</template>
