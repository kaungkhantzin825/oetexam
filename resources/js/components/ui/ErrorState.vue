<script setup>
import { AlertCircle } from 'lucide-vue-next';
import { useTranslation } from '@/lib/i18n';

const props = defineProps({
    message: { type: String, default: '' },
    onRetry: { type: Function, default: null },
});

const { t } = useTranslation();
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-xl border border-red-200 bg-red-50 px-6 py-12 text-center">
        <AlertCircle :size="28" class="mb-3 text-red-500" />
        <h3 class="text-base font-semibold text-red-800">{{ message || t('errors.loadFailed') }}</h3>
        <button v-if="props.onRetry" type="button" class="btn-secondary mt-5" @click="props.onRetry">
            {{ t('common.retry') }}
        </button>
    </div>
</template>
