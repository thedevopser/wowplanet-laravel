import { describe, it, expect, vi, beforeEach } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { mountWithPlugins, createMockRouter } from '../tests/helpers';
import DatabaseQuestsPage from './DatabaseQuestsPage.vue';
import axios from 'axios';

vi.mock('axios');

const mockData = {
    items: [
        { id: 1, name_fr: 'La menace grondante', zone_name: 'Durotar', faction: 'Horde' },
        { id: 2, name_fr: 'Bienvenue à Hurlevent', zone_name: 'Forêt d\'Elwynn', faction: 'Alliance' },
        { id: 3, name_fr: 'Chasse au sanglier', zone_name: 'Durotar', faction: null },
    ],
    expansions: [
        { slug: 'classic', name: 'Classic', count: 3000 },
        { slug: 'the-war-within', name: 'The War Within', count: 800 },
    ],
    zones: [],
    total: 24000,
    current_page: 1,
    last_page: 480,
    per_page: 50,
};

const routes = [
    { path: '/base-de-donnees/quetes/:expansion?/:zone?', component: DatabaseQuestsPage },
    { path: '/base-de-donnees', component: { template: '<div/>' } },
];

describe('DatabaseQuestsPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockResolvedValue({ data: mockData });
    });

    it('renders heading "Quêtes"', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseQuestsPage, {
            router,
            initialRoute: '/base-de-donnees/quetes',
            stubs: { BreadcrumbNav: true, SearchFilter: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Quêtes');
    });

    it('fetches data with no expansion', async () => {
        const router = createMockRouter({ routes });
        await mountWithPlugins(DatabaseQuestsPage, {
            router,
            initialRoute: '/base-de-donnees/quetes',
            stubs: { BreadcrumbNav: true, SearchFilter: true },
        });
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/database/quests', { params: { page: 1 } });
    });

    it('displays zone groups when data has items with zone_name', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseQuestsPage, {
            router,
            initialRoute: '/base-de-donnees/quetes',
            stubs: { BreadcrumbNav: true, SearchFilter: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Durotar');
        expect(wrapper.text()).toContain('Forêt d\'Elwynn');
    });

    it('search triggers server-side fetch', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseQuestsPage, {
            router,
            initialRoute: '/base-de-donnees/quetes',
            stubs: { BreadcrumbNav: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Durotar');
        expect(wrapper.text()).toContain('Forêt d\'Elwynn');

        // Simulate debounced search event
        wrapper.vm.onSearchDebounced('hurlevent');
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/database/quests', {
            params: { page: 1, search: 'hurlevent' },
        });
    });

    it('handles API error', async () => {
        axios.get.mockRejectedValue(new Error('Network error'));

        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseQuestsPage, {
            router,
            initialRoute: '/base-de-donnees/quetes',
            stubs: { BreadcrumbNav: true, SearchFilter: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Aucun résultat trouvé');
    });
});
