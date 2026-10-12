<!--
  components/Timer.vue
  ---------------------------------------------------------------------------
  Purely presentational countdown for the exam player.

  The parent owns the interval and passes `remaining` in seconds — keeping the
  clock in one place means a page refresh can't reset it and the auto-submit
  logic and the display can never disagree.
-->
<script setup>
import { computed } from 'vue';
import { Clock } from 'lucide-vue-next';
import { formatClock } from '@/lib/format';

const props = defineProps({
    remaining: { type: Number, required: true },
});

const tone = computed(() => {
    if (props.remaining <= 60) return 'text-red-600 bg-red-50 border-red-200';
    if (props.remaining <= 300) return 'text-amber-700 bg-amber-50 border-amber-200';
    return 'text-slate-700 bg-white border-slate-200';
});
</script>

<template>
    <div
        :class="['inline-flex items-center gap-2 rounded-lg border px-3 py-2 font-semibold tabular-nums', tone]"
        role="timer"
        :aria-live="remaining <= 300 ? 'polite' : 'off'"
    >
        <Clock :size="16" />
        <span class="text-sm sm:text-base">{{ formatClock(remaining) }}</span>
    </div>
</template>
