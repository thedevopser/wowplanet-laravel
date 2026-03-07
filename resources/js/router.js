import { createRouter, createWebHistory } from 'vue-router';
import HomePage from './pages/HomePage.vue';
import CharacterPage from './pages/CharacterPage.vue';
import MyCharactersPage from './pages/MyCharactersPage.vue';
import ClassStatsPage from './pages/ClassStatsPage.vue';
import AccountScorePage from './pages/AccountScorePage.vue';
import PrivacyPage from './pages/PrivacyPage.vue';
import CguPage from './pages/CguPage.vue';

const routes = [
    {
        path: '/',
        name: 'home',
        component: HomePage,
        meta: { title: 'WowPlanet - Suivi de progression World of Warcraft' },
    },
    {
        path: '/character/:realm/:name',
        name: 'character',
        component: CharacterPage,
        meta: { title: 'Chargement...' },
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
        name: 'database-index',
        component: () => import('./pages/DatabaseIndexPage.vue'),
        meta: { title: 'Base de données WoW | WowPlanet' },
    },
    {
        path: '/base-de-donnees/montures/:category?',
        name: 'database-mounts',
        component: () => import('./pages/DatabaseMountsPage.vue'),
        meta: { title: 'Montures WoW | WowPlanet' },
    },
    {
        path: '/base-de-donnees/hauts-faits/:expansion?',
        name: 'database-achievements',
        component: () => import('./pages/DatabaseAchievementsPage.vue'),
        meta: { title: 'Hauts-faits WoW | WowPlanet' },
    },
    {
        path: '/base-de-donnees/quetes/:expansion?/:zone?',
        name: 'database-quests',
        component: () => import('./pages/DatabaseQuestsPage.vue'),
        meta: { title: 'Quêtes WoW | WowPlanet' },
    },
    {
        path: '/base-de-donnees/mascottes/:category?',
        name: 'database-pets',
        component: () => import('./pages/DatabasePetsPage.vue'),
        meta: { title: 'Mascottes WoW | WowPlanet' },
    },
    {
        path: '/base-de-donnees/decorations/:category?',
        name: 'database-decors',
        component: () => import('./pages/DatabaseDecorsPage.vue'),
        meta: { title: 'Décorations WoW | WowPlanet' },
    },
    {
        path: '/base-de-donnees/professions/:profession?',
        name: 'database-professions',
        component: () => import('./pages/DatabaseProfessionsPage.vue'),
        meta: { title: 'Professions WoW | WowPlanet' },
    },
    {
        path: '/privacy',
        name: 'privacy',
        component: PrivacyPage,
        meta: { title: 'Politique de confidentialité - WowPlanet' },
    },
    {
        path: '/cgu',
        name: 'cgu',
        component: CguPage,
        meta: { title: 'CGU - WowPlanet' },
    },
    {
        path: '/admin',
        name: 'admin',
        component: () => import('./pages/AdminPage.vue'),
        meta: { title: 'Administration - WowPlanet' },
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/',
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
});

export default router;
