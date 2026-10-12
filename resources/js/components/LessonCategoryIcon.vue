<!--
  components/LessonCategoryIcon.vue
  ---------------------------------------------------------------------------
  A round, tinted tile holding the icon for a tutorial topic. Used on the
  library cards and in the reader header.
-->
<script setup>
import { computed } from 'vue';
import { lessonCategoryMeta } from '@/lib/lessonCategories';

const props = defineProps({
    category: { type: String, default: '' },
    size: { type: Number, default: 20 },
    /** 'tile' renders the tinted square; 'bare' just the icon. */
    variant: { type: String, default: 'tile' },
});

const meta = computed(() => lessonCategoryMeta(props.category));
</script>

<template>
    <span
        v-if="variant === 'tile'"
        :class="['flex h-10 w-10 shrink-0 items-center justify-center rounded-lg', meta.tile]"
    >
        <component :is="meta.icon" :size="size" />
    </span>
    <component v-else :is="meta.icon" :size="size" />
</template>
