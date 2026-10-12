/**
 * lib/auth.js
 * ---------------------------------------------------------------------------
 * Read the signed-in user from Inertia's shared props.
 *
 * The user is shared by App\Http\Middleware\HandleInertiaRequests on every
 * request, so this needs no extra round-trip and is always fresh after a
 * redirect.
 */

import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useAuth() {
    const page = usePage();

    const user = computed(() => page.props.auth?.user ?? null);
    const isAuthenticated = computed(() => Boolean(user.value));
    const isAdmin = computed(() => user.value?.role === 'admin');
    const firstName = computed(() => (user.value?.name || '').split(' ')[0]);

    return { user, isAuthenticated, isAdmin, firstName };
}

/**
 * Is `path` the current page?
 * `exact` matches the whole URL; otherwise a prefix match is used, so
 * `/admin/exams/12` still highlights the `/admin/exams` link.
 */
export function useIsActive() {
    const page = usePage();

    return (path, exact = false) => {
        const current = (page.url || '/').split('?')[0];
        const target = path.split('?')[0];
        if (target === '/') return current === '/';
        return exact ? current === target : current === target || current.startsWith(target + '/');
    };
}

export default { useAuth, useIsActive };
