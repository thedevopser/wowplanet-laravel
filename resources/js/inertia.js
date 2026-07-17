import './bootstrap';

window.whTooltips = { colorLinks: true, iconizeLinks: true, renameLinks: false, locale: 'fr' };

import { createApp, h, nextTick } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';

createInertiaApp({
    resolve: (name) =>
        resolvePageComponent(`./pages/${name}.vue`, import.meta.glob('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.use(plugin);
        app.use(createPinia());
        app.mount(el);
    },
    progress: { color: '#3b82f6' },
});

// Rafraîchit les tooltips Wowhead après chaque navigation Inertia
// (remplace l'ancien router.afterEach de Vue Router).
router.on('navigate', () => {
    nextTick(() => window.$WowheadPower?.refreshLinks());
});
