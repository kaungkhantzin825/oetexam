<!--
  components/BilingualText.vue
  ---------------------------------------------------------------------------
  Plain-text bilingual block (exam explanations, model answers, role-play
  cards). `whitespace-pre-wrap` preserves the line breaks in model letters —
  do not remove it.

  Shares the `oet_lang_mode` preference with the tutorials via LangModeToggle.
-->
<script setup>
import { useTranslation } from '@/lib/i18n';
import { langMode } from '@/lib/langMode';
import { useVisibility } from '@/lib/bilingual';

const props = defineProps({
    en: { type: String, default: '' },
    my: { type: String, default: '' },
    /** Optional per-instance override of the shared mode. */
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
        <p v-if="vis.showEn" class="whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ en }}</p>

        <div v-if="vis.showMy" :class="vis.showEn ? 'mt-3 border-l-2 border-primary-300 pl-3' : ''">
            <span
                class="mb-1.5 inline-block rounded bg-primary-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-primary-700"
            >
                {{ t('result.myanmarLabel') }}
            </span>
            <p class="whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ my }}</p>
        </div>

        <p v-if="vis.pendingMy" class="mt-2 text-xs italic text-slate-400">
            {{ t('result.translationPending') }}
        </p>
    </div>
</template>
