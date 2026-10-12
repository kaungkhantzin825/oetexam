<script setup>
import { useTranslation } from '@/lib/i18n';
import Spinner from './Spinner.vue';

const props = defineProps({
    label: { type: String, default: '' },
    full: { type: Boolean, default: false },
});

const { t } = useTranslation();
</script>

<template>
    <div :class="['flex flex-col items-center justify-center gap-3', props.full ? 'min-h-[60vh]' : 'py-12']">
        <Spinner :size="28" class="text-primary-600" />
        <p class="text-sm text-slate-500">{{ label || t('common.loading') }}</p>
    </div>
</template>
