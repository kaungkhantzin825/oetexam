/**
 * lib/i18n.js
 * ---------------------------------------------------------------------------
 * Minimal, dependency-free i18n layer for Vue.
 *
 * Why hand-rolled instead of vue-i18n? The requirement is "prepare the code so
 * Myanmar language can be added later easily" — this gives exactly that with
 * zero extra dependencies: a `t()` function with {{placeholder}} interpolation,
 * a persisted language choice, and automatic fallback to English for any
 * missing key.
 *
 * Adding another language = drop a new file in resources/js/locales/ and
 * register it in the LOCALES map below.
 *
 * State lives in a module-level `ref`, so every component that calls `t()`
 * re-renders when the language changes — no provider component needed.
 */

import { computed, ref } from 'vue';
import en from '../locales/en';
import my from '../locales/my';

const LOCALES = {
    en: { label: 'English', flag: '🇬🇧', dict: en },
    my: { label: 'မြန်မာ', flag: '🇲🇲', dict: my },
};

export const SUPPORTED_LANGUAGES = Object.entries(LOCALES).map(([code, v]) => ({
    code,
    label: v.label,
    flag: v.flag,
}));

const STORAGE_KEY = 'oet_language';

/** Resolve "a.b.c" against a nested object. */
function lookup(dict, path) {
    return path.split('.').reduce((acc, key) => (acc && acc[key] !== undefined ? acc[key] : undefined), dict);
}

/** Replace {{placeholders}} with values from `vars`. */
function interpolate(template, vars) {
    if (typeof template !== 'string' || !vars) return template;
    return template.replace(/\{\{\s*(\w+)\s*\}\}/g, (_, key) =>
        vars[key] !== undefined && vars[key] !== null ? String(vars[key]) : `{{${key}}}`
    );
}

function readStored() {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved && LOCALES[saved]) return saved;
        // Fall back to the browser preference when we support it
        const browser = (navigator.language || 'en').slice(0, 2);
        return LOCALES[browser] ? browser : 'en';
    } catch {
        return 'en';
    }
}

/** The current UI language. Reactive — read it anywhere. */
export const lang = ref(readStored());

/** Keep <html lang> and the Myanmar font class in sync. */
function syncDocument() {
    if (typeof document === 'undefined') return;
    document.documentElement.lang = lang.value;
    document.body.classList.toggle('lang-my', lang.value === 'my');
}

export function setLang(code) {
    if (!LOCALES[code]) return;
    lang.value = code;
    try {
        localStorage.setItem(STORAGE_KEY, code);
    } catch {
        /* private mode — preference simply won't persist */
    }
    syncDocument();
}

/** Call once from app.js before mounting. */
export function initI18n() {
    syncDocument();
}

/**
 * Translate a key. Falls back to English, then to the raw key, so a missing
 * translation never breaks the UI.
 */
export function t(key, vars) {
    const primary = lookup(LOCALES[lang.value]?.dict, key);
    const fallback = lookup(LOCALES.en.dict, key);
    const value = primary !== undefined ? primary : fallback;
    if (value === undefined) return key;
    return interpolate(value, vars);
}

export const isMyanmar = computed(() => lang.value === 'my');

/**
 * Composable form. `const { t, lang, setLang, isMyanmar } = useTranslation()`
 *
 * `t` is deliberately a plain function rather than a computed: it is called
 * with different keys inside one template, and because it reads `lang.value`
 * it still tracks the dependency and re-renders correctly.
 */
export function useTranslation() {
    return { t, lang, setLang, isMyanmar, languages: SUPPORTED_LANGUAGES };
}

export default { t, lang, setLang, useTranslation, SUPPORTED_LANGUAGES, initI18n };
