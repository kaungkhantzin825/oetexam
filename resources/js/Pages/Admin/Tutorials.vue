<!--
  Pages/Admin/Tutorials.vue
  ---------------------------------------------------------------------------
  Tutorial management: create/edit a bilingual lesson, toggle publish, delete,
  draft one with AI, and machine-translate the English fields into Myanmar.

  The AI endpoints return DRAFTS — nothing is written until the admin saves.
-->
<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Bot,
    Eye,
    EyeOff,
    GraduationCap,
    Languages,
    Layers,
    Pencil,
    Plus,
    Sparkles,
    Trash2,
} from 'lucide-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { AppModal, ConfirmDialog, EmptyState } from '@/components/ui';
import LessonCategoryIcon from '@/components/LessonCategoryIcon.vue';
import { useTranslation } from '@/lib/i18n';
import { http } from '@/lib/http';
import { toast } from '@/lib/toast';
import { LESSON_CATEGORIES, LESSON_TRACKS, lessonTrackMeta } from '@/lib/lessonCategories';

const props = defineProps({
    lessons: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
});

const { t } = useTranslation();

const LEVELS = ['beginner', 'intermediate', 'advanced'];

/* ==========================================================================
   Editor
   ========================================================================== */

const showEditor = ref(false);
const editing = ref(null);
const translating = ref(false);

const form = useForm({
    title: '',
    title_my: '',
    slug: '',
    summary: '',
    summary_my: '',
    content: '',
    content_my: '',
    category: 'ExamStrategy',
    track: 'Radiography',
    level: 'beginner',
    duration_minutes: 10,
    order_index: 0,
    is_published: false,
    key_points: [],
    key_points_my: [],
});

/** Key takeaways are edited as one-per-line text and stored as arrays. */
const keyPointsText = ref('');
const keyPointsMyText = ref('');

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.category = 'ExamStrategy';
    // Inherit the filter so creating while browsing "General medical" does not
    // silently drop the new lesson into the radiography catalogue.
    form.track = trackFilter.value === 'All' ? 'Radiography' : trackFilter.value;
    form.level = 'beginner';
    form.duration_minutes = 10;
    form.order_index = props.lessons.length;
    form.is_published = false;
    keyPointsText.value = '';
    keyPointsMyText.value = '';
    showEditor.value = true;
}

function openEdit(lesson) {
    editing.value = lesson;
    form.clearErrors();
    form.title = lesson.title ?? '';
    form.title_my = lesson.title_my ?? '';
    form.slug = lesson.slug ?? '';
    form.summary = lesson.summary ?? '';
    form.summary_my = lesson.summary_my ?? '';
    form.content = lesson.content ?? '';
    form.content_my = lesson.content_my ?? '';
    form.category = lesson.category ?? 'ExamStrategy';
    form.track = lesson.track ?? 'Radiography';
    form.level = lesson.level ?? 'beginner';
    form.duration_minutes = lesson.duration_minutes ?? 10;
    form.order_index = lesson.order_index ?? 0;
    form.is_published = Boolean(lesson.is_published);
    form.key_points = Array.isArray(lesson.key_points) ? lesson.key_points : [];
    form.key_points_my = Array.isArray(lesson.key_points_my) ? lesson.key_points_my : [];
    keyPointsText.value = form.key_points.join('\n');
    keyPointsMyText.value = form.key_points_my.join('\n');
    showEditor.value = true;
}

function syncKeyPoints() {
    const split = (v) => v.split('\n').map((s) => s.trim()).filter(Boolean);
    form.key_points = split(keyPointsText.value);
    form.key_points_my = split(keyPointsMyText.value);
}

function save() {
    syncKeyPoints();

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showEditor.value = false;
            editing.value = null;
        },
    };

    if (editing.value) {
        form.put(`/admin/tutorials/${editing.value.id}`, options);
    } else {
        form.post('/admin/tutorials', options);
    }
}

/* ==========================================================================
   AI: draft a lesson
   ========================================================================== */

const showAi = ref(false);
const aiBusy = ref(false);

const aiForm = ref({
    topic: '',
    category: 'ExamStrategy',
    track: 'Radiography',
    level: 'beginner',
    notes: '',
});

function openAi() {
    // Same inheritance rule as openCreate(): the filter is the intent.
    aiForm.value.track = trackFilter.value === 'All' ? 'Radiography' : trackFilter.value;
    showAi.value = true;
}

async function generateLesson() {
    if (!aiForm.value.topic.trim()) {
        toast.error(t('tutorials.fillTitleFirst'));
        return;
    }

    aiBusy.value = true;
    try {
        const res = await http.post('/admin/ai/generate-lesson', { ...aiForm.value });
        const lesson = res.data.lesson || {};

        // Load the draft into the editor rather than saving it — the admin
        // reviews both languages first.
        editing.value = null;
        form.title = lesson.title ?? '';
        form.title_my = lesson.title_my ?? '';
        form.summary = lesson.summary ?? '';
        form.summary_my = lesson.summary_my ?? '';
        form.content = lesson.content ?? '';
        form.content_my = lesson.content_my ?? '';
        form.category = aiForm.value.category;
        form.track = lesson.track ?? aiForm.value.track;
        form.level = aiForm.value.level;
        form.key_points = Array.isArray(lesson.key_points) ? lesson.key_points : [];
        form.key_points_my = Array.isArray(lesson.key_points_my) ? lesson.key_points_my : [];
        keyPointsText.value = form.key_points.join('\n');
        keyPointsMyText.value = form.key_points_my.join('\n');

        showAi.value = false;
        showEditor.value = true;
        toast.success(t('tutorials.aiDraftReady'));
    } catch (err) {
        toast.from(err);
    } finally {
        aiBusy.value = false;
    }
}

/* ==========================================================================
   AI: translate the English fields into Myanmar
   ========================================================================== */

async function translateToMyanmar() {
    const payload = {
        title_my: form.title,
        summary_my: form.summary,
        content_my: form.content,
    };

    if (!form.title.trim() || !form.content.trim()) {
        toast.error(t('tutorials.translateNeedsEnglish'));
        return;
    }

    translating.value = true;
    try {
        // The track selects the translator persona — a General lesson must not
        // come back with imaging vocabulary.
        const res = await http.post('/admin/ai/translate', { payload, track: form.track });
        const out = res.data.translations || {};

        // Only fill blanks unless the admin explicitly asked to overwrite.
        const overwrite = !form.title_my.trim() && !form.content_my.trim();
        if (overwrite) {
            form.title_my = out.title_my ?? form.title_my;
            form.content_my = out.content_my ?? form.content_my;
        } else {
            form.title_my = out.title_my ?? form.title_my;
            form.summary_my = out.summary_my || form.summary_my;
            form.content_my = out.content_my ?? form.content_my;
        }

        toast.success(t('tutorials.translateDone'));
    } catch (err) {
        toast.from(err);
    } finally {
        translating.value = false;
    }
}

/* ==========================================================================
   Row actions
   ========================================================================== */

const pendingDelete = ref(null);

function togglePublish(lesson) {
    router.patch(`/admin/tutorials/${lesson.id}/publish`, { is_published: !lesson.is_published }, { preserveScroll: true });
}

function confirmDelete() {
    if (!pendingDelete.value) return;
    router.delete(`/admin/tutorials/${pendingDelete.value.id}`, {
        preserveScroll: true,
        onFinish: () => (pendingDelete.value = null),
    });
}

const translatedCount = computed(() => props.lessons.filter((l) => l.translation_complete).length);

/* ==========================================================================
   Track filter
   ========================================================================== */

const trackFilter = ref('All');

/** Counts over the WHOLE catalogue, so a filter never zeroes the other tab. */
const trackCounts = computed(() => {
    const counts = { All: props.lessons.length };
    for (const track of LESSON_TRACKS) counts[track] = 0;
    for (const lesson of props.lessons) {
        if (counts[lesson.track] !== undefined) counts[lesson.track] += 1;
    }
    return counts;
});

const visibleLessons = computed(() =>
    trackFilter.value === 'All' ? props.lessons : props.lessons.filter((l) => l.track === trackFilter.value),
);

function trackLabel(track) {
    if (track === 'All') return t('tutorials.allTracks');
    const key = `tutorials.track${track}`;
    const translated = t(key);
    return translated === key ? track : translated;
}

function categoryLabel(c) {
    const key = `lessonCategory.${c}`;
    const translated = t(key);
    return translated === key ? c : translated;
}
</script>

<template>
    <Head :title="t('admin.tutorials')" />

    <AdminLayout>
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ t('tutorials.manage') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ t('tutorials.manageSubtitle') }}</p>
                <p class="mt-1 text-xs text-slate-500">
                    {{ total }} {{ t('tutorials.lessons') }} ·
                    {{ translatedCount }} {{ t('tutorials.translationComplete') }}
                </p>
            </div>

            <div class="flex gap-2">
                <button type="button" class="btn-secondary btn-sm gap-1.5" @click="openAi">
                    <Sparkles :size="15" /> {{ t('tutorials.generateWithAi') }}
                </button>
                <button type="button" class="btn-primary btn-sm gap-1.5" @click="openCreate">
                    <Plus :size="15" /> {{ t('tutorials.newLesson') }}
                </button>
            </div>
        </header>

        <!-- ---- Track filter ---- -->
        <div class="mt-5 flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                <Layers :size="13" /> {{ t('tutorials.track') }}
            </span>
            <button
                v-for="option in ['All', ...LESSON_TRACKS]"
                :key="option"
                type="button"
                :class="[
                    'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition',
                    trackFilter === option
                        ? 'border-primary-500 bg-primary-600 text-white shadow-sm'
                        : 'border-slate-300 bg-white text-slate-600 hover:border-primary-300 hover:bg-primary-50/40',
                ]"
                @click="trackFilter = option"
            >
                <component
                    v-if="option !== 'All'"
                    :is="lessonTrackMeta(option).icon"
                    :size="13"
                />
                {{ trackLabel(option) }}
                <span :class="['rounded px-1.5 text-[10px] font-bold', trackFilter === option ? 'bg-white/20' : 'bg-slate-100 text-slate-500']">
                    {{ trackCounts[option] || 0 }}
                </span>
            </button>
        </div>

        <!-- ---- Table ---- -->
        <section class="card mt-4 overflow-hidden">
            <div v-if="visibleLessons.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-semibold">#</th>
                            <th class="px-5 py-3 text-left font-semibold">{{ t('tutorials.lessonTitle') }}</th>
                            <th class="px-5 py-3 text-left font-semibold">{{ t('result.langMy') }}</th>
                            <th class="px-5 py-3 text-left font-semibold">{{ t('common.status') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="lesson in visibleLessons" :key="lesson.id" class="transition hover:bg-slate-50/70">
                            <td class="px-5 py-3.5 align-top text-xs tabular-nums text-slate-400">{{ lesson.order_index }}</td>
                            <td class="max-w-lg px-5 py-3.5 align-top">
                                <div class="flex items-start gap-3">
                                    <LessonCategoryIcon :category="lesson.category" :size="17" />
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-800">{{ lesson.title }}</p>
                                        <p class="mt-0.5 line-clamp-2 text-xs text-slate-500">{{ lesson.summary || '—' }}</p>
                                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                            <span class="badge-slate">{{ categoryLabel(lesson.category) }}</span>
                                            <span
                                                class="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-500"
                                            >
                                                <component :is="lessonTrackMeta(lesson.track).icon" :size="10" />
                                                {{ trackLabel(lesson.track) }}
                                            </span>
                                            <span class="badge-slate">{{ lesson.level }}</span>
                                            <span class="badge-slate">{{ lesson.duration_minutes }} {{ t('tutorials.min') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 align-top">
                                <span :class="lesson.translation_complete ? 'badge-green' : 'badge-amber'">
                                    {{ lesson.translation_complete ? t('tutorials.translationComplete') : t('tutorials.translationMissing') }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 align-top">
                                <button
                                    type="button"
                                    :class="lesson.is_published ? 'badge-green' : 'badge-slate'"
                                    @click="togglePublish(lesson)"
                                >
                                    <Eye v-if="lesson.is_published" :size="12" />
                                    <EyeOff v-else :size="12" />
                                    {{ lesson.is_published ? t('admin.published') : t('admin.draft') }}
                                </button>
                            </td>
                            <td class="px-5 py-3.5 align-top">
                                <div class="flex justify-end gap-1.5">
                                    <Link :href="`/tutorials/${lesson.slug}`" class="btn-ghost btn-sm" :title="t('tutorials.preview')">
                                        <GraduationCap :size="14" />
                                    </Link>
                                    <button type="button" class="btn-ghost btn-sm" :title="t('common.edit')" @click="openEdit(lesson)">
                                        <Pencil :size="14" />
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-ghost btn-sm text-red-600 hover:bg-red-50"
                                        :title="t('common.delete')"
                                        @click="pendingDelete = lesson"
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
                <EmptyState :icon="GraduationCap" :title="t('tutorials.noLessons')">
                    <template #action>
                        <button type="button" class="btn-primary btn-sm" @click="openCreate">
                            <Plus :size="15" /> {{ t('tutorials.newLesson') }}
                        </button>
                    </template>
                </EmptyState>
            </div>
        </section>

        <!-- ================= Editor ================= -->
        <AppModal
            :open="showEditor"
            :title="editing ? t('tutorials.editLesson') : t('tutorials.newLesson')"
            size="xl"
            @close="showEditor = false"
        >
            <form id="lesson-form" class="space-y-4" @submit.prevent="save">
                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label for="l-title" class="label">{{ t('tutorials.lessonTitle') }}</label>
                        <input id="l-title" v-model="form.title" type="text" :class="['input', form.errors.title ? 'input-error' : '']" />
                        <p v-if="form.errors.title" class="field-error">{{ form.errors.title }}</p>
                    </div>
                    <div>
                        <label for="l-title-my" class="label">{{ t('tutorials.lessonTitleMy') }}</label>
                        <input id="l-title-my" v-model="form.title_my" type="text" class="input font-myanmar" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-4">
                    <div class="sm:col-span-2">
                        <label for="l-slug" class="label">{{ t('tutorials.slugLabel') }}</label>
                        <input id="l-slug" v-model="form.slug" type="text" class="input font-mono text-xs" :placeholder="t('common.optional')" />
                        <p v-if="form.errors.slug" class="field-error">{{ form.errors.slug }}</p>
                    </div>
                    <div>
                        <label for="l-category" class="label">{{ t('tutorials.category') }}</label>
                        <select id="l-category" v-model="form.category" class="input">
                            <option v-for="c in LESSON_CATEGORIES" :key="c" :value="c">{{ categoryLabel(c) }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="l-level" class="label">{{ t('tutorials.levelLabel') }}</label>
                        <select id="l-level" v-model="form.level" class="input">
                            <option v-for="lv in LEVELS" :key="lv" :value="lv">{{ t(`tutorials.${lv}`) }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="l-track" class="label">{{ t('tutorials.track') }}</label>
                        <select id="l-track" v-model="form.track" class="input">
                            <option v-for="tk in LESSON_TRACKS" :key="tk" :value="tk">{{ trackLabel(tk) }}</option>
                        </select>
                        <p v-if="form.errors.track" class="field-error">{{ form.errors.track }}</p>
                    </div>
                    <div>
                        <label for="l-duration" class="label">{{ t('tutorials.durationMinutes') }}</label>
                        <input id="l-duration" v-model.number="form.duration_minutes" type="number" min="1" max="600" class="input" />
                    </div>
                    <div>
                        <label for="l-order" class="label">{{ t('tutorials.orderIndex') }}</label>
                        <input id="l-order" v-model.number="form.order_index" type="number" min="0" class="input" />
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label for="l-summary" class="label">{{ t('tutorials.summary') }}</label>
                        <textarea id="l-summary" v-model="form.summary" class="textarea min-h-[70px]" />
                    </div>
                    <div>
                        <label for="l-summary-my" class="label">{{ t('tutorials.summaryMy') }}</label>
                        <textarea id="l-summary-my" v-model="form.summary_my" class="textarea min-h-[70px] font-myanmar" />
                    </div>
                </div>

                <!-- Translate button -->
                <div class="flex flex-wrap items-center gap-3 rounded-xl border border-primary-200 bg-primary-50/50 p-4">
                    <button type="button" class="btn-secondary btn-sm gap-1.5" :disabled="translating" @click="translateToMyanmar">
                        <Languages :size="14" />
                        {{ translating ? t('tutorials.translating') : t('tutorials.translateToMyanmar') }}
                    </button>
                    <p class="text-xs text-slate-600">{{ t('tutorials.translateNeedsEnglish') }}</p>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label for="l-content" class="label">{{ t('tutorials.contentEnglish') }}</label>
                        <textarea
                            id="l-content"
                            v-model="form.content"
                            class="textarea min-h-[320px] font-mono text-xs"
                            :class="form.errors.content ? 'input-error' : ''"
                        />
                        <p v-if="form.errors.content" class="field-error">{{ form.errors.content }}</p>
                    </div>
                    <div>
                        <label for="l-content-my" class="label">{{ t('tutorials.contentMyanmar') }}</label>
                        <textarea id="l-content-my" v-model="form.content_my" class="textarea min-h-[320px] font-myanmar text-xs" />
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label for="l-keypoints" class="label">{{ t('tutorials.keyPointsLabel') }}</label>
                        <textarea id="l-keypoints" v-model="keyPointsText" class="textarea min-h-[90px] text-xs" />
                    </div>
                    <div>
                        <label for="l-keypoints-my" class="label">{{ t('tutorials.keyPointsMyLabel') }}</label>
                        <textarea id="l-keypoints-my" v-model="keyPointsMyText" class="textarea min-h-[90px] font-myanmar text-xs" />
                    </div>
                </div>

                <label class="flex items-center gap-2.5">
                    <input v-model="form.is_published" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" />
                    <span class="text-sm text-slate-700">{{ t('tutorials.publishedLabel') }}</span>
                </label>
            </form>

            <template #footer>
                <button type="button" class="btn-secondary" @click="showEditor = false">{{ t('common.cancel') }}</button>
                <button type="submit" form="lesson-form" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('tutorials.saveLesson') }}
                </button>
            </template>
        </AppModal>

        <!-- ================= AI draft ================= -->
        <AppModal :open="showAi" :title="t('tutorials.generateWithAi')" @close="showAi = false">
            <div class="space-y-4">
                <div>
                    <label for="ai-topic" class="label">{{ t('tutorials.aiTopic') }}</label>
                    <input
                        id="ai-topic"
                        v-model="aiForm.topic"
                        type="text"
                        class="input"
                        :placeholder="t('tutorials.aiTopicPlaceholder')"
                    />
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="ai-category" class="label">{{ t('tutorials.category') }}</label>
                        <select id="ai-category" v-model="aiForm.category" class="input">
                            <option v-for="c in LESSON_CATEGORIES" :key="c" :value="c">{{ categoryLabel(c) }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="ai-track" class="label">{{ t('tutorials.track') }}</label>
                        <select id="ai-track" v-model="aiForm.track" class="input">
                            <option v-for="tk in LESSON_TRACKS" :key="tk" :value="tk">{{ trackLabel(tk) }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="ai-level" class="label">{{ t('tutorials.levelLabel') }}</label>
                        <select id="ai-level" v-model="aiForm.level" class="input">
                            <option v-for="lv in LEVELS" :key="lv" :value="lv">{{ t(`tutorials.${lv}`) }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="ai-notes" class="label">{{ t('tutorials.aiNotes') }}</label>
                    <textarea
                        id="ai-notes"
                        v-model="aiForm.notes"
                        class="textarea min-h-[80px]"
                        :placeholder="t('tutorials.aiNotesPlaceholder')"
                    />
                </div>
            </div>

            <template #footer>
                <button type="button" class="btn-secondary" @click="showAi = false">{{ t('common.cancel') }}</button>
                <button type="button" class="btn-primary" :disabled="aiBusy" @click="generateLesson">
                    <Bot :size="16" /> {{ aiBusy ? t('tutorials.aiGenerating') : t('tutorials.generateWithAi') }}
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
