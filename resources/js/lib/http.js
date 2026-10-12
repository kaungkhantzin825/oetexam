/**
 * lib/http.js
 * ---------------------------------------------------------------------------
 * Thin axios wrapper used by the XHR-only endpoints (auto-save, submit, AI).
 *
 * Every Laravel API error in this project comes back as
 *   { success: false, message, details? }
 * or as a 422 with `errors: { field: [msg] }` from the validator. This wrapper
 * normalises both into an Error carrying:
 *   err.friendlyMessage  a message safe to show the student
 *   err.fieldErrors      { field: 'first message' } for inline form errors
 *   err.status           the HTTP status
 *
 * That mirrors the contract the React frontend relied on, so page code reads
 * the same either way.
 */

import axios from 'axios';

const client = axios.create({
    baseURL: '/',
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
    // The session cookie is httpOnly and same-origin; axios sends it by
    // default for same-origin requests, but be explicit.
    withCredentials: true,
});

// Laravel needs the CSRF token on every mutating request. It is exposed in the
// root Blade view as a meta tag.
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

client.interceptors.request.use((config) => {
    const method = (config.method || 'get').toLowerCase();
    if (!['get', 'head', 'options'].includes(method)) {
        config.headers['X-CSRF-TOKEN'] = csrfToken();
    }
    return config;
});

/** Turn an axios failure into a friendly, uniform Error. */
function normalise(error) {
    const res = error?.response;
    const data = res?.data;

    const err = new Error(data?.message || error.message || 'Request failed');
    err.status = res?.status ?? 0;
    err.friendlyMessage = data?.message || defaultMessage(res?.status);
    err.fieldErrors = {};

    if (data?.errors && typeof data.errors === 'object') {
        for (const [field, messages] of Object.entries(data.errors)) {
            err.fieldErrors[field] = Array.isArray(messages) ? messages[0] : String(messages);
        }
        // A 422 body has no `message` worth showing — use the first field error.
        const first = Object.values(err.fieldErrors)[0];
        if (first) err.friendlyMessage = first;
    }

    if (data?.details?.kind) err.kind = data.details.kind;

    return err;
}

function defaultMessage(status) {
    if (status === 401) return 'Your session has expired. Please sign in again.';
    if (status === 403) return 'You do not have permission to do that.';
    if (status === 404) return 'That item no longer exists.';
    if (status === 419) return 'Your session expired. Please refresh the page.';
    if (status === 429) return 'Too many requests — please wait a moment and try again.';
    if (status >= 500) return 'The server had a problem. Please try again in a moment.';
    return 'Something went wrong. Please try again.';
}

async function request(promise) {
    try {
        const { data } = await promise;
        return data;
    } catch (error) {
        throw normalise(error);
    }
}

export const http = {
    get: (url, config) => request(client.get(url, config)),
    post: (url, body, config) => request(client.post(url, body, config)),
    put: (url, body, config) => request(client.put(url, body, config)),
    patch: (url, body, config) => request(client.patch(url, body, config)),
    delete: (url, config) => request(client.delete(url, config)),
};

export default http;
