import { describe, it, expect, vi, beforeEach } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { mountWithPlugins, createMockRouter } from '../tests/helpers';
import DatabasePetsPage from './DatabasePetsPage.vue';
import axios from 'axios';

vi.mock('axios');

const mockData = {
    items: [
        { id: 1, name_fr: 'Louveteau noir', source: 'Vendeur', icon_url: '', creature_id: 200 },
        { id: 2, name_fr: 'Perroquet vert', source: 'Vendeur', icon_url: '', creature_id: 201 },
        { id: 3, name_fr: 'Whelpling rouge', source: 'Drop', icon_url: '', creature_id: 202 },
    ],
    categories: [
        { slug: 'aquatic', name: 'Aquatique', count: 40 },
        { slug: 'beast', name: 'Bête', count: 60 },
    ],
    total: 1800,
};

const routes = [
    { path: '/base-de-donnees/mascottes/:category?', component: DatabasePetsPage },
    { path: '/base-de-donnees', component: { template: '<div/>' } },
];

describe('DatabasePetsPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockResolvedValue({ data: mockData });
    });

    it('renders heading "Mascottes"', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabasePetsPage, {
            router,
            initialRoute: '/base-de-donnees/mascottes',
            stubs: { BreadcrumbNav: true, SearchFilter: true, CollectionIcon: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Mascottes');
    });

    it('fetches data and displays source groups', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabasePetsPage, {
            router,
            initialRoute: '/base-de-donnees/mascottes',
            stubs: { BreadcrumbNav: true, SearchFilter: true, CollectionIcon: true },
        });
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/database/pets', { params: {} });
        expect(wrapper.text()).toContain('Vendeur');
        expect(wrapper.text()).toContain('Drop');
    });

    it('search filters items', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabasePetsPage, {
            router,
            initialRoute: '/base-de-donnees/mascottes',
            stubs: { BreadcrumbNav: true, CollectionIcon: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Vendeur');
        expect(wrapper.text()).toContain('Drop');

        wrapper.vm.search = 'whelpling';
        await flushPromises();

        expect(wrapper.text()).toContain('Drop');
        expect(wrapper.text()).not.toContain('Vendeur');
    });

    it('handles API error', async () => {
        axios.get.mockRejectedValue(new Error('Network error'));

        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabasePetsPage, {
            router,
            initialRoute: '/base-de-donnees/mascottes',
            stubs: { BreadcrumbNav: true, SearchFilter: true, CollectionIcon: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Aucun résultat trouvé');
    });
});
