import './bootstrap';

window.whTooltips = { colorLinks: true, iconizeLinks: true, renameLinks: false, locale: 'fr' };

import { createApp, h, nextTick } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import { showNavigationLoader, hideNavigationLoader } from './utils/navigationLoader';

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

// Overlay de synchronisation pendant une visite Inertia vers une page personnage
// (récupération Blizzard potentiellement lente). Cohérent avec le rechargement
// complet depuis le SPA legacy, qui affiche déjà cet overlay via beforeEnter.
router.on('start', (event) => {
    if (event.detail.visit.url.pathname.startsWith('/character/')) {
        showNavigationLoader();
    }
});

router.on('finish', () => {
    hideNavigationLoader();
});

// Rafraîchit les tooltips Wowhead après chaque navigation Inertia
// (remplace l'ancien router.afterEach de Vue Router).
router.on('navigate', () => {
    nextTick(() => window.$WowheadPower?.refreshLinks());
});
