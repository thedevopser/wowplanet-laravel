import { describe, it, expect } from 'vitest';
import { mountWithPlugins } from '../tests/helpers';
import ReputationsTab from './ReputationsTab.vue';

const characterData = {
    name: 'Arthas',
    collections: {
        10: {
            quests: { completed: 25, total: 50 },
            achievements: { completed: 8, total: 20, categories: [] },
            reputations: {
                completed: 2,
                total: 5,
                factions: [
                    { id: 2600, name: 'Council of Dornogal', standing_name: 'Exalté', tier: 7, value: 0, max: 0, raw: 42999, renown_level: 0, completed: true },
                    { id: 2601, name: 'Assemblée des Profondeurs', standing_name: 'Honoré', tier: 5, value: 5000, max: 12000, raw: 14000, renown_level: 0, completed: false },
                    { id: 2602, name: 'Artisans de Dornogal', standing_name: 'Renom 25', tier: 0, value: 0, max: 2500, raw: 62500, renown_level: 25, completed: true },
                    { id: 2603, name: 'Bêtes de Khaz Algar', standing_name: 'Révéré', tier: 6, value: 8000, max: 21000, raw: 35000, renown_level: 0, completed: false },
                    { id: 2604, name: 'Explorateurs de Dornogal', standing_name: 'Renom 10', tier: 0, value: 1200, max: 2500, raw: 25000, renown_level: 10, completed: false },
                ],
            },
        },
    },
};

describe('ReputationsTab', () => {
    it('renders the reputations heading', async () => {
        const wrapper = await mountWithPlugins(ReputationsTab, {
            initialState: { character: { character: characterData } },
        });

        expect(wrapper.text()).toContain('Réputations');
        expect(wrapper.text()).toContain('Progression auprès des factions');
    });

    it('displays progress counter', async () => {
        const wrapper = await mountWithPlugins(ReputationsTab, {
            initialState: { character: { character: characterData } },
        });

        expect(wrapper.text()).toContain('2 / 5 terminé');
    });

    it('displays faction names sorted alphabetically', async () => {
        const wrapper = await mountWithPlugins(ReputationsTab, {
            initialState: { character: { character: characterData } },
        });

        const factionLinks = wrapper.findAll('a[target="_blank"]');
        const names = factionLinks.map(el => el.text());
        expect(names[0]).toBe('Artisans de Dornogal');
        expect(names[1]).toBe('Assemblée des Profondeurs');
        expect(names[2]).toBe('Bêtes de Khaz Algar');
    });

    it('displays standing badges', async () => {
        const wrapper = await mountWithPlugins(ReputationsTab, {
            initialState: { character: { character: characterData } },
        });

        expect(wrapper.text()).toContain('Exalté');
        expect(wrapper.text()).toContain('Honoré');
        expect(wrapper.text()).toContain('Renom 25');
    });

    it('hides progress bar for exalted factions (max === 0)', async () => {
        const wrapper = await mountWithPlugins(ReputationsTab, {
            initialState: { character: { character: characterData } },
        });

        // Exalté faction (tier 7, max 0) should not show progress values
        expect(wrapper.text()).not.toContain('0 / 0');
    });

    it('hides progress bar for max renown (value === 0)', async () => {
        const data = {
            ...characterData,
            collections: {
                10: {
                    ...characterData.collections[10],
                    reputations: {
                        completed: 1,
                        total: 1,
                        factions: [
                            { id: 2503, name: 'Centaure maruuk', standing_name: 'Renom 25', tier: 0, value: 0, max: 2500, raw: 62500, renown_level: 25, completed: true },
                        ],
                    },
                },
            },
        };

        const wrapper = await mountWithPlugins(ReputationsTab, {
            initialState: { character: { character: data } },
        });

        // Should not show 0/2500 for max renown (locale may use non-breaking spaces)
        expect(wrapper.text()).not.toMatch(/2\s*500/);
    });

    it('shows progress bar for in-progress factions', async () => {
        const wrapper = await mountWithPlugins(ReputationsTab, {
            initialState: { character: { character: characterData } },
        });

        // Honoré faction with 5000/12000 should show progress (locale may use non-breaking spaces)
        expect(wrapper.text()).toMatch(/12\s*000/);
    });

    it('shows progress bar for in-progress renown factions', async () => {
        const wrapper = await mountWithPlugins(ReputationsTab, {
            initialState: { character: { character: characterData } },
        });

        // Renom 10 faction with 1200/2500 should show progress
        expect(wrapper.text()).toMatch(/1\s*200/);
    });

    it('uses amber color for completed renown factions', async () => {
        const data = {
            ...characterData,
            collections: {
                10: {
                    ...characterData.collections[10],
                    reputations: {
                        completed: 1,
                        total: 1,
                        factions: [
                            { id: 2503, name: 'Centaure maruuk', standing_name: 'Renom 25', tier: 0, value: 0, max: 2500, raw: 62500, renown_level: 25, completed: true },
                        ],
                    },
                },
            },
        };

        const wrapper = await mountWithPlugins(ReputationsTab, {
            initialState: { character: { character: data } },
        });

        const badge = wrapper.find('span.rounded-full');
        expect(badge.classes()).toContain('text-amber-300');
    });

    it('uses sky color for in-progress renown factions', async () => {
        const data = {
            ...characterData,
            collections: {
                10: {
                    ...characterData.collections[10],
                    reputations: {
                        completed: 0,
                        total: 1,
                        factions: [
                            { id: 2503, name: 'Centaure maruuk', standing_name: 'Renom 15', tier: 0, value: 1200, max: 2500, raw: 37500, renown_level: 15, completed: false },
                        ],
                    },
                },
            },
        };

        const wrapper = await mountWithPlugins(ReputationsTab, {
            initialState: { character: { character: data } },
        });

        const badge = wrapper.find('span.rounded-full');
        expect(badge.classes()).toContain('text-sky-400');
    });

    it('generates correct wowhead links', async () => {
        const wrapper = await mountWithPlugins(ReputationsTab, {
            initialState: { character: { character: characterData } },
        });

        const links = wrapper.findAll('a[target="_blank"]');
        const hrefs = links.map(el => el.attributes('href'));
        expect(hrefs).toContain('https://www.wowhead.com/fr/faction=2600');
    });
});
