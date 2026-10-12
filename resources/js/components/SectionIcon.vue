<!--
  components/SectionIcon.vue
  ---------------------------------------------------------------------------
  One icon per OET sub-test, shared by the dashboard, practice catalogue and
  the exam player so the visual language stays consistent.
-->
<script setup>
import { Ear, BookOpen, PenLine, Mic, LayoutGrid } from 'lucide-vue-next';

const props = defineProps({
    section: { type: String, default: '' },
    size: { type: Number, default: 20 },
});

const ICONS = {
    Listening: Ear,
    Reading: BookOpen,
    Writing: PenLine,
    Speaking: Mic,
    Full: LayoutGrid,
};
</script>

<template>
    <component :is="ICONS[props.section] || LayoutGrid" :size="size" />
</template>
