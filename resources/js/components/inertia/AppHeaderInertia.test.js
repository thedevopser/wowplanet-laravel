import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');
    const page = reactive({ url: '/', props: {} });

    return {
        __page: page,
        Head: { name: 'Head', render: () => null },
        Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
        usePage: () => page,
        router: { visit: vi.fn(), on: vi.fn(() => () => {}) },
    };
});

import { __page } from '@inertiajs/vue3';
import { useCharacterStore } from '../../stores/character';
import { mountWithPlugins } from '../../tests/helpers';
import AppHeaderInertia from './AppHeaderInertia.vue';

const hrefs = wrapper => wrapper.findAll('a').map(a => a.attributes('href'));

const mountHeader = (state = {}) => mountWithPlugins(AppHeaderInertia, {
    initialState: { character: state },
});

beforeEach(() => {
    __page.url = '/';
});

describe('AppHeaderInertia', () => {
    it('links to the public sections', async () => {
        const wrapper = await mountHeader();

        expect(hrefs(wrapper)).toEqual(expect.arrayContaining(['/', '/base-de-donnees', '/classements-pvp']));
    });

    it('offers the login link to an anonymous visitor', async () => {
        const wrapper = await mountHeader();

        expect(hrefs(wrapper)).toContain('/auth/blizzard/redirect');
        expect(wrapper.text()).not.toContain('Déconnexion');
    });

    it('hides the member sections from an anonymous visitor', async () => {
        const wrapper = await mountHeader();

        expect(hrefs(wrapper)).not.toContain('/my-characters');
        expect(hrefs(wrapper)).not.toContain('/my-score');
    });

    it('shows the member sections to an authenticated visitor', async () => {
        const wrapper = await mountHeader({ isAuthenticated: true });

        expect(hrefs(wrapper)).toEqual(expect.arrayContaining(['/my-characters', '/class-stats', '/my-score']));
        expect(hrefs(wrapper)).not.toContain('/auth/blizzard/redirect');
    });

    it('reserves the administration link to an administrator', async () => {
        const member = await mountHeader({ isAuthenticated: true });
        const admin = await mountHeader({ isAuthenticated: true, isAdmin: true });

        expect(hrefs(member)).not.toContain('/admin');
        expect(hrefs(admin)).toContain('/admin');
    });

    it('logs out then reloads the home page', async () => {
        const wrapper = await mountHeader({ isAuthenticated: true });
        const store = useCharacterStore(wrapper.vm.$pinia);
        const location = { href: '/my-characters' };
        vi.spyOn(window, 'location', 'get').mockReturnValue(location);

        await wrapper.findAll('button').find(b => b.text() === 'Déconnexion').trigger('click');

        expect(store.logout).toHaveBeenCalled();
        expect(location.href).toBe('/');
    });

    it('toggles the theme from the header', async () => {
        const wrapper = await mountHeader();
        const store = useCharacterStore(wrapper.vm.$pinia);

        await wrapper.find('button[aria-label="Passer en mode clair"]').trigger('click');

        expect(store.toggleTheme).toHaveBeenCalled();
    });

    it('labels the theme button according to the current theme', async () => {
        const dark = await mountHeader({ theme: 'dark' });
        const light = await mountHeader({ theme: 'light' });

        expect(dark.find('button[aria-label="Passer en mode clair"]').exists()).toBe(true);
        expect(light.find('button[aria-label="Passer en mode sombre"]').exists()).toBe(true);
    });

    it('opens and closes the mobile menu', async () => {
        const wrapper = await mountHeader();
        const toggle = () => wrapper.find('button[aria-label="Ouvrir le menu"], button[aria-label="Fermer le menu"]');

        expect(toggle().attributes('aria-expanded')).toBe('false');

        await toggle().trigger('click');
        expect(toggle().attributes('aria-expanded')).toBe('true');

        await toggle().trigger('click');
        expect(toggle().attributes('aria-expanded')).toBe('false');
    });

    it('closes the mobile menu when the page changes', async () => {
        const wrapper = await mountHeader();
        const toggle = () => wrapper.find('button[aria-label="Ouvrir le menu"], button[aria-label="Fermer le menu"]');

        await toggle().trigger('click');
        expect(toggle().attributes('aria-expanded')).toBe('true');

        __page.url = '/base-de-donnees';
        await wrapper.vm.$nextTick();

        expect(toggle().attributes('aria-expanded')).toBe('false');
    });

    it('keeps the mobile menu open when only the query string changes', async () => {
        const wrapper = await mountHeader();
        const toggle = () => wrapper.find('button[aria-label="Ouvrir le menu"], button[aria-label="Fermer le menu"]');

        await toggle().trigger('click');

        __page.url = '/?page=2';
        await wrapper.vm.$nextTick();

        expect(toggle().attributes('aria-expanded')).toBe('true');
    });

    it('highlights the section matching the current page', async () => {
        __page.url = '/base-de-donnees/montures';

        const wrapper = await mountHeader();
        const link = wrapper.findAll('a').find(a => a.attributes('href') === '/base-de-donnees');

        expect(link.classes().join(' ')).not.toContain('text-slate-400');
    });
});
