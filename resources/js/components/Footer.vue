<!--
  components/Footer.vue
-->
<script setup>
import { Link } from '@inertiajs/vue3';
import { Activity, Heart } from 'lucide-vue-next';
import { useTranslation } from '@/lib/i18n';
import { useAuth } from '@/lib/auth';

const { t } = useTranslation();
const { isAuthenticated, isAdmin } = useAuth();

const year = new Date().getFullYear();
</script>

<template>
    <footer class="no-print mt-auto border-t border-slate-200 bg-white">
        <div class="container-page grid gap-8 py-10 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Brand -->
            <div class="sm:col-span-2">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-600 text-white">
                        <Activity :size="17" />
                    </span>
                    <span class="text-sm font-bold text-slate-900">{{ t('app.name') }}</span>
                </div>
                <p class="mt-3 max-w-md text-sm leading-relaxed text-slate-500">{{ t('app.description') }}</p>
            </div>

            <!-- Practice -->
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ t('nav.practice') }}</h4>
                <ul class="mt-3 space-y-2 text-sm">
                    <template v-if="isAuthenticated && !isAdmin">
                        <li><Link href="/practice?section=Listening" class="text-slate-600 transition hover:text-primary-600">{{ t('sections.Listening') }}</Link></li>
                        <li><Link href="/practice?section=Reading" class="text-slate-600 transition hover:text-primary-600">{{ t('sections.Reading') }}</Link></li>
                        <li><Link href="/practice?section=Writing" class="text-slate-600 transition hover:text-primary-600">{{ t('sections.Writing') }}</Link></li>
                        <li><Link href="/practice?section=Speaking" class="text-slate-600 transition hover:text-primary-600">{{ t('sections.Speaking') }}</Link></li>
                    </template>
                    <template v-else>
                        <li><Link href="/about" class="text-slate-600 transition hover:text-primary-600">{{ t('nav.about') }}</Link></li>
                        <li><Link href="/login" class="text-slate-600 transition hover:text-primary-600">{{ t('nav.login') }}</Link></li>
                        <li><Link href="/register" class="text-slate-600 transition hover:text-primary-600">{{ t('nav.register') }}</Link></li>
                    </template>
                </ul>
            </div>

            <!-- Account -->
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ t('nav.profile') }}</h4>
                <ul class="mt-3 space-y-2 text-sm">
                    <template v-if="isAuthenticated">
                        <li>
                            <Link :href="isAdmin ? '/admin' : '/dashboard'" class="text-slate-600 transition hover:text-primary-600">
                                {{ t('nav.dashboard') }}
                            </Link>
                        </li>
                        <template v-if="!isAdmin">
                            <li><Link href="/history" class="text-slate-600 transition hover:text-primary-600">{{ t('nav.history') }}</Link></li>
                            <li><Link href="/profile" class="text-slate-600 transition hover:text-primary-600">{{ t('nav.profile') }}</Link></li>
                        </template>
                    </template>
                    <template v-else>
                        <li><Link href="/login" class="text-slate-600 transition hover:text-primary-600">{{ t('nav.login') }}</Link></li>
                        <li><Link href="/register" class="text-slate-600 transition hover:text-primary-600">{{ t('nav.register') }}</Link></li>
                    </template>
                </ul>
            </div>
        </div>

        <div class="border-t border-slate-100">
            <div class="container-page flex flex-col items-center justify-between gap-2 py-4 text-xs text-slate-500 sm:flex-row">
                <p>© {{ year }} {{ t('app.fullName') }}. All rights reserved.</p>
                <p class="inline-flex items-center gap-1">
                    Built for radiographers with <Heart :size="12" class="text-red-400" /> and OET in mind.
                </p>
            </div>
        </div>
    </footer>
</template>
