<!--
  components/Navbar.vue
  ---------------------------------------------------------------------------
  Responsive top navigation. Renders a different link set for guests, students
  and admins, and includes the language switcher.

  Navigation uses Inertia's <Link>, so every page change is an XHR + partial
  reload — the session cookie and the scroll position behave like a normal app.
-->
<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import {
    Activity,
    BookOpen,
    Globe,
    GraduationCap,
    History,
    LayoutDashboard,
    LogOut,
    Menu,
    ShieldCheck,
    User,
    X,
} from 'lucide-vue-next';
import { useTranslation } from '@/lib/i18n';
import { useAuth, useIsActive } from '@/lib/auth';

const { t, lang, setLang, languages } = useTranslation();
const { user, isAuthenticated, isAdmin } = useAuth();
const isActive = useIsActive();

const open = ref(false);
const langOpen = ref(false);

// No Ziggy in this project — the logout URL is fixed, so post to it directly.
function logout() {
    open.value = false;
    router.post('/logout');
}

function linkClass(path, exact = false) {
    return [
        'rounded-lg px-3 py-2 text-sm font-medium transition',
        isActive(path, exact)
            ? 'bg-primary-50 text-primary-700'
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
    ];
}

/** The brand link goes to the admin panel for admins, the dashboard otherwise. */
function homePath() {
    if (!isAuthenticated.value) return '/';
    return isAdmin.value ? '/admin' : '/dashboard';
}
</script>

<template>
    <header class="no-print sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="container-page flex h-16 items-center justify-between gap-3">
            <!-- ---- Brand ---- -->
            <Link :href="homePath()" class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-600 text-white">
                    <Activity :size="19" />
                </span>
                <span class="leading-tight">
                    <span class="block text-sm font-bold text-slate-900">{{ t('app.name') }}</span>
                    <span class="hidden text-[11px] font-medium text-slate-500 sm:block">OET Practice Platform</span>
                </span>
            </Link>

            <!-- ---- Desktop nav ---- -->
            <nav class="hidden items-center gap-1 lg:flex">
                <template v-if="!isAuthenticated">
                    <Link href="/" :class="linkClass('/')">{{ t('nav.home') }}</Link>
                    <Link href="/about" :class="linkClass('/about')">{{ t('nav.about') }}</Link>
                </template>

                <template v-else-if="isAdmin">
                    <Link href="/admin" :class="linkClass('/admin', true)">
                        <span class="inline-flex items-center gap-1.5"><ShieldCheck :size="15" /> {{ t('nav.admin') }}</span>
                    </Link>
                    <Link href="/admin/exams" :class="linkClass('/admin/exams')">{{ t('admin.exams') }}</Link>
                    <Link href="/admin/questions" :class="linkClass('/admin/questions')">{{ t('admin.questions') }}</Link>
                    <Link href="/admin/tutorials" :class="linkClass('/admin/tutorials')">{{ t('admin.tutorials') }}</Link>
                    <Link href="/admin/students" :class="linkClass('/admin/students')">{{ t('admin.students') }}</Link>
                    <Link href="/admin/results" :class="linkClass('/admin/results')">{{ t('admin.results') }}</Link>
                </template>

                <template v-else>
                    <Link href="/dashboard" :class="linkClass('/dashboard')">
                        <span class="inline-flex items-center gap-1.5">
                            <LayoutDashboard :size="15" /> {{ t('nav.dashboard') }}
                        </span>
                    </Link>
                    <Link href="/practice" :class="linkClass('/practice')">
                        <span class="inline-flex items-center gap-1.5"><BookOpen :size="15" /> {{ t('nav.practice') }}</span>
                    </Link>
                    <Link href="/tutorials" :class="linkClass('/tutorials')">
                        <span class="inline-flex items-center gap-1.5">
                            <GraduationCap :size="15" /> {{ t('nav.tutorials') }}
                        </span>
                    </Link>
                    <Link href="/history" :class="linkClass('/history')">
                        <span class="inline-flex items-center gap-1.5"><History :size="15" /> {{ t('nav.history') }}</span>
                    </Link>
                </template>
            </nav>

            <!-- ---- Right side (desktop) ---- -->
            <div class="hidden items-center gap-2 lg:flex">
                <!-- Language switcher -->
                <div class="relative">
                    <button
                        type="button"
                        class="btn-ghost btn-sm gap-1.5"
                        :aria-label="t('common.language')"
                        @click="langOpen = !langOpen"
                    >
                        <Globe :size="15" />
                        <span class="hidden sm:inline">{{ languages.find((l) => l.code === lang)?.label || 'English' }}</span>
                    </button>

                    <template v-if="langOpen">
                        <div class="fixed inset-0 z-10" aria-hidden="true" @click="langOpen = false" />
                        <div class="absolute right-0 z-20 mt-1 w-40 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                            <button
                                v-for="l in languages"
                                :key="l.code"
                                type="button"
                                :class="[
                                    'flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition hover:bg-slate-50',
                                    lang === l.code ? 'font-semibold text-primary-700' : 'text-slate-700',
                                ]"
                                @click="setLang(l.code); langOpen = false"
                            >
                                <span>{{ l.flag }}</span> {{ l.label }}
                            </button>
                        </div>
                    </template>
                </div>

                <template v-if="isAuthenticated">
                    <Link v-if="!isAdmin" href="/profile" class="btn-ghost btn-sm gap-1.5">
                        <User :size="15" /> {{ user?.name?.split(' ')[0] }}
                    </Link>
                    <button type="button" class="btn-secondary btn-sm gap-1.5" @click="logout">
                        <LogOut :size="15" /> {{ t('nav.logout') }}
                    </button>
                </template>

                <template v-else>
                    <Link href="/login" class="btn-secondary btn-sm">{{ t('nav.login') }}</Link>
                    <Link href="/register" class="btn-primary btn-sm">{{ t('nav.register') }}</Link>
                </template>
            </div>

            <!-- ---- Mobile toggle ---- -->
            <div class="flex items-center gap-1 lg:hidden">
                <div class="relative">
                    <button
                        type="button"
                        class="btn-ghost btn-sm gap-1.5"
                        :aria-label="t('common.language')"
                        @click="langOpen = !langOpen"
                    >
                        <Globe :size="15" />
                        <span class="hidden sm:inline">{{ languages.find((l) => l.code === lang)?.label || 'English' }}</span>
                    </button>

                    <template v-if="langOpen">
                        <div class="fixed inset-0 z-10" aria-hidden="true" @click="langOpen = false" />
                        <div class="absolute right-0 z-20 mt-1 w-40 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                            <button
                                v-for="l in languages"
                                :key="l.code"
                                type="button"
                                :class="[
                                    'flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition hover:bg-slate-50',
                                    lang === l.code ? 'font-semibold text-primary-700' : 'text-slate-700',
                                ]"
                                @click="setLang(l.code); langOpen = false"
                            >
                                <span>{{ l.flag }}</span> {{ l.label }}
                            </button>
                        </div>
                    </template>
                </div>

                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-600 hover:bg-slate-100"
                    aria-label="Toggle navigation"
                    :aria-expanded="open"
                    @click="open = !open"
                >
                    <X v-if="open" :size="20" />
                    <Menu v-else :size="20" />
                </button>
            </div>
        </div>

        <!-- ---- Mobile panel ---- -->
        <div v-if="open" class="border-t border-slate-200 bg-white lg:hidden">
            <nav class="container-page flex flex-col gap-1 py-3">
                <template v-if="!isAuthenticated">
                    <Link href="/" :class="linkClass('/')" @click="open = false">{{ t('nav.home') }}</Link>
                    <Link href="/about" :class="linkClass('/about')" @click="open = false">{{ t('nav.about') }}</Link>
                </template>

                <template v-else-if="isAdmin">
                    <Link href="/admin" :class="linkClass('/admin', true)" @click="open = false">{{ t('nav.admin') }}</Link>
                    <Link href="/admin/exams" :class="linkClass('/admin/exams')" @click="open = false">{{ t('admin.exams') }}</Link>
                    <Link href="/admin/questions" :class="linkClass('/admin/questions')" @click="open = false">{{ t('admin.questions') }}</Link>
                    <Link href="/admin/tutorials" :class="linkClass('/admin/tutorials')" @click="open = false">{{ t('admin.tutorials') }}</Link>
                    <Link href="/admin/students" :class="linkClass('/admin/students')" @click="open = false">{{ t('admin.students') }}</Link>
                    <Link href="/admin/results" :class="linkClass('/admin/results')" @click="open = false">{{ t('admin.results') }}</Link>
                </template>

                <template v-else>
                    <Link href="/dashboard" :class="linkClass('/dashboard')" @click="open = false">{{ t('nav.dashboard') }}</Link>
                    <Link href="/practice" :class="linkClass('/practice')" @click="open = false">{{ t('nav.practice') }}</Link>
                    <Link href="/tutorials" :class="linkClass('/tutorials')" @click="open = false">{{ t('nav.tutorials') }}</Link>
                    <Link href="/history" :class="linkClass('/history')" @click="open = false">{{ t('nav.history') }}</Link>
                </template>

                <div class="mt-2 flex flex-col gap-2 border-t border-slate-200 pt-3">
                    <template v-if="isAuthenticated">
                        <Link v-if="!isAdmin" href="/profile" class="btn-secondary justify-start gap-2" @click="open = false">
                            <User :size="15" /> {{ t('nav.profile') }}
                        </Link>
                        <button type="button" class="btn-secondary justify-start gap-2" @click="logout">
                            <LogOut :size="15" /> {{ t('nav.logout') }}
                        </button>
                    </template>
                    <template v-else>
                        <Link href="/login" class="btn-secondary" @click="open = false">{{ t('nav.login') }}</Link>
                        <Link href="/register" class="btn-primary" @click="open = false">{{ t('nav.register') }}</Link>
                    </template>
                </div>
            </nav>
        </div>
    </header>
</template>
