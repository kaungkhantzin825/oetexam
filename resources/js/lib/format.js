/**
 * lib/format.js
 * ---------------------------------------------------------------------------
 * Small formatting helpers shared across pages.
 */

/** 0 -> "0:00", 754 -> "12:34", 3725 -> "1:02:05" */
export function formatClock(totalSeconds) {
    const s = Math.max(0, Math.floor(Number(totalSeconds) || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = s % 60;
    const pad = (n) => String(n).padStart(2, '0');
    return h > 0 ? `${h}:${pad(m)}:${pad(sec)}` : `${m}:${pad(sec)}`;
}

/** 3725 -> "1h 2m", 754 -> "12m", 45 -> "45s" */
export function formatDuration(totalSeconds, t) {
    const s = Math.max(0, Math.floor(Number(totalSeconds) || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const hours = t ? t('dashboard.hours') : 'h';
    const minutes = t ? t('dashboard.minutes') : 'm';
    if (h > 0) return `${h}${hours} ${m}${minutes}`;
    if (m > 0) return `${m}${minutes}`;
    return `${s}s`;
}

/** "2026-10-12 09:31:00" -> "12 Oct 2026" (falls back to the raw string). */
export function formatDate(value, withTime = false) {
    if (!value) return '—';
    const d = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return String(value);
    const opts = { day: 'numeric', month: 'short', year: 'numeric' };
    if (withTime) {
        opts.hour = '2-digit';
        opts.minute = '2-digit';
    }
    return d.toLocaleString(undefined, opts);
}

/**
 * Tailwind tone classes for an OET band on the 0.0–6.0 scale.
 * NOTE: this is an exam result, not a stock price, so the standard
 * red → amber → green "quality" ramp applies, NOT the CN market convention.
 */
export function bandTone(band) {
    const v = Number(band) || 0;
    if (v >= 5) return 'bg-teal-100 text-teal-800';
    if (v >= 3.5) return 'bg-primary-100 text-primary-800';
    if (v >= 2) return 'bg-amber-100 text-amber-800';
    return 'bg-red-100 text-red-800';
}

/** "0" -> "A", "1" -> "B", … Anything unmappable returns null. */
export function optionLetter(index) {
    const n = Number(index);
    if (!Number.isInteger(n) || n < 0 || n > 25) return null;
    return String.fromCharCode(65 + n);
}

/** Percentage, clamped to 0–100 and rounded. */
export function pct(value, max) {
    if (!max || max <= 0) return 0;
    return Math.min(100, Math.max(0, Math.round((value / max) * 100)));
}

/** Countdown used by the exam timer. */
export function secondsUntil(isoDate) {
    if (!isoDate) return 0;
    const target = new Date(String(isoDate).replace(' ', 'T')).getTime();
    if (Number.isNaN(target)) return 0;
    return Math.max(0, Math.round((target - Date.now()) / 1000));
}

/**
 * Turn a stored asset path into a URL the browser can fetch.
 *
 * `questions.audio_url` holds a path relative to the uploads root (for example
 * `listening/q12.mp3`), which Laravel serves from `public/uploads/…`. Absolute
 * URLs and already-rooted paths are returned untouched.
 */
export function resolveAssetUrl(path) {
    if (!path) return null;
    const value = String(path);
    if (/^(https?:)?\/\//i.test(value) || value.startsWith('/')) return value;
    return `/uploads/${value.replace(/^\/+/, '')}`;
}

