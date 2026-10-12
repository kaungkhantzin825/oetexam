<!--
  layouts/AppLayout.vue
  ---------------------------------------------------------------------------
  The chrome every page shares: navbar, flash-message toasts, footer.

  Flash messages arrive from Laravel's session (`with('success', …)`) via the
  shared Inertia props, so a redirect after a form post shows the confirmation
  without any page-level plumbing.
-->
<script setup>
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Navbar from '@/components/Navbar.vue';
import Footer from '@/components/Footer.vue';
import Toaster from '@/components/Toaster.vue';
import { toast } from '@/lib/toast';

const page = usePage();

watch(
    () => [page.props.flash?.success, page.props.flash?.error],
    ([success, error]) => {
        if (success) toast.success(success);
        if (error) toast.error(error);
    },
    { immediate: true }
);
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50">
        <Navbar />

        <main class="flex-1">
            <slot />
        </main>

        <Footer />
        <Toaster />
    </div>
</template>
