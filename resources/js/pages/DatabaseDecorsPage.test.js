import { describe, it, expect, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/base-de-donnees/decorations', props: {} }),
    router: { get: vi.fn(), visit: vi.fn(), on: vi.fn() },
}));

import DatabaseDecorsPage from './DatabaseDecorsPage.vue';
import { mountWithPlugins } from '../tests/helpers';

const meta = {
    title: 'Décorations WoW | WowPlanet',
    description: 'Décorations',
    ogTitle: 'Décorations', ogDescription: 'Décorations', ogImage: '', ogUrl: '', ogType: 'website',
    canonicalUrl: 'https://example.com/base-de-donnees/decorations', jsonLd: null,
};

const items = [
    { id: 1, name_fr: 'Table ronde', source: 'Artisanat', icon_url: '', item_id: 100 },
    { id: 2, name_fr: 'Chaise dorée', source: 'Vendeur', icon_url: '', item_id: 101 },
];

const categories = [{ slug: 'furniture', name: 'Mobilier', count: 40 }];

function mountPage(props = {}) {
    return mountWithPlugins(DatabaseDecorsPage, {
        props: { meta, category: null, items, categories, total: 3200, ...props },
        stubs: { SearchFilter: true, CollectionIcon: true },
    });
}

describe('DatabaseDecorsPage', () => {
    it('renders heading and displays items from props', async () => {
        const wrapper = await mountPage();
        expect(wrapper.text()).toContain('Décorations');
        expect(wrapper.text()).toContain('Table ronde');
        expect(wrapper.text()).toContain('Chaise dorée');
    });

    it('search filters items by name', async () => {
        const wrapper = await mountPage();
        wrapper.vm.search = 'chaise';
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Chaise dorée');
        expect(wrapper.text()).not.toContain('Table ronde');
    });

    it('shows empty state when no items', async () => {
        const wrapper = await mountPage({ items: [], total: 0 });
        expect(wrapper.text()).toContain('Aucun résultat trouvé');
    });
});
