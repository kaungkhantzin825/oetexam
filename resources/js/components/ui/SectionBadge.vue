<script setup>
import { computed } from 'vue';
import { useTranslation } from '@/lib/i18n';

const props = defineProps({
    section: { type: String, default: '' },
});

const { t } = useTranslation();

const TONES = {
    Listening: 'badge-blue',
    Reading: 'badge-green',
    Writing: 'badge-amber',
    Speaking: 'badge-purple',
    Full: 'badge-slate',
};

const cls = computed(() => TONES[props.section] || 'badge-slate');
const label = computed(() => t(`sections.${props.section}`) || props.section);
</script>

<template>
    <span :class="cls">{{ label }}</span>
</template>
