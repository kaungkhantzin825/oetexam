<!--
  components/ui/BandBadge.vue
  ---------------------------------------------------------------------------
  Colour-codes an OET band on the 0.0–6.0 scale.
  This is an exam result, not a stock price, so the standard red → amber →
  green "quality" ramp applies, NOT the CN market colour convention.
-->
<script setup>
import { computed } from 'vue';
import { bandTone } from '@/lib/format';

const props = defineProps({
    band: { type: [Number, String], default: 0 },
    size: { type: String, default: 'md' }, // sm | md | lg
});

const value = computed(() => Number(props.band) || 0);
const tone = computed(() => bandTone(value.value));

const sizes = { sm: 'px-2 py-0.5 text-xs', md: 'px-2.5 py-1 text-sm', lg: 'px-3.5 py-1.5 text-lg' };
</script>

<template>
    <span
        :class="[
            'inline-flex items-center gap-1 rounded-full font-bold tabular-nums',
            tone,
            sizes[size] || sizes.md,
        ]"
    >
        {{ value.toFixed(1) }}
    </span>
</template>
