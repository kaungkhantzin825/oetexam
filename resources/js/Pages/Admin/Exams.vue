<!--
  Pages/Admin/Exams.vue
  ---------------------------------------------------------------------------
  Exam CRUD. The list is server-paginated and filterable by sub-test and title;
  create/edit happen in a modal; publish toggling is a one-click PATCH.
-->
<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, FileText, ListChecks, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { AppModal, AppPagination, ConfirmDialog, EmptyState, SectionBadge } from '@/components/ui';
import { useTranslation } from '@/lib/i18n';
import { formatDate } from '@/lib/format';

const props = defineProps({
    exams: { type: Object, default: () => ({ data: [], current_page: 1, last_page: 1 }) },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useTranslation();

const SECTIONS = ['Listening', 'Reading', 'Writing', 'Speaking', 'Full'];
const DIFFICULTIES = ['easy', 'medium', 'hard'];

const search = ref(props.filters.q || '');
const sectionFilter = ref(props.filters.section || '');

/* ---- Create / edit ---- */
const showEditor = ref(false);
const editing = ref(null);

const form = useForm({
    title: '',
    description: '',
    duration_minutes: 60,
    section: 'Reading',
    difficulty: 'medium',
    is_published: false,
});

/* ---- Delete ---- */
const pendingDelete = ref(null);

function applyFilters() {
    router.get(
        '/admin/exams',
        {
            q: search.value || undefined,
            section: sectionFilter.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.section = 'Reading';
    form.duration_minutes = 60;
    form.difficulty = 'medium';
    form.is_published = false;
    showEditor.value = true;
}

function openEdit(exam) {
    editing.value = exam;
    form.clearErrors();
    form.title = exam.title;
    form.description = exam.description ?? '';
    form.duration_minutes = exam.duration_minutes ?? 60;
    form.section = exam.section;
    form.difficulty = exam.difficulty ?? 'medium';
    form.is_published = Boolean(exam.is_published);
    showEditor.value = true;
}

function save() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showEditor.value = false;
            editing.value = null;
        },
    };

    if (editing.value) {
        form.put(`/admin/exams/${editing.value.id}`, options);
    } else {
        form.post('/admin/exams', options);
    }
}

function togglePublish(exam) {
    router.patch(`/admin/exams/${exam.id}/publish`, { is_published: !exam.is_published }, { preserveScroll: true });
}

function confirmDelete() {
    if (!pendingDelete.value) return;
    router.delete(`/admin/exams/${pendingDelete.value.id}`, {
        preserveScroll: true,
        onFinish: () => (pendingDelete.value = null),
    });
}

const rows = computed(() => props.exams.data || []);
</script>

<template>
    <Head :title="t('admin.exams')" />

    <AdminLayout>
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ t('admin.exams') }}</h1>
                <p class="mt-1 text-sm text-slate-600">
                    {{ exams.total ?? rows.length }} {{ t('admin.totalExams') }}
                </p>
            </div>

            <button type="button" class="btn-primary btn-sm gap-1.5" @click="openCreate">
                <Plus :size="15" /> {{ t('admin.newExam') }}
            </button>
        </header>

        <!-- ---- Filters ---- -->
        <div class="card mt-5 flex flex-wrap items-end gap-3 p-4">
            <div class="min-w-[200px] flex-1">
                <label class="label" for="exam-search">{{ t('common.search') }}</label>
                <div class="relative">
                    <Search :size="15" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input
                        id="exam-search"
                        v-model="search"
                        type="search"
                        class="input pl-9"
                        :placeholder="t('common.search')"
                        @keyup.enter="applyFilters"
                    />
                </div>
            </div>

            <div>
                <label class="label" for="exam-section">{{ t('common.filter') }}</label>
                <select id="exam-section" v-model="sectionFilter" class="input">
                    <option value="">{{ t('common.all') }}</option>
                    <option v-for="s in SECTIONS" :key="s" :value="s">{{ t(`sections.${s}`) }}</option>
                </select>
            </div>

            <button type="button" class="btn-secondary" @click="applyFilters">{{ t('common.filter') }}</button>
        </div>

        <!-- ---- Table ---- -->
        <section class="card mt-5 overflow-hidden">
            <div v-if="rows.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-semibold">{{ t('nav.practice') }}</th>
                            <th class="px-5 py-3 text-left font-semibold">{{ t('common.status') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ t('admin.totalQuestions') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="exam in rows" :key="exam.id" class="transition hover:bg-slate-50/70">
                            <td class="px-5 py-3.5">
                                <p class="font-medium text-slate-800">{{ exam.title }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                    <SectionBadge :section="exam.section" />
                                    <span class="text-[11px] text-slate-400">
                                        {{ exam.duration_minutes }} {{ t('common.minutes') }} ·
                                        {{ formatDate(exam.created_at) }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <button
                                    type="button"
                                    :class="exam.is_published ? 'badge-green' : 'badge-slate'"
                                    :title="exam.is_published ? t('admin.unpublish') : t('admin.publish')"
                                    @click="togglePublish(exam)"
                                >
                                    <Eye v-if="exam.is_published" :size="12" />
                                    <EyeOff v-else :size="12" />
                                    {{ exam.is_published ? t('admin.published') : t('admin.draft') }}
                                </button>
                            </td>
                            <td class="px-5 py-3.5 text-right tabular-nums text-slate-600">{{ exam.question_count }}</td>
                            <td class="px-5 py-3.5">
                                <div class="flex justify-end gap-1.5">
                                    <Link
                                        :href="`/admin/questions?exam_id=${exam.id}`"
                                        class="btn-ghost btn-sm"
                                        :title="t('admin.questions')"
                                    >
                                        <ListChecks :size="14" />
                                    </Link>
                                    <button type="button" class="btn-ghost btn-sm" :title="t('common.edit')" @click="openEdit(exam)">
                                        <Pencil :size="14" />
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-ghost btn-sm text-red-600 hover:bg-red-50"
                                        :title="t('common.delete')"
                                        @click="pendingDelete = exam"
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
                <EmptyState :icon="FileText" :title="t('common.noData')">
                    <template #action>
                        <button type="button" class="btn-primary btn-sm" @click="openCreate">
                            <Plus :size="15" /> {{ t('admin.newExam') }}
                        </button>
                    </template>
                </EmptyState>
            </div>

            <AppPagination
                v-if="exams.last_page > 1"
                :page="exams.current_page"
                :pages="exams.last_page"
                @change="(p) => router.get('/admin/exams', { ...filters, page: p }, { preserveScroll: true })"
            />
        </section>

        <!-- ---- Editor ---- -->
        <AppModal
            :open="showEditor"
            :title="editing ? t('common.edit') : t('admin.newExam')"
            size="lg"
            @close="showEditor = false"
        >
            <form id="exam-form" class="space-y-4" @submit.prevent="save">
                <div>
                    <label for="title" class="label">{{ t('tutorials.lessonTitle') }}</label>
                    <input id="title" v-model="form.title" type="text" :class="['input', form.errors.title ? 'input-error' : '']" />
                    <p v-if="form.errors.title" class="field-error">{{ form.errors.title }}</p>
                </div>

                <div>
                    <label for="description" class="label">
                        {{ t('tutorials.summary') }} <span class="text-slate-400">({{ t('common.optional') }})</span>
                    </label>
                    <textarea id="description" v-model="form.description" class="textarea min-h-[90px]" />
                    <p v-if="form.errors.description" class="field-error">{{ form.errors.description }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="section" class="label">{{ t('tutorials.category') }}</label>
                        <select id="section" v-model="form.section" class="input">
                            <option v-for="s in SECTIONS" :key="s" :value="s">{{ t(`sections.${s}`) }}</option>
                        </select>
                        <p v-if="form.errors.section" class="field-error">{{ form.errors.section }}</p>
                    </div>

                    <div>
                        <label for="difficulty" class="label">{{ t('tutorials.levelLabel') }}</label>
                        <select id="difficulty" v-model="form.difficulty" class="input">
                            <option v-for="d in DIFFICULTIES" :key="d" :value="d">{{ d }}</option>
                        </select>
                    </div>

                    <div>
                        <label for="duration" class="label">{{ t('tutorials.durationMinutes') }}</label>
                        <input id="duration" v-model.number="form.duration_minutes" type="number" min="1" max="600" class="input" />
                        <p v-if="form.errors.duration_minutes" class="field-error">{{ form.errors.duration_minutes }}</p>
                    </div>
                </div>

                <label class="flex items-center gap-2.5">
                    <input v-model="form.is_published" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-primary-600" />
                    <span class="text-sm text-slate-700">{{ t('admin.publish') }}</span>
                </label>
            </form>

            <template #footer>
                <button type="button" class="btn-secondary" @click="showEditor = false">{{ t('common.cancel') }}</button>
                <button type="submit" form="exam-form" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('common.save') }}
                </button>
            </template>
        </AppModal>

        <!-- ---- Delete confirm ---- -->
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
