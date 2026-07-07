import { describe, it, expect, vi, beforeEach } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { mountWithPlugins, createMockRouter } from '../tests/helpers';
import DatabaseTransmogPage from './DatabaseTransmogPage.vue';
import axios from 'axios';

vi.mock('axios');

const mockData = {
    items: [
        { id: 1, name_fr: 'Casque épique', slot: 'HEAD', category: 'Armure', quality: 4, item_id: 111 },
        { id: 2, name_fr: 'Épée runique', slot: 'WEAPON', category: 'Arme', quality: 4, item_id: 222 },
    ],
    slots: [
        { name: 'HEAD', slug: 'head', count: 5000 },
        { name: 'WEAPON', slug: 'weapon', count: 5200 },
    ],
    total: 52000,
    current_page: 1,
    last_page: 1040,
    per_page: 50,
};

const routes = [
    { path: '/base-de-donnees/garde-robe/:slot?', component: DatabaseTransmogPage },
    { path: '/base-de-donnees', component: { template: '<div/>' } },
];

describe('DatabaseTransmogPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        axios.get.mockResolvedValue({ data: mockData });
    });

    it('renders heading and total count', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseTransmogPage, {
            router,
            initialRoute: '/base-de-donnees/garde-robe',
            stubs: { SearchFilter: true, CollectionIcon: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Garde-robe');
        expect(wrapper.text()).toMatch(/52\s*000/);
    });

    it('fetches appearances and renders wowhead item links', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseTransmogPage, {
            router,
            initialRoute: '/base-de-donnees/garde-robe',
            stubs: { SearchFilter: true, CollectionIcon: true },
        });
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/database/appearances', { params: { page: 1 } });
        expect(wrapper.text()).toContain('Casque épique');
        expect(wrapper.html()).toContain('wowhead.com/fr/item=111');
    });

    it('search triggers server-side fetch', async () => {
        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseTransmogPage, {
            router,
            initialRoute: '/base-de-donnees/garde-robe',
            stubs: { SearchFilter: true, CollectionIcon: true },
        });
        await flushPromises();

        wrapper.vm.onSearchDebounced('casque');
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/api/database/appearances', {
            params: { page: 1, search: 'casque' },
        });
    });

    it('handles API error', async () => {
        axios.get.mockRejectedValue(new Error('Network error'));

        const router = createMockRouter({ routes });
        const wrapper = await mountWithPlugins(DatabaseTransmogPage, {
            router,
            initialRoute: '/base-de-donnees/garde-robe',
            stubs: { SearchFilter: true, CollectionIcon: true },
        });
        await flushPromises();

        expect(wrapper.text()).toContain('Aucun résultat trouvé');
    });
});
