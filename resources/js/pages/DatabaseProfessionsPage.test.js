import { describe, it, expect, vi, beforeEach } from 'vitest';

let pageUrl = '/base-de-donnees/professions';

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: pageUrl, props: {} }),
    router: { get: vi.fn(), visit: vi.fn(), on: vi.fn() },
}));

import { router } from '@inertiajs/vue3';
import DatabaseProfessionsPage from './DatabaseProfessionsPage.vue';
import { mountWithPlugins } from '../tests/helpers';

const meta = {
    title: 'Professions WoW | WowPlanet',
    description: 'Professions',
    ogTitle: 'Professions', ogDescription: 'Professions', ogImage: '', ogUrl: '', ogType: 'website',
    canonicalUrl: 'https://example.com/base-de-donnees/professions', jsonLd: null,
};

const professions = [
    { id: 1, name_fr: 'Alchimie', type: 'primary', slug: 'alchimie', recipe_count: 200 },
    { id: 2, name_fr: 'Cuisine', type: 'secondary', slug: 'cuisine', recipe_count: 80 },
];

const recipes = {
    items: [
        { id: 1, name_fr: 'Potion de soin', category_name: 'Potions', faction: null, wowhead_spell_id: 500 },
        { id: 2, name_fr: 'Élixir de force', category_name: 'Élixirs', faction: 'Alliance', wowhead_spell_id: 501 },
    ],
    expansions: [{ id: 10, name: 'The War Within', slug: 'the-war-within', count: 40 }],
    profession: { id: 1, name_fr: 'Alchimie', type: 'primary' },
    total: 2,
    current_page: 1,
    last_page: 3,
};

function mountList() {
    pageUrl = '/base-de-donnees/professions';
    return mountWithPlugins(DatabaseProfessionsPage, {
        props: { meta, profession: null, expansion: null, search: null, professions, total_recipes: 280, recipes: null },
        stubs: { SearchFilter: true },
    });
}

function mountDetail(props = {}) {
    pageUrl = '/base-de-donnees/professions/alchimie';
    return mountWithPlugins(DatabaseProfessionsPage, {
        props: { meta, profession: 'alchimie', expansion: null, search: null, professions, total_recipes: 280, recipes, ...props },
        stubs: { SearchFilter: true },
    });
}

describe('DatabaseProfessionsPage', () => {
    beforeEach(() => vi.clearAllMocks());

    it('lists primary and secondary professions with links', async () => {
        const wrapper = await mountList();
        expect(wrapper.text()).toContain('Professions principales');
        expect(wrapper.text()).toContain('Alchimie');
        expect(wrapper.text()).toContain('Professions secondaires');
        expect(wrapper.text()).toContain('Cuisine');

        const hrefs = wrapper.findAll('a').map(l => l.attributes('href'));
        expect(hrefs).toContain('/base-de-donnees/professions/alchimie');
        expect(hrefs).toContain('/base-de-donnees/professions/cuisine');
    });

    it('renders recipes in detail mode', async () => {
        const wrapper = await mountDetail();
        expect(wrapper.text()).toContain('Potion de soin');
        expect(wrapper.text()).toContain('Élixir de force');
        expect(wrapper.text()).toContain('The War Within');
    });

    it('toggles expansion via a partial Inertia visit', async () => {
        const wrapper = await mountDetail();
        wrapper.vm.toggleExpansion('the-war-within');

        expect(router.get).toHaveBeenCalledWith(
            '/base-de-donnees/professions/alchimie',
            expect.objectContaining({ expansion: 'the-war-within', page: 1 }),
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('paginates recipes via a partial Inertia visit', async () => {
        const wrapper = await mountDetail();
        wrapper.vm.onPageChange(2);

        expect(router.get).toHaveBeenCalledWith(
            '/base-de-donnees/professions/alchimie',
            expect.objectContaining({ page: 2 }),
            expect.objectContaining({ only: expect.arrayContaining(['recipes']) }),
        );
    });

    it('searches recipes via a partial Inertia visit', async () => {
        const wrapper = await mountDetail();
        wrapper.vm.onSearchDebounced('potion');

        expect(router.get).toHaveBeenCalledWith(
            '/base-de-donnees/professions/alchimie',
            expect.objectContaining({ page: 1, search: 'potion' }),
            expect.objectContaining({ preserveScroll: true }),
        );
    });
});
