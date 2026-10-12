<!--
  components/QuestionCard.vue
  ---------------------------------------------------------------------------
  Renders one exam question in the player. Handles all four OET question types:

    MCQ          — single-select option list (A/B/C/D)
    ShortAnswer  — free-text single-line answer
    Writing      — long-form letter, either typed OR photographed from paper
    RolePlay     — role-play card + long-form response

  This component is purely controlled: it reports changes upward via
  `update:value` / `update:imagePath` / `toggle-mark` and never mutates the
  attempt state itself.

  IMPORTANT — the MCQ payload is the 0-based option INDEX as a string ("0" for
  option A). Never emit the option text; the server's marking resolves the index
  and anything unmappable is marked WRONG, never right.
-->
<script setup>
import { computed, ref, watch } from 'vue';
import { AlertTriangle, Check, FileText, Headphones, Info, Mic, PenLine, ScanText } from 'lucide-vue-next';
import AudioPlayer from './AudioPlayer.vue';
import WritingPhotoUpload from './WritingPhotoUpload.vue';
import { useTranslation } from '@/lib/i18n';
import { resolveAssetUrl } from '@/lib/format';

const props = defineProps({
    question: { type: Object, required: true },
    index: { type: Number, required: true },
    total: { type: Number, required: true },
    value: { type: [String, Number], default: '' },
    isMarked: { type: Boolean, default: false },
    /** Needed by the photo upload; only the Writing task uses it. */
    attemptId: { type: Number, default: null },
    imagePath: { type: String, default: '' },
});

const emit = defineEmits(['update:value', 'update:imagePath', 'toggle-mark']);

const { t } = useTranslation();

const LETTERS = ['A', 'B', 'C', 'D', 'E', 'F'];

const audioUrl = computed(() => (props.question.audio_url ? resolveAssetUrl(props.question.audio_url) : null));
const options = computed(() => (Array.isArray(props.question.options) ? props.question.options : []));
const card = computed(() => props.question.role_play_card || null);
const hasValue = computed(() => props.value !== '' && props.value != null);

const isWriting = computed(() => props.question.question_type === 'Writing');
const photoSupported = computed(() => isWriting.value && Boolean(props.attemptId));

/**
 * How the candidate is answering the Writing task: `type` or `photo`.
 * A question that already has a photo opens in photo mode, so revisiting it
 * during a sitting shows the work rather than an empty textarea.
 */
const writingMode = ref(props.imagePath ? 'photo' : 'type');

watch(
    () => props.imagePath,
    (path) => {
        if (path) writingMode.value = 'photo';
    },
);

/** Word counter used by the typed Writing task. */
const wordCount = computed(() => String(props.value || '').trim().split(/\s+/).filter(Boolean).length);

function select(index) {
    // 0-based index as a string — the storage convention for every MCQ answer.
    emit('update:value', String(index));
}
</script>

<template>
    <div class="animate-fade-in">
        <!-- ---- Header: number, type, marks, flag ---- -->
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="badge-blue">{{ t('exam.questionOf', { current: index + 1, total }) }}</span>
                    <span v-if="question.part" class="badge-slate">{{ question.part }}</span>
                    <span class="badge-amber">{{ question.marks }} {{ t('common.marks') }}</span>
                </div>
            </div>

            <button
                type="button"
                :aria-pressed="isMarked"
                :class="[
                    'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition',
                    isMarked
                        ? 'border-amber-300 bg-amber-50 text-amber-700'
                        : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50',
                ]"
                @click="emit('toggle-mark')"
            >
                <AlertTriangle :size="13" />
                {{ isMarked ? t('exam.markedForReview') : t('exam.markForReview') }}
            </button>
        </div>

        <!-- ---- Audio player (Listening) ---- -->
        <div v-if="question.section === 'Listening'" class="mb-5 rounded-xl border border-primary-200 bg-primary-50/60 p-4">
            <p class="mb-2 inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-primary-700">
                <Headphones :size="14" /> {{ t('exam.audio') }}
            </p>

            <!-- AudioPlayer preflights with HEAD; never swap this for a bare <audio>. -->
            <AudioPlayer v-if="audioUrl" :src="audioUrl" />
            <p v-else class="inline-flex items-start gap-2 text-sm text-primary-800">
                <Info :size="15" class="mt-0.5 shrink-0" />
                {{ t('exam.audioNotAvailable') }}
            </p>
        </div>

        <!-- ---- Role-play card (Speaking) ---- -->
        <div
            v-if="question.question_type === 'RolePlay' && card"
            class="mb-5 rounded-xl border border-purple-200 bg-purple-50/60 p-4"
        >
            <p class="mb-3 inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-purple-700">
                <Mic :size="14" /> {{ t('exam.rolePlayCard') }}
            </p>

            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div v-if="card.setting">
                    <dt class="text-xs font-semibold text-purple-700">{{ t('exam.setting') }}</dt>
                    <dd class="text-slate-700">{{ card.setting }}</dd>
                </div>
                <div v-if="card.your_role">
                    <dt class="text-xs font-semibold text-purple-700">{{ t('exam.yourRole') }}</dt>
                    <dd class="text-slate-700">{{ card.your_role }}</dd>
                </div>
                <div v-if="card.patient">
                    <dt class="text-xs font-semibold text-purple-700">{{ t('exam.patient') }}</dt>
                    <dd class="text-slate-700">{{ card.patient }}</dd>
                </div>
                <div v-if="card.language_focus">
                    <dt class="text-xs font-semibold text-purple-700">{{ t('exam.languageFocus') }}</dt>
                    <dd class="text-slate-700">{{ card.language_focus }}</dd>
                </div>
            </dl>

            <div v-if="Array.isArray(card.task) && card.task.length" class="mt-3">
                <p class="text-xs font-semibold text-purple-700">{{ t('exam.task') }}</p>
                <ul class="mt-1.5 space-y-1">
                    <li v-for="(taskItem, i) in card.task" :key="i" class="flex items-start gap-2 text-sm text-slate-700">
                        <Check :size="13" class="mt-1 shrink-0 text-purple-500" />
                        <span>{{ taskItem }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- ---- Question text ---- -->
        <div class="mb-5 rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
            <p class="whitespace-pre-wrap text-[15px] font-medium leading-relaxed text-slate-900">
                {{ question.question_text }}
            </p>
        </div>

        <!-- ---- Answer input: MCQ ---- -->
        <div v-if="question.question_type === 'MCQ'" class="space-y-2.5" role="radiogroup" aria-label="Answer options">
            <label
                v-for="(opt, i) in options"
                :key="i"
                :class="['option', String(value) === String(i) ? 'option-selected' : '']"
                @click="select(i)"
            >
                <span
                    :class="[
                        'mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-xs font-bold transition',
                        String(value) === String(i)
                            ? 'border-primary-600 bg-primary-600 text-white'
                            : 'border-slate-300 bg-white text-slate-500',
                    ]"
                >
                    {{ LETTERS[i] || i + 1 }}
                </span>
                <span class="flex-1 leading-relaxed text-slate-800">{{ opt }}</span>
                <Check v-if="String(value) === String(i)" :size="16" class="mt-0.5 shrink-0 text-primary-600" />
            </label>
        </div>

        <!-- ---- Answer input: ShortAnswer ---- -->
        <input
            v-else-if="question.question_type === 'ShortAnswer'"
            type="text"
            class="input text-base"
            autocomplete="off"
            spellcheck="false"
            :placeholder="t('exam.typeAnswer')"
            :value="value || ''"
            @input="emit('update:value', $event.target.value)"
        />

        <!-- ---- Answer input: Writing / RolePlay ---- -->
        <div v-else-if="question.question_type === 'Writing' || question.question_type === 'RolePlay'">
            <!-- Writing only: choose between typing and photographing paper. -->
            <div v-if="photoSupported" class="mb-3">
                <div class="inline-flex rounded-lg border border-slate-300 bg-slate-100 p-0.5" role="tablist">
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="writingMode === 'type'"
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold transition',
                            writingMode === 'type' ? 'bg-white text-primary-700 shadow-sm' : 'text-slate-600 hover:text-slate-800',
                        ]"
                        @click="writingMode = 'type'"
                    >
                        <PenLine :size="13" /> {{ t('exam.answerByTyping') }}
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="writingMode === 'photo'"
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold transition',
                            writingMode === 'photo' ? 'bg-white text-primary-700 shadow-sm' : 'text-slate-600 hover:text-slate-800',
                        ]"
                        @click="writingMode = 'photo'"
                    >
                        <ScanText :size="13" /> {{ t('exam.answerByPhoto') }}
                    </button>
                </div>
            </div>

            <!-- Photograph of a hand-written letter -->
            <WritingPhotoUpload
                v-if="photoSupported && writingMode === 'photo'"
                :attempt-id="attemptId"
                :question-id="question.id"
                :image-path="imagePath"
                @update:image-path="emit('update:imagePath', $event)"
            />

            <!-- Typed letter (and the only option for RolePlay) -->
            <template v-else>
                <!--
                  A photo already on file still wins at submit, so typing over it
                  would silently mark the wrong thing. Say so rather than let the
                  student discover it on the result page.
                -->
                <p
                    v-if="photoSupported && imagePath"
                    class="mb-3 flex flex-wrap items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-relaxed text-amber-800"
                >
                    <AlertTriangle :size="14" class="shrink-0" />
                    <span class="flex-1">{{ t('exam.photoOverridesTypedAnswer') }}</span>
                    <button
                        type="button"
                        class="font-semibold underline"
                        @click="writingMode = 'photo'"
                    >
                        {{ t('exam.managePhoto') }}
                    </button>
                </p>

                <label class="label inline-flex items-center gap-1.5">
                    <FileText :size="14" />
                    {{ question.question_type === 'Writing' ? t('exam.writeLetter') : t('exam.yourResponse') }}
                </label>

                <textarea
                    class="textarea min-h-[280px] text-[15px]"
                    :placeholder="question.question_type === 'Writing' ? t('exam.writeLetter') : t('exam.typeAnswer')"
                    :value="value || ''"
                    @input="emit('update:value', $event.target.value)"
                />

                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
                    <span>
                        {{ wordCount }} {{ t('exam.words') }}
                        <span v-if="question.question_type === 'Writing'" class="ml-1">{{ t('exam.targetWords') }}</span>
                    </span>
                    <span class="inline-flex items-center gap-1 text-slate-400">
                        <Info :size="12" /> {{ t('exam.aiMarked') }}
                    </span>
                </div>
            </template>
        </div>

        <!-- ---- Clear answer ---- -->
        <div v-if="hasValue" class="mt-3">
            <button type="button" class="btn-ghost btn-sm" @click="emit('update:value', '')">
                {{ t('exam.clearAnswer') }}
            </button>
        </div>
    </div>
</template>
