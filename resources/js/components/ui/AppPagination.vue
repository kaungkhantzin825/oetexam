<script setup>
import { useTranslation } from '@/lib/i18n';

const props = defineProps({
    page: { type: Number, default: 1 },
    pages: { type: Number, default: 1 },
});

const emit = defineEmits(['change']);

const { t } = useTranslation();
</script>

<template>
    <div v-if="pages > 1" class="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3">
        <button type="button" class="btn-secondary btn-sm" :disabled="page <= 1" @click="emit('change', page - 1)">
            {{ t('common.previous') }}
        </button>
        <span class="text-xs text-slate-500">
            {{ t('common.page') }} {{ page }} {{ t('common.of') }} {{ pages }}
        </span>
        <button type="button" class="btn-secondary btn-sm" :disabled="page >= pages" @click="emit('change', page + 1)">
            {{ t('common.next') }}
        </button>
    </div>
</template>
