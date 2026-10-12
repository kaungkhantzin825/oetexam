<!--
  Pages/NotFound.vue — rendered by the Route::fallback in routes/web.php.
-->
<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Home, SearchX } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslation } from '@/lib/i18n';

const { t } = useTranslation();
</script>

<template>
    <Head :title="t('notFound.title')" />

    <AppLayout>
        <div class="container-page flex min-h-[60vh] flex-col items-center justify-center py-16 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100">
                <SearchX :size="30" class="text-slate-400" />
            </div>

            <p class="mt-6 text-5xl font-bold tabular-nums text-slate-300">404</p>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ t('notFound.title') }}</h1>
            <p class="mt-2 max-w-md text-sm leading-relaxed text-slate-600">{{ t('notFound.body') }}</p>

            <Link href="/" class="btn-primary mt-7">
                <Home :size="17" /> {{ t('notFound.goHome') }}
            </Link>
        </div>
    </AppLayout>
</template>
