<!--
  layouts/AdminLayout.vue
  ---------------------------------------------------------------------------
  Admin chrome: a sidebar of admin sections plus the shared navbar/footer.
  The sidebar collapses into a horizontal strip on small screens.
-->
<script setup>
import { Link } from '@inertiajs/vue3';
import {
    BarChart3,
    Bot,
    GraduationCap,
    LayoutDashboard,
    ListChecks,
    Users,
    FileText,
} from 'lucide-vue-next';
import Navbar from '@/components/Navbar.vue';
import Footer from '@/components/Footer.vue';
import Toaster from '@/components/Toaster.vue';
import { useTranslation } from '@/lib/i18n';
import { useIsActive } from '@/lib/auth';

const { t } = useTranslation();
const isActive = useIsActive();

const links = [
    { href: '/admin', label: () => t('admin.dashboard'), icon: LayoutDashboard, exact: true },
    { href: '/admin/exams', label: () => t('admin.exams'), icon: FileText },
    { href: '/admin/questions', label: () => t('admin.questions'), icon: ListChecks },
    { href: '/admin/tutorials', label: () => t('admin.tutorials'), icon: GraduationCap },
    { href: '/admin/students', label: () => t('admin.students'), icon: Users },
    { href: '/admin/results', label: () => t('admin.results'), icon: BarChart3 },
    { href: '/admin/ai-logs', label: () => t('admin.aiLogs'), icon: Bot },
];
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50">
        <Navbar />

        <div class="container-page flex-1 py-6">
            <div class="flex flex-col gap-6 lg:flex-row">
                <!-- Sidebar -->
                <aside class="lg:w-56 lg:shrink-0">
                    <nav class="no-scrollbar flex gap-1 overflow-x-auto lg:flex-col lg:overflow-visible">
                        <Link
                            v-for="item in links"
                            :key="item.href"
                            :href="item.href"
                            :class="[
                                'inline-flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition',
                                isActive(item.href, item.exact)
                                    ? 'bg-primary-600 text-white shadow-sm'
                                    : 'text-slate-600 hover:bg-white hover:text-slate-900',
                            ]"
                        >
                            <component :is="item.icon" :size="15" />
                            <span class="whitespace-nowrap">{{ item.label() }}</span>
                        </Link>
                    </nav>
                </aside>

                <!-- Content -->
                <div class="min-w-0 flex-1">
                    <slot />
                </div>
            </div>
        </div>

        <Footer />
        <Toaster />
    </div>
</template>
