<!--
  Pages/Admin/Questions.vue
  ---------------------------------------------------------------------------
  Question CRUD plus the AI drafting flow.

  Two things are easy to get wrong here and are handled deliberately:

   1. `correct_answer` for an MCQ is the **0-based option index as a string**
      ("0" = option A). The select below binds to the index, never to the
      option text. Getting this wrong marks every correct answer wrong.
   2. Audio uploads return a path relative to `public/uploads`, which is what
      `questions.audio_url` stores.
-->
<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Bot, ListChecks, Pencil, Plus, Search, Sparkles, Trash2, Upload } from 'lucide-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { AppModal, AppPagination, ConfirmDialog, EmptyState, SectionBadge, TypeBadge } from '@/components/ui';
import AudioPlayer from '@/components/AudioPlayer.vue';
import { useTranslation } from '@/lib/i18n';
import { http } from '@/lib/http';
import { toast } from '@/lib/toast';
import { resolveAssetUrl } from '@/lib/format';

const props = defineProps({
    questions: { type: Object, default: () => ({ data: [], current_page: 1, last_page: 1 }) },
    exams: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useTranslation();

const SECTIONS = ['Listening', 'Reading', 'Writing', 'Speaking', 'Full'];
const TYPES = ['MCQ', 'ShortAnswer', 'Writing', 'RolePlay'];
const LETTERS = ['A', 'B', 'C', 'D', 'E', 'F'];

/* ---- Filters ---- */
const fExam = ref(props.filters.exam_id ? String(props.filters.exam_id) : '');
const fSection = ref(props.filters.section || '');
const fType = ref(props.filters.type || '');
const fQuery = ref(props.filters.q || '');

function applyFilters() {
    router.get(
        '/admin/questions',
        {
            exam_id: fExam.value || undefined,
            section: fSection.value || undefined,
            type: fType.value || undefined,
            q: fQuery.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

/* ==========================================================================
   Editor
   ========================================================================== */

const showEditor = ref(false);
const editing = ref(null);
const optionText = ref(''); // newline- or pipe-separated option entry
const uploading = ref(false);

const form = useForm({
    exam_id: '',
    section: 'Reading',
    question_type: 'MCQ',
    part: '',
    order_index: 0,
    question_text: '',
    options: [],
    correct_answer: '',
    marks: 1,
    audio_url: '',
    audio_script: '',
    explanation: '',
    explanation_my: '',
    model_answer: '',
    model_answer_my: '',
    is_active: true,
});

const isMcq = computed(() => form.question_type === 'MCQ');
const isListening = computed(() => form.section === 'Listening');

function resetForm() {
    form.reset();
    form.clearErrors();
    form.options = [];
    form.correct_answer = '';
    form.marks = 1;
    form.is_active = true;
    optionText.value = '';
}

function openCreate() {
    editing.value = null;
    resetForm();
    form.exam_id = fExam.value || props.exams[0]?.id || '';
    if (fExam.value) {
        const exam = props.exams.find((e) => String(e.id) === String(fExam.value));
        if (exam) form.section = exam.section;
    }
    showEditor.value = true;
}

function openEdit(q) {
    editing.value = q;
    form.clearErrors();
    form.exam_id = q.exam_id;
    form.section = q.section;
    form.question_type = q.question_type;
    form.part = q.part ?? '';
    form.order_index = q.order_index ?? 0;
    form.question_text = q.question_text ?? '';
    form.options = Array.isArray(q.options) ? [...q.options] : [];
    form.correct_answer = q.correct_answer ?? '';
    form.marks = q.marks ?? 1;
    form.audio_url = q.audio_url ?? '';
    form.audio_script = q.audio_script ?? '';
    form.explanation = q.explanation ?? '';
    form.explanation_my = q.explanation_my ?? '';
    form.model_answer = q.model_answer ?? '';
    form.model_answer_my = q.model_answer_my ?? '';
    form.is_active = Boolean(q.is_active);
    optionText.value = form.options.join('\n');
    showEditor.value = true;
}

/** Parse the textarea into the options array. */
function syncOptions() {
    form.options = optionText.value
        .split(/\r?\n|\|/)
        .map((s) => s.trim())
        .filter(Boolean);
}

function save() {
    if (isMcq.value) syncOptions();

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showEditor.value = false;
            editing.value = null;
        },
    };

    if (editing.value) {
        form.put(`/admin/questions/${editing.value.id}`, options);
    } else {
        form.post('/admin/questions', options);
    }
}

/* ---- Audio upload ---- */

async function uploadAudio(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    uploading.value = true;
    try {
        const body = new FormData();
        body.append('audio', file);
        const res = await http.post('/admin/uploads/audio', body, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        form.audio_url = res.data.audio_url;
        toast.success(res.message || t('common.saved'));
    } catch (err) {
        toast.from(err);
    } finally {
        uploading.value = false;
        event.target.value = '';
    }
}

/* ==========================================================================
   AI drafting
   ========================================================================== */

const showAi = ref(false);
const aiBusy = ref(false);
const aiDrafts = ref([]);

const aiForm = ref({
    section: 'Reading',
    count: 5,
    question_type: 'MCQ',
    topic: 'General radiography',
    transcript: '',
});

async function generate() {
    aiBusy.value = true;
    aiDrafts.value = [];
    try {
        const res = await http.post('/admin/ai/generate-questions', { ...aiForm.value });
        aiDrafts.value = res.data.questions || [];
        toast.success(res.message || t('common.saved'));
    } catch (err) {
        toast.from(err);
    } finally {
        aiBusy.value = false;
    }
}

async function saveDrafts() {
    if (!aiDrafts.value.length || !form.exam_id) {
        toast.error(t('common.required'));
        return;
    }

    aiBusy.value = true;
    try {
        const res = await http.post('/admin/questions/bulk', {
            exam_id: Number(form.exam_id),
            questions: aiDrafts.value,
        });
        toast.success(res.message || t('common.saved'));
        aiDrafts.value = [];
        showAi.value = false;
        router.reload({ only: ['questions'] });
    } catch (err) {
        toast.from(err);
    } finally {
        aiBusy.value = false;
    }
}

/* ---- Delete ---- */
const pendingDelete = ref(null);

function confirmDelete() {
    if (!pendingDelete.value) return;
    router.delete(`/admin/questions/${pendingDelete.value.id}`, {
        preserveScroll: true,
        onFinish: () => (pendingDelete.value = null),
    });
}

const rows = computed(() => props.questions.data || []);

function optionLabel(q, index) {
    const options = Array.isArray(q.options) ? q.options : [];
    // correct_answer holds the 0-based index as a string.
    const i = Number(q.correct_answer);
    return options[i] ?? q.correct_answer ?? '—';
}
</script>

<template>
    <Head :title="t('admin.questions')" />

    <AdminLayout>
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ t('admin.questions') }}</h1>
                <p class="mt-1 text-sm text-slate-600">
                    {{ questions.total ?? rows.length }} {{ t('admin.totalQuestions') }}
                </p>
            </div>

            <div class="flex gap-2">
                <button type="button" class="btn-secondary btn-sm gap-1.5" @click="showAi = true">
                    <Sparkles :size="15" /> {{ t('admin.generateWithAi') }}
                </button>
                <button type="button" class="btn-primary btn-sm gap-1.5" @click="openCreate">
                    <Plus :size="15" /> {{ t('admin.newQuestion') }}
                </button>
            </div>
        </header>

        <!-- ---- Filters ---- -->
        <div class="card mt-5 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <label class="label" for="q-search">{{ t('common.search') }}</label>
                <div class="relative">
                    <Search :size="15" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input id="q-search" v-model="fQuery" type="search" class="input pl-9" @keyup.enter="applyFilters" />
                </div>
            </div>

            <div>
                <label class="label" for="q-exam">{{ t('nav.practice') }}</label>
                <select id="q-exam" v-model="fExam" class="input">
                    <option value="">{{ t('common.all') }}</option>
                    <option v-for="e in exams" :key="e.id" :value="String(e.id)">{{ e.title }}</option>
                </select>
            </div>

            <div>
                <label class="label" for="q-type">{{ t('admin.questions') }}</label>
                <select id="q-type" v-model="fType" class="input">
                    <option value="">{{ t('common.all') }}</option>
                    <option v-for="ty in TYPES" :key="ty" :value="ty">{{ ty }}</option>
                </select>
            </div>

            <div class="flex items-end">
                <button type="button" class="btn-secondary w-full" @click="applyFilters">{{ t('common.filter') }}</button>
            </div>
        </div>

        <!-- ---- Table ---- -->
        <section class="card mt-5 overflow-hidden">
            <div v-if="rows.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">#</th>
                            <th class="px-4 py-3 text-left font-semibold">{{ t('admin.questions') }}</th>
                            <th class="px-4 py-3 text-left font-semibold">{{ t('result.correctAnswer') }}</th>
                            <th class="px-4 py-3 text-right font-semibold">{{ t('common.marks') }}</th>
                            <th class="px-4 py-3 text-right font-semibold">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="q in rows" :key="q.id" class="transition hover:bg-slate-50/70">
                            <td class="px-4 py-3.5 align-top text-xs tabular-nums text-slate-400">{{ q.order_index }}</td>
                            <td class="max-w-md px-4 py-3.5 align-top">
                                <p class="line-clamp-2 text-slate-800">{{ q.question_text }}</p>
                                <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                    <SectionBadge :section="q.section" />
                                    <TypeBadge :type="q.question_type" />
                                    <span v-if="q.has_audio" class="badge-green">{{ t('exam.audio') }}</span>
                                    <span v-if="!q.is_active" class="badge-slate">{{ t('admin.inactive') }}</span>
                                </div>
                                <p class="mt-1 truncate text-[11px] text-slate-400">{{ q.exam_title }}</p>
                            </td>
                            <td class="max-w-xs px-4 py-3.5 align-top">
                                <p v-if="q.question_type === 'MCQ'" class="text-xs text-slate-600">
                                    <strong class="font-semibold text-teal-700">
                                        {{ LETTERS[Number(q.correct_answer)] ?? '?' }}
                                    </strong>
                                    — {{ optionLabel(q, q.correct_answer) }}
                                </p>
                                <p v-else-if="q.correct_answer" class="line-clamp-2 text-xs text-slate-600">{{ q.correct_answer }}</p>
                                <span v-else class="badge-purple">{{ t('result.aiMarked') }}</span>
                            </td>
                            <td class="px-4 py-3.5 text-right align-top tabular-nums text-slate-600">{{ q.marks }}</td>
                            <td class="px-4 py-3.5 align-top">
                                <div class="flex justify-end gap-1.5">
                                    <button type="button" class="btn-ghost btn-sm" :title="t('common.edit')" @click="openEdit(q)">
                                        <Pencil :size="14" />
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-ghost btn-sm text-red-600 hover:bg-red-50"
                                        :title="t('common.delete')"
                                        @click="pendingDelete = q"
                                    >
                                        <Trash2 :size="14" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="p-5">
                <EmptyState :icon="ListChecks" :title="t('common.noData')">
                    <template #action>
                        <button type="button" class="btn-primary btn-sm" @click="openCreate">
                            <Plus :size="15" /> {{ t('admin.newQuestion') }}
                        </button>
                    </template>
                </EmptyState>
            </div>

            <AppPagination
                v-if="questions.last_page > 1"
                :page="questions.current_page"
                :pages="questions.last_page"
                @change="(p) => router.get('/admin/questions', { ...filters, page: p }, { preserveScroll: true })"
            />
        </section>

        <!-- ================= Editor ================= -->
        <AppModal
            :open="showEditor"
            :title="editing ? t('common.edit') : t('admin.newQuestion')"
            size="xl"
            @close="showEditor = false"
        >
            <form id="question-form" class="space-y-4" @submit.prevent="save">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="q-exam-id" class="label">{{ t('nav.practice') }}</label>
                        <select id="q-exam-id" v-model="form.exam_id" class="input" :disabled="Boolean(editing)">
                            <option v-for="e in exams" :key="e.id" :value="e.id">{{ e.title }}</option>
                        </select>
                        <p v-if="form.errors.exam_id" class="field-error">{{ form.errors.exam_id }}</p>
                    </div>

                    <div>
                        <label for="q-section" class="label">{{ t('tutorials.category') }}</label>
                        <select id="q-section" v-model="form.section" class="input">
                            <option v-for="s in SECTIONS" :key="s" :value="s">{{ t(`sections.${s}`) }}</option>
                        </select>
                        <p v-if="form.errors.section" class="field-error">{{ form.errors.section }}</p>
                    </div>

                    <div>
                        <label for="q-type2" class="label">{{ t('admin.questions') }}</label>
                        <select id="q-type2" v-model="form.question_type" class="input">
                            <option v-for="ty in TYPES" :key="ty" :value="ty">{{ ty }}</option>
                        </select>
                        <p v-if="form.errors.question_type" class="field-error">{{ form.errors.question_type }}</p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="q-part" class="label">Part <span class="text-slate-400">({{ t('common.optional') }})</span></label>
                        <input id="q-part" v-model="form.part" type="text" class="input" placeholder="Part A" />
                    </div>
                    <div>
                        <label for="q-order" class="label">{{ t('tutorials.orderIndex') }}</label>
                        <input id="q-order" v-model.number="form.order_index" type="number" min="0" class="input" />
                    </div>
                    <div>
                        <label for="q-marks" class="label">{{ t('common.marks') }}</label>
                        <input id="q-marks" v-model.number="form.marks" type="number" min="1" class="input" />
                    </div>
                </div>

                <div>
                    <label for="q-text" class="label">{{ t('admin.newQuestion') }}</label>
                    <textarea
                        id="q-text"
                        v-model="form.question_text"
                        class="textarea min-h-[100px]"
                        :class="form.errors.question_text ? 'input-error' : ''"
                    />
                    <p v-if="form.errors.question_text" class="field-error">{{ form.errors.question_text }}</p>
                </div>

                <!-- ---- MCQ options ---- -->
                <div v-if="isMcq" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <label for="q-options" class="label">
                        {{ t('admin.questions') }} — {{ t('common.all') }} ({{ t('common.optional') }}: one per line, or A | B | C | D)
                    </label>
                    <textarea
                        id="q-options"
                        v-model="optionText"
                        class="textarea min-h-[110px] bg-white font-mono text-xs"
                        placeholder="Option A&#10;Option B&#10;Option C&#10;Option D"
                        @blur="syncOptions"
                    />
                    <p v-if="form.errors.options" class="field-error">{{ form.errors.options }}</p>

                    <div v-if="form.options.length" class="mt-4">
                        <p class="label">{{ t('result.correctAnswer') }}</p>
                        <div class="space-y-2">
                            <label
                                v-for="(opt, i) in form.options"
                                :key="i"
                                :class="[
                                    'flex cursor-pointer items-start gap-3 rounded-lg border bg-white p-3 text-sm transition',
                                    String(form.correct_answer) === String(i)
                                        ? 'border-teal-500 ring-1 ring-teal-500'
                                        : 'border-slate-300 hover:border-primary-400',
                                ]"
                            >
                                <input
                                    v-model="form.correct_answer"
                                    type="radio"
                                    class="mt-0.5 h-4 w-4 border-slate-300 text-teal-600"
                                    :value="String(i)"
                                />
                                <span class="flex-1 text-slate-800">
                                    <strong class="mr-1.5 font-bold text-slate-500">{{ LETTERS[i] }}</strong>{{ opt }}
                                </span>
                            </label>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">
                            Stored as the 0-based option index — option A saves as <code class="rounded bg-slate-200 px-1">"0"</code>.
                        </p>
                    </div>
                    <p v-if="form.errors.correct_answer" class="field-error">{{ form.errors.correct_answer }}</p>
                </div>

                <!-- ---- Short answer ---- -->
                <div v-else-if="form.question_type === 'ShortAnswer'">
                    <label for="q-correct" class="label">{{ t('result.correctAnswer') }}</label>
                    <input
                        id="q-correct"
                        v-model="form.correct_answer"
                        type="text"
                        :class="['input', form.errors.correct_answer ? 'input-error' : '']"
                    />
                    <p v-if="form.errors.correct_answer" class="field-error">{{ form.errors.correct_answer }}</p>
                </div>

                <!-- ---- Listening audio ---- -->
                <div v-if="isListening" class="rounded-xl border border-primary-200 bg-primary-50/50 p-4">
                    <p class="label">{{ t('exam.audio') }}</p>

                    <input
                        :value="form.audio_url"
                        type="text"
                        class="input bg-white font-mono text-xs"
                        placeholder="listening/q123.mp3"
                        @input="form.audio_url = $event.target.value"
                    />

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <label class="btn-secondary btn-sm cursor-pointer gap-1.5">
                            <Upload :size="14" />
                            {{ uploading ? t('common.saving') : t('admin.uploadAudio') }}
                            <input type="file" accept="audio/*" class="hidden" :disabled="uploading" @change="uploadAudio" />
                        </label>
                    </div>

                    <!-- AudioPlayer preflights with HEAD; never use a bare <audio>. -->
                    <div v-if="form.audio_url" class="mt-3">
                        <AudioPlayer :src="resolveAssetUrl(form.audio_url)" />
                    </div>

                    <div class="mt-3">
                        <label for="q-script" class="label">{{ t('tutorials.contentEnglish') }} — script</label>
                        <textarea id="q-script" v-model="form.audio_script" class="textarea min-h-[80px] bg-white" />
                    </div>
                </div>

                <!-- ---- Bilingual explanation ---- -->
                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label for="q-exp" class="label">{{ t('result.explanation') }} (English)</label>
                        <textarea id="q-exp" v-model="form.explanation" class="textarea min-h-[100px]" />
                    </div>
                    <div>
                        <label for="q-exp-my" class="label">{{ t('result.explanation') }} ({{ t('result.langMy') }})</label>
                        <textarea id="q-exp-my" v-model="form.explanation_my" class="textarea min-h-[100px] font-myanmar" />
                    </div>
                </div>

                <!-- ---- Bilingual model answer ---- -->
                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label for="q-model" class="label">{{ t('result.modelAnswer') }} (English)</label>
                        <textarea id="q-model" v-model="form.model_answer" class="textarea min-h-[90px]" />
                    </div>
                    <div>
                        <label for="q-model-my" class="label">{{ t('result.modelAnswer') }} ({{ t('result.langMy') }})</label>
                        <textarea id="q-model-my" v-model="form.model_answer_my" class="textarea min-h-[90px] font-myanmar" />
                    </div>
                </div>

                <label class="flex items-center gap-2.5">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" />
                    <span class="text-sm text-slate-700">{{ t('admin.active') }}</span>
                </label>
            </form>

            <template #footer>
                <button type="button" class="btn-secondary" @click="showEditor = false">{{ t('common.cancel') }}</button>
                <button type="submit" form="question-form" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('common.save') }}
                </button>
            </template>
        </AppModal>

        <!-- ================= AI drafting ================= -->
        <AppModal :open="showAi" :title="t('admin.generateWithAi')" size="lg" @close="showAi = false">
            <div class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="ai-section" class="label">{{ t('tutorials.category') }}</label>
                        <select id="ai-section" v-model="aiForm.section" class="input">
                            <option v-for="s in SECTIONS" :key="s" :value="s">{{ t(`sections.${s}`) }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="ai-type" class="label">{{ t('admin.questions') }}</label>
                        <select id="ai-type" v-model="aiForm.question_type" class="input">
                            <option v-for="ty in TYPES" :key="ty" :value="ty">{{ ty }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label for="ai-topic" class="label">{{ t('tutorials.aiTopic') }}</label>
                        <input id="ai-topic" v-model="aiForm.topic" type="text" class="input" />
                    </div>
                    <div>
                        <label for="ai-count" class="label">{{ t('common.questions') }}</label>
                        <input id="ai-count" v-model.number="aiForm.count" type="number" min="1" max="20" class="input" />
                    </div>
                </div>

                <div>
                    <label for="ai-exam" class="label">{{ t('nav.practice') }}</label>
                    <select id="ai-exam" v-model="form.exam_id" class="input">
                        <option v-for="e in exams" :key="e.id" :value="e.id">{{ e.title }}</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">{{ t('tutorials.aiDraftReady') }}</p>
                </div>

                <button type="button" class="btn-primary w-full" :disabled="aiBusy" @click="generate">
                    <Bot :size="16" /> {{ aiBusy ? t('tutorials.aiGenerating') : t('admin.generateWithAi') }}
                </button>

                <!-- Drafts -->
                <div v-if="aiDrafts.length" class="rounded-xl border border-slate-200">
                    <p class="border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{ aiDrafts.length }} {{ t('common.questions') }}
                    </p>
                    <ol class="max-h-[320px] divide-y divide-slate-100 overflow-y-auto">
                        <li v-for="(d, i) in aiDrafts" :key="i" class="p-4">
                            <p class="text-sm font-medium text-slate-800">{{ i + 1 }}. {{ d.question_text }}</p>
                            <ul v-if="Array.isArray(d.options)" class="mt-2 space-y-1">
                                <li v-for="(opt, j) in d.options" :key="j" class="text-xs text-slate-600">
                                    <strong class="mr-1 text-slate-400">{{ LETTERS[j] }}</strong>{{ opt }}
                                </li>
                            </ul>
                            <p v-if="d.correct_answer != null" class="mt-2 text-xs text-teal-700">
                                {{ t('result.correctAnswer') }}:
                                <strong>{{ LETTERS[Number(d.correct_answer)] ?? d.correct_answer }}</strong>
                            </p>
                        </li>
                    </ol>
                </div>
            </div>

            <template #footer>
                <button type="button" class="btn-secondary" @click="showAi = false">{{ t('common.close') }}</button>
                <button type="button" class="btn-primary" :disabled="aiBusy || !aiDrafts.length" @click="saveDrafts">
                    {{ t('common.save') }}
                </button>
            </template>
        </AppModal>

        <!-- ================= Delete ================= -->
        <ConfirmDialog
            :open="Boolean(pendingDelete)"
            :title="t('common.delete')"
            :message="t('tutorials.deleteConfirm')"
            danger
            @cancel="pendingDelete = null"
            @confirm="confirmDelete"
        />
    </AdminLayout>
</template>
