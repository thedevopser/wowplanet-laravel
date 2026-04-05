import { describe, it, expect, vi, beforeEach } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { mountWithPlugins, createMockRouter } from '../tests/helpers';
import DatabaseAchievementsPage from './DatabaseAchievementsPage.vue';
import axios from 'axios';

vi.mock('axios');

const mockData = {
    items: [
        { id: 1, name_fr: 'Explorateur', category_name: 'Exploration', icon_url: '', points: 10 },
        { id: 2, name_fr: 'Chef cuisinier', category_name: 'Exploration', icon_url: '', points: 10 },
        { id: 3, name_fr: 'Gladiateur', category_name: 'JcJ', icon_url: '', points: 25 },
    ],
    expansions: [
        { slug: 'classic', name: 'Classic', count: 500 },
        { slug: 'the-war-within', name: 'The War Within', count: 200 },
    ],
    total: 5123,
    current_page: 1,
    last_page: 103,
    per_page: 50,
};

const routes = [
    { path: '/base-de-donnees/hauts-faits/:expansion?', component: DatabaseAchievementsPage },
    { path: '/base-de-donnees', component: { template: '<div/>' } },
];

describe('DatabaseAchievementsPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockResolvedValue({ data: mockData });
    });

    it('renders heading "Hauts-faits" and total count', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseAchievementsPage, {
            router,
            initialRoute: '/base-de-donnees/hauts-faits',
            stubs: { BreadcrumbNav: true, SearchFilter: true, CollectionIcon: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Hauts-faits');
        // toLocaleString('fr-FR') uses narrow no-break space (U+202F) as thousands separator
        expect(wrapper.text()).toMatch(/5\s*123/);
    });

    it('fetches data and displays category groups', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseAchievementsPage, {
            router,
            initialRoute: '/base-de-donnees/hauts-faits',
            stubs: { BreadcrumbNav: true, SearchFilter: true, CollectionIcon: true },
        });
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/database/achievements', { params: { page: 1 } });
        expect(wrapper.text()).toContain('Exploration');
        expect(wrapper.text()).toContain('JcJ');
    });

    it('search triggers server-side fetch', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseAchievementsPage, {
            router,
            initialRoute: '/base-de-donnees/hauts-faits',
            stubs: { BreadcrumbNav: true, CollectionIcon: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Exploration');
        expect(wrapper.text()).toContain('JcJ');

        // Simulate debounced search event
        wrapper.vm.onSearchDebounced('gladiateur');
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/database/achievements', {
            params: { page: 1, search: 'gladiateur' },
        });
    });

    it('handles API error', async () => {
        axios.get.mockRejectedValue(new Error('Network error'));

        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseAchievementsPage, {
            router,
            initialRoute: '/base-de-donnees/hauts-faits',
            stubs: { BreadcrumbNav: true, SearchFilter: true, CollectionIcon: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Aucun résultat trouvé');
    });
});
