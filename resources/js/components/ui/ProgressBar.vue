<script setup>
import { computed } from 'vue';

const props = defineProps({
    value: { type: Number, default: 0 },
    max: { type: Number, default: 100 },
    tone: { type: String, default: 'primary' }, // primary | teal | amber | red
    showLabel: { type: Boolean, default: false },
    class: { type: String, default: '' },
});

const pct = computed(() => (props.max > 0 ? Math.min(100, Math.max(0, (props.value / props.max) * 100)) : 0));

const tones = { primary: 'bg-primary-600', teal: 'bg-teal-600', amber: 'bg-amber-500', red: 'bg-red-500' };
</script>

<template>
    <div :class="props.class">
        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-200">
            <div
                :class="['h-full rounded-full transition-all duration-500', tones[tone] || tones.primary]"
                :style="{ width: pct + '%' }"
            />
        </div>
        <p v-if="showLabel" class="mt-1 text-xs text-slate-500">{{ Math.round(pct) }}%</p>
    </div>
</template>
