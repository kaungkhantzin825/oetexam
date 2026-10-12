<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, default: '' },
});

const MAP = {
    MCQ: { cls: 'badge-blue', label: 'Multiple choice' },
    ShortAnswer: { cls: 'badge-green', label: 'Short answer' },
    Writing: { cls: 'badge-amber', label: 'Writing task' },
    RolePlay: { cls: 'badge-purple', label: 'Role-play' },
};

const cfg = computed(() => MAP[props.type] || { cls: 'badge-slate', label: props.type });
</script>

<template>
    <span :class="cfg.cls">{{ cfg.label }}</span>
</template>
