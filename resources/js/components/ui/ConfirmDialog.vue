<script setup>
import { useTranslation } from '@/lib/i18n';
import AppModal from './AppModal.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: '' },
    cancelLabel: { type: String, default: '' },
    danger: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'cancel']);

const { t } = useTranslation();
</script>

<template>
    <AppModal :open="open" :title="title" size="sm" @close="emit('cancel')">
        <p class="text-sm leading-relaxed text-slate-600">{{ message }}</p>

        <template #footer>
            <button type="button" class="btn-secondary" @click="emit('cancel')">
                {{ cancelLabel || t('common.cancel') }}
            </button>
            <button type="button" :class="danger ? 'btn-danger' : 'btn-primary'" @click="emit('confirm')">
                {{ confirmLabel || t('common.confirm') }}
            </button>
        </template>
    </AppModal>
</template>
