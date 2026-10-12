/**
 * lib/toast.js
 * ---------------------------------------------------------------------------
 * Tiny dependency-free toast system, replacing react-hot-toast.
 *
 *   import { toast } from '@/lib/toast';
 *   toast.success('Saved.');
 *   toast.error(err.friendlyMessage);
 *
 * Mount <Toaster /> once, in the root layout.
 */

import { ref } from 'vue';

let nextId = 1;

/** @type {import('vue').Ref<Array<{id:number,type:string,message:string}>>} */
export const toasts = ref([]);

function push(type, message, duration = 4000) {
    if (!message) return null;
    const id = nextId++;
    toasts.value.push({ id, type, message });

    if (duration > 0) {
        setTimeout(() => dismiss(id), duration);
    }
    return id;
}

export function dismiss(id) {
    const i = toasts.value.findIndex((x) => x.id === id);
    if (i !== -1) toasts.value.splice(i, 1);
}

export function clearToasts() {
    toasts.value = [];
}

export const toast = {
    success: (m, d) => push('success', m, d),
    error: (m, d) => push('error', m, d ?? 6000),
    info: (m, d) => push('info', m, d),
    /** Show a caught error's friendly message. */
    from: (err, fallback = 'Something went wrong. Please try again.') =>
        push('error', err?.friendlyMessage || err?.message || fallback, 6000),
};

export default toast;
