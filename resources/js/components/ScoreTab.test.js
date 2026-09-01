import { describe, it, expect, vi } from 'vitest';
import ScoreTab from './ScoreTab.vue';
import { mountWithPlugins } from '../tests/helpers';

vi.mock('../utils/scoreCardRenderer', () => ({
    renderScoreCard: vi.fn(() => ({ width: 700, height: 430 })),
}));

const dimension = (key, label, completed, total, score, applicable = true) =>
    ({ key, label, completed, total, score, applicable, weight: 0.1 });

// Le score arrive désormais calculé du serveur.
const score = {
    version: 2,
    global: 42.5,
    rank: 'Commun',
    dimensions: [
        dimension('quests', 'Quêtes', 50, 100, 50),
        dimension('achievements', 'Hauts-faits', 30, 100, 30),
        dimension('reputations', 'Réputations', 10, 20, 50),
        dimension('raids', 'Raids', 0, 0, 0, false),
        dimension('mounts', 'Montures', 1, 2, 50),
        dimension('transmog', 'Garde-robe', 300, 1000, 30),
        dimension('pets', 'Mascottes', 1, 1, 100),
        dimension('decor', 'Décorations', 1, 3, 33.3),
        dimension('professions', 'Métiers', 5, 10, 50),
    ],
};

const character = {
    score,
    name: 'TestChar',
    realm: 'Dalaran',
    class: 'Guerrier',
    race: 'Humain',
    level: 80,
    classId: 1,
    collections: {
        1: {
            quests: {
                completed: 50,
                total: 100,
                zones: [{ name: 'Hurlevent', items: [
                    { id: 10, name: 'Livraison', is_completed: true },
                    { id: 11, name: 'Chasse au sanglier', is_completed: false },
                ] }],
            },
            achievements: {
                completed: 30,
                total: 100,
                categories: [{ name: 'Exploration', items: [
                    { id: 20, name: 'Explorateur', is_completed: true },
                    { id: 21, name: 'Vagabond', is_completed: false },
                ] }],
            },
            reputations: { completed: 10, total: 20 },
        },
    },
    mounts: [
        { id: 1, name: 'Cheval', source: 'Raid', wowhead_id: 100, is_completed: true },
        { id: 2, name: 'Drake', source: 'Raid', wowhead_id: 200, is_completed: false },
    ],
    pets: [{ id: 3, name: 'Chaton', source: 'Vendeur', creature_id: 300, is_completed: true }],
    decor: [
        { id: 4, name: 'Table', source: 'Craft', item_id: 400, is_completed: true },
        { id: 5, name: 'Chaise', source: 'Craft', item_id: 500, is_completed: false },
        { id: 6, name: 'Lampe', source: 'Craft', item_id: 600, is_completed: false },
    ],
    professions: [{ expansions: { 1: { completed: 5, total: 10, skill_points: 50, max_skill_points: 100 } } }],
};

describe('ScoreTab', () => {
    it('renders nothing when store.character is null', async () => {
        const wrapper = await mountWithPlugins(ScoreTab, {
            initialState: { character: { character: null } },
            stubs: { ShareScoreModal: true, ScoreRadar: true },
        });

        expect(wrapper.find('.space-y-8').exists()).toBe(false);
    });

    it('renders score heading when character has data', async () => {
        const wrapper = await mountWithPlugins(ScoreTab, {
            initialState: { character: { character } },
            stubs: { ShareScoreModal: true, ScoreRadar: true },
        });

        expect(wrapper.text()).toContain('Score de Complétion');
    });

    it('displays global score number', async () => {
        const wrapper = await mountWithPlugins(ScoreTab, {
            initialState: { character: { character } },
            stubs: { ShareScoreModal: true, ScoreRadar: true },
        });

        expect(wrapper.text()).toContain('42.5');
        expect(wrapper.text()).toContain('/ 100');
        // The global score should be a number present in the text
        const scoreEl = wrapper.find('.tabular-nums');
        expect(scoreEl.exists()).toBe(true);
    });

    it('shows rank badge', async () => {
        const wrapper = await mountWithPlugins(ScoreTab, {
            initialState: { character: { character } },
            stubs: { ShareScoreModal: true, ScoreRadar: true },
        });

        const text = wrapper.text();
        expect(text).toContain('Commun');
    });

    it('renders a card per dimension, including the new ones', async () => {
        const wrapper = await mountWithPlugins(ScoreTab, {
            initialState: { character: { character } },
            stubs: { ShareScoreModal: true, ScoreRadar: true },
        });

        const labels = ['Quêtes', 'Hauts-faits', 'Réputations', 'Raids', 'Montures', 'Garde-robe', 'Mascottes', 'Décorations', 'Métiers'];
        for (const label of labels) {
            expect(wrapper.text()).toContain(label);
        }
    });

    it('marks a dimension without data as non applicable', async () => {
        const wrapper = await mountWithPlugins(ScoreTab, {
            initialState: { character: { character } },
            stubs: { ShareScoreModal: true, ScoreRadar: true },
        });

        expect(wrapper.text()).toContain('Non applicable');
    });

    it('keeps non-applicable dimensions off the radar', async () => {
        const wrapper = await mountWithPlugins(ScoreTab, {
            initialState: { character: { character } },
            stubs: { ShareScoreModal: true, ScoreRadar: true },
        });

        // Huit dimensions applicables sur neuf : les raids sont exclus.
        expect(wrapper.text()).toContain('8 dimensions');
        expect(wrapper.findComponent({ name: 'ScoreRadar' }).props('axes')).toHaveLength(8);
    });

    it('shows the formula version', async () => {
        const wrapper = await mountWithPlugins(ScoreTab, {
            initialState: { character: { character } },
            stubs: { ShareScoreModal: true, ScoreRadar: true },
        });

        expect(wrapper.text()).toContain('formule v2');
    });

    it('shows "Partager mon score" button', async () => {
        const wrapper = await mountWithPlugins(ScoreTab, {
            initialState: { character: { character } },
            stubs: { ShareScoreModal: true, ScoreRadar: true },
        });

        expect(wrapper.text()).toContain('Partager mon score');
    });

    it('displays progression info on dimension cards', async () => {
        const wrapper = await mountWithPlugins(ScoreTab, {
            initialState: { character: { character } },
            stubs: { ShareScoreModal: true, ScoreRadar: true },
        });

        // Should show "Détail par dimension" section heading
        expect(wrapper.text()).toContain('Détail par dimension');
    });

    it('liste les catégories les plus proches de la complétion', async () => {
        const wrapper = await mountWithPlugins(ScoreTab, {
            initialState: { character: { character } },
            stubs: { ShareScoreModal: true, ScoreRadar: true },
        });

        const text = wrapper.text();
        expect(text).toContain('Il vous reste...');
        // Une recommandation par source de collection et par groupe de contenu.
        expect(text).toContain('Raid');
        expect(text).toContain('Craft');
        expect(text).toContain('Exploration');
        expect(text).toContain('Hurlevent');
        // Les mascottes sont complètes : leur source ne donne aucune recommandation.
        expect(text).not.toContain('Vendeur');
    });

    it('déplie les items manquants et pointe vers Wowhead', async () => {
        const wrapper = await mountWithPlugins(ScoreTab, {
            initialState: { character: { character } },
            stubs: { ShareScoreModal: true, ScoreRadar: true },
        });

        await wrapper.findAll('.cursor-pointer')[0].trigger('click');

        const links = wrapper.findAll('a[href^="https://www.wowhead.com"]');
        expect(links.length).toBeGreaterThan(0);
        expect(links.some(a => a.attributes('href').includes('wowhead.com/fr/'))).toBe(true);
    });

    it('replie une recommandation déjà ouverte', async () => {
        const wrapper = await mountWithPlugins(ScoreTab, {
            initialState: { character: { character } },
            stubs: { ShareScoreModal: true, ScoreRadar: true },
        });

        const header = wrapper.findAll('.cursor-pointer')[0];
        await header.trigger('click');
        expect(wrapper.findAll('a[href^="https://www.wowhead.com"]').length).toBeGreaterThan(0);

        await header.trigger('click');
        expect(wrapper.findAll('a[href^="https://www.wowhead.com"]')).toHaveLength(0);
    });
});
