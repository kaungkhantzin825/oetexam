<!--
  components/Toaster.vue
  ---------------------------------------------------------------------------
  Renders the toast stack from lib/toast.js. Mount once, in AppLayout.
-->
<script setup>
import { AlertCircle, CheckCircle2, Info, X } from 'lucide-vue-next';
import { toasts, dismiss } from '@/lib/toast';

const ICONS = { success: CheckCircle2, error: AlertCircle, info: Info };

const TONES = {
    success: 'border-teal-200 bg-teal-50 text-teal-900',
    error: 'border-red-200 bg-red-50 text-red-900',
    info: 'border-primary-200 bg-primary-50 text-primary-900',
};
</script>

<template>
    <div class="no-print pointer-events-none fixed inset-x-0 top-4 z-[100] flex flex-col items-center gap-2 px-4">
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-for="item in toasts"
                :key="item.id"
                :class="[
                    'pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-xl border px-4 py-3 shadow-card-hover',
                    TONES[item.type] || TONES.info,
                ]"
                role="status"
            >
                <component :is="ICONS[item.type] || Info" :size="18" class="mt-0.5 shrink-0" />
                <p class="flex-1 text-sm font-medium leading-relaxed">{{ item.message }}</p>
                <button
                    type="button"
                    class="shrink-0 rounded p-0.5 opacity-60 transition hover:opacity-100"
                    aria-label="Dismiss"
                    @click="dismiss(item.id)"
                >
                    <X :size="15" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
