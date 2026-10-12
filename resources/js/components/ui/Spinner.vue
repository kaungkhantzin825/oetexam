<script setup>
import { Loader2 } from 'lucide-vue-next';

defineProps({
    size: { type: Number, default: 20 },
    class: { type: String, default: '' },
});
</script>

<template>
    <Loader2 :size="size" :class="['animate-spin', $props.class]" />
</template>
