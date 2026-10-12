<!--
  components/ui/AppModal.vue
  ---------------------------------------------------------------------------
  Accessible modal: closes on Escape, locks background scrolling while open,
  and optionally closes on backdrop click.
-->
<script setup>
import { onBeforeUnmount, watch } from 'vue';
import { X } from 'lucide-vue-next';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    size: { type: String, default: 'md' }, // sm | md | lg | xl
    closeOnBackdrop: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

const widths = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl' };

function onKey(e) {
    if (e.key === 'Escape') emit('close');
}

let previousOverflow = '';

watch(
    () => props.open,
    (isOpen) => {
        if (typeof document === 'undefined') return;
        if (isOpen) {
            document.addEventListener('keydown', onKey);
            previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
        } else {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = previousOverflow;
        }
    }
);

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    if (typeof document !== 'undefined') document.body.style.overflow = previousOverflow;
});
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-4">
            <div
                class="absolute inset-0 animate-fade-in bg-slate-900/50 backdrop-blur-sm"
                aria-hidden="true"
                @click="closeOnBackdrop && emit('close')"
            />

            <div
                role="dialog"
                aria-modal="true"
                :aria-label="title"
                :class="[
                    'relative z-10 w-full animate-slide-up rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl',
                    widths[size] || widths.md,
                ]"
            >
                <div v-if="title" class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <h2 class="text-base font-semibold text-slate-900">{{ title }}</h2>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        aria-label="Close"
                        @click="emit('close')"
                    >
                        <X :size="18" />
                    </button>
                </div>

                <div class="max-h-[70vh] overflow-y-auto px-5 py-5"><slot /></div>

                <div
                    v-if="$slots.footer"
                    class="flex flex-wrap justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:rounded-b-2xl"
                >
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>
