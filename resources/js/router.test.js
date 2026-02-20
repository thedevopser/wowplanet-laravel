import { describe, it, expect, vi } from 'vitest';
import router from './router';

describe('router', () => {
    it('defines all expected routes', () => {
        const routeNames = router.getRoutes()
            .filter(r => r.name)
            .map(r => r.name);

        expect(routeNames).toContain('home');
        expect(routeNames).toContain('character');
        expect(routeNames).toContain('my-characters');
        expect(routeNames).toContain('class-stats');
        expect(routeNames).toContain('privacy');
        expect(routeNames).toContain('cgu');
    });

    it('character route accepts realm and name params', () => {
        const route = router.getRoutes().find(r => r.name === 'character');

        expect(route.path).toBe('/character/:realm/:name');
    });

    it('has meta titles for all named routes', () => {
        const namedRoutes = router.getRoutes().filter(r => r.name);

        for (const route of namedRoutes) {
            expect(route.meta.title).toBeTruthy();
        }
    });

    it('catch-all route redirects to home', () => {
        const catchAll = router.getRoutes().find(r => r.path === '/:pathMatch(.*)*');

        expect(catchAll.redirect).toBe('/');
    });

    it('scrollBehavior returns savedPosition when available', () => {
        const savedPosition = { left: 0, top: 200 };
        const result = router.options.scrollBehavior({}, {}, savedPosition);

        expect(result).toEqual(savedPosition);
    });

    it('scrollBehavior returns top 0 when no savedPosition', () => {
        const result = router.options.scrollBehavior({}, {}, null);

        expect(result).toEqual({ top: 0 });
    });

    it('afterEach updates document.title from route meta', async () => {
        document.title = '';

        await router.push('/');
        await router.isReady();

        expect(document.title).toBe('WowPlanet - Suivi de progression World of Warcraft');
    });
});
