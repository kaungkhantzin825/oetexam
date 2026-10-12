<!--
  Pages/Admin/Students.vue
  ---------------------------------------------------------------------------
  Student accounts: create, search, activate/deactivate, delete.
  An admin can never demote or deactivate themselves — the server enforces it,
  and the buttons are hidden here so the rule is visible too.
-->
<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Search, ShieldCheck, Trash2, UserCheck, UserX, Users } from 'lucide-vue-next';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { AppModal, AppPagination, ConfirmDialog, EmptyState } from '@/components/ui';
import { useTranslation } from '@/lib/i18n';
import { useAuth } from '@/lib/auth';
import { formatDate } from '@/lib/format';

const props = defineProps({
    students: { type: Object, default: () => ({ data: [], current_page: 1, last_page: 1 }) },
    filters: { type: Object, default: () => ({}) },
});

const { t } = useTranslation();
const { user: me } = useAuth();

const query = ref(props.filters.q || '');

function applyFilters() {
    router.get('/admin/students', { q: query.value || undefined }, { preserveState: true, preserveScroll: true, replace: true });
}

/* ---- Create ---- */
const showCreate = ref(false);

const form = useForm({
    name: '',
    email: '',
    password: '',
    country: '',
    target_band: '',
    role: 'student',
});

function openCreate() {
    form.reset();
    form.clearErrors();
    form.role = 'student';
    showCreate.value = true;
}

function save() {
    form.post('/admin/students', {
        preserveScroll: true,
        onSuccess: () => {
            showCreate.value = false;
            form.reset();
        },
    });
}

/* ---- Row actions ---- */
const pendingDelete = ref(null);

function toggleActive(row) {
    router.patch(`/admin/students/${row.id}`, { is_active: !row.is_active }, { preserveScroll: true });
}

function toggleRole(row) {
    router.patch(
        `/admin/students/${row.id}`,
        { role: row.role === 'admin' ? 'student' : 'admin' },
        { preserveScroll: true }
    );
}

function confirmDelete() {
    if (!pendingDelete.value) return;
    router.delete(`/admin/students/${pendingDelete.value.id}`, {
        preserveScroll: true,
        onFinish: () => (pendingDelete.value = null),
    });
}

const rows = computed(() => props.students.data || []);
const isSelf = (row) => row.id === me.value?.id;
</script>

<template>
    <Head :title="t('admin.students')" />

    <AdminLayout>
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ t('admin.students') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ students.total ?? rows.length }} {{ t('admin.totalStudents') }}</p>
            </div>

            <button type="button" class="btn-primary btn-sm gap-1.5" @click="openCreate">
                <Plus :size="15" /> {{ t('admin.addStudent') }}
            </button>
        </header>

        <!-- ---- Search ---- -->
        <div class="card mt-5 flex flex-wrap items-end gap-3 p-4">
            <div class="min-w-[240px] flex-1">
                <label class="label" for="student-search">{{ t('common.search') }}</label>
                <div class="relative">
                    <Search :size="15" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input
                        id="student-search"
                        v-model="query"
                        type="search"
                        class="input pl-9"
                        :placeholder="t('auth.email')"
                        @keyup.enter="applyFilters"
                    />
                </div>
            </div>
            <button type="button" class="btn-secondary" @click="applyFilters">{{ t('common.search') }}</button>
        </div>

        <!-- ---- Table ---- -->
        <section class="card mt-5 overflow-hidden">
            <div v-if="rows.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-semibold">{{ t('auth.name') }}</th>
                            <th class="px-5 py-3 text-left font-semibold">{{ t('common.status') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ t('admin.totalAttempts') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ t('admin.avgBand') }}</th>
                            <th class="px-5 py-3 text-right font-semibold">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in rows" :key="row.id" class="transition hover:bg-slate-50/70">
                            <td class="px-5 py-3.5">
                                <p class="font-medium text-slate-800">
                                    {{ row.name }}
                                    <span v-if="isSelf(row)" class="badge-slate ml-1.5">{{ t('nav.profile') }}</span>
                                </p>
                                <p class="text-xs text-slate-500">{{ row.email }}</p>
                                <p class="mt-0.5 text-[11px] text-slate-400">
                                    {{ row.country || '—' }} · {{ formatDate(row.created_at) }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span :class="row.is_active ? 'badge-green' : 'badge-red'">
                                        {{ row.is_active ? t('admin.active') : t('admin.inactive') }}
                                    </span>
                                    <span v-if="row.role === 'admin'" class="badge-purple inline-flex items-center gap-1">
                                        <ShieldCheck :size="11" /> {{ t('nav.admin') }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-right tabular-nums text-slate-600">{{ row.attempts }}</td>
                            <td class="px-5 py-3.5 text-right tabular-nums text-slate-600">
                                {{ row.avg_band != null ? Number(row.avg_band).toFixed(1) : '—' }}
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex justify-end gap-1.5">
                                    <button
                                        type="button"
                                        class="btn-ghost btn-sm"
                                        :disabled="isSelf(row)"
                                        :title="row.is_active ? t('admin.deactivate') : t('admin.activate')"
                                        @click="toggleActive(row)"
                                    >
                                        <UserCheck v-if="!row.is_active" :size="14" />
                                        <UserX v-else :size="14" />
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-ghost btn-sm"
                                        :disabled="isSelf(row)"
                                        :title="t('nav.admin')"
                                        @click="toggleRole(row)"
                                    >
                                        <ShieldCheck :size="14" />
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-ghost btn-sm text-red-600 hover:bg-red-50"
                                        :disabled="isSelf(row)"
                                        :title="t('common.delete')"
                                        @click="pendingDelete = row"
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
                <EmptyState :icon="Users" :title="t('common.noData')">
                    <template #action>
                        <button type="button" class="btn-primary btn-sm" @click="openCreate">
                            <Plus :size="15" /> {{ t('admin.addStudent') }}
                        </button>
                    </template>
                </EmptyState>
            </div>

            <AppPagination
                v-if="students.last_page > 1"
                :page="students.current_page"
                :pages="students.last_page"
                @change="(p) => router.get('/admin/students', { ...filters, page: p }, { preserveScroll: true })"
            />
        </section>

        <!-- ---- Create ---- -->
        <AppModal :open="showCreate" :title="t('admin.addStudent')" size="lg" @close="showCreate = false">
            <form id="student-form" class="space-y-4" @submit.prevent="save">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="s-name" class="label">{{ t('auth.name') }}</label>
                        <input id="s-name" v-model="form.name" type="text" :class="['input', form.errors.name ? 'input-error' : '']" />
                        <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label for="s-email" class="label">{{ t('auth.email') }}</label>
                        <input id="s-email" v-model="form.email" type="email" :class="['input', form.errors.email ? 'input-error' : '']" />
                        <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="s-password" class="label">{{ t('auth.password') }}</label>
                        <input
                            id="s-password"
                            v-model="form.password"
                            type="text"
                            :class="['input', form.errors.password ? 'input-error' : '']"
                        />
                        <p class="mt-1 text-xs text-slate-500">{{ t('auth.passwordHint') }}</p>
                        <p v-if="form.errors.password" class="field-error">{{ form.errors.password }}</p>
                    </div>
                    <div>
                        <label for="s-role" class="label">{{ t('nav.admin') }}</label>
                        <select id="s-role" v-model="form.role" class="input">
                            <option value="student">{{ t('admin.totalStudents') }}</option>
                            <option value="admin">{{ t('nav.admin') }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="s-country" class="label">{{ t('auth.country') }}</label>
                        <input id="s-country" v-model="form.country" type="text" class="input" />
                    </div>
                    <div>
                        <label for="s-band" class="label">{{ t('auth.targetBand') }}</label>
                        <select id="s-band" v-model="form.target_band" class="input">
                            <option value="">—</option>
                            <option v-for="b in ['3.5', '4.0', '4.5', '5.0', '5.5', '6.0']" :key="b" :value="b">{{ b }}</option>
                        </select>
                    </div>
                </div>
            </form>

            <template #footer>
                <button type="button" class="btn-secondary" @click="showCreate = false">{{ t('common.cancel') }}</button>
                <button type="submit" form="student-form" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? t('common.saving') : t('common.create') }}
                </button>
            </template>
        </AppModal>

        <!-- ---- Delete ---- -->
        <ConfirmDialog
            :open="Boolean(pendingDelete)"
            :title="t('common.delete')"
            :message="`${t('common.delete')} — ${pendingDelete?.name ?? ''}`"
            danger
            @cancel="pendingDelete = null"
            @confirm="confirmDelete"
        />
    </AdminLayout>
</template>
