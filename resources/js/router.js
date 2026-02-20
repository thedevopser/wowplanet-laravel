import { createRouter, createWebHistory } from 'vue-router';
import HomePage from './pages/HomePage.vue';
import CharacterPage from './pages/CharacterPage.vue';
import MyCharactersPage from './pages/MyCharactersPage.vue';
import ClassStatsPage from './pages/ClassStatsPage.vue';
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
