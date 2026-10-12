<script setup>
import { Inbox } from 'lucide-vue-next';
import { useTranslation } from '@/lib/i18n';

const props = defineProps({
    icon: { type: [Object, Function], default: Inbox },
    title: { type: String, default: '' },
    message: { type: String, default: '' },
});

const { t } = useTranslation();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50/60 px-6 py-14 text-center"
    >
        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-slate-200/70">
            <component :is="props.icon" :size="22" class="text-slate-500" />
        </div>
        <h3 class="text-base font-semibold text-slate-800">{{ title || t('common.noData') }}</h3>
        <p v-if="message" class="mt-1.5 max-w-sm text-sm text-slate-500">{{ message }}</p>
        <div v-if="$slots.action" class="mt-5"><slot name="action" /></div>
    </div>
</template>
