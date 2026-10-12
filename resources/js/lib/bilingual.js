/**
 * lib/bilingual.js
 * ---------------------------------------------------------------------------
 * Shared visibility logic for the English/Myanmar/both display modes.
 *
 * Falls back to English when a Myanmar translation is missing, so a student who
 * picked "မြန်မာ" never ends up staring at an empty panel.
 */

import { computed } from 'vue';
import { langMode } from './langMode';

/**
 * @param {import('vue').Ref<string>|Function} en  English text (or a getter)
 * @param {import('vue').Ref<string>|Function} my  Myanmar text (or a getter)
 * @param {import('vue').Ref<string>|undefined} mode  Override the shared mode
 */
export function useVisibility(en, my, mode) {
    const has = (v) => Boolean(v && String(v).trim());
    const resolve = (v) => (typeof v === 'function' ? v() : v?.value ?? v);

    return computed(() => {
        const enValue = resolve(en);
        const myValue = resolve(my);
        const m = resolve(mode) || langMode.value;

        const hasEn = has(enValue);
        const hasMy = has(myValue);

        return {
            hasEn,
            hasMy,
            showEn: hasEn && (m !== 'my' || !hasMy),
            showMy: hasMy && m !== 'en',
            mode: m,
            // A Myanmar block is expected but absent — show a "pending" note.
            pendingMy: m !== 'en' && !hasMy && hasEn,
        };
    });
}

export default { useVisibility };
