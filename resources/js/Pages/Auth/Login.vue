<!--
  Pages/Auth/Login.vue
  ---------------------------------------------------------------------------
  Session login via Laravel's own Auth guard. Existing bcrypt password hashes
  verify unchanged, so no account needs a password reset after the migration.
-->
<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Activity, KeyRound, LogIn, Mail, UserCheck } from 'lucide-vue-next';
import { useTranslation } from '@/lib/i18n';

const { t } = useTranslation();

const form = useForm({
    email: '',
    password: '',
});

const DEMO = [
    { role: 'admin', labelKey: 'nav.admin', email: 'admin@oetradiographer.com', password: 'Admin@123' },
    { role: 'student', labelKey: 'admin.totalStudents', email: 'student@oetradiographer.com', password: 'Student@123' },
];

const canSubmit = computed(() => form.email.length > 0 && form.password.length > 0 && !form.processing);

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}

function useDemo(account) {
    form.email = account.email;
    form.password = account.password;
    form.clearErrors();
}
</script>

<template>
    <Head :title="t('auth.signInTitle')" />

    <div class="flex min-h-screen flex-col bg-slate-50">
        <div class="container-page flex flex-1 items-center justify-center py-12">
            <div class="w-full max-w-md">
                <!-- Brand -->
                <Link href="/" class="mb-7 flex items-center justify-center gap-2.5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-600 text-white">
                        <Activity :size="21" />
                    </span>
                    <span class="text-base font-bold text-slate-900">{{ t('app.name') }}</span>
                </Link>

                <div class="card card-pad">
                    <h1 class="text-xl font-bold text-slate-900">{{ t('auth.signInTitle') }}</h1>
                    <p class="mt-1.5 text-sm text-slate-500">{{ t('auth.signInSubtitle') }}</p>

                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <!-- Email -->
                        <div>
                            <label for="email" class="label">{{ t('auth.email') }}</label>
                            <div class="relative">
                                <Mail :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    name="email"
                                    autocomplete="username"
                                    required
                                    :class="['input pl-9', form.errors.email ? 'input-error' : '']"
                                    :placeholder="t('auth.email')"
                                />
                            </div>
                            <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
                        </div>

                        <!-- Password -->
                        <div>
                            <label for="password" class="label">{{ t('auth.password') }}</label>
                            <div class="relative">
                                <KeyRound :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                                <input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    name="password"
                                    autocomplete="current-password"
                                    required
                                    :class="['input pl-9', form.errors.password ? 'input-error' : '']"
                                    placeholder="••••••••"
                                />
                            </div>
                            <p v-if="form.errors.password" class="field-error">{{ form.errors.password }}</p>
                        </div>

                        <button type="submit" class="btn-primary w-full" :disabled="!canSubmit">
                            <LogIn :size="17" />
                            {{ form.processing ? t('auth.signingIn') : t('nav.login') }}
                        </button>
                    </form>

                    <p class="mt-5 text-center text-sm text-slate-600">
                        {{ t('auth.noAccount') }}
                        <Link href="/register" class="link">{{ t('nav.register') }}</Link>
                    </p>
                </div>

                <!-- Demo accounts -->
                <div class="card mt-5 card-pad">
                    <h2 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <UserCheck :size="14" /> {{ t('auth.demoAccounts') }}
                    </h2>

                    <div class="mt-3 space-y-2">
                        <button
                            v-for="account in DEMO"
                            :key="account.role"
                            type="button"
                            class="flex w-full items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-3 text-left transition hover:border-primary-300 hover:bg-primary-50/50"
                            @click="useDemo(account)"
                        >
                            <span class="min-w-0">
                                <span class="block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    {{ t(account.labelKey) }}
                                </span>
                                <span class="mt-0.5 block truncate font-mono text-xs text-slate-700">{{ account.email }}</span>
                            </span>
                            <span class="shrink-0 text-xs font-semibold text-primary-600">{{ t('auth.useDemo') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
