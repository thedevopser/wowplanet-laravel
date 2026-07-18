import { createRouter, createWebHistory } from 'vue-router';
import { nextTick } from 'vue';
import { showNavigationLoader } from './utils/navigationLoader';

// Force un rechargement complet vers la route Inertia correspondante.
function fullReload(to) {
    window.location.href = to.fullPath;
    return false;
}

// Route « pont » : la page est servie par Inertia, toute navigation client depuis
// le SPA legacy force un rechargement complet (aucun composant legacy rendu).
function inertiaBridge(name, path, title, beforeEnter = fullReload) {
    return { path, name, meta: { title }, beforeEnter, component: { render: () => null } };
}

const routes = [
    inertiaBridge('home', '/', 'WowPlanet - Suivi de progression World of Warcraft'),
    // Seule la page personnage affiche l'overlay de synchronisation (récupération
    // Blizzard potentiellement lente) ; les autres ponts se contentent du reload.
    inertiaBridge('character', '/character/:realm/:name', 'Personnage - WowPlanet', (to) => {
        showNavigationLoader();
        return fullReload(to);
    }),
    inertiaBridge('my-characters', '/my-characters', 'Mes personnages - WowPlanet'),
    inertiaBridge('class-stats', '/class-stats', 'Mes classes - WowPlanet'),
    inertiaBridge('my-score', '/my-score', 'Mon score compte - WowPlanet'),
    {
        path: '/base-de-donnees',
        component: () => import('./components/DatabaseLayout.vue'),
        children: [
            { path: '', name: 'database-index', component: () => import('./pages/DatabaseIndexPage.vue'), meta: { title: 'Base de données WoW | WowPlanet' } },
            { path: 'montures/:category?', name: 'database-mounts', component: () => import('./pages/DatabaseMountsPage.vue'), meta: { title: 'Montures WoW | WowPlanet' } },
            { path: 'hauts-faits/:expansion?', name: 'database-achievements', component: () => import('./pages/DatabaseAchievementsPage.vue'), meta: { title: 'Hauts-faits WoW | WowPlanet' } },
            { path: 'quetes/:expansion?', name: 'database-quests', component: () => import('./pages/DatabaseQuestsPage.vue'), meta: { title: 'Quêtes WoW | WowPlanet' } },
            { path: 'mascottes/:category?', name: 'database-pets', component: () => import('./pages/DatabasePetsPage.vue'), meta: { title: 'Mascottes WoW | WowPlanet' } },
            { path: 'decorations/:category?', name: 'database-decors', component: () => import('./pages/DatabaseDecorsPage.vue'), meta: { title: 'Décorations WoW | WowPlanet' } },
            { path: 'garde-robe/:slot?', name: 'database-transmog', component: () => import('./pages/DatabaseTransmogPage.vue'), meta: { title: 'Transmogrification WoW | WowPlanet' } },
            { path: 'professions/:profession?', name: 'database-professions', component: () => import('./pages/DatabaseProfessionsPage.vue'), meta: { title: 'Professions WoW | WowPlanet' } },
        ],
    },
    inertiaBridge('privacy', '/privacy', 'Politique de confidentialité - WowPlanet'),
    inertiaBridge('cgu', '/cgu', 'CGU - WowPlanet'),
    inertiaBridge('faq', '/faq', 'FAQ - WowPlanet'),
    inertiaBridge('admin', '/admin', 'Administration - WowPlanet'),
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('./pages/NotFoundPage.vue'),
        meta: { title: 'Page introuvable - WowPlanet' },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) return savedPosition;
        return { top: 0 };
    },
});

router.afterEach((to) => {
    if (to.meta.title) {
        document.title = to.meta.title;
    }
    nextTick(() => window.$WowheadPower?.refreshLinks());
});

export default router;
