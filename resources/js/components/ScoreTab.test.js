import { describe, it, expect, vi } from 'vitest';
import ScoreTab from './ScoreTab.vue';
import { mountWithPlugins } from '../tests/helpers';

vi.mock('../utils/scoreCardRenderer', () => ({
    renderScoreCard: vi.fn(() => ({ width: 700, height: 430 })),
}));

const character = {
    name: 'TestChar',
    realm: 'Dalaran',
    class: 'Guerrier',
    race: 'Humain',
    level: 80,
    classId: 1,
    collections: {
        1: {
            quests: { completed: 50, total: 100 },
            achievements: { completed: 30, total: 100 },
            reputations: { completed: 10, total: 20 },
        },
    },
    mounts: [{ is_completed: true }, { is_completed: false }],
    pets: [{ is_completed: true }],
    decor: [{ is_completed: true }, { is_completed: false }, { is_completed: false }],
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

        // The score is computed from the character data. We check it renders a number and "/ 100"
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
        // Score is around 35-45 range based on the data, so rank should be Commun or Rare
        const hasRank = ['Débutant', 'Commun', 'Rare', 'Épique', 'Légendaire'].some(
            (r) => text.includes(r),
        );
        expect(hasRank).toBe(true);
    });

    it('renders 7 dimension cards', async () => {
        const wrapper = await mountWithPlugins(ScoreTab, {
            initialState: { character: { character } },
            stubs: { ShareScoreModal: true, ScoreRadar: true },
        });

        const dimensionLabels = ['Quêtes', 'Hauts-faits', 'Réputations', 'Montures', 'Mascottes', 'Décorations', 'Métiers'];
        for (const label of dimensionLabels) {
            expect(wrapper.text()).toContain(label);
        }
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
});
