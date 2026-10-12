<!--
  Pages/Auth/Register.vue
-->
<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Activity, UserPlus } from 'lucide-vue-next';
import { useTranslation } from '@/lib/i18n';

const { t } = useTranslation();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    country: '',
    target_band: '',
});

const passwordMismatch = computed(
    () => form.password_confirmation.length > 0 && form.password !== form.password_confirmation
);

const canSubmit = computed(
    () =>
        form.name.length >= 2 &&
        form.email.length > 0 &&
        form.password.length >= 6 &&
        !passwordMismatch.value &&
        !form.processing
);

function submit() {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head :title="t('auth.registerTitle')" />

    <div class="flex min-h-screen flex-col bg-slate-50">
        <div class="container-page flex flex-1 items-center justify-center py-12">
            <div class="w-full max-w-lg">
                <Link href="/" class="mb-7 flex items-center justify-center gap-2.5">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-600 text-white">
                        <Activity :size="21" />
                    </span>
                    <span class="text-base font-bold text-slate-900">{{ t('app.name') }}</span>
                </Link>

                <div class="card card-pad">
                    <h1 class="text-xl font-bold text-slate-900">{{ t('auth.registerTitle') }}</h1>
                    <p class="mt-1.5 text-sm text-slate-500">{{ t('auth.registerSubtitle') }}</p>

                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <div>
                            <label for="name" class="label">{{ t('auth.name') }}</label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                autocomplete="name"
                                required
                                :class="['input', form.errors.name ? 'input-error' : '']"
                            />
                            <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label for="email" class="label">{{ t('auth.email') }}</label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                autocomplete="username"
                                required
                                :class="['input', form.errors.email ? 'input-error' : '']"
                            />
                            <p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="password" class="label">{{ t('auth.password') }}</label>
                                <input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    autocomplete="new-password"
                                    required
                                    :class="['input', form.errors.password ? 'input-error' : '']"
                                />
                                <p class="mt-1 text-xs text-slate-500">{{ t('auth.passwordHint') }}</p>
                                <p v-if="form.errors.password" class="field-error">{{ form.errors.password }}</p>
                            </div>

                            <div>
                                <label for="password_confirmation" class="label">{{ t('auth.confirmPassword') }}</label>
                                <input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    required
                                    :class="['input', passwordMismatch ? 'input-error' : '']"
                                />
                                <p v-if="passwordMismatch" class="field-error">{{ t('auth.passwordMismatch') }}</p>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="country" class="label">
                                    {{ t('auth.country') }} <span class="text-slate-400">({{ t('common.optional') }})</span>
                                </label>
                                <input id="country" v-model="form.country" type="text" class="input" placeholder="Myanmar" />
                                <p v-if="form.errors.country" class="field-error">{{ form.errors.country }}</p>
                            </div>

                            <div>
                                <label for="target_band" class="label">
                                    {{ t('auth.targetBand') }} <span class="text-slate-400">({{ t('common.optional') }})</span>
                                </label>
                                <select id="target_band" v-model="form.target_band" class="input">
                                    <option value="">—</option>
                                    <option v-for="b in ['3.5', '4.0', '4.5', '5.0', '5.5', '6.0']" :key="b" :value="b">{{ b }}</option>
                                </select>
                                <p v-if="form.errors.target_band" class="field-error">{{ form.errors.target_band }}</p>
                            </div>
                        </div>

                        <button type="submit" class="btn-primary w-full" :disabled="!canSubmit">
                            <UserPlus :size="17" />
                            {{ form.processing ? t('auth.creating') : t('nav.register') }}
                        </button>
                    </form>

                    <p class="mt-5 text-center text-sm text-slate-600">
                        {{ t('auth.haveAccount') }}
                        <Link href="/login" class="link">{{ t('nav.login') }}</Link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
