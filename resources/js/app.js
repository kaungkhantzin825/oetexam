import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

import { initI18n } from '@/lib/i18n';
import { initLangMode } from '@/lib/langMode';

const appName = 'OET Radiographer';

// Restore the persisted UI language + bilingual display mode before the first
// render, so the app never flashes the wrong language.
initI18n();
initLangMode();

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),

    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),

    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(createPinia())
            .mount(el);
    },

    progress: {
        color: '#2563eb',
        showSpinner: false,
    },
});
