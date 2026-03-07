import { describe, it, expect, vi, beforeEach } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { mountWithPlugins, createMockRouter } from '../tests/helpers';
import DatabaseIndexPage from './DatabaseIndexPage.vue';
import axios from 'axios';

vi.mock('axios');

const mockCounts = {
    mounts: 942,
    achievements: 5123,
    quests: 24000,
    pets: 1800,
    decors: 3200,
    recipes: 15000,
    professions: 14,
};

const routes = [
    { path: '/base-de-donnees', component: DatabaseIndexPage },
    { path: '/base-de-donnees/montures', component: { template: '<div/>' } },
    { path: '/base-de-donnees/hauts-faits', component: { template: '<div/>' } },
    { path: '/base-de-donnees/quetes', component: { template: '<div/>' } },
    { path: '/base-de-donnees/mascottes', component: { template: '<div/>' } },
    { path: '/base-de-donnees/decorations', component: { template: '<div/>' } },
    { path: '/base-de-donnees/professions', component: { template: '<div/>' } },
];

describe('DatabaseIndexPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockResolvedValue({ data: mockCounts });
    });

    it('renders heading "Base de données WoW"', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseIndexPage, {
            router,
            initialRoute: '/base-de-donnees',
            stubs: { BreadcrumbNav: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Base de données WoW');
    });

    it('fetches counts on mount and displays them', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseIndexPage, {
            router,
            initialRoute: '/base-de-donnees',
            stubs: { BreadcrumbNav: true },
        });
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/database/counts');
        expect(wrapper.text()).toContain('942');
        // toLocaleString('fr-FR') uses narrow no-break space (U+202F) as thousands separator
        expect(wrapper.text()).toMatch(/1\s*800/);
    });

    it('renders 6 category links', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseIndexPage, {
            router,
            initialRoute: '/base-de-donnees',
            stubs: { BreadcrumbNav: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Montures');
        expect(wrapper.text()).toContain('Hauts-faits');
        expect(wrapper.text()).toContain('Quêtes');
        expect(wrapper.text()).toContain('Mascottes');
        expect(wrapper.text()).toContain('Décorations');
        expect(wrapper.text()).toContain('Professions');

        const links = wrapper.findAll('a');
        const hrefs = links.map(l => l.attributes('href'));
        expect(hrefs).toContain('/base-de-donnees/montures');
        expect(hrefs).toContain('/base-de-donnees/hauts-faits');
        expect(hrefs).toContain('/base-de-donnees/quetes');
        expect(hrefs).toContain('/base-de-donnees/mascottes');
        expect(hrefs).toContain('/base-de-donnees/decorations');
        expect(hrefs).toContain('/base-de-donnees/professions');
    });

    it('handles API error gracefully (items still render, no crash)', async () => {
        axios.get.mockRejectedValue(new Error('Network error'));

        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseIndexPage, {
            router,
            initialRoute: '/base-de-donnees',
            stubs: { BreadcrumbNav: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Montures');
        expect(wrapper.text()).toContain('Hauts-faits');
        expect(wrapper.text()).toContain('...');
    });
});
