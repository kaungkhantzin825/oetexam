<!--
  Pages/Student/ExamPlayer.vue
  ---------------------------------------------------------------------------
  The exam-taking screen. Responsibilities:

    • Countdown derived from an ABSOLUTE deadline, so a refresh cannot reset it
    • ALL questions shown on a single scrollable page (no one-at-a-time nav)
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
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Cloud,
    CloudOff,
    Flag,
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
const secondsLeft = ref(0);
const saveState = ref('idle'); // idle | saving | saved | error
const lastSavedAt = ref(null);
const showSubmit = ref(false);
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

async function saveDirty({ silent = true, rethrow = false } = {}) {
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
        // When called from doSubmit, propagate the error so the submit does
        // not proceed against a server that just rejected the save (likely
        // a CSRF mismatch or session expiry — the submit would fail too).
        if (rethrow) throw err;
    }
}

/* ==========================================================================
   Answer editing — all questions are on one page, so edits target a specific
   question ID rather than "the current question".
   ========================================================================== */

function setAnswer(questionId, value) {
    dirty.add(questionId);
    answers.value = {
        ...answers.value,
        [questionId]: { ...answers.value[questionId], user_answer: value },
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
function setImage(questionId, path) {
    answers.value = {
        ...answers.value,
        [questionId]: { ...answers.value[questionId], image_path: path || '' },
    };
}

function toggleMark(questionId) {
    dirty.add(questionId);
    answers.value = {
        ...answers.value,
        [questionId]: { ...answers.value[questionId], is_marked: !answers.value[questionId]?.is_marked },
    };
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

/** Group questions by `part` for visual separation on the single-page view. */
const groupedQuestions = computed(() => {
    const groups = [];
    let currentGroup = null;

    for (let i = 0; i < props.questions.length; i++) {
        const q = props.questions[i];
        const part = q.part || '';

        if (!currentGroup || currentGroup.part !== part) {
            currentGroup = { part, questions: [] };
            groups.push(currentGroup);
        }
        currentGroup.questions.push({ ...q, _index: i });
    }
    return groups;
});

/* ==========================================================================
   Submission
   ========================================================================== */

async function doSubmit(auto = false) {
    if (submitted) return;
    submitted = true;
    submitting.value = true;

    const startedMs = new Date(String(props.attempt.started_at).replace(' ', 'T')).getTime() || Date.now();

    try {
        // Save any unsaved answers first, so the server has the latest state.
        // Re-throw on failure so we don't submit against a broken session.
        if (dirty.size > 0) {
            await saveDirty({ silent: true, rethrow: true });
        }

        // Build the full answer payload from ALL answers (not just dirty).
        const allIds = Object.keys(answers.value);
        const payload = payloadFor(allIds);

        // http.post() already unwraps the axios response — the returned value
        // IS the JSON body: { success, message, data: { attempt_id, ... } }
        const result = await http.post(`/attempts/${props.attempt.id}/submit`, {
            answers: payload,
            time_spent_seconds: Math.max(0, Math.round((Date.now() - startedMs) / 1000)),
            auto_submitted: auto,
        });

        if (!auto) toast.success(t('common.submit'));

        // Handle both possible response shapes and fall back gracefully.
        const attemptId = result?.data?.attempt_id ?? result?.attempt_id ?? props.attempt.id;

        // Navigate to the result page.
        router.visit(`/attempts/${attemptId}/result`, { replace: true });
    } catch (err) {
        submitted = false;
        submitting.value = false;
        // If it's a CSRF / session issue (419), the token is stale — reload
        // the page so the meta tag gets a fresh token.
        if (err?.status === 419) {
            toast.error('Your session expired. The page will reload to refresh it.');
            setTimeout(() => window.location.reload(), 2000);
            return;
        }
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
            <div class="mx-auto flex w-full max-w-5xl items-center gap-3 px-4 py-3 sm:px-6">
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

                <button type="button" class="btn-primary inline-flex" @click="showSubmit = true">
                    <Send :size="15" /> {{ t('exam.submitTest') }}
                </button>
            </div>

            <!-- Progress strip — shows answered / total -->
            <div class="h-1 w-full bg-slate-200">
                <div
                    class="h-full bg-primary-600 transition-all duration-300"
                    :style="{ width: total ? (answeredCount / total) * 100 + '%' : '0%' }"
                />
            </div>
        </header>

        <!-- ================= Summary bar ================= -->
        <div class="sticky top-[57px] z-20 border-b border-slate-200 bg-slate-50/95 backdrop-blur">
            <div class="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-4 py-2 sm:px-6">
                <div class="flex items-center gap-4 text-xs">
                    <span class="inline-flex items-center gap-1 text-slate-600">
                        <span class="font-bold text-teal-700">{{ answeredCount }}</span>
                        / {{ total }} {{ t('exam.answered') }}
                    </span>
                    <span class="inline-flex items-center gap-1 text-slate-600">
                        <Flag :size="11" class="text-amber-500" />
                        <span class="font-bold text-amber-700">{{ flaggedCount }}</span>
                        {{ t('exam.flagged') }}
                    </span>
                    <span class="hidden text-slate-600 sm:inline">
                        <span class="font-bold text-slate-700">{{ unansweredCount }}</span>
                        {{ t('exam.unanswered') }}
                    </span>
                </div>
            </div>
        </div>

        <!-- ================= Body — all questions on one page ================= -->
        <div class="mx-auto w-full max-w-5xl flex-1 px-4 py-6 sm:px-6">
            <!-- No questions -->
            <div v-if="!questions.length" class="card card-pad text-center text-sm text-slate-500">
                {{ t('exam.noQuestions') }}
            </div>

            <!-- Questions grouped by part -->
            <template v-else>
                <div
                    v-for="(group, gi) in groupedQuestions"
                    :key="gi"
                    class="mb-6"
                >
                    <!-- Part heading -->
                    <div v-if="group.part" class="mb-3 flex items-center gap-2">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-700">{{ group.part }}</h2>
                        <div class="h-px flex-1 bg-slate-200"></div>
                    </div>

                    <!-- Each question card -->
                    <div
                        v-for="q in group.questions"
                        :key="q.id"
                        :id="`question-${q.id}`"
                        class="card card-pad mb-4 scroll-mt-32"
                    >
                        <QuestionCard
                            :question="q"
                            :index="q._index"
                            :total="total"
                            :value="answers[q.id]?.user_answer ?? ''"
                            :image-path="answers[q.id]?.image_path ?? ''"
                            :is-marked="Boolean(answers[q.id]?.is_marked)"
                            :attempt-id="attempt.id"
                            @update:value="setAnswer(q.id, $event)"
                            @update:image-path="setImage(q.id, $event)"
                            @toggle-mark="toggleMark(q.id)"
                        />
                    </div>
                </div>
            </template>
        </div>

        <!-- ---- Mobile submit bar ---- -->
        <div class="sticky bottom-0 border-t border-slate-200 bg-white p-3 sm:hidden">
            <button type="button" class="btn-primary w-full" @click="showSubmit = true">
                <Send :size="15" /> {{ t('exam.submitTest') }}
            </button>
        </div>

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
