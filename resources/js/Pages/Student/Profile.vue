<!--
  Pages/Student/Profile.vue
  ---------------------------------------------------------------------------
  Two independent forms — profile details and password change — each posting to
  its own route. Both are plain Inertia form posts, so validation errors come
  back as `form.errors` and the success message arrives as a flash toast.
-->
<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Award, BookOpen, GraduationCap, KeyRound, Save, Target, TrendingUp, UserCog } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { StatCard } from '@/components/ui';
import { useTranslation } from '@/lib/i18n';
import { useAuth } from '@/lib/auth';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
});

const { t } = useTranslation();
const { user } = useAuth();

const profileForm = useForm({
    name: user.value?.name ?? '',
    country: user.value?.country ?? '',
    phone: user.value?.phone ?? '',
    target_band: user.value?.target_band ?? '',
    language: user.value?.language ?? 'en',
});

const passwordForm = useForm({
    currentPassword: '',
    newPassword: '',
});

function saveProfile() {
    profileForm.put('/profile', { preserveScroll: true });
}

function changePassword() {
    passwordForm.put('/profile/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}
</script>

<template>
    <Head :title="t('nav.profile')" />

    <AppLayout>
        <div class="container-page py-8">
            <header>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ t('nav.profile') }}</h1>
                <p class="mt-1 text-sm text-slate-600">{{ user?.email }}</p>
            </header>

            <!-- ---- Stats ---- -->
            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard :icon="Target" :label="t('dashboard.testsTaken')" :value="stats.tests_taken ?? 0" tone="blue" />
                <StatCard
                    :icon="TrendingUp"
                    :label="t('dashboard.averageBand')"
                    :value="Number(stats.avg_band ?? 0).toFixed(1)"
                    tone="purple"
                />
                <StatCard
                    :icon="Award"
                    :label="t('dashboard.bestBand')"
                    :value="Number(stats.best_band ?? 0).toFixed(1)"
                    tone="teal"
                />
                <StatCard
                    :icon="GraduationCap"
                    :label="t('tutorials.completed')"
                    :value="stats.lessons_completed ?? 0"
                    tone="amber"
                />
            </div>

            <div class="mt-8 grid gap-6 lg:grid-cols-2">
                <!-- ---- Profile details ---- -->
                <section class="card card-pad">
                    <h2 class="inline-flex items-center gap-2 text-base font-semibold text-slate-900">
                        <UserCog :size="17" class="text-primary-600" /> {{ t('nav.profile') }}
                    </h2>

                    <form class="mt-5 space-y-4" @submit.prevent="saveProfile">
                        <div>
                            <label for="name" class="label">{{ t('auth.name') }}</label>
                            <input id="name" v-model="profileForm.name" type="text" class="input" />
                            <p v-if="profileForm.errors.name" class="field-error">{{ profileForm.errors.name }}</p>
                        </div>

                        <div>
                            <label for="email" class="label">{{ t('auth.email') }}</label>
                            <input id="email" :value="user?.email" type="email" class="input bg-slate-50" disabled />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="country" class="label">{{ t('auth.country') }}</label>
                                <input id="country" v-model="profileForm.country" type="text" class="input" />
                                <p v-if="profileForm.errors.country" class="field-error">{{ profileForm.errors.country }}</p>
                            </div>

                            <div>
                                <label for="phone" class="label">{{ t('auth.phone') }}</label>
                                <input id="phone" v-model="profileForm.phone" type="text" class="input" />
                                <p v-if="profileForm.errors.phone" class="field-error">{{ profileForm.errors.phone }}</p>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="target_band" class="label">{{ t('auth.targetBand') }}</label>
                                <select id="target_band" v-model="profileForm.target_band" class="input">
                                    <option value="">—</option>
                                    <option v-for="b in ['3.5', '4.0', '4.5', '5.0', '5.5', '6.0']" :key="b" :value="b">{{ b }}</option>
                                </select>
                                <p v-if="profileForm.errors.target_band" class="field-error">{{ profileForm.errors.target_band }}</p>
                            </div>

                            <div>
                                <label for="language" class="label">{{ t('common.language') }}</label>
                                <select id="language" v-model="profileForm.language" class="input">
                                    <option value="en">English</option>
                                    <option value="my">မြန်မာ</option>
                                </select>
                                <p v-if="profileForm.errors.language" class="field-error">{{ profileForm.errors.language }}</p>
                            </div>
                        </div>

                        <button type="submit" class="btn-primary" :disabled="profileForm.processing">
                            <Save :size="16" />
                            {{ profileForm.processing ? t('common.saving') : t('common.save') }}
                        </button>
                    </form>
                </section>

                <!-- ---- Password ---- -->
                <section class="card card-pad">
                    <h2 class="inline-flex items-center gap-2 text-base font-semibold text-slate-900">
                        <KeyRound :size="17" class="text-primary-600" /> {{ t('auth.password') }}
                    </h2>

                    <form class="mt-5 space-y-4" @submit.prevent="changePassword">
                        <div>
                            <label for="currentPassword" class="label">{{ t('auth.currentPassword') }}</label>
                            <input
                                id="currentPassword"
                                v-model="passwordForm.currentPassword"
                                type="password"
                                autocomplete="current-password"
                                :class="['input', passwordForm.errors.currentPassword ? 'input-error' : '']"
                            />
                            <p v-if="passwordForm.errors.currentPassword" class="field-error">
                                {{ passwordForm.errors.currentPassword }}
                            </p>
                        </div>

                        <div>
                            <label for="newPassword" class="label">{{ t('auth.newPassword') }}</label>
                            <input
                                id="newPassword"
                                v-model="passwordForm.newPassword"
                                type="password"
                                autocomplete="new-password"
                                :class="['input', passwordForm.errors.newPassword ? 'input-error' : '']"
                            />
                            <p class="mt-1 text-xs text-slate-500">{{ t('auth.passwordHint') }}</p>
                            <p v-if="passwordForm.errors.newPassword" class="field-error">
                                {{ passwordForm.errors.newPassword }}
                            </p>
                        </div>

                        <button type="submit" class="btn-primary" :disabled="passwordForm.processing">
                            <Save :size="16" />
                            {{ passwordForm.processing ? t('common.saving') : t('common.save') }}
                        </button>
                    </form>

                    <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-3.5">
                        <p class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <BookOpen :size="13" /> {{ t('nav.tutorials') }}
                        </p>
                        <p class="mt-1.5 text-sm text-slate-600">{{ t('tutorials.subtitle') }}</p>
                        <Link href="/tutorials" class="btn-secondary btn-sm mt-3">{{ t('tutorials.title') }}</Link>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
