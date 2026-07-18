import { createRouter, createWebHistory } from 'vue-router';
import { nextTick } from 'vue';
import { showNavigationLoader } from './utils/navigationLoader';
import HomePage from './pages/HomePage.vue';
import MyCharactersPage from './pages/MyCharactersPage.vue';
import ClassStatsPage from './pages/ClassStatsPage.vue';
import AccountScorePage from './pages/AccountScorePage.vue';

const routes = [
    {
        path: '/',
        name: 'home',
        component: HomePage,
        meta: { title: 'WowPlanet - Suivi de progression World of Warcraft' },
    },
    {
        // La page personnage est désormais servie par Inertia (rendu serveur).
        // Toute navigation client depuis le SPA legacy force un chargement complet.
        path: '/character/:realm/:name',
        name: 'character',
        meta: { title: 'Personnage - WowPlanet' },
        beforeEnter: (to) => {
            showNavigationLoader();
            window.location.href = to.fullPath;
            return false;
        },
        component: { render: () => null },
    },
    {
        path: '/my-characters',
        name: 'my-characters',
        component: MyCharactersPage,
        meta: { title: 'Mes personnages - WowPlanet' },
    },
    {
        path: '/class-stats',
        name: 'class-stats',
        component: ClassStatsPage,
        meta: { title: 'Mes classes - WowPlanet' },
    },
    {
        path: '/my-score',
        name: 'my-score',
        component: AccountScorePage,
        meta: { title: 'Mon score compte - WowPlanet' },
    },
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
    {
        // Pages statiques désormais servies par Inertia (rendu serveur).
        // Toute navigation client depuis le SPA legacy force un chargement complet.
        path: '/privacy',
        name: 'privacy',
        meta: { title: 'Politique de confidentialité - WowPlanet' },
        beforeEnter: (to) => {
            showNavigationLoader();
            window.location.href = to.fullPath;
            return false;
        },
        component: { render: () => null },
    },
    {
        path: '/cgu',
        name: 'cgu',
        meta: { title: 'CGU - WowPlanet' },
        beforeEnter: (to) => {
            showNavigationLoader();
            window.location.href = to.fullPath;
            return false;
        },
        component: { render: () => null },
    },
    {
        path: '/faq',
        name: 'faq',
        meta: { title: 'FAQ - WowPlanet' },
        beforeEnter: (to) => {
            showNavigationLoader();
            window.location.href = to.fullPath;
            return false;
        },
        component: { render: () => null },
    },
    {
        path: '/admin',
        name: 'admin',
        component: () => import('./pages/AdminPage.vue'),
        meta: { title: 'Administration - WowPlanet' },
    },
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
