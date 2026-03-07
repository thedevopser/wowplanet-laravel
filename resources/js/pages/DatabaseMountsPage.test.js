import { describe, it, expect, vi, beforeEach } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { mountWithPlugins, createMockRouter } from '../tests/helpers';
import DatabaseMountsPage from './DatabaseMountsPage.vue';
import axios from 'axios';

vi.mock('axios');

const mockData = {
    items: [
        { id: 1, name_fr: 'Destrier noir', source: 'Vendeur', icon_url: '', source_spell_id: 100 },
        { id: 2, name_fr: 'Loup rapide', source: 'Vendeur', icon_url: '', source_spell_id: 101 },
        { id: 3, name_fr: 'Dragon rouge', source: 'Raid', icon_url: '', source_spell_id: 102 },
    ],
    categories: [
        { slug: 'flying', name: 'Volantes', count: 50 },
        { slug: 'ground', name: 'Terrestres', count: 80 },
    ],
    total: 942,
};

const routes = [
    { path: '/base-de-donnees/montures/:category?', component: DatabaseMountsPage },
    { path: '/base-de-donnees', component: { template: '<div/>' } },
];

describe('DatabaseMountsPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockResolvedValue({ data: mockData });
    });

    it('renders heading "Montures" and total count', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseMountsPage, {
            router,
            initialRoute: '/base-de-donnees/montures',
            stubs: { BreadcrumbNav: true, SearchFilter: true, CollectionIcon: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Montures');
        expect(wrapper.text()).toContain('942');
    });

    it('fetches data on mount', async () => {
        const router = createMockRouter({ routes });
        await mountWithPlugins(DatabaseMountsPage, {
            router,
            initialRoute: '/base-de-donnees/montures',
            stubs: { BreadcrumbNav: true, SearchFilter: true, CollectionIcon: true },
        });
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/database/mounts', { params: {} });
    });

    it('displays source groups from items', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseMountsPage, {
            router,
            initialRoute: '/base-de-donnees/montures',
            stubs: { BreadcrumbNav: true, SearchFilter: true, CollectionIcon: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Vendeur');
        expect(wrapper.text()).toContain('Raid');
    });

    it('search filters items by name', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseMountsPage, {
            router,
            initialRoute: '/base-de-donnees/montures',
            stubs: { BreadcrumbNav: true, CollectionIcon: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Vendeur');
        expect(wrapper.text()).toContain('Raid');

        wrapper.vm.search = 'dragon';
        await flushPromises();

        expect(wrapper.text()).toContain('Raid');
        expect(wrapper.text()).not.toContain('Vendeur');
    });

    it('handles API error (items = [])', async () => {
        axios.get.mockRejectedValue(new Error('Network error'));

        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseMountsPage, {
            router,
            initialRoute: '/base-de-donnees/montures',
            stubs: { BreadcrumbNav: true, SearchFilter: true, CollectionIcon: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Aucun résultat trouvé');
    });
});
