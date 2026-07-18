import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/base-de-donnees/garde-robe', props: {} }),
    router: { get: vi.fn(), visit: vi.fn(), on: vi.fn() },
}));

import { router } from '@inertiajs/vue3';
import DatabaseTransmogPage from './DatabaseTransmogPage.vue';
import { mountWithPlugins } from '../tests/helpers';

const meta = {
    title: 'Garde-robe WoW | WowPlanet',
    description: 'Garde-robe',
    ogTitle: 'Garde-robe', ogDescription: 'Garde-robe', ogImage: '', ogUrl: '', ogType: 'website',
    canonicalUrl: 'https://example.com/base-de-donnees/garde-robe', jsonLd: null,
};

const items = [
    { id: 1, name_fr: 'Casque de guerre', slot: 'HEAD', category: 'Plaques', icon_url: '', item_id: 100 },
    { id: 2, name_fr: 'Cape royale', slot: 'CLOAK', category: 'Tissu', icon_url: '', item_id: 101 },
];

const slots = [{ slug: 'head', name: 'HEAD', count: 200 }];

function mountPage(props = {}) {
    return mountWithPlugins(DatabaseTransmogPage, {
        props: {
            meta, slot: null, search: null, items, slots,
            total: 2, current_page: 1, last_page: 3, ...props,
        },
        stubs: { SearchFilter: true, CollectionIcon: true },
    });
}

describe('DatabaseTransmogPage', () => {
    beforeEach(() => vi.clearAllMocks());

    it('renders heading and items with FR slot labels', async () => {
        const wrapper = await mountPage();
        expect(wrapper.text()).toContain('Garde-robe');
        expect(wrapper.text()).toContain('Casque de guerre');
        expect(wrapper.text()).toContain('Tête');
    });

    it('triggers a partial Inertia visit on page change', async () => {
        const wrapper = await mountPage();
        wrapper.vm.onPageChange(2);

        expect(router.get).toHaveBeenCalledWith(
            '/base-de-donnees/garde-robe',
            expect.objectContaining({ page: 2 }),
            expect.objectContaining({ preserveState: true, only: expect.arrayContaining(['slots']) }),
        );
    });

    it('triggers a partial Inertia visit on debounced search', async () => {
        const wrapper = await mountPage();
        wrapper.vm.onSearchDebounced('casque');

        expect(router.get).toHaveBeenCalledWith(
            '/base-de-donnees/garde-robe',
            expect.objectContaining({ page: 1, search: 'casque' }),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows empty state when no items', async () => {
        const wrapper = await mountPage({ items: [], total: 0, last_page: 1 });
        expect(wrapper.text()).toContain('Aucun résultat trouvé');
    });
});
