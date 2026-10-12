<!--
  components/BilingualMarkdown.vue
  ---------------------------------------------------------------------------
  Markdown bilingual block — used for tutorial lesson bodies.

  Renders via components/Markdown.vue, which builds VNodes directly and never
  uses v-html.
-->
<script setup>
import { useTranslation } from '@/lib/i18n';
import { langMode } from '@/lib/langMode';
import { useVisibility } from '@/lib/bilingual';
import Markdown from './Markdown.vue';

const props = defineProps({
    en: { type: String, default: '' },
    my: { type: String, default: '' },
    mode: { type: String, default: '' },
    class: { type: String, default: '' },
});

const { t } = useTranslation();

const vis = useVisibility(
    () => props.en,
    () => props.my,
    () => props.mode || langMode.value
);
</script>

<template>
    <div v-if="vis.hasEn || vis.hasMy" :class="props.class">
        <div v-if="vis.showEn" :class="vis.showMy ? 'rounded-xl bg-slate-50/80 p-4 sm:p-5' : ''">
            <span
                v-if="vis.showMy"
                class="mb-2 inline-block rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600"
            >
                English
            </span>
            <Markdown :source="en" />
        </div>

        <div v-if="vis.showMy" :class="vis.showEn ? 'mt-5 border-t border-dashed border-primary-200 pt-5' : ''">
            <span
                class="mb-1.5 inline-block rounded bg-primary-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-primary-700"
            >
                {{ t('result.myanmarLabel') }}
            </span>
            <Markdown :source="my" />
        </div>

        <p v-if="vis.pendingMy" class="mt-3 text-xs italic text-slate-400">—</p>
    </div>
</template>
