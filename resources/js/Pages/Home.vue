<!--
  Pages/Home.vue
  ---------------------------------------------------------------------------
  Public landing page. Shows live counts pulled from the database so the page
  never claims more content than actually exists.
-->
<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, BookOpen, GraduationCap, Languages, Sparkles } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import SectionIcon from '@/components/SectionIcon.vue';
import { useTranslation } from '@/lib/i18n';
import { useAuth } from '@/lib/auth';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    sections: { type: Array, default: () => [] },
});

const { t } = useTranslation();
const { isAuthenticated, isAdmin } = useAuth();

const SECTION_ORDER = ['Listening', 'Reading', 'Writing', 'Speaking', 'Full'];

function dashboardHref() {
    if (!isAuthenticated.value) return '/login';
    return isAdmin.value ? '/admin' : '/dashboard';
}
</script>

<template>
    <Head :title="t('nav.home')" />

    <AppLayout>
        <!-- ---- Hero ---- -->
        <section class="relative overflow-hidden border-b border-slate-200 bg-white">
            <div class="container-page py-16 sm:py-24">
                <div class="mx-auto max-w-3xl text-center">
                    <span class="badge-blue inline-flex items-center gap-1.5">
                        <Sparkles :size="13" /> {{ t('app.fullName') }}
                    </span>

                    <h1 class="mt-5 text-3xl font-bold tracking-tight text-slate-900 sm:text-5xl">
                        {{ t('app.tagline') }}
                    </h1>

                    <p class="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-slate-600 sm:text-lg">
                        {{ t('app.description') }}
                    </p>

                    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                        <Link :href="dashboardHref()" class="btn-primary btn-lg">
                            {{ isAuthenticated ? t('nav.practice') : t('nav.register') }}
                            <ArrowRight :size="18" />
                        </Link>
                        <Link v-if="!isAuthenticated" href="/login" class="btn-secondary btn-lg">
                            {{ t('nav.login') }}
                        </Link>
                        <Link v-else href="/tutorials" class="btn-secondary btn-lg">
                            <GraduationCap :size="18" /> {{ t('nav.tutorials') }}
                        </Link>
                    </div>
                </div>

                <!-- ---- Live counts ---- -->
                <dl class="mx-auto mt-14 grid max-w-3xl grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="card card-pad text-center">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('admin.totalExams') }}</dt>
                        <dd class="mt-2 text-3xl font-bold tabular-nums text-slate-900">{{ stats.exams ?? 0 }}</dd>
                    </div>
                    <div class="card card-pad text-center">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('admin.totalQuestions') }}</dt>
                        <dd class="mt-2 text-3xl font-bold tabular-nums text-slate-900">{{ stats.questions ?? 0 }}</dd>
                    </div>
                    <div class="card card-pad text-center">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('tutorials.title') }}</dt>
                        <dd class="mt-2 text-3xl font-bold tabular-nums text-slate-900">{{ stats.lessons ?? 0 }}</dd>
                    </div>
                    <div class="card card-pad text-center">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('admin.totalStudents') }}</dt>
                        <dd class="mt-2 text-3xl font-bold tabular-nums text-slate-900">{{ stats.students ?? 0 }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        <!-- ---- Sub-tests ---- -->
        <section class="container-page py-16">
            <h2 class="section-title text-center">{{ t('nav.practice') }}</h2>
            <p class="section-subtitle mx-auto max-w-2xl text-center">{{ t('about.subtitle') }}</p>

            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="row in SECTION_ORDER"
                    :key="row"
                    :href="isAuthenticated ? `/practice?section=${row}` : '/login'"
                    class="card-hover card-pad group"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-primary-50 text-primary-600">
                            <SectionIcon :section="row" :size="21" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-slate-900">{{ t(`sections.${row}`) }}</h3>
                            <p class="text-xs text-slate-500">
                                {{ (sections.find((s) => s.section === row)?.count ?? 0) }} {{ t('common.questions') }}
                            </p>
                        </div>
                    </div>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ t(`sectionDesc.${row}`) }}</p>
                    <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary-600 group-hover:gap-2 transition-all">
                        {{ t('exam.start') }} <ArrowRight :size="15" />
                    </span>
                </Link>
            </div>
        </section>

        <!-- ---- Bilingual + AI ---- -->
        <section class="border-t border-slate-200 bg-white">
            <div class="container-page grid gap-8 py-16 lg:grid-cols-2">
                <div>
                    <h2 class="section-title">{{ t('tutorials.title') }}</h2>
                    <p class="mt-4 text-sm leading-relaxed text-slate-600">{{ t('tutorials.subtitle') }}</p>

                    <ul class="mt-6 space-y-3">
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-600">
                                <Languages :size="16" />
                            </span>
                            <p class="text-sm leading-relaxed text-slate-700">
                                {{ t('result.explanationLang') }} — {{ t('result.langEn') }} / {{ t('result.langMy') }} / {{ t('result.langBoth') }}
                            </p>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                                <Sparkles :size="16" />
                            </span>
                            <p class="text-sm leading-relaxed text-slate-700">{{ t('result.aiFeedback') }}</p>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600">
                                <BookOpen :size="16" />
                            </span>
                            <p class="text-sm leading-relaxed text-slate-700">{{ t('result.reviewAnswers') }}</p>
                        </li>
                    </ul>
                </div>

                <div class="card card-pad">
                    <h3 class="text-base font-semibold text-slate-900">{{ t('auth.demoAccounts') }}</h3>
                    <p class="mt-1.5 text-sm text-slate-500">{{ t('auth.signInSubtitle') }}</p>

                    <div class="mt-5 space-y-3">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3.5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('nav.admin') }}</p>
                            <p class="mt-1 font-mono text-sm text-slate-800">admin@oetradiographer.com</p>
                            <p class="font-mono text-sm text-slate-800">Admin@123</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3.5">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('admin.totalStudents') }}</p>
                            <p class="mt-1 font-mono text-sm text-slate-800">student@oetradiographer.com</p>
                            <p class="font-mono text-sm text-slate-800">Student@123</p>
                        </div>
                    </div>

                    <Link href="/login" class="btn-primary mt-5 w-full">{{ t('nav.login') }}</Link>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
