<!--
  components/LangModeToggle.vue
  ---------------------------------------------------------------------------
  English | မြန်မာ | Both segmented control. Writes to the shared
  `oet_lang_mode` preference (lib/langMode.js).
-->
<script setup>
import { Languages } from 'lucide-vue-next';
import { useTranslation } from '@/lib/i18n';
import { useLangMode } from '@/lib/langMode';

const props = defineProps({
    class: { type: String, default: '' },
    compact: { type: Boolean, default: false },
});

const { t } = useTranslation();
const { langMode, setLangMode } = useLangMode();
</script>

<template>
    <div :class="['inline-flex items-center gap-2', props.class]">
        <span
            v-if="!compact"
            class="hidden items-center gap-1.5 text-xs font-medium text-slate-500 sm:inline-flex"
        >
            <Languages :size="14" />
            {{ t('result.explanationLang') }}
        </span>

        <div class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5">
            <button
                v-for="o in [
                    { key: 'en', label: t('result.langEn') },
                    { key: 'my', label: t('result.langMy') },
                    { key: 'both', label: t('result.langBoth') },
                ]"
                :key="o.key"
                type="button"
                :aria-pressed="langMode === o.key"
                :class="[
                    'rounded-md px-3 py-1.5 text-xs font-semibold transition',
                    langMode === o.key
                        ? 'bg-primary-600 text-white shadow-sm'
                        : 'text-slate-600 hover:bg-white hover:text-slate-900',
                ]"
                @click="setLangMode(o.key)"
            >
                {{ o.label }}
            </button>
        </div>
    </div>
</template>
