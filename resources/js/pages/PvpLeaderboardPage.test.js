import { vi, describe, it, expect, beforeEach } from 'vitest';

// vi.mock est hissé en tête de fichier : le spy doit l'être aussi.
const { routerGet } = vi.hoisted(() => ({ routerGet: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    Head: { name: 'Head', render: () => null },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: () => ({ url: '/classements-pvp/3v3', props: {} }),
    router: { get: routerGet, visit: vi.fn(), on: vi.fn() },
}));

import { mountWithPlugins } from '../tests/helpers';
import PvpLeaderboardPage from './PvpLeaderboardPage.vue';

const meta = {
    title: 'Classements PvP | WowPlanet',
    description: 'Classements PvP officiels',
    canonicalUrl: 'https://wowplanet.fr/classements-pvp',
    ogType: 'website',
    ogTitle: 'Classements PvP',
    ogDescription: 'Classements PvP officiels',
    ogImage: 'https://wowplanet.fr/images/og-default.png',
    ogUrl: 'https://wowplanet.fr/classements-pvp',
};

const groups = [
    {
        key: 'arena',
        label: 'Arène',
        brackets: [
            { slug: '2v2', label: 'Arène 2c2', short: 'Arène 2c2' },
            { slug: '3v3', label: 'Arène 3c3', short: 'Arène 3c3' },
        ],
    },
    {
        key: 'shuffle',
        label: 'Mêlée solo',
        // 40 brackets comme en production : le second niveau doit passer en liste déroulante.
        brackets: [
            { slug: 'shuffle-overall', label: 'Mêlée solo — toutes spés', short: 'Toutes spés' },
            ...Array.from({ length: 39 }, (_, i) => ({
                slug: `shuffle-spec-${i}`,
                label: `Mêlée solo — Spé ${i}`,
                short: `Spé ${i}`,
            })),
        ],
    },
];

const entries = [
    { rank: 1, name: 'Thrall', realm: 'Hyjal', realm_slug: 'hyjal', faction: 'HORDE', rating: 2999, won: 70, lost: 30 },
    { rank: 2, name: 'Jaina', realm: 'Dalaran', realm_slug: 'dalaran', faction: 'ALLIANCE', rating: 2950, won: 60, lost: 40 },
];

function mountPage(props = {}) {
    return mountWithPlugins(PvpLeaderboardPage, {
        props: {
            meta,
            groups,
            entries,
            bracket: '3v3',
            label: 'Arène 3c3',
            seasonId: 40,
            total: 2,
            currentPage: 1,
            lastPage: 1,
            unavailable: false,
            search: null,
            ...props,
        },
    });
}

describe('PvpLeaderboardPage', () => {
    beforeEach(() => {
        routerGet.mockClear();
    });

    it('renders one row per leaderboard entry', async () => {
        const wrapper = await mountPage();

        const rows = wrapper.findAll('[data-testid^="pvp-rank-"]');
        expect(rows).toHaveLength(2);
        expect(rows[0].text()).toContain('Thrall');
        expect(rows[0].text()).toContain('Hyjal');
        expect(rows[0].text()).toContain('2999');
        expect(rows[0].text()).toContain('70');
    });

    it('links each entry to its character page', async () => {
        const wrapper = await mountPage();

        expect(wrapper.find('[data-testid="pvp-rank-1"] a').attributes('href'))
            .toBe('/character/hyjal/thrall');
    });

    it('shows one tab per mode and only the active mode brackets', async () => {
        const wrapper = await mountPage();

        expect(wrapper.findAll('[data-testid^="pvp-mode-"]')).toHaveLength(2);
        expect(wrapper.find('[data-testid="pvp-mode-arena"]').classes().join(' ')).toContain('bg-amber');

        // Mode arène actif : ses deux brackets en boutons, aucun bracket de mêlée solo.
        expect(wrapper.findAll('[data-testid^="pvp-bracket-option-"]')).toHaveLength(2);
        expect(wrapper.find('[data-testid="pvp-bracket-select"]').exists()).toBe(false);
    });

    it('falls back to a dropdown when a mode has too many brackets', async () => {
        const wrapper = await mountPage({ bracket: 'shuffle-overall', label: 'Mêlée solo — toutes spés' });

        expect(wrapper.find('[data-testid="pvp-mode-shuffle"]').classes().join(' ')).toContain('bg-amber');
        expect(wrapper.findAll('[data-testid^="pvp-bracket-option-"]')).toHaveLength(0);

        const select = wrapper.find('[data-testid="pvp-bracket-select"]');
        expect(select.exists()).toBe(true);
        expect(select.findAll('option')).toHaveLength(40);
    });

    it('navigates when another bracket is selected', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="pvp-bracket-option-2v2"]').trigger('click');

        expect(routerGet).toHaveBeenCalledWith(
            '/classements-pvp/2v2',
            {},
            expect.objectContaining({ preserveState: false }),
        );
    });

    it('navigates when a specialization is picked in the dropdown', async () => {
        const wrapper = await mountPage({ bracket: 'shuffle-overall' });

        const select = wrapper.find('[data-testid="pvp-bracket-select"]');
        select.element.value = 'shuffle-spec-3';
        await select.trigger('change');

        expect(routerGet).toHaveBeenCalledWith(
            '/classements-pvp/shuffle-spec-3',
            {},
            expect.objectContaining({ preserveState: false }),
        );
    });

    it('switching mode loads that mode default bracket', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="pvp-mode-shuffle"]').trigger('click');

        expect(routerGet).toHaveBeenCalledWith(
            '/classements-pvp/shuffle-overall',
            {},
            expect.objectContaining({ preserveState: false }),
        );
    });

    it('does not navigate when clicking the already active mode', async () => {
        const wrapper = await mountPage();

        await wrapper.find('[data-testid="pvp-mode-arena"]').trigger('click');

        expect(routerGet).not.toHaveBeenCalled();
    });

    it('requests the new page on pagination', async () => {
        const wrapper = await mountPage({ lastPage: 4, total: 200 });

        wrapper.findComponent({ name: 'DatabasePagination' }).vm.$emit('page-change', 3);
        await wrapper.vm.$nextTick();

        expect(routerGet).toHaveBeenCalledWith(
            '/classements-pvp/3v3',
            expect.objectContaining({ page: 3 }),
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('requests a filtered page on debounced search', async () => {
        const wrapper = await mountPage();

        wrapper.findComponent({ name: 'SearchFilter' }).vm.$emit('search-debounced', 'thrall');
        await wrapper.vm.$nextTick();

        expect(routerGet).toHaveBeenCalledWith(
            '/classements-pvp/3v3',
            expect.objectContaining({ page: 1, search: 'thrall' }),
            expect.objectContaining({ preserveState: true }),
        );
    });

    it('shows the unavailable state', async () => {
        const wrapper = await mountPage({ unavailable: true, entries: [], total: 0 });

        expect(wrapper.text()).toContain('indisponible');
        expect(wrapper.findAll('[data-testid^="pvp-rank-"]')).toHaveLength(0);
    });

    it('shows an empty state when the search matches nothing', async () => {
        const wrapper = await mountPage({ entries: [], total: 0, search: 'personne' });

        expect(wrapper.text()).toContain('Aucun résultat');
    });
});
