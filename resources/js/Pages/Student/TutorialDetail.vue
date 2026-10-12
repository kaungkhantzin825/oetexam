<!--
  Pages/Student/TutorialDetail.vue
  ---------------------------------------------------------------------------
  The lesson reader.

    • Title, summary and body in English, Myanmar, or both (shared `oet_lang_mode`)
    • Key takeaways list, bilingual
    • A read-time tracker that reports `seconds_spent` to the server and a
      "Mark as complete" action (progress never flips back to incomplete)
    • Previous / next navigation so a student can work through the course
-->
<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, CheckCircle2, Clock, ListChecks, Star } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import BilingualMarkdown from '@/components/BilingualMarkdown.vue';
import LangModeToggle from '@/components/LangModeToggle.vue';
import LessonCategoryIcon from '@/components/LessonCategoryIcon.vue';
import { http } from '@/lib/http';
import { toast } from '@/lib/toast';
import { useTranslation } from '@/lib/i18n';
import { langMode } from '@/lib/langMode';
import { LEVEL_TONE } from '@/lib/lessonCategories';

const props = defineProps({
    lesson: { type: Object, required: true },
    navigation: { type: Object, default: () => ({}) },
    progress: { type: Object, default: () => ({}) },
});

const { t, lang } = useTranslation();

const completed = ref(Boolean(props.progress.completed));
const marking = ref(false);

/** Seconds actually spent on this page in the current visit. */
let secondsThisVisit = 0;
let tickId = null;

const preferMy = computed(() => langMode.value === 'my' || lang.value === 'my');

function display(en, my) {
    return preferMy.value && my ? my : en || my;
}

/** Key takeaways are stored as arrays of strings. */
const keyPoints = computed(() => (Array.isArray(props.lesson.key_points) ? props.lesson.key_points : []));
const keyPointsMy = computed(() => (Array.isArray(props.lesson.key_points_my) ? props.lesson.key_points_my : []));
const hasKeyPoints = computed(() => keyPoints.value.length > 0 || keyPointsMy.value.length > 0);

const levelTone = computed(() => LEVEL_TONE[props.lesson.level] || 'badge-slate');

function levelLabel(level) {
    const key = `tutorials.${level}`;
    const translated = t(key);
    return translated === key ? level : translated;
}

function categoryLabel(c) {
    const key = `lessonCategory.${c}`;
    const translated = t(key);
    return translated === key ? c : translated;
}

/**
 * Report reading time. `seconds_spent` is cumulative on the server (it keeps
 * the GREATEST of the old and new values), so sending this visit's elapsed
 * seconds is correct and never rewinds a student's progress.
 */
async function reportProgress(markComplete = false) {
    if (markComplete) marking.value = true;
    try {
        const res = await http.post(`/lessons/${props.lesson.id}/progress`, {
            seconds_spent: secondsThisVisit,
            completed: markComplete ? true : undefined,
        });
        if (markComplete) {
            completed.value = Boolean(res.data?.completed);
            toast.success(t('tutorials.completedBadge'));
        }
    } catch (err) {
        if (markComplete) toast.from(err, t('errors.saveFailed'));
    } finally {
        marking.value = false;
    }
}

onMounted(() => {
    tickId = setInterval(() => {
        secondsThisVisit += 5;
    }, 5000);
});

onBeforeUnmount(() => {
    clearInterval(tickId);
    // Best-effort flush on the way out; a failure here is not worth interrupting
    // the student for.
    if (secondsThisVisit > 0) {
        http.post(`/lessons/${props.lesson.id}/progress`, { seconds_spent: secondsThisVisit }).catch(() => {});
    }
});
</script>

<template>
    <Head :title="lesson.title" />

    <AppLayout>
        <div class="container-page py-8">
            <!-- ---- Back ---- -->
            <Link href="/tutorials" class="btn-ghost btn-sm gap-1.5">
                <ArrowLeft :size="15" /> {{ t('tutorials.backToList') }}
            </Link>

            <!-- ---- Header ---- -->
            <header class="card mt-4 card-pad">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex min-w-0 items-start gap-3.5">
                        <LessonCategoryIcon :category="lesson.category" :size="21" />

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="badge-slate">{{ categoryLabel(lesson.category) }}</span>
                                <span :class="levelTone">{{ levelLabel(lesson.level) }}</span>
                                <span v-if="completed" class="badge-green inline-flex items-center gap-1">
                                    <CheckCircle2 :size="11" /> {{ t('tutorials.completedBadge') }}
                                </span>
                            </div>

                            <h1 class="mt-2.5 text-xl font-bold leading-snug tracking-tight text-slate-900 sm:text-2xl">
                                {{ display(lesson.title, lesson.title_my) }}
                            </h1>

                            <p class="mt-2 inline-flex items-center gap-1.5 text-xs text-slate-500">
                                <Clock :size="13" /> {{ t('tutorials.minutes', { count: lesson.duration_minutes }) }}
                            </p>
                        </div>
                    </div>

                    <div class="no-print flex flex-col items-end gap-3">
                        <LangModeToggle compact />
                        <button
                            type="button"
                            :class="completed ? 'btn-secondary btn-sm' : 'btn-success btn-sm'"
                            :disabled="marking || completed"
                            @click="reportProgress(true)"
                        >
                            <CheckCircle2 :size="14" />
                            {{ completed ? t('tutorials.completedBadge') : t('tutorials.markComplete') }}
                        </button>
                    </div>
                </div>

                <p v-if="lesson.summary || lesson.summary_my" class="mt-4 border-t border-slate-100 pt-4 text-sm leading-relaxed text-slate-600">
                    {{ display(lesson.summary, lesson.summary_my) }}
                </p>
            </header>

            <!-- ---- Body ---- -->
            <article class="card mt-5 card-pad">
                <BilingualMarkdown :en="lesson.content" :my="lesson.content_my" />
            </article>

            <!-- ---- Key takeaways ---- -->
            <section v-if="hasKeyPoints" class="card mt-5 card-pad">
                <h2 class="inline-flex items-center gap-2 text-base font-semibold text-slate-900">
                    <Star :size="17" class="text-amber-500" /> {{ t('tutorials.keyPoints') }}
                </h2>

                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    <div v-if="keyPoints.length">
                        <ul class="space-y-2">
                            <li v-for="(point, i) in keyPoints" :key="`en-${i}`" class="flex items-start gap-2.5">
                                <ListChecks :size="15" class="mt-0.5 shrink-0 text-teal-600" />
                                <span class="text-sm leading-relaxed text-slate-700">{{ point }}</span>
                            </li>
                        </ul>
                    </div>

                    <div v-if="keyPointsMy.length" :class="keyPoints.length ? 'sm:border-l sm:border-dashed sm:border-primary-200 sm:pl-5' : ''">
                        <span class="mb-2 inline-block rounded bg-primary-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-primary-700">
                            {{ t('result.myanmarLabel') }}
                        </span>
                        <ul class="space-y-2">
                            <li v-for="(point, i) in keyPointsMy" :key="`my-${i}`" class="flex items-start gap-2.5">
                                <ListChecks :size="15" class="mt-0.5 shrink-0 text-primary-500" />
                                <span class="text-sm leading-relaxed text-slate-700">{{ point }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- ---- Prev / next ---- -->
            <nav class="no-print mt-6 grid gap-3 sm:grid-cols-2">
                <Link
                    v-if="navigation.prev_slug"
                    :href="`/tutorials/${navigation.prev_slug}`"
                    class="card-hover flex items-center gap-3 p-4"
                >
                    <ArrowLeft :size="17" class="shrink-0 text-slate-400" />
                    <span class="min-w-0">
                        <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                            {{ t('tutorials.prev') }}
                        </span>
                        <span class="block truncate text-sm font-medium text-slate-800">{{ navigation.prev_title }}</span>
                    </span>
                </Link>
                <div v-else />

                <Link
                    v-if="navigation.next_slug"
                    :href="`/tutorials/${navigation.next_slug}`"
                    class="card-hover flex items-center justify-end gap-3 p-4 text-right"
                >
                    <span class="min-w-0">
                        <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                            {{ t('tutorials.next') }}
                        </span>
                        <span class="block truncate text-sm font-medium text-slate-800">{{ navigation.next_title }}</span>
                    </span>
                    <ArrowRight :size="17" class="shrink-0 text-slate-400" />
                </Link>
            </nav>
        </div>
    </AppLayout>
</template>
