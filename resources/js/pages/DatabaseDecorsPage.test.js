import { describe, it, expect, vi, beforeEach } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { mountWithPlugins, createMockRouter } from '../tests/helpers';
import DatabaseDecorsPage from './DatabaseDecorsPage.vue';
import axios from 'axios';

vi.mock('axios');

const mockData = {
    items: [
        { id: 1, name_fr: 'Table en bois', source: 'Artisanat', icon_url: '', item_id: 300 },
        { id: 2, name_fr: 'Chaise dorée', source: 'Artisanat', icon_url: '', item_id: 301 },
        { id: 3, name_fr: 'Trophée de raid', source: 'Raid', icon_url: '', item_id: 302 },
    ],
    categories: [
        { slug: 'furniture', name: 'Mobilier', count: 100 },
        { slug: 'trophies', name: 'Trophées', count: 30 },
    ],
    total: 3200,
};

const routes = [
    { path: '/base-de-donnees/decorations/:category?', component: DatabaseDecorsPage },
    { path: '/base-de-donnees', component: { template: '<div/>' } },
];

describe('DatabaseDecorsPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockResolvedValue({ data: mockData });
    });

    it('renders heading "Décorations"', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseDecorsPage, {
            router,
            initialRoute: '/base-de-donnees/decorations',
            stubs: { BreadcrumbNav: true, SearchFilter: true, CollectionIcon: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Décorations');
    });

    it('fetches data and displays source groups', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseDecorsPage, {
            router,
            initialRoute: '/base-de-donnees/decorations',
            stubs: { BreadcrumbNav: true, SearchFilter: true, CollectionIcon: true },
        });
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/database/decors', { params: {} });
        expect(wrapper.text()).toContain('Artisanat');
        expect(wrapper.text()).toContain('Raid');
    });

    it('search filters items', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseDecorsPage, {
            router,
            initialRoute: '/base-de-donnees/decorations',
            stubs: { BreadcrumbNav: true, CollectionIcon: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Artisanat');
        expect(wrapper.text()).toContain('Raid');

        wrapper.vm.search = 'trophée';
        await flushPromises();

        expect(wrapper.text()).toContain('Raid');
        expect(wrapper.text()).not.toContain('Artisanat');
    });

    it('handles API error', async () => {
        axios.get.mockRejectedValue(new Error('Network error'));

        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseDecorsPage, {
            router,
            initialRoute: '/base-de-donnees/decorations',
            stubs: { BreadcrumbNav: true, SearchFilter: true, CollectionIcon: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Aucun résultat trouvé');
    });
});
