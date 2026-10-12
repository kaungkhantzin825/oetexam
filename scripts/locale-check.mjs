/**
 * scripts/locale-check.mjs
 * ---------------------------------------------------------------------------
 * Guard: `locales/en.js` and `locales/my.js` must expose exactly the same key
 * set. A string added to one locale but not the other renders as a raw key
 * (e.g. "exam.uploadPhoto") in the UI, which is easy to ship and hard to spot.
 *
 * Run with:  npm run check:locales
 */
import en from '../resources/js/locales/en.js';
import my from '../resources/js/locales/my.js';

/** "exam.uploadPhoto" for every leaf key, in declaration order. */
function flatten(obj, prefix = '') {
    const out = [];
    for (const [key, value] of Object.entries(obj)) {
        const path = prefix ? `${prefix}.${key}` : key;
        if (value && typeof value === 'object' && !Array.isArray(value)) {
            out.push(...flatten(value, path));
        } else {
            out.push(path);
        }
    }
    return out;
}

const enKeys = flatten(en);
const myKeys = flatten(my);

const missingInMy = enKeys.filter((k) => !myKeys.includes(k));
const missingInEn = myKeys.filter((k) => !enKeys.includes(k));

console.log(`en: ${enKeys.length} keys · my: ${myKeys.length} keys`);

if (missingInMy.length) console.error(`\nMissing from my.js:\n  ${missingInMy.join('\n  ')}`);
if (missingInEn.length) console.error(`\nMissing from en.js:\n  ${missingInEn.join('\n  ')}`);

if (missingInMy.length || missingInEn.length) {
    console.error('\nLocale key sets differ — add the missing keys to both files.');
    process.exit(1);
}

console.log('Locale key sets match.');
