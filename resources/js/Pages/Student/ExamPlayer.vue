<!--
  Pages/Student/ExamPlayer.vue
  ---------------------------------------------------------------------------
  The exam-taking screen. Responsibilities:

    • Countdown derived from an ABSOLUTE deadline, so a refresh cannot reset it
    • Question navigation: previous / next / jump via the navigator grid
    • Auto-save of changed answers every 10 seconds, plus save on exit
    • "Mark for review" flagging
    • Submit confirmation with a summary of unanswered questions
    • Automatic submission when the timer reaches zero
    • A beforeunload guard so a refresh does not silently lose work

  The page renders bare (no site chrome) so nothing competes with the exam.

  NOTE — the attempt is created server-side by ExamController@play, so this
  component never has to POST "start"; a plain page load is enough.
-->
<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ChevronLeft,
    ChevronRight,
    Cloud,
    CloudOff,
    Flag,
    Grid3x3,
    Send,
    X,
} from 'lucide-vue-next';
import { AppModal, Spinner } from '@/components/ui';
import QuestionCard from '@/components/QuestionCard.vue';
import Timer from '@/components/Timer.vue';
import { useTranslation } from '@/lib/i18n';
import { http } from '@/lib/http';
import { toast } from '@/lib/toast';

const props = defineProps({
    exam: { type: Object, required: true },
    attempt: { type: Object, required: true },
    questions: { type: Array, default: () => [] },
    savedAnswers: { type: Object, default: () => ({}) },
});

const { t } = useTranslation();

const AUTOSAVE_INTERVAL_MS = 10000;

/* ==========================================================================
   State
   ========================================================================== */

/** answers: { [questionId]: { user_answer, image_path, is_marked, time_spent_seconds } } */
const answers = ref({});
const index = ref(0);
const secondsLeft = ref(0);
const saveState = ref('idle'); // idle | saving | saved | error
const lastSavedAt = ref(null);
const showSubmit = ref(false);
const showNav = ref(false);
const submitting = ref(false);

// Non-reactive bookkeeping
const dirty = new Set();
let deadline = 0;
let submitted = false;
let timerId = null;
let autosaveId = null;

/* ==========================================================================
   Boot: hydrate saved answers and compute the deadline
   ========================================================================== */

function hydrate() {
    const initial = {};
    for (const q of props.questions) {
        initial[q.id] = { user_answer: '', image_path: '', is_marked: false, time_spent_seconds: 0 };
    }
    for (const [qid, saved] of Object.entries(props.savedAnswers || {})) {
        initial[qid] = {
            user_answer: saved.user_answer ?? '',
            // A photographed hand-written answer. Restored so a refresh during
            // the sitting still shows the photo the student already uploaded.
            image_path: saved.image_path ?? '',
            is_marked: Boolean(saved.is_marked),
            time_spent_seconds: Number(saved.time_spent_seconds) || 0,
        };
    }
    answers.value = initial;

    // The deadline is absolute: start + duration. A refresh therefore keeps the
    // same clock instead of restarting the test.
    const startMs = new Date(String(props.attempt.started_at).replace(' ', 'T')).getTime() || Date.now();
    deadline = startMs + (props.exam.duration_minutes || 0) * 60 * 1000;

    // If an abandoned sitting is resumed after its clock ran out, restarting is
    // kinder than auto-submitting an untouched attempt — and the student is told.
    if (props.attempt.resumed && deadline - Date.now() <= 0) {
        deadline = Date.now() + (props.exam.duration_minutes || 0) * 60 * 1000;
        toast.info(t('exam.sessionRestarted'));
    }

    secondsLeft.value = Math.max(0, Math.round((deadline - Date.now()) / 1000));
}

/* ==========================================================================
   Timer
   ========================================================================== */

function tick() {
    secondsLeft.value = Math.max(0, Math.round((deadline - Date.now()) / 1000));
    if (secondsLeft.value === 0 && !submitted) {
        toast.error(t('exam.timeUp'), 6000);
        doSubmit(true);
    }
}

/* ==========================================================================
   Auto-save
   ========================================================================== */

function payloadFor(ids) {
    return ids.map((id) => ({
        question_id: Number(id),
        user_answer: answers.value[id]?.user_answer ?? '',
        is_marked: Boolean(answers.value[id]?.is_marked),
        time_spent_seconds: answers.value[id]?.time_spent_seconds || 0,
    }));
}

async function saveDirty({ silent = true } = {}) {
    const ids = [...dirty];
    if (!ids.length) return;

    saveState.value = 'saving';
    try {
        await http.post(`/attempts/${props.attempt.id}/save`, { answers: payloadFor(ids) });
        // Only clear the ids we actually sent — new edits may have landed since.
        ids.forEach((id) => dirty.delete(id));
        saveState.value = 'saved';
        lastSavedAt.value = new Date();
        if (!silent) toast.success(t('exam.autosaved'));
    } catch (err) {
        saveState.value = 'error';
        if (!silent) toast.from(err, t('errors.saveFailed'));
    }
}

/* ==========================================================================
   Answer editing
   ========================================================================== */

const current = computed(() => props.questions[index.value] || null);

function setAnswer(value) {
    const q = current.value;
    if (!q) return;
    dirty.add(q.id);
    answers.value = {
        ...answers.value,
        [q.id]: { ...answers.value[q.id], user_answer: value },
    };
}

/**
 * Record a photographed hand-written answer.
 *
 * Deliberately NOT marked dirty: the upload endpoint has already written the
 * path to the database, so there is nothing for the auto-save to carry. The
 * local copy exists only so the player can show the photo and count the
 * question as answered.
 */
function setImage(path) {
    const q = current.value;
    if (!q) return;
    answers.value = {
        ...answers.value,
        [q.id]: { ...answers.value[q.id], image_path: path || '' },
    };
}

function toggleMark() {
    const q = current.value;
    if (!q) return;
    dirty.add(q.id);
    answers.value = {
        ...answers.value,
        [q.id]: { ...answers.value[q.id], is_marked: !answers.value[q.id]?.is_marked },
    };
}

function goTo(i) {
    index.value = Math.max(0, Math.min(props.questions.length - 1, i));
    showNav.value = false;
}

/* ==========================================================================
   Derived counts
   ========================================================================== */

const total = computed(() => props.questions.length);

function isAnswered(q) {
    const a = answers.value[q.id];
    if (!a) return false;
    // A photo of a hand-written letter is an answer even with no typed text.
    if (a.image_path) return true;
    return a.user_answer != null && String(a.user_answer).trim() !== '';
}

const answeredCount = computed(() => props.questions.filter(isAnswered).length);
const flaggedCount = computed(() => props.questions.filter((q) => answers.value[q.id]?.is_marked).length);
const unansweredCount = computed(() => total.value - answeredCount.value);

function navClass(q, i) {
    const a = answers.value[q.id];
    let cls = 'border-slate-300 bg-white text-slate-600 hover:border-primary-400';
    if (isAnswered(q)) cls = 'border-teal-500 bg-teal-50 text-teal-700';
    if (a?.is_marked) cls = 'border-amber-400 bg-amber-50 text-amber-700';
    if (i === index.value) cls += ' ring-2 ring-primary-500 ring-offset-1';
    return ['qnav-btn', cls];
}

/* ==========================================================================
   Submission
   ========================================================================== */

async function doSubmit(auto = false) {
    if (submitted) return;
    submitted = true;
    submitting.value = true;

    const startedMs = new Date(String(props.attempt.started_at).replace(' ', 'T')).getTime() || Date.now();

    try {
        const res = await http.post(`/attempts/${props.attempt.id}/submit`, {
            answers: payloadFor(Object.keys(answers.value)),
            time_spent_seconds: Math.max(0, Math.round((Date.now() - startedMs) / 1000)),
            auto_submitted: auto,
        });

        if (!auto) toast.success(t('common.submit'));
        router.visit(`/attempts/${res.data.attempt_id}/result`, { replace: true });
    } catch (err) {
        submitted = false;
        submitting.value = false;
        toast.from(err, t('errors.generic'));
    }
}

/* ==========================================================================
   Lifecycle
   ========================================================================== */

function beforeUnload(e) {
    if (submitted || dirty.size === 0) return;
    e.preventDefault();
    e.returnValue = t('exam.leavingWarning');
    return e.returnValue;
}

function saveAndExit() {
    saveDirty({ silent: false }).then(() => {
        if (window.confirm(t('exam.leavingWarning'))) router.visit('/practice');
    });
}

function jumpToFirstFlagged() {
    const i = props.questions.findIndex((q) => answers.value[q.id]?.is_marked);
    if (i >= 0) goTo(i);
}

onMounted(() => {
    hydrate();
    tick();
    timerId = setInterval(tick, 1000);
    autosaveId = setInterval(() => saveDirty(), AUTOSAVE_INTERVAL_MS);
    window.addEventListener('beforeunload', beforeUnload);
});

onBeforeUnmount(() => {
    clearInterval(timerId);
    clearInterval(autosaveId);
    window.removeEventListener('beforeunload', beforeUnload);
});

// Warn before losing the exam if the student closes the tab mid-attempt.
watch(showSubmit, (open) => {
    if (open) showNav.value = false;
});

const saveLabel = computed(() => {
    switch (saveState.value) {
        case 'saving':
            return t('exam.autosaving');
        case 'saved':
            return t('exam.autosaved');
        case 'error':
            return t('exam.notSaved');
        default:
            return t('exam.progressHint');
    }
});
</script>

<template>
    <Head :title="exam.title" />

    <div class="flex min-h-screen flex-col bg-slate-50">
        <!-- ================= Top bar ================= -->
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex w-full max-w-7xl items-center gap-3 px-4 py-3 sm:px-6">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-bold text-slate-900">{{ exam.title }}</p>
                    <p class="mt-0.5 inline-flex items-center gap-1.5 text-xs text-slate-500">
                        <Spinner v-if="saveState === 'saving'" :size="11" class="text-primary-600" />
                        <Cloud v-else-if="saveState === 'saved'" :size="12" class="text-teal-600" />
                        <CloudOff v-else-if="saveState === 'error'" :size="12" class="text-red-500" />
                        <span :class="saveState === 'error' ? 'text-red-600' : ''">{{ saveLabel }}</span>
                        <span v-if="saveState === 'saved' && lastSavedAt" class="text-slate-400">
                            · {{ lastSavedAt.toLocaleTimeString() }}
                        </span>
                    </p>
                </div>

                <Timer :remaining="secondsLeft" />

                <button type="button" class="btn-primary hidden sm:inline-flex" @click="showSubmit = true">
                    <Send :size="15" /> {{ t('exam.submitTest') }}
                </button>

                <button
                    type="button"
                    class="btn-secondary lg:hidden"
                    :aria-label="t('exam.questionNavigator')"
                    @click="showNav = true"
                >
                    <Grid3x3 :size="17" />
                </button>
            </div>

            <!-- Progress strip -->
            <div class="h-1 w-full bg-slate-200">
                <div
                    class="h-full bg-primary-600 transition-all duration-300"
                    :style="{ width: total ? ((index + 1) / total) * 100 + '%' : '0%' }"
                />
            </div>
        </header>

        <!-- ================= Body ================= -->
        <div class="mx-auto flex w-full max-w-7xl flex-1 gap-6 px-4 py-6 sm:px-6">
            <!-- ---- Question ---- -->
            <main class="min-w-0 flex-1">
                <div v-if="current" class="card card-pad">
                    <QuestionCard
                        :question="current"
                        :index="index"
                        :total="total"
                        :value="answers[current.id]?.user_answer ?? ''"
                        :image-path="answers[current.id]?.image_path ?? ''"
                        :is-marked="Boolean(answers[current.id]?.is_marked)"
                        :attempt-id="attempt.id"
                        @update:value="setAnswer"
                        @update:image-path="setImage"
                        @toggle-mark="toggleMark"
                    />

                    <!-- Prev / next -->
                    <div class="mt-6 flex items-center justify-between gap-3 border-t border-slate-200 pt-5">
                        <button type="button" class="btn-secondary" :disabled="index === 0" @click="goTo(index - 1)">
                            <ChevronLeft :size="16" /> {{ t('common.previous') }}
                        </button>

                        <span class="text-xs text-slate-500">
                            {{ t('exam.questionOf', { current: index + 1, total }) }}
                        </span>

                        <button v-if="index === total - 1" type="button" class="btn-success" @click="showSubmit = true">
                            <Send :size="16" /> {{ t('exam.submitTest') }}
                        </button>
                        <button v-else type="button" class="btn-primary" @click="goTo(index + 1)">
                            {{ t('common.next') }} <ChevronRight :size="16" />
                        </button>
                    </div>
                </div>

                <div v-else class="card card-pad text-center text-sm text-slate-500">
                    {{ t('exam.noQuestions') }}
                </div>
            </main>

            <!-- ---- Side navigator (desktop) ---- -->
            <aside class="hidden w-64 shrink-0 lg:block">
                <div class="card sticky top-24 p-4">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{ t('exam.questionNavigator') }}
                    </h2>

                    <div class="navigator-scroll mt-3 grid max-h-[320px] grid-cols-6 gap-2 overflow-y-auto">
                        <button
                            v-for="(q, i) in questions"
                            :key="q.id"
                            type="button"
                            :class="navClass(q, i)"
                            :title="q.part || `Question ${i + 1}`"
                            @click="goTo(i)"
                        >
                            {{ i + 1 }}
                        </button>
                    </div>

                    <dl class="mt-4 space-y-1.5 border-t border-slate-100 pt-3.5 text-xs">
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-500">{{ t('exam.answered') }}</dt>
                            <dd class="font-semibold text-teal-700">{{ answeredCount }} / {{ total }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="inline-flex items-center gap-1 text-slate-500">
                                <Flag :size="11" class="text-amber-500" /> {{ t('exam.flagged') }}
                            </dt>
                            <dd class="font-semibold text-amber-700">{{ flaggedCount }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-500">{{ t('exam.unanswered') }}</dt>
                            <dd class="font-semibold text-slate-700">{{ unansweredCount }}</dd>
                        </div>
                    </dl>

                    <button type="button" class="btn-primary mt-4 w-full" @click="showSubmit = true">
                        <Send :size="15" /> {{ t('exam.submitTest') }}
                    </button>
                </div>
            </aside>
        </div>

        <!-- ---- Mobile submit bar ---- -->
        <div class="sticky bottom-0 border-t border-slate-200 bg-white p-3 sm:hidden">
            <button type="button" class="btn-primary w-full" @click="showSubmit = true">
                <Send :size="15" /> {{ t('exam.submitTest') }}
            </button>
        </div>

        <!-- ================= Mobile navigator ================= -->
        <AppModal :open="showNav" :title="t('exam.questionNavigator')" @close="showNav = false">
            <div class="grid grid-cols-6 gap-2 sm:grid-cols-8">
                <button
                    v-for="(q, i) in questions"
                    :key="q.id"
                    type="button"
                    :class="navClass(q, i)"
                    @click="goTo(i)"
                >
                    {{ i + 1 }}
                </button>
            </div>

            <div class="mt-5 grid grid-cols-3 gap-3 border-t border-slate-100 pt-4 text-center text-xs">
                <div>
                    <p class="font-bold text-teal-700">{{ answeredCount }}</p>
                    <p class="text-slate-500">{{ t('exam.answered') }}</p>
                </div>
                <div>
                    <p class="font-bold text-amber-700">{{ flaggedCount }}</p>
                    <p class="text-slate-500">{{ t('exam.flagged') }}</p>
                </div>
                <div>
                    <p class="font-bold text-slate-700">{{ unansweredCount }}</p>
                    <p class="text-slate-500">{{ t('exam.unanswered') }}</p>
                </div>
            </div>
        </AppModal>

        <!-- ================= Submit confirmation ================= -->
        <AppModal
            :open="showSubmit"
            :title="t('exam.submitConfirmTitle')"
            size="md"
            :close-on-backdrop="!submitting"
            @close="showSubmit = false"
        >
            <p class="text-sm leading-relaxed text-slate-600">
                {{ t('exam.submitConfirmBody', { answered: answeredCount, total }) }}
            </p>

            <div class="mt-5 grid grid-cols-3 gap-3 rounded-xl bg-slate-50 p-4 text-center">
                <div>
                    <p class="text-xl font-bold text-teal-700">{{ answeredCount }}</p>
                    <p class="text-xs text-slate-500">{{ t('exam.answered') }}</p>
                </div>
                <div>
                    <p class="text-xl font-bold text-amber-700">{{ flaggedCount }}</p>
                    <p class="text-xs text-slate-500">{{ t('exam.flagged') }}</p>
                </div>
                <div>
                    <p class="text-xl font-bold text-slate-700">{{ unansweredCount }}</p>
                    <p class="text-xs text-slate-500">{{ t('exam.unanswered') }}</p>
                </div>
            </div>

            <p
                v-if="unansweredCount > 0"
                class="mt-4 flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-xs leading-relaxed text-amber-800"
            >
                <AlertTriangle :size="14" class="mt-0.5 shrink-0" />
                {{ t('exam.unansweredWarning', { count: unansweredCount }) }}
            </p>

            <p
                v-if="flaggedCount > 0"
                class="mt-3 flex flex-wrap items-center gap-1.5 rounded-lg bg-primary-50 p-3 text-xs leading-relaxed text-primary-800"
            >
                <Flag :size="14" class="shrink-0" />
                <span>{{ t('exam.flaggedWarning', { count: flaggedCount }) }}</span>
                <button type="button" class="font-semibold underline" @click="jumpToFirstFlagged">
                    {{ t('exam.reviewFlagged') }}
                </button>
            </p>

            <template #footer>
                <button type="button" class="btn-secondary" :disabled="submitting" @click="showSubmit = false">
                    {{ t('common.cancel') }}
                </button>
                <button type="button" class="btn-primary" :disabled="submitting" @click="doSubmit(false)">
                    <Spinner v-if="submitting" :size="16" />
                    <Send v-else :size="16" />
                    {{ submitting ? t('exam.submitting') : t('common.submit') }}
                </button>
            </template>
        </AppModal>

        <!-- Save-and-exit, always available -->
        <button
            type="button"
            class="fixed bottom-4 right-4 z-20 hidden rounded-full border border-slate-300 bg-white p-3 text-slate-500 shadow-lg transition hover:bg-slate-50 hover:text-slate-700 lg:flex"
            :title="t('exam.saveAndExit')"
            :aria-label="t('exam.saveAndExit')"
            @click="saveAndExit"
        >
            <X :size="18" />
        </button>
    </div>
</template>
