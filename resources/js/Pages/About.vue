<!--
  Pages/About.vue
  ---------------------------------------------------------------------------
  Explains what the OET is, what each sub-test involves, and how this platform
  helps a radiographer prepare.
-->
<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2, Clock, FileText, Headphones, Mic, PenLine, BookOpen } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTranslation } from '@/lib/i18n';
import { useAuth } from '@/lib/auth';

const { t } = useTranslation();
const { isAuthenticated } = useAuth();

const PARTS = [
    {
        key: 'Listening',
        icon: Headphones,
        minutes: 40,
        body: 'Three parts. You hear each recording twice and answer while you listen — consultations, a workplace handover, and a patient conversation.',
        tip: 'Read the questions in the pause before each recording starts. The answers come in order.',
    },
    {
        key: 'Reading',
        icon: BookOpen,
        minutes: 60,
        body: 'Part A is a fast expeditious-reading task on one clinical topic. Parts B and C are longer texts with multiple-choice questions.',
        tip: 'Part A rewards scanning, not reading. Budget about 15 minutes for it.',
    },
    {
        key: 'Writing',
        icon: PenLine,
        minutes: 45,
        body: 'You write one referral letter of 180–200 words based on case notes, in about 40 minutes of writing time.',
        tip: 'Identify the reader and the purpose first. The letter is judged on relevance and clarity, not length.',
    },
    {
        key: 'Speaking',
        icon: Mic,
        minutes: 20,
        body: 'Two role-plays with a patient or colleague. You get a role-play card and two minutes to prepare.',
        tip: 'Practise the opening lines. A clear, empathetic opening carries the whole role-play.',
    },
];

const CRITERIA = [
    'Relevance and completeness of the response',
    'Appropriate use of the genre and register',
    'Accuracy of grammar and vocabulary',
    'Organisation and cohesion',
];
</script>

<template>
    <Head :title="t('nav.about')" />

    <AppLayout>
        <section class="border-b border-slate-200 bg-white">
            <div class="container-page py-14">
                <h1 class="section-title">{{ t('about.title') }}</h1>
                <p class="section-subtitle max-w-3xl">{{ t('about.subtitle') }}</p>

                <div class="mt-6 flex flex-wrap gap-3">
                    <span class="badge-slate inline-flex items-center gap-1.5"><Clock :size="13" /> 40 + 60 + 45 + 20 min</span>
                    <span class="badge-slate inline-flex items-center gap-1.5"><FileText :size="13" /> Band 0.0 – 6.0</span>
                    <span class="badge-slate inline-flex items-center gap-1.5"><CheckCircle2 :size="13" /> Grade B = 3.5+</span>
                </div>
            </div>
        </section>

        <section class="container-page py-14">
            <h2 class="section-title">{{ t('nav.practice') }}</h2>
            <p class="section-subtitle">{{ t('app.description') }}</p>

            <div class="mt-8 grid gap-5 sm:grid-cols-2">
                <article v-for="part in PARTS" :key="part.key" class="card card-pad">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 text-primary-600">
                                <component :is="part.icon" :size="19" />
                            </span>
                            <h3 class="text-base font-semibold text-slate-900">{{ t(`sections.${part.key}`) }}</h3>
                        </div>
                        <span class="badge-slate">{{ part.minutes }} min</span>
                    </div>

                    <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ part.body }}</p>
                    <p class="mt-3 rounded-lg border-l-2 border-primary-300 bg-primary-50/50 py-2 pl-3 text-xs leading-relaxed text-slate-700">
                        {{ part.tip }}
                    </p>
                </article>
            </div>
        </section>

        <section class="border-t border-slate-200 bg-white">
            <div class="container-page grid gap-8 py-14 lg:grid-cols-2">
                <div>
                    <h2 class="section-title">{{ t('result.criteria') }}</h2>
                    <ul class="mt-5 space-y-2.5">
                        <li v-for="c in CRITERIA" :key="c" class="flex items-start gap-2.5">
                            <CheckCircle2 :size="17" class="mt-0.5 shrink-0 text-teal-600" />
                            <span class="text-sm leading-relaxed text-slate-700">{{ c }}</span>
                        </li>
                    </ul>
                </div>

                <div class="card card-pad">
                    <h2 class="text-lg font-semibold text-slate-900">{{ t('app.fullName') }}</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ t('app.description') }}</p>

                    <ul class="mt-5 space-y-3">
                        <li v-for="row in ['Listening', 'Reading', 'Writing', 'Speaking']" :key="row" class="flex items-start gap-2.5">
                            <CheckCircle2 :size="16" class="mt-0.5 shrink-0 text-primary-600" />
                            <p class="text-sm leading-relaxed text-slate-700">
                                <strong class="font-semibold text-slate-900">{{ t(`sections.${row}`) }}</strong> —
                                {{ t(`sectionDesc.${row}`) }}
                            </p>
                        </li>
                    </ul>

                    <Link :href="isAuthenticated ? '/practice' : '/register'" class="btn-primary mt-6 w-full">
                        {{ isAuthenticated ? t('exam.start') : t('nav.register') }}
                    </Link>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
