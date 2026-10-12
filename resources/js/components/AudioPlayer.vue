<!--
  components/AudioPlayer.vue
  ---------------------------------------------------------------------------
  Audio player for Listening questions.

  Why this exists: a bare `<audio :src="...">` fails SILENTLY. If the file is
  missing (404) or unplayable, the browser renders a dead "0:00 / 0:00" player
  and the student has no idea whether it is broken or just loading.

  This component preflights the URL with a HEAD request so a missing file is
  reported immediately, and keeps an `error` listener as a second safety net for
  files that exist but cannot be decoded (wrong format, truncated upload).

  NEVER replace this with a bare <audio src> — that is a known regression.
-->
<script setup>
import { computed, ref, watch } from 'vue';
import { AlertTriangle, Loader2, RefreshCw } from 'lucide-vue-next';
import { useTranslation } from '@/lib/i18n';

const props = defineProps({
    src: { type: String, default: '' },
    class: { type: String, default: '' },
});

const { t } = useTranslation();

/** 'checking' -> 'ready' | 'error' */
const status = ref(props.src ? 'checking' : 'error');
const attempt = ref(0);
let cancelled = false;

/**
 * Preflight with HEAD before mounting the player. The Laravel static handler
 * answers HEAD, so this reliably distinguishes "file missing" from "file fine".
 */
async function preflight() {
    if (!props.src) {
        status.value = 'error';
        return;
    }

    cancelled = false;
    status.value = 'checking';

    try {
        const res = await fetch(props.src, { method: 'HEAD' });
        if (!cancelled) status.value = res.ok ? 'ready' : 'error';
    } catch {
        if (!cancelled) status.value = 'error';
    }
}

watch(() => [props.src, attempt.value], preflight, { immediate: true });

const hasSrc = computed(() => Boolean(props.src));
const message = computed(() => (hasSrc.value ? t('exam.audioLoadFailed') : t('exam.audioNotAvailable')));

function retry() {
    attempt.value += 1;
}
</script>

<template>
    <div v-if="!hasSrc || status === 'error'" :class="['rounded-lg border border-amber-300 bg-amber-50 p-3', props.class]">
        <p class="flex items-start gap-2 text-sm font-medium text-amber-900">
            <AlertTriangle :size="16" class="mt-0.5 shrink-0" />
            {{ message }}
        </p>
        <p class="mt-1 pl-6 text-xs text-amber-800">{{ t('exam.audioHint') }}</p>
        <button
            v-if="hasSrc"
            type="button"
            class="ml-6 mt-2 inline-flex items-center gap-1.5 rounded-md border border-amber-400 bg-white px-2.5 py-1 text-xs font-semibold text-amber-900 hover:bg-amber-100"
            @click="retry"
        >
            <RefreshCw :size="12" /> {{ t('exam.audioRetry') }}
        </button>
    </div>

    <div
        v-else-if="status === 'checking'"
        :class="[
            'flex items-center gap-2 rounded-lg border border-primary-200 bg-white p-3 text-sm text-primary-700',
            props.class,
        ]"
    >
        <Loader2 :size="15" class="animate-spin" />
        {{ t('exam.audioLoading') }}
    </div>

    <audio
        v-else
        controls
        preload="metadata"
        :class="['w-full', props.class]"
        :src="src"
        @error="status = 'error'"
    >
        Your browser does not support the audio element.
    </audio>
</template>
