import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/base-de-donnees/hauts-faits', props: {} }),
    router: { get: vi.fn(), visit: vi.fn(), on: vi.fn() },
}));

import { router } from '@inertiajs/vue3';
import DatabaseAchievementsPage from './DatabaseAchievementsPage.vue';
import { mountWithPlugins } from '../tests/helpers';

const meta = {
    title: 'Hauts-faits WoW | WowPlanet',
    description: 'Hauts-faits',
    ogTitle: 'Hauts-faits', ogDescription: 'Hauts-faits', ogImage: '', ogUrl: '', ogType: 'website',
    canonicalUrl: 'https://example.com/base-de-donnees/hauts-faits', jsonLd: null,
};

const items = [
    { id: 1, name_fr: 'Explorateur', category_name: 'Exploration', icon_url: '', points: 10 },
    { id: 2, name_fr: 'Vétéran', category_name: 'PvP', icon_url: '', points: 25 },
];

const expansions = [{ id: 10, name: 'The War Within', slug: 'the-war-within', count: 500 }];

function mountPage(props = {}) {
    return mountWithPlugins(DatabaseAchievementsPage, {
        props: {
            meta, expansion: null, search: null, items, expansions,
            total: 2, current_page: 1, last_page: 3, ...props,
        },
        stubs: { SearchFilter: true, CollectionIcon: true },
    });
}

describe('DatabaseAchievementsPage', () => {
    beforeEach(() => vi.clearAllMocks());

    it('renders heading and items from props', async () => {
        const wrapper = await mountPage();
        expect(wrapper.text()).toContain('Hauts-faits');
        expect(wrapper.text()).toContain('Explorateur');
        expect(wrapper.text()).toContain('Vétéran');
    });

    it('triggers a partial Inertia visit on page change', async () => {
        const wrapper = await mountPage();
        wrapper.vm.onPageChange(2);

        expect(router.get).toHaveBeenCalledWith(
            '/base-de-donnees/hauts-faits',
            expect.objectContaining({ page: 2 }),
            expect.objectContaining({ preserveState: true, only: expect.arrayContaining(['items']) }),
        );
    });

    it('triggers a partial Inertia visit on debounced search', async () => {
        const wrapper = await mountPage();
        wrapper.vm.onSearchDebounced('explo');

        expect(router.get).toHaveBeenCalledWith(
            '/base-de-donnees/hauts-faits',
            expect.objectContaining({ page: 1, search: 'explo' }),
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('shows empty state when no items', async () => {
        const wrapper = await mountPage({ items: [], total: 0, last_page: 1 });
        expect(wrapper.text()).toContain('Aucun résultat trouvé');
    });
});
