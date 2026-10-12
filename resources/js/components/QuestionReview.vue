<!--
  components/QuestionReview.vue
  ---------------------------------------------------------------------------
  Collapsible panel showing one reviewed question: the student's answer, the
  correct answer, the explanation, the model answer, any AI feedback, and an
  on-demand "Ask AI to explain" action.

  Bilingual content goes through BilingualText, which honours the shared
  `oet_lang_mode` preference (English / မြန်မာ / both).
-->
<script setup>
import { computed, ref } from 'vue';
import {
    BookOpen,
    Brain,
    CheckCircle2,
    ChevronDown,
    Lightbulb,
    ScanText,
    Sparkles,
    Target,
    XCircle,
} from 'lucide-vue-next';
import { ProgressBar, SectionBadge } from './ui';
import BilingualText from './BilingualText.vue';
import { useTranslation } from '@/lib/i18n';
import { langMode } from '@/lib/langMode';
import { optionLetter, resolveAssetUrl } from '@/lib/format';
import { http } from '@/lib/http';
import { toast } from '@/lib/toast';

const props = defineProps({
    q: { type: Object, required: true },
    index: { type: Number, required: true },
});

const { t } = useTranslation();

const open = ref(false);
const explanation = ref(null);
const aiBusy = ref(false);

const isObjective = computed(() => props.q.question_type === 'MCQ' || props.q.question_type === 'ShortAnswer');
const correct = computed(() => props.q.is_correct === true);
const wrong = computed(() => props.q.is_correct === false);

const tone = computed(() => {
    if (!isObjective.value) return 'border-slate-200';
    if (correct.value) return 'border-teal-200 bg-teal-50/40';
    if (wrong.value) return 'border-red-200 bg-red-50/40';
    return 'border-slate-200';
});

const feedback = computed(() => props.q.ai_feedback || null);

/** The photo of a hand-written letter, when the student answered that way. */
const photoUrl = computed(() => (props.q.image_path ? resolveAssetUrl(props.q.image_path) : null));

/** How the candidate answered — a photo means the text was read by the AI. */
const answerLabel = computed(() =>
    props.q.image_path ? t('result.yourAnswerFromPhoto') : t('result.yourAnswer'),
);

/** The AI's own confidence in reading the handwriting, if it reported one. */
const handwritingConfidence = computed(() => feedback.value?.handwriting_confidence || null);

const confidenceTone = computed(() => {
    switch (handwritingConfidence.value) {
        case 'high':
            return 'bg-teal-100 text-teal-800';
        case 'medium':
            return 'bg-amber-100 text-amber-800';
        default:
            return 'bg-red-100 text-red-800';
    }
});

/** The AI may return `criteria` as an object map or as a list of {name,…}. */
const criteriaList = computed(() => {
    const c = feedback.value?.criteria;
    if (!c) return [];
    if (Array.isArray(c)) return c;
    return Object.entries(c).map(([key, v]) => ({ name: key.replace(/_/g, ' '), ...v }));
});

function toneFor(score, max) {
    const ratio = max > 0 ? score / max : 0;
    if (ratio >= 0.7) return 'teal';
    if (ratio >= 0.4) return 'primary';
    return 'amber';
}

/**
 * Ask the AI to explain this answer.
 *
 * The request carries only the question id and the student's own answer — the
 * server looks the official answer up itself, so nothing can be tampered with
 * from the client.
 */
async function askAi() {
    aiBusy.value = true;
    try {
        const res = await http.post('/ai/explain', {
            question_id: props.q.question_id,
            user_answer: props.q.user_answer ?? null,
        });
        explanation.value = res.data;
    } catch (err) {
        toast.from(err, t('result.aiExplainFailed'));
    } finally {
        aiBusy.value = false;
    }
}
</script>

<template>
    <div :class="['rounded-xl border', tone]">
        <!-- ---- Header ---- -->
        <button
            type="button"
            class="flex w-full items-start gap-3 p-4 text-left"
            :aria-expanded="open"
            @click="open = !open"
        >
            <span
                :class="[
                    'mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                    !isObjective
                        ? 'bg-slate-200 text-slate-600'
                        : correct
                          ? 'bg-teal-600 text-white'
                          : wrong
                            ? 'bg-red-600 text-white'
                            : 'bg-slate-300 text-slate-700',
                ]"
            >
                {{ index + 1 }}
            </span>

            <span class="min-w-0 flex-1">
                <span class="flex flex-wrap items-center gap-2">
                    <SectionBadge :section="q.section" />
                    <span v-if="q.part" class="badge-slate">{{ q.part }}</span>

                    <template v-if="isObjective">
                        <span v-if="correct" class="badge-green inline-flex items-center gap-1">
                            <CheckCircle2 :size="11" /> {{ t('result.correct') }}
                        </span>
                        <span v-else class="badge-red inline-flex items-center gap-1">
                            <XCircle :size="11" /> {{ t('result.incorrect') }}
                        </span>
                    </template>
                    <span v-else class="badge-purple inline-flex items-center gap-1">
                        <Brain :size="11" /> {{ t('result.aiMarked') }}
                    </span>

                    <span class="badge-slate">
                        {{ Number(q.score || 0).toFixed(1) }} / {{ Number(q.max_score || 0).toFixed(0) }} {{ t('common.marks') }}
                    </span>
                </span>

                <span class="mt-2 line-clamp-2 block text-sm font-medium text-slate-800">{{ q.question_text }}</span>
            </span>

            <ChevronDown :size="18" :class="['mt-1 shrink-0 text-slate-400 transition', open ? 'rotate-180' : '']" />
        </button>

        <!-- ---- Body ---- -->
        <div v-if="open" class="border-t border-slate-200 px-4 pb-4 pt-4">
            <div class="rounded-lg bg-slate-50 p-3.5">
                <p class="whitespace-pre-wrap text-sm leading-relaxed text-slate-800">{{ q.question_text }}</p>
            </div>

            <!-- Photographed hand-written answer -->
            <div v-if="photoUrl" class="mt-3 rounded-lg border border-slate-200 bg-white p-3.5">
                <p class="inline-flex flex-wrap items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <ScanText :size="12" /> {{ t('result.handwrittenAnswer') }}
                    <span
                        v-if="handwritingConfidence"
                        :class="['ml-1 rounded-full px-2 py-0.5 text-[11px] font-bold normal-case', confidenceTone]"
                    >
                        {{ t('result.handwritingConfidence') }}: {{ handwritingConfidence }}
                    </span>
                </p>

                <a :href="photoUrl" target="_blank" rel="noopener" class="mt-2 inline-block" :title="t('result.viewFullPhoto')">
                    <img
                        :src="photoUrl"
                        alt=""
                        class="max-h-72 w-auto rounded-lg border border-slate-300 transition hover:border-primary-400"
                    />
                </a>

                <p v-if="feedback?.legibility_notes" class="mt-2 text-xs italic leading-relaxed text-slate-500">
                    {{ feedback.legibility_notes }}
                </p>
            </div>

            <!-- MCQ options with highlighting -->
            <ul v-if="q.question_type === 'MCQ' && Array.isArray(q.options)" class="mt-3 space-y-2">
                <li
                    v-for="(opt, i) in q.options"
                    :key="i"
                    :class="[
                        'flex items-start gap-3 rounded-lg border p-3 text-sm',
                        i === q.correct_option_index
                            ? 'border-teal-400 bg-teal-50'
                            : i === q.chosen_option_index
                              ? 'border-red-300 bg-red-50'
                              : 'border-slate-200 bg-white',
                    ]"
                >
                    <span
                        :class="[
                            'flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                            i === q.correct_option_index
                                ? 'bg-teal-600 text-white'
                                : i === q.chosen_option_index
                                  ? 'bg-red-600 text-white'
                                  : 'bg-slate-200 text-slate-600',
                        ]"
                    >
                        {{ optionLetter(i) }}
                    </span>
                    <span class="flex-1 text-slate-800">{{ opt }}</span>
                    <CheckCircle2 v-if="i === q.correct_option_index" :size="15" class="mt-0.5 shrink-0 text-teal-600" />
                    <XCircle
                        v-else-if="i === q.chosen_option_index"
                        :size="15"
                        class="mt-0.5 shrink-0 text-red-500"
                    />
                </li>
            </ul>

            <!-- Short answer / subjective -->
            <div v-if="q.question_type !== 'MCQ'" class="mt-3 grid gap-3 sm:grid-cols-2">
                <div class="rounded-lg border border-slate-200 p-3.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ answerLabel }}</p>
                    <p v-if="q.user_answer" class="prose-answer mt-1.5">{{ q.user_answer }}</p>
                    <p v-else-if="q.image_path" class="mt-1.5 text-sm italic text-slate-400">
                        {{ t('result.transcriptUnavailable') }}
                    </p>
                    <p v-else class="mt-1.5 text-sm italic text-slate-400">{{ t('result.noAnswerGiven') }}</p>
                </div>

                <div v-if="q.correct_answer" class="rounded-lg border border-teal-200 bg-teal-50/50 p-3.5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-teal-700">{{ t('result.correctAnswer') }}</p>
                    <p class="prose-answer mt-1.5">{{ q.correct_answer }}</p>
                </div>
            </div>

            <!-- Explanation -->
            <div
                v-if="q.explanation || q.explanation_my"
                class="mt-3 flex items-start gap-2.5 rounded-lg border border-primary-200 bg-primary-50/60 p-3.5"
            >
                <Lightbulb :size="16" class="mt-0.5 shrink-0 text-primary-600" />
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-primary-700">{{ t('result.explanation') }}</p>
                    <BilingualText class="mt-1" :en="q.explanation" :my="q.explanation_my" :mode="langMode" />
                </div>
            </div>

            <!-- Model answer -->
            <div v-if="q.model_answer" class="mt-3 rounded-lg border border-slate-200 bg-white p-3.5">
                <p class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <BookOpen :size="12" /> {{ t('result.modelAnswer') }}
                </p>
                <BilingualText class="mt-2" :en="q.model_answer" :my="q.model_answer_my" :mode="langMode" />
            </div>

            <!-- AI feedback (Writing / Speaking) -->
            <div v-if="feedback" class="mt-3 rounded-lg border border-purple-200 bg-purple-50/50 p-3.5">
                <p class="inline-flex flex-wrap items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-purple-700">
                    <Sparkles :size="12" /> {{ t('result.aiFeedback') }}
                    <span
                        v-if="feedback.band_score != null"
                        class="ml-1 rounded-full bg-white px-2 py-0.5 text-[11px] font-bold text-purple-700"
                    >
                        {{ t('common.band') }} {{ Number(feedback.band_score).toFixed(1) }}
                    </span>
                </p>

                <p v-if="feedback.notice" class="mt-2 rounded-md bg-amber-50 p-2.5 text-xs text-amber-800">
                    {{ feedback.notice }}
                </p>

                <div v-if="criteriaList.length" class="mt-3 space-y-2">
                    <div v-for="c in criteriaList" :key="c.name" class="rounded-lg bg-white p-3">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs font-semibold capitalize text-slate-700">{{ c.name }}</p>
                            <span class="text-xs font-bold tabular-nums text-purple-700">{{ c.score }} / {{ c.max }}</span>
                        </div>
                        <ProgressBar
                            v-if="c.max"
                            class="mt-1.5"
                            :value="Number(c.score) || 0"
                            :max="Number(c.max) || 1"
                            :tone="toneFor(Number(c.score) || 0, Number(c.max) || 1)"
                        />
                        <p v-if="c.comment" class="mt-1.5 text-xs leading-relaxed text-slate-600">{{ c.comment }}</p>
                    </div>
                </div>

                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div v-if="feedback.strengths?.length" class="rounded-lg bg-white p-3">
                        <p class="text-xs font-semibold text-teal-700">{{ t('result.strengths') }}</p>
                        <ul class="mt-1.5 space-y-1">
                            <li
                                v-for="(s, i) in feedback.strengths"
                                :key="i"
                                class="flex items-start gap-1.5 text-xs leading-relaxed text-slate-600"
                            >
                                <CheckCircle2 :size="11" class="mt-0.5 shrink-0 text-teal-500" /> {{ s }}
                            </li>
                        </ul>
                    </div>

                    <div v-if="feedback.improvements?.length" class="rounded-lg bg-white p-3">
                        <p class="text-xs font-semibold text-amber-700">{{ t('result.improvements') }}</p>
                        <ul class="mt-1.5 space-y-1">
                            <li
                                v-for="(s, i) in feedback.improvements"
                                :key="i"
                                class="flex items-start gap-1.5 text-xs leading-relaxed text-slate-600"
                            >
                                <Target :size="11" class="mt-0.5 shrink-0 text-amber-500" /> {{ s }}
                            </li>
                        </ul>
                    </div>
                </div>

                <div v-if="feedback.corrected_sentences?.length" class="mt-3 rounded-lg bg-white p-3">
                    <p class="text-xs font-semibold text-slate-700">{{ t('result.corrections') }}</p>
                    <ul class="mt-2 space-y-2.5">
                        <li v-for="(c, i) in feedback.corrected_sentences" :key="i" class="text-xs leading-relaxed">
                            <p class="text-red-600 line-through">{{ c.original }}</p>
                            <p class="text-teal-700">{{ c.corrected }}</p>
                            <p v-if="c.reason" class="mt-0.5 text-slate-500">{{ c.reason }}</p>
                        </li>
                    </ul>
                </div>

                <p v-if="feedback.overall_feedback" class="mt-3 rounded-lg bg-white p-3 text-xs leading-relaxed text-slate-700">
                    {{ feedback.overall_feedback }}
                </p>
            </div>

            <!-- On-demand AI explanation -->
            <div v-if="isObjective" class="mt-3">
                <button
                    v-if="!explanation"
                    type="button"
                    class="btn-secondary btn-sm gap-1.5"
                    :disabled="aiBusy"
                    @click="askAi"
                >
                    <Sparkles :size="13" /> {{ aiBusy ? t('result.askingAi') : t('result.askAi') }}
                </button>

                <div v-else class="rounded-lg border border-purple-200 bg-purple-50/50 p-3.5">
                    <p class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-purple-700">
                        <Sparkles :size="12" /> {{ t('result.aiExplanation') }}
                    </p>

                    <div class="mt-2 space-y-2 text-sm leading-relaxed text-slate-700">
                        <p v-if="explanation.why_correct">
                            <strong class="text-slate-800">{{ t('result.whyCorrect') }}: </strong>{{ explanation.why_correct }}
                        </p>
                        <p v-if="explanation.why_student">
                            <strong class="text-slate-800">{{ t('result.yourAnswer') }}: </strong>{{ explanation.why_student }}
                        </p>
                        <p v-if="explanation.key_concept">
                            <strong class="text-slate-800">{{ t('result.keyConcept') }}: </strong>{{ explanation.key_concept }}
                        </p>
                        <p v-if="explanation.study_tip" class="rounded-md bg-white p-2.5 text-xs text-slate-600">
                            <strong>{{ t('result.studyTip') }}: </strong>{{ explanation.study_tip }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
