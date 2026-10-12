<!--
  Pages/Student/Result.vue
  ---------------------------------------------------------------------------
  Result & Feedback page.

    • Overall band score and per-section breakdown
    • Every question with the student's answer, the correct answer, the
      explanation, the model answer and any AI feedback
    • A filter to show only the questions that were answered incorrectly
    • "Ask AI to explain" for any individual objective question (on demand)

  The whole payload arrives from AttemptController@result as Inertia props, so
  this page needs no client-side fetch. Note that the correct answers and
  explanations ARE present here — this is the review screen, which is exactly
  why the exam-player payload omits them.
-->
<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Award,
    CheckCircle2,
    ClipboardList,
    Clock,
    Printer,
    RotateCcw,
    TrendingUp,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import QuestionReview from '@/components/QuestionReview.vue';
import LangModeToggle from '@/components/LangModeToggle.vue';
import { BandBadge, EmptyState, ProgressBar, SectionBadge } from '@/components/ui';
import { useTranslation } from '@/lib/i18n';
import { formatDuration } from '@/lib/format';

const props = defineProps({
    attempt: { type: Object, required: true },
    exam: { type: Object, required: true },
    summary: { type: Object, default: () => ({}) },
    questions: { type: Array, default: () => [] },
});

const { t } = useTranslation();

const filter = ref('all'); // all | wrong

const filtered = computed(() =>
    filter.value === 'wrong' ? props.questions.filter((q) => q.is_correct === false) : props.questions
);

const sectionEntries = computed(() => Object.entries(props.attempt.section_scores || {}));

/** 1-based position of a question inside the FULL list (not the filtered one). */
function fullIndex(q) {
    return props.questions.findIndex((x) => x.question_id === q.question_id);
}

const passed = computed(() => Number(props.attempt.band_score) >= 5);

const summaryStrip = computed(() => [
    {
        icon: ClipboardList,
        label: t('exam.answered'),
        value: `${props.summary.answered ?? 0} / ${props.summary.total_questions ?? 0}`,
    },
    {
        icon: CheckCircle2,
        label: t('result.correctAnswers'),
        value: `${props.summary.correct_objective ?? 0} / ${props.summary.total_objective ?? 0}`,
    },
    {
        icon: TrendingUp,
        label: t('result.accuracy'),
        value: `${Number(props.summary.accuracy || 0).toFixed(0)}%`,
    },
    {
        icon: Clock,
        label: t('result.timeTaken'),
        value: formatDuration(props.attempt.time_spent_seconds, t),
    },
]);

function retake() {
    router.visit(`/practice/${props.attempt.exam_id}`);
}

// `window` is not exposed inside a Vue template, so printing goes through here.
function printPage() {
    window.print();
}
</script>

<template>
    <Head :title="t('result.title')" />

    <AppLayout>
        <div class="container-page py-8">
            <!-- ================= Back + actions ================= -->
            <div class="no-print flex flex-wrap items-center justify-between gap-3">
                <Link href="/dashboard" class="btn-ghost btn-sm gap-1.5">
                    <ArrowLeft :size="15" /> {{ t('result.backToDashboard') }}
                </Link>

                <div class="flex gap-2">
                    <button type="button" class="btn-secondary btn-sm gap-1.5" @click="printPage">
                        <Printer :size="14" /> {{ t('result.print') }}
                    </button>
                    <button type="button" class="btn-primary btn-sm gap-1.5" @click="retake">
                        <RotateCcw :size="14" /> {{ t('result.retake') }}
                    </button>
                </div>
            </div>

            <!-- ================= Score hero ================= -->
            <section class="card mt-5 overflow-hidden">
                <div
                    :class="[
                        'px-5 py-6 sm:px-7',
                        passed
                            ? 'bg-gradient-to-br from-teal-50 to-primary-50'
                            : 'bg-gradient-to-br from-primary-50 to-slate-50',
                    ]"
                >
                    <div class="flex flex-wrap items-center justify-between gap-6">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ exam.title }}</p>
                            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ t('result.title') }}</h1>
                            <p class="mt-2 text-sm text-slate-600">{{ attempt.finished_at || '' }}</p>
                        </div>

                        <div class="flex items-center gap-6">
                            <div class="text-center">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('result.bandScore') }}</p>
                                <div class="mt-1.5"><BandBadge :band="attempt.band_score" size="lg" /></div>
                                <p class="mt-1 text-[11px] text-slate-500">{{ t('result.outOf6') }}</p>
                            </div>

                            <div class="text-center">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('common.score') }}</p>
                                <p class="mt-1.5 text-3xl font-extrabold tabular-nums text-slate-900">
                                    {{ Number(attempt.total_score || 0).toFixed(0) }}
                                    <span class="text-base font-semibold text-slate-400">
                                        /{{ Number(attempt.max_score || 0).toFixed(0) }}
                                    </span>
                                </p>
                                <p class="mt-1 text-[11px] text-slate-500">{{ t('common.marks') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary strip -->
                <div class="grid divide-y divide-slate-100 border-t border-slate-200 sm:grid-cols-4 sm:divide-x sm:divide-y-0">
                    <div v-for="item in summaryStrip" :key="item.label" class="flex items-center gap-3 px-5 py-4">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                            <component :is="item.icon" :size="16" />
                        </span>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ item.label }}</p>
                            <p class="text-sm font-bold tabular-nums text-slate-900">{{ item.value }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ================= Section breakdown ================= -->
            <section v-if="sectionEntries.length" class="mt-7">
                <h2 class="inline-flex items-center gap-2 text-lg font-bold text-slate-900">
                    <Award :size="18" class="text-amber-500" />
                    {{ t('result.sectionBreakdown') }}
                </h2>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="[section, s] in sectionEntries" :key="section" class="card card-pad">
                        <div class="flex items-center justify-between">
                            <SectionBadge :section="section" />
                            <BandBadge :band="s.band" size="sm" />
                        </div>

                        <p class="mt-3 text-2xl font-bold tabular-nums text-slate-900">
                            {{ Number(s.score || 0).toFixed(0) }}
                            <span class="text-sm font-semibold text-slate-400">/{{ Number(s.max || 0).toFixed(0) }}</span>
                        </p>

                        <ProgressBar
                            class="mt-2.5"
                            :value="Number(s.score) || 0"
                            :max="Number(s.max) || 1"
                            :tone="s.band >= 5 ? 'teal' : s.band >= 3.5 ? 'primary' : 'amber'"
                        />

                        <p class="mt-1.5 text-xs text-slate-500">
                            {{ t('result.percentCorrect', { pct: Number(s.percentage || 0).toFixed(0) }) }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- ================= Answer review ================= -->
            <section class="mt-9">
                <div class="no-print flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-bold text-slate-900">{{ t('result.reviewAnswers') }}</h2>

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- The shared EN | မြန်မာ | Both choice — the Tutorials pages
                             follow whatever is picked here. -->
                        <LangModeToggle />

                        <div class="inline-flex rounded-lg border border-slate-300 bg-white p-1">
                            <button
                                v-for="opt in [
                                    { key: 'all', label: t('result.allQuestions') },
                                    { key: 'wrong', label: t('result.onlyWrong') },
                                ]"
                                :key="opt.key"
                                type="button"
                                :class="[
                                    'rounded-md px-3 py-1.5 text-xs font-semibold transition',
                                    filter === opt.key ? 'bg-primary-600 text-white' : 'text-slate-600 hover:bg-slate-100',
                                ]"
                                @click="filter = opt.key"
                            >
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <EmptyState
                        v-if="filtered.length === 0"
                        :icon="CheckCircle2"
                        :title="filter === 'wrong' ? t('result.noIncorrect') : t('result.nothingToReview')"
                        :message="filter === 'wrong' ? t('result.everyObjectiveCorrect') : ''"
                    />

                    <QuestionReview
                        v-for="q in filtered"
                        :key="q.question_id"
                        :q="q"
                        :index="fullIndex(q)"
                    />
                </div>
            </section>

            <!-- ================= Footer CTA ================= -->
            <div class="no-print mt-9 flex flex-wrap justify-center gap-3">
                <Link href="/practice" class="btn-primary">{{ t('nav.practice') }}</Link>
                <Link href="/history" class="btn-secondary">{{ t('nav.history') }}</Link>
            </div>
        </div>
    </AppLayout>
</template>
