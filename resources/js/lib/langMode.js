/**
 * lib/langMode.js
 * ---------------------------------------------------------------------------
 * The `en | my | both` preference that controls how AUTHORED content is shown.
 *
 * This is a different axis from the UI language in lib/i18n.js:
 *   - lib/i18n.js      which language the *interface* speaks
 *   - lib/langMode.js  how exam explanations / tutorial bodies are displayed
 *
 * Persisted to localStorage under the key **`oet_lang_mode`** — this
 * SUPERSEDES the older `oet_review_lang` key used by an earlier build. The
 * review page and the tutorials deliberately share one choice, so switching to
 * "မြန်မာ" on a result page opens the tutorials in Burmese too.
 */

import { ref } from 'vue';

const STORAGE_KEY = 'oet_lang_mode';

export const LANG_MODES = ['en', 'my', 'both'];

function readStored() {
    try {
        const v = localStorage.getItem(STORAGE_KEY);
        return LANG_MODES.includes(v) ? v : 'both';
    } catch {
        return 'both';
    }
}

/** Shared, reactive across the whole app. */
export const langMode = ref(readStored());

export function setLangMode(mode) {
    if (!LANG_MODES.includes(mode)) return;
    langMode.value = mode;
    try {
        localStorage.setItem(STORAGE_KEY, mode);
    } catch {
        /* private mode — preference simply won't persist */
    }
}

/** Keep multiple tabs in sync. Call once from app.js. */
export function initLangMode() {
    if (typeof window === 'undefined') return;
    window.addEventListener('storage', (e) => {
        if (e.key === STORAGE_KEY && LANG_MODES.includes(e.newValue)) {
            langMode.value = e.newValue;
        }
    });
}

export function useLangMode() {
    return { langMode, setLangMode };
}

export default { langMode, setLangMode, useLangMode, LANG_MODES, initLangMode };
